<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// task 6: pontos calculados pelo valor da compra (arredonda pra baixo)
final class PointsRuleTest extends HttpTestCase {
    private const SCREEN = 'merchant/customer&phone=11922220002';

    private function setUpStore(?int $ruleCents): int {
        $merchant = $this->createMerchant('loja@teste.test');
        if ($ruleCents !== null) {
            $this->db->exec("UPDATE merchants SET points_rule_cents = $ruleCents WHERE id = $merchant");
        }
        $this->createCard($merchant, 'Bia', '11922220002');
        $this->loginAs('loja@teste.test');
        return $merchant;
    }

    private function launch(array $fields): ?string {
        return $this->post('merchant/customer', ['action' => 'score', 'phone' => '11922220002'] + $fields, true, self::SCREEN)[1];
    }

    public function testDefineAlteraERemoveARegra(): void {
        $merchant = $this->setUpStore(null);

        [, $location] = $this->post('merchant/points-rule', ['action' => 'save', 'rule' => '1,00'], true, 'merchant/points-rule');
        $this->assertSame('merchant/points-rule&success=regra_salva', $location);
        $this->assertSame(100, (int)$this->scalar('SELECT points_rule_cents FROM merchants WHERE id = ?', [$merchant]));

        $this->get('merchant/points-rule');
        $this->assertStringContainsString('a cada <strong>R$ 1,00</strong> em compras', $this->lastBody);

        $this->post('merchant/points-rule', ['action' => 'save', 'rule' => '5'], true, 'merchant/points-rule');
        $this->assertSame(500, (int)$this->scalar('SELECT points_rule_cents FROM merchants WHERE id = ?', [$merchant]));

        [, $location] = $this->post('merchant/points-rule', ['action' => 'clear'], true, 'merchant/points-rule');
        $this->assertSame('merchant/points-rule&success=regra_removida', $location);
        $this->assertNull($this->scalar('SELECT points_rule_cents FROM merchants WHERE id = ?', [$merchant]));
    }

    public function testRegraInvalidaERecusada(): void {
        $merchant = $this->setUpStore(null);

        foreach (['0', 'abc', '', '0,001', '2000000'] as $rule) {
            [, $location] = $this->post('merchant/points-rule', ['action' => 'save', 'rule' => $rule], true, 'merchant/points-rule');
            $this->assertSame('merchant/points-rule&error=regra_invalida', $location, $rule);
        }
        $this->assertNull($this->scalar('SELECT points_rule_cents FROM merchants WHERE id = ?', [$merchant]));
    }

    public function testValorDaCompraViraPontosArredondandoParaBaixo(): void {
        $this->setUpStore(100);

        $this->get(self::SCREEN);
        $this->assertStringContainsString('name="amount"', $this->lastBody);
        $this->assertStringContainsString('data-rule-cents="100"', $this->lastBody);

        $location = $this->launch(['amount' => '12,90']);
        $this->assertMatchesRegularExpression('/lancamento=\d+$/', $location);
        $this->assertSame(12, (int)$this->scalar('SELECT current_points FROM loyalty_cards'));
        $this->assertSame('Compra de R$ 12,90', $this->scalar('SELECT description FROM points_log'));
    }

    public function testOServidorRecalculaEIgnoraPontosDiferentesDoValor(): void {
        $this->setUpStore(500);

        // a previa da tela poderia ter sido adulterada: vale o valor da compra
        $this->launch(['amount' => '14,99', 'points' => '9999']);
        $this->assertSame(2, (int)$this->scalar('SELECT current_points FROM loyalty_cards'));
    }

    public function testSemValorValeOCampoDePontos(): void {
        $this->setUpStore(100);

        $this->launch(['amount' => '', 'points' => '7', 'description' => 'Brinde']);
        $this->assertSame(7, (int)$this->scalar('SELECT current_points FROM loyalty_cards'));
        $this->assertSame('Brinde', $this->scalar('SELECT description FROM points_log'));
    }

    public function testValorInvalidoOuAbaixoDeUmPontoNaoLanca(): void {
        $this->setUpStore(500);

        $this->assertSame(self::SCREEN . '&error=valor_invalido', $this->launch(['amount' => 'abc']));
        $this->assertSame(self::SCREEN . '&error=valor_sem_pontos', $this->launch(['amount' => '4,99']));
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM points_log'));
    }

    public function testValorQuePassaDoLimiteDePontosNaoLanca(): void {
        $this->setUpStore(1); // R$ 0,01 = 1 ponto

        // mensagem propria (task 52): a de "pontos entre 1 e 10.000" nao fazia sentido pra quem digitou um valor
        $this->assertSame(self::SCREEN . '&error=valor_pontos_demais', $this->launch(['amount' => '200,00']));
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM points_log'));
        $this->get(self::SCREEN . '&error=valor_pontos_demais');
        $this->assertStringContainsString('mais de 10.000 pontos', $this->lastBody);

        // valor negativo nao vira positivo
        $this->assertSame(self::SCREEN . '&error=valor_invalido', $this->launch(['amount' => '-10,00']));
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM points_log'));
    }

    public function testSemRegraOValorEIgnoradoETelaOfereceDefinir(): void {
        $this->setUpStore(null);

        $this->get(self::SCREEN);
        $this->assertStringNotContainsString('name="amount"', $this->lastBody);
        $this->assertStringContainsString('merchant%2Fpoints-rule', $this->lastBody);

        $this->assertSame(self::SCREEN . '&error=pontos_invalidos', $this->launch(['amount' => '50,00']));
    }

    public function testGuiaDePrimeirosPassosTemARegraComoOpcional(): void {
        $merchant = $this->setUpStore(null);

        $this->get('merchant/dashboard');
        $this->assertStringContainsString('Defina a regra de pontos', $this->lastBody);

        $this->db->exec("UPDATE merchants SET points_rule_cents = 100 WHERE id = $merchant");
        $this->get('merchant/dashboard');
        $this->assertMatchesRegularExpression('#step step-done">\s*<span class="step-mark" aria-hidden="true">✓</span>\s*<div class="step-text">\s*<strong>Defina a regra de pontos#', $this->lastBody);
    }

    public function testRegraDeUmaLojaNaoValeParaOutra(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $this->db->exec("UPDATE merchants SET points_rule_cents = 100 WHERE id = $lojaA");
        $lojaB = $this->createMerchant('b@teste.test');
        $this->createCard($lojaB, 'Bia', '11922220002');
        $this->loginAs('b@teste.test');

        $this->get(self::SCREEN);
        $this->assertStringNotContainsString('name="amount"', $this->lastBody);
    }
}
