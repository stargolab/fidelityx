<?php

namespace App\Support;

// protecoes aplicadas em toda requisicao, antes de rotear (public/index.php)
final class RequestGuard {
    // cabecalhos de seguranca de toda resposta:
    // - o painel nao abre dentro de iframe de outro site (clickjacking: enganar o lojista pra clicar em "Resgatar")
    // - o navegador nao "adivinha" tipo de arquivo (nosniff)
    // - o telefone que vai na url (?phone=) nao vaza no Referer para outros sites
    // - nao anuncia a versao do PHP
    public const HEADERS = [
        'X-Frame-Options'         => 'DENY',
        'Content-Security-Policy' => "frame-ancestors 'none'",
        'X-Content-Type-Options'  => 'nosniff',
        'Referrer-Policy'         => 'same-origin',
    ];

    public static function sendSecurityHeaders(): void {
        header_remove('X-Powered-By');
        foreach (self::HEADERS as $name => $value) {
            header("$name: $value");
        }
    }

    // painel e consulta de saldo mostram nome, telefone e saldo: a resposta nao fica guardada no navegador
    // nem em proxy (o Voltar depois do logout ou um computador compartilhado nao mostram nada).
    // chamado depois do session_start, que manda o cache-control dele e seria sobrescrito por este.
    public static function sendNoStore(): void {
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
    }

    // nenhum formulario do sistema manda lista (campo[]=...). parametro em formato de lista vira
    // texto vazio, que cada tela ja trata como invalido. sem isso, um (string) num array gera
    // warning ("Array to string conversion") e, com display_errors ligado, quebra o redirect.
    public static function dropArrayParams(array $params): array {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = '';
            }
        }
        return $params;
    }

    // a requisicao chegou por https: direto, pelo servidor web ou por um proxy de TRUSTED_PROXIES
    // que termina o https e avisa pelo X-Forwarded-Proto
    public static function isHttps(): bool {
        return ClientIp::isHttps($_SERVER, Env::get('TRUSTED_PROXIES'));
    }
}
