<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// task 43: corrigir nome e trocar telefone pela tela do cliente
final class CardCorrectionScreenTest extends HttpTestCase {
    private const SCREEN = 'merchant/customer&phone=11922220002';

    private function send(array $fields): ?string {
        return $this->post('merchant/customer', $fields + ['phone' => '11922220002'], true, self::SCREEN)[1];
    }

    public function testCorrigeONome(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->createCard($merchant, 'Bai Souza', '11922220002');
        $this->loginAs('loja@teste.test');

        $this->get(self::SCREEN);
        $this->assertStringContainsString('Corrigir nome ou trocar telefone', $this->lastBody);

        $this->assertSame(self::SCREEN . '&error=nome_invalido', $this->send(['action' => 'rename', 'name' => '  ']));
        $this->assertSame(self::SCREEN . '&success=nome_corrigido', $this->send(['action' => 'rename', 'name' => ' Bia Souza ']));

        $this->get(self::SCREEN);
        $this->assertStringContainsString('<div class="customer-name">Bia Souza</div>', $this->lastBody);
    }

    public function testTrocaOTelefoneELevaOSaldo(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $card = $this->createCard($merchant, 'Bia', '11922220002');
        (new \App\Models\LoyaltyCardModel($this->db))->addPoints($card, 40, 'Compra');
        $this->loginAs('loja@teste.test');

        $this->assertSame(self::SCREEN . '&error=telefone_novo_invalido', $this->send(['action' => 'change_phone', 'new_phone' => '123']));
        $this->assertSame(self::SCREEN . '&error=telefone_igual', $this->send(['action' => 'change_phone', 'new_phone' => '(11) 92222-0002']));

        $this->assertSame(
            'merchant/customer&phone=11955550005&success=telefone_trocado',
            $this->send(['action' => 'change_phone', 'new_phone' => '(11) 95555-0005'])
        );
        $this->get('merchant/customer&phone=11955550005');
        $this->assertStringContainsString('customer-balance-value">40<', $this->lastBody);

        // o numero antigo virou "telefone novo" para esta loja: cadastro rapido
        [, $location] = $this->get('merchant/dashboard&phone=11922220002');
        $this->assertSame('merchant/customer-new&phone=11922220002', $location);
    }

    // a recusa so fala desta loja; telefone de cliente de outra loja segue como qualquer numero novo (task 11)
    public function testTelefoneDeClienteDestaLojaERecusadoEDeOutraLojaNao(): void {
        $loja = $this->createMerchant('loja@teste.test');
        $outra = $this->createMerchant('outra@teste.test');
        $this->createCard($loja, 'Bia', '11922220002');
        $this->createCard($loja, 'Caio', '11933330003');
        $this->createCard($outra, 'Dani', '11944440004');
        $this->loginAs('loja@teste.test');

        $this->assertSame(self::SCREEN . '&error=telefone_ja_cliente', $this->send(['action' => 'change_phone', 'new_phone' => '11933330003']));
        $this->assertSame(
            'merchant/customer&phone=11944440004&success=telefone_trocado',
            $this->send(['action' => 'change_phone', 'new_phone' => '11944440004'])
        );
        $this->get('merchant/customer&phone=11944440004');
        $this->assertStringContainsString('Bia', $this->lastBody);
        $this->assertStringNotContainsString('Dani', $this->lastBody);
    }

    public function testCorrecaoExigeCsrf(): void {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->createCard($merchant, 'Bia', '11922220002');
        $this->loginAs('loja@teste.test');

        [$status] = $this->post('merchant/customer', ['action' => 'rename', 'phone' => '11922220002', 'name' => 'X'], false);
        $this->assertSame(403, $status);
        $this->assertSame('Bia', $this->scalar('SELECT customer_name FROM loyalty_cards'));
    }
}
