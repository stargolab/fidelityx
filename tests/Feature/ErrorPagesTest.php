<?php

namespace Tests\Feature;

use App\Controllers\ErrorController;
use Tests\Support\HttpTestCase;

// botao de voltar das paginas de erro (task 39): visitante vai pra home publica, lojista logado pro painel
final class ErrorPagesTest extends HttpTestCase {
    private const HOME = 'href="/index.php?url=home"';
    private const PAINEL = 'href="/index.php?url=merchant%2Fdashboard"';

    public function testVisitanteNoErro404VoltaParaAHomePublica(): void {
        [$status] = $this->get('rota/que-nao-existe');

        $this->assertSame(404, $status);
        $this->assertStringContainsString(self::HOME, $this->lastBody);
        $this->assertStringContainsString('Voltar ao início', $this->lastBody);
        $this->assertStringNotContainsString('merchant', $this->lastBody, 'visitante nao e mandado pro login do lojista');
    }

    public function testLojistaLogadoNoErro404VoltaParaOPainel(): void {
        $this->createMerchant('erro@teste.test');
        $this->loginAs('erro@teste.test');

        [$status] = $this->get('merchant/rota-que-nao-existe');

        $this->assertSame(404, $status);
        $this->assertStringContainsString(self::PAINEL, $this->lastBody);
        $this->assertStringContainsString('Voltar ao Painel', $this->lastBody);
    }

    // depois do logout a pessoa volta a ser visitante
    public function testDepoisDeSairOBotaoVoltaALevarParaAHome(): void {
        $this->createMerchant('erro@teste.test');
        $this->loginAs('erro@teste.test');
        $this->post('merchant/logout', [], true, 'merchant/dashboard');

        $this->get('rota/que-nao-existe');

        $this->assertStringContainsString(self::HOME, $this->lastBody);
    }

    // o cliente da consulta publica que estoura o limite ve a 429: nao pode cair no login do lojista
    public function testClienteBloqueadoNaConsultaPublicaVoltaParaAHome(): void {
        $code = $this->publicCode($this->createMerchant('erro@teste.test'));
        $page = 'customer/balance&loja=' . $code;
        $fields = ['phone' => '11900000000', 'loja' => $code];
        for ($i = 0; $i < 5; $i++) {
            $this->post('customer/balance', $fields, true, $page);
        }

        [$status] = $this->post('customer/balance', $fields, true, $page);

        $this->assertSame(429, $status);
        $this->assertStringContainsString(self::HOME, $this->lastBody);
        $this->assertStringNotContainsString('merchant', $this->lastBody);
    }

    // formulario sem o token csrf (403), com ou sem login
    public function testErro403SegueAMesmaRegra(): void {
        [$status] = $this->post('merchant/login', ['email' => 'x@teste.test', 'password' => 'x'], false);
        $this->assertSame(403, $status);
        $this->assertStringContainsString(self::HOME, $this->lastBody);

        $this->createMerchant('erro@teste.test');
        $this->loginAs('erro@teste.test');
        [$status] = $this->post('merchant/rewards', ['name' => 'Cafe'], false);
        $this->assertSame(403, $status);
        $this->assertStringContainsString(self::PAINEL, $this->lastBody);
    }

    // nenhuma pagina de erro ficou com o link fixo antigo
    public function testTodasAsPaginasDeErroUsamODestinoCalculado(): void {
        $views = glob(dirname(__DIR__, 2) . '/views/errors/*.php');

        $this->assertNotEmpty($views);
        foreach ($views as $view) {
            $html = (string)file_get_contents($view);
            $this->assertStringContainsString('<?= e($backUrl) ?>', $html, basename($view));
            $this->assertStringNotContainsString('url=merchant/dashboard', $html, basename($view));
        }
    }

    public function testDestinoDoBotao(): void {
        $this->assertSame(['/index.php?url=home', 'Voltar ao início'], ErrorController::backLink(false));
        $this->assertSame(['/index.php?url=merchant%2Fdashboard', 'Voltar ao Painel'], ErrorController::backLink(true));
    }
}
