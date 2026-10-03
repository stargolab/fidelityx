<?php

namespace Tests\Feature;

use App\Models\LoyaltyCardModel;
use App\Support\Privacy;
use Tests\Support\HttpTestCase;

// task 11 (LGPD), regra em docs/adr/002-lgpd-dados-por-loja.md:
// dados do cliente por loja, consentimento registrado, exclusao pelo lojista e consulta publica por loja
final class LgpdTest extends HttpTestCase {
    // ---- uma loja nao descobre nada do cadastro de outra ------------------------------------

    public function testTelefoneDeOutraLojaSegueOMesmoCaminhoDeTelefoneNovo(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $this->createMerchant('b@teste.test');
        $this->createCard($lojaA, 'Carla Mendes', '11922220002');
        $this->loginAs('b@teste.test');

        // a busca manda os dois pro cadastro rapido
        [, $conhecido] = $this->get('merchant/dashboard&phone=11922220002');
        [, $novo] = $this->get('merchant/dashboard&phone=11933330003');
        $this->assertSame('merchant/customer-new&phone=11922220002', $conhecido);
        $this->assertSame('merchant/customer-new&phone=11933330003', $novo);

        // e a tela de cadastro e identica (tirando o proprio telefone): nenhuma pista de que um deles existe
        $this->get('merchant/customer-new&phone=11922220002');
        $telaConhecido = str_replace(['11922220002', '(11) 92222-0002'], 'TEL', $this->lastBody);
        $this->get('merchant/customer-new&phone=11933330003');
        $telaNovo = str_replace(['11933330003', '(11) 93333-0003'], 'TEL', $this->lastBody);

        $this->assertSame($telaNovo, $telaConhecido);
        $this->assertStringNotContainsString('Carla', $telaConhecido);
    }

    public function testCadaLojaGuardaONomeQueOClienteDeuAEla(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $lojaB = $this->createMerchant('b@teste.test');
        $this->createCard($lojaA, 'Carla Mendes', '11922220002');
        $this->loginAs('b@teste.test');

        [, $location] = $this->post(
            'merchant/customer-new',
            ['phone' => '11922220002', 'name' => 'Carlinha', 'consent' => '1'],
            true,
            'merchant/customer-new&phone=11922220002'
        );
        $this->assertSame('merchant/customer&phone=11922220002&success=cliente_cadastrado', $location);

        // a loja B ve so o que ela cadastrou; o nome da loja A nao vaza em nenhuma tela dela
        foreach (['merchant/customer&phone=11922220002', 'merchant/customers', 'merchant/customers&q=Carla', 'merchant/statement&phone=11922220002'] as $route) {
            $this->get($route);
            $this->assertStringNotContainsString('Mendes', $this->lastBody, $route);
        }
        $this->get('merchant/customer&phone=11922220002');
        $this->assertStringContainsString('Carlinha', $this->lastBody);

        // o telefone continua um cliente so; cada cartao com o seu nome
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM customers'));
        $this->assertSame('Carla Mendes', $this->scalar('SELECT customer_name FROM loyalty_cards WHERE merchant_id = ?', [$lojaA]));
        $this->assertSame('Carlinha', $this->scalar('SELECT customer_name FROM loyalty_cards WHERE merchant_id = ?', [$lojaB]));
    }

    public function testBuscaPorNomeNaoAchaNomeDadoEmOutraLoja(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $lojaB = $this->createMerchant('b@teste.test');
        $this->createCard($lojaA, 'Carla Mendes', '11922220002');
        $this->createCard($lojaB, 'Carlinha', '11922220002');
        $this->loginAs('b@teste.test');

        $this->get('merchant/customers&q=Mendes');
        $this->assertStringContainsString('Nenhum cliente encontrado', $this->lastBody);
    }

    public function testCadastroRapidoDeClienteDaLojaNaoSobrescreveONome(): void {
        $loja = $this->createMerchant('a@teste.test');
        $this->createCard($loja, 'Carla Mendes', '11922220002');
        $this->loginAs('a@teste.test');

        // GET manda pra tela do cliente; POST direto (ex.: enviado duas vezes) nao troca nada
        [, $location] = $this->get('merchant/customer-new&phone=11922220002');
        $this->assertSame('merchant/customer&phone=11922220002', $location);
        [, $location] = $this->post('merchant/customer-new', ['phone' => '11922220002', 'name' => 'Outro', 'consent' => '1'], true, 'merchant/rewards');
        $this->assertSame('merchant/customer&phone=11922220002', $location);
        $this->assertSame('Carla Mendes', $this->scalar('SELECT customer_name FROM loyalty_cards'));
    }

