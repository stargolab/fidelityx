<?php
// prepara o ambiente de teste: autoload, credenciais do banco e banco de teste limpo.
//
// credenciais: local vem do .env (DB_HOST, DB_USER...); no CI vem das variaveis de ambiente.
// o DB_NAME e forcado pelo phpunit.xml para fidelityx_test, e o .env nunca sobrescreve isso.

require __DIR__ . '/../vendor/autoload.php';

// mesma carga do index.php: variaveis de ambiente reais (CI) primeiro, e o .env local so completa
// o que faltar (nunca troca o que ja existe, como o DB_NAME de teste)
App\Support\Env::load(dirname(__DIR__));

// mesmo fuso do index.php (o php.ini de cada maquina pode ter outro)
date_default_timezone_set(app_timezone());

$dbName = $_ENV['DB_NAME'] ?? '';
if (!preg_match('/^[a-z0-9_]+_test$/', $dbName)) {
    // trava de seguranca: o bootstrap apaga e recria o banco, entao nunca pode apontar pro banco de verdade
    fwrite(STDERR, "DB_NAME de teste invalido ('$dbName'): precisa terminar em _test.\n");
    exit(1);
}

Tests\Support\TestDatabase::recreate();
