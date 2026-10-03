-- fidelityx - migration 002 (limite de tentativas)
-- para bancos criados antes desta versão do schema.sql.
-- rodar uma única vez: mysql -u root -p fidelityx < database/migrations/002_rate_limit.sql

USE fidelityx;

-- tentativas para limitar abuso (login, consulta publica).
-- a chave (e-mail ou ip) fica so como hash sha-256: da pra contar sem guardar o dado pessoal.
CREATE TABLE IF NOT EXISTS rate_limit_hits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  bucket VARCHAR(40) NOT NULL,
  key_hash CHAR(64) NOT NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_rate_limit_lookup (bucket, key_hash, created_at),
  KEY idx_rate_limit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
