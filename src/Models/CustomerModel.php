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
}
