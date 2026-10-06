-- fidelityx - migration 007 (cliente e so o telefone, task 49)
-- para bancos criados antes desta versao do schema.sql. rodar uma unica vez:
--   php bin/migrate.php
-- (ou, a mao: mysql -u root -p fidelityx < database/migrations/007_customers_so_telefone.sql)

USE fidelityx;

-- cpf, email, birth_date e gender estavam "para o futuro" e nunca foram usados: o cliente e so o telefone
-- (task 11, docs/adr/002) e o nome fica no cartao de cada loja. coluna vazia de dado pessoal convida a
-- guardar dado sem base legal, entao sai. o indice unico do cpf e o do email saem junto.
ALTER TABLE customers
  DROP KEY uq_customers_cpf,
  DROP KEY idx_customers_email,
  DROP COLUMN cpf,
  DROP COLUMN email,
  DROP COLUMN birth_date,
  DROP COLUMN gender;
