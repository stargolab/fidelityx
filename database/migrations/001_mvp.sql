-- fidelityx - migration 001 (MVP)
-- para bancos criados com a versão anterior do schema.sql.
-- instalações novas não precisam disto: o schema.sql já está atualizado.
-- rodar uma única vez: mysql -u root -p fidelityx < database/migrations/001_mvp.sql

USE fidelityx;

-- lojistas novos passam a nascer ativos (o login bloqueia contas 'inactive')
ALTER TABLE merchants
  MODIFY status ENUM('active', 'inactive') NOT NULL DEFAULT 'active';

-- catálogo de prêmios de cada lojista
CREATE TABLE IF NOT EXISTS rewards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  merchant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  points_cost INT UNSIGNED NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_rewards_merchant_active (merchant_id, active),

  CONSTRAINT fk_rewards_merchant
    FOREIGN KEY (merchant_id) REFERENCES merchants(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- resgates passam a apontar pro prêmio resgatado
ALTER TABLE points_log
  ADD COLUMN reward_id BIGINT UNSIGNED NULL AFTER description,
  ADD KEY idx_points_log_reward (reward_id),
  ADD CONSTRAINT fk_points_log_reward
    FOREIGN KEY (reward_id) REFERENCES rewards(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
