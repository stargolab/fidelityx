<?php

namespace Tests\Integration;

use App\Models\CustomerModel;
use App\Models\LoyaltyCardModel;
use Tests\Support\DatabaseTestCase;

final class LoyaltyCardModelTest extends DatabaseTestCase {
    private LoyaltyCardModel $cards;
    private int $merchantId;
    private int $customerId;

    protected function setUp(): void {
        parent::setUp();
        $this->cards = new LoyaltyCardModel($this->db);
        $this->merchantId = $this->createMerchant();
        $this->customerId = (new CustomerModel($this->db))->create('11999990001');
    }

    public function testFindOrCreateDevolveOMesmoCartaoSemDuplicar(): void {
        $first = $this->cards->findOrCreate($this->merchantId, $this->customerId);
        $second = $this->cards->findOrCreate($this->merchantId, $this->customerId);

        $this->assertSame($first, $second);
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM loyalty_cards'));
    }

    public function testMesmoClienteTemUmCartaoPorLoja(): void {
        $otherMerchant = $this->createMerchant('outra@teste.test');

        $a = $this->cards->findOrCreate($this->merchantId, $this->customerId);
        $b = $this->cards->findOrCreate($otherMerchant, $this->customerId);

        $this->assertNotSame($a, $b);
    }

    public function testAddPointsSomaSaldoTotalEGravaHistorico(): void {
        $cardId = $this->cards->findOrCreate($this->merchantId, $this->customerId);

        $this->cards->addPoints($cardId, 30, 'Compra');
        $this->cards->addPoints($cardId, 5, 'Compra');

        $card = $this->cards->findByMerchantAndPhone($this->merchantId, '11999990001');
        $this->assertSame(35, (int)$card['current_points']);
        $this->assertSame(35, (int)$card['total_accumulated']);
        $this->assertSame(2, (int)$this->scalar("SELECT COUNT(*) FROM points_log WHERE card_id = ? AND type = 'earn'", [$cardId]));
    }

    public function testRedeemDebitaSaldoMantemTotalEGravaPremioNoHistorico(): void {
        $cardId = $this->cards->findOrCreate($this->merchantId, $this->customerId);
        $this->cards->addPoints($cardId, 30, 'Compra');
        $reward = $this->createReward($this->merchantId, 'Cafe', 20);

        $this->assertTrue($this->cards->redeem($cardId, $reward));

        $card = $this->cards->findByMerchantAndPhone($this->merchantId, '11999990001');
        $this->assertSame(10, (int)$card['current_points']);
        $this->assertSame(30, (int)$card['total_accumulated'], 'resgate nao reduz o total acumulado');

        $log = $this->db->query("SELECT quantity, reward_id, description FROM points_log WHERE type = 'redeem'")->fetch();
        $this->assertSame(20, (int)$log['quantity']);
        $this->assertSame($reward['id'], (int)$log['reward_id']);
        $this->assertSame('Resgate: Cafe', $log['description']);
    }

    public function testRedeemSemSaldoNaoMexeEmNada(): void {
        $cardId = $this->cards->findOrCreate($this->merchantId, $this->customerId);
        $this->cards->addPoints($cardId, 10, 'Compra');
        $reward = $this->createReward($this->merchantId, 'Bolo', 100);

        $this->assertFalse($this->cards->redeem($cardId, $reward));

        $this->assertSame(10, (int)$this->scalar('SELECT current_points FROM loyalty_cards WHERE id = ?', [$cardId]));
        $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM points_log WHERE type = 'redeem'"));
        $this->assertFalse($this->db->inTransaction(), 'a transacao do resgate recusado precisa ser fechada');
    }

    public function testRedeemComSaldoExatoZeraOCartao(): void {
        $cardId = $this->cards->findOrCreate($this->merchantId, $this->customerId);
        $this->cards->addPoints($cardId, 20, 'Compra');

        $this->assertTrue($this->cards->redeem($cardId, $this->createReward($this->merchantId, 'Cafe', 20)));
        $this->assertSame(0, (int)$this->scalar('SELECT current_points FROM loyalty_cards WHERE id = ?', [$cardId]));
    }

    public function testErroNoMeioDoLancamentoDesfazTudo(): void {
        $cardId = $this->cards->findOrCreate($this->merchantId, $this->customerId);

        // trigger que derruba o insert do historico, ou seja, falha DEPOIS do update do saldo.
        // (nao da pra usar texto longo demais: em sql_mode nao estrito o mysql so trunca, sem erro)
        $this->db->exec("CREATE TRIGGER falha_no_historico BEFORE INSERT ON points_log FOR EACH ROW
                         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'falha simulada'");
        try {
            $this->cards->addPoints($cardId, 50, 'Compra');
            $this->fail('era esperado erro no insert do historico');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('falha simulada', $e->getMessage());
        } finally {
            $this->db->exec('DROP TRIGGER IF EXISTS falha_no_historico');
        }

        $this->assertSame(0, (int)$this->scalar('SELECT current_points FROM loyalty_cards WHERE id = ?', [$cardId]), 'saldo voltou ao que era');
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM points_log'));
        $this->assertFalse($this->db->inTransaction());
    }

    public function testBuscaPorTelefoneRespeitaALoja(): void {
        $this->cards->findOrCreate($this->merchantId, $this->customerId);
        $otherMerchant = $this->createMerchant('outra@teste.test');

        $this->assertNotFalse($this->cards->findByMerchantAndPhone($this->merchantId, '11999990001'));
        $this->assertFalse($this->cards->findByMerchantAndPhone($otherMerchant, '11999990001'));
    }

    public function testNomeEConsentimentoSaoDoCartaoDeCadaLoja(): void {
        $otherMerchant = $this->createMerchant('outra@teste.test');

        $this->cards->findOrCreate($this->merchantId, $this->customerId, 'Ana Teste', 'v1');
        $this->cards->findOrCreate($otherMerchant, $this->customerId, 'Aninha', null);

        $here = $this->cards->findByMerchantAndPhone($this->merchantId, '11999990001');
        $there = $this->cards->findByMerchantAndPhone($otherMerchant, '11999990001');
        $this->assertSame('Ana Teste', $here['customer_name']);
        $this->assertSame('v1', $here['consent_version']);
        $this->assertNotNull($here['consent_at']);
        $this->assertSame('Aninha', $there['customer_name']);
        $this->assertNull($there['consent_at']);
    }

    public function testCartaoExistenteNaoTemNomeNemConsentimentoTrocados(): void {
        $first = $this->cards->findOrCreate($this->merchantId, $this->customerId, 'Ana Teste', 'v1');
        $again = $this->cards->findOrCreate($this->merchantId, $this->customerId, 'Outro Nome', 'v2');

        $this->assertSame($first, $again);
        $card = $this->cards->findByMerchantAndPhone($this->merchantId, '11999990001');
        $this->assertSame('Ana Teste', $card['customer_name']);
        $this->assertSame('v1', $card['consent_version']);
    }
}
