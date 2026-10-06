<?php

namespace Tests\Unit;

use App\Support\ErrorLog;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorLogTest extends TestCase {
    private string $dir;
    private string $previousLog;

    protected function setUp(): void {
        $this->dir = sys_get_temp_dir() . '/fx-log-' . bin2hex(random_bytes(4));
        $this->previousLog = (string)ini_get('error_log');
    }

    protected function tearDown(): void {
        ini_set('error_log', $this->previousLog);
        @unlink($this->dir . '/logs/app.log');
        @rmdir($this->dir . '/logs');
        @rmdir($this->dir);
    }

    public function testLinhaTemCodigoErroLocalERota(): void {
        $line = ErrorLog::format(
            $this->failWith('11999998888', 'senha-secreta'),
            'ab12cd34',
            ['REQUEST_METHOD' => 'POST'],
            ['url' => 'merchant/customer', 'phone' => '11999998888']
        );

        $this->assertStringStartsWith('[uncaught ab12cd34] RuntimeException: falha de teste em tests/Unit/ErrorLogTest.php:', $line);
        $this->assertStringContainsString('| POST merchant/customer |', $line);
        $this->assertStringContainsString('failWith()', $line, 'o caminho das chamadas entra no log');
        $this->assertStringNotContainsString("\n", $line, 'um erro por linha');
    }

    // a query string e os argumentos das funcoes podem ter telefone e senha
    public function testDadoPessoalDaRequisicaoNaoVaiProLog(): void {
        $line = ErrorLog::format(
            $this->failWith('11999998888', 'senha-secreta'),
            'ab12cd34',
            ['REQUEST_METHOD' => 'GET', 'QUERY_STRING' => 'url=merchant/customer&phone=11999998888'],
            ['url' => 'merchant/customer', 'phone' => '11999998888']
        );

        $this->assertStringNotContainsString('11999998888', $line);
        $this->assertStringNotContainsString('senha-secreta', $line);
    }

    public function testRotaForaDoFormatoNaoEGravada(): void {
        $e = new RuntimeException('x');

        $this->assertStringContainsString('| GET - |', ErrorLog::format($e, 'id', ['REQUEST_METHOD' => 'GET'], ['url' => "merchant/x\n[uncaught falso]"]));
        $this->assertStringContainsString('| GET - |', ErrorLog::format($e, 'id', ['REQUEST_METHOD' => 'GET'], []));
        $this->assertStringContainsString('| cli |', ErrorLog::format($e, 'id', [], []));
    }

    public function testMensagemComQuebraDeLinhaViraUmaLinha(): void {
        $line = ErrorLog::format(new RuntimeException("linha 1\nlinha 2"), 'id', [], []);

        $this->assertStringContainsString('RuntimeException: linha 1 linha 2 em', $line);
    }

    public function testLogVaiProArquivoConfigurado(): void {
        $file = $this->dir . '/logs/app.log';

        $this->assertTrue(ErrorLog::useFile($file), 'cria a pasta que faltar');
        error_log('[teste] gravou no arquivo');

        $this->assertStringContainsString('[teste] gravou no arquivo', (string)file_get_contents($file));
    }

    public function testSemArquivoConfiguradoFicaODestinoDoPhpIni(): void {
        $this->assertFalse(ErrorLog::useFile(''));
        $this->assertSame($this->previousLog, (string)ini_get('error_log'));
    }

    private function failWith(string $phone, string $password): RuntimeException {
        try {
            throw new RuntimeException('falha de teste');
        } catch (RuntimeException $e) {
            return $e;
        }
    }
}
