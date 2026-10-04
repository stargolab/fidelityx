-- fidelityx - migration 005 (pontos calculados pelo valor da compra, task 6)
-- para bancos criados antes desta versao do schema.sql. rodar uma unica vez:
--   mysql -u root -p fidelityx < database/migrations/005_regra_pontos.sql

USE fidelityx;

-- a cada points_rule_cents centavos em compras, 1 ponto (arredonda pra baixo). NULL = sem regra.
ALTER TABLE merchants
  ADD COLUMN points_rule_cents INT UNSIGNED NULL AFTER plan;