    // ---- consentimento -------------------------------------------------------------------------

    public function testCadastroRapidoGravaDataEVersaoDoConsentimento(): void {
        $this->createMerchant('a@teste.test');
        $this->loginAs('a@teste.test');

        $this->get('merchant/customer-new&phone=11922220002');
        $this->assertStringContainsString('url=privacy', $this->lastBody, 'o checkbox leva a politica de privacidade');

        $this->post('merchant/customer-new', ['phone' => '11922220002', 'name' => 'Bia', 'consent' => '1'], true, 'merchant/customer-new&phone=11922220002');

        $this->assertSame(Privacy::VERSION, $this->scalar('SELECT consent_version FROM loyalty_cards'));
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM loyalty_cards WHERE consent_at >= NOW() - INTERVAL 1 MINUTE'));
    }

    public function testCadastroAntigoPedeERegistraOConsentimento(): void {
        $loja = $this->createMerchant('a@teste.test');
        $this->createCard($loja, 'Bia', '11922220002', false);
        $this->loginAs('a@teste.test');
        $screen = 'merchant/customer&phone=11922220002';

        $this->get($screen);
        $this->assertStringContainsString('Consentimento não registrado', $this->lastBody);

        // sem marcar o checkbox nao grava
        [, $location] = $this->post('merchant/customer', ['action' => 'consent', 'phone' => '11922220002'], true, $screen);
        $this->assertSame($screen . '&error=consentimento_obrigatorio', $location);
        $this->assertNull($this->scalar('SELECT consent_at FROM loyalty_cards'));

        [, $location] = $this->post('merchant/customer', ['action' => 'consent', 'phone' => '11922220002', 'consent' => '1'], true, $screen);
        $this->assertSame($screen . '&success=consentimento_registrado', $location);
        $this->assertSame(Privacy::VERSION, $this->scalar('SELECT consent_version FROM loyalty_cards'));

        $this->get($screen);
        $this->assertStringNotContainsString('Consentimento não registrado', $this->lastBody);
    }

    // ---- exclusao a pedido do cliente --------------------------------------------------------

    public function testExcluirExigeConfirmacaoECsrf(): void {
        $loja = $this->createMerchant('a@teste.test');
        $this->createCard($loja, 'Bia', '11922220002');
        $this->loginAs('a@teste.test');
        $screen = 'merchant/customer&phone=11922220002';

        [, $location] = $this->post('merchant/customer', ['action' => 'anonymize', 'phone' => '11922220002'], true, $screen);
        $this->assertSame($screen . '&error=confirmacao_obrigatoria', $location);

        [$status] = $this->post('merchant/customer', ['action' => 'anonymize', 'phone' => '11922220002', 'confirm' => '1'], false);
        $this->assertSame(403, $status);

        $this->assertNull($this->scalar('SELECT anonymized_at FROM loyalty_cards'));
    }

