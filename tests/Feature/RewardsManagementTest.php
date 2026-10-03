<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// editar e excluir premio pelo painel
final class RewardsManagementTest extends HttpTestCase {
    private function rewardField(int $id, string $field) {
        return $this->scalar("SELECT $field FROM rewards WHERE id = :id", [':id' => $id]);
    }

    public function testEditaPremioPeloFormulario(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $reward = $this->createReward($merchant, 'Cafe', 20);
        $this->loginAs('loja@teste.test');
        $form = 'merchant/reward-edit&id=' . $reward['id'];

        $this->get($form);
        $this->assertStringContainsString('value="Cafe"', $this->lastBody);

        [, $location] = $this->post(
            'merchant/reward-edit',
            ['reward_id' => $reward['id'], 'name' => 'Cafe grande', 'points_cost' => 25, 'description' => ''],
            true,
            $form
        );
        $this->assertSame('merchant/rewards&success=premio_editado', $location);
        $this->assertSame('Cafe grande', $this->rewardField($reward['id'], 'name'));
        $this->assertSame(25, (int)$this->rewardField($reward['id'], 'points_cost'));
    }

    public function testEdicaoInvalidaVoltaParaOFormulario(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $reward = $this->createReward($merchant, 'Cafe', 20);
        $this->loginAs('loja@teste.test');
        $form = 'merchant/reward-edit&id=' . $reward['id'];

        [, $location] = $this->post(
            'merchant/reward-edit',
            ['reward_id' => $reward['id'], 'name' => '', 'points_cost' => 0],
            true,
            $form
        );
        $this->assertSame($form . '&error=campos_invalidos', $location);
        $this->assertSame('Cafe', $this->rewardField($reward['id'], 'name'));
    }

    public function testLojaNaoEditaNemExcluiPremioDeOutra(): void {
        $dona = $this->createMerchant('dona@teste.test');
        $this->createMerchant('outra@teste.test');
        $reward = $this->createReward($dona, 'Cafe', 20);
        $this->loginAs('outra@teste.test');

        [, $location] = $this->get('merchant/reward-edit&id=' . $reward['id']);
        $this->assertSame('merchant/rewards&error=premio_invalido', $location);

        [, $location] = $this->post(
            'merchant/reward-edit',
            ['reward_id' => $reward['id'], 'name' => 'Invadido', 'points_cost' => 1],
            true,
            'merchant/rewards'
        );
        $this->assertSame('merchant/rewards&error=premio_invalido', $location);

        [, $location] = $this->post('merchant/rewards', ['action' => 'delete', 'reward_id' => $reward['id']]);
        $this->assertSame('merchant/rewards&error=premio_invalido', $location);

        $this->assertSame('Cafe', $this->rewardField($reward['id'], 'name'));
    }

    public function testExcluirApagaOuSoDesativaSeJaFoiResgatado(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $livre = $this->createReward($merchant, 'Livre', 10);
        $usado = $this->createReward($merchant, 'Usado', 10);
        $this->loginAs('loja@teste.test');

        // um resgate do "Usado"
        $this->db->exec("INSERT INTO customers (phone) VALUES ('11911110001')");
        $this->db->exec("INSERT INTO loyalty_cards (merchant_id, customer_id, customer_name, current_points) SELECT $merchant, id, 'Bia', 50 FROM customers");
        $this->post('merchant/customer', ['action' => 'redeem', 'phone' => '11911110001', 'reward_id' => $usado['id']], true, 'merchant/customer&phone=11911110001');

        [, $location] = $this->post('merchant/rewards', ['action' => 'delete', 'reward_id' => $livre['id']]);
        $this->assertSame('merchant/rewards&success=premio_excluido', $location);
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM rewards WHERE id = :id', [':id' => $livre['id']]));

        [, $location] = $this->post('merchant/rewards', ['action' => 'delete', 'reward_id' => $usado['id']]);
        $this->assertSame('merchant/rewards&success=premio_desativado_resgatado', $location);
        $this->assertSame(0, (int)$this->rewardField($usado['id'], 'active'));
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM points_log WHERE reward_id = :id', [':id' => $usado['id']]));
    }

    public function testEdicaoEExclusaoExigemCsrf(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $reward = $this->createReward($merchant, 'Cafe', 20);
        $this->loginAs('loja@teste.test');

        [$status] = $this->post('merchant/reward-edit', ['reward_id' => $reward['id'], 'name' => 'X', 'points_cost' => 1], false);
        $this->assertSame(403, $status);
        [$status] = $this->post('merchant/rewards', ['action' => 'delete', 'reward_id' => $reward['id']], false);
        $this->assertSame(403, $status);
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM rewards'));
    }
}
