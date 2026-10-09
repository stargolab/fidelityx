<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase {
    public function testFormatDocument(): void {
        $this->assertSame('529.982.247-25', format_document('52998224725'));
        $this->assertSame('11.222.333/0001-81', format_document('11222333000181'));
        $this->assertSame('529.982.247-25', format_document('529.982.247-25'), 'ja formatado continua igual');
        // task 57: cnpj alfanumerico, com a mesma mascara
        $this->assertSame('12.ABC.345/01DE-35', format_document('12ABC34501DE35'));
        $this->assertSame('12.ABC.345/01DE-35', format_document('12.abc.345/01de-35'));
        $this->assertSame('123', format_document('123'), 'tamanho desconhecido sai so com os digitos');
        $this->assertSame('', format_document(null));
    }
}
