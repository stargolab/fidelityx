<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// task 50: conta nova so usa o painel depois de confirmar o e-mail
final class EmailVerificationTest extends HttpTestCase {
    private function register(string $email = 'padaria@teste.test'): void {
        [, $location] = $this->post('merchant/register', [
            'owner_name' => 'Dona Ana', 'shop_name' => 'Padaria da Ana', 'address' => 'Rua 1',
            'state' => 'SP', 'city' => 'Sao Paulo', 'email' => $email,
            'phone' => '11988887777', 'category' => 'alimentacao', 'document' => '52998224725',
            'password' => 'teste123', 'password_confirm' => 'teste123',
        ]);
        $this->assertSame('merchant/login&success=cadastrado', $location);
    }

    private function lastToken(): string {
        $mails = $this->sentMails();
        preg_match('#verify-email&token=([a-f0-9]{64})#', (string)end($mails)['body'], $m);
        $this->assertNotEmpty($m, 'sem link de confirmacao');
        return $m[1];
    }

    public function testCadastroMandaOLinkEOPainelEsperaAConfirmacao(): void {
        $this->register();

        $mails = $this->sentMails();
        $this->assertCount(1, $mails);
        $this->assertSame('padaria@teste.test', $mails[0]['to']);
        $this->assertStringContainsString('24 horas', $mails[0]['body']);
        $this->assertNull($this->scalar('SELECT email_verified_at FROM merchants'));

        // entra, mas toda tela do painel leva pra confirmacao
        $this->loginAs('padaria@teste.test');
        foreach (['merchant/dashboard', 'merchant/customers', 'merchant/rewards', 'merchant/profile', 'merchant/export&tipo=clientes'] as $route) {
            [$status, $location] = $this->get($route);
            $this->assertSame(302, $status, $route);
            $this->assertSame('merchant/confirm-email', $location, $route);
        }
        [$status] = $this->post('merchant/rewards', ['action' => 'create', 'name' => 'Cafe', 'points_cost' => 10], true, 'merchant/confirm-email');
        $this->assertSame(302, $status);
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM rewards'));

        $this->get('merchant/confirm-email');
        $this->assertStringContainsString('padaria@teste.test', $this->lastBody);

        // confirma pelo link (logado: volta pro painel)
        $this->assertSame('merchant/dashboard&success=email_confirmado', $this->confirmEmailFromMail('padaria@teste.test'));
        $this->assertNotNull($this->scalar('SELECT email_verified_at FROM merchants'));
        $this->assertSame(200, $this->get('merchant/dashboard')[0]);
        [, $location] = $this->get('merchant/confirm-email');
        $this->assertSame('merchant/dashboard', $location, 'ja confirmado nao ve mais a tela');
    }

    public function testLinkAbertoSemLoginConfirmaEUmaVezSo(): void {
        $this->register();
        $token = $this->lastToken();

        $this->newSession();
        [, $location] = $this->get('merchant/verify-email&token=' . $token);
        $this->assertSame('merchant/login&success=email_confirmado', $location);
        [, $location] = $this->get('merchant/verify-email&token=' . $token);
        $this->assertSame('merchant/login&error=confirmacao_invalida', $location);
    }

    public function testLinkVencidoOuInventadoNaoConfirma(): void {
        $this->register();
        $token = $this->lastToken();

        $this->assertSame('merchant/login&error=confirmacao_invalida', $this->get('merchant/verify-email&token=' . str_repeat('a', 64))[1]);
        $this->assertSame('merchant/login&error=confirmacao_invalida', $this->get('merchant/verify-email')[1]);

        $this->db->exec('UPDATE email_verifications SET expires_at = NOW() - INTERVAL 1 SECOND');
        $this->assertSame('merchant/login&error=confirmacao_invalida', $this->get('merchant/verify-email&token=' . $token)[1]);
        $this->assertNull($this->scalar('SELECT email_verified_at FROM merchants'));
    }

    public function testReenviarCancelaOLinkAnteriorETemLimite(): void {
        $this->register();
        $first = $this->lastToken();
        $this->loginAs('padaria@teste.test');

        [, $location] = $this->post('merchant/confirm-email', []);
        $this->assertSame('merchant/confirm-email&success=confirmacao_reenviada', $location);
        $second = $this->lastToken();
        $this->assertNotSame($first, $second);
        $this->assertSame('merchant/confirm-email&error=confirmacao_invalida', $this->get('merchant/verify-email&token=' . $first)[1]);

        $this->post('merchant/confirm-email', []);
        $this->post('merchant/confirm-email', []);
        [, $location] = $this->post('merchant/confirm-email', []);
        $this->assertSame('merchant/confirm-email&error=muitos_pedidos', $location);
        $this->assertCount(4, $this->sentMails(), 'cadastro + 3 reenvios');

        [$status] = $this->post('merchant/confirm-email', [], false);
        $this->assertSame(403, $status, 'reenvio exige csrf');
    }

    // contas criadas antes da regra (e as de teste, criadas direto no banco) ja contam como confirmadas
    public function testContaJaConfirmadaUsaOPainelNormalmente(): void {
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');
        $this->assertSame(200, $this->get('merchant/dashboard')[0]);
        $this->assertSame([], $this->sentMails());
    }

    public function testMigration009MarcaContasExistentesComoConfirmadas(): void {
        $sql = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/009_email_verifications.sql');
        $this->assertStringContainsString('UPDATE merchants SET email_verified_at = created_at WHERE email_verified_at IS NULL', $sql);
    }
}
