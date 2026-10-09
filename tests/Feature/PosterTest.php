<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// cartaz com qr code da consulta de saldo
final class PosterTest extends HttpTestCase {
    public function testCartazMostraNomeDaLojaQrCodeEEnderecoDaConsulta(): void {
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        [$status] = $this->get('merchant/poster');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Loja Teste', $this->lastBody);
        $this->assertStringContainsString('<svg', $this->lastBody);
        $this->assertStringNotContainsString('<?xml', $this->lastBody);
        $this->assertMatchesRegularExpression('#http://127\.0\.0\.1:\d+/index\.php\?url=customer%2Fbalance#', $this->lastBody);
        $this->assertStringContainsString('data-print>Imprimir', $this->lastBody); // o nav.js liga no window.print()
    }

    public function testCartazExigeLogin(): void {
        [, $location] = $this->get('merchant/poster');
        $this->assertSame('merchant/login&error=sessao_expirada', $location);
    }

    public function testMenuTemOLinkDoCartaz(): void {
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        $this->get('merchant/dashboard');
        $this->assertStringContainsString('merchant%2Fposter', $this->lastBody);
    }

    public function testGuiaDePrimeirosPassosSugereOCartazMasNaoSegura(): void {
        $m = $this->createMerchant('loja@teste.test');
        $this->createReward($m, 'Cafe', 20);
        $this->db->exec("INSERT INTO customers (phone) VALUES ('11911110001')");
        $this->db->exec("INSERT INTO loyalty_cards (merchant_id, customer_id, customer_name) SELECT $m, id, 'Bia' FROM customers");
        $this->db->exec("INSERT INTO points_log (card_id, type, quantity, description) SELECT id, 'earn', 5, 'Compra' FROM loyalty_cards");
        $this->loginAs('loja@teste.test');

        // premio e pontos feitos: o guia some mesmo sem imprimir o cartaz
        $this->get('merchant/dashboard');
        $this->assertStringNotContainsString('Primeiros passos', $this->lastBody);
    }

    public function testGuiaMostraOCartazComoOpcionalEnquantoFaltaOutroPasso(): void {
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        $this->get('merchant/dashboard');
        $this->assertStringContainsString('Imprima o cartaz com o QR code', $this->lastBody);
        $this->assertStringContainsString('(opcional)', $this->lastBody);
    }
}
