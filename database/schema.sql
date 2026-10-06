CREATE DATABASE IF NOT EXISTS fidelityx;
USE fidelityx;

-- fidelityx - MySQL 8.0+
-- infra: InnoDB + utf8mb4 + utf8mb4_unicode_ci

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';

-- =========================
-- merchants // lojistas
-- =========================
CREATE TABLE IF NOT EXISTS merchants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- codigo publico da loja (8 caracteres): vai no QR do cartaz e identifica a loja na consulta de saldo
  public_code CHAR(8) NOT NULL,

  owner_name VARCHAR(255) NOT NULL,
  store_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  category VARCHAR(30) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  plan ENUM('free', 'pro') NOT NULL DEFAULT 'free',
  -- regra de pontos pelo valor da compra: a cada points_rule_cents centavos, 1 ponto (arredonda pra baixo).
  -- NULL = sem regra (o lojista digita os pontos direto)
  points_rule_cents INT UNSIGNED NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',

  -- para futuras implementações (NULL)
  cnpj VARCHAR(14) NULL,
  cpf VARCHAR(11) NULL,
  address VARCHAR(255) NOT NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(50) NOT NULL,
  logo_url VARCHAR(2048) NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_merchants_email (email),
  UNIQUE KEY uq_merchants_public_code (public_code),
  UNIQUE KEY uq_merchants_cpf (cpf),
  UNIQUE KEY uq_merchants_cnpj (cnpj),
  KEY idx_merchants_status (status), -- teste com indices pra melhorar a otimizacao de busca
  KEY idx_merchants_plan (plan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- customers // clientes
-- =========================
-- so o telefone e global. o nome fica no cartao de cada loja (loyalty_cards.customer_name):
-- uma loja nunca ve o que o cliente informou em outra (LGPD, ver docs/adr/002).
CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  phone VARCHAR(30) NOT NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_customers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- loyalty_cards // cartões de fidelidade
-- =========================
CREATE TABLE IF NOT EXISTS loyalty_cards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  merchant_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NULL, -- NULL depois que o cartao e anonimizado

  -- dados que o cliente deu a ESTA loja (NULL depois de anonimizado)
  customer_name VARCHAR(255) NULL,
  consent_at TIMESTAMP NULL,           -- quando o cliente autorizou (NULL = cadastro anterior ao registro)
  consent_version VARCHAR(20) NULL,    -- versao do texto de privacidade aceito (App\Support\Privacy::VERSION)
  anonymized_at TIMESTAMP NULL,        -- exclusao a pedido do cliente: o cartao fica so para os relatorios

  current_points INT NOT NULL DEFAULT 0,
  total_accumulated INT NOT NULL DEFAULT 0,

  -- para futuras implementações (NULL)
  last_use_at TIMESTAMP NULL,
  expiration_date TIMESTAMP NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_loyalty_cards_merchant_customer (merchant_id, customer_id),
  KEY idx_loyalty_cards_customer (customer_id),
  KEY idx_loyalty_cards_merchant (merchant_id),

  CONSTRAINT fk_loyalty_cards_merchant
    FOREIGN KEY (merchant_id) REFERENCES merchants(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_loyalty_cards_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- rewards // catálogo de prêmios de cada lojista
-- =========================
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

-- =========================
-- points_log // histórico / auditoria
-- =========================
CREATE TABLE IF NOT EXISTS points_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  card_id BIGINT UNSIGNED NOT NULL,
  type ENUM('earn', 'redeem', 'reversal') NOT NULL, -- reversal = estorno de um earn
  quantity INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  reward_id BIGINT UNSIGNED NULL, -- preenchido quando type = 'redeem'
  reverses_id BIGINT UNSIGNED NULL, -- preenchido quando type = 'reversal': o lancamento estornado

  -- para futuras implementações (NULL)
  responsible_user VARCHAR(255) NULL,
  ip_address VARCHAR(45) NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- extrato paginado: movimentacoes de um cartao ja na ordem de data (o id, chave primaria, desempata)
  KEY idx_points_log_card_created (card_id, created_at),
  KEY idx_points_log_type (type),

  KEY idx_points_log_reward (reward_id),
  UNIQUE KEY uq_points_log_reverses (reverses_id), -- um lancamento so pode ser estornado uma vez

  CONSTRAINT fk_points_log_card
    FOREIGN KEY (card_id) REFERENCES loyalty_cards(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_points_log_reward
    FOREIGN KEY (reward_id) REFERENCES rewards(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_points_log_reverses
    FOREIGN KEY (reverses_id) REFERENCES points_log(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- rate_limit_hits // tentativas para limitar abuso (login, consulta publica)
-- =========================
-- a chave (e-mail ou ip) fica so como hash sha-256: da pra contar sem guardar o dado pessoal.
CREATE TABLE IF NOT EXISTS rate_limit_hits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  bucket VARCHAR(40) NOT NULL, -- ex.: login_pair, login_ip, login_account, password_change, balance_ip
  key_hash CHAR(64) NOT NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_rate_limit_lookup (bucket, key_hash, created_at),
  KEY idx_rate_limit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- schema_migrations // quais migrations de database/migrations ja rodaram neste banco
-- =========================
-- controle usado pelo php bin/migrate.php (App\Support\Migrator).
CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(100) NOT NULL PRIMARY KEY, -- nome do arquivo sem .sql, ex.: 003_lgpd
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- este arquivo ja e o estado final: banco criado por ele nasce com todas as migrations registradas.
-- migration nova = arquivo em database/migrations + uma linha aqui (o teste MigratorTest cobra as duas coisas).
INSERT IGNORE INTO schema_migrations (version) VALUES
  ('001_mvp'),
  ('002_rate_limit'),
  ('003_lgpd'),
  ('004_estorno'),
  ('005_regra_pontos'),
  ('006_points_log_extrato'),
  ('007_customers_so_telefone');
