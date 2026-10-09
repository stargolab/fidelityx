<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// perfil do lojista (task 20): editar os dados da loja e trocar a senha informando a atual
final class ProfileTest extends HttpTestCase {
    private const DADOS = [
        'owner_name' => 'Ana Souza',
        'shop_name'  => 'Padaria da Ana',
        'phone'      => '(11) 97777-6666',
        'category'   => 'alimentacao',
        'address'    => 'Rua Nova, 10',
        'city'       => 'Cotia',
        'state'      => 'SP',
    ];

    private function merchantField(int $id, string $field) {
        return $this->scalar("SELECT $field FROM merchants WHERE id = :id", [':id' => $id]);
    }

    private function changePassword(string $current, string $new, ?string $confirm = null): ?string {
        return $this->post('merchant/profile', [
            'action' => 'password',
            'current_password' => $current,
            'new_password' => $new,
            'new_password_confirm' => $confirm ?? $new,
        ])[1];
    }

    public function testPerfilExigeLogin(): void {
        [$status, $location] = $this->get('merchant/profile');

        $this->assertSame(302, $status);
        $this->assertSame('merchant/login&error=sessao_expirada', $location);
    }

    public function testTelaMostraOsDadosAtuaisEOMenuTemOPerfil(): void {
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        [$status] = $this->get('merchant/profile');

        $this->assertSame(200, $status);
        $this->assertStringContainsString('value="Loja Teste"', $this->lastBody);
        $this->assertStringContainsString('value="Dona Teste"', $this->lastBody);
        $this->assertStringContainsString('value="(11) 98888-7777"', $this->lastBody);
        $this->assertStringContainsString('value="loja@teste.test" disabled', $this->lastBody);
        $this->assertStringContainsString('<option value="SP" selected>', $this->lastBody);
        $this->assertStringContainsString('href="/index.php?url=merchant%2Fprofile"', $this->lastBody);
        $this->assertStringNotContainsString('$2y$', $this->lastBody, 'o hash da senha nunca vai pra tela');
    }

    public function testSalvaOsDadosDaLoja(): void {
        $id = $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        [, $location] = $this->post('merchant/profile', self::DADOS);

        $this->assertSame('merchant/profile&success=perfil_atualizado', $location);
        $this->assertSame('Padaria da Ana', $this->merchantField($id, 'store_name'));
        $this->assertSame('Ana Souza', $this->merchantField($id, 'owner_name'));
        $this->assertSame('11977776666', $this->merchantField($id, 'phone'), 'telefone gravado so com digitos');
        $this->assertSame('alimentacao', $this->merchantField($id, 'category'));
        $this->assertSame('Cotia', $this->merchantField($id, 'city'));

        // o nome novo aparece no menu ja na proxima pagina
        $this->get('merchant/profile');
        $this->assertStringContainsString('Padaria da Ana', $this->lastBody);
    }

    // e-mail e o login e o documento e a identidade da conta: mesmo forjando o post, nao mudam
    public function testEmailEDocumentoNaoSaoAlteradosPeloFormulario(): void {
        $id = $this->createMerchant('loja@teste.test');
        $cpf = $this->merchantField($id, 'cpf');
        $this->loginAs('loja@teste.test');

        $this->post('merchant/profile', self::DADOS + [
            'email' => 'invasor@teste.test', 'cpf' => '52998224725', 'document' => '52998224725',
            'status' => 'inactive', 'plan' => 'pro', 'password_hash' => 'x',
        ]);

        $this->assertSame('loja@teste.test', $this->merchantField($id, 'email'));
        $this->assertSame($cpf, $this->merchantField($id, 'cpf'));
        $this->assertSame('active', $this->merchantField($id, 'status'));
        $this->assertSame('free', $this->merchantField($id, 'plan'));
    }

    public function testDadosInvalidosNaoSaoGravados(): void {
        $id = $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        $cases = [
            ['campos_invalidos', ['shop_name' => '   ']],
            ['campos_invalidos', ['state' => 'XX']],
            ['campos_invalidos', ['category' => 'inexistente']],
            ['telefone_invalido', ['phone' => '123']],
            ['dados_muito_longos', ['city' => str_repeat('a', 101)]],
        ];
        foreach ($cases as [$error, $change]) {
            [, $location] = $this->post('merchant/profile', $change + self::DADOS);
            $this->assertSame('merchant/profile&error=' . $error, $location, json_encode($change));
        }

        $this->assertSame('Loja Teste', $this->merchantField($id, 'store_name'));
        $this->assertSame('Sao Paulo', $this->merchantField($id, 'city'));
    }