    public function testExcluirApagaODadoPessoalEMantemOHistoricoSemIdentificacao(): void {
        $loja = $this->createMerchant('a@teste.test');
        $reward = $this->createReward($loja, 'Cafe', 10);
        $card = $this->createCard($loja, 'Bia Souza', '11922220002');
        $cards = new LoyaltyCardModel($this->db);
        $cards->addPoints($card, 30, 'Compra da Bia Souza');
        $cards->redeem($card, $reward);
        $this->loginAs('a@teste.test');

        [, $location] = $this->post(
            'merchant/customer',
            ['action' => 'anonymize', 'phone' => '11922220002', 'confirm' => '1'],
            true,
            'merchant/customer&phone=11922220002'
        );
        $this->assertSame('merchant/dashboard&success=cliente_excluido', $location);

        // dado pessoal sumiu: nome, telefone (cliente so desta loja), consentimento, saldo e o texto livre
        $row = $this->db->query('SELECT * FROM loyalty_cards')->fetch();
        $this->assertNull($row['customer_name']);
        $this->assertNull($row['customer_id']);
        $this->assertNull($row['consent_at']);
        $this->assertSame(0, (int)$row['current_points']);
        $this->assertNotNull($row['anonymized_at']);
        $this->assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM customers'));
        $this->assertSame(0, (int)$this->scalar("SELECT COUNT(*) FROM points_log WHERE description LIKE '%Bia%'"));

        // telas: o cliente nao e mais encontrado
        [, $location] = $this->get('merchant/customer&phone=11922220002');
        $this->assertSame('merchant/dashboard&error=cliente_nao_encontrado', $location);
        $this->get('merchant/customers');
        $this->assertStringNotContainsString('Bia', $this->lastBody);

        // relatorios: movimentacoes continuam, sem identificacao, e os totais de pontos nao mudam
        $this->get('merchant/reports');
        $this->assertStringContainsString('Cliente excluído', $this->lastBody);
        $this->assertStringContainsString('Resgate: Cafe', $this->lastBody);
        $this->assertStringNotContainsString('Bia', $this->lastBody);
        $this->assertMatchesRegularExpression('#stat-value">30</div>\s*<div class="stat-label">Pontos emitidos#', $this->lastBody);
        $this->assertMatchesRegularExpression('#stat-value">0</div>\s*<div class="stat-label">Clientes#', $this->lastBody);
    }

    public function testExcluirEmUmaLojaNaoMexeNoCartaoDaOutra(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $lojaB = $this->createMerchant('b@teste.test');
        $this->createCard($lojaA, 'Bia', '11922220002');
        $cardB = $this->createCard($lojaB, 'Beatriz', '11922220002');
        $this->db->exec("UPDATE loyalty_cards SET current_points = 40 WHERE id = $cardB");
        $this->loginAs('a@teste.test');

        $this->post('merchant/customer', ['action' => 'anonymize', 'phone' => '11922220002', 'confirm' => '1'], true, 'merchant/customer&phone=11922220002');

        // o telefone fica (ainda e cliente da loja B), e o cartao de B esta intacto
        $this->assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM customers'));
        $b = (new LoyaltyCardModel($this->db))->findByMerchantAndPhone($lojaB, '11922220002');
        $this->assertSame('Beatriz', $b['customer_name']);
        $this->assertSame(40, (int)$b['current_points']);

        // e a loja A pode cadastrar o mesmo telefone de novo, do zero
        [, $location] = $this->get('merchant/dashboard&phone=11922220002');
        $this->assertSame('merchant/customer-new&phone=11922220002', $location);
    }

    public function testLojaNaoExcluiClienteDeOutra(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $this->createMerchant('b@teste.test');
        $this->createCard($lojaA, 'Bia', '11922220002');
        $this->loginAs('b@teste.test');

        [, $location] = $this->post('merchant/customer', ['action' => 'anonymize', 'phone' => '11922220002', 'confirm' => '1'], true, 'merchant/rewards');
        $this->assertSame('merchant/dashboard&error=cliente_nao_encontrado', $location);
        $this->assertNull($this->scalar('SELECT anonymized_at FROM loyalty_cards'));
    }

    // ---- consulta publica por loja ------------------------------------------------------------

    private function consult(string $code, string $phone): void {
        $this->post('customer/balance', ['phone' => $phone, 'loja' => $code], true, 'customer/balance&loja=' . $code);
    }

    public function testConsultaMostraSoALojaDoCodigo(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $lojaB = $this->createMerchant('b@teste.test');
        $this->db->exec("UPDATE merchants SET store_name = 'Padaria A' WHERE id = $lojaA");
        $this->db->exec("UPDATE merchants SET store_name = 'Farmacia B' WHERE id = $lojaB");
        $this->createCard($lojaA, 'Bia Souza', '11922220002');
        $this->createCard($lojaB, 'Beatriz Lima', '11922220002');

        $this->consult($this->publicCode($lojaA), '11922220002');
        $this->assertStringContainsString('Padaria A', $this->lastBody);
        $this->assertStringContainsString('<strong>Bia</strong>', $this->lastBody);
        $this->assertStringNotContainsString('Farmacia B', $this->lastBody, 'nao revela as outras lojas do telefone');
        $this->assertStringNotContainsString('Beatriz', $this->lastBody);
        $this->assertStringNotContainsString('Souza', $this->lastBody, 'so o primeiro nome');
    }

