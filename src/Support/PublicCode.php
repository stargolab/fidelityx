<?php

namespace App\Support;

// codigo publico da loja: vai impresso no cartaz (embaixo do QR) e identifica a loja na consulta de saldo.
// 8 caracteres sem os que se confundem ao ler/digitar (0/O, 1/I/L).
final class PublicCode {
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    public const LENGTH = 8;

    public static function generate(): string {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        return $code;
    }

    // o que o cliente digitou -> formato do banco. tolera minuscula, espaco e hifen.
    // O vira 0 e I/L viram 1: lojas antigas (migration 003) tem codigo hexadecimal, com 0 e 1.
    // devolve null se nao tiver cara de codigo.
    public static function normalize($input): ?string {
        $code = strtoupper(preg_replace('/[\s-]/', '', (string)$input));
        $code = strtr($code, ['O' => '0', 'I' => '1', 'L' => '1']);

        return preg_match('/^[0-9A-Z]{' . self::LENGTH . '}$/', $code) ? $code : null;
    }
}
