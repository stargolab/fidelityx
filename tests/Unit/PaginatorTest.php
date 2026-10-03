<?php

namespace Tests\Unit;

use App\Support\Paginator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase {
    public function testCalculaPaginasEOffset(): void {
        $p = new Paginator(45, 2, 20);
        $this->assertSame(3, $p->pages);
        $this->assertSame(2, $p->page);
        $this->assertSame(20, $p->offset());
    }

    public function testListaVaziaTemUmaPagina(): void {
        $p = new Paginator(0, 1, 20);
        $this->assertSame(1, $p->pages);
        $this->assertSame(0, $p->offset());
    }

    public function testTotalExatoNaoCriaPaginaExtra(): void {
        $this->assertSame(2, (new Paginator(40, 1, 20))->pages);
    }

    public static function paginasInvalidas(): array {
        return [
            'zero'          => [0, 1],
            'negativa'      => [-3, 1],
            'letras'        => ['abc', 1],
            'vazia'         => ['', 1],
            'alem da ultima' => [99, 3],
            'decimal'       => ['1.5', 1],
        ];
    }

    #[DataProvider('paginasInvalidas')]
    public function testPaginaInvalidaCaiNaMaisProxima($requested, int $expected): void {
        $this->assertSame($expected, (new Paginator(45, $requested, 20))->page);
    }
}
