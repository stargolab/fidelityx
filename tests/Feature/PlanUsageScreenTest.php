<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// task 32: o lojista ve o plano da loja e quanto dos limites ja usou (perfil e tela de premios)
final class PlanUsageScreenTest extends HttpTestCase {
    private function store(string $plan = 'free'): int {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->db->exec("UPDATE merchants SET plan = '$plan' WHERE id = $merchant");
        return $merchant;
    }

    private function fillCustomers(int $merchant, int $total): void {
        $customer = $this->db->prepare('INSERT INTO customers (phone) VALUES (:phone)');
        $card = $this->db->prepare(
            "INSERT INTO loyalty_cards (merchant_id, customer_id, customer_name) VALUES (:merchant, :customer, 'Cliente')"
        );
        for ($i = 1; $i <= $total; $i++) {
            $customer->execute([':phone' => sprintf('119%08d', $i)]);
            $card->execute([':merchant' => $merchant, ':customer' => (int)$this->db->lastInsertId()]);
        }
    }

    // o html tem quebras de linha entre as partes da frase: compara sem os espacos repetidos
    private function text(): string {
        return (string)preg_replace('/\s+/', ' ', strip_tags($this->lastBody));
    }

    public function testPerfilMostraOPlanoFreeEOUsoDosLimites(): void {
        $merchant = $this->store();
        $this->fillCustomers($merchant, 37);
        $this->createReward($merchant, 'Cafe', 10);
        $this->createReward($merchant, 'Suco', 20, false); // inativo nao conta
        $this->loginAs('loja@teste.test');

        $this->get('merchant/profile');
        $this->assertStringContainsString('<h2>Plano Free</h2>', $this->lastBody);
        $this->assertStringContainsString('37 de 100 clientes', $this->text());
        $this->assertStringContainsString('1 de 3 prêmios ativos', $this->text());
        // barras: 37% arredonda pra 35 e 1 de 3 (33%) tambem
        $this->assertStringContainsString('aria-valuenow="37"', $this->lastBody);
        $this->assertStringContainsString('progress-bar progress-w-35', $this->lastBody);
        $this->assertStringContainsString('peça o plano Pro', $this->lastBody);
        $this->assertStringNotContainsString('Você chegou ao limite', $this->lastBody);
    }

    public function testPerfilAvisaQuandoChegaNoLimite(): void {
        $merchant = $this->store();
        $this->fillCustomers($merchant, 100);
        foreach (['Cafe', 'Suco', 'Bolo'] as $name) {
            $this->createReward($merchant, $name, 10);
        }
        $this->loginAs('loja@teste.test');

        $this->get('merchant/profile');
        $this->assertStringContainsString('100 de 100 clientes', $this->text());
        $this->assertStringContainsString('progress-bar progress-w-100', $this->lastBody);
        $this->assertStringContainsString('Você chegou ao limite de clientes e de prêmios ativos do plano Free', $this->text());
    }

    // loja acima do limite (voltou do Pro): a barra para em 100%, sem classe que nao existe no css
    public function testLojaAcimaDoLimiteMostraBarraCheia(): void {
        $merchant = $this->store();
        $this->fillCustomers($merchant, 130);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/profile');
        $this->assertStringContainsString('130 de 100 clientes', $this->text());
        $this->assertStringContainsString('aria-valuenow="100"', $this->lastBody);
        $this->assertStringNotContainsString('progress-w-130', $this->lastBody);
    }

    public function testProMostraSemLimiteESemBarra(): void {
        $merchant = $this->store('pro');
        $this->fillCustomers($merchant, 5);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/profile');
        $this->assertStringContainsString('<h2>Plano Pro</h2>', $this->lastBody);
        $this->assertStringContainsString('5 clientes (sem limite)', $this->text());
        $this->assertStringNotContainsString('progressbar', $this->lastBody);
        $this->assertStringNotContainsString('peça o plano Pro', $this->lastBody);
    }

    public function testTelaDePremiosMostraQuantosAtivosCabem(): void {
        $merchant = $this->store();
        $this->createReward($merchant, 'Cafe', 10);
        $this->createReward($merchant, 'Suco', 20);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/rewards');
        $this->assertStringContainsString('2 de 3 prêmios ativos do plano Free', $this->text());
        $this->assertStringNotContainsString('desative um prêmio ou peça o plano Pro', $this->text());

        $this->createReward($merchant, 'Bolo', 30);
        $this->get('merchant/rewards');
        $this->assertStringContainsString('3 de 3 prêmios ativos do plano Free', $this->text());
        $this->assertStringContainsString('desative um prêmio ou peça o plano Pro', $this->text());
    }

    public function testTelaDePremiosDoProNaoMostraLimite(): void {
        $merchant = $this->store('pro');
        $this->createReward($merchant, 'Cafe', 10);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/rewards');
        $this->assertStringNotContainsString('prêmios ativos do plano', $this->text());
    }

    // o uso mostrado e so o desta loja
    public function testUsoDeUmaLojaNaoApareceNaOutra(): void {
        $outra = $this->createMerchant('outra@teste.test');
        $this->fillCustomers($outra, 50);
        $this->store();
        $this->loginAs('loja@teste.test');

        $this->get('merchant/profile');
        $this->assertStringContainsString('0 de 100 clientes', $this->text());
    }
}
