<?php

namespace App\Mail;

// envio de e-mail transacional (task 55). quem chama nao sabe qual provedor e usado (Mailer::fromEnv).
// devolve false quando o e-mail nao saiu; o motivo ja foi para o log (nunca o corpo: tem link com token).
interface Mailer {
    public function send(Message $message): bool;
}
