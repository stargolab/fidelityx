<?php

namespace Tests\Feature;

use App\Models\LoyaltyCardModel;
use Tests\Support\HttpTestCase;

// pagina de relatorios: indicadores e historico de movimentacoes
final class ReportsTest extends HttpTestCase {
    private function cardFor(int $merchant, string $name, string $phone): int {
        $this->db->exec("INSERT INTO customers (phone) VALUES ('$phone')");
        return (new LoyaltyCardModel($this->db))->findOrCreate($merchant, (int)$this->db->lastInsertId(), "$name", \App\Support\Privacy::VERSION);
    }

    public function testMostraIndicadoresEHistorico(): void {
        $m = $this->createMerchant('loja@teste.test');
        $reward = $this->createReward($m, 'Cafe', 20);
        $cards = new LoyaltyCardModel($this->db);
        $bia = $this->cardFor($m, 'Bia Souza', '11911110001');
        $ana = $this->cardFor($m, 'Ana Lima', '11922220002');
        $cards->addPoints($bia, 30, 'Compra da Bia');
        $cards->addPoints($ana, 10, 'Compra da Ana');
        $cards->redeem($bia, $reward);
        $this->loginAs('loja@teste.test');

        [$status] = $this->get('merchant/reports');
        $this->assertSame(200, $status);
        $this->assertMatchesRegularExpression('#stat-value">2</div>\s*<div class="stat-label">Clientes#', $this->lastBody);
        $this->assertMatchesRegularExpression('#stat-value">40</div>\s*<div class="stat-label">Pontos emitidos#', $this->lastBody);
        $this->assertMatchesRegularExpression('#stat-value">1</div>\s*<div class="stat-label">Resgates#', $this->lastBody);
        $this->assertMatchesRegularExpression('#stat-value">20</div>\s*<div class="stat-label">Pontos em circulação#', $this->lastBody);
        $this->assertStringContainsString('Compra da Bia', $this->lastBody);
        $this->assertStringContainsString('Resgate: Cafe', $this->lastBody);
        $this->assertStringContainsString('merchant%2Fstatement&amp;phone=11911110001', $this->lastBody);
    }

    public function testHistoricoPaginaDeVinteEmVinte(): void {
        $m = $this->createMerchant('loja@teste.test');
        $card = $this->cardFor($m, 'Bia', '11911110001');
        $cards = new LoyaltyCardModel($this->db);
        for ($i = 1; $i <= 25; $i++) {
            $cards->addPoints($card, 1, "Lanc $i");
        }
        $this->loginAs('loja@teste.test');

        $this->get('merchant/reports');
        $this->assertSame(20, substr_count($this->lastBody, 'badge-earn">Ganhou'));
        $this->assertStringContainsString('Página 1 de 2', $this->lastBody);

        $this->get('merchant/reports&page=2');
        $this->assertSame(5, substr_count($this->lastBody, 'badge-earn">Ganhou'));
    }

    public function testRelatorioSoContaDadosDaPropriaLoja(): void {
        $a = $this->createMerchant('a@teste.test');
        $this->createMerchant('b@teste.test');
        $cards = new LoyaltyCardModel($this->db);
        $cards->addPoints($this->cardFor($a, 'Cliente da A', '11911110001'), 50, 'So da A');
        $this->loginAs('b@teste.test');

        $this->get('merchant/reports');
        $this->assertStringNotContainsString('So da A', $this->lastBody);
        $this->assertMatchesRegularExpression('#stat-value">0</div>\s*<div class="stat-label">Pontos emitidos#', $this->lastBody);
        $this->assertStringContainsString('Nenhuma movimentação ainda', $this->lastBody);
    }

    public function testRelatorioExigeLoginETemLinkNoMenu(): void {
        [, $location] = $this->get('merchant/reports');
        $this->assertSame('merchant/login&error=sessao_expirada', $location);

        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');
        $this->get('merchant/dashboard');
        $this->assertStringContainsString('merchant%2Freports', $this->lastBody);
    }
}
