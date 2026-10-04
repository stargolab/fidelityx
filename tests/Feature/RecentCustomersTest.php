<?php

namespace Tests\Feature;

use App\Models\LoyaltyCardModel;
use Tests\Support\HttpTestCase;

// task 7: clientes atendidos recentemente na home
final class RecentCustomersTest extends HttpTestCase {
    public function testMostraOsUltimos5AtendidosDoMaisRecente(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $cards = new LoyaltyCardModel($this->db);
        for ($i = 1; $i <= 7; $i++) {
            $card = $this->createCard($merchant, "Cliente $i", '1192222000' . $i);
            $cards->addPoints($card, 1, 'Compra');
            $this->db->exec("UPDATE loyalty_cards SET last_use_at = NOW() - INTERVAL " . (10 - $i) . " MINUTE WHERE id = $card");
        }
        $this->loginAs('loja@teste.test');

        $this->get('merchant/dashboard');
        $this->assertStringContainsString('Atendidos recentemente', $this->lastBody);
        preg_match_all('#recent-name">([^<]+)<#', $this->lastBody, $m);
        $this->assertSame(['Cliente 7', 'Cliente 6', 'Cliente 5', 'Cliente 4', 'Cliente 3'], $m[1]);
        $this->assertStringContainsString('merchant%2Fcustomer&amp;phone=11922220007', $this->lastBody, 'um toque abre a tela do cliente');
    }

    public function testCadastroSemMovimentacaoNaoEAtendimento(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->createCard($merchant, 'Bia', '11922220001');
        $this->loginAs('loja@teste.test');

        $this->get('merchant/dashboard');
        $this->assertStringNotContainsString('Atendidos recentemente', $this->lastBody);
    }

    public function testSoClientesDaPropriaLojaENaoExcluidos(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $lojaB = $this->createMerchant('b@teste.test');
        $cards = new LoyaltyCardModel($this->db);
        $cards->addPoints($this->createCard($lojaA, 'Da Loja A', '11922220001'), 1, 'Compra');
        $excluido = $this->createCard($lojaB, 'Excluido', '11922220002');
        $cards->addPoints($excluido, 1, 'Compra');
        $cards->anonymize($excluido, $lojaB);
        $cards->addPoints($this->createCard($lojaB, 'Da Loja B', '11922220003'), 1, 'Compra');
        $this->loginAs('b@teste.test');

        $this->get('merchant/dashboard');
        $this->assertStringContainsString('Da Loja B', $this->lastBody);
        $this->assertStringNotContainsString('Da Loja A', $this->lastBody);
        $this->assertStringNotContainsString('Excluido', $this->lastBody);
    }
}
