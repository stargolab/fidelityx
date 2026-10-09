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
            "INSERT INTO merchants (public_code, owner_name, store_name, address, state, city, email, email_verified_at, phone, category, cpf, password_hash)
             VALUES (:code, 'Dona Teste', 'Loja Teste', 'Rua 1', 'SP', 'Sao Paulo', :email, CURRENT_TIMESTAMP, '11988887777', 'varejo', :cpf, :hash)"
        );
        $stmt->execute([
            ':code'  => strtoupper(substr(md5($email), 0, 8)),
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

    // cliente (telefone global) + cartao nesta loja com o nome dado a ela; devolve o id do cartao.
    // $consent = false simula cadastro anterior ao registro de consentimento (migration 003).
    protected function createCard(int $merchantId, string $name, string $phone, bool $consent = true): int {
        $customerId = (new \App\Models\CustomerModel($this->db))->findOrCreate($phone);
        return (new \App\Models\LoyaltyCardModel($this->db))
            ->findOrCreate($merchantId, $customerId, $name, $consent ? \App\Support\Privacy::VERSION : null);
    }

    protected function publicCode(int $merchantId): string {
        return (string)$this->scalar('SELECT public_code FROM merchants WHERE id = :id', [':id' => $merchantId]);
    }

    protected function scalar(string $sql, array $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
