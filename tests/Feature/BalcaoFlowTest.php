<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// fluxo completo por http, do jeito que o lojista e o cliente usam o sistema
final class BalcaoFlowTest extends HttpTestCase {
    public function testFluxoCompletoDoCadastroDaLojaAoResgate(): void {
        // cadastro e login do lojista
        [$status, $location] = $this->post('merchant/register', [
            'owner_name' => 'Dona Ana', 'shop_name' => 'Padaria da Ana', 'address' => 'Rua 1',
            'state' => 'SP', 'city' => 'Sao Paulo', 'email' => 'padaria@teste.test',
            'phone' => '(11) 98888-7777', 'category' => 'alimentacao', 'document' => '529.982.247-25',
            'password' => 'teste123', 'password_confirm' => 'teste123',
        ]);
        $this->assertSame('merchant/login&success=cadastrado', $location);
        $this->loginAs('padaria@teste.test');
        $this->assertSame('merchant/dashboard&success=email_confirmado', $this->confirmEmailFromMail('padaria@teste.test'));

        // premio
        [, $location] = $this->post('merchant/rewards', ['action' => 'create', 'name' => 'Cafe', 'points_cost' => 20]);
        $this->assertSame('merchant/rewards&success=premio_criado', $location);
        $cafeId = (int)$this->scalar("SELECT id FROM rewards WHERE name = 'Cafe'");

        // home: telefone novo vai pro cadastro rapido (a mascara da busca e aceita)
        [$status] = $this->get('merchant/dashboard');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('inputmode="numeric"', $this->lastBody);
        [, $location] = $this->get('merchant/dashboard&phone=' . urlencode('(11) 91111-0001'));
        $this->assertSame('merchant/customer-new&phone=11911110001', $location);

        // cadastro rapido: consentimento obrigatorio, depois cai na tela do cliente
        [, $location] = $this->post('merchant/customer-new', ['phone' => '11911110001', 'name' => 'Bia Souza'], true, 'merchant/customer-new&phone=11911110001');
        $this->assertSame('merchant/customer-new&phone=11911110001&error=consentimento_obrigatorio', $location);
        [, $location] = $this->post('merchant/customer-new', ['phone' => '11911110001', 'name' => 'Bia Souza', 'consent' => '1'], true, 'merchant/customer-new&phone=11911110001');
        $this->assertSame('merchant/customer&phone=11911110001&success=cliente_cadastrado', $location);

        // tela do cliente: lancar pontos e o saldo atualiza na hora
        $screen = 'merchant/customer&phone=11911110001';
        [, $location] = $this->post('merchant/customer', ['action' => 'score', 'phone' => '11911110001', 'points' => 30], true, $screen);
        $this->assertMatchesRegularExpression('/^merchant\/customer&phone=11911110001&lancamento=\d+$/', $location);
        $this->get($screen);
        $this->assertStringContainsString('balance-value">30<', $this->lastBody);
        $this->assertStringContainsString('name="reward_id" value="' . $cafeId . '"', $this->lastBody, 'premio que o saldo paga aparece');

        // resgate
        [, $location] = $this->post('merchant/customer', ['action' => 'redeem', 'phone' => '11911110001', 'reward_id' => $cafeId], true, $screen);
        $this->assertSame('merchant/customer&phone=11911110001&success=resgate_realizado', $location);
        $this->get($screen);
        $this->assertStringContainsString('balance-value">10<', $this->lastBody);

        // historico gravado
        $this->assertSame(
            'earn:30,redeem:20',
            $this->scalar("SELECT GROUP_CONCAT(CONCAT(type, ':', quantity) ORDER BY id) FROM points_log")
        );

        // cliente ja cadastrado: a busca vai direto pra tela dele (2 telas)
        [, $location] = $this->get('merchant/dashboard&phone=11911110001');
        $this->assertSame('merchant/customer&phone=11911110001', $location);

        // consulta publica (pelo codigo da loja, como no QR): saldo na loja e so o primeiro nome
        $code = (string)$this->scalar("SELECT public_code FROM merchants WHERE email = 'padaria@teste.test'");
        $this->newSession();
        [$status] = $this->post('customer/balance', ['phone' => '11911110001', 'loja' => $code], true, 'customer/balance&loja=' . $code);
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Padaria da Ana', $this->lastBody);
        $this->assertStringContainsString('Bia', $this->lastBody);
        $this->assertStringNotContainsString('Souza', $this->lastBody);
    }