    public function testConsultaSemCartaoNaLojaNaoDizSeOTelefoneExisteEmOutra(): void {
        $lojaA = $this->createMerchant('a@teste.test');
        $lojaB = $this->createMerchant('b@teste.test');
        $this->createCard($lojaB, 'Beatriz', '11922220002');
        $code = $this->publicCode($lojaA);

        $this->consult($code, '11922220002');
        $existeEmOutra = str_replace(['11922220002', '(11) 92222-0002'], 'TEL', $this->lastBody);
        $this->consult($code, '11933330003');
        $naoExiste = str_replace(['11933330003', '(11) 93333-0003'], 'TEL', $this->lastBody);

        $this->assertStringContainsString('Nenhum cartão fidelidade nesta loja', $naoExiste);
        $this->assertSame($naoExiste, $existeEmOutra);
    }

    public function testSemCodigoOuComCodigoInvalidoPedeOCodigoDaLoja(): void {
        $inativa = $this->createMerchant('inativa@teste.test');
        $this->db->exec("UPDATE merchants SET status = 'inactive' WHERE id = $inativa");

        $this->get('customer/balance');
        $this->assertStringContainsString('Código da loja', $this->lastBody);
        $this->assertStringNotContainsString('name="phone"', $this->lastBody);
        $this->assertStringNotContainsString('Código de loja não encontrado', $this->lastBody);

        foreach (['ZZZZZZZZ', $this->publicCode($inativa)] as $code) {
            $this->get('customer/balance&loja=' . $code);
            $this->assertStringContainsString('Código de loja não encontrado', $this->lastBody, $code);
            $this->assertStringNotContainsString('name="phone"', $this->lastBody);
        }
    }

    public function testCodigoDigitadoComMinusculaEEspacoFunciona(): void {
        $loja = $this->createMerchant('a@teste.test');
        $code = $this->publicCode($loja);

        $this->get('customer/balance&loja=' . urlencode(' ' . strtolower($code) . ' '));
        $this->assertStringContainsString('Loja Teste', $this->lastBody);
        $this->assertStringContainsString('name="phone"', $this->lastBody);
    }

    public function testClienteExcluidoNaoApareceNaConsulta(): void {
        $loja = $this->createMerchant('a@teste.test');
        $card = $this->createCard($loja, 'Bia', '11922220002');
        (new LoyaltyCardModel($this->db))->anonymize($card, $loja);

        $this->consult($this->publicCode($loja), '11922220002');
        $this->assertStringContainsString('Nenhum cartão fidelidade nesta loja', $this->lastBody);
    }

    // ---- cartaz, cadastro da loja e politica --------------------------------------------------

    public function testCartazLevaAConsultaDaLojaEMostraOCodigo(): void {
        $loja = $this->createMerchant('a@teste.test');
        $code = $this->publicCode($loja);
        $this->loginAs('a@teste.test');

        $this->get('merchant/poster');
        $this->assertMatchesRegularExpression('#index\.php\?url=customer%2Fbalance&amp;loja=' . $code . '#', $this->lastBody);
        $this->assertStringContainsString('<strong>' . $code . '</strong>', $this->lastBody);
    }

    public function testLojaNovaGanhaCodigoPublico(): void {
        $this->post('merchant/register', [
            'owner_name' => 'Dona Ana', 'shop_name' => 'Padaria da Ana', 'address' => 'Rua 1',
            'state' => 'SP', 'city' => 'Sao Paulo', 'email' => 'padaria@teste.test',
            'phone' => '11988887777', 'category' => 'alimentacao', 'document' => '52998224725',
            'password' => 'teste123', 'password_confirm' => 'teste123',
        ]);

        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{8}$/', (string)$this->scalar('SELECT public_code FROM merchants'));
    }

    public function testPoliticaDePrivacidadePublicaComVersao(): void {
        [$status] = $this->get('privacy');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Política de privacidade', $this->lastBody);
        $this->assertStringContainsString('Versão ' . Privacy::VERSION, $this->lastBody);
    }
}
