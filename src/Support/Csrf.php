<?php

namespace App\Support;

use App\Controllers\ErrorController;

// protecao contra CSRF: cada sessao recebe um token aleatorio,
// todo form POST envia esse token num campo escondido e o servidor confere.
class Csrf {
    private const KEY = '_csrf';

    public static function token(): string {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    // token novo (no login): um token visto antes de entrar nao vale para a sessao logada
    public static function regenerate(): void {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }

    // campo pronto pra colocar dentro do <form>
    public static function field(): string {
        return '<input type="hidden" name="' . self::KEY . '" value="' . self::token() . '">';
    }

    // chamado no inicio de todo handle de POST. se o token nao bater, para com 403.
    public static function verify(): void {
        $sent = $_POST[self::KEY] ?? '';
        $expected = $_SESSION[self::KEY] ?? '';

        if (!is_string($sent) || $expected === '' || !hash_equals($expected, $sent)) {
            (new ErrorController())->handle(403);
            exit;
        }
    }
}
