<?php

namespace App\Support;

// versao do texto de privacidade (views/privacy.php).
// cada consentimento grava esta versao no cartao (loyalty_cards.consent_version):
// mudou o texto de forma relevante -> troque a versao, e da pra saber quem aceitou qual.
final class Privacy {
    public const VERSION = '2026-10-03';
}
