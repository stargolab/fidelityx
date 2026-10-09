-- fidelityx - migration 007 (validade dos pontos, task 31)
-- para bancos criados antes desta versao do schema.sql. rodar uma unica vez:
--   php bin/migrate.php
-- (ou, a mao: mysql -u root -p fidelityx < database/migrations/007_validade_pontos.sql)

USE fidelityx;

-- prazo de validade por loja: o saldo do cliente vence depois de points_expiry_months meses sem
-- nenhuma movimentacao naquela loja (loyalty_cards.last_use_at). NULL = os pontos nao vencem.
-- o padrao e 12 meses, e as lojas que ja existem tambem comecam com ele.
ALTER TABLE merchants
  ADD COLUMN points_expiry_months TINYINT UNSIGNED NULL DEFAULT 12 AFTER points_rule_cents;

-- o vencimento vira uma linha propria no historico ('expire'), como o resgate e o estorno
ALTER TABLE points_log
  MODIFY type ENUM('earn', 'redeem', 'reversal', 'expire') NOT NULL;
