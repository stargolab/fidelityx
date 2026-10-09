<?php

namespace App\Models;

// links de nova senha (task 21): valem 1 hora (ver MerchantTokenModel)
class PasswordResetModel extends MerchantTokenModel {
    protected const TABLE = 'password_resets';
    public const TTL_SECONDS = 3600;

    // troca a senha pelo link; devolve o id do lojista ou null se o link nao vale
    public function resetPassword(string $token, string $passwordHash): ?int {
        return $this->consume($token, function (int $merchantId) use ($passwordHash) {
            $this->db->prepare('UPDATE merchants SET password_hash = :hash WHERE id = :id')
                ->execute([':hash' => $passwordHash, ':id' => $merchantId]);
        });
    }
}
