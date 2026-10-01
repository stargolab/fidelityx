<?php

namespace App\Controllers;

use App\Models\LoyaltyCardModel;
use App\Models\RewardModel;
use App\Support\Csrf;
use App\Support\View;
use App\Validators\PhoneValidator;

// area publica do cliente (sem login): consulta de saldo pelo telefone
class CustomerController {
    // limite simples por sessao pra dificultar varredura de telefones
    private const MAX_LOOKUPS = 5;
    private const WINDOW_SECONDS = 60;

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function renderBalance() {
        $cards = null;
        $phone = '';
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();

            if ($this->tooManyLookups()) {
                (new ErrorController())->handle(429);
                return;
            }

            $phone = PhoneValidator::sanitize($_POST['phone'] ?? '');

            if (!PhoneValidator::isValid($phone)) {
                $error = 'telefone_invalido';
            } else {
                $cards = (new LoyaltyCardModel($this->db))->listByPhone($phone);
                $rewardModel = new RewardModel($this->db);

                foreach ($cards as &$card) {
                    $card['rewards'] = $rewardModel->listByMerchant($card['merchant_id'], true);
                }
                unset($card);
            }
        }

        View::render('customer/balance', [
            'phone' => $phone,
            'cards' => $cards,
            'error' => $error,
            // mostra so o primeiro nome, pra nao expor dados de quem digitou o telefone errado
            'firstName' => $cards ? strtok($cards[0]['customer_name'], ' ') : null,
        ]);
    }

    private function tooManyLookups(): bool {
        $now = time();
        $recent = array_filter(
            $_SESSION['balance_lookups'] ?? [],
            fn($t) => $t > $now - self::WINDOW_SECONDS
        );
        $recent[] = $now;
        $_SESSION['balance_lookups'] = array_values($recent);

        return count($recent) > self::MAX_LOOKUPS;
    }
}
