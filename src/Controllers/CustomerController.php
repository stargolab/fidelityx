<?php

namespace App\Controllers;

use App\Models\LoyaltyCardModel;
use App\Models\RewardModel;
use App\Support\Csrf;
use App\Support\RateLimiter;
use App\Support\RewardProgress;
use App\Support\View;
use App\Validators\PhoneValidator;

// area publica do cliente (sem login): consulta de saldo pelo telefone
class CustomerController {
    // limite por ip guardado no banco, pra dificultar varredura de telefones.
    // fica no banco (e nao na sessao) pra nao zerar quando a pessoa apaga o cookie.
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

            if ($this->tooManyLookups(RateLimiter::clientIp())) {
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
                    $card['progress'] = RewardProgress::next((int)$card['current_points'], $card['rewards']);
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

    // conta a consulta atual e diz se o ip passou do limite da janela
    private function tooManyLookups(string $ip): bool {
        $limiter = new RateLimiter($this->db);
        if ($limiter->tooMany('balance_ip', $ip, self::MAX_LOOKUPS, self::WINDOW_SECONDS)) {
            return true;
        }

        $limiter->hit('balance_ip', $ip);
        return false;
    }
}
