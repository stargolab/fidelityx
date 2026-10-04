<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// task 34: sessao do lojista revalidada a cada requisicao e protegida contra session fixation
final class SessionTest extends HttpTestCase {
    public function testLojistaDesativadoPerdeOAcessoNaHora(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');
        $this->assertSame(200, $this->get('merchant/customers')[0]);

        // desativado (ex.: pelo painel admin) com a sessao aberta
        $this->db->exec("UPDATE merchants SET status = 'inactive' WHERE id = $merchant");

        [, $location] = $this->get('merchant/customers');
        $this->assertSame('merchant/login&error=conta_inativa', $location);

        // a sessao foi encerrada: reativar a conta nao devolve o acesso sem novo login
        $this->db->exec("UPDATE merchants SET status = 'active' WHERE id = $merchant");
        [, $location] = $this->get('merchant/customers');
        $this->assertSame('merchant/login&error=sessao_expirada', $location);
    }

    public function testLojistaApagadoPerdeOAcesso(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        $this->db->exec("DELETE FROM merchants WHERE id = $merchant");

        [, $location] = $this->get('merchant/dashboard');
        $this->assertSame('merchant/login&error=conta_inativa', $location);
    }

    public function testNomeDaLojaNoMenuAcompanhaOBanco(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        $this->db->exec("UPDATE merchants SET store_name = 'Padaria Nova' WHERE id = $merchant");

        $this->get('merchant/dashboard');
        $this->assertStringContainsString('nav-store">Padaria Nova<', $this->lastBody);
    }

    public function testIdDeSessaoInventadoNaoEAceito(): void {
        // session fixation: quem chega com um id escolhido por outra pessoa recebe um id novo
        // id aleatorio a cada rodada: um id que ja existe no servidor seria (corretamente) aceito
        $forged = 'atacante' . bin2hex(random_bytes(8));
        $this->setCookie('PHPSESSID', $forged);
        $this->get('merchant/login');

        $issued = $this->cookie('PHPSESSID');
        $this->assertNotNull($issued);
        $this->assertNotSame($forged, $issued);
    }

    public function testLoginTrocaOIdDaSessao(): void {
        $this->createMerchant('loja@teste.test');
        $this->get('merchant/login');
        $before = $this->cookie('PHPSESSID');

        $this->loginAs('loja@teste.test');

        $this->assertNotSame($before, $this->cookie('PHPSESSID'));
    }
}
