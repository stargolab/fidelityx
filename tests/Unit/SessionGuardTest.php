<?php

namespace Tests\Unit;

use App\Support\SessionGuard;
use PHPUnit\Framework\TestCase;

final class SessionGuardTest extends TestCase {
    public function testSessaoParadaAlemDoLimiteExpira(): void {
        $now = 1_800_000_000;
        $this->assertFalse(SessionGuard::isExpired($now - 60, $now));
        $this->assertFalse(SessionGuard::isExpired($now - SessionGuard::IDLE_SECONDS, $now), 'no limite exato ainda vale');
        $this->assertTrue(SessionGuard::isExpired($now - SessionGuard::IDLE_SECONDS - 1, $now));
    }

    public function testSessaoSemRegistroDeAtividadeNaoExpira(): void {
        $this->assertFalse(SessionGuard::isExpired(null, 1_800_000_000));
    }

    public function testSessaoAbertaComSenhaAntigaDeixaDeValer(): void {
        $old = password_hash('antiga', PASSWORD_BCRYPT);
        $new = password_hash('nova', PASSWORD_BCRYPT);
        $signature = SessionGuard::passwordSignature($old);

        $this->assertFalse(SessionGuard::passwordChanged($signature, $old));
        $this->assertTrue(SessionGuard::passwordChanged($signature, $new));
        $this->assertStringNotContainsString($old, $signature, 'a sessao nao guarda o hash da senha');
    }

    public function testSessaoSemAssinaturaNaoEDerrubada(): void {
        $this->assertFalse(SessionGuard::passwordChanged(null, password_hash('qualquer', PASSWORD_BCRYPT)));
    }

    public function testLimiteEUmDiaDeBalcao(): void {
        $this->assertSame(8 * 3600, SessionGuard::IDLE_SECONDS);
    }
}
