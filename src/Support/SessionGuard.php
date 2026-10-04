<?php

namespace App\Support;

// regras da sessao do lojista (usadas no index.php e no authGuard do MerchantController)
final class SessionGuard {
    // sessao parada por mais que isso expira (um dia de balcao). o gc do PHP usa o mesmo valor,
    // senao o padrao dele (24 min) apagaria a sessao antes.
    public const IDLE_SECONDS = 8 * 3600;

    // true se a sessao ficou parada alem do limite. sem registro de atividade = sessao nova, nao expira.
    public static function isExpired(?int $lastSeen, int $now, int $idle = self::IDLE_SECONDS): bool {
        return $lastSeen !== null && ($now - $lastSeen) > $idle;
    }

    // apaga os dados e o cookie da sessao (logout, conta desativada, sessao expirada)
    public static function destroy(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
