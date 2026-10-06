<?php

namespace App\Support;

// dinheiro sempre em centavos (inteiro): sem float, sem erro de arredondamento.
final class Money {
    // o que o lojista digitou -> centavos. aceita "12,90", "12.90", "1.234,56", "R$ 12,90", "12".
    // com virgula, a virgula e a decimal e o ponto e milhar; sem virgula, ponto seguido de 1 ou 2
    // digitos e decimal ("12.9"), senao e milhar ("1.234"). devolve null se nao for um valor.
    public static function toCents($input): ?int {
        $value = preg_replace('/[^\d,.]/', '', (string)$input);
        if ($value === '' || substr_count($value, ',') > 1) {
            return null;
        }

        if (str_contains($value, ',')) {
            [$int, $dec] = explode(',', str_replace('.', '', $value), 2);
        } else {
            $parts = explode('.', $value);
            $last = end($parts);
            if (count($parts) > 1 && strlen($last) <= 2) {
                $dec = array_pop($parts);
            } else {
                $dec = '';
            }
            $int = implode('', $parts);
        }

        if (($int === '' && $dec === '') || strlen($dec) > 2 || strlen($int) > 9) {
            return null;
        }

        return (int)($int === '' ? '0' : $int) * 100 + (int)str_pad($dec, 2, '0');
    }

    // centavos -> "R$ 1.234,56"
    public static function format(int $cents): string {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    // pontos de uma compra pela regra (a cada $ruleCents, 1 ponto), sempre arredondando pra baixo
    public static function pointsFor(int $amountCents, int $ruleCents): int {
        return $ruleCents > 0 ? intdiv(max(0, $amountCents), $ruleCents) : 0;
    }
}
