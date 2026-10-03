<?php

namespace App\Models;

// catalogo de premios. toda consulta filtra por merchant_id,
// assim um lojista nunca enxerga nem altera premio de outro.
class RewardModel {
    private $db;

    function __construct($db) {
        $this->db = $db;
    }

    public function listByMerchant($merchantId, $onlyActive = false) {
        $sql = 'SELECT id, name, description, points_cost, active
                FROM rewards
                WHERE merchant_id = :merchant_id' . ($onlyActive ? ' AND active = 1' : '') . '
                ORDER BY active DESC, points_cost ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':merchant_id' => $merchantId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findActiveForMerchant($rewardId, $merchantId) {
        $sql = 'SELECT id, name, points_cost FROM rewards
                WHERE id = :id AND merchant_id = :merchant_id AND active = 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id'          => $rewardId,
            ':merchant_id' => $merchantId,
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function create($merchantId, $name, $description, $pointsCost) {
        $sql = 'INSERT INTO rewards (merchant_id, name, description, points_cost)
                VALUES (:merchant_id, :name, :description, :points_cost)';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':merchant_id' => $merchantId,
            ':name'        => $name,
            ':description' => $description,
            ':points_cost' => $pointsCost,
        ]);
    }

    public function toggleActive($rewardId, $merchantId) {
        $sql = 'UPDATE rewards SET active = 1 - active WHERE id = :id AND merchant_id = :merchant_id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id'          => $rewardId,
            ':merchant_id' => $merchantId,
        ]);
    }

    // premio da loja (ativo ou nao), para a tela de edicao
    public function findForMerchant($rewardId, $merchantId) {
        $sql = 'SELECT id, name, description, points_cost, active FROM rewards
                WHERE id = :id AND merchant_id = :merchant_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id'          => $rewardId,
            ':merchant_id' => $merchantId,
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // muda nome, descricao e custo. resgates antigos ficam como foram (o log guarda o nome da epoca na descricao).
    // devolve false se o premio nao for desta loja.
    public function update($rewardId, $merchantId, $name, $description, $pointsCost) {
        $sql = 'UPDATE rewards SET name = :name, description = :description, points_cost = :points_cost
                WHERE id = :id AND merchant_id = :merchant_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name'        => $name,
            ':description' => $description,
            ':points_cost' => $pointsCost,
            ':id'          => $rewardId,
            ':merchant_id' => $merchantId,
        ]);

        return $this->findForMerchant($rewardId, $merchantId) !== false;
    }

    // premio ja resgatado nao pode sumir (o historico aponta pra ele): so desativa.
    // sem resgates, apaga de verdade. devolve 'deleted', 'deactivated' ou null (nao e desta loja).
    public function deleteOrDeactivate($rewardId, $merchantId) {
        if (!$this->findForMerchant($rewardId, $merchantId)) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM points_log WHERE reward_id = :id');
        $stmt->execute([':id' => $rewardId]);

        if ((int)$stmt->fetchColumn() > 0) {
            $stmt = $this->db->prepare('UPDATE rewards SET active = 0 WHERE id = :id AND merchant_id = :merchant_id');
            $stmt->execute([':id' => $rewardId, ':merchant_id' => $merchantId]);
            return 'deactivated';
        }

        $stmt = $this->db->prepare('DELETE FROM rewards WHERE id = :id AND merchant_id = :merchant_id');
        $stmt->execute([':id' => $rewardId, ':merchant_id' => $merchantId]);
        return 'deleted';
    }
}
