<?php

namespace App\Support;

// descobre o ip de quem fez a requisicao e se ela chegou por https, com ou sem proxy na frente.
//
// sem proxy: vale o REMOTE_ADDR e os cabecalhos X-Forwarded-* sao ignorados (qualquer um pode manda-los).
// atras de proxy/load balancer: o REMOTE_ADDR e o ip do proxy. so quando esse ip esta na lista
// TRUSTED_PROXIES (ips ou faixas CIDR separados por virgula) os cabecalhos do proxy passam a valer.
final class ClientIp {
    // $server = $_SERVER; $trustedProxies = valor de TRUSTED_PROXIES
    public static function resolve(array $server, string $trustedProxies): string {
        $remote = trim((string)($server['REMOTE_ADDR'] ?? ''));
        if ($remote === '') {
            return 'desconhecido';
        }

        $trusted = self::parseList($trustedProxies);
        if (!self::matchesAny($remote, $trusted)) {
            return $remote;
        }

        // X-Forwarded-For: "cliente, proxy1, proxy2". cada proxy acrescenta no fim o ip de quem falou com ele,
        // entao so a ponta direita e confiavel: anda da direita pra esquerda e para no primeiro ip
        // que nao e proxy nosso (o que vier antes dele pode ter sido inventado pelo cliente).
        $hops = array_reverse(array_map('trim', explode(',', (string)($server['HTTP_X_FORWARDED_FOR'] ?? ''))));
        foreach ($hops as $hop) {
            if (filter_var($hop, FILTER_VALIDATE_IP) === false) {
                break; // cabecalho malformado: fica com o ultimo ip confiavel
            }
            $remote = $hop;
            if (!self::matchesAny($hop, $trusted)) {
                break;
            }
        }

        return $remote;
    }

    // https direto no servidor ou, vindo de proxy confiavel, pelo X-Forwarded-Proto
    public static function isHttps(array $server, string $trustedProxies): bool {
        if (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') {
            return true;
        }

        $remote = trim((string)($server['REMOTE_ADDR'] ?? ''));
        if ($remote === '' || !self::matchesAny($remote, self::parseList($trustedProxies))) {
            return false;
        }

        // com varios proxies o cabecalho pode vir "https, http": vale o primeiro (o que o cliente usou)
        $proto = strtolower(trim(explode(',', (string)($server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        return $proto === 'https';
    }

    // chave usada nos limites de tentativas por ip.
    // ipv4: o proprio endereco. ipv6: a rede /64 do endereco, porque cada assinante recebe um bloco
    // inteiro desses e, se o limite fosse por endereco, bastaria trocar de endereco a cada tentativa.
    public static function rateLimitKey(string $ip): string {
        $packed = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false ? false : inet_pton($ip);
        if ($packed === false) {
            return $ip; // ipv4 (ou "desconhecido")
        }

        // ipv4 escrito como ipv6 ("::ffff:203.0.113.9"): vale o ipv4, senao todos cairiam na mesma rede
        if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            return inet_ntop(substr($packed, 12));
        }

        return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
    }

    // "10.0.0.1, 172.16.0.0/12" -> ['10.0.0.1', '172.16.0.0/12'] (so entradas validas)
    public static function parseList(string $list): array {
        $entries = [];
        foreach (explode(',', $list) as $entry) {
            $entry = trim($entry);
            if ($entry !== '' && self::parseRange($entry) !== null) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    private static function matchesAny(string $ip, array $ranges): bool {
        foreach ($ranges as $range) {
            if (self::inRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    // $range = ip unico ou faixa CIDR (ipv4 ou ipv6)
    public static function inRange(string $ip, string $range): bool {
        $parsed = self::parseRange($range);
        $packed = filter_var($ip, FILTER_VALIDATE_IP) === false ? false : inet_pton($ip);
        if ($parsed === null || $packed === false) {
            return false;
        }

        [$network, $bits] = $parsed;
        if (strlen($packed) !== strlen($network)) {
            return false; // ipv4 nunca casa com faixa ipv6 e vice-versa
        }

        // compara os bytes inteiros da mascara e depois os bits que sobram
        $bytes = intdiv($bits, 8);
        if (substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = 0xFF << (8 - $rest) & 0xFF;
        return (ord($packed[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }

    // devolve [rede em binario, bits da mascara] ou null se a entrada nao for ip nem faixa valida
    private static function parseRange(string $range): ?array {
        $parts = explode('/', $range, 2);
        if (filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $network = inet_pton($parts[0]);
        $max = strlen($network) * 8;
        if (!isset($parts[1])) {
            return [$network, $max];
        }
        if (!ctype_digit($parts[1]) || (int)$parts[1] > $max) {
            return null;
        }
        return [$network, (int)$parts[1]];
    }
}
