<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// as paginas com formulario carregam o script do botao "carregando" (a logica dele e testada em tests/js)
final class FormsScriptTest extends HttpTestCase {
    public function testLoginECadastroCarregamOScript(): void {
        $this->get('merchant/login');
        $this->assertStringContainsString('src="/js/forms.js"', $this->lastBody);

        $this->get('merchant/register');
        $this->assertStringContainsString('src="/js/forms.js"', $this->lastBody);
    }

    public function testConsultaPublicaCarregaOScript(): void {
        $code = $this->publicCode($this->createMerchant('forms@teste.test'));
        $this->get('customer/balance&loja=' . $code);
        $this->assertStringContainsString('src="/js/forms.js"', $this->lastBody);
    }

    public function testPainelCarregaOScript(): void {
        $this->createMerchant('forms@teste.test');
        $this->loginAs('forms@teste.test');
        $this->get('merchant/dashboard');
        $this->assertStringContainsString('src="/js/forms.js"', $this->lastBody);
    }

    public function testOArquivoCompiladoExiste(): void {
        $this->assertFileExists(__DIR__ . '/../../public/js/forms.js');
    }
}
