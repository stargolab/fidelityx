<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// pagina inicial publica
final class HomePageTest extends HttpTestCase {
    public function testHomeApresentaOProdutoESeguePraCadastroEConsulta(): void {
        [$status, $location] = $this->get('home');

        $this->assertSame(200, $status);
        $this->assertNull($location, 'a home nao redireciona mais pro login');
        $this->assertStringContainsString('Faça seus clientes voltarem sempre', $this->lastBody);
        $this->assertStringContainsString('href="/index.php?url=merchant%2Fregister"', $this->lastBody);
        $this->assertStringContainsString('href="/index.php?url=customer%2Fbalance"', $this->lastBody);
        $this->assertStringContainsString('href="/index.php?url=merchant%2Flogin"', $this->lastBody);
    }

    public function testRaizDoSiteTambemMostraAHome(): void {
        [$status] = $this->get('');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Cadastrar minha loja', $this->lastBody);
    }

    public function testLojistaLogadoVaiDireitoParaOPainel(): void {
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        [$status, $location] = $this->get('home');
        $this->assertSame(302, $status);
        $this->assertSame('merchant/dashboard', $location);
    }

    public function testHomeNaoExpoeDadosNemQuebraComParametrosEstranhos(): void {
        [$status] = $this->get('home&error=<script>alert(1)</script>');
        $this->assertSame(200, $status);
        $this->assertStringNotContainsString('alert(1)', $this->lastBody);
    }
}
