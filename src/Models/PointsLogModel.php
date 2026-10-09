<?php

namespace App\Models;

use App\Support\Period;

// leitura do historico de pontos (a escrita fica no LoyaltyCardModel)
class PointsLogModel {
    private $db;

    function __construct($db) {
        $this->db = $db;
    }

    public function recentByMerchant($merchantId, $limit = 10) {
        return $this->pageByMerchant($merchantId, (int)$limit, 0);
    }

    // filtro de periodo (task 59) sobre a coluna $column. $tag deixa os placeholders unicos
    // (o PDO sem emulacao nao aceita o mesmo placeholder duas vezes na query)
    private function periodClause(?Period $period, string $column, string $tag = ''): array {
        if ($period === null || !$period->isFiltered()) {
            return ['', []];
        }
        return [
            " AND $column >= :from$tag AND $column < :to$tag",
            [":from$tag" => $period->from, ":to$tag" => $period->to],
        ];
    }

    // uma pagina do historico de movimentacoes da loja, da mais recente pra mais antiga (opcionalmente so do periodo).
    // LEFT JOIN: cartao anonimizado nao tem cliente, mas a movimentacao continua no historico (nome e telefone NULL)
    public function pageByMerchant($merchantId, int $limit, int $offset, ?Period $period = null) {
        [$where, $params] = $this->periodClause($period, 'pl.created_at');
        $sql = 'SELECT pl.type, pl.quantity, pl.description, pl.created_at, lc.customer_name, c.phone,
                       orig.type AS reversed_type
                FROM points_log pl
                JOIN loyalty_cards lc ON lc.id = pl.card_id
                LEFT JOIN customers c ON c.id = lc.customer_id
                LEFT JOIN points_log orig ON orig.id = pl.reverses_id
                WHERE lc.merchant_id = :merchant_id' . $where . '
                ORDER BY pl.created_at DESC, pl.id DESC
                LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':merchant_id' => $merchantId] + $params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // extrato de um cartao: movimentacoes do cliente nesta loja, da mais recente pra mais antiga
    public function pageByCard($cardId, int $limit, int $offset) {
        // can_reverse: lancamento ou resgate que ainda pode ser estornado (o model confere tudo de novo no POST).
        // reversed_type: num estorno, o tipo do que foi estornado (estorno de resgate devolve pontos)
        $sql = 'SELECT pl.id, pl.type, pl.quantity, pl.description, pl.created_at,
                       (SELECT orig.type FROM points_log orig WHERE orig.id = pl.reverses_id) AS reversed_type,
                       (pl.type IN (\'earn\', \'redeem\')
                        AND pl.created_at >= NOW() - INTERVAL ' . LoyaltyCardModel::REVERSAL_WINDOW_HOURS . ' HOUR
                        AND NOT EXISTS (SELECT 1 FROM points_log r WHERE r.reverses_id = pl.id)) AS can_reverse
                FROM points_log pl
                WHERE pl.card_id = :card_id
                ORDER BY pl.created_at DESC, pl.id DESC
                LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':card_id' => $cardId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // lancamento (earn) deste cartao, feito ha no maximo $maxAgeSeconds e ainda nao estornado; false se nao houver
    public function findFreshEarn($logId, $cardId, int $maxAgeSeconds) {
        $sql = "SELECT id, quantity FROM points_log pl
                WHERE pl.id = :id AND pl.card_id = :card_id AND pl.type = 'earn'
                  AND pl.created_at >= NOW() - INTERVAL " . (int)$maxAgeSeconds . " SECOND
                  AND NOT EXISTS (SELECT 1 FROM points_log r WHERE r.reverses_id = pl.id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $logId, ':card_id' => $cardId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // tipo da movimentacao, se ela for deste cartao; null se nao for (o estorno confere antes,
    // pra nao estornar movimentacao de outro cliente)
    public function typeForCard(int $logId, int $cardId): ?string {
        $stmt = $this->db->prepare('SELECT type FROM points_log WHERE id = :id AND card_id = :card_id');
        $stmt->execute([':id' => $logId, ':card_id' => $cardId]);
        $type = $stmt->fetchColumn();
        return $type === false ? null : $type;
    }

    public function countByCard($cardId): int {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM points_log WHERE card_id = :card_id');
        $stmt->execute([':card_id' => $cardId]);

        return (int)$stmt->fetchColumn();
    }

    public function countByMerchant($merchantId, ?Period $period = null): int {
        [$where, $params] = $this->periodClause($period, 'pl.created_at');
        $sql = 'SELECT COUNT(*) FROM points_log pl
                JOIN loyalty_cards lc ON lc.id = pl.card_id
                WHERE lc.merchant_id = :merchant_id' . $where;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':merchant_id' => $merchantId] + $params);

        return (int)$stmt->fetchColumn();
    }

    // numeros do dashboard. estorno de lancamento desconta dos pontos emitidos; resgate estornado
    // (task 44) nao conta como resgate, e o estorno dele nao mexe nos pontos emitidos.
    // com periodo (task 59): clientes = cartoes novos no periodo; pontos emitidos e resgates = movimentacoes
    // do periodo (o estorno conta na data dele). o saldo em circulacao e sempre o de agora.
    public function statsByMerchant($merchantId, ?Period $period = null) {
        [$cardsWhere, $cardsParams] = $this->periodClause($period, 'created_at', 'c');
        [$issuedWhere, $issuedParams] = $this->periodClause($period, 'pl.created_at', 'i');
        [$redeemWhere, $redeemParams] = $this->periodClause($period, 'pl.created_at', 'r');
        $sql = "SELECT
                    (SELECT COUNT(*) FROM loyalty_cards WHERE merchant_id = :m1 AND anonymized_at IS NULL$cardsWhere) AS customers,
                    (SELECT COALESCE(SUM(CASE WHEN pl.type = 'earn' THEN pl.quantity ELSE -pl.quantity END), 0) FROM points_log pl
                        JOIN loyalty_cards lc ON lc.id = pl.card_id
                        LEFT JOIN points_log orig ON orig.id = pl.reverses_id
                        WHERE lc.merchant_id = :m2
                          AND (pl.type = 'earn' OR (pl.type = 'reversal' AND orig.type = 'earn'))$issuedWhere) AS points_issued,
                    (SELECT COUNT(*) FROM points_log pl
                        JOIN loyalty_cards lc ON lc.id = pl.card_id
                        WHERE lc.merchant_id = :m3 AND pl.type = 'redeem'
                          AND NOT EXISTS (SELECT 1 FROM points_log r WHERE r.reverses_id = pl.id)$redeemWhere) AS redemptions,
                    (SELECT COALESCE(SUM(current_points), 0) FROM loyalty_cards WHERE merchant_id = :m4) AS points_balance";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':m1' => $merchantId, ':m2' => $merchantId, ':m3' => $merchantId, ':m4' => $merchantId]
            + $cardsParams + $issuedParams + $redeemParams);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
