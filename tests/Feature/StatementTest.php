<?php

namespace Tests\Feature;

use App\Models\LoyaltyCardModel;
use App\Models\PointsLogModel;
use Tests\Support\HttpTestCase;

// extrato de pontos do cliente
final class StatementTest extends HttpTestCase {
    private function cardFor(int $merchant, string $name, string $phone): int {
        $this->db->exec("INSERT INTO customers (phone) VALUES ('$phone')");
        return (new LoyaltyCardModel($this->db))->findOrCreate($merchant, (int)$this->db->lastInsertId(), "$name", \App\Support\Privacy::VERSION);
    }

    public function testExtratoMostraGanhosEResgatesDoCliente(): void {
        $m = $this->createMerchant('loja@teste.test');
        $reward = $this->createReward($m, 'Cafe', 20);
        $card = $this->cardFor($m, 'Bia Souza', '11911110001');
        $cards = new LoyaltyCardModel($this->db);
        $cards->addPoints($card, 30, 'Compra da manha');
        $cards->redeem($card, $reward);
        $this->loginAs('loja@teste.test');

        // a tela do cliente leva ao extrato
        $this->get('merchant/customer&phone=11911110001');
        $this->assertStringContainsString('merchant%2Fstatement&amp;phone=11911110001', $this->lastBody);

        [$status] = $this->get('merchant/statement&phone=11911110001');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Compra da manha', $this->lastBody);
        $this->assertStringContainsString('Resgate: Cafe', $this->lastBody);
        $this->assertStringContainsString('+30', $this->lastBody);
        $this->assertStringContainsString('−20', $this->lastBody);
        // o resgate (mais recente) vem antes do ganho
        $this->assertLessThan(strpos($this->lastBody, 'Compra da manha'), strpos($this->lastBody, 'Resgate: Cafe'));
    }

    public function testExtratoPaginaDe20EmVinte(): void {
        $m = $this->createMerchant('loja@teste.test');
        $card = $this->cardFor($m, 'Bia', '11911110001');
        $cards = new LoyaltyCardModel($this->db);
        for ($i = 1; $i <= 25; $i++) {
            $cards->addPoints($card, 1, "Lanc $i");
        }
        $this->loginAs('loja@teste.test');

        $this->get('merchant/statement&phone=11911110001');
        $this->assertSame(20, substr_count($this->lastBody, 'badge-earn">Ganhou'));
        $this->assertStringContainsString('Página 1 de 2', $this->lastBody);

        $this->get('merchant/statement&phone=11911110001&page=2');
        $this->assertSame(5, substr_count($this->lastBody, 'badge-earn">Ganhou'));
    }

    public function testExtratoDeOutraLojaOuTelefoneInvalidoVoltaParaBusca(): void {
        $a = $this->createMerchant('a@teste.test');
        $this->createMerchant('b@teste.test');
        $this->cardFor($a, 'Bia', '11911110001');
        $this->loginAs('b@teste.test');

        [, $location] = $this->get('merchant/statement&phone=11911110001');
        $this->assertSame('merchant/dashboard&error=cliente_nao_encontrado', $location);
        [, $location] = $this->get('merchant/statement&phone=abc');
        $this->assertSame('merchant/dashboard&error=cliente_nao_encontrado', $location);
    }

    public function testExtratoExigeLogin(): void {
        [, $location] = $this->get('merchant/statement&phone=11911110001');
        $this->assertSame('merchant/login&error=sessao_expirada', $location);
    }

    public function testClienteSemMovimentacaoTemMensagemVazia(): void {
        $m = $this->createMerchant('loja@teste.test');
        $this->cardFor($m, 'Bia', '11911110001');
        $this->loginAs('loja@teste.test');

        $this->get('merchant/statement&phone=11911110001');
        $this->assertStringContainsString('ainda não tem movimentações', $this->lastBody);
        $this->assertSame(0, (new PointsLogModel($this->db))->countByMerchant($m));
    }
}
