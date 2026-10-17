<?php

namespace Tests\Feature;

use App\Models\LoyaltyCardModel;
use Tests\Support\HttpTestCase;

// task 32: plano Free para em 100 clientes e 3 premios ativos; o Pro nao tem limite
final class PlanLimitsTest extends HttpTestCase {
    private const NOVO = '11955550005';

    private function store(string $plan = 'free', string $email = 'loja@teste.test'): int {
        $merchant = $this->createMerchant($email);
        $this->db->exec("UPDATE merchants SET plan = '$plan' WHERE id = $merchant");
        return $merchant;
    }

    // cria $total clientes direto no banco (telefones 11900000001, 11900000002...)
    private function fillCustomers(int $merchant, int $total): void {
        $customer = $this->db->prepare('INSERT INTO customers (phone) VALUES (:phone)');
        $card = $this->db->prepare(
            "INSERT INTO loyalty_cards (merchant_id, customer_id, customer_name) VALUES (:merchant, :customer, 'Cliente')"
        );
        for ($i = 1; $i <= $total; $i++) {
            $customer->execute([':phone' => sprintf('119%08d', $merchant * 1000 + $i)]);
            $card->execute([':merchant' => $merchant, ':customer' => (int)$this->db->lastInsertId()]);
        }
    }

    private function registerNew(string $phone = self::NOVO): ?string {
        return $this->post(
            'merchant/customer-new',
            ['phone' => $phone, 'name' => 'Cliente Novo', 'consent' => '1'],
            true,
            'merchant/dashboard'
        )[1];
    }

    private function cards(int $merchant): int {
        return (int)$this->scalar('SELECT COUNT(*) FROM loyalty_cards WHERE merchant_id = ?', [$merchant]);
    }

    private function addReward(string $name): ?string {
        return $this->post('merchant/rewards', ['action' => 'create', 'name' => $name, 'points_cost' => '10'], true, 'merchant/rewards')[1];
    }

    private function toggleReward(int $rewardId): ?string {
        return $this->post('merchant/rewards', ['action' => 'toggle', 'reward_id' => $rewardId], true, 'merchant/rewards')[1];
    }

    private function activeRewards(int $merchant): int {
        return (int)$this->scalar('SELECT COUNT(*) FROM rewards WHERE merchant_id = ? AND active = 1', [$merchant]);
    }

    // CLIENTES ----------------------------------------------------------

    public function testFreeCadastraOCentesimoClienteERecusaOProximo(): void {
        $merchant = $this->store();
        $this->fillCustomers($merchant, 99);
        $this->loginAs('loja@teste.test');

        $this->assertSame('merchant/customer&phone=' . self::NOVO . '&success=cliente_cadastrado', $this->registerNew());
        $this->assertSame(100, $this->cards($merchant));

        // o 101o nao entra, nem pela tela (GET) nem enviando o formulario direto (POST)
        $this->assertSame('merchant/dashboard&error=limite_clientes', $this->get('merchant/customer-new&phone=11966660006')[1]);
        $this->assertSame('merchant/dashboard&error=limite_clientes', $this->registerNew('11966660006'));
        $this->assertSame(100, $this->cards($merchant));

        $this->get('merchant/dashboard&error=limite_clientes');
        $this->assertStringContainsString('limite de 100 clientes do plano Free', $this->lastBody);
    }

    public function testNoLimiteOsClientesQueJaExistemContinuamSendoAtendidos(): void {
        $merchant = $this->store();
        $this->fillCustomers($merchant, 99);
        $card = $this->createCard($merchant, 'Bia', '11922220002');
        $this->loginAs('loja@teste.test');

        // buscar pelo telefone abre a tela do cliente, e lancar pontos funciona
        $this->assertSame('merchant/customer&phone=11922220002', $this->get('merchant/dashboard&phone=11922220002')[1]);
        $this->post('merchant/customer', ['action' => 'score', 'phone' => '11922220002', 'points' => '5'], true, 'merchant/customer&phone=11922220002');
        $this->assertSame(5, (int)$this->scalar('SELECT current_points FROM loyalty_cards WHERE id = ?', [$card]));
    }

    public function testProNaoTemLimiteDeClientes(): void {
        $merchant = $this->store('pro');
        $this->fillCustomers($merchant, 100);
        $this->loginAs('loja@teste.test');

        $this->assertSame('merchant/customer&phone=' . self::NOVO . '&success=cliente_cadastrado', $this->registerNew());
        $this->assertSame(101, $this->cards($merchant));
    }

    // cliente excluido a pedido (LGPD) deixa de contar e libera a vaga
    public function testClienteAnonimizadoNaoContaNoLimite(): void {
        $merchant = $this->store();
        $this->fillCustomers($merchant, 99);
        $card = $this->createCard($merchant, 'Bia', '11922220002');
        $this->loginAs('loja@teste.test');
        $this->assertSame('merchant/dashboard&error=limite_clientes', $this->registerNew());

        (new LoyaltyCardModel($this->db))->anonymize($card, $merchant);
        $this->assertSame('merchant/customer&phone=' . self::NOVO . '&success=cliente_cadastrado', $this->registerNew());
    }

