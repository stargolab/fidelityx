-- fidelityx - migration 004 (estorno de lancamento de pontos, task 4)
-- para bancos criados antes desta versao do schema.sql. rodar uma unica vez:
--   php bin/migrate.php
-- (ou, a mao: mysql -u root -p fidelityx < database/migrations/004_estorno.sql)

USE fidelityx;

-- estorno vira um registro novo no historico (nada e apagado), apontando para o lancamento estornado.
-- o UNIQUE em reverses_id garante que um lancamento so e estornado uma vez, mesmo com dois cliques ao mesmo tempo.
ALTER TABLE points_log
  MODIFY type ENUM('earn', 'redeem', 'reversal') NOT NULL,
  ADD COLUMN reverses_id BIGINT UNSIGNED NULL AFTER reward_id,
  ADD UNIQUE KEY uq_points_log_reverses (reverses_id),
  ADD CONSTRAINT fk_points_log_reverses
    FOREIGN KEY (reverses_id) REFERENCES points_log(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;
