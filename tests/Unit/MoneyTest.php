<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase {
    public static function valores(): array {
        return [
            'inteiro'              => ['12', 1200],
            'virgula'              => ['12,90', 1290],
            'virgula 1 casa'       => ['12,9', 1290],
            'ponto decimal'        => ['12.90', 1290],
            'milhar com ponto'     => ['1.234', 123400],
            'milhar e virgula'     => ['1.234,56', 123456],
            'com R$ e espaco'      => [' R$ 12,90 ', 1290],
            'centavo'              => ['0,01', 1],
            'so centavos'          => [',50', 50],
        ];
    }

    #[DataProvider('valores')]
    public function testConverteParaCentavos(string $input, int $cents): void {
        $this->assertSame($cents, Money::toCents($input));
    }

    public function testRecusaOQueNaoEValor(): void {
        foreach (['', 'abc', '12,345', '1,2,3', '9999999999', null, '-10', 'R$ -12,90', '10-'] as $input) {
            $this->assertNull(Money::toCents($input), var_export($input, true));
        }
    }

    public function testFormata(): void {
        $this->assertSame('R$ 12,90', Money::format(1290));
        $this->assertSame('R$ 1.234,56', Money::format(123456));
        $this->assertSame('R$ 0,01', Money::format(1));
    }

    public function testPontosArredondamParaBaixo(): void {
        $this->assertSame(12, Money::pointsFor(1290, 100), 'R$ 12,90 com R$ 1 = 1 ponto');
        $this->assertSame(2, Money::pointsFor(1499, 500), 'R$ 14,99 com R$ 5 = 1 ponto');
        $this->assertSame(0, Money::pointsFor(99, 100));
        $this->assertSame(1290, Money::pointsFor(1290, 1), 'regra minima: R$ 0,01 = 1 ponto');
        $this->assertSame(0, Money::pointsFor(1290, 0), 'sem regra');
    }
}
