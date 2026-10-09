<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

// task 59: planilha no formato do Excel em portugues e sem injecao de formula
final class CsvTest extends TestCase {
    public function testSeparaComPontoEVirgulaETerminaComCrlf(): void {
        $this->assertSame("Bia;(11) 92222-0002;30\r\n", Csv::line(['Bia', '(11) 92222-0002', 30]));
    }

    public function testAspasQuandoOTextoTemSeparadorAspasOuQuebra(): void {
        $this->assertSame("\"a;b\";\"diz \"\"oi\"\"\";\"linha\nnova\"\r\n", Csv::line(['a;b', 'diz "oi"', "linha\nnova"]));
    }

    public function testTextoQueParecFormulaNaoViraFormula(): void {
        $this->assertSame("'=1+1;'+1;'@SOMA(A1)\r\n", Csv::line(['=1+1', '+1', '@SOMA(A1)']));
        $this->assertSame("\"'=HYPERLINK(\"\"x\"\")\"\r\n", Csv::line(['=HYPERLINK("x")']));
        $this->assertSame("'-10\r\n", Csv::line(['-10']), 'texto, nao numero');
    }

    public function testNumeroInteiroSaiComoNumeroMesmoNegativo(): void {
        $this->assertSame("-20;0;1500\r\n", Csv::line([-20, 0, 1500]));
    }

    public function testBomDoUtf8(): void {
        $this->assertSame("\xEF\xBB\xBF", Csv::BOM);
    }
}
