<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// campo com erro sai marcado (aria-invalid) e ligado a mensagem (aria-describedby -> id da mensagem)
final class FieldErrorTest extends HttpTestCase {
    private const MARCADO = ' aria-invalid="true" aria-describedby="flash-error"';

    public function testLoginErradoMarcaEmailESenha(): void {
        $this->createMerchant('erro@teste.test');
        [, $location] = $this->post('merchant/login', ['email' => 'erro@teste.test', 'password' => 'errada'], true, 'merchant/login');

        $this->get($location);
        $this->assertStringContainsString('id="flash-error" role="alert"', $this->lastBody);
        $this->assertStringContainsString('name="email" id="email"' . self::MARCADO, $this->lastBody);
        $this->assertStringContainsString('name="password" id="password"' . self::MARCADO, $this->lastBody);
    }

    public function testTelaSemErroNaoMarcaNada(): void {
        $this->get('merchant/login');
        $this->assertStringNotContainsString('aria-invalid', $this->lastBody);
        $this->assertStringNotContainsString('flash-error', $this->lastBody);
    }

    public function testErroDeUmCampoNaoMarcaOsOutros(): void {
        $this->get('merchant/register&error=documento_invalido');
        $this->assertStringContainsString('name="document" id="document"' . self::MARCADO, $this->lastBody);
        $this->assertSame(1, substr_count($this->lastBody, 'aria-invalid'));
    }

    public function testCodigoDesconhecidoNaUrlNaoMarcaNada(): void {
        $this->get('merchant/register&error=' . urlencode('"><script>'));
        $this->assertStringNotContainsString('aria-invalid', $this->lastBody);
    }

    public function testPontosInvalidosMarcaOCampoDePontos(): void {
        $m = $this->createMerchant('erro@teste.test');
        $this->db->exec("INSERT INTO customers (phone) VALUES ('11911110001')");
        (new \App\Models\LoyaltyCardModel($this->db))->findOrCreate($m, (int)$this->db->lastInsertId(), 'Bia', \App\Support\Privacy::VERSION);
        $this->loginAs('erro@teste.test');

        $this->get('merchant/customer&phone=11911110001&error=pontos_invalidos');
        $this->assertStringContainsString('name="points" id="points"' . self::MARCADO, $this->lastBody);
    }

    public function testConsultaPublicaMarcaTelefoneInvalido(): void {
        $code = $this->publicCode($this->createMerchant('erro@teste.test'));
        $this->post('customer/balance', ['phone' => '123', 'loja' => $code], true, 'customer/balance&loja=' . $code);

        $this->assertStringContainsString('id="flash-error" role="alert"', $this->lastBody);
        $this->assertStringContainsString('name="phone" id="phone"' . self::MARCADO, $this->lastBody);
    }

    public function testMensagemDeSucessoTemPapelDeStatus(): void {
        $this->get('merchant/login&success=logout');
        $this->assertStringContainsString('id="flash-success" role="status"', $this->lastBody);
    }
}
