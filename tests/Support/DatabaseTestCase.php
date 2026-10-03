<?php

namespace Tests\Support;

use App\Database;
use PDO;
use PHPUnit\Framework\TestCase;

// base dos testes que usam banco: cada teste comeca com as tabelas vazias
abstract class DatabaseTestCase extends TestCase {
    protected PDO $db;

    protected function setUp(): void {
        $this->db = Database::getConnection();
        TestDatabase::truncate($this->db);
    }

    // fabricas pequenas: so o necessario pra montar o cenario de cada teste

    protected function createMerchant(string $email = 'loja@teste.test'): int {
        $stmt = $this->db->prepare(
            "INSERT INTO merchants (owner_name, store_name, address, state, city, email, phone, category, cpf, password_hash)
             VALUES ('Dona Teste', 'Loja Teste', 'Rua 1', 'SP', 'Sao Paulo', :email, '11988887777', 'varejo', :cpf, :hash)"
        );
        $stmt->execute([
            ':email' => $email,
            ':cpf'   => substr(str_pad((string)crc32($email), 11, '0'), 0, 11),
            ':hash'  => password_hash('teste123', PASSWORD_BCRYPT),
        ]);
        return (int)$this->db->lastInsertId();
    }

    protected function createReward(int $merchantId, string $name, int $cost, bool $active = true): array {
        $stmt = $this->db->prepare(
            'INSERT INTO rewards (merchant_id, name, points_cost, active) VALUES (:m, :n, :c, :a)'
        );
        $stmt->execute([':m' => $merchantId, ':n' => $name, ':c' => $cost, ':a' => (int)$active]);
        return ['id' => (int)$this->db->lastInsertId(), 'name' => $name, 'points_cost' => $cost];
    }

    protected function scalar(string $sql, array $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
