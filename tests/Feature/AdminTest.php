<?php

namespace Tests\Feature;

use App\Models\AdminModel;
use Tests\Support\HttpTestCase;

// task 30: painel administrativo (login proprio, lista de lojas, ativar e desativar)
final class AdminTest extends HttpTestCase {
    private function createAdmin(string $email = 'admin@fidelityx.test', string $password = 'admin-senha-1'): int {
        return (new AdminModel($this->db))->create($email, 'Admin Teste', $password);
    }

    private function loginAdmin(string $email = 'admin@fidelityx.test', string $password = 'admin-senha-1'): ?string {
        return $this->post('admin/login', ['email' => $email, 'password' => $password])[1];
    }

    private function merchantStatus(int $merchantId): string {
        return (string)$this->scalar('SELECT status FROM merchants WHERE id = ?', [$merchantId]);
    }

    public function testPainelExigeLoginDeAdmin(): void {
        [$status, $location] = $this->get('admin/dashboard');
        $this->assertSame(302, $status);
        $this->assertSame('admin/login&error=sessao_expirada', $location);
        $this->assertSame('no-store', $this->lastHeaders['cache-control'][0]);

        // lojista logado nao e admin
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');
        $this->assertSame('admin/login&error=sessao_expirada', $this->get('admin/dashboard')[1]);
    }

    public function testLoginDeAdmin(): void {
        $this->createAdmin();
        // senha de lojista nao vale no admin (outra tabela)
        $this->createMerchant('admin@fidelityx.test');

        $this->assertSame('admin/login&error=credenciais_invalidas', $this->loginAdmin('admin@fidelityx.test', 'teste123'));
        $this->assertSame('admin/login&error=credenciais_invalidas', $this->loginAdmin('ninguem@fidelityx.test'));
        $this->assertSame('admin/dashboard', $this->loginAdmin());
        $this->assertSame(200, $this->get('admin/dashboard')[0]);
        $this->assertStringContainsString('Administração · Admin Teste', $this->lastBody);
    }

    public function testListaAsLojasSemDadosDosClientes(): void {
        $this->createAdmin();
        $loja = $this->createMerchant('loja@teste.test');
        $this->createCard($loja, 'Bia Cliente', '11922220002');
        $this->createCard($loja, 'Caio Cliente', '11933330003');
        $this->createMerchant('outra@teste.test');

        $this->loginAdmin();
        $this->get('admin/dashboard');

        $this->assertStringContainsString('2 lojas', $this->lastBody);
        $this->assertStringContainsString('loja@teste.test', $this->lastBody);
        $this->assertStringContainsString('outra@teste.test', $this->lastBody);
        $this->assertMatchesRegularExpression('#loja@teste\.test</td>\s*<td class="num">2</td>#', $this->lastBody);
        $this->assertStringNotContainsString('Bia Cliente', $this->lastBody);
        $this->assertStringNotContainsString('92222', $this->lastBody);
    }

    public function testDesativarDerrubaOLojistaNaHoraEAtivarDevolveOAcesso(): void {
        $this->createAdmin();
        $loja = $this->createMerchant('loja@teste.test');

        // o lojista esta logado em outro aparelho
        $this->loginAs('loja@teste.test');
        $merchantSession = $this->cookie('PHPSESSID');

        $this->newSession();
        $this->loginAdmin();
        [, $location] = $this->post('admin/dashboard', ['merchant_id' => $loja, 'status' => 'inactive', 'page' => 1]);
        $this->assertSame('admin/dashboard&page=1&success=loja_desativada', $location);
        $this->assertSame('inactive', $this->merchantStatus($loja));

        $this->newSession();
        $this->setCookie('PHPSESSID', (string)$merchantSession);
        $this->assertSame('merchant/login&error=conta_inativa', $this->get('merchant/dashboard')[1]);

        $this->newSession();
        $this->loginAdmin();
        [, $location] = $this->post('admin/dashboard', ['merchant_id' => $loja, 'status' => 'active', 'page' => 1]);
        $this->assertSame('admin/dashboard&page=1&success=loja_ativada', $location);
        $this->newSession();
        $this->loginAs('loja@teste.test');
    }

    public function testAcaoInvalidaOuSemCsrfNaoMudaNada(): void {
        $this->createAdmin();
        $loja = $this->createMerchant('loja@teste.test');
        $this->loginAdmin();

        $this->assertSame('admin/dashboard&page=1&error=loja_invalida', $this->post('admin/dashboard', ['merchant_id' => $loja, 'status' => 'apagada'])[1]);
        $this->assertSame('admin/dashboard&page=1&error=loja_invalida', $this->post('admin/dashboard', ['merchant_id' => 999999, 'status' => 'inactive'])[1]);

        [$status] = $this->post('admin/dashboard', ['merchant_id' => $loja, 'status' => 'inactive'], false);
        $this->assertSame(403, $status);
        $this->assertSame('active', $this->merchantStatus($loja));
    }

    public function testSessaoDeAdminCaiQuandoASenhaMudaENoLogout(): void {
        $id = $this->createAdmin();
        $this->loginAdmin();

        [, $location] = $this->post('admin/logout', [], true, 'admin/dashboard');
        $this->assertSame('admin/login&success=logout', $location);
        $this->assertSame('admin/login&error=sessao_expirada', $this->get('admin/dashboard')[1]);

        $this->loginAdmin();
        $this->db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash('outra-senha-1', PASSWORD_BCRYPT), $id]);
        $this->assertSame('admin/login&error=sessao_expirada', $this->get('admin/dashboard')[1]);
    }

    public function testErrosDemaisNoLoginDeAdminDao429(): void {
        $this->createAdmin();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('admin/login&error=credenciais_invalidas', $this->loginAdmin('admin@fidelityx.test', 'errada-123'));
        }
        [$status] = $this->post('admin/login', ['email' => 'admin@fidelityx.test', 'password' => 'admin-senha-1']);
        $this->assertSame(429, $status);
    }

    // o script de linha de comando cria o admin (aqui, no banco de teste)
    public function testScriptCriaOAdmin(): void {
        $env = getenv();
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
            $env[$key] = (string)($_ENV[$key] ?? '');
        }
        $run = function (array $args, string $input) use ($env): array {
            $process = proc_open(
                array_merge([PHP_BINARY, dirname(__DIR__, 2) . '/bin/create-admin.php'], $args),
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                $env
            );
            fwrite($pipes[0], $input);
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            return [proc_close($process), $out];
        };

        [$code, $out] = $run(['novo@fidelityx.test', 'Nova Admin'], "curta\n");
        $this->assertSame(1, $code);
        $this->assertStringContainsString('a senha precisa ter de 8 a 72 bytes', $out);

        [$code, $out] = $run(['novo@fidelityx.test', 'Nova Admin'], "senha-forte-1\n");
        $this->assertSame(0, $code, $out);
        $this->assertSame('Nova Admin', $this->scalar("SELECT name FROM admins WHERE email = 'novo@fidelityx.test'"));
        $this->assertSame('admin/dashboard', $this->loginAdmin('novo@fidelityx.test', 'senha-forte-1'));

        [$code, $out] = $run(['novo@fidelityx.test', 'Nova Admin'], "senha-forte-1\n");
        $this->assertSame(1, $code);
        $this->assertStringContainsString('ja existe admin com esse e-mail', $out);
    }
}
