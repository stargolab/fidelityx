<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// cadastro da loja (merchant/register)
final class RegisterTest extends HttpTestCase {
    private function register(array $overrides = []): ?string {
        return $this->post('merchant/register', $overrides + [
            'owner_name' => 'Dona Ana', 'shop_name' => 'Padaria da Ana', 'address' => 'Rua 1',
            'state' => 'SP', 'city' => 'Sao Paulo', 'email' => 'padaria@teste.test',
            'phone' => '11988887777', 'category' => 'alimentacao', 'document' => '52998224725',
            'password' => 'teste123', 'password_confirm' => 'teste123',
        ])[1];
    }

    // task 46: de 8 a 72 bytes (o bcrypt ignora o que passa de 72 sem avisar)
    public function testSenhaPrecisaTerDe8A72Bytes(): void {
        $this->assertSame('merchant/register&error=senha_curta', $this->register(['password' => '1234567', 'password_confirm' => '1234567']));

        $long = str_repeat('é', 37); // 74 bytes
        $this->assertSame('merchant/register&error=senha_longa', $this->register(['password' => $long, 'password_confirm' => $long]));
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM merchants'));

        $this->assertSame('merchant/login&success=cadastrado', $this->register(['password' => '12345678', 'password_confirm' => '12345678']));
        $this->loginAs('padaria@teste.test', '12345678');
    }

    // task 57: cnpj alfanumerico (desde jul/2026) cadastra, grava em maiusculas e aparece formatado no perfil
    public function testCadastraCnpjAlfanumerico(): void {
        $this->assertSame('merchant/login&success=cadastrado', $this->register(['document' => '12.abc.345/01de-35']));

        $this->assertSame('12ABC34501DE35', $this->scalar('SELECT cnpj FROM merchants'));
        $this->assertNull($this->scalar('SELECT cpf FROM merchants'));

        $this->loginAs('padaria@teste.test');
        $this->confirmEmailFromMail('padaria@teste.test');
        $this->get('merchant/profile');
        $this->assertStringContainsString('value="12.ABC.345/01DE-35"', $this->lastBody);
        $this->assertStringContainsString('>CNPJ<', $this->lastBody);
    }

    public function testCnpjNumericoContinuaValendoEAlfanumericoErradoNao(): void {
        $this->assertSame('merchant/register&error=documento_invalido', $this->register(['document' => '12.ABC.345/01DE-36']));
        $this->assertSame('merchant/login&success=cadastrado', $this->register(['document' => '11.222.333/0001-81']));
        $this->assertSame('11222333000181', $this->scalar('SELECT cnpj FROM merchants'));
    }

    // o mesmo cnpj digitado em minusculas nao vira uma segunda conta
    public function testCnpjAlfanumericoRepetidoEmMinusculasEDuplicado(): void {
        $this->register(['document' => '12ABC34501DE35']);
        $this->assertSame('merchant/register&error=ja_cadastrado', $this->register(['document' => '12abc34501de35', 'email' => 'outra@teste.test']));
    }

    // senha antiga, de antes da regra, continua entrando
    public function testSenhaCurtaCadastradaAntesDaRegraContinuaValendo(): void {
        $id = $this->createMerchant('loja@teste.test');
        $stmt = $this->db->prepare('UPDATE merchants SET password_hash = :hash WHERE id = :id');
        $stmt->execute([':hash' => password_hash('abc123', PASSWORD_BCRYPT), ':id' => $id]);

        $this->loginAs('loja@teste.test', 'abc123');
    }

    public function testMensagemExplicaARegra(): void {
        $this->get('merchant/register&error=senha_longa');
        $this->assertStringContainsString('no máximo 72 caracteres', $this->lastBody);
        $this->get('merchant/register');
        $this->assertStringContainsString('minlength="8"', $this->lastBody);
    }
}
