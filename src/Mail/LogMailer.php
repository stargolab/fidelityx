<?php

namespace App\Mail;

// "envia" gravando o e-mail inteiro, uma linha json por mensagem, em MAIL_LOG_FILE (MAIL_DRIVER=log).
// so para desenvolvimento e testes: o arquivo tem os links com token. nunca use em producao.
final class LogMailer implements Mailer {
    public function __construct(private readonly string $file) {
    }

    public function send(Message $message): bool {
        $line = json_encode([
            'at'      => date('c'),
            'to'      => $message->to,
            'subject' => $message->subject,
            'body'    => $message->body,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (@file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('[mail] nao deu pra gravar em MAIL_LOG_FILE: ' . $this->file);
            return false;
        }
        return true;
    }

    // mensagens gravadas no arquivo, da mais antiga pra mais nova (usado pelos testes)
    public static function read(string $file): array {
        $messages = [];
        foreach (@file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $messages[] = json_decode($line, true);
        }
        return $messages;
    }
}
