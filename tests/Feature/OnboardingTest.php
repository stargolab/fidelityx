<?php

namespace Tests\Feature;

use App\Models\LoyaltyCardModel;
use Tests\Support\HttpTestCase;

// guia de primeiros passos da home do lojista
final class OnboardingTest extends HttpTestCase {
    public function testLojaNovaVeOGuiaComOsDoisPassosPendentes(): void {
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        $this->get('merchant/dashboard');
        $this->assertStringContainsString('Primeiros passos', $this->lastBody);
        $this->assertStringContainsString('Cadastre seu primeiro prêmio', $this->lastBody);
        $this->assertStringContainsString('Lance os primeiros pontos', $this->lastBody);
        $this->assertSame(0, substr_count($this->lastBody, 'step-done'));
    }

    public function testPassoFeitoFicaMarcadoEOGuiaContinuaAteTerminar(): void {
        $m = $this->createMerchant('loja@teste.test');
        $this->createReward($m, 'Cafe', 20);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/dashboard');
        $this->assertStringContainsString('Primeiros passos', $this->lastBody);
        $this->assertSame(1, substr_count($this->lastBody, 'step step-done'));
        $this->assertStringContainsString('Lance os primeiros pontos', $this->lastBody);
    }

    public function testGuiaSomeQuandoTudoEstaFeito(): void {
        $m = $this->createMerchant('loja@teste.test');
        $this->createReward($m, 'Cafe', 20);
        $this->db->exec("INSERT INTO customers (phone) VALUES ('11911110001')");
        $cards = new LoyaltyCardModel($this->db);
        $cards->addPoints($cards->findOrCreate($m, (int)$this->db->lastInsertId(), "Bia", \App\Support\Privacy::VERSION), 5, 'Compra');
        $this->loginAs('loja@teste.test');

        $this->get('merchant/dashboard');
        $this->assertStringNotContainsString('Primeiros passos', $this->lastBody);
        $this->assertStringContainsString('Telefone do cliente', $this->lastBody, 'a busca continua na home');
    }

    public function testProgressoEDeCadaLojaSeparadamente(): void {
        $a = $this->createMerchant('a@teste.test');
        $this->createReward($a, 'Cafe', 20);
        $this->createMerchant('b@teste.test');
        $this->loginAs('b@teste.test');

        $this->get('merchant/dashboard');
        $this->assertSame(0, substr_count($this->lastBody, 'step step-done'), 'premio da loja A nao conta pra B');
    }
}
