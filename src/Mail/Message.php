<?php

namespace App\Mail;

// um e-mail transacional (task 55): so texto, um destinatario
final class Message {
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
    ) {
    }
}
