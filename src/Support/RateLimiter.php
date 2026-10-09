<?php

namespace App\Support;

// limite de tentativas guardado no banco (tabela rate_limit_hits), entao vale
// mesmo se a pessoa apagar o cookie ou abrir outra sessao.
// cada "bucket" e um tipo de limite (login_pair, login_ip, balance_ip...) e a chave
// e quem esta sendo limitado (e-mail + ip, ip, conta). a chave so e gravada como hash.
class RateLimiter {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // true se a chave ja atingiu $max tentativas nos ultimos $windowSeconds
    public function tooMany(string $bucket, string $key, int $max, int $windowSeconds): bool {
        return $this->count($bucket, $key, $windowSeconds) >= $max;
    }

    // tentativas da chave nos ultimos $windowSeconds
    public function count(string $bucket, string $key, int $windowSeconds): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM rate_limit_hits
             WHERE bucket = :bucket AND key_hash = :key_hash
               AND created_at > NOW() - INTERVAL :window SECOND'
        );
        $stmt->execute([
            ':bucket'   => $bucket,
            ':key_hash' => $this->hash($key),
            ':window'   => $windowSeconds,
        ]);

        return (int)$stmt->fetchColumn();
    }

    // registra uma tentativa
    public function hit(string $bucket, string $key): void {
        $stmt = $this->db->prepare('INSERT INTO rate_limit_hits (bucket, key_hash) VALUES (:bucket, :key_hash)');
        $stmt->execute([':bucket' => $bucket, ':key_hash' => $this->hash($key)]);

        // de vez em quando apaga o que ja nao conta pra nenhuma janela (a maior e de 15 min)
        if (random_int(1, 100) === 1) {
            $this->db->exec('DELETE FROM rate_limit_hits WHERE created_at < NOW() - INTERVAL 1 DAY');
        }
    }

    // zera as tentativas da chave (ex.: login certo zera os erros daquele e-mail)
    public function clear(string $bucket, string $key): void {
        $stmt = $this->db->prepare('DELETE FROM rate_limit_hits WHERE bucket = :bucket AND key_hash = :key_hash');
        $stmt->execute([':bucket' => $bucket, ':key_hash' => $this->hash($key)]);
    }

    // ip de quem fez a requisicao. o X-Forwarded-For so vale quando a requisicao chega de um proxy
    // listado em TRUSTED_PROXIES (qualquer um pode mandar esse header); sem isso e o REMOTE_ADDR.
    public static function clientIp(): string {
        return ClientIp::resolve($_SERVER, Env::get('TRUSTED_PROXIES'));
    }

    // chave pros limites por ip: o proprio ip ou, em ipv6, a rede /64 dele (ver ClientIp::rateLimitKey).
    // use esta nos buckets; clientIp() e pra registrar o endereco exato.
    public static function clientKey(): string {
        return ClientIp::rateLimitKey(self::clientIp());
    }

    private function hash(string $key): string {
        return hash('sha256', mb_strtolower(trim($key)));
    }
}
