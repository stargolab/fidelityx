<?php

namespace App\Models;

// cliente = so o telefone, unico na plataforma. nome, consentimento e saldo ficam no cartao
// de cada loja (LoyaltyCardModel): uma loja nunca ve o que o cliente informou em outra.
class CustomerModel {
    private $db;

    function __construct($db) {
        $this->db = $db;
    }

    public function findByPhone($phone) {
        $sql = 'SELECT id, phone FROM customers WHERE phone = :phone';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':phone' => $phone]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // cria o cliente e devolve o id gerado
    public function create($phone) {
        $stmt = $this->db->prepare('INSERT INTO customers (phone) VALUES (:phone)');
        $stmt->execute([':phone' => $phone]);

        return (int)$this->db->lastInsertId();
    }

    // devolve o id do cliente do telefone, criando se ainda nao existir.
    // se outro lojista cadastrar o mesmo telefone no mesmo instante, o UNIQUE barra
    // o segundo insert; ai o cliente que acabou de ser criado e relido.
    public function findOrCreate($phone) {
        $customer = $this->findByPhone($phone);
        if ($customer) {
            return (int)$customer['id'];
        }

        try {
            return $this->create($phone);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            return (int)$this->findByPhone($phone)['id'];
        }
    }
}
