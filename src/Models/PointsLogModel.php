<?php

namespace App\Models;

// leitura do historico de pontos (a escrita fica no LoyaltyCardModel)
class PointsLogModel {
    private $db;

    function __construct($db) {
        $this->db = $db;
    }

    public function recentByMerchant($merchantId, $limit = 10) {
        $sql = 'SELECT pl.type, pl.quantity, pl.description, pl.created_at, c.name AS customer_name, c.phone
                FROM points_log pl
                JOIN loyalty_cards lc ON lc.id = pl.card_id
                JOIN customers c ON c.id = lc.customer_id
                WHERE lc.merchant_id = :merchant_id
                ORDER BY pl.created_at DESC, pl.id DESC
                LIMIT ' . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':merchant_id' => $merchantId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // numeros do dashboard
    public function statsByMerchant($merchantId) {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM loyalty_cards WHERE merchant_id = :m1) AS customers,
                    (SELECT COALESCE(SUM(pl.quantity), 0) FROM points_log pl
                        JOIN loyalty_cards lc ON lc.id = pl.card_id
                        WHERE lc.merchant_id = :m2 AND pl.type = 'earn') AS points_issued,
                    (SELECT COUNT(*) FROM points_log pl
                        JOIN loyalty_cards lc ON lc.id = pl.card_id
                        WHERE lc.merchant_id = :m3 AND pl.type = 'redeem') AS redemptions";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':m1' => $merchantId, ':m2' => $merchantId, ':m3' => $merchantId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
