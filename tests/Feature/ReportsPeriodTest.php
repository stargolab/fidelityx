<?php

namespace Tests\Feature;

use App\Models\LoyaltyCardModel;
use Tests\Support\HttpTestCase;

// task 59: relatorios por periodo e exportacao em csv, so com dados desta loja
final class ReportsPeriodTest extends HttpTestCase {
    private int $loja;
    private int $bia;

    protected function setUp(): void {
        parent::setUp();
        $this->loja = $this->createMerchant('loja@teste.test');
        $this->bia = $this->createCard($this->loja, 'Bia Souza', '11922220002');
        $cards = new LoyaltyCardModel($this->db);
        $reward = $this->createReward($this->loja, 'Cafe', 10);

        // mes passado: cadastro antigo, 100 pontos e um resgate
        $cards->addPoints($this->bia, 100, 'Compra antiga');
        $cards->redeem($this->bia, $reward);
        $this->db->exec("UPDATE points_log SET created_at = NOW() - INTERVAL 40 DAY");
        $this->db->exec("UPDATE loyalty_cards SET created_at = NOW() - INTERVAL 40 DAY");

        // hoje: cliente novo e 30 pontos
        $caio = $this->createCard($this->loja, 'Caio', '11933330003');
        $cards->addPoints($caio, 30, '=1+1');
    }

    public function testIndicadoresEHistoricoDoPeriodo(): void {
        $this->loginAs('loja@teste.test');

        $this->get('merchant/reports&periodo=hoje');
        $this->assertMatchesRegularExpression('#stat-value">1</div>\s*<div class="stat-label">Clientes novos#', $this->lastBody);
        $this->assertMatchesRegularExpression('#stat-value">30</div>\s*<div class="stat-label">Pontos emitidos#', $this->lastBody);
        $this->assertMatchesRegularExpression('#stat-value">0</div>\s*<div class="stat-label">Resgates#', $this->lastBody);
        // saldo em circulacao e o de agora: 90 da Bia + 30 do Caio
        $this->assertMatchesRegularExpression('#stat-value">120</div>\s*<div class="stat-label">Pontos em circula#', $this->lastBody);
        $this->assertStringNotContainsString('Compra antiga', $this->lastBody);
        $this->assertStringContainsString('<option value="hoje" selected>', $this->lastBody);
        $this->assertStringContainsString('merchant%2Fexport&amp;tipo=historico&amp;periodo=hoje', $this->lastBody);

        $this->get('merchant/reports');
        $this->assertMatchesRegularExpression('#stat-value">2</div>\s*<div class="stat-label">Clientes<#', $this->lastBody);
        $this->assertMatchesRegularExpression('#stat-value">130</div>\s*<div class="stat-label">Pontos emitidos#', $this->lastBody);
        $this->assertStringContainsString('Compra antiga', $this->lastBody);
    }

    public function testDatasInvalidasAvisamEMostramTudo(): void {
        $this->loginAs('loja@teste.test');

        $this->get('merchant/reports&periodo=intervalo&de=2026-09-30&ate=2026-09-01');
        $this->assertStringContainsString('Datas inválidas', $this->lastBody);
        $this->assertStringContainsString('Compra antiga', $this->lastBody);
    }

    public function testCsvDoHistoricoDoPeriodo(): void {
        // outra loja com movimentacao no mesmo dia nao entra
        $outra = $this->createMerchant('outra@teste.test');
        (new LoyaltyCardModel($this->db))->addPoints($this->createCard($outra, 'Dani', '11944440004'), 50, 'Outra loja');
        $this->loginAs('loja@teste.test');

        [$status] = $this->get('merchant/export&tipo=historico&periodo=hoje');
        $this->assertSame(200, $status);
        $this->assertSame('text/csv; charset=utf-8', $this->lastHeaders['content-type'][0]);
        $this->assertMatchesRegularExpression('/attachment; filename="historico-\d{4}-\d{2}-\d{2}\.csv"/', $this->lastHeaders['content-disposition'][0]);
        $this->assertSame('no-store', $this->lastHeaders['cache-control'][0]);

        $this->assertStringStartsWith("\xEF\xBB\xBFData;Cliente;Telefone;Tipo;Pontos;Descrição\r\n", $this->lastBody);
        $lines = explode("\r\n", trim($this->lastBody));
        $this->assertCount(2, $lines, 'cabecalho + o lancamento de hoje');
        $this->assertMatchesRegularExpression("#^\d{2}/\d{2}/\d{4} \d{2}:\d{2};Caio;\(11\) 93333-0003;Ganhou;30;'=1\+1$#", $lines[1]);
        $this->assertStringNotContainsString('Dani', $this->lastBody);
        $this->assertStringNotContainsString('Outra loja', $this->lastBody);
    }

    public function testCsvDoHistoricoCompletoComSinalEClienteExcluido(): void {
        $cards = new LoyaltyCardModel($this->db);
        $cards->anonymize($this->bia, $this->loja);
        $this->loginAs('loja@teste.test');

        $this->get('merchant/export&tipo=historico');
        $this->assertStringContainsString(";Cliente excluído;;Resgatou;-10;Resgate: Cafe\r\n", $this->lastBody);
        $this->assertStringContainsString(";Cliente excluído;;Ganhou;100;Cliente excluído\r\n", $this->lastBody);
        $this->assertStringNotContainsString('92222', $this->lastBody);
    }

    public function testCsvDosClientesComABusca(): void {
        $outra = $this->createMerchant('outra@teste.test');
        $this->createCard($outra, 'Beatriz de outra loja', '11922220002');
        $this->loginAs('loja@teste.test');

        $this->get('merchant/customers');
        $this->assertStringContainsString('merchant%2Fexport&amp;tipo=clientes', $this->lastBody);

        $this->get('merchant/export&tipo=clientes');
        $this->assertMatchesRegularExpression('/filename="clientes-\d{4}-\d{2}-\d{2}\.csv"/', $this->lastHeaders['content-disposition'][0]);
        $this->assertStringStartsWith("\xEF\xBB\xBFCliente;Telefone;Saldo;Total acumulado;Última visita\r\n", $this->lastBody);
        $this->assertStringContainsString('Bia Souza;(11) 92222-0002;90;100;', $this->lastBody);
        $this->assertStringContainsString('Caio;(11) 93333-0003;30;30;', $this->lastBody);
        $this->assertStringNotContainsString('outra loja', $this->lastBody);

        $this->get('merchant/export&tipo=clientes&q=bia');
        $this->assertStringContainsString('Bia Souza', $this->lastBody);
        $this->assertStringNotContainsString('Caio', $this->lastBody);
    }

    public function testExportacaoExigeLogin(): void {
        [$status, $location] = $this->get('merchant/export&tipo=clientes');
        $this->assertSame(302, $status);
        $this->assertSame('merchant/login&error=sessao_expirada', $location);
    }
}
