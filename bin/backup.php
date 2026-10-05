<?php
// backup do banco (mysqldump compactado) com limpeza dos arquivos antigos.
//
//   php bin/backup.php
//
// configuracao (.env ou variaveis de ambiente):
//   BACKUP_DIR        pasta de destino (padrao: storage/backups na raiz do projeto)
//   BACKUP_KEEP_DAYS  dias que cada backup fica guardado (padrao: 14; 0 = nunca apaga)
//   MYSQLDUMP_BIN     caminho do mysqldump, se nao estiver no PATH
//
// agendar uma vez por dia (cron, agendador de tarefas). sai com codigo 1 se falhar, pra o agendador avisar.
// restaurar: gunzip -c ARQUIVO.sql.gz | mysql -u USUARIO -p NOME_DO_BANCO   (ver docs/operacao.md)

require __DIR__ . '/../vendor/autoload.php';

use App\Support\Backup;
use App\Support\Env;
use App\Support\ErrorLog;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
Env::load($root);
date_default_timezone_set(app_timezone());
$logToFile = ErrorLog::useFile(Env::get('LOG_FILE'));

try {
    if (Env::missing() !== []) {
        throw new RuntimeException('variaveis obrigatorias sem valor: ' . implode(', ', Env::missing()));
    }

    $bin = Backup::findDump(Env::get('MYSQLDUMP_BIN'));
    if ($bin === null) {
        throw new RuntimeException('mysqldump nao encontrado: instale o cliente do MySQL ou aponte MYSQLDUMP_BIN para ele.');
    }

    $keepDays = Env::get('BACKUP_KEEP_DAYS');
    $result = Backup::run(
        [
            'host' => Env::get('DB_HOST'),
            'port' => Env::get('DB_PORT', '3306'),
            'name' => Env::get('DB_NAME'),
            'user' => Env::get('DB_USER'),
            'pass' => (string)($_ENV['DB_PASS'] ?? ''),
        ],
        Env::get('BACKUP_DIR', $root . '/storage/backups'),
        ctype_digit($keepDays) ? (int)$keepDays : Backup::DEFAULT_KEEP_DAYS,
        $bin
    );

    printf("backup gravado: %s (%s KB)\n", $result['file'], number_format(filesize($result['file']) / 1024, 0, ',', '.'));
    foreach ($result['removed'] as $name) {
        echo "backup antigo apagado: $name\n";
    }
} catch (Throwable $e) {
    // vai pro terminal e, se houver LOG_FILE, pro log de erros (o agendador nem sempre guarda a saida)
    fwrite(STDERR, 'erro no backup: ' . $e->getMessage() . "\n");
    if ($logToFile) {
        error_log('[backup] ' . $e->getMessage());
    }
    exit(1);
}
