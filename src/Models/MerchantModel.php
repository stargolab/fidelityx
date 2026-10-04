<?php

namespace App\Models;

use App\Support\PublicCode;

class MerchantModel{
    private $db;

    function __construct($db){
        $this->db = $db;
    }

    // cria o lojista ja com o codigo publico da loja.
    // codigo repetido (chance minima) e sorteado de novo; outros duplicados (e-mail, cpf, cnpj) sobem pro controller.
    public function create($data){
        $sql = "INSERT INTO merchants (public_code, owner_name, store_name, address, state, city, email, phone, category, cpf, cnpj, password_hash) VALUES (:public_code, :on, :sn, :address, :state, :city, :email, :phone, :category, :cpf, :cnpj, :password_hash)";
        $stmt = $this->db->prepare($sql);

        for ($attempt = 1; ; $attempt++) {
            try {
                return $stmt->execute([':public_code' => PublicCode::generate()] + $data);
            } catch (\PDOException $e) {
                $duplicatedCode = ($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'uq_merchants_public_code');
                if (!$duplicatedCode || $attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    public function findByEmail($email){
        $sql = 'SELECT id, owner_name, store_name, password_hash, status FROM merchants WHERE email = :email';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':email' => $email,
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC); // array associativo
    }

    // dados que o authGuard confere a cada requisicao
    public function findById($merchantId) {
        $stmt = $this->db->prepare('SELECT id, owner_name, store_name, status, points_rule_cents FROM merchants WHERE id = :id');
        $stmt->execute([':id' => $merchantId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // regra de pontos pelo valor da compra (centavos por ponto); null remove a regra
    public function updatePointsRule($merchantId, ?int $ruleCents): void {
        $stmt = $this->db->prepare('UPDATE merchants SET points_rule_cents = :rule WHERE id = :id');
        $stmt->execute([':rule' => $ruleCents, ':id' => $merchantId]);
    }

    public function findPublicCode($merchantId): ?string {
        $stmt = $this->db->prepare('SELECT public_code FROM merchants WHERE id = :id');
        $stmt->execute([':id' => $merchantId]);
        $code = $stmt->fetchColumn();

        return $code === false ? null : $code;
    }

    // loja ativa pelo codigo publico (consulta de saldo); false se nao existir
    public function findActiveByPublicCode(string $code) {
        $stmt = $this->db->prepare(
            "SELECT id, store_name, public_code FROM merchants WHERE public_code = :code AND status = 'active'"
        );
        $stmt->execute([':code' => $code]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
