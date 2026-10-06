<?php

namespace App\Models;

// cartao de fidelidade = vinculo entre um lojista e um cliente, com o saldo de pontos.
// toda movimentacao de pontos passa por aqui e fica registrada no points_log.
class LoyaltyCardModel {
    private $db;

    function __construct($db) {
        $this->db = $db;
    }

    // devolve o id do cartao do cliente nesta loja, criando se ainda nao existir.
    // o nome e o que o cliente informou a ESTA loja. com $consentVersion, grava o consentimento agora.
    // o ON DUPLICATE KEY com LAST_INSERT_ID(id) faz o lastInsertId() devolver o id existente
    // (cartao que ja existe nao tem o nome nem o consentimento trocados).
    public function findOrCreate($merchantId, $customerId, ?string $customerName = null, ?string $consentVersion = null) {
        $sql = 'INSERT INTO loyalty_cards (merchant_id, customer_id, customer_name, consent_at, consent_version)
                VALUES (:merchant_id, :customer_id, :customer_name,
                        CASE WHEN :v1 IS NULL THEN NULL ELSE CURRENT_TIMESTAMP END, :v2)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':merchant_id'   => $merchantId,
            ':customer_id'   => $customerId,
            ':customer_name' => $customerName,
            ':v1'            => $consentVersion,
            ':v2'            => $consentVersion,
        ]);

        return (int)$this->db->lastInsertId();
    }

    // cartao do cliente (pelo telefone) nesta loja. cartao anonimizado nao tem mais telefone, entao nao aparece.
    public function findByMerchantAndPhone($merchantId, $phone) {
        $sql = 'SELECT lc.id, lc.current_points, lc.total_accumulated, lc.customer_name, lc.consent_at, lc.consent_version, c.phone
                FROM loyalty_cards lc
                JOIN customers c ON c.id = lc.customer_id
                WHERE lc.merchant_id = :merchant_id AND c.phone = :phone';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':merchant_id' => $merchantId,
            ':phone'       => $phone,
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // soma pontos no cartao e registra no historico (tudo ou nada). devolve o id do lancamento.
    public function addPoints($cardId, $points, $description): int {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE loyalty_cards
                 SET current_points = current_points + :p1,
                     total_accumulated = total_accumulated + :p2,
                     last_use_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([':p1' => $points, ':p2' => $points, ':id' => $cardId]);

            $logId = $this->log($cardId, 'earn', $points, $description, null);

            $this->db->commit();
            return $logId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // estorno de um lancamento de pontos (digitou 500 em vez de 50) ou de um resgate feito por engano (task 44).
    // nada e apagado: o estorno vira uma linha nova 'reversal' que aponta pra movimentacao estornada.
    // - lancamento (earn): saldo e total acumulado voltam ao que eram, e so quando o saldo atual cobre os pontos
    //   (se o cliente ja gastou, o saldo ficaria negativo).
    // - resgate (redeem): os pontos voltam ao saldo; o total acumulado nao muda (o resgate nao mexeu nele).
    // so vale para movimentacao desta loja, feita nas ultimas REVERSAL_WINDOW_HOURS horas e ainda nao estornada.
    // devolve 'ok', 'not_found', 'already', 'expired' ou 'insufficient'.
    public const REVERSAL_WINDOW_HOURS = 24;

    public function reverse($logId, $merchantId): string {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT pl.id, pl.card_id, pl.type, pl.quantity, pl.description,
                        pl.created_at >= NOW() - INTERVAL " . self::REVERSAL_WINDOW_HOURS . " HOUR AS recent,
                        EXISTS (SELECT 1 FROM points_log r WHERE r.reverses_id = pl.id) AS reversed
                 FROM points_log pl
                 JOIN loyalty_cards lc ON lc.id = pl.card_id
                 WHERE pl.id = :id AND lc.merchant_id = :merchant_id AND pl.type IN ('earn', 'redeem')
                   AND lc.anonymized_at IS NULL"
            );
            $stmt->execute([':id' => $logId, ':merchant_id' => $merchantId]);
            $entry = $stmt->fetch(\PDO::FETCH_ASSOC);

            $problem = match (true) {
                !$entry               => 'not_found',
                (bool)$entry['reversed'] => 'already',
                !$entry['recent']     => 'expired',
                default               => null,
            };
            if ($problem) {
                $this->db->rollBack();
                return $problem;
            }

            // trava o cartao: um resgate ao mesmo tempo nao consegue gastar os pontos que estao sendo estornados
            $stmt = $this->db->prepare('SELECT current_points FROM loyalty_cards WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $entry['card_id']]);
            $quantity = (int)$entry['quantity'];

            if ($entry['type'] === 'redeem') {
                $this->db->prepare(
                    'UPDATE loyalty_cards SET current_points = current_points + :p1, last_use_at = CURRENT_TIMESTAMP
                     WHERE id = :id'
                )->execute([':p1' => $quantity, ':id' => $entry['card_id']]);
            } else {
                if ((int)$stmt->fetchColumn() < $quantity) {
                    $this->db->rollBack();
                    return 'insufficient';
                }

                $this->db->prepare(
                    'UPDATE loyalty_cards
                     SET current_points = current_points - :p1, total_accumulated = total_accumulated - :p2,
                         last_use_at = CURRENT_TIMESTAMP
                     WHERE id = :id'
                )->execute([':p1' => $quantity, ':p2' => $quantity, ':id' => $entry['card_id']]);
            }

            $description = mb_substr('Estorno: ' . $entry['description'], 0, 255);
            $this->log($entry['card_id'], 'reversal', $quantity, $description, null, (int)$entry['id']);

            $this->db->commit();
            return 'ok';
        } catch (\PDOException $e) {
            $this->db->rollBack();
            // dois estornos do mesmo lancamento ao mesmo tempo: o UNIQUE barra o segundo
            if (($e->errorInfo[1] ?? null) === 1062) {
                return 'already';
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // debita o custo do premio. devolve false se o saldo nao for suficiente.
    // o FOR UPDATE trava a linha do cartao ate o commit, entao dois resgates
    // simultaneos nao conseguem gastar o mesmo saldo.
    public function redeem($cardId, $reward) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT current_points FROM loyalty_cards WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $cardId]);
            $current = (int)$stmt->fetchColumn();

            $cost = (int)$reward['points_cost'];
            if ($current < $cost) {
                $this->db->rollBack();
                return false;
            }

            $stmt = $this->db->prepare(
                'UPDATE loyalty_cards
                 SET current_points = current_points - :cost, last_use_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([':cost' => $cost, ':id' => $cardId]);

            $this->log($cardId, 'redeem', $cost, 'Resgate: ' . $reward['name'], $reward['id']);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // filtro de busca por nome ou telefone. so numero (com ou sem mascara) busca no telefone;
    // qualquer outra coisa busca no nome. % e _ do que foi digitado valem como letra, nao como coringa.
    private function searchClause(string $search): array {
        $search = trim($search);
        if ($search === '') {
            return ['', []];
        }

        if (preg_match('/^[\d\s().+-]+$/', $search)) {
            $digits = preg_replace('/\D/', '', $search);
            return $digits === '' ? ['', []] : [' AND c.phone LIKE :q', [':q' => '%' . $digits . '%']];
        }

        // "\x5c" e a barra invertida (o escape padrao do LIKE no MySQL)
        $escaped = str_replace(["\x5c", '%', '_'], ["\x5c\x5c", "\x5c%", "\x5c_"], $search);
        return [' AND lc.customer_name LIKE :q', [':q' => '%' . $escaped . '%']];
    }

    public function countByMerchant($merchantId, string $search = ''): int {
        [$where, $params] = $this->searchClause($search);
        $sql = 'SELECT COUNT(*) FROM loyalty_cards lc
                JOIN customers c ON c.id = lc.customer_id
                WHERE lc.merchant_id = :merchant_id' . $where;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':merchant_id' => $merchantId] + $params);

        return (int)$stmt->fetchColumn();
    }

    // uma pagina de clientes da loja (com busca opcional), do uso mais recente pro mais antigo
    public function searchByMerchant($merchantId, string $search, int $limit, int $offset) {
        [$where, $params] = $this->searchClause($search);
        $sql = 'SELECT lc.customer_name AS name, c.phone, lc.current_points, lc.total_accumulated, lc.last_use_at
                FROM loyalty_cards lc
                JOIN customers c ON c.id = lc.customer_id
                WHERE lc.merchant_id = :merchant_id' . $where . '
                ORDER BY lc.last_use_at DESC, lc.id DESC
                LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':merchant_id' => $merchantId] + $params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // registra o consentimento de um cartao antigo (criado antes de o consentimento ser gravado)
    public function recordConsent($cardId, string $version) {
        $stmt = $this->db->prepare(
            'UPDATE loyalty_cards SET consent_at = CURRENT_TIMESTAMP, consent_version = :version
             WHERE id = :id AND anonymized_at IS NULL'
        );
        $stmt->execute([':version' => $version, ':id' => $cardId]);
    }

    // exclusao a pedido do cliente (LGPD), tudo ou nada:
    // - o cartao perde nome, telefone (customer_id), consentimento e saldo; fica so como numero nos relatorios
    // - as descricoes de ganho (texto livre do lojista, pode ter dado pessoal) sao trocadas; os resgates
    //   ficam ("Resgate: <premio>" nao identifica ninguem)
    // - o telefone (customers) e apagado quando nao sobra cartao dele em nenhuma loja
    // devolve false se o cartao nao for desta loja ou ja estiver anonimizado.
    public function anonymize($cardId, $merchantId): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT customer_id FROM loyalty_cards
                 WHERE id = :id AND merchant_id = :merchant_id AND anonymized_at IS NULL
                 FOR UPDATE'
            );
            $stmt->execute([':id' => $cardId, ':merchant_id' => $merchantId]);
            $customerId = $stmt->fetchColumn();

            if ($customerId === false) {
                $this->db->rollBack();
                return false;
            }

            $this->db->prepare(
                'UPDATE loyalty_cards
                 SET customer_id = NULL, customer_name = NULL, consent_at = NULL, consent_version = NULL,
                     current_points = 0, anonymized_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            )->execute([':id' => $cardId]);

            $this->db->prepare(
                "UPDATE points_log SET description = 'Cliente excluído' WHERE card_id = :id AND type IN ('earn', 'reversal')"
            )->execute([':id' => $cardId]);

            if ($customerId !== null) {
                // so apaga o telefone se nenhuma outra loja ainda tem cartao com ele
                $this->db->prepare(
                    'DELETE FROM customers
                     WHERE id = :c1 AND NOT EXISTS (SELECT 1 FROM loyalty_cards WHERE customer_id = :c2)'
                )->execute([':c1' => $customerId, ':c2' => $customerId]);
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // grava uma linha no historico e devolve o id dela
    private function log($cardId, $type, $quantity, $description, $rewardId, $reversesId = null): int {
        $stmt = $this->db->prepare(
            'INSERT INTO points_log (card_id, type, quantity, description, reward_id, reverses_id, ip_address)
             VALUES (:card_id, :type, :quantity, :description, :reward_id, :reverses_id, :ip)'
        );
        $stmt->execute([
            ':card_id'     => $cardId,
            ':type'        => $type,
            ':quantity'    => $quantity,
            ':description' => $description,
            ':reward_id'   => $rewardId,
            ':reverses_id' => $reversesId,
            ':ip'          => isset($_SERVER['REMOTE_ADDR']) ? \App\Support\RateLimiter::clientIp() : null,
        ]);

        return (int)$this->db->lastInsertId();
    }
}
