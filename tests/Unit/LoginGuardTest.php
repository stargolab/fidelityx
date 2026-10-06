<?php

namespace Tests\Unit;

use App\Support\LoginGuard;
use PHPUnit\Framework\TestCase;

// task 45: login nao revela quais e-mails sao lojas e avisa quando uma conta vira alvo
final class LoginGuardTest extends TestCase {
    public function testHashFixoTemOMesmoCustoDoCadastro(): void {
        // custo diferente mudaria o tempo e o e-mail inexistente voltaria a dar pista
        $cadastro = password_get_info(password_hash('x', PASSWORD_BCRYPT));
        $fixo = password_get_info(LoginGuard::DUMMY_HASH);

        $this->assertSame($cadastro['algo'], $fixo['algo']);
        $this->assertSame($cadastro['options']['cost'], $fixo['options']['cost']);
    }

    public function testConfereASenhaDaConta(): void {
        $merchant = ['password_hash' => password_hash('teste123', PASSWORD_BCRYPT)];

        $this->assertTrue(LoginGuard::verify($merchant, 'teste123'));
        $this->assertFalse(LoginGuard::verify($merchant, 'errada'));
    }

    public function testSemContaNuncaEntra(): void {
        $this->assertFalse(LoginGuard::verify(null, ''));
        $this->assertFalse(LoginGuard::verify(null, 'teste123'));
    }

    // sem conta, o bcrypt roda do mesmo jeito: o tempo fica na mesma ordem do da senha errada
    // (a margem e larga de proposito: so pega a volta do atalho, que responde em microssegundos)
    public function testSemContaLevaOTempoDeUmaVerificacao(): void {
        $merchant = ['password_hash' => password_hash('teste123', PASSWORD_BCRYPT)];

        $start = hrtime(true);
        LoginGuard::verify($merchant, 'errada');
        $wrongPassword = hrtime(true) - $start;

        $start = hrtime(true);
        LoginGuard::verify(null, 'errada');
        $noAccount = hrtime(true) - $start;

        $this->assertGreaterThan($wrongPassword / 4, $noAccount);
    }

    public function testAvisoSoQuandoAContaChegaNoTeto(): void {
        $max = LoginGuard::MAX_FAILURES_PER_ACCOUNT;

        $this->assertNull(LoginGuard::accountAlert($max - 1, 'loja@teste.test'));
        $this->assertNull(LoginGuard::accountAlert($max + 1, 'loja@teste.test'), 'uma linha por janela, nao uma por erro');

        $alert = LoginGuard::accountAlert($max, 'Loja@Teste.test ');
        $this->assertNotNull($alert);
        // o e-mail nao vai pro log
        $this->assertStringNotContainsStringIgnoringCase('loja@teste', $alert);
        $this->assertStringContainsString(substr(hash('sha256', 'loja@teste.test'), 0, 12), $alert);
    }
}
