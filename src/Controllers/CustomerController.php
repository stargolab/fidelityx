<?php

namespace App\Controllers;

use App\Models\LoyaltyCardModel;
use App\Models\MerchantModel;
use App\Models\RewardModel;
use App\Support\Csrf;
use App\Support\PublicCode;
use App\Support\RateLimiter;
use App\Support\RewardProgress;
use App\Support\View;
use App\Validators\PhoneValidator;

// area publica do cliente (sem login): consulta de saldo pelo telefone, UMA loja por vez.
// a loja vem do codigo publico (?loja=, impresso no cartaz e embutido no QR). assim quem sabe o telefone
// de alguem nao descobre em quais lojas a pessoa compra (LGPD, docs/adr/002).
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
        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($isPost) {
            Csrf::verify();
        }

        $rawCode = $isPost ? ($_POST['loja'] ?? '') : ($_GET['loja'] ?? '');
        $code = PublicCode::normalize($rawCode);
        $store = $code ? (new MerchantModel($this->db))->findActiveByPublicCode($code) : false;

        // sem loja valida: pede o codigo da loja (nada de listar lojas)
        if (!$store) {
            View::render('customer/balance', [
                'store' => null,
                'error' => trim((string)$rawCode) !== '' ? 'loja_invalida' : null,
            ]);
            return;
        }

        $data = ['store' => $store, 'phone' => '', 'card' => null, 'error' => null];

        if ($isPost) {
            if ($this->tooManyLookups(RateLimiter::clientIp())) {
                (new ErrorController())->handle(429);
                return;
            }

            $phone = PhoneValidator::sanitize($_POST['phone'] ?? '');
            $data['phone'] = $phone;

            if (!PhoneValidator::isValid($phone)) {
                $data['error'] = 'telefone_invalido';
            } else {
                $card = (new LoyaltyCardModel($this->db))->findByMerchantAndPhone((int)$store['id'], $phone);
                $data['card'] = $card ?: false;

                if ($card) {
                    $rewards = (new RewardModel($this->db))->listByMerchant((int)$store['id'], true);
                    $data['rewards'] = $rewards;
                    $data['progress'] = RewardProgress::next((int)$card['current_points'], $rewards);
                    // so o primeiro nome, pra nao expor dados de quem digitou o telefone errado
                    $data['firstName'] = strtok((string)$card['customer_name'], ' ');
                }
            }
        }

        View::render('customer/balance', $data);
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
