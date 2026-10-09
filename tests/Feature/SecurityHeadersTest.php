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
            $this->assertSame(\App\Support\RequestGuard::CSP, $this->header('Content-Security-Policy'), $route);
            $this->assertNull($this->header('Strict-Transport-Security'), $route . ': http nao manda hsts');
            $this->assertSame('nosniff', $this->header('X-Content-Type-Options'), $route);
            $this->assertSame('same-origin', $this->header('Referrer-Policy'), $route);
            $this->assertNull($this->header('X-Powered-By'), $route);
        }

        $this->loginAs('loja@teste.test');
        $this->get('merchant/customers');
        $this->assertSame('DENY', $this->header('X-Frame-Options'));
    }

    // task 48: a politica bloqueia script inline e de outro site (so os arquivos de public/js rodam)
    public function testCspSoAceitaScriptDoProprioSite(): void {
        $this->get('home');
        $csp = (string)$this->header('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self';", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    // nenhuma view tem <script> inline nem atributo de evento (onclick, onsubmit...): a CSP bloquearia
    public function testViewsSemScriptInlineNemAtributoDeEvento(): void {
        $root = dirname(__DIR__, 2) . '/views';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $html = (string)file_get_contents($file->getPathname());
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $html, $file->getFilename());
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $html, $file->getFilename());
        }
    }

    public function testScriptsDasTelasExistem(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->createCard($merchant, 'Bia', '11922220002');
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customer&phone=11922220002');
        preg_match_all('#<script src="(/js/[a-z]+\.js)"></script>#', $this->lastBody, $m);
        $this->assertSame(['/js/nav.js', '/js/customer.js', '/js/masks.js', '/js/forms.js'], $m[1]);
        foreach ($m[1] as $src) {
            $this->assertFileExists(dirname(__DIR__, 2) . '/public' . $src);
        }

        $this->get('merchant/poster');
        $this->assertStringContainsString('data-print>', $this->lastBody);
    }

    // hsts so por https (aqui, atras do proxy confiavel que avisa pelo X-Forwarded-Proto)
    public function testHstsSoEmHttps(): void {
        $this->withHeaders(['X-Forwarded-Proto: https']);
        $this->get('home');
        $this->assertSame('max-age=31536000', $this->header('Strict-Transport-Security'));

        $this->withHeaders([]);
        $this->get('home');
        $this->assertNull($this->header('Strict-Transport-Security'));
    }

    // task 47: nome, telefone e saldo nao ficam no cache (Voltar depois do logout, computador compartilhado)
    public function testPainelEConsultaDeSaldoSaemComNoStore(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->createCard($merchant, 'Bia', '11922220002');
        $code = $this->scalar('SELECT public_code FROM merchants WHERE id = ' . $merchant);

        // publicas: login e consulta de saldo (GET e o POST com o resultado)
        foreach (['merchant/login', 'customer/balance', 'customer/balance&loja=' . $code] as $route) {
            $this->get($route);
            $this->assertSame('no-store', $this->header('Cache-Control'), $route);
        }
        $this->post('customer/balance', ['loja' => $code, 'phone' => '11922220002'], true, 'customer/balance&loja=' . $code);
        $this->assertStringContainsString('Bia', $this->lastBody);
        $this->assertSame('no-store', $this->header('Cache-Control'));

        // painel logado, inclusive redirect
        $this->loginAs('loja@teste.test');
        foreach (['merchant/dashboard', 'merchant/customer&phone=11922220002', 'merchant/customers',
                  'merchant/statement&phone=11922220002', 'merchant/reports', 'merchant/profile',
                  'merchant/dashboard&phone=11922220002'] as $route) {
            $this->get($route);
            $this->assertSame('no-store', $this->header('Cache-Control'), $route);
            $this->assertSame('no-cache', $this->header('Pragma'), $route);
        }
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
