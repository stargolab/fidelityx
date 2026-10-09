<?php

namespace App\Support;

use Dotenv\Dotenv;

// configuracao da aplicacao: vem das variaveis de ambiente (docker, servidor) e, se existir, do .env.
// tudo acaba no $_ENV, que e o que o resto do codigo le.
final class Env {
    // tudo que a aplicacao le do ambiente
    public const KEYS = [
        'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
        'APP_URL', 'APP_TIMEZONE', 'TRUSTED_PROXIES', 'LOG_FILE',
        'BACKUP_DIR', 'BACKUP_KEEP_DAYS', 'MYSQLDUMP_BIN',
        'MAIL_DRIVER', 'MAIL_LOG_FILE',
    ];

    // sem estas o banco nao conecta (DB_PASS pode ser vazia, DB_PORT tem padrao)
    public const REQUIRED = ['DB_HOST', 'DB_NAME', 'DB_USER'];

    // $root = pasta onde fica o .env (a raiz do projeto).
    // ordem de prioridade: o que ja esta no $_ENV, variavel de ambiente real, .env.
    public static function load(string $root): void {
        // variavel de ambiente real entra no $_ENV mesmo se o php.ini nao tiver "E" no variables_order
        // (o php.ini de producao nao tem, entao so o getenv enxerga o que o docker passou)
        foreach (self::KEYS as $key) {
            $value = getenv($key);
            if ($value !== false && !isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }

        // safeLoad: sem .env nao da erro (producao sobe so com variaveis de ambiente).
        // immutable: o .env so completa o que falta, nunca troca o que ja veio do ambiente.
        Dotenv::createImmutable($root)->safeLoad();
    }

    // obrigatorias que ficaram sem valor (lista vazia = configuracao ok)
    public static function missing(): array {
        return array_values(array_filter(
            self::REQUIRED,
            fn (string $key) => trim((string)($_ENV[$key] ?? '')) === ''
        ));
    }

    public static function get(string $key, string $default = ''): string {
        $value = trim((string)($_ENV[$key] ?? ''));
        return $value === '' ? $default : $value;
    }
}
