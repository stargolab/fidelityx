<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// task 33: cabecalhos de seguranca, logout por POST e parametros em formato de lista
final class SecurityHeadersTest extends HttpTestCase {
    private function header(string $name): ?string {
        return $this->lastHeaders[strtolower($name)][0] ?? null;
    }

    public function testTodaRespostaTemCabecalhosDeSegurancaESemVersaoDoPhp(): void {
        $this->createMerchant('loja@teste.test');

        // pagina publica, pagina de erro, redirect e pagina logada
        $responses = ['home' => null, 'merchant/nao-existe' => null, 'merchant/dashboard' => null];
        foreach (array_keys($responses) as $route) {
            $this->get($route);
            $this->assertSame('DENY', $this->header('X-Frame-Options'), $route);
            $this->assertSame("frame-ancestors 'none'", $this->header('Content-Security-Policy'), $route);
            $this->assertSame('nosniff', $this->header('X-Content-Type-Options'), $route);
            $this->assertSame('same-origin', $this->header('Referrer-Policy'), $route);
            $this->assertNull($this->header('X-Powered-By'), $route);
        }

        $this->loginAs('loja@teste.test');
        $this->get('merchant/customers');
        $this->assertSame('DENY', $this->header('X-Frame-Options'));
    }

    public function testLogoutSoPorPostComCsrf(): void {
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        // o menu manda POST com csrf
        $this->get('merchant/dashboard');
        $this->assertMatchesRegularExpression('#<form action="/index.php\?url=merchant%2Flogout" method="POST"#', $this->lastBody);

        // GET (link ou imagem em outro site) nao desloga
        [, $location] = $this->get('merchant/logout');
        $this->assertSame('merchant/dashboard', $location);
        [$status] = $this->get('merchant/customers');
        $this->assertSame(200, $status, 'continua logado');

        // POST sem csrf: 403 e continua logado
        [$status] = $this->post('merchant/logout', [], false);
        $this->assertSame(403, $status);
        [$status] = $this->get('merchant/customers');
        $this->assertSame(200, $status);

        // POST com csrf: sai
        [, $location] = $this->post('merchant/logout', [], true, 'merchant/dashboard');
        $this->assertSame('merchant/login&success=logout', $location);
        [, $location] = $this->get('merchant/customers');
        $this->assertSame('merchant/login&error=sessao_expirada', $location);
    }

    public function testParametroEmFormatoDeListaNaoGeraWarningNemQuebraORedirect(): void {
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        [, $location] = $this->get('merchant/dashboard&phone[]=11999998888');
        $this->assertSame('merchant/dashboard&error=telefone_invalido', $location);

        foreach (['merchant/customers&q[]=x&page[]=2', 'merchant/statement&phone[]=1', 'customer/balance&loja[]=x'] as $route) {
            $this->get($route);
            $this->assertStringNotContainsString('Warning', $this->lastBody, $route);
            $this->assertStringNotContainsString('Array to string', $this->lastBody, $route);
        }

        // url em formato de lista cai na home, sem warning
        $this->newSession();
        [$status] = $this->get('home&url[]=x');
        $this->assertSame(200, $status);
        $this->assertStringNotContainsString('Warning', $this->lastBody);
    }

    public function testPostComCampoEmFormatoDeListaETratadoComoInvalido(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->createCard($merchant, 'Bia', '11922220002');
        $this->loginAs('loja@teste.test');

        [, $location] = $this->post(
            'merchant/customer',
            ['action' => 'score', 'phone' => '11922220002', 'points' => ['5']],
            true,
            'merchant/customer&phone=11922220002'
        );
        $this->assertSame('merchant/customer&phone=11922220002&error=pontos_invalidos', $location);
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM points_log'));
    }
}
