<?php

namespace App\Models;

// links de confirmacao do e-mail do lojista (task 50): valem 24 horas (ver MerchantTokenModel)
class EmailVerificationModel extends MerchantTokenModel {
    protected const TABLE = 'email_verifications';
    public const TTL_SECONDS = 86400;

    // confirma o e-mail pelo link; devolve o id do lojista ou null se o link nao vale
    public function verify(string $token): ?int {
        return $this->consume($token, function (int $merchantId) {
            $this->db->prepare('UPDATE merchants SET email_verified_at = CURRENT_TIMESTAMP WHERE id = :id AND email_verified_at IS NULL')
                ->execute([':id' => $merchantId]);
        });
    }
}
