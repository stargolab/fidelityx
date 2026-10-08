<?php

namespace Tests\Feature;

use App\Models\LoyaltyCardModel;
use Tests\Support\HttpTestCase;

// progresso ate o proximo premio nas duas telas: a do cliente (publica) e a do lojista
final class RewardProgressScreenTest extends HttpTestCase {
    private function setUpCustomer(int $points): int {
        $m = $this->createMerchant('loja@teste.test');
        $this->createReward($m, 'Cafe', 20);
        $this->createReward($m, 'Suco', 50);
        $this->db->exec("INSERT INTO customers (phone) VALUES ('11911110001')");
        $cards = new LoyaltyCardModel($this->db);
        $card = $cards->findOrCreate($m, (int)$this->db->lastInsertId(), "Bia Souza", \App\Support\Privacy::VERSION);
        if ($points > 0) {
            $cards->addPoints($card, $points, 'Compra');
        }
        return $m;
    }

    public function testConsultaPublicaMostraFaltamPontosEBarra(): void {
        $code = $this->publicCode($this->setUpCustomer(30));

        $this->post('customer/balance', ['phone' => '11911110001', 'loja' => $code], true, 'customer/balance&loja=' . $code);
        $this->assertMatchesRegularExpression('/Faltam\s*<strong>20 pontos<\/strong>\s*para\s*<strong>Suco<\/strong>/', $this->lastBody);
        $this->assertStringContainsString('max="100" value="60"', $this->lastBody);
        // a largura vem do atributo value: a view nao pode voltar a ter estilo solto
        $this->assertStringNotContainsString('style=', $this->lastBody);
    }

    public function testTelaDoLojistaMostraOMesmoProgresso(): void {
        $this->setUpCustomer(30);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customer&phone=11911110001');
        $this->assertMatchesRegularExpression('/Faltam\s*<strong>20 pontos<\/strong>\s*para\s*<strong>Suco<\/strong>/', $this->lastBody);
        $this->assertStringContainsString('max="100" value="60"', $this->lastBody);
    }

    public function testSaldoZeroMostraOPrimeiroPremio(): void {
        $this->setUpCustomer(0);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customer&phone=11911110001');
        $this->assertMatchesRegularExpression('/Faltam\s*<strong>20 pontos<\/strong>\s*para\s*<strong>Cafe<\/strong>/', $this->lastBody);
        $this->assertStringContainsString('max="100" value="0"', $this->lastBody);
    }

    public function testQuandoPagaTudoDizQueJaDaParaResgatar(): void {
        $this->setUpCustomer(80);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customer&phone=11911110001');
        $this->assertStringContainsString('Já dá para resgatar qualquer prêmio', $this->lastBody);
        $this->assertStringContainsString('max="100" value="100"', $this->lastBody);
    }

    public function testLojaSemPremioNaoMostraBarra(): void {
        $m = $this->createMerchant('loja@teste.test');
        $this->db->exec("INSERT INTO customers (phone) VALUES ('11911110001')");
        (new LoyaltyCardModel($this->db))->findOrCreate($m, (int)$this->db->lastInsertId(), "Bia", \App\Support\Privacy::VERSION);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customer&phone=11911110001');
        $this->assertStringNotContainsString('<progress', $this->lastBody);
    }

    public function testPremioInativoNaoEntraNoProgresso(): void {
        $m = $this->createMerchant('loja@teste.test');
        $this->createReward($m, 'Cafe', 20, false);
        $this->createReward($m, 'Suco', 50);
        $this->db->exec("INSERT INTO customers (phone) VALUES ('11911110001')");
        (new LoyaltyCardModel($this->db))->findOrCreate($m, (int)$this->db->lastInsertId(), "Bia", \App\Support\Privacy::VERSION);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customer&phone=11911110001');
        $this->assertMatchesRegularExpression('/<strong>50 pontos<\/strong>\s*para\s*<strong>Suco<\/strong>/', $this->lastBody);
    }
}
