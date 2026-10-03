<?php

namespace App\Support;

// quanto falta para o proximo premio de uma loja, dado o saldo do cliente.
// "proximo" = o premio ativo mais barato que o saldo ainda nao paga.
final class RewardProgress {
    // $rewards: premios ativos da loja (qualquer ordem, com name e points_cost).
    // devolve null se a loja nao tem premio; senao:
    //   ['reward' => premio, 'missing' => pontos que faltam, 'percent' => 0..100, 'all_available' => bool]
    // quando o saldo ja paga tudo, all_available = true, missing = 0 e percent = 100 (reward = o mais caro).
    public static function next(int $balance, array $rewards): ?array {
        if (!$rewards) {
            return null;
        }

        usort($rewards, fn($a, $b) => (int)$a['points_cost'] <=> (int)$b['points_cost']);

        foreach ($rewards as $reward) {
            $cost = (int)$reward['points_cost'];
            if ($cost > $balance) {
                return [
                    'reward'        => $reward,
                    'missing'       => $cost - $balance,
                    'percent'       => (int)floor(max(0, $balance) * 100 / $cost),
                    'all_available' => false,
                ];
            }
        }

        return [
            'reward'        => end($rewards),
            'missing'       => 0,
            'percent'       => 100,
            'all_available' => true,
        ];
    }
}
