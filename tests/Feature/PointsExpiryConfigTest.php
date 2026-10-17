<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// task 31: prazo de validade dos pontos, configurado por loja na tela Regra de pontos
final class PointsExpiryConfigTest extends HttpTestCase {
    private const SCREEN = 'merchant/points-rule';

    private function months(int $merchant) {
        return $this->scalar('SELECT points_expiry_months FROM merchants WHERE id = ?', [$merchant]);
    }

    private function save(string $choice): ?string {
        return $this->post(self::SCREEN, ['action' => 'expiry', 'expiry_months' => $choice], true, self::SCREEN)[1];
    }

    public function testLojaNovaComecaComDozeMeses(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        $this->assertSame(12, (int)$this->months($merchant));
        $this->get(self::SCREEN);
        $this->assertStringContainsString('Validade atual: <strong>12 meses</strong>', $this->lastBody);
        $this->assertStringContainsString('<option value="12" selected>', $this->lastBody);
    }

    public function testLojaTrocaOPrazoEDesligaOVencimento(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        $this->assertSame(self::SCREEN . '&success=validade_salva', $this->save('6'));
        $this->assertSame(6, (int)$this->months($merchant));

        $this->assertSame(self::SCREEN . '&success=validade_salva', $this->save('never'));
        $this->assertNull($this->months($merchant));

        $this->get(self::SCREEN);
        $this->assertStringContainsString('os pontos não vencem', $this->lastBody);
        $this->assertStringContainsString('<option value="never" selected>', $this->lastBody);
    }

    public function testPrazoForaDaListaERecusado(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        foreach (['0', '5', '-12', '999', 'abc', '', '12.5'] as $choice) {
            $this->assertSame(self::SCREEN . '&error=validade_invalida', $this->save($choice), $choice);
        }
        $this->assertSame(12, (int)$this->months($merchant));

        $this->get(self::SCREEN . '&error=validade_invalida');
        // a mensagem aponta o campo: o forms.js marca e poe o foco nele
        $this->assertStringContainsString('data-fields="expiry_months"', $this->lastBody);
    }

    public function testSalvarAValidadeNaoMexeNaRegraDePontos(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->db->exec("UPDATE merchants SET points_rule_cents = 500 WHERE id = $merchant");
        $this->loginAs('loja@teste.test');

        $this->save('24');
        $this->assertSame(500, (int)$this->scalar('SELECT points_rule_cents FROM merchants WHERE id = ?', [$merchant]));
    }

    public function testPrazoDeUmaLojaNaoMudaODaOutra(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $lojaB = $this->createMerchant('b@teste.test');
        $this->loginAs('b@teste.test');

        $this->save('3');
        $this->assertSame(12, (int)$this->months($lojaA));
        $this->assertSame(3, (int)$this->months($lojaB));
    }

    public function testSemLoginNaoSalva(): void {
        $merchant = $this->createMerchant('loja@teste.test');

        [, $location] = $this->post(self::SCREEN, ['action' => 'expiry', 'expiry_months' => '3'], false);
        $this->assertSame('merchant/login&error=sessao_expirada', $location);
        $this->assertSame(12, (int)$this->months($merchant));
    }

    // a migration e o schema.sql precisam chegar no mesmo lugar: historico aceita o tipo 'expire'
    public function testHistoricoAceitaOTipoExpire(): void {
        $type = $this->scalar(
            "SELECT column_type FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'points_log' AND column_name = 'type'"
        );
        $this->assertStringContainsString("'expire'", (string)$type);
    }
}
