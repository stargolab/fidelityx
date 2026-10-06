<?php

namespace Tests\Integration;

use App\Models\LoyaltyCardModel;
use App\Models\PointsLogModel;
use Tests\Support\DatabaseTestCase;

// task 4: estorno de lancamento de pontos
final class ReversalTest extends DatabaseTestCase {
    private LoyaltyCardModel $cards;
    private int $merchant;
    private int $card;

    protected function setUp(): void {
        parent::setUp();
        $this->cards = new LoyaltyCardModel($this->db);
        $this->merchant = $this->createMerchant();
        $this->card = $this->createCard($this->merchant, 'Bia', '11922220002');
    }

    private function balance(): array {
        return $this->db->query("SELECT current_points, total_accumulated FROM loyalty_cards WHERE id = {$this->card}")->fetch();
    }

    // task 52: a conferencia que o controller fazia com sql direto agora e do model
    public function testMovimentacaoPertenceSoAoProprioCartao(): void {
        $logId = $this->cards->addPoints($this->card, 50, 'Compra');
        $other = $this->createCard($this->merchant, 'Caio', '11933330003');

        $logs = new PointsLogModel($this->db);
        $this->assertTrue($logs->belongsToCard($logId, $this->card));
        $this->assertFalse($logs->belongsToCard($logId, $other));
        $this->assertFalse($logs->belongsToCard($logId + 999, $this->card));
    }

    public function testEstornoDevolveSaldoETotalENadaEApagado(): void {
        $this->cards->addPoints($this->card, 50, 'Compra');
        $errado = $this->cards->addPoints($this->card, 500, 'Compra errada');

        $this->assertSame('ok', $this->cards->reverse($errado, $this->merchant));

        $this->assertSame(['current_points' => 50, 'total_accumulated' => 50], array_map('intval', $this->balance()));
        // o lancamento errado continua no historico, e o estorno aponta pra ele
        $this->assertSame(3, (int)$this->scalar('SELECT COUNT(*) FROM points_log'));
        $estorno = $this->db->query("SELECT type, quantity, description, reverses_id FROM points_log WHERE type = 'reversal'")->fetch();
        $this->assertSame(500, (int)$estorno['quantity']);
        $this->assertSame($errado, (int)$estorno['reverses_id']);
        $this->assertSame('Estorno: Compra errada', $estorno['description']);
    }

    public function testNaoEstornaDuasVezes(): void {
        $id = $this->cards->addPoints($this->card, 30, 'Compra');

        $this->assertSame('ok', $this->cards->reverse($id, $this->merchant));
        $this->assertSame('already', $this->cards->reverse($id, $this->merchant));
        $this->assertSame(0, (int)$this->balance()['current_points']);
    }

    public function testSaldoNuncaFicaNegativo(): void {
        $reward = $this->createReward($this->merchant, 'Cafe', 20);
        $id = $this->cards->addPoints($this->card, 30, 'Compra');
        $this->cards->redeem($this->card, $reward); // saldo 10, menor que os 30 do lancamento

        $this->assertSame('insufficient', $this->cards->reverse($id, $this->merchant));
        $this->assertSame(10, (int)$this->balance()['current_points']);
        $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM points_log WHERE type = 'reversal'"));
    }

    public function testSoLancamentoRecente(): void {
        $id = $this->cards->addPoints($this->card, 30, 'Compra');
        $this->db->exec("UPDATE points_log SET created_at = NOW() - INTERVAL " . (LoyaltyCardModel::REVERSAL_WINDOW_HOURS + 1) . " HOUR WHERE id = $id");

        $this->assertSame('expired', $this->cards->reverse($id, $this->merchant));
    }

    public function testSoLancamentoDeGanhoDestaLoja(): void {
        $reward = $this->createReward($this->merchant, 'Cafe', 20);
        $ganho = $this->cards->addPoints($this->card, 30, 'Compra');
        $this->cards->redeem($this->card, $reward);
        $resgate = (int)$this->scalar("SELECT id FROM points_log WHERE type = 'redeem'");

        $outraLoja = $this->createMerchant('outra@teste.test');

        $this->assertSame('not_found', $this->cards->reverse($resgate, $this->merchant), 'resgate nao se estorna');
        $this->assertSame('not_found', $this->cards->reverse($ganho, $outraLoja), 'outra loja nao estorna');
        $this->assertSame('not_found', $this->cards->reverse(999999, $this->merchant));
    }

    public function testPontosEmitidosDescontamOEstorno(): void {
        $this->cards->addPoints($this->card, 50, 'Compra');
        $id = $this->cards->addPoints($this->card, 500, 'Compra errada');
        $this->cards->reverse($id, $this->merchant);

        $this->assertSame(50, (int)(new PointsLogModel($this->db))->statsByMerchant($this->merchant)['points_issued']);
    }

    public function testExclusaoDoClienteLimpaADescricaoDoEstornoTambem(): void {
        $id = $this->cards->addPoints($this->card, 30, 'Compra da Bia');
        $this->cards->reverse($id, $this->merchant);

        $this->cards->anonymize($this->card, $this->merchant);

        $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM points_log WHERE description LIKE '%Bia%'"));
    }
}
