<?php

namespace Tests\Feature;

use App\Models\LoyaltyCardModel;
use Tests\Support\HttpTestCase;

// task 31: a data de vencimento do saldo aparece na consulta publica e na tela do cliente no painel
final class PointsExpiryScreenTest extends HttpTestCase {
    private const PHONE = '11922220002';

    private function setUpCard(int $points, string $email = 'loja@teste.test'): array {
        $merchant = $this->createMerchant($email);
        $card = $this->createCard($merchant, 'Bia Souza', self::PHONE);
        if ($points > 0) {
            (new LoyaltyCardModel($this->db))->addPoints($card, $points, 'Compra');
        }
        return [$merchant, $card];
    }

    // a data esperada sai da mesma conta do banco: ultima movimentacao + prazo da loja
    private function expectedDate(int $card, int $months): string {
        return (string)$this->scalar(
            "SELECT DATE_FORMAT(last_use_at + INTERVAL $months MONTH, '%d/%m/%Y') FROM loyalty_cards WHERE id = ?",
            [$card]
        );
    }

    private function publicBalance(int $merchant): void {
        $code = $this->publicCode($merchant);
        $this->post('customer/balance', ['phone' => self::PHONE, 'loja' => $code], true, 'customer/balance&loja=' . $code);
    }

    public function testConsultaPublicaMostraAteQuandoOsPontosValem(): void {
        [$merchant, $card] = $this->setUpCard(30);

        $this->publicBalance($merchant);
        $this->assertStringContainsString(
            'Pontos válidos até <strong>' . $this->expectedDate($card, 12) . '</strong>',
            $this->lastBody
        );
    }

    public function testTelaDoLojistaMostraAMesmaData(): void {
        [, $card] = $this->setUpCard(30);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customer&phone=' . self::PHONE);
        $this->assertStringContainsString(
            'Pontos válidos até <strong>' . $this->expectedDate($card, 12) . '</strong>',
            $this->lastBody
        );
    }

    public function testDataSegueOPrazoDaLoja(): void {
        [$merchant, $card] = $this->setUpCard(30);
        $this->db->exec("UPDATE merchants SET points_expiry_months = 3 WHERE id = $merchant");

        $this->publicBalance($merchant);
        $this->assertStringContainsString('<strong>' . $this->expectedDate($card, 3) . '</strong>', $this->lastBody);
    }

    public function testLojaEmQueOsPontosNaoVencemNaoMostraData(): void {
        [$merchant] = $this->setUpCard(30);
        $this->db->exec("UPDATE merchants SET points_expiry_months = NULL WHERE id = $merchant");

        $this->publicBalance($merchant);
        $this->assertStringContainsString('30 pts', $this->lastBody);
        $this->assertStringNotContainsString('points-expiry', $this->lastBody);
    }

    public function testSemSaldoNaoMostraData(): void {
        [$merchant] = $this->setUpCard(0);

        $this->publicBalance($merchant);
        $this->assertStringNotContainsString('points-expiry', $this->lastBody);

        $this->loginAs('loja@teste.test');
        $this->get('merchant/customer&phone=' . self::PHONE);
        $this->assertStringNotContainsString('points-expiry', $this->lastBody);
    }

    // prazo ja passou e a rotina ainda nao rodou: a tela avisa que venceu em vez de prometer uma data no passado
    public function testSaldoQueJaPassouDoPrazoApareceComoVencido(): void {
        [$merchant, $card] = $this->setUpCard(30);
        $this->db->exec("UPDATE loyalty_cards SET last_use_at = NOW() - INTERVAL 13 MONTH WHERE id = $card");

        $this->publicBalance($merchant);
        $this->assertStringContainsString(
            'Pontos vencidos em <strong>' . $this->expectedDate($card, 12) . '</strong>',
            $this->lastBody
        );
        $this->assertStringNotContainsString('Pontos válidos até', $this->lastBody);
    }

    // depois que a rotina vence o saldo, a data some (nao ha mais o que vencer) e o extrato mostra o vencimento
    public function testDepoisDeVencerADataSomeEOExtratoMostraOVencimento(): void {
        [, $card] = $this->setUpCard(30);
        $this->db->exec("UPDATE loyalty_cards SET last_use_at = NOW() - INTERVAL 13 MONTH WHERE id = $card");
        (new LoyaltyCardModel($this->db))->expireIdle();
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customer&phone=' . self::PHONE);
        $this->assertStringContainsString('balance-value">0<', $this->lastBody);
        $this->assertStringNotContainsString('points-expiry', $this->lastBody);

        $this->get('merchant/statement&phone=' . self::PHONE);
        $this->assertStringContainsString('badge-expire', $this->lastBody);
        $this->assertStringContainsString('Venceu', $this->lastBody);
        $this->assertStringContainsString('Pontos vencidos: 12 meses sem movimentação', $this->lastBody);
    }

    // LGPD: a data e so desta loja; o prazo de outra loja em que o cliente tambem compra nao aparece aqui
    public function testDataDeUmaLojaNaoVazaParaOutra(): void {
        [$lojaA, $cardA] = $this->setUpCard(30, 'a@teste.test');
        [$lojaB, $cardB] = $this->setUpCard(50, 'b@teste.test');
        $this->db->exec("UPDATE merchants SET points_expiry_months = 3 WHERE id = $lojaB");
        $this->db->exec("UPDATE loyalty_cards SET last_use_at = NOW() - INTERVAL 1 MONTH WHERE id = $cardB");

        $this->publicBalance($lojaA);
        $this->assertStringContainsString('<strong>' . $this->expectedDate($cardA, 12) . '</strong>', $this->lastBody);
        $this->assertStringNotContainsString($this->expectedDate($cardB, 3), $this->lastBody);
    }
}
