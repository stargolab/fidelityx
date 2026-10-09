<?php

namespace App\Mail;

use App\Support\Env;

// escolhe o envio pelo MAIL_DRIVER (task 55):
// - vazio (padrao): NullMailer, nada sai e o log avisa. e o que vale em producao ate o provedor ser escolhido.
// - "log": LogMailer em MAIL_LOG_FILE (padrao storage/mail.log), pra desenvolvimento e testes.
// provedor de verdade (smtp/api) entra aqui como mais um driver.
final class MailerFactory {
    public static function fromEnv(): Mailer {
        return match (Env::get('MAIL_DRIVER')) {
            'log'   => new LogMailer(Env::get('MAIL_LOG_FILE', dirname(__DIR__, 2) . '/storage/mail.log')),
            default => new NullMailer(),
        };
    }
}
