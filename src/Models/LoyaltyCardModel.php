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
    // o ON DUPLICATE KEY com LAST_INSERT_ID(id) faz o lastInsertId() devolver o id existente.
    public function findOrCreate($merchantId, $customerId) {
        $sql = 'INSERT INTO loyalty_cards (merchant_id, customer_id) VALUES (:merchant_id, :customer_id)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':merchant_id' => $merchantId,
            ':customer_id' => $customerId,
        ]);

        return (int)$this->db->lastInsertId();
    }

    // cartao do cliente (pelo telefone) nesta loja
    public function findByMerchantAndPhone($merchantId, $phone) {
        $sql = 'SELECT lc.id, lc.current_points, lc.total_accumulated, c.name AS customer_name, c.phone
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

    // soma pontos no cartao e registra no historico (tudo ou nada)
    public function addPoints($cardId, $points, $description) {
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

            $this->log($cardId, 'earn', $points, $description, null);

            $this->db->commit();
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

    // clientes da loja com saldo, do uso mais recente pro mais antigo
    public function listByMerchant($merchantId) {
        $sql = 'SELECT c.name, c.phone, lc.current_points, lc.total_accumulated, lc.last_use_at
                FROM loyalty_cards lc
                JOIN customers c ON c.id = lc.customer_id
                WHERE lc.merchant_id = :merchant_id
                ORDER BY lc.last_use_at DESC, lc.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':merchant_id' => $merchantId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // consulta publica: cartoes do cliente em todas as lojas
    public function listByPhone($phone) {
        $sql = 'SELECT lc.merchant_id, lc.current_points, m.store_name, c.name AS customer_name
                FROM loyalty_cards lc
                JOIN customers c ON c.id = lc.customer_id
                JOIN merchants m ON m.id = lc.merchant_id
                WHERE c.phone = :phone AND m.status = \'active\'
                ORDER BY m.store_name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':phone' => $phone]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function log($cardId, $type, $quantity, $description, $rewardId) {
        $stmt = $this->db->prepare(
            'INSERT INTO points_log (card_id, type, quantity, description, reward_id, ip_address)
             VALUES (:card_id, :type, :quantity, :description, :reward_id, :ip)'
        );
        $stmt->execute([
            ':card_id'     => $cardId,
            ':type'        => $type,
            ':quantity'    => $quantity,
            ':description' => $description,
            ':reward_id'   => $rewardId,
            ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
