<?php

namespace App\Support;

// regras do login do lojista que nao dependem de http (task 45)
final class LoginGuard {
    // hash de uma senha aleatoria que ninguem sabe, com o mesmo custo do password_hash do cadastro.
    // e-mail que nao existe tambem roda o password_verify (contra este hash), entao a resposta leva
    // o mesmo tempo da senha errada e nao revela quais e-mails sao lojas.
    public const DUMMY_HASH = '$2y$10$P3Cmt4YHVU9P3LDWxJg6huOfigMTBC.gbKP3kF3.60ZbFp/q7ugJK';

    // teto de erros por conta, somando todos os ips, na mesma janela do login. ataque espalhado
    // por muitos ips ganha 5 tentativas por ip em cada conta; passar daqui e sinal de ataque.
    // nao bloqueia (trancaria a dona, ver task 38): por enquanto so avisa no log.
    // o CAPTCHA que deve entrar a partir daqui ainda nao foi escolhido.
    public const MAX_FAILURES_PER_ACCOUNT = 50;

    // senha confere com a conta. sem conta, compara com o DUMMY_HASH e devolve false.
    public static function verify(?array $merchant, string $password): bool {
        $hash = $merchant['password_hash'] ?? self::DUMMY_HASH;
        $ok = password_verify($password, $hash);
        return $merchant !== null && $ok;
    }

    // linha de log quando a conta acaba de chegar no teto (uma vez por janela, nao a cada erro).
    // o e-mail nao vai pro log: so um pedaco do hash, o mesmo do rate_limit_hits, pra cruzar se precisar.
    public static function accountAlert(int $failures, string $email): ?string {
        if ($failures !== self::MAX_FAILURES_PER_ACCOUNT) {
            return null;
        }
        $ref = substr(hash('sha256', mb_strtolower(trim($email))), 0, 12);
        return sprintf('[login] %d erros de senha na mesma conta em 15 min, de varios ips (conta %s): possivel ataque distribuido', $failures, $ref);
    }
}
