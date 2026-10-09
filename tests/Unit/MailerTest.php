<?php

namespace Tests\Unit;

use App\Mail\LogMailer;
use App\Mail\MailerFactory;
use App\Mail\Message;
use App\Mail\NullMailer;
use PHPUnit\Framework\TestCase;

// task 55: envio de e-mail sem mandar e-mail de verdade nos testes
final class MailerTest extends TestCase {
    private array $envBackup;
    private string $log;
    private string|false $previousErrorLog;

    protected function setUp(): void {
        $this->envBackup = $_ENV;
        $this->log = sys_get_temp_dir() . '/fx-mailer-' . bin2hex(random_bytes(4)) . '.log';
        // o que o codigo manda pro error_log vem pra ca (e nao pra saida do teste)
        $this->previousErrorLog = ini_set('error_log', $this->log . '.err');
    }

    protected function tearDown(): void {
        $_ENV = $this->envBackup;
        ini_set('error_log', (string)$this->previousErrorLog);
        @unlink($this->log);
        @unlink($this->log . '.err');
    }

    private function message(): Message {
        return new Message('ana@teste.test', 'Redefinir senha', "Abra o link:\nhttps://x/?token=segredo");
    }

    public function testSemDriverNenhumEmailSaiEOLogNaoTemOConteudo(): void {
        unset($_ENV['MAIL_DRIVER']);
        $mailer = MailerFactory::fromEnv();

        $this->assertInstanceOf(NullMailer::class, $mailer);
        $this->assertFalse($mailer->send($this->message()));

        $errors = (string)file_get_contents($this->log . '.err');
        $this->assertStringContainsString('[mail] e-mail nao enviado', $errors);
        $this->assertStringContainsString('Redefinir senha', $errors);
        $this->assertStringNotContainsString('segredo', $errors);
        $this->assertStringNotContainsString('ana@teste.test', $errors);
    }

    public function testDriverDesconhecidoNaoEnvia(): void {
        $_ENV['MAIL_DRIVER'] = 'smtp';
        $this->assertInstanceOf(NullMailer::class, MailerFactory::fromEnv());
    }

    public function testDriverLogGravaUmaLinhaPorMensagem(): void {
        $_ENV['MAIL_DRIVER'] = 'log';
        $_ENV['MAIL_LOG_FILE'] = $this->log;
        $mailer = MailerFactory::fromEnv();

        $this->assertInstanceOf(LogMailer::class, $mailer);
        $this->assertTrue($mailer->send($this->message()));
        $this->assertTrue($mailer->send(new Message('bia@teste.test', 'Outro', 'Corpo')));

        $sent = LogMailer::read($this->log);
        $this->assertCount(2, $sent);
        $this->assertSame('ana@teste.test', $sent[0]['to']);
        $this->assertSame('Redefinir senha', $sent[0]['subject']);
        $this->assertSame("Abra o link:\nhttps://x/?token=segredo", $sent[0]['body']);
        $this->assertSame('bia@teste.test', $sent[1]['to']);
    }

    public function testArquivoQueNaoDaPraGravarDevolveFalseEAvisa(): void {
        // a "pasta" do arquivo e um arquivo comum: nao da pra criar nem gravar
        file_put_contents($this->log, '');
        $mailer = new LogMailer($this->log . '/mail.log');

        $this->assertFalse($mailer->send($this->message()));
        $this->assertStringContainsString('[mail] nao deu pra gravar', (string)file_get_contents($this->log . '.err'));
    }

    public function testArquivoSemMensagens(): void {
        $this->assertSame([], LogMailer::read($this->log . '-nao-existe'));
    }
}