    public function testLojaNaoMexeEmClienteNemPremioDeOutra(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $this->createMerchant('b@teste.test');
        $premioA = $this->createReward($lojaA, 'Cafe', 1);
        $cardA = $this->createCard($lojaA, 'Carla Mendes', '11922220002');
        $this->db->exec("UPDATE loyalty_cards SET current_points = 50 WHERE id = $cardA");

        $this->loginAs('b@teste.test');

        // lancar pontos num cliente que nao e da loja B
        [, $location] = $this->post('merchant/customer', ['action' => 'score', 'phone' => '11922220002', 'points' => 5], true, 'merchant/rewards');
        $this->assertSame('merchant/dashboard&error=cliente_nao_encontrado', $location);

        // mesmo depois de virar cliente da loja B (cadastro rapido), o premio da loja A nao vale ali
        $this->post('merchant/customer-new', ['phone' => '11922220002', 'name' => 'Carla', 'consent' => '1'], true, 'merchant/customer-new&phone=11922220002');
        [, $location] = $this->post('merchant/customer', ['action' => 'redeem', 'phone' => '11922220002', 'reward_id' => $premioA['id']], true, 'merchant/rewards');
        $this->assertSame('merchant/customer&phone=11922220002&error=premio_invalido', $location);

        $this->assertSame(50, (int)$this->scalar('SELECT current_points FROM loyalty_cards WHERE merchant_id = ?', [$lojaA]));
    }

    public function testRotasPrivadasExigemLoginEPostExigeCsrf(): void {
        foreach (['merchant/dashboard', 'merchant/customer&phone=11911110001', 'merchant/customer-new&phone=11911110001', 'merchant/customers', 'merchant/rewards'] as $route) {
            [, $location] = $this->get($route);
            $this->assertSame('merchant/login&error=sessao_expirada', $location, $route);
        }

        $this->createMerchant('a@teste.test');
        $this->loginAs('a@teste.test');
        [$status] = $this->post('merchant/customer', ['action' => 'score', 'phone' => '11911110001', 'points' => 5], false);
        $this->assertSame(403, $status);
    }

    public function testRotasAntigasESumiram(): void {
        $this->assertSame(404, $this->get('merchant/score')[0]);
        $this->assertSame(404, $this->get('merchant/redeem')[0]);
        $this->assertSame(404, $this->get('merchant/nao-existe')[0]);
    }

    public function testLoginBloqueiaDepoisDeCincoErros(): void {
        $this->createMerchant('a@teste.test');

        for ($i = 0; $i < 5; $i++) {
            [, $location] = $this->post('merchant/login', ['email' => 'a@teste.test', 'password' => 'errada']);
            $this->assertSame('merchant/login&error=credenciais_invalidas', $location);
        }

        // 6a tentativa e bloqueada mesmo com a senha certa, e numa sessao nova tambem
        $this->newSession();
        [$status] = $this->post('merchant/login', ['email' => 'a@teste.test', 'password' => 'teste123']);
        $this->assertSame(429, $status);
    }

    public function testConsultaPublicaLimitadaPorIpMesmoComSessaoNova(): void {
        $code = $this->publicCode($this->createMerchant('a@teste.test'));
        $page = 'customer/balance&loja=' . $code;
        $fields = ['phone' => '11911110001', 'loja' => $code];

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(200, $this->post('customer/balance', $fields, true, $page)[0]);
        }
        $this->assertSame(429, $this->post('customer/balance', $fields, true, $page)[0]);

        $this->newSession();
        $this->assertSame(429, $this->post('customer/balance', $fields, true, $page)[0]);
    }
}
