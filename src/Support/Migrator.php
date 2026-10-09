<?php

namespace App\Support;

use PDO;
use RuntimeException;

// controle de versao das migrations: a tabela schema_migrations guarda o nome de cada arquivo de
// database/migrations que ja rodou neste banco (uso pela linha de comando: php bin/migrate.php).
//
// o que conta e o NOME do arquivo, nao o maior numero aplicado: migration que chega depois com numero
// menor (ex.: dois PRs abertos ao mesmo tempo, um com 004/005 e outro com 006) continua pendente e roda.
final class Migrator {
    public const TABLE = 'schema_migrations';
    private const LOCK = 'fidelityx_migrate';

    private $db;
    private $dir;

    public function __construct(PDO $db, string $dir) {
        $this->db = $db;
        $this->dir = rtrim($dir, '/\\');
    }

    // nomes dos arquivos NNN_descricao.sql, sem a extensao e em ordem
    public function available(): array {
        $names = [];
        foreach (glob($this->dir . '/*.sql') ?: [] as $file) {
            $name = basename($file, '.sql');
            if (preg_match('/^\d{3}_[a-z0-9_]+$/', $name)) {
                $names[] = $name;
            }
        }
        sort($names, SORT_STRING);
        return $names;
    }

    // nome => data em que rodou (ou foi marcada como ja aplicada)
    public function applied(): array {
        $this->ensureTable();
        $rows = $this->db->query('SELECT version, applied_at FROM ' . self::TABLE . ' ORDER BY version')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        return $rows;
    }

    public function pending(): array {
        return array_values(array_diff($this->available(), array_keys($this->applied())));
    }

    // banco que ja tem as tabelas do sistema mas nenhum registro de migration: foi criado antes deste
    // controle existir, e nao da pra adivinhar quais migrations ja rodaram nele (ver baseline)
    public function needsBaseline(): bool {
        return $this->applied() === [] && $this->tableExists('merchants');
    }

    // roda as pendentes em ordem e devolve os nomes das que rodaram.
    // $onApply e chamado antes de cada uma (o script usa pra mostrar o progresso).
    public function migrate(?callable $onApply = null): array {
        if (!$this->tableExists('merchants')) {
            throw new RuntimeException('banco vazio: importe o database/schema.sql (ele ja vem com todas as migrations registradas).');
        }
        if ($this->needsBaseline()) {
            throw new RuntimeException(
                'este banco foi criado antes do controle de migrations. informe a ultima migration que ja rodou nele: '
                . 'php bin/migrate.php baseline NNN (ex.: baseline 003)'
            );
        }

        $this->lock();
        try {
            $done = [];
            foreach ($this->pending() as $name) {
                if ($onApply !== null) {
                    $onApply($name);
                }
                $this->run($name);
                $done[] = $name;
            }
            return $done;
        } finally {
            $this->unlock();
        }
    }

    // marca como ja aplicadas, sem rodar, todas as migrations ate $upTo (numero "003" ou nome "003_lgpd").
    // devolve os nomes marcados agora.
    public function baseline(string $upTo): array {
        $available = $this->available();
        $limit = null;
        foreach ($available as $name) {
            if ($name === $upTo || str_starts_with($name, $upTo . '_')) {
                $limit = $name;
            }
        }
        if ($limit === null) {
            throw new RuntimeException("migration '$upTo' nao existe em database/migrations.");
        }

        $applied = $this->applied();
        $marked = [];
        foreach ($available as $name) {
            if (strcmp($name, $limit) <= 0 && !isset($applied[$name])) {
                $this->record($name);
                $marked[] = $name;
            }
        }
        return $marked;
    }

    public function ensureTable(): void {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                version VARCHAR(100) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    // quebra um arquivo .sql em comandos. tira os comentarios (-- e # ate o fim da linha, /* ... */) e os
    // comandos que escolhem o banco (USE / CREATE DATABASE): a migration roda sempre no banco da conexao (DB_NAME).
    // o ';' so separa comandos fora de texto ('...', "...", `...`) e fora de comentario, entao um
    // DEFAULT 'a;b' ou um COMMENT com ponto e virgula nao corta o comando no meio.
    public static function statements(string $sql): array {
        $statements = [];
        $current = '';
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            // texto entre aspas ou crases: copia ate fechar (\x e '' escapam, como no mysql)
            if ($char === "'" || $char === '"' || $char === '`') {
                $current .= $char;
                for ($i++; $i < $length; $i++) {
                    $current .= $sql[$i];
                    if ($sql[$i] === '\\' && $char !== '`' && $i + 1 < $length) {
                        $current .= $sql[++$i];
                    } elseif ($sql[$i] === $char) {
                        if (($sql[$i + 1] ?? '') !== $char) {
                            break;
                        }
                        $current .= $sql[++$i];
                    }
                }
                continue;
            }

            // comentario de linha: "-- " (o mysql exige o espaco) ou "#"
            if (($char === '-' && $next === '-' && in_array($sql[$i + 2] ?? "\n", [' ', "\t", "\r", "\n"], true)) || $char === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end - 1;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                $current .= ' ';
                continue;
            }

            if ($char === ';') {
                $statements[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }
        $statements[] = $current;

        $result = [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '' || preg_match('/^(USE|CREATE\s+DATABASE)\s/i', $statement)) {
                continue;
            }
            $result[] = $statement;
        }
        return $result;
    }

    // o mysql confirma cada ALTER/CREATE na hora (nao ha rollback de DDL): se um comando falhar no meio,
    // a migration nao e registrada e o erro diz em qual comando parou, pra corrigir o banco a mao.
    private function run(string $name): void {
        $statements = self::statements((string)file_get_contents($this->dir . '/' . $name . '.sql'));
        foreach ($statements as $i => $statement) {
            try {
                $this->db->exec($statement);
            } catch (\PDOException $e) {
                throw new RuntimeException(
                    sprintf('%s falhou no comando %d de %d: %s', $name, $i + 1, count($statements), $e->getMessage()),
                    0,
                    $e
                );
            }
        }
        $this->record($name);
    }

    private function record(string $name): void {
        $stmt = $this->db->prepare('INSERT INTO ' . self::TABLE . ' (version) VALUES (:version)');
        $stmt->execute([':version' => $name]);
    }

    private function tableExists(string $table): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute([':table' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // duas pessoas (ou dois containers) rodando ao mesmo tempo: a segunda para em vez de aplicar em dobro
    private function lock(): void {
        if ((int)$this->db->query("SELECT GET_LOCK(CONCAT('" . self::LOCK . "_', DATABASE()), 0)")->fetchColumn() !== 1) {
            throw new RuntimeException('ja existe uma migracao em andamento neste banco.');
        }
    }

    private function unlock(): void {
        $this->db->query("SELECT RELEASE_LOCK(CONCAT('" . self::LOCK . "_', DATABASE()))")->fetchColumn();
    }
}
