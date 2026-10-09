<?php

namespace App\Mail;

// sem provedor configurado (MAIL_DRIVER vazio, o padrao): nada sai e o log registra que nao saiu.
// o log leva so o assunto e um pedaco do hash do destinatario: o corpo tem link com token e o e-mail e dado pessoal.
final class NullMailer implements Mailer {
    public function send(Message $message): bool {
        error_log(sprintf(
            '[mail] e-mail nao enviado (MAIL_DRIVER sem provedor): "%s" para %s',
            $message->subject,
            substr(hash('sha256', mb_strtolower(trim($message->to))), 0, 12)
        ));
        return false;
    }
}
