<?php
// roteador do php -S usado pelos testes de fluxo (tests/Feature).
// passa o banco de teste (variaveis de ambiente do processo) pro $_ENV antes do index.php;
// o Dotenv::createImmutable do index.php nao sobrescreve o que ja existe, entao o .env nao troca o banco.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $_SERVER['DOCUMENT_ROOT'] . $path;
if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
    return false; // css, js e imagens saem direto
}

foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
    $value = getenv($key);
    if ($value !== false) {
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}

// warnings e notices aparecem no html: um teste que procura texto na pagina pega o erro na hora
// (no CI o php.ini pode vir com display_errors desligado)
ini_set('display_errors', '1');
error_reporting(E_ALL);

chdir($_SERVER['DOCUMENT_ROOT']);
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
