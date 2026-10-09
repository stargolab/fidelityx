<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// task 21: recuperacao de senha por e-mail (token aleatorio, uso unico, 1 hora, guardado como hash)
final class PasswordResetTest extends HttpTestCase {
    private function requestLink(string $email): ?string {
        return $this->post('merchant/forgot', ['email' => $email])[1];
    }

    // token do link do ultimo e-mail enviado
    private function tokenFromMail(): string {
        $mails = $this->sentMails();
        $this->assertNotEmpty($mails, 'nenhum e-mail enviado');
        $this->assertMatchesRegularExpression('#http://127\.0\.0\.1:\d+/index\.php\?url=merchant%2Freset&token=([a-f0-9]{64})#', end($mails)['body']);
        preg_match('#token=([a-f0-9]{64})#', end($mails)['body'], $m);
        return $m[1];
    }

    private function reset(string $token, string $password, ?string $confirm = null): ?string {
        return $this->post(
            'merchant/reset',
            ['token' => $token, 'new_password' => $password, 'new_password_confirm' => $confirm ?? $password],
            true,
            'merchant/reset&token=' . $token
        )[1];
    }

    public function testLoginTemOLinkDeEsqueciASenha(): void {
        $this->get('merchant/login');
        $this->assertStringContainsString('merchant%2Fforgot', $this->lastBody);
        $this->assertSame(200, $this->get('merchant/forgot')[0]);
    }

    public function testFluxoCompletoTrocaASenhaEOLinkSoValeUmaVez(): void {
        $this->createMerchant('loja@teste.test');

        $this->assertSame('merchant/forgot&success=reset_enviado', $this->requestLink('loja@teste.test'));
        $mail = $this->sentMails()[0];
        $this->assertSame('loja@teste.test', $mail['to']);
        $this->assertStringContainsString('1 hora', $mail['body']);
        $token = $this->tokenFromMail();

        // o banco guarda so o hash do token
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [$token]));
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [hash('sha256', $token)]));

        [$status] = $this->get('merchant/reset&token=' . $token);
        $this->assertSame(200, $status);
        $this->assertStringContainsString('name="token" value="' . $token . '"', $this->lastBody);

        $this->assertSame('merchant/reset&token=' . $token . '&error=senha_curta', $this->reset($token, '1234567'));
        $this->assertSame('merchant/reset&token=' . $token . '&error=senhas_diferentes', $this->reset($token, 'nova-senha-1', 'outra-senha-1'));
        $this->assertSame('merchant/login&success=senha_redefinida', $this->reset($token, 'nova-senha-1'));

        $this->newSession();
        [, $location] = $this->post('merchant/login', ['email' => 'loja@teste.test', 'password' => 'teste123']);
        $this->assertSame('merchant/login&error=credenciais_invalidas', $location);
        $this->loginAs('loja@teste.test', 'nova-senha-1');

        // de novo: o link ja foi usado
        $this->newSession();
        [, $location] = $this->get('merchant/reset&token=' . $token);
        $this->assertSame('merchant/forgot&error=link_invalido', $location);
    }

    public function testEmailQueNaoELojaRespondeIgualENaoMandaNada(): void {
        $this->assertSame('merchant/forgot&success=reset_enviado', $this->requestLink('ninguem@teste.test'));
        $this->assertSame([], $this->sentMails());
        $this->assertSame('merchant/forgot&error=email_invalido', $this->requestLink('nao-e-email'));
    }

    public function testContaDesativadaNaoRecebeLink(): void {
        $id = $this->createMerchant('loja@teste.test');
        $this->db->exec("UPDATE merchants SET status = 'inactive' WHERE id = $id");

        $this->assertSame('merchant/forgot&success=reset_enviado', $this->requestLink('loja@teste.test'));
        $this->assertSame([], $this->sentMails());
    }

    public function testLinkVencidoOuInventadoNaoVale(): void {
        $this->createMerchant('loja@teste.test');
        $this->requestLink('loja@teste.test');
        $token = $this->tokenFromMail();

        foreach (['', 'abc', str_repeat('a', 64)] as $fake) {
            [, $location] = $this->get('merchant/reset&token=' . $fake);
            $this->assertSame('merchant/forgot&error=link_invalido', $location, $fake);
        }

        $this->db->exec('UPDATE password_resets SET expires_at = NOW() - INTERVAL 1 SECOND');
        [, $location] = $this->get('merchant/reset&token=' . $token);
        $this->assertSame('merchant/forgot&error=link_invalido', $location);

        // POST direto com o token vencido tambem nao troca
        $this->get('merchant/forgot');
        [, $location] = $this->post('merchant/reset', ['token' => $token, 'new_password' => 'nova-senha-1', 'new_password_confirm' => 'nova-senha-1'], true, 'merchant/forgot');
        $this->assertSame('merchant/forgot&error=link_invalido', $location);
        $this->loginAs('loja@teste.test');
    }

    public function testSoOUltimoLinkPedidoVale(): void {
        $this->createMerchant('loja@teste.test');
        $this->requestLink('loja@teste.test');
        $first = $this->tokenFromMail();
        $this->requestLink('loja@teste.test');
        $second = $this->tokenFromMail();

        $this->assertNotSame($first, $second);
        $this->assertSame('merchant/forgot&error=link_invalido', $this->get('merchant/reset&token=' . $first)[1]);
        $this->assertSame(200, $this->get('merchant/reset&token=' . $second)[0]);
    }

    // a senha nova derruba quem estava logado com a antiga
    public function testRedefinirDerrubaAsSessoesAbertas(): void {
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');
        $logged = $this->cookie('PHPSESSID');

        $this->newSession();
        $this->requestLink('loja@teste.test');
        $this->reset($this->tokenFromMail(), 'nova-senha-1');

        $this->newSession();
        $this->setCookie('PHPSESSID', (string)$logged);
        [, $location] = $this->get('merchant/dashboard');
        $this->assertSame('merchant/login&error=sessao_expirada', $location);
    }

    public function testPedidosDemaisParaOMesmoEmailSaoBarrados(): void {
        $this->createMerchant('loja@teste.test');
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame('merchant/forgot&success=reset_enviado', $this->requestLink('loja@teste.test'));
        }
        $this->assertSame('merchant/forgot&error=muitos_pedidos', $this->requestLink('loja@teste.test'));
        $this->assertCount(3, $this->sentMails());
    }

    public function testPedidoExigeCsrf(): void {
        $this->createMerchant('loja@teste.test');
        [$status] = $this->post('merchant/forgot', ['email' => 'loja@teste.test'], false);
        $this->assertSame(403, $status);
        $this->assertSame([], $this->sentMails());
    }
}
