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

    // dados que o authGuard confere a cada requisicao (o hash da senha serve pra derrubar
    // as sessoes antigas quando a senha e trocada, ver SessionGuard::passwordSignature)
    public function findById($merchantId) {
        $stmt = $this->db->prepare('SELECT id, owner_name, store_name, status, points_rule_cents, points_expiry_months, password_hash FROM merchants WHERE id = :id');
        $stmt->execute([':id' => $merchantId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // regra de pontos pelo valor da compra (centavos por ponto); null remove a regra
    public function updatePointsRule($merchantId, ?int $ruleCents): void {
        $stmt = $this->db->prepare('UPDATE merchants SET points_rule_cents = :rule WHERE id = :id');
        $stmt->execute([':rule' => $ruleCents, ':id' => $merchantId]);
    }

    // validade dos pontos em meses sem movimentacao; null = os pontos da loja nao vencem
    public function updatePointsExpiry($merchantId, ?int $months): void {
        $stmt = $this->db->prepare('UPDATE merchants SET points_expiry_months = :months WHERE id = :id');
        $stmt->execute([':months' => $months, ':id' => $merchantId]);
    }

    // dados exibidos na tela de perfil; false se a conta nao existir
    public function findProfile($merchantId) {
        $stmt = $this->db->prepare(
            'SELECT owner_name, store_name, email, phone, category, cpf, cnpj, address, city, state
             FROM merchants WHERE id = :id'
        );
        $stmt->execute([':id' => $merchantId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // dados da loja que o proprio lojista edita. e-mail (login) e cpf/cnpj (identidade) ficam de fora de proposito.
    public function updateProfile($merchantId, array $fields): void {
        $stmt = $this->db->prepare(
            'UPDATE merchants
             SET owner_name = :owner_name, store_name = :store_name, phone = :phone, category = :category,
                 address = :address, city = :city, state = :state
             WHERE id = :id'
        );
        $stmt->execute([
            ':owner_name' => $fields['owner_name'],
            ':store_name' => $fields['store_name'],
            ':phone'      => $fields['phone'],
            ':category'   => $fields['category'],
            ':address'    => $fields['address'],
            ':city'       => $fields['city'],
            ':state'      => $fields['state'],
            ':id'         => $merchantId,
        ]);
    }

    public function updatePasswordHash($merchantId, string $hash): void {
        $stmt = $this->db->prepare('UPDATE merchants SET password_hash = :hash WHERE id = :id');
        $stmt->execute([':hash' => $hash, ':id' => $merchantId]);
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
