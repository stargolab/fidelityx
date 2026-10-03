<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// lista de clientes: busca e paginacao pela tela
final class CustomersListTest extends HttpTestCase {
    private function seedCustomers(int $merchant, int $count): void {
        for ($i = 1; $i <= $count; $i++) {
            $phone = '1191' . str_pad((string)$i, 7, '0', STR_PAD_LEFT);
            $this->db->exec("INSERT INTO customers (phone) VALUES ('$phone')");
            $this->db->exec("INSERT INTO loyalty_cards (merchant_id, customer_id, customer_name) VALUES ($merchant, LAST_INSERT_ID(), 'Cliente $i')");
        }
    }

    public function testListaPagina20PorVezEMantemABusca(): void {
        $m = $this->createMerchant('loja@teste.test');
        $this->seedCustomers($m, 45);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customers');
        $this->assertSame(20, substr_count($this->lastBody, 'merchant%2Fcustomer&amp;phone='));
        $this->assertStringContainsString('Página 1 de 3', $this->lastBody);
        $this->assertStringContainsString('45 clientes', $this->lastBody);

        $this->get('merchant/customers&page=3');
        $this->assertSame(5, substr_count($this->lastBody, 'merchant%2Fcustomer&amp;phone='));
        $this->assertStringContainsString('Página 3 de 3', $this->lastBody);

        // pagina absurda cai na ultima, sem erro
        [$status] = $this->get('merchant/customers&page=999');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Página 3 de 3', $this->lastBody);

        // a busca entra no link da proxima pagina
        $this->get('merchant/customers&q=Cliente');
        $this->assertStringContainsString('q=Cliente', $this->lastBody);
        $this->assertStringContainsString('page=2', $this->lastBody);
    }

    public function testBuscaPorNomeETelefoneESemResultado(): void {
        $m = $this->createMerchant('loja@teste.test');
        $this->seedCustomers($m, 12);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customers&q=' . urlencode('Cliente 12'));
        $this->assertStringContainsString('Cliente 12', $this->lastBody);
        $this->assertStringNotContainsString('Cliente 3<', $this->lastBody);
        $this->assertStringContainsString('1 cliente<', str_replace(' </p>', '</p>', $this->lastBody) . '<');

        $this->get('merchant/customers&q=' . urlencode('(11) 91'));
        $this->assertStringContainsString('12 clientes', $this->lastBody);

        $this->get('merchant/customers&q=naoexiste');
        $this->assertStringContainsString('Nenhum cliente encontrado', $this->lastBody);
    }

    public function testBuscaNaoMostraClienteDeOutraLojaENaoQuebraComLixo(): void {
        $a = $this->createMerchant('a@teste.test');
        $b = $this->createMerchant('b@teste.test');
        $this->seedCustomers($a, 3);
        $this->loginAs('b@teste.test');

        $this->get('merchant/customers&q=Cliente');
        $this->assertStringContainsString('Nenhum cliente', $this->lastBody);

        foreach (['%', '_', "' OR 1=1 --", '<script>', str_repeat('a', 500)] as $q) {
            [$status] = $this->get('merchant/customers&q=' . urlencode($q));
            $this->assertSame(200, $status, "busca por $q");
        }

        $this->get('merchant/customers&q=' . urlencode('<script>alert(1)</script>'));
        $this->assertStringContainsString('&lt;script&gt;alert(1)', $this->lastBody, 'busca digitada e escapada');
        $this->assertStringNotContainsString('<script>alert(1)', $this->lastBody);
    }
}
