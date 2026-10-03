<?php

namespace Tests\Unit;

use App\Support\RewardProgress;
use PHPUnit\Framework\TestCase;

final class RewardProgressTest extends TestCase {
    private function rewards(): array {
        return [
            ['name' => 'Suco', 'points_cost' => 50],
            ['name' => 'Cafe', 'points_cost' => 20],
            ['name' => 'Combo', 'points_cost' => 100],
        ];
    }

    public function testSemPremioNaoHaProgresso(): void {
        $this->assertNull(RewardProgress::next(30, []));
    }

    public function testProximoEOMaisBaratoQueOSaldoAindaNaoPaga(): void {
        $p = RewardProgress::next(30, $this->rewards());
        $this->assertSame('Suco', $p['reward']['name']);
        $this->assertSame(20, $p['missing']);
        $this->assertSame(60, $p['percent']);
        $this->assertFalse($p['all_available']);
    }

    public function testSaldoZeroComecaNoPrimeiroPremio(): void {
        $p = RewardProgress::next(0, $this->rewards());
        $this->assertSame('Cafe', $p['reward']['name']);
        $this->assertSame(20, $p['missing']);
        $this->assertSame(0, $p['percent']);
    }

    public function testSaldoExatoDoPremioJaPagaEMiraNoSeguinte(): void {
        $p = RewardProgress::next(20, $this->rewards());
        $this->assertSame('Suco', $p['reward']['name']);
        $this->assertSame(30, $p['missing']);
    }

    public function testSaldoQuePagaTudoMarcaTudoDisponivel(): void {
        $p = RewardProgress::next(500, $this->rewards());
        $this->assertTrue($p['all_available']);
        $this->assertSame(0, $p['missing']);
        $this->assertSame(100, $p['percent']);
        $this->assertSame('Combo', $p['reward']['name']);
    }

    public function testPercentualArredondaParaBaixo(): void {
        $p = RewardProgress::next(1, [['name' => 'X', 'points_cost' => 3]]);
        $this->assertSame(33, $p['percent']);
    }
}
