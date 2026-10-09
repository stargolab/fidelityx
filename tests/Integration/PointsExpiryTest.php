<?php

namespace Tests\Integration;

use App\Models\LoyaltyCardModel;
use Tests\Support\DatabaseTestCase;

// task 31: saldo parado alem do prazo da loja vence e fica registrado no historico
final class PointsExpiryTest extends DatabaseTestCase {
    private LoyaltyCardModel $cards;
    private int $merchant;
    private int $card;

    protected function setUp(): void {
        parent::setUp();
        $this->cards = new LoyaltyCardModel($this->db);
        $this->merchant = $this->createMerchant(); // nasce com 12 meses de validade
        $this->card = $this->createCard($this->merchant, 'Bia', '11922220002');
    }

    // lanca pontos e empurra a ultima movimentacao do cartao pra $months meses atras
    private function idleCard(int $card, int $points, int $months): void {
        $this->cards->addPoints($card, $points, 'Compra');
        $this->db->exec("UPDATE loyalty_cards SET last_use_at = NOW() - INTERVAL $months MONTH WHERE id = $card");
    }

    private function balance(int $card): int {
        return (int)$this->scalar('SELECT current_points FROM loyalty_cards WHERE id = ?', [$card]);
    }

    public function testSaldoParadoAlemDoPrazoVenceEFicaNoHistorico(): void {
        $this->idleCard($this->card, 80, 13);
        $before = $this->db->query("SELECT last_use_at, total_accumulated FROM loyalty_cards WHERE id = {$this->card}")->fetch();

        $this->assertSame(['cards' => 1, 'points' => 80], $this->cards->countExpirable());
        $this->assertSame(['cards' => 1, 'points' => 80], $this->cards->expireIdle());

        $this->assertSame(0, $this->balance($this->card));
        $log = $this->db->query("SELECT quantity, description, reward_id, reverses_id FROM points_log WHERE type = 'expire'")->fetch();
        $this->assertSame(80, (int)$log['quantity']);
        $this->assertSame('Pontos vencidos: 12 meses sem movimentação', $log['description']);
        $this->assertNull($log['reward_id']);
        $this->assertNull($log['reverses_id']);

        // vencer nao e movimentacao do cliente: total acumulado e ultima movimentacao ficam como estavam
        $after = $this->db->query("SELECT last_use_at, total_accumulated FROM loyalty_cards WHERE id = {$this->card}")->fetch();
        $this->assertSame($before, $after);
    }

    public function testDentroDoPrazoNaoVence(): void {
        $this->idleCard($this->card, 80, 11);

        $this->assertSame(['cards' => 0, 'points' => 0], $this->cards->expireIdle());
        $this->assertSame(80, $this->balance($this->card));
        $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM points_log WHERE type = 'expire'"));
    }

    public function testRodarDeNovoNaoVenceEmDobro(): void {
        $this->idleCard($this->card, 80, 13);

        $this->cards->expireIdle();
        $this->assertSame(['cards' => 0, 'points' => 0], $this->cards->expireIdle());
        $this->assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM points_log WHERE type = 'expire'"));
    }

    public function testMovimentacaoNovaRenovaOPrazoDoSaldoInteiro(): void {
        $this->idleCard($this->card, 80, 13);
        $this->cards->addPoints($this->card, 5, 'Compra'); // cliente voltou antes da rotina rodar

        $this->assertSame(['cards' => 0, 'points' => 0], $this->cards->expireIdle());
        $this->assertSame(85, $this->balance($this->card));
    }

    public function testCadaLojaTemOSeuPrazo(): void {
        $lojaCurta = $this->createMerchant('curta@teste.test');
        $this->db->exec("UPDATE merchants SET points_expiry_months = 3 WHERE id = $lojaCurta");
        $cardCurta = $this->createCard($lojaCurta, 'Bia', '11922220002');

        // o mesmo cliente, 4 meses parado nas duas lojas: so vence na de 3 meses
        $this->idleCard($this->card, 50, 4);
        $this->idleCard($cardCurta, 30, 4);

        $this->assertSame(['cards' => 1, 'points' => 30], $this->cards->expireIdle());
        $this->assertSame(50, $this->balance($this->card));
        $this->assertSame(0, $this->balance($cardCurta));
        $this->assertSame(
            'Pontos vencidos: 3 meses sem movimentação',
            $this->scalar("SELECT description FROM points_log WHERE type = 'expire'")
        );
    }

    public function testLojaSemPrazoNaoVenceNunca(): void {
        $this->db->exec("UPDATE merchants SET points_expiry_months = NULL WHERE id = {$this->merchant}");
        $this->idleCard($this->card, 80, 60);

        $this->assertSame(['cards' => 0, 'points' => 0], $this->cards->expireIdle());
        $this->assertSame(80, $this->balance($this->card));
    }

    public function testLojaInativaFicaComOSaldoCongelado(): void {
        $this->db->exec("UPDATE merchants SET status = 'inactive' WHERE id = {$this->merchant}");
        $this->idleCard($this->card, 80, 13);

        $this->assertSame(['cards' => 0, 'points' => 0], $this->cards->expireIdle());
        $this->assertSame(80, $this->balance($this->card));
    }

    public function testCartaoSemSaldoOuAnonimizadoFicaDeFora(): void {
        // sem saldo: nada a vencer, e nao gera linha de 0 pontos no historico
        $this->db->exec("UPDATE loyalty_cards SET last_use_at = NOW() - INTERVAL 13 MONTH WHERE id = {$this->card}");

        $outro = $this->createCard($this->merchant, 'Caio', '11933330003');
        $this->idleCard($outro, 40, 13);
        $this->cards->anonymize($outro, $this->merchant);

        $this->assertSame(['cards' => 0, 'points' => 0], $this->cards->expireIdle());
        $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM points_log WHERE type = 'expire'"));
    }

    // a rotina confere a regra de novo com o cartao travado: chamar direto num cartao em dia nao vence nada
    public function testExpireDeUmCartaoEmDiaNaoFazNada(): void {
        $this->idleCard($this->card, 80, 2);

        $this->assertSame(0, $this->cards->expire($this->card));
        $this->assertSame(80, $this->balance($this->card));
    }

    public function testVenceVariosCartoesEmLotes(): void {
        $this->idleCard($this->card, 10, 13);
        foreach (['11933330003', '11944440004', '11955550005', '11966660006'] as $i => $phone) {
            $this->idleCard($this->createCard($this->merchant, "Cliente $i", $phone), 10, 13);
        }

        // lote de 2: precisa de mais de uma volta pra vencer os 5
        $this->assertSame(['cards' => 5, 'points' => 50], $this->cards->expireIdle(2));
        $this->assertSame(0, (int)$this->scalar('SELECT COALESCE(SUM(current_points), 0) FROM loyalty_cards'));
    }

    // depois de vencer, o cliente volta a acumular normalmente
    public function testClienteAcumulaDeNovoDepoisDoVencimento(): void {
        $this->idleCard($this->card, 80, 13);
        $this->cards->expireIdle();

        $this->cards->addPoints($this->card, 15, 'Compra');
        $this->assertSame(15, $this->balance($this->card));
        $this->assertSame(['cards' => 0, 'points' => 0], $this->cards->expireIdle());
    }
}
