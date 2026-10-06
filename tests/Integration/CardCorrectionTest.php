<?php

namespace Tests\Integration;

use App\Models\LoyaltyCardModel;
use Tests\Support\DatabaseTestCase;

// task 43: corrigir o nome e mover o cartao para um telefone novo, so nesta loja
final class CardCorrectionTest extends DatabaseTestCase {
    private LoyaltyCardModel $cards;
    private int $loja;
    private int $outra;

    protected function setUp(): void {
        parent::setUp();
        $this->cards = new LoyaltyCardModel($this->db);
        $this->loja = $this->createMerchant('loja@teste.test');
        $this->outra = $this->createMerchant('outra@teste.test');
    }

    private function phoneOf(int $cardId): ?string {
        $phone = $this->scalar(
            'SELECT c.phone FROM loyalty_cards lc LEFT JOIN customers c ON c.id = lc.customer_id WHERE lc.id = :id',
            [':id' => $cardId]
        );
        return $phone === false ? null : $phone;
    }

    public function testCorrigeONomeSoNestaLoja(): void {
        $aqui = $this->createCard($this->loja, 'Bai', '11922220002');
        $la = $this->createCard($this->outra, 'Beatriz', '11922220002');

        $this->assertTrue($this->cards->rename($aqui, $this->loja, 'Bia'));

        $this->assertSame('Bia', $this->scalar('SELECT customer_name FROM loyalty_cards WHERE id = ?', [$aqui]));
        $this->assertSame('Beatriz', $this->scalar('SELECT customer_name FROM loyalty_cards WHERE id = ?', [$la]));
        $this->assertFalse($this->cards->rename($aqui, $this->outra, 'Invadido'), 'outra loja nao mexe');
        $this->assertSame('Bia', $this->scalar('SELECT customer_name FROM loyalty_cards WHERE id = ?', [$aqui]));
    }

    public function testTrocaOTelefoneComSaldoEHistorico(): void {
        $card = $this->createCard($this->loja, 'Bia', '11922220002');
        $this->cards->addPoints($card, 40, 'Compra');

        $this->assertSame('ok', $this->cards->changePhone($card, $this->loja, '11955550005'));

        $this->assertSame('11955550005', $this->phoneOf($card));
        $moved = $this->cards->findByMerchantAndPhone($this->loja, '11955550005');
        $this->assertSame($card, (int)$moved['id']);
        $this->assertSame(40, (int)$moved['current_points']);
        $this->assertSame('Bia', $moved['customer_name']);
        $this->assertNotNull($moved['consent_at'], 'o consentimento vem junto');
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM points_log WHERE card_id = ?', [$card]));
        $this->assertFalse($this->cards->findByMerchantAndPhone($this->loja, '11922220002'));
        // numero antigo sem nenhum cartao some da plataforma
        $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM customers WHERE phone = '11922220002'"));
    }

    public function testCartaoDoNumeroAntigoEmOutraLojaNaoMuda(): void {
        $aqui = $this->createCard($this->loja, 'Bia', '11922220002');
        $la = $this->createCard($this->outra, 'Beatriz', '11922220002');

        $this->assertSame('ok', $this->cards->changePhone($aqui, $this->loja, '11955550005'));

        $this->assertSame('11922220002', $this->phoneOf($la));
        $this->assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM customers WHERE phone = '11922220002'"));
    }

    // o numero novo ja existe na plataforma (cliente de outra loja): reaproveita o telefone, sem misturar cartoes
    public function testTelefoneNovoQueEClienteDeOutraLoja(): void {
        $aqui = $this->createCard($this->loja, 'Bia', '11922220002');
        $la = $this->createCard($this->outra, 'Caio', '11955550005');

        $this->assertSame('ok', $this->cards->changePhone($aqui, $this->loja, '11955550005'));

        $this->assertSame(1, (int)$this->scalar("SELECT COUNT(*) FROM customers WHERE phone = '11955550005'"));
        $this->assertSame('Bia', $this->cards->findByMerchantAndPhone($this->loja, '11955550005')['customer_name']);
        $this->assertSame('Caio', $this->cards->findByMerchantAndPhone($this->outra, '11955550005')['customer_name']);
        $this->assertSame((int)$la, (int)$this->cards->findByMerchantAndPhone($this->outra, '11955550005')['id']);
    }

    public function testTelefoneQueJaTemCartaoNestaLojaERecusado(): void {
        $bia = $this->createCard($this->loja, 'Bia', '11922220002');
        $this->createCard($this->loja, 'Caio', '11955550005');

        $this->assertSame('taken', $this->cards->changePhone($bia, $this->loja, '11955550005'));

        $this->assertSame('11922220002', $this->phoneOf($bia));
        $this->assertFalse($this->db->inTransaction());
    }

    public function testCartaoDeOutraLojaOuAnonimizadoNaoTroca(): void {
        $card = $this->createCard($this->loja, 'Bia', '11922220002');
        $this->assertSame('not_found', $this->cards->changePhone($card, $this->outra, '11955550005'));

        $this->cards->anonymize($card, $this->loja);
        $this->assertSame('not_found', $this->cards->changePhone($card, $this->loja, '11955550005'));
        $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM customers WHERE phone = '11955550005'"));
    }
}
