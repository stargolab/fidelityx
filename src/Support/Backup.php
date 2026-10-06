<?php

namespace App\Support;

use RuntimeException;

// backup do banco com o mysqldump: um arquivo .sql.gz por rodada, com data e hora no nome,
// e limpeza dos arquivos mais antigos que o prazo (uso pela linha de comando: php bin/backup.php).
//
// o arquivo tem os dados dos clientes (telefone, nome): guarde em lugar com acesso restrito
// e copie para fora do servidor, senao o backup some junto com ele.
final class Backup {
    public const DEFAULT_KEEP_DAYS = 14;

    // "fidelityx-20261005-114500.sql.gz"
    public static function fileName(string $database, \DateTimeInterface $now, bool $gzip = true): string {
        return sprintf('%s-%s.sql%s', $database, $now->format('Ymd-His'), $gzip ? '.gz' : '');
    }

    // dos nomes de arquivo da pasta, quais sao backups deste banco feitos ha mais de $keepDays dias.
    // vale a data que esta no nome (a de modificacao muda quando o arquivo e copiado ou restaurado).
    // arquivo com outro nome nunca entra, entao a pasta pode ter outras coisas. $keepDays <= 0 guarda tudo.
    public static function expired(array $fileNames, string $database, \DateTimeInterface $now, int $keepDays): array {
        if ($keepDays <= 0) {
            return [];
        }

        $limit = \DateTimeImmutable::createFromInterface($now)->modify("-$keepDays days")->format('Ymd-His');
        $pattern = '/^' . preg_quote($database, '/') . '-(\d{8}-\d{6})\.sql(\.gz)?$/';

        $expired = [];
        foreach ($fileNames as $name) {
            if (preg_match($pattern, $name, $m) && strcmp($m[1], $limit) < 0) {
                $expired[] = $name;
            }
        }
        return $expired;
    }

    // comando do mysqldump (em lista, sem shell no meio). a senha nao entra aqui: vai pela variavel
    // MYSQL_PWD, porque argumento de linha de comando aparece na lista de processos do servidor.
    public static function dumpCommand(string $bin, array $config): array {
        return [
            $bin,
            '--host=' . $config['host'],
            '--port=' . $config['port'],
            '--user=' . $config['user'],
            '--single-transaction', // copia consistente das tabelas InnoDB sem travar o sistema
            '--quick',
            '--routines',
            '--triggers',
            '--no-tablespaces',     // dispensa o privilegio PROCESS no usuario do backup
            '--default-character-set=utf8mb4',
            $config['name'],
        ];
    }

    // acha o mysqldump: o caminho configurado (MYSQLDUMP_BIN), o do PATH ou o do XAMPP
    public static function findDump(string $configured = ''): ?string {
        $candidates = $configured !== '' ? [$configured] : ['mysqldump', 'C:\\xampp\\mysql\\bin\\mysqldump.exe'];
        foreach ($candidates as $bin) {
            $process = @proc_open(
                [$bin, '--version'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            if (!is_resource($process)) {
                continue;
            }
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) === 0) {
                return $bin;
            }
        }
        return null;
    }

    // faz o backup em $dir e apaga os vencidos. devolve ['file' => caminho criado, 'removed' => nomes apagados].
    // $config = ['host', 'port', 'name', 'user', 'pass'] do banco.
    public static function run(array $config, string $dir, int $keepDays, string $dumpBin, ?\DateTimeInterface $now = null): array {
        $now = $now ?? new \DateTimeImmutable();

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException("nao foi possivel criar a pasta de backup: $dir");
        }
        if (!is_writable($dir)) {
            throw new RuntimeException("sem permissao de escrita na pasta de backup: $dir");
        }

        $gzip = function_exists('gzopen');
        $final = $dir . DIRECTORY_SEPARATOR . self::fileName($config['name'], $now, $gzip);
        // grava num .part e so renomeia no fim: backup interrompido nunca fica com cara de backup bom
        $partial = $final . '.part';

        try {
            self::dump(self::dumpCommand($dumpBin, $config), (string)$config['pass'], $partial, $gzip);
        } catch (\Throwable $e) {
            @unlink($partial);
            throw $e;
        }

        if (!rename($partial, $final)) {
            @unlink($partial);
            throw new RuntimeException("nao foi possivel gravar $final");
        }
        @chmod($final, 0600);

        // so limpa depois que o backup novo deu certo: uma falha nunca deixa a pasta sem backup recente
        $removed = [];
        foreach (self::expired(scandir($dir) ?: [], $config['name'], $now, $keepDays) as $name) {
            if ($name !== basename($final) && @unlink($dir . DIRECTORY_SEPARATOR . $name)) {
                $removed[] = $name;
            }
        }

        return ['file' => $final, 'removed' => $removed];
    }

    private static function dump(array $command, string $password, string $target, bool $gzip): void {
        // erros do mysqldump vao pra um arquivo temporario: ler dois pipes ao mesmo tempo pode travar
        $errors = tmpfile();
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $errors],
            $pipes,
            null,
            array_merge(getenv(), ['MYSQL_PWD' => $password])
        );
        if (!is_resource($process)) {
            throw new RuntimeException('nao foi possivel executar o mysqldump: ' . $command[0]);
        }
        fclose($pipes[0]);

        $out = $gzip ? gzopen($target, 'wb6') : fopen($target, 'wb');
        if ($out === false) {
            fclose($pipes[1]);
            proc_terminate($process);
            proc_close($process);
            throw new RuntimeException("nao foi possivel gravar $target");
        }

        $bytes = 0;
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 1 << 16);
            if ($chunk === false || $chunk === '') {
                continue;
            }
            $bytes += strlen($chunk);
            $gzip ? gzwrite($out, $chunk) : fwrite($out, $chunk);
        }
        fclose($pipes[1]);
        $gzip ? gzclose($out) : fclose($out);

        $exit = proc_close($process);
        rewind($errors);
        $message = trim((string)stream_get_contents($errors));
        fclose($errors);

        if ($exit !== 0 || $bytes === 0) {
            throw new RuntimeException('mysqldump falhou' . ($message !== '' ? ': ' . $message : " (codigo $exit)"));
        }
    }
}
