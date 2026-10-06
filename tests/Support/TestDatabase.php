<?php

namespace Tests\Support;

use PDO;

// banco de teste: recriado do schema.sql no inicio da rodada e esvaziado antes de cada teste
final class TestDatabase {
    // ordem respeita as chaves estrangeiras (filhos antes dos pais)
    private const TABLES = ['points_log', 'rewards', 'loyalty_cards', 'customers', 'password_resets', 'email_verifications', 'merchants', 'rate_limit_hits'];

    public static function name(): string {
        return $_ENV['DB_NAME'];
    }

    // apaga e cria o banco de teste a partir do database/schema.sql
    public static function recreate(): void {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $_ENV['DB_HOST'], $_ENV['DB_PORT'] ?? '3306'),
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $name = self::name();
        $sql = file_get_contents(__DIR__ . '/../../database/schema.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $sql = str_replace(
            ['CREATE DATABASE IF NOT EXISTS fidelityx;', 'USE fidelityx;'],
            ["CREATE DATABASE `$name`;", "USE `$name`;"],
            $sql
        );

        $pdo->exec("DROP DATABASE IF EXISTS `$name`");
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
    }

    // esvazia todas as tabelas antes de cada teste.
    // DELETE e nao TRUNCATE: com poucas linhas e bem mais rapido (TRUNCATE recria a tabela no InnoDB).
    public static function truncate(PDO $db): void {
        foreach (self::TABLES as $table) {
            $db->exec("DELETE FROM `$table`");
        }
    }
}
