<?php

namespace Tests\Unit;

use App\Support\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// task 46: de 8 a 72 bytes (o limite do bcrypt)
final class PasswordPolicyTest extends TestCase {
    public static function senhas(): array {
        return [
            'vazia'                 => ['', 'senha_curta'],
            '7 caracteres'          => ['1234567', 'senha_curta'],
            '8 caracteres'          => ['12345678', null],
            '72 caracteres'         => [str_repeat('a', 72), null],
            '73 caracteres'         => [str_repeat('a', 73), 'senha_longa'],
            // 36 letras acentuadas = 72 bytes; uma a mais passa do limite do bcrypt
            '36 acentos (72 bytes)' => [str_repeat('é', 36), null],
            '37 acentos (74 bytes)' => [str_repeat('é', 37), 'senha_longa'],
            // 4 acentos sao 8 bytes: conta em bytes, como o bcrypt
            '4 acentos (8 bytes)'   => ['éééé', null],
        ];
    }

    #[DataProvider('senhas')]
    public function testTamanhoEmBytes(string $password, ?string $expected): void {
        $this->assertSame($expected, PasswordPolicy::problem($password));
    }
}
