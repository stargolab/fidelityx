<?php

namespace App\Support;

class View {
    // renderiza uma view da pasta /views passando variaveis pra ela.
    // exemplo: View::render('merchant/dashboard', ['stats' => $stats]) -> dentro da view existe $stats
    public static function render(string $view, array $data = []): void {
        extract($data, EXTR_SKIP);
        require __DIR__ . '/../../views/' . $view . '.php';
    }
}
