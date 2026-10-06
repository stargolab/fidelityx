<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// limite de tentativas do login (task 38): errar a senha de outra pessoa nao pode trancar a dona da conta.
// o bloqueio vale pra quem errou (e-mail + ip), nunca pro e-mail sozinho.
final class LoginLockoutTest extends HttpTestCase {
    private const ATACANTE = '203.0.113.50';
    private const DONA = '198.51.100.7';

    // [status, destino] de uma tentativa de login
    private function tryLogin(string $email, string $password): array {
        return $this->post('merchant/login', ['email' => $email, 'password' => $password]);
    }

    private function failLogin(string $email, int $times): void {
        for ($i = 0; $i < $times; $i++) {
            [, $location] = $this->tryLogin($email, 'senha-errada');
            $this->assertSame('merchant/login&error=credenciais_invalidas', $location, 'tentativa ' . ($i + 1));
        }
    }

    public function testErrarASenhaDeOutraPessoaNaoTrancaADonaDaConta(): void {
        $this->createMerchant('loja@teste.test');

        // alguem que so sabe o e-mail da loja erra a senha ate ser bloqueado
        $this->fromIp(self::ATACANTE);
        $this->failLogin('loja@teste.test', 5);
        $this->assertSame(429, $this->tryLogin('loja@teste.test', 'senha-errada')[0], 'quem errou fica bloqueado');

        // a dona, no aparelho dela, entra normalmente
        $this->fromIp(self::DONA);
        $this->loginAs('loja@teste.test');
        $this->assertSame(200, $this->get('merchant/dashboard')[0]);
    }

    // o ataque pode continuar o dia inteiro: a dona nunca e afetada
    public function testAtaqueRepetidoDeVariosIpsNaoBloqueiaADona(): void {
        $this->createMerchant('loja@teste.test');

        foreach (['203.0.113.1', '203.0.113.2', '203.0.113.3'] as $ip) {
            $this->fromIp($ip);
            $this->failLogin('loja@teste.test', 5);
        }

        $this->fromIp(self::DONA);
        $this->loginAs('loja@teste.test');
    }

    public function testQuemErraCincoVezesFicaBloqueadoMesmoComASenhaCerta(): void {
        $this->createMerchant('loja@teste.test');
        $this->fromIp(self::ATACANTE);
        $this->failLogin('loja@teste.test', 5);

        // acertar na 6a nao adianta: senao o limite nao seguraria quem esta adivinhando a senha
        $this->assertSame(429, $this->tryLogin('loja@teste.test', 'teste123')[0]);

        // e o bloqueio nao depende do cookie: sessao nova, mesmo ip, continua bloqueado
        $this->fromIp(self::ATACANTE);
        $this->assertSame(429, $this->tryLogin('loja@teste.test', 'teste123')[0]);
    }

    // o bloqueio e daquela conta naquele ip: outra loja na mesma rede (shopping, galeria) nao e afetada
    public function testBloqueioDeUmaContaNaoImpedeOutraContaNoMesmoIp(): void {
        $this->createMerchant('loja@teste.test');
        $this->createMerchant('vizinha@teste.test');
        $this->fromIp(self::ATACANTE);
        $this->failLogin('loja@teste.test', 5);

        $this->loginAs('vizinha@teste.test');
    }

    public function testLoginCertoZeraOsErrosDaquelaContaNaqueleIp(): void {
        $this->createMerchant('loja@teste.test');
        $this->fromIp(self::DONA);

        // a dona erra 4 vezes, acerta, sai e erra mais 4: nao soma 8, porque o acerto zerou a contagem
        $this->failLogin('loja@teste.test', 4);
        $this->loginAs('loja@teste.test');
        $this->newSession();
        $this->failLogin('loja@teste.test', 4);

        $this->loginAs('loja@teste.test');
    }

    // maiusculas no e-mail nao rendem tentativas extras (o banco acha a mesma conta nos dois casos)
    public function testVariarMaiusculasDoEmailNaoBurlaOLimite(): void {
        $this->createMerchant('loja@teste.test');
        $this->fromIp(self::ATACANTE);

        $this->failLogin('loja@teste.test', 3);
        $this->failLogin('LOJA@Teste.Test', 2);

        $this->assertSame(429, $this->tryLogin('Loja@teste.test', 'teste123')[0]);
    }

    // quem testa senhas em varias contas e barrado pelo limite do ip (20), mesmo sem chegar a 5 em nenhuma
    public function testLimitePorIpSomaAsTentativasDeTodasAsContas(): void {
        $this->createMerchant('loja@teste.test');
        $this->fromIp(self::ATACANTE);

        for ($i = 1; $i <= 5; $i++) {
            $this->failLogin("alvo$i@teste.test", 4);
        }

        $this->assertSame(429, $this->tryLogin('loja@teste.test', 'teste123')[0], '21a tentativa do mesmo ip');

        // outro ip nao tem nada a ver com isso
        $this->fromIp(self::DONA);
        $this->loginAs('loja@teste.test');
    }

    // em ipv6 cada assinante tem uma rede /64 inteira: trocar de endereco dentro dela nao zera o limite
    public function testTrocarDeEnderecoIpv6NaMesmaRedeNaoBurlaOLimite(): void {
        $this->createMerchant('loja@teste.test');

        for ($i = 1; $i <= 5; $i++) {
            $this->fromIp('2001:db8:aaaa:bbbb::' . $i);
            $this->failLogin('loja@teste.test', 1);
        }

        $this->fromIp('2001:db8:aaaa:bbbb:1234:5678:9abc:def0');
        $this->assertSame(429, $this->tryLogin('loja@teste.test', 'teste123')[0]);

        // outra rede ipv6 (outro assinante) entra normalmente
        $this->fromIp('2001:db8:aaaa:cccc::1');
        $this->loginAs('loja@teste.test');
    }

    // cada erro conta em dois lugares (e-mail + ip, e ip), e a chave continua gravada so como hash
    public function testTentativasFicamGravadasSoComoHash(): void {
        $this->createMerchant('loja@teste.test');
        $this->fromIp(self::ATACANTE);
        $this->failLogin('loja@teste.test', 1);

        $rows = $this->db->query('SELECT bucket, key_hash FROM rate_limit_hits ORDER BY bucket')->fetchAll(\PDO::FETCH_KEY_PAIR);

        $this->assertSame(['login_ip', 'login_pair'], array_keys($rows), 'nao existe mais o bloqueio so por e-mail');
        $this->assertSame(hash('sha256', self::ATACANTE), $rows['login_ip']);
        $this->assertSame(hash('sha256', 'loja@teste.test|' . self::ATACANTE), $rows['login_pair']);
    }
}
