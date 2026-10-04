<?php

namespace Tests\Feature;

use Tests\Support\HttpTestCase;

// task 5: confirmacao do lancamento com novo saldo, quanto falta e Desfazer
final class LaunchConfirmationTest extends HttpTestCase {
    private const SCREEN = 'merchant/customer&phone=11922220002';

    private function setUpStore(int $points = 0): int {
        $merchant = $this->createMerchant('loja@teste.test');
        $this->createReward($merchant, 'Bolo', 100);
        $card = $this->createCard($merchant, 'Bia Souza', '11922220002');
        if ($points) {
            $this->db->exec("UPDATE loyalty_cards SET current_points = $points WHERE id = $card");
        }
        $this->loginAs('loja@teste.test');
        return $card;
    }

    private function launch(int $points): string {
        [, $location] = $this->post('merchant/customer', ['action' => 'score', 'phone' => '11922220002', 'points' => $points], true, self::SCREEN);
        return $location;
    }

    public function testConfirmacaoMostraPontosNovoSaldoEQuantoFalta(): void {
        $this->setUpStore(27);

        $location = $this->launch(30);
        $this->assertMatchesRegularExpression('/^merchant\/customer&phone=11922220002&lancamento=\d+$/', $location);

        $this->get($location);
        $this->assertMatchesRegularExpression('#<strong>\+30 pontos lançados</strong>\s*para Bia\.#', $this->lastBody);
        $this->assertStringContainsString('Saldo agora: <strong>57 pontos</strong>', $this->lastBody);
        $this->assertStringContainsString('Faltam 43 para Bolo', $this->lastBody);
        $this->assertStringContainsString('name="action" value="reverse"', $this->lastBody);
        $this->assertStringContainsString('data-undo-seconds="30"', $this->lastBody);
    }

    public function testDesfazerVoltaOSaldoEAConfirmacaoSome(): void {
        $this->setUpStore(27);
        $location = $this->launch(30);
        $logId = (int)substr($location, strrpos($location, '=') + 1);

        [, $back] = $this->post('merchant/customer', ['action' => 'reverse', 'phone' => '11922220002', 'log_id' => $logId], true, $location);
        $this->assertSame(self::SCREEN . '&success=lancamento_estornado', $back);
        $this->assertSame(27, (int)$this->scalar('SELECT current_points FROM loyalty_cards'));

        // abrir de novo o link da confirmacao nao oferece desfazer de novo
        $this->get($location);
        $this->assertStringNotContainsString('pontos lançados', $this->lastBody);
    }

    public function testConfirmacaoSoAparecePraLancamentoRecenteDesteCliente(): void {
        $card = $this->setUpStore();
        $location = $this->launch(10);
        $logId = (int)substr($location, strrpos($location, '=') + 1);

        // passou do tempo do aviso
        $this->db->exec("UPDATE points_log SET created_at = NOW() - INTERVAL 10 MINUTE WHERE id = $logId");
        $this->get($location);
        $this->assertStringNotContainsString('pontos lançados', $this->lastBody);

        // lancamento de outro cliente no link
        $other = $this->createCard((int)$this->scalar('SELECT merchant_id FROM loyalty_cards WHERE id = ?', [$card]), 'Caio', '11933330003');
        $this->launchFor('11933330003');
        $otherLog = (int)$this->scalar('SELECT MAX(id) FROM points_log WHERE card_id = ?', [$other]);
        $this->get(self::SCREEN . '&lancamento=' . $otherLog);
        $this->assertStringNotContainsString('pontos lançados', $this->lastBody);
    }

    private function launchFor(string $phone): void {
        $this->post('merchant/customer', ['action' => 'score', 'phone' => $phone, 'points' => 5], true, 'merchant/customer&phone=' . $phone);
    }

    public function testQuandoPagaTudoDizQueJaDaParaResgatar(): void {
        $this->setUpStore(90);
        $this->get($this->launch(20));
        $this->assertStringContainsString('Já dá para resgatar qualquer prêmio.', $this->lastBody);
    }
}
