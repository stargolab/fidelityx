<?php
// prepara o ambiente de teste: autoload, credenciais do banco e banco de teste limpo.
//
// credenciais: local vem do .env (DB_HOST, DB_USER...); no CI vem das variaveis de ambiente.
// o DB_NAME e forcado pelo phpunit.xml para fidelityx_test, e o .env nunca sobrescreve isso.

require __DIR__ . '/../vendor/autoload.php';

// variaveis de ambiente reais (CI) entram no $_ENV mesmo se o php.ini nao tiver "E" no variables_order
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
    $value = getenv($key);
    if ($value !== false && !isset($_ENV[$key])) {
        $_ENV[$key] = $value;
    }
}

// local: completa o que faltar com o .env (immutable: nao troca o que ja existe, como o DB_NAME de teste)
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$dbName = $_ENV['DB_NAME'] ?? '';
if (!preg_match('/^[a-z0-9_]+_test$/', $dbName)) {
    // trava de seguranca: o bootstrap apaga e recria o banco, entao nunca pode apontar pro banco de verdade
    fwrite(STDERR, "DB_NAME de teste invalido ('$dbName'): precisa terminar em _test.\n");
    exit(1);
}

Tests\Support\TestDatabase::recreate();
