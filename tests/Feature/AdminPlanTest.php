<?php

namespace Tests\Feature;

use App\Models\AdminModel;
use Tests\Support\HttpTestCase;

// task 32: o administrador troca o plano da loja (Free <-> Pro) na lista de lojas
final class AdminPlanTest extends HttpTestCase {
    private const SCREEN = 'admin/dashboard';

    private function loginAdmin(): void {
        (new AdminModel($this->db))->create('admin@fidelityx.test', 'Ana Admin', 'admin-senha-1');
        [, $location] = $this->post('admin/login', ['email' => 'admin@fidelityx.test', 'password' => 'admin-senha-1']);
        $this->assertSame(self::SCREEN, $location, 'login de administrador falhou');
    }

    private function plan(int $merchant): string {
        return (string)$this->scalar('SELECT plan FROM merchants WHERE id = ?', [$merchant]);
    }

    private function setPlan($merchantId, string $plan, array $extra = []): array {
        return $this->post(self::SCREEN, ['action' => 'plan', 'merchant_id' => $merchantId, 'plan' => $plan] + $extra, true, self::SCREEN);
    }

    public function testListaMostraOPlanoDeCadaLoja(): void {
        $free = $this->createMerchant('free@teste.test');
        $pro = $this->createMerchant('pro@teste.test');
        $this->db->exec("UPDATE merchants SET plan = 'pro' WHERE id = $pro");
        $this->loginAdmin();

        $this->get(self::SCREEN);
        $this->assertStringContainsString('<th>Plano</th>', $this->lastBody);
        $this->assertMatchesRegularExpression('/id="plan-' . $free . '">\s*<option value="free" selected>Free<\/option>/', $this->lastBody);
        $this->assertMatchesRegularExpression('/id="plan-' . $pro . '">.*?<option value="pro" selected>Pro<\/option>/s', $this->lastBody);
    }

    public function testAdminPassaALojaParaProEVoltaParaFree(): void {
        $loja = $this->createMerchant('loja@teste.test');
        $this->loginAdmin();
        $this->assertSame('free', $this->plan($loja));

        [, $location] = $this->setPlan($loja, 'pro');
        $this->assertSame(self::SCREEN . '&page=1&success=plano_alterado', $location);
        $this->assertSame('pro', $this->plan($loja));

        $this->get(self::SCREEN . '&success=plano_alterado');
        $this->assertStringContainsString('Plano da loja alterado', $this->lastBody);

        $this->setPlan($loja, 'free');
        $this->assertSame('free', $this->plan($loja));
    }

    // o efeito pratico: loja no limite do Free volta a cadastrar premio assim que vira Pro
    public function testTrocarParaProLiberaOLimiteNaHora(): void {
        $loja = $this->createMerchant('loja@teste.test');
        foreach (['Cafe', 'Suco', 'Bolo'] as $name) {
            $this->createReward($loja, $name, 10);
        }

        $this->loginAs('loja@teste.test');
        $novo = ['action' => 'create', 'name' => 'Torta', 'points_cost' => '10'];
        $this->assertSame('merchant/rewards&error=limite_premios', $this->post('merchant/rewards', $novo, true, 'merchant/rewards')[1]);

        // admin em outro navegador troca o plano; o lojista continua logado
        $lojista = $this->cookie('PHPSESSID');
        $this->newSession();
        $this->loginAdmin();
        $this->setPlan($loja, 'pro');

        $this->newSession();
        $this->setCookie('PHPSESSID', (string)$lojista);
        $this->assertSame('merchant/rewards&success=premio_criado', $this->post('merchant/rewards', $novo, true, 'merchant/rewards')[1]);
    }

    public function testTrocarOPlanoDeUmaLojaNaoMexeNasOutrasNemNoStatus(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $lojaB = $this->createMerchant('b@teste.test');
        $this->loginAdmin();

        $this->setPlan($lojaA, 'pro');
        $this->assertSame('pro', $this->plan($lojaA));
        $this->assertSame('free', $this->plan($lojaB));
        $this->assertSame('active', $this->scalar('SELECT status FROM merchants WHERE id = ?', [$lojaA]));
    }

    public function testPlanoQueNaoExisteOuLojaInvalidaDaErro(): void {
        $loja = $this->createMerchant('loja@teste.test');
        $this->loginAdmin();

        $erro = self::SCREEN . '&page=1&error=loja_invalida';
        foreach (['premium', '', 'PRO', 'free; DROP TABLE merchants'] as $plan) {
            $this->assertSame($erro, $this->setPlan($loja, $plan)[1], $plan);
        }
        $this->assertSame($erro, $this->setPlan(999999, 'pro')[1]);
        $this->assertSame($erro, $this->setPlan('abc', 'pro')[1]);
        $this->assertSame('free', $this->plan($loja));
    }

    public function testVoltaParaAMesmaPaginaDaLista(): void {
        $loja = $this->createMerchant('loja@teste.test');
        $this->loginAdmin();

        [, $location] = $this->setPlan($loja, 'pro', ['page' => '3']);
        $this->assertSame(self::SCREEN . '&page=3&success=plano_alterado', $location);
    }

    // quem troca plano e so o administrador: lojista logado nao consegue virar Pro sozinho
    public function testLojistaNaoTrocaOProprioPlano(): void {
        $loja = $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        [, $location] = $this->post(self::SCREEN, ['action' => 'plan', 'merchant_id' => $loja, 'plan' => 'pro'], true, 'merchant/dashboard');
        $this->assertSame('admin/login&error=sessao_expirada', $location);
        $this->assertSame('free', $this->plan($loja));

        // e nenhuma tela do lojista aceita o campo plan
        $this->post('merchant/profile', ['plan' => 'pro', 'owner_name' => 'Dona', 'shop_name' => 'Loja', 'category' => 'varejo',
            'phone' => '11988887777', 'address' => 'Rua 1', 'city' => 'Sao Paulo', 'state' => 'SP'], true, 'merchant/profile');
        $this->assertSame('free', $this->plan($loja));
    }

    public function testSemCsrfNaoTroca(): void {
        $loja = $this->createMerchant('loja@teste.test');
        $this->loginAdmin();

        [$status] = $this->post(self::SCREEN, ['action' => 'plan', 'merchant_id' => $loja, 'plan' => 'pro'], false);
        $this->assertSame(403, $status);
        $this->assertSame('free', $this->plan($loja));
    }

    // a troca de plano nao atrapalha o ativar/desativar que ja existia na mesma tela
    public function testAtivarEDesativarContinuamFuncionando(): void {
        $loja = $this->createMerchant('loja@teste.test');
        $this->loginAdmin();

        [, $location] = $this->post(self::SCREEN, ['merchant_id' => $loja, 'status' => 'inactive'], true, self::SCREEN);
        $this->assertSame(self::SCREEN . '&page=1&success=loja_desativada', $location);
        $this->assertSame('inactive', $this->scalar('SELECT status FROM merchants WHERE id = ?', [$loja]));
        $this->assertSame('free', $this->plan($loja));
    }
}
