<?php

namespace Tests\Integration;

use App\Models\CustomerModel;
use Tests\Support\DatabaseTestCase;

// cliente = so o telefone (o nome fica no cartao de cada loja, ver LgpdTest)
final class CustomerModelTest extends DatabaseTestCase {
    public function testFindOrCreateCriaClienteNovo(): void {
        $id = (new CustomerModel($this->db))->findOrCreate('11999990001');

        $this->assertGreaterThan(0, $id);
        $this->assertSame('11999990001', $this->scalar('SELECT phone FROM customers WHERE id = ?', [$id]));
    }

    public function testFindOrCreateReaproveitaClienteExistente(): void {
        $customers = new CustomerModel($this->db);
        $first = $customers->findOrCreate('11999990001');
        $second = $customers->findOrCreate('11999990001');

        $this->assertSame($first, $second);
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM customers'));
    }

    public function testClienteNaoTemMaisNomeGlobal(): void {
        $columns = $this->db->query('SHOW COLUMNS FROM customers')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertNotContains('name', $columns, 'o nome e por loja (loyalty_cards.customer_name)');
    }

    // task 10: outro lojista cadastrou o mesmo telefone entre a busca e o insert.
    // o UNIQUE barra o segundo insert e o cliente que acabou de nascer e relido, sem erro 500.
    public function testCadastroSimultaneoReleOClienteEmVezDeQuebrar(): void {
        $existingId = (new CustomerModel($this->db))->create('11999990001');

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

        $id = $racing->findOrCreate('11999990001');

        $this->assertSame($existingId, $id);
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM customers'));
    }

    public function testFindByPhoneDevolveFalseQuandoNaoExiste(): void {
        $this->assertFalse((new CustomerModel($this->db))->findByPhone('11999990001'));
    }
}
