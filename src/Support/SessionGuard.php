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

    // "assinatura" da senha atual, guardada na sessao no login. o authGuard compara com a do banco
    // a cada requisicao: trocou a senha, as sessoes abertas com a senha antiga (outro aparelho,
    // alguem que entrou sem permissao) deixam de valer. e um hash do hash: nao revela nada da senha.
    public static function passwordSignature(string $passwordHash): string {
        return hash('sha256', $passwordHash);
    }

    // true se a sessao foi aberta com uma senha que nao e mais a da conta.
    // sessao sem assinatura (aberta antes desta regra existir) nao e derrubada.
    public static function passwordChanged(?string $sessionSignature, string $passwordHash): bool {
        return $sessionSignature !== null && !hash_equals(self::passwordSignature($passwordHash), $sessionSignature);
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
