-- fidelityx - migration 010 (painel administrativo, task 30)
-- para bancos criados antes desta versao do schema.sql. rodar uma unica vez:
--   php bin/migrate.php
-- (ou, a mao: mysql -u root -p fidelityx < database/migrations/010_admins.sql)

USE fidelityx;

-- administradores do FidelityX (nao sao lojistas): login proprio em admin/login, conta criada
-- pela linha de comando (php bin/create-admin.php). veem as lojas, nunca os clientes delas.
CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  email VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