    // o id da conta vem sempre da sessao: editar o perfil nunca toca outra loja
    public function testEdicaoSoAlteraALojaLogada(): void {
        $outra = $this->createMerchant('outra@teste.test');
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        $this->post('merchant/profile', self::DADOS + ['id' => $outra, 'merchant_id' => $outra]);

        $this->assertSame('Loja Teste', $this->merchantField($outra, 'store_name'));
    }

    public function testFormularioSemCsrfERecusado(): void {
        $id = $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        [$status] = $this->post('merchant/profile', self::DADOS, false);

        $this->assertSame(403, $status);
        $this->assertSame('Loja Teste', $this->merchantField($id, 'store_name'));
    }

    public function testTrocaASenhaInformandoAAtual(): void {
        $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        $this->assertSame('merchant/profile&success=senha_alterada', $this->changePassword('teste123', 'nova-senha-1'));

        // quem trocou continua logado
        $this->assertSame(200, $this->get('merchant/profile')[0]);

        // a senha antiga nao entra mais, a nova entra
        $this->newSession();
        [, $location] = $this->post('merchant/login', ['email' => 'loja@teste.test', 'password' => 'teste123']);
        $this->assertSame('merchant/login&error=credenciais_invalidas', $location);
        $this->loginAs('loja@teste.test', 'nova-senha-1');
    }

    public function testSenhaAtualErradaNaoTroca(): void {
        $id = $this->createMerchant('loja@teste.test');
        $hash = $this->merchantField($id, 'password_hash');
        $this->loginAs('loja@teste.test');

        $this->assertSame('merchant/profile&error=senha_atual_incorreta', $this->changePassword('errada', 'nova-senha-1'));
        $this->assertSame($hash, $this->merchantField($id, 'password_hash'));
    }

    public function testNovaSenhaCurtaOuSemConfirmacaoIgualNaoTroca(): void {
        $id = $this->createMerchant('loja@teste.test');
        $hash = $this->merchantField($id, 'password_hash');
        $this->loginAs('loja@teste.test');

        $this->assertSame('merchant/profile&error=senha_curta', $this->changePassword('teste123', '1234567'));
        $this->assertSame('merchant/profile&error=senha_longa', $this->changePassword('teste123', str_repeat('a', 73)));
        // task 46: a nova precisa ser outra (a senha atual de quem tem acesso indevido continuaria valendo)
        $this->assertSame('merchant/profile&error=senha_igual', $this->changePassword('teste123', 'teste123'));
        $this->assertSame('merchant/profile&error=senhas_diferentes', $this->changePassword('teste123', 'nova-senha-1', 'nova-senha-2'));
        $this->assertSame('merchant/profile&error=campos_obrigatorios', $this->changePassword('teste123', ''));
        $this->assertSame($hash, $this->merchantField($id, 'password_hash'));
    }

    // sessao aberta num aparelho esquecido nao pode servir pra adivinhar a senha atual
    public function testMuitasSenhasAtuaisErradasBloqueiamATroca(): void {
        $id = $this->createMerchant('loja@teste.test');
        $hash = $this->merchantField($id, 'password_hash');
        $this->loginAs('loja@teste.test');

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('merchant/profile&error=senha_atual_incorreta', $this->changePassword('errada', 'nova-senha-1'));
        }

        // a 6a e recusada mesmo com a senha atual certa, ate a janela passar
        $this->assertSame('merchant/profile&error=muitas_tentativas', $this->changePassword('teste123', 'nova-senha-1'));
        $this->assertSame($hash, $this->merchantField($id, 'password_hash'));

        // o bloqueio e so da troca de senha: o resto do painel segue funcionando
        $this->assertSame(200, $this->get('merchant/dashboard')[0]);
    }

    // trocou a senha: quem estava logado em outro aparelho com a senha antiga precisa entrar de novo
    public function testTrocaDeSenhaDerrubaAsOutrasSessoes(): void {
        $id = $this->createMerchant('loja@teste.test');
        $this->loginAs('loja@teste.test');

        // a troca acontece em "outro aparelho" (direto no banco, como se fosse outra sessao)
        $stmt = $this->db->prepare('UPDATE merchants SET password_hash = :hash WHERE id = :id');
        $stmt->execute([':hash' => password_hash('nova-senha-1', PASSWORD_BCRYPT), ':id' => $id]);

        [$status, $location] = $this->get('merchant/dashboard');
        $this->assertSame(302, $status);
        $this->assertSame('merchant/login&error=sessao_expirada', $location);
    }
}
