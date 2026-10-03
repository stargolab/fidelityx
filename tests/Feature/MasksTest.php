<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// as paginas com telefone/documento carregam a mascara (a logica dela e testada em tests/js)
final class MasksTest extends HttpTestCase {
    public function testCadastroTemMascaraDeTelefoneEDocumento(): void {
        $this->get('merchant/register');
        $this->assertStringContainsString('data-mask="phone"', $this->lastBody);
        $this->assertStringContainsString('data-mask="document"', $this->lastBody);
        $this->assertStringContainsString('src="/js/masks.js"', $this->lastBody);
    }

    public function testConsultaPublicaTemMascaraDeTelefone(): void {
        $code = $this->publicCode($this->createMerchant('mascara@teste.test'));
        $this->get('customer/balance&loja=' . $code);
        $this->assertStringContainsString('data-mask="phone"', $this->lastBody);
        $this->assertStringContainsString('src="/js/masks.js"', $this->lastBody);
    }

    public function testBuscaDoBalcaoTemMascaraDeTelefone(): void {
        $this->createMerchant('mascara@teste.test');
        $this->loginAs('mascara@teste.test');
        $this->get('merchant/dashboard');
        $this->assertStringContainsString('data-mask="phone"', $this->lastBody);
        $this->assertStringContainsString('src="/js/masks.js"', $this->lastBody);
    }

    public function testBackEndContinuaAceitandoValorComMascara(): void {
        $this->createMerchant('mascara@teste.test');
        $this->loginAs('mascara@teste.test');
        [, $location] = $this->get('merchant/dashboard&phone=' . urlencode('(11) 93333-0003'));
        $this->assertSame('merchant/customer-new&phone=11933330003', $location);
    }
}
