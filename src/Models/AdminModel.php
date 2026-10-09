<?php

namespace App\Models;

use App\Support\PasswordPolicy;

// administradores do FidelityX (task 30). separados dos lojistas de proposito: outra tabela, outro login.
class AdminModel {
    private $db;

    function __construct($db) {
        $this->db = $db;
    }

    public function findByEmail(string $email) {
        $stmt = $this->db->prepare('SELECT id, name, email, password_hash FROM admins WHERE email = :email');
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id) {
        $stmt = $this->db->prepare('SELECT id, name, email, password_hash FROM admins WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // o que esta errado nos dados de um admin novo (texto pra linha de comando) ou null se esta tudo certo
    public static function problem(string $email, string $name, string $password): ?string {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            return 'e-mail invalido';
        }
        if (trim($name) === '' || mb_strlen($name) > 255) {
            return 'informe o nome (ate 255 caracteres)';
        }
        if (PasswordPolicy::problem($password) !== null) {
            return 'a senha precisa ter de ' . PasswordPolicy::MIN_BYTES . ' a ' . PasswordPolicy::MAX_BYTES . ' bytes';
        }
        return null;
    }

    // cria o admin e devolve o id. e-mail repetido sobe como PDOException (UNIQUE).
    public function create(string $email, string $name, string $password): int {
        $problem = self::problem($email, $name, $password);
        if ($problem !== null) {
            throw new \InvalidArgumentException($problem);
        }

        $stmt = $this->db->prepare('INSERT INTO admins (email, name, password_hash) VALUES (:email, :name, :hash)');
        $stmt->execute([':email' => $email, ':name' => trim($name), ':hash' => password_hash($password, PASSWORD_BCRYPT)]);
        return (int)$this->db->lastInsertId();
    }
}
