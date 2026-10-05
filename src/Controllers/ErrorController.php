<?php

namespace App\Controllers;
class ErrorController {
    // $errorId: codigo gravado no log junto com o erro (so a pagina 500 mostra)
    public function handle($code = 404, $errorId = null) {
        http_response_code($code);

        $viewPath = __DIR__ . "/../../views/errors/{$code}.php";

        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {

            require_once __DIR__ . '/../../views/errors/default.php';
        }
        return;
    }
}
