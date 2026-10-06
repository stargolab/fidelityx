<?php

namespace App\Controllers;
class ErrorController {
    // $errorId: codigo gravado no log junto com o erro (so a pagina 500 mostra)
    public function handle($code = 404, $errorId = null) {
        http_response_code($code);

        // botao de voltar de todas as paginas de erro ($backUrl e $backLabel sao usados nas views).
        // a sessao pode nem ter comecado (erro antes do session_start): ai vale como visitante.
        [$backUrl, $backLabel] = self::backLink(isset($_SESSION['merchant_id']));

        $viewPath = __DIR__ . "/../../views/errors/{$code}.php";

        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {

            require_once __DIR__ . '/../../views/errors/default.php';
        }
        return;
    }

    // para onde o botao de voltar leva: lojista logado volta pro painel; qualquer outra pessoa
    // (ex.: cliente na consulta publica de saldo) volta pra home publica, e nao pro login do lojista.
    public static function backLink(bool $loggedIn): array {
        return $loggedIn
            ? [url('merchant/dashboard'), 'Voltar ao Painel']
            : [url('home'), 'Voltar ao início'];
    }
}
