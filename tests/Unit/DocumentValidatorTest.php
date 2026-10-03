<?php

namespace Tests\Unit;

use App\Validators\DocumentValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentValidatorTest extends TestCase {
    public static function validos(): array {
        return [
            'cpf sem mascara'  => ['52998224725'],
            'cpf com mascara'  => ['529.982.247-25'],
            'outro cpf'        => ['111.444.777-35'],
            'cnpj sem mascara' => ['11222333000181'],
            'cnpj com mascara' => ['11.222.333/0001-81'],
            'outro cnpj'       => ['11.444.777/0001-61'],
        ];
    }

    public static function invalidos(): array {
        return [
            'cpf com 1o digito errado'  => ['52998224735'],
            'cpf com 2o digito errado'  => ['52998224726'],
            'cpf todos iguais'          => ['11111111111'],
            'cpf zeros'                 => ['000.000.000-00'],
            'cnpj com digito errado'    => ['11222333000182'],
            'cnpj todos iguais'         => ['22222222222222'],
            'curto demais'              => ['1234567890'],
            'tamanho entre cpf e cnpj'  => ['123456789012'],
            'longo demais'              => ['112223330001811'],
            'vazio'                     => [''],
            'so letras'                 => ['abc.def.ghi-jk'],
        ];
    }

    #[DataProvider('validos')]
    public function testAceitaDocumentoValido(string $document): void {
        $this->assertTrue(DocumentValidator::isValid($document));
    }

    #[DataProvider('invalidos')]
    public function testRecusaDocumentoInvalido(string $document): void {
        $this->assertFalse(DocumentValidator::isValid($document));
    }
}
