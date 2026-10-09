-- fidelityx - migration 009 (confirmacao do e-mail do lojista, task 50)
-- para bancos criados antes desta versao do schema.sql. rodar uma unica vez:
--   php bin/migrate.php
-- (ou, a mao: mysql -u root -p fidelityx < database/migrations/009_email_verifications.sql)

USE fidelityx;

-- conta nova so usa o painel depois de confirmar o e-mail (NULL = ainda nao confirmou)
ALTER TABLE merchants ADD COLUMN email_verified_at TIMESTAMP NULL AFTER email;

-- contas que ja existiam contam como confirmadas: nao da pra travar quem ja usa o sistema
UPDATE merchants SET email_verified_at = created_at WHERE email_verified_at IS NULL;

-- link de confirmacao: como o de nova senha, so o hash do token fica aqui. vale 24 horas e uma vez.
CREATE TABLE IF NOT EXISTS email_verifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  merchant_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL, -- usado ou cancelado (reenvio)

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_email_verifications_token (token_hash),
  KEY idx_email_verifications_merchant (merchant_id),

  CONSTRAINT fk_email_verifications_merchant
    FOREIGN KEY (merchant_id) REFERENCES merchants(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
