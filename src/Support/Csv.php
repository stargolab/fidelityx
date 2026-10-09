<?php

namespace App\Support;

// planilha exportada (task 59): ';' e utf-8 com BOM, o formato que o Excel em portugues abre direto
// (com ',' ele junta tudo numa coluna so; sem o BOM os acentos saem quebrados).
final class Csv {
    public const BOM = "\xEF\xBB\xBF";
    private const SEPARATOR = ';';

    // uma linha do arquivo. texto que comeca com = + - @ (ou tab/enter) ganha um ' na frente: senao a
    // planilha executaria como formula o que alguem digitou na descricao de um lancamento (injecao de csv).
    // numero (int) sai como numero, inclusive negativo.
    public static function line(array $fields): string {
        $cells = [];
        foreach ($fields as $field) {
            if (is_int($field)) {
                $cells[] = (string)$field;
                continue;
            }
            $text = (string)$field;
            if ($text !== '' && strpbrk($text[0], "=+-@\t\r") !== false) {
                $text = "'" . $text;
            }
            $cells[] = preg_match('/[;"\r\n]/', $text) ? '"' . str_replace('"', '""', $text) . '"' : $text;
        }
        return implode(self::SEPARATOR, $cells) . "\r\n";
    }

    // cabecalhos de download (o no-store das rotas do painel ja vem do index.php)
    public static function sendHeaders(string $filename): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9._-]/i', '', $filename) . '"');
        header('X-Content-Type-Options: nosniff');
    }
}
