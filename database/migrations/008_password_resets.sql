-- fidelityx - migration 008 (recuperacao de senha por e-mail, task 21)
-- para bancos criados antes desta versao do schema.sql. rodar uma unica vez:
--   php bin/migrate.php
-- (ou, a mao: mysql -u root -p fidelityx < database/migrations/008_password_resets.sql)

USE fidelityx;

-- link de redefinicao de senha: o token vai so no e-mail; aqui fica o hash sha-256 dele
-- (quem ler o banco nao consegue usar). vale 1 hora e uma vez so.
CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  merchant_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL, -- usado ou cancelado (pedido novo ou senha trocada)

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_password_resets_token (token_hash),
  KEY idx_password_resets_merchant (merchant_id),

  CONSTRAINT fk_password_resets_merchant
    FOREIGN KEY (merchant_id) REFERENCES merchants(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
