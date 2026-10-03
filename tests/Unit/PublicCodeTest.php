<?php

namespace Tests\Unit;

use App\Support\PublicCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicCodeTest extends TestCase {
    public function testGeraOitoCaracteresSemOsQueSeConfundem(): void {
        for ($i = 0; $i < 200; $i++) {
            $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{8}$/', PublicCode::generate());
        }
    }

    public static function digitados(): array {
        return [
            'exato'                 => ['7K2M9QXA', '7K2M9QXA'],
            'minusculo'             => ['7k2m9qxa', '7K2M9QXA'],
            'com espaco e hifen'    => [' 7K2M-9QXA ', '7K2M9QXA'],
            'O vira zero (hex antigo)' => ['81711F1O', '81711F10'],
            'I e L viram um'        => ['8I7L1F16', '81711F16'],
        ];
    }

    #[DataProvider('digitados')]
    public function testNormalizaOQueOClienteDigitou(string $input, string $expected): void {
        $this->assertSame($expected, PublicCode::normalize($input));
    }

    public function testRecusaOQueNaoTemCaraDeCodigo(): void {
        foreach (['', 'ABC', '123456789', 'ABCD#EFG', null] as $input) {
            $this->assertNull(PublicCode::normalize($input), var_export($input, true));
        }
    }
}
