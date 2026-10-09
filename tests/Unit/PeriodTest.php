<?php

namespace Tests\Unit;

use App\Support\Period;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

// task 59: periodo dos relatorios
final class PeriodTest extends TestCase {
    private DateTimeImmutable $now;

    protected function setUp(): void {
        $this->now = new DateTimeImmutable('2026-10-06 15:30:00');
    }

    private function period(array $query): Period {
        return Period::fromQuery($query, $this->now);
    }

    public function testSemPeriodoNaoFiltra(): void {
        foreach ([[], ['periodo' => 'tudo'], ['periodo' => 'ontem'], ['periodo' => '']] as $query) {
            $period = $this->period($query);
            $this->assertFalse($period->isFiltered());
            $this->assertSame('tudo', $period->key);
            $this->assertFalse($period->invalid);
            $this->assertSame([], $period->query());
        }
    }

    public function testHojeSeteDiasEMes(): void {
        $hoje = $this->period(['periodo' => 'hoje']);
        $this->assertSame(['2026-10-06 00:00:00', '2026-10-07 00:00:00'], [$hoje->from, $hoje->to]);

        // os ultimos 7 dias contam hoje
        $semana = $this->period(['periodo' => '7dias']);
        $this->assertSame(['2026-09-30 00:00:00', '2026-10-07 00:00:00'], [$semana->from, $semana->to]);

        $mes = $this->period(['periodo' => 'mes']);
        $this->assertSame(['2026-10-01 00:00:00', '2026-10-07 00:00:00'], [$mes->from, $mes->to]);
        $this->assertSame(['periodo' => 'mes'], $mes->query());
    }

    public function testIntervaloIncluiOsDoisDias(): void {
        $period = $this->period(['periodo' => 'intervalo', 'de' => '2026-09-01', 'ate' => '2026-09-30']);

        $this->assertSame(['2026-09-01 00:00:00', '2026-10-01 00:00:00'], [$period->from, $period->to]);
        $this->assertSame(['periodo' => 'intervalo', 'de' => '2026-09-01', 'ate' => '2026-09-30'], $period->query());

        $umDia = $this->period(['periodo' => 'intervalo', 'de' => '2026-09-01', 'ate' => '2026-09-01']);
        $this->assertSame(['2026-09-01 00:00:00', '2026-09-02 00:00:00'], [$umDia->from, $umDia->to]);
    }

    public function testIntervaloInvalidoCaiEmTudoEAvisa(): void {
        $casos = [
            ['de' => '', 'ate' => '2026-09-30'],
            ['de' => '2026-09-30', 'ate' => '2026-09-01'],   // invertido
            ['de' => '2026-02-30', 'ate' => '2026-03-01'],   // dia que nao existe
            ['de' => '01/09/2026', 'ate' => '30/09/2026'],   // formato errado
            ['de' => "2026-09-01' OR 1=1", 'ate' => '2026-09-30'],
        ];
        foreach ($casos as $datas) {
            $period = $this->period(['periodo' => 'intervalo'] + $datas);
            $this->assertTrue($period->invalid, json_encode($datas));
            $this->assertFalse($period->isFiltered());
        }
    }
}
