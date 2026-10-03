<?php

namespace App\Models;

// leitura do historico de pontos (a escrita fica no LoyaltyCardModel)
class PointsLogModel {
    private $db;

    function __construct($db) {
        $this->db = $db;
    }

    public function recentByMerchant($merchantId, $limit = 10) {
        return $this->pageByMerchant($merchantId, (int)$limit, 0);
    }

    // uma pagina do historico de movimentacoes da loja, da mais recente pra mais antiga.
    // LEFT JOIN: cartao anonimizado nao tem cliente, mas a movimentacao continua no historico (nome e telefone NULL)
    public function pageByMerchant($merchantId, int $limit, int $offset) {
        $sql = 'SELECT pl.type, pl.quantity, pl.description, pl.created_at, lc.customer_name, c.phone
                FROM points_log pl
                JOIN loyalty_cards lc ON lc.id = pl.card_id
                LEFT JOIN customers c ON c.id = lc.customer_id
                WHERE lc.merchant_id = :merchant_id
                ORDER BY pl.created_at DESC, pl.id DESC
                LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':merchant_id' => $merchantId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // extrato de um cartao: movimentacoes do cliente nesta loja, da mais recente pra mais antiga
    public function pageByCard($cardId, int $limit, int $offset) {
        $sql = 'SELECT type, quantity, description, created_at
                FROM points_log
                WHERE card_id = :card_id
                ORDER BY created_at DESC, id DESC
                LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':card_id' => $cardId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countByCard($cardId): int {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM points_log WHERE card_id = :card_id');
        $stmt->execute([':card_id' => $cardId]);

        return (int)$stmt->fetchColumn();
    }

    public function countByMerchant($merchantId): int {
        $sql = 'SELECT COUNT(*) FROM points_log pl
                JOIN loyalty_cards lc ON lc.id = pl.card_id
                WHERE lc.merchant_id = :merchant_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':merchant_id' => $merchantId]);

        return (int)$stmt->fetchColumn();
    }

    // numeros do dashboard
    public function statsByMerchant($merchantId) {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM loyalty_cards WHERE merchant_id = :m1 AND anonymized_at IS NULL) AS customers,
                    (SELECT COALESCE(SUM(pl.quantity), 0) FROM points_log pl
                        JOIN loyalty_cards lc ON lc.id = pl.card_id
                        WHERE lc.merchant_id = :m2 AND pl.type = 'earn') AS points_issued,
                    (SELECT COUNT(*) FROM points_log pl
                        JOIN loyalty_cards lc ON lc.id = pl.card_id
                        WHERE lc.merchant_id = :m3 AND pl.type = 'redeem') AS redemptions,
                    (SELECT COALESCE(SUM(current_points), 0) FROM loyalty_cards WHERE merchant_id = :m4) AS points_balance";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':m1' => $merchantId, ':m2' => $merchantId, ':m3' => $merchantId, ':m4' => $merchantId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
