-- fidelityx - migration 003 (LGPD: dados do cliente por loja)
-- para bancos criados antes desta versao do schema.sql. rodar uma unica vez:
--   mysql -u root -p fidelityx < database/migrations/003_lgpd.sql
-- decisao e motivos: docs/adr/002-lgpd-dados-por-loja.md

USE fidelityx;

-- 1. cartao guarda o nome que o cliente deu a esta loja, o consentimento e a anonimizacao.
--    customer_id passa a aceitar NULL (cartao anonimizado perde o vinculo com o telefone);
--    a chave estrangeira sai e volta para permitir mudar a coluna.
ALTER TABLE loyalty_cards DROP FOREIGN KEY fk_loyalty_cards_customer;

ALTER TABLE loyalty_cards
  MODIFY customer_id BIGINT UNSIGNED NULL,
  ADD COLUMN customer_name VARCHAR(255) NULL AFTER customer_id,
  ADD COLUMN consent_at TIMESTAMP NULL AFTER customer_name,
  ADD COLUMN consent_version VARCHAR(20) NULL AFTER consent_at,
  ADD COLUMN anonymized_at TIMESTAMP NULL AFTER consent_version;

ALTER TABLE loyalty_cards
  ADD CONSTRAINT fk_loyalty_cards_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

-- 2. cada cartao existente herda o nome que estava no cliente (nada se perde).
--    consent_at fica NULL: o consentimento antigo nao foi gravado; a tela do cliente pede de novo.
UPDATE loyalty_cards lc
JOIN customers c ON c.id = lc.customer_id
SET lc.customer_name = c.name;

-- 3. o nome deixa de ser global
ALTER TABLE customers DROP COLUMN name;

-- 4. codigo publico de cada loja (QR do cartaz e consulta de saldo).
--    lojas novas recebem um codigo gerado pelo app; as existentes, um hexadecimal aleatorio.
ALTER TABLE merchants ADD COLUMN public_code CHAR(8) NULL AFTER id;

UPDATE merchants SET public_code = UPPER(SUBSTRING(SHA2(CONCAT(id, '-', RAND(), '-', NOW(6)), 256), 1, 8));

ALTER TABLE merchants
  MODIFY public_code CHAR(8) NOT NULL,
  ADD UNIQUE KEY uq_merchants_public_code (public_code);
