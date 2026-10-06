<?php

namespace App\Models;

// links de redefinicao de senha (task 21). o token so existe no e-mail; o banco guarda o hash sha-256,
// entao quem ler o banco (ou um backup) nao consegue usar um link.
class PasswordResetModel {
    public const TTL_SECONDS = 3600;

    private $db;

    function __construct($db) {
        $this->db = $db;
    }

    public static function hash(string $token): string {
        return hash('sha256', $token);
    }

    // cria um link novo e devolve o token (o que vai no e-mail). pedidos anteriores ainda abertos
    // deixam de valer: so o ultimo link enviado funciona.
    public function create(int $merchantId): string {
        $token = bin2hex(random_bytes(32));

        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE password_resets SET used_at = CURRENT_TIMESTAMP WHERE merchant_id = :id AND used_at IS NULL')
                ->execute([':id' => $merchantId]);
            $this->db->prepare(
                'INSERT INTO password_resets (merchant_id, token_hash, expires_at)
                 VALUES (:merchant_id, :token_hash, NOW() + INTERVAL ' . self::TTL_SECONDS . ' SECOND)'
            )->execute([':merchant_id' => $merchantId, ':token_hash' => self::hash($token)]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $token;
    }

    // o link ainda vale (existe, nao foi usado, nao venceu e a conta esta ativa)
    public function isValid(string $token): bool {
        return $token !== '' && $this->findValid(self::hash($token), false) !== false;
    }

    // troca a senha pelo link, tudo ou nada: o link (e qualquer outro aberto da conta) deixa de valer.
    // FOR UPDATE: dois envios do mesmo link ao mesmo tempo nao trocam a senha duas vezes.
    // devolve o id do lojista ou null se o link nao vale.
    public function resetPassword(string $token, string $passwordHash): ?int {
        if ($token === '') {
            return null;
        }

        $this->db->beginTransaction();
        try {
            $merchantId = $this->findValid(self::hash($token), true);
            if ($merchantId === false) {
                $this->db->rollBack();
                return null;
            }

            $this->db->prepare('UPDATE merchants SET password_hash = :hash WHERE id = :id')
                ->execute([':hash' => $passwordHash, ':id' => $merchantId]);
            $this->db->prepare('UPDATE password_resets SET used_at = CURRENT_TIMESTAMP WHERE merchant_id = :id AND used_at IS NULL')
                ->execute([':id' => $merchantId]);

            $this->db->commit();
            return (int)$merchantId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // id do lojista dono do link valido, ou false
    private function findValid(string $tokenHash, bool $lock) {
        $stmt = $this->db->prepare(
            "SELECT pr.merchant_id FROM password_resets pr
             JOIN merchants m ON m.id = pr.merchant_id
             WHERE pr.token_hash = :token_hash AND pr.used_at IS NULL AND pr.expires_at > NOW()
               AND m.status = 'active'" . ($lock ? ' FOR UPDATE' : '')
        );
        $stmt->execute([':token_hash' => $tokenHash]);
        return $stmt->fetchColumn();
    }
}
