<?php

namespace App\Support;

// planos da loja (merchants.plan, task 32) e o que cada um permite.
// a regra fica toda aqui: controller pergunta "cabe mais um?", view pergunta "qual o limite?".
final class Plan {
    public const FREE = 'free';
    public const PRO  = 'pro';

    // nome que aparece nas telas
    public const LABELS = [
        self::FREE => 'Free',
        self::PRO  => 'Pro',
    ];

    // o que cada plano limita. null = sem limite.
    public const CUSTOMERS      = 'customers';      // clientes (cartoes) da loja
    public const ACTIVE_REWARDS = 'active_rewards'; // premios ativos ao mesmo tempo

    private const LIMITS = [
        self::FREE => [self::CUSTOMERS => 100,  self::ACTIVE_REWARDS => 3],
        self::PRO  => [self::CUSTOMERS => null, self::ACTIVE_REWARDS => null],
    ];

    public static function isValid(string $plan): bool {
        return isset(self::LIMITS[$plan]);
    }

    // plano desconhecido (valor estranho no banco) vale como o mais restrito, nunca como "sem limite"
    public static function normalize(?string $plan): string {
        return $plan !== null && self::isValid($plan) ? $plan : self::FREE;
    }

    public static function label(?string $plan): string {
        return self::LABELS[self::normalize($plan)];
    }

    // limite do plano para o recurso, ou null quando nao ha limite
    public static function limit(?string $plan, string $resource): ?int {
        return self::LIMITS[self::normalize($plan)][$resource] ?? null;
    }

    // true se a loja, que hoje tem $current, ainda pode ganhar mais um.
    // quem ja passou do limite (ex.: voltou do Pro pro Free) fica com o que tem, so nao adiciona.
    public static function allowsOneMore(?string $plan, string $resource, int $current): bool {
        $limit = self::limit($plan, $resource);
        return $limit === null || $current < $limit;
    }
}
