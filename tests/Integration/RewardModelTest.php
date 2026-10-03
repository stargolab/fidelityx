<?php

namespace Tests\Integration;

use App\Models\LoyaltyCardModel;
use App\Models\RewardModel;
use Tests\Support\DatabaseTestCase;

final class RewardModelTest extends DatabaseTestCase {
    public function testEditaNomeDescricaoECusto(): void {
        $merchant = $this->createMerchant();
        $reward = $this->createReward($merchant, 'Cafe', 20);
        $model = new RewardModel($this->db);

        $this->assertTrue($model->update($reward['id'], $merchant, 'Cafe grande', 'Com leite', 25));

        $saved = $model->findForMerchant($reward['id'], $merchant);
        $this->assertSame('Cafe grande', $saved['name']);
        $this->assertSame('Com leite', $saved['description']);
        $this->assertSame(25, (int)$saved['points_cost']);
    }

    public function testNaoEditaPremioDeOutraLoja(): void {
        $dona = $this->createMerchant('dona@teste.test');
        $outra = $this->createMerchant('outra@teste.test');
        $reward = $this->createReward($dona, 'Cafe', 20);
        $model = new RewardModel($this->db);

        $this->assertFalse($model->update($reward['id'], $outra, 'Invadido', null, 1));
        $this->assertNull($model->deleteOrDeactivate($reward['id'], $outra));

        $this->assertSame('Cafe', $model->findForMerchant($reward['id'], $dona)['name']);
    }

    public function testPremioSemResgateEApagadoDeVerdade(): void {
        $merchant = $this->createMerchant();
        $reward = $this->createReward($merchant, 'Cafe', 20);

        $this->assertSame('deleted', (new RewardModel($this->db))->deleteOrDeactivate($reward['id'], $merchant));
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM rewards'));
    }

    public function testPremioJaResgatadoSoEDesativadoEHistoricoFica(): void {
        $merchant = $this->createMerchant();
        $reward = $this->createReward($merchant, 'Cafe', 20);

        $this->db->exec("INSERT INTO customers (name, phone) VALUES ('Bia', '11911110001')");
        $cards = new LoyaltyCardModel($this->db);
        $cardId = $cards->findOrCreate($merchant, (int)$this->db->lastInsertId());
        $cards->addPoints($cardId, 30, 'Compra');
        $this->assertTrue($cards->redeem($cardId, $reward));

        $this->assertSame('deactivated', (new RewardModel($this->db))->deleteOrDeactivate($reward['id'], $merchant));

        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM rewards WHERE id = :id AND active = 0', [':id' => $reward['id']]));
        $this->assertSame(
            $reward['id'],
            (int)$this->scalar("SELECT reward_id FROM points_log WHERE type = 'redeem'"),
            'o resgate continua apontando pro premio'
        );
    }
}
