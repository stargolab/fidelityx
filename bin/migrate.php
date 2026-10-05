<?php
// controle das migrations de database/migrations (tabela schema_migrations).
//
//   php bin/migrate.php                roda, em ordem, as migrations que ainda nao rodaram neste banco
//   php bin/migrate.php status         lista as migrations e quais ja rodaram
//   php bin/migrate.php baseline 003   marca como ja aplicadas (sem rodar) todas ate a 003:
//                                      para banco criado antes deste controle existir
//
// usa o banco do .env / variaveis de ambiente (DB_NAME); o "USE fidelityx" dos arquivos e ignorado.

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Support\Env;
use App\Support\Migrator;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

Env::load(dirname(__DIR__));
date_default_timezone_set(app_timezone());

$command = $argv[1] ?? 'up';

try {
    if (Env::missing() !== []) {
        throw new RuntimeException('variaveis obrigatorias sem valor: ' . implode(', ', Env::missing()));
    }

    $migrator = new Migrator(Database::connect(), dirname(__DIR__) . '/database/migrations');

    switch ($command) {
        case 'up':
            $done = $migrator->migrate(function (string $name) {
                echo "rodando $name...\n";
            });
            echo $done === [] ? "nada a fazer: o banco ja esta atualizado.\n" : count($done) . " migration(s) aplicada(s).\n";
            break;

        case 'status':
            $applied = $migrator->applied();
            foreach ($migrator->available() as $name) {
                printf("%-40s %s\n", $name, isset($applied[$name]) ? 'aplicada em ' . $applied[$name] : 'PENDENTE');
            }
            // registro sem arquivo: a migration veio de outra branch que nao esta neste checkout
            foreach (array_diff(array_keys($applied), $migrator->available()) as $name) {
                printf("%-40s %s\n", $name, 'aplicada em ' . $applied[$name] . ' (arquivo nao esta nesta branch)');
            }
            if ($migrator->needsBaseline()) {
                echo "\nbanco anterior ao controle de migrations: rode 'php bin/migrate.php baseline NNN' com a ultima que ja rodou nele.\n";
            }
            break;

        case 'baseline':
            if (!isset($argv[2])) {
                throw new RuntimeException('informe ate qual migration o banco ja esta: php bin/migrate.php baseline 003');
            }
            $marked = $migrator->baseline($argv[2]);
            echo $marked === [] ? "nada a marcar.\n" : "marcadas como ja aplicadas: " . implode(', ', $marked) . "\n";
            break;

        default:
            throw new RuntimeException("comando desconhecido: $command (use: up, status ou baseline NNN)");
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'erro: ' . $e->getMessage() . "\n");
    exit(1);
}
