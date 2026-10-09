<?php

namespace App\Models;

// link com token mandado por e-mail ao lojista (nova senha, task 21; confirmacao de e-mail, task 50).
// o token so existe no e-mail; o banco guarda o hash sha-256, entao quem ler o banco (ou um backup)
// nao consegue usar um link. cada link vale TTL_SECONDS e uma vez; pedir outro cancela o anterior.
abstract class MerchantTokenModel {
    // tabela com merchant_id, token_hash, expires_at e used_at
    protected const TABLE = '';
    public const TTL_SECONDS = 3600;

    protected $db;

    function __construct($db) {
        $this->db = $db;
    }

    public static function hash(string $token): string {
        return hash('sha256', $token);
    }

    // cria um link novo e devolve o token (o que vai no e-mail). os anteriores ainda abertos deixam de valer.
    public function create(int $merchantId): string {
        $token = bin2hex(random_bytes(32));

        $this->db->beginTransaction();
        try {
            $this->cancelOpen($merchantId);
            $this->db->prepare(
                'INSERT INTO ' . static::TABLE . ' (merchant_id, token_hash, expires_at)
                 VALUES (:merchant_id, :token_hash, NOW() + INTERVAL ' . static::TTL_SECONDS . ' SECOND)'
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

    // usa o link, tudo ou nada: $apply recebe o id do lojista e faz a mudanca; depois o link (e qualquer
    // outro aberto da conta) deixa de valer. FOR UPDATE: dois envios do mesmo link ao mesmo tempo nao
    // aplicam duas vezes. devolve o id do lojista ou null se o link nao vale.
    protected function consume(string $token, callable $apply): ?int {
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

            $apply((int)$merchantId);
            $this->cancelOpen((int)$merchantId);

            $this->db->commit();
            return (int)$merchantId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function cancelOpen(int $merchantId): void {
        $this->db->prepare('UPDATE ' . static::TABLE . ' SET used_at = CURRENT_TIMESTAMP WHERE merchant_id = :id AND used_at IS NULL')
            ->execute([':id' => $merchantId]);
    }

    // id do lojista dono do link valido, ou false
    private function findValid(string $tokenHash, bool $lock) {
        $stmt = $this->db->prepare(
            'SELECT t.merchant_id FROM ' . static::TABLE . " t
             JOIN merchants m ON m.id = t.merchant_id
             WHERE t.token_hash = :token_hash AND t.used_at IS NULL AND t.expires_at > NOW()
               AND m.status = 'active'" . ($lock ? ' FOR UPDATE' : '')
        );
        $stmt->execute([':token_hash' => $tokenHash]);
        return $stmt->fetchColumn();
    }
}
