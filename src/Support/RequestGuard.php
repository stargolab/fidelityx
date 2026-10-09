<?php

namespace App\Support;

// protecoes aplicadas em toda requisicao, antes de rotear (public/index.php)
final class RequestGuard {
    // cabecalhos de seguranca de toda resposta:
    // - o painel nao abre dentro de iframe de outro site (clickjacking: enganar o lojista pra clicar em "Resgatar")
    // - o navegador nao "adivinha" tipo de arquivo (nosniff)
    // - o telefone que vai na url (?phone=) nao vaza no Referer para outros sites
    // - nao anuncia a versao do PHP
    // - CSP (task 48): script, estilo, imagem e formulario so do proprio site (nada inline: script injetado
    //   num campo nao roda), mais as fontes do Google das paginas de erro. o <svg> do qr e marcacao, nao script.
    public const CSP = "default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; form-action 'self'; "
        . "base-uri 'self'; object-src 'none'; frame-ancestors 'none'";

    // em https o navegador passa a usar so https neste dominio por 1 ano. sem includeSubDomains/preload:
    // isso e decisao do dominio de producao (task 53)
    public const HSTS = 'max-age=31536000';

    public const HEADERS = [
        'X-Frame-Options'         => 'DENY',
        'Content-Security-Policy' => self::CSP,
        'X-Content-Type-Options'  => 'nosniff',
        'Referrer-Policy'         => 'same-origin',
    ];

    public static function sendSecurityHeaders(): void {
        header_remove('X-Powered-By');
        foreach (self::HEADERS as $name => $value) {
            header("$name: $value");
        }
    }

    // so em https (direto ou por proxy de TRUSTED_PROXIES): chamado depois do Env::load, que traz a lista de proxies
    public static function sendHsts(): void {
        if (self::isHttps()) {
            header('Strict-Transport-Security: ' . self::HSTS);
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
