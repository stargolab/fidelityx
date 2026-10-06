<?php

namespace Tests\Feature;

use App\Models\LoyaltyCardModel;
use Tests\Support\HttpTestCase;

// task 18: estilos em classe, formularios com o forms.js e erro apontando o campo
final class AccessibilityTest extends HttpTestCase {
    public function testNenhumaViewTemAtributoStyle(): void {
        $root = dirname(__DIR__, 2) . '/views';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/\sstyle="/', (string)file_get_contents($file->getPathname()), $file->getFilename());
        }
    }

    public function testPaginasComFormularioCarregamOFormsJs(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $code = $this->scalar('SELECT public_code FROM merchants WHERE id = ?', [$merchant]);

        foreach (['merchant/login', 'merchant/register', 'customer/balance&loja=' . $code] as $route) {
            $this->get($route);
            $this->assertStringContainsString('<script src="/js/forms.js"></script>', $this->lastBody, $route);
        }

        $this->loginAs('loja@teste.test');
        $this->get('merchant/dashboard');
        $this->assertStringContainsString('<script src="/js/forms.js"></script>', $this->lastBody);
    }

    public function testErroApontaOCampoESucessoNao(): void {
        $this->get('merchant/register&error=documento_invalido');
        $this->assertStringContainsString(
            '<div class="alert alert-error" id="flash-error" role="alert" data-fields="document">',
            $this->lastBody
        );

        // login nao diz qual campo errou
        $this->get('merchant/login&error=credenciais_invalidas');
        $this->assertStringContainsString('<div class="alert alert-error" id="flash-error" role="alert">', $this->lastBody);

        $this->get('merchant/login&success=logout');
        $this->assertStringContainsString('<div class="alert alert-success" id="flash-success" role="status">', $this->lastBody);
    }

    public function testBarraDeProgressoUsaClasseDeLargura(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $card = $this->createCard($merchant, 'Bia', '11922220002');
        $this->createReward($merchant, 'Cafe', 30);
        (new LoyaltyCardModel($this->db))->addPoints($card, 10, 'Compra'); // 33% -> passo de 35
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customer&phone=11922220002');
        $this->assertStringContainsString('class="progress-bar progress-w-35"', $this->lastBody);
        $this->assertStringContainsString('aria-valuenow="33"', $this->lastBody);
    }
}
