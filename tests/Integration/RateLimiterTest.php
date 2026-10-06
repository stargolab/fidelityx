<?php

namespace Tests\Integration;

use App\Support\RateLimiter;
use Tests\Support\DatabaseTestCase;

final class RateLimiterTest extends DatabaseTestCase {
    private RateLimiter $limiter;

    protected function setUp(): void {
        parent::setUp();
        $this->limiter = new RateLimiter($this->db);
    }

    public function testBloqueiaAoAtingirOMaximoNaJanela(): void {
        for ($i = 0; $i < 4; $i++) {
            $this->limiter->hit('login_email', 'ana@teste.test');
        }
        $this->assertFalse($this->limiter->tooMany('login_email', 'ana@teste.test', 5, 900));

        $this->limiter->hit('login_email', 'ana@teste.test');
        $this->assertTrue($this->limiter->tooMany('login_email', 'ana@teste.test', 5, 900));
    }

    public function testContaAsTentativasDaChaveNaJanela(): void {
        $this->assertSame(0, $this->limiter->count('login_account', 'ana@teste.test', 900));
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->hit('login_account', 'ana@teste.test');
        }
        $this->limiter->hit('login_account', 'outra@teste.test');
        $this->assertSame(3, $this->limiter->count('login_account', 'ana@teste.test', 900));

        $this->db->exec('UPDATE rate_limit_hits SET created_at = NOW() - INTERVAL 16 MINUTE');
        $this->assertSame(0, $this->limiter->count('login_account', 'ana@teste.test', 900));
    }

    public function testTentativasForaDaJanelaNaoContam(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit('login_email', 'ana@teste.test');
        }
        $this->db->exec('UPDATE rate_limit_hits SET created_at = NOW() - INTERVAL 16 MINUTE');

        $this->assertFalse($this->limiter->tooMany('login_email', 'ana@teste.test', 5, 900));
    }

    public function testChaveIgnoraMaiusculasEEspacos(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit('login_email', 'Ana@Teste.test');
        }

        $this->assertTrue($this->limiter->tooMany('login_email', '  ana@teste.TEST ', 5, 900));
    }

    public function testBucketsEChavesSaoIndependentes(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit('login_email', 'ana@teste.test');
        }

        $this->assertFalse($this->limiter->tooMany('login_email', 'bia@teste.test', 5, 900), 'outra chave');
        $this->assertFalse($this->limiter->tooMany('balance_ip', 'ana@teste.test', 5, 900), 'outro bucket');
    }

    public function testClearZeraSoAChaveDoBucket(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit('login_email', 'ana@teste.test');
            $this->limiter->hit('login_ip', '10.0.0.1');
        }

        $this->limiter->clear('login_email', 'ana@teste.test');

        $this->assertFalse($this->limiter->tooMany('login_email', 'ana@teste.test', 5, 900));
        $this->assertTrue($this->limiter->tooMany('login_ip', '10.0.0.1', 5, 900));
    }

    public function testGuardaSoOHashDaChave(): void {
        $this->limiter->hit('login_email', 'ana@teste.test');

        $stored = $this->scalar('SELECT key_hash FROM rate_limit_hits');
        $this->assertSame(hash('sha256', 'ana@teste.test'), $stored);
        $this->assertStringNotContainsString('@', $stored);
    }
}
