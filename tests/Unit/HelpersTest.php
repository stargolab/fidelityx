<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase {
    public function testFormatDocument(): void {
        $this->assertSame('529.982.247-25', format_document('52998224725'));
        $this->assertSame('11.222.333/0001-81', format_document('11222333000181'));
        $this->assertSame('529.982.247-25', format_document('529.982.247-25'), 'ja formatado continua igual');
        $this->assertSame('123', format_document('123'), 'tamanho desconhecido sai so com os digitos');
        $this->assertSame('', format_document(null));
    }

    public function testFieldErrorAttrMarcaSoOCampoQueOErroAponta(): void {
        $marcado = ' aria-invalid="true" aria-describedby="flash-error"';

        $this->assertSame($marcado, field_error_attr('email', 'email_invalido'));
        $this->assertSame('', field_error_attr('phone', 'email_invalido'), 'outro campo da mesma tela fica limpo');
        $this->assertSame($marcado, field_error_attr('password_confirm', 'senhas_diferentes'), 'erro que aponta dois campos');
        $this->assertSame('', field_error_attr('email', 'erro_servidor'), 'erro geral nao marca campo');
        $this->assertSame('', field_error_attr('email', 'codigo_inventado'));
    }

    public function testFieldErrorAttrSemCodigoUsaOErroDaUrl(): void {
        $_GET['error'] = 'telefone_invalido';
        try {
            $this->assertNotSame('', field_error_attr('phone'));
            $this->assertSame('', field_error_attr('email'));

            unset($_GET['error']);
            $this->assertSame('', field_error_attr('phone'), 'sem erro na url nada e marcado');
        } finally {
            unset($_GET['error']);
        }
    }
}
