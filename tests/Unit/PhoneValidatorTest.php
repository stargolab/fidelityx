<?php

namespace Tests\Unit;

use App\Validators\PhoneValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneValidatorTest extends TestCase {
    public function testSanitizeTiraAMascara(): void {
        $this->assertSame('11999998888', PhoneValidator::sanitize('(11) 99999-8888'));
        $this->assertSame('1133334444', PhoneValidator::sanitize('11 3333.4444'));
        $this->assertSame('', PhoneValidator::sanitize(null));
    }

    public static function validos(): array {
        return [
            'celular (11 digitos)'   => ['11999998888'],
            'fixo (10 digitos)'      => ['1133334444'],
            'celular com mascara'    => ['(11) 99999-8888'],
            'fixo com mascara'       => ['(11) 3333-4444'],
        ];
    }

    public static function invalidos(): array {
        return [
            'sem ddd'        => ['999998888'],
            'curto'          => ['123'],
            'longo demais'   => ['119999988889'],
            'vazio'          => [''],
            'so letras'      => ['telefone'],
        ];
    }

    #[DataProvider('validos')]
    public function testAceitaTelefoneComDdd(string $phone): void {
        $this->assertTrue(PhoneValidator::isValid($phone));
    }

    #[DataProvider('invalidos')]
    public function testRecusaTelefoneInvalido(string $phone): void {
        $this->assertFalse(PhoneValidator::isValid($phone));
    }
}
