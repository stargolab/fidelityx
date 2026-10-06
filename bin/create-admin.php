<?php
// cria um administrador do FidelityX (task 30), que entra em /index.php?url=admin/login.
//
//   php bin/create-admin.php email@dominio.com "Nome"
//
// a senha e pedida no terminal (ou lida da entrada: echo "senha" | php bin/create-admin.php ...),
// nunca vai como argumento (ficaria no historico do shell).
// usa o banco do .env / variaveis de ambiente (DB_NAME).

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Models\AdminModel;
use App\Support\Env;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

Env::load(dirname(__DIR__));
date_default_timezone_set(app_timezone());

$email = trim((string)($argv[1] ?? ''));
$name = trim((string)($argv[2] ?? ''));
if ($email === '' || $name === '') {
    fwrite(STDERR, "uso: php bin/create-admin.php email@dominio.com \"Nome\"\n");
    exit(1);
}

fwrite(STDOUT, 'senha: ');
$password = rtrim((string)fgets(STDIN), "\r\n");

$problem = AdminModel::problem($email, $name, $password);
if ($problem !== null) {
    fwrite(STDERR, "erro: $problem\n");
    exit(1);
}

try {
    if (Env::missing() !== []) {
        throw new RuntimeException('variaveis obrigatorias sem valor: ' . implode(', ', Env::missing()));
    }
    $id = (new AdminModel(Database::connect()))->create($email, $name, $password);
    fwrite(STDOUT, "\nadmin criado (id $id). entre em /index.php?url=admin/login\n");
} catch (PDOException $e) {
    fwrite(STDERR, (($e->errorInfo[1] ?? null) === 1062 ? 'erro: ja existe admin com esse e-mail' : 'erro no banco: ' . $e->getMessage()) . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, 'erro: ' . $e->getMessage() . "\n");
    exit(1);
}
