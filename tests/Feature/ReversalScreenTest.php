<?php

namespace Tests\Feature;

use App\Models\LoyaltyCardModel;
use Tests\Support\HttpTestCase;

// task 4: estorno pelo extrato do cliente
final class ReversalScreenTest extends HttpTestCase {
    private const STATEMENT = 'merchant/statement&phone=11922220002';

    public function testEstornaPeloExtrato(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $card = $this->createCard($merchant, 'Bia', '11922220002');
        $id = (new LoyaltyCardModel($this->db))->addPoints($card, 500, 'Compra errada');
        $this->loginAs('loja@teste.test');

        $this->get(self::STATEMENT);
        $this->assertStringContainsString('name="log_id" value="' . $id . '"', $this->lastBody);

        [, $location] = $this->post(
            'merchant/customer',
            ['action' => 'reverse', 'back' => 'statement', 'phone' => '11922220002', 'log_id' => $id],
            true,
            self::STATEMENT
        );
        $this->assertSame(self::STATEMENT . '&success=lancamento_estornado', $location);
        $this->assertSame(0, (int)$this->scalar('SELECT current_points FROM loyalty_cards'));

        // o extrato mostra o estorno e o lancamento nao oferece mais o botao
        $this->get(self::STATEMENT);
        $this->assertStringContainsString('badge-reversal">Estorno', $this->lastBody);
        $this->assertStringContainsString('Estorno: Compra errada', $this->lastBody);
        $this->assertStringNotContainsString('name="log_id"', $this->lastBody);
    }

    public function testErrosVoltamComMensagem(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $card = $this->createCard($merchant, 'Bia', '11922220002');
        $reward = $this->createReward($merchant, 'Cafe', 20);
        $cards = new LoyaltyCardModel($this->db);
        $id = $cards->addPoints($card, 30, 'Compra');
        $cards->redeem($card, $reward);
        $this->loginAs('loja@teste.test');

        $reverse = fn($logId) => $this->post(
            'merchant/customer',
            ['action' => 'reverse', 'back' => 'statement', 'phone' => '11922220002', 'log_id' => $logId],
            true,
            self::STATEMENT
        )[1];

        $this->assertSame(self::STATEMENT . '&error=estorno_sem_saldo', $reverse($id));
        $this->assertSame(self::STATEMENT . '&error=estorno_invalido', $reverse('abc'));
    }

    public function testNaoEstornaLancamentoDeOutroClienteNemDeOutraLoja(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $this->createMerchant('b@teste.test');
        $cardA = $this->createCard($lojaA, 'Bia', '11922220002');
        $idA = (new LoyaltyCardModel($this->db))->addPoints($cardA, 30, 'Compra');

        // loja B, com um cliente proprio, tenta estornar o lancamento da loja A pelo id
        $this->loginAs('b@teste.test');
        $this->post('merchant/customer-new', ['phone' => '11933330003', 'name' => 'Caio', 'consent' => '1'], true, 'merchant/customer-new&phone=11933330003');
        [, $location] = $this->post(
            'merchant/customer',
            ['action' => 'reverse', 'phone' => '11933330003', 'log_id' => $idA],
            true,
            'merchant/customer&phone=11933330003'
        );
        $this->assertSame('merchant/customer&phone=11933330003&error=estorno_invalido', $location);
        $this->assertSame(30, (int)$this->scalar('SELECT current_points FROM loyalty_cards WHERE id = ?', [$cardA]));
    }

    public function testEstornoExigeCsrf(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $card = $this->createCard($merchant, 'Bia', '11922220002');
        $id = (new LoyaltyCardModel($this->db))->addPoints($card, 30, 'Compra');
        $this->loginAs('loja@teste.test');

        [$status] = $this->post('merchant/customer', ['action' => 'reverse', 'phone' => '11922220002', 'log_id' => $id], false);
        $this->assertSame(403, $status);
        $this->assertSame(30, (int)$this->scalar('SELECT current_points FROM loyalty_cards'));
    }

    public function testRelatorioMostraOEstornoEDescontaDosPontosEmitidos(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $card = $this->createCard($merchant, 'Bia', '11922220002');
        $cards = new LoyaltyCardModel($this->db);
        $cards->addPoints($card, 50, 'Compra');
        $cards->reverse($cards->addPoints($card, 500, 'Compra errada'), $merchant);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/reports');
        $this->assertStringContainsString('badge-reversal">Estorno', $this->lastBody);
        $this->assertMatchesRegularExpression('#stat-value">50</div>\s*<div class="stat-label">Pontos emitidos#', $this->lastBody);
    }
}
