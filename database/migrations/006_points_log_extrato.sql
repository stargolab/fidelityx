-- fidelityx - migration 006 (indice do extrato e do historico paginados, task 40)
-- para bancos criados antes desta versao do schema.sql. rodar uma unica vez:
--   php bin/migrate.php
-- (ou, a mao: mysql -u root -p fidelityx < database/migrations/006_points_log_extrato.sql)

USE fidelityx;

-- o extrato busca as movimentacoes de um cartao da mais recente pra mais antiga
-- (WHERE card_id = ? ORDER BY created_at DESC, id DESC LIMIT ...). com o indice so em card_id o mysql
-- lia todas as linhas do cartao e ordenava a cada pagina; com (card_id, created_at) ele ja le na ordem
-- e para no LIMIT (o id, chave primaria, vem no fim de todo indice do InnoDB e desempata).
-- o indice novo comeca por card_id, entao substitui o antigo, inclusive para a chave estrangeira.
ALTER TABLE points_log
  ADD KEY idx_points_log_card_created (card_id, created_at),
  DROP KEY idx_points_log_card;
