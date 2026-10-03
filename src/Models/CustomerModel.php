<?php

namespace App\Models;

class CustomerModel {
    private $db;

    function __construct($db) {
        $this->db = $db;
    }

    public function findByPhone($phone) {
        $sql = 'SELECT id, name, phone FROM customers WHERE phone = :phone';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':phone' => $phone]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // cria o cliente e devolve o id gerado
    public function create($name, $phone) {
        $sql = 'INSERT INTO customers (name, phone) VALUES (:name, :phone)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name'  => $name,
            ':phone' => $phone,
        ]);

        return (int)$this->db->lastInsertId();
    }

    // devolve o id do cliente do telefone, criando se ainda nao existir.
    // se outro lojista cadastrar o mesmo telefone no mesmo instante, o UNIQUE barra
    // o segundo insert; ai o cliente que acabou de ser criado e relido.
    public function findOrCreate($name, $phone) {
        $customer = $this->findByPhone($phone);
        if ($customer) {
            return (int)$customer['id'];
        }

        try {
            return $this->create($name, $phone);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            return (int)$this->findByPhone($phone)['id'];
        }
    }
}
