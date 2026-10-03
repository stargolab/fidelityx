<?php

namespace App\Support;

// limite de tentativas guardado no banco (tabela rate_limit_hits), entao vale
// mesmo se a pessoa apagar o cookie ou abrir outra sessao.
// cada "bucket" e um tipo de limite (login_email, login_ip, balance_ip...) e a chave
// e quem esta sendo limitado (e-mail, ip). a chave so e gravada como hash.
class RateLimiter {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // true se a chave ja atingiu $max tentativas nos ultimos $windowSeconds
    public function tooMany(string $bucket, string $key, int $max, int $windowSeconds): bool {
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

        return (int)$stmt->fetchColumn() >= $max;
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

    // ip de quem fez a requisicao. nao confia em X-Forwarded-For: qualquer um pode mandar esse header.
    public static function clientIp(): string {
        return (string)($_SERVER['REMOTE_ADDR'] ?? 'desconhecido');
    }

    private function hash(string $key): string {
        return hash('sha256', mb_strtolower(trim($key)));
    }
}