    public function testLimiteDeUmaLojaNaoAfetaOutra(): void {
        $cheia = $this->store('free', 'cheia@teste.test');
        $this->fillCustomers($cheia, 100);
        $vazia = $this->store('free', 'vazia@teste.test');
        $this->loginAs('vazia@teste.test');

        $this->assertSame('merchant/customer&phone=' . self::NOVO . '&success=cliente_cadastrado', $this->registerNew());
        $this->assertSame(1, $this->cards($vazia));
    }

    // loja que voltou do Pro pro Free com mais de 100: fica com todos, so nao cadastra novo
    public function testLojaAcimaDoLimiteFicaComOQueTemMasNaoCadastraMais(): void {
        $merchant = $this->store();
        $this->fillCustomers($merchant, 120);
        $this->loginAs('loja@teste.test');

        $this->assertSame('merchant/dashboard&error=limite_clientes', $this->registerNew());
        $this->assertSame(120, $this->cards($merchant));
        $this->get('merchant/customers');
        $this->assertStringContainsString('120 clientes', $this->lastBody);
    }

    // PREMIOS -----------------------------------------------------------

    public function testFreeAceitaTresPremiosAtivosERecusaOQuarto(): void {
        $merchant = $this->store();
        $this->loginAs('loja@teste.test');

        foreach (['Cafe', 'Suco', 'Bolo'] as $name) {
            $this->assertSame('merchant/rewards&success=premio_criado', $this->addReward($name));
        }
        $this->assertSame('merchant/rewards&error=limite_premios', $this->addReward('Torta'));
        $this->assertSame(3, (int)$this->scalar('SELECT COUNT(*) FROM rewards WHERE merchant_id = ?', [$merchant]));

        $this->get('merchant/rewards&error=limite_premios');
        $this->assertStringContainsString('até 3 prêmios ativos', $this->lastBody);
    }

    // o limite e de premios ATIVOS: desativar um abre vaga, e reativar o quarto nao passa
    public function testDesativarAbreVagaEReativarRespeitaOLimite(): void {
        $merchant = $this->store();
        $cafe = $this->createReward($merchant, 'Cafe', 10)['id'];
        $this->createReward($merchant, 'Suco', 20);
        $this->createReward($merchant, 'Bolo', 30);
        $this->loginAs('loja@teste.test');

        $this->assertSame('merchant/rewards&success=premio_atualizado', $this->toggleReward($cafe));
        $this->assertSame(2, $this->activeRewards($merchant));

        $this->assertSame('merchant/rewards&success=premio_criado', $this->addReward('Torta'));
        $this->assertSame(3, $this->activeRewards($merchant));

        // o cafe esta inativo e nao tem mais vaga: reativar e recusado, e ele continua inativo
        $this->assertSame('merchant/rewards&error=limite_premios', $this->toggleReward($cafe));
        $this->assertSame(0, (int)$this->scalar('SELECT active FROM rewards WHERE id = ?', [$cafe]));
        $this->assertSame(3, $this->activeRewards($merchant));
    }

    public function testPremioInativoNaoContaNoLimite(): void {
        $merchant = $this->store();
        foreach (['A', 'B', 'C', 'D', 'E'] as $name) {
            $this->createReward($merchant, $name, 10, false);
        }
        $this->loginAs('loja@teste.test');

        $this->assertSame('merchant/rewards&success=premio_criado', $this->addReward('Cafe'));
    }

    public function testProNaoTemLimiteDePremios(): void {
        $merchant = $this->store('pro');
        $this->loginAs('loja@teste.test');

        foreach (['Cafe', 'Suco', 'Bolo', 'Torta', 'Pizza'] as $name) {
            $this->assertSame('merchant/rewards&success=premio_criado', $this->addReward($name));
        }
        $this->assertSame(5, $this->activeRewards($merchant));
    }

    // editar premio que ja existe nao e barrado pelo limite
    public function testEditarPremioNoLimiteContinuaFuncionando(): void {
        $merchant = $this->store();
        $cafe = $this->createReward($merchant, 'Cafe', 10)['id'];
        $this->createReward($merchant, 'Suco', 20);
        $this->createReward($merchant, 'Bolo', 30);
        $this->loginAs('loja@teste.test');

        [, $location] = $this->post(
            'merchant/reward-edit',
            ['reward_id' => $cafe, 'name' => 'Cafe grande', 'points_cost' => '15'],
            true,
            'merchant/reward-edit&id=' . $cafe
        );
        $this->assertSame('merchant/rewards&success=premio_editado', $location);
    }
}
