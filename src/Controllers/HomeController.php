<?php

namespace App\Controllers;

use App\Support\Privacy;
use App\Support\View;

// pagina inicial publica (rota "home"): apresenta o produto e leva ao cadastro e a consulta de saldo
class HomeController {
    public function render() {
        // lojista ja logado quer o painel, nao a apresentacao
        if (isset($_SESSION['merchant_id'])) {
            redirect('merchant/dashboard');
        }

        View::render('home');
    }

    // politica de privacidade (publica). a versao vai gravada em cada consentimento.
    public function renderPrivacy() {
        View::render('privacy', ['version' => Privacy::VERSION]);
    }
}
