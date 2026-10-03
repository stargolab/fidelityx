<?php

namespace Tests\Unit;

use App\Support\QrSvg;
use PHPUnit\Framework\TestCase;

final class QrSvgTest extends TestCase {
    public function testGeraSvgPuroSemDeclaracaoXml(): void {
        $svg = QrSvg::svg('http://localhost/index.php?url=customer%2Fbalance');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function testTextosDiferentesGeramQrDiferentes(): void {
        $this->assertNotSame(QrSvg::svg('http://a.test/x'), QrSvg::svg('http://b.test/x'));
    }
}
