<?php

namespace Tests\Integration;

use App\Models\CustomerModel;
use Tests\Support\DatabaseTestCase;

final class CustomerModelTest extends DatabaseTestCase {
    public function testFindOrCreateCriaClienteNovo(): void {
        $id = (new CustomerModel($this->db))->findOrCreate('Ana Teste', '11999990001');

        $this->assertGreaterThan(0, $id);
        $this->assertSame('Ana Teste', $this->scalar('SELECT name FROM customers WHERE id = ?', [$id]));
    }

    public function testFindOrCreateReaproveitaClienteExistenteSemTrocarONome(): void {
        $customers = new CustomerModel($this->db);
        $first = $customers->findOrCreate('Ana Teste', '11999990001');
        $second = $customers->findOrCreate('Outro Nome', '11999990001');

        $this->assertSame($first, $second);
        $this->assertSame('Ana Teste', $this->scalar('SELECT name FROM customers WHERE id = ?', [$first]));
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM customers'));
    }

    // task 10: outro lojista cadastrou o mesmo telefone entre a busca e o insert.
    // o UNIQUE barra o segundo insert e o cliente que acabou de nascer e relido, sem erro 500.
    public function testCadastroSimultaneoReleOClienteEmVezDeQuebrar(): void {
        $existingId = (new CustomerModel($this->db))->create('Cadastro da Outra Loja', '11999990001');

        $racing = new class($this->db) extends CustomerModel {
            private bool $firstLookup = true;

            // na 1a busca finge que o telefone ainda nao existe (o outro cadastro ainda nao tinha acontecido)
            public function findByPhone($phone) {
                if ($this->firstLookup) {
                    $this->firstLookup = false;
                    return false;
                }
                return parent::findByPhone($phone);
            }
        };

        $id = $racing->findOrCreate('Nome Digitado Aqui', '11999990001');

        $this->assertSame($existingId, $id);
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM customers'));
    }

    public function testFindByPhoneDevolveFalseQuandoNaoExiste(): void {
        $this->assertFalse((new CustomerModel($this->db))->findByPhone('11999990001'));
    }
}
