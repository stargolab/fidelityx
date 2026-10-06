# Schema do banco — FidelityX

MySQL 8.0+ / MariaDB 10.4+ · InnoDB · `utf8mb4_unicode_ci` · colunas `TIMESTAMP` (guardadas em UTC pelo MySQL e lidas no fuso da aplicação, `APP_TIMEZONE`, padrão `America/Sao_Paulo`).
Arquivo: [`database/schema.sql`](../../database/schema.sql). Bancos já existentes: `php bin/migrate.php` roda as migrations de [`database/migrations/`](../../database/migrations/) que ainda não rodaram (ver [operação](../operacao.md#migrations)).

## Visão geral

```
merchants 1───N loyalty_cards N───1 customers
    │                 │
    1                 1
    │                 │
    N                 N
 rewards 1───N  points_log
```

- Um **cliente** é só o **telefone**, único na plataforma inteira.
- Um **cartão** (`loyalty_cards`) liga um cliente a um lojista e guarda o que o cliente informou **àquela loja** (nome e consentimento) e o saldo. Existe no máximo um cartão por par lojista × cliente. Uma loja nunca vê o cartão de outra ([ADR 002](../adr/002-lgpd-dados-por-loja.md)).
- Toda movimentação de pontos gera uma linha em `points_log`, que funciona como trilha de auditoria.

## Tabelas

### `merchants` — lojistas
| Coluna | Observação |
|---|---|
| `points_rule_cents` | Regra de pontos pelo valor da compra: a cada tantos centavos, 1 ponto (sempre arredonda para baixo, `Money::pointsFor`). `NULL` = sem regra, o lojista digita os pontos. |
| `public_code` | `UNIQUE`, 8 caracteres. Código público da loja: vai no QR do cartaz e identifica a loja na consulta de saldo. Gerado no cadastro (`App\Support\PublicCode`). |
| `email`, `cpf`, `cnpj` | `UNIQUE`. Só um dos dois documentos é preenchido, apenas com números. |
| `password_hash` | BCRYPT (`password_hash`). |
| `plan` | `free`/`pro`. Ainda não é usado pelo código (pós-MVP). |
| `status` | Padrão `active`. Contas `inactive` são barradas no login. |
| `category`, `state` | Validados contra as listas de `MerchantController::CATEGORIES` e `STATES`. |

### `customers` — clientes
| Coluna | Observação |
|---|---|
| `phone` | `UNIQUE`, 10 ou 11 dígitos sem máscara. É a chave de busca no balcão e na consulta pública. |

O cliente é **só o telefone** (task 11, ADR 002). As colunas `cpf`, `email`, `birth_date` e `gender`, que estavam “para uso futuro”, saíram na migration 007 (task 49): dado pessoal sem uso e sem base legal não fica no banco. Nome e consentimento ficam no cartão de cada loja.

### `loyalty_cards` — cartões de fidelidade
| Coluna | Observação |
|---|---|
| `merchant_id`, `customer_id` | `UNIQUE (merchant_id, customer_id)`. `customer_id` vira `NULL` quando o cartão é anonimizado. |
| `customer_name` | Nome que o cliente informou a esta loja. Nenhuma outra loja vê. |
| `consent_at`, `consent_version` | Quando o cliente autorizou e qual versão da política (`Privacy::VERSION`). `NULL` = cadastro anterior ao registro; a tela do cliente pede de novo. |
| `anonymized_at` | Exclusão a pedido do cliente: o cartão perde nome, telefone, consentimento e saldo e fica só para os relatórios. |
| `current_points` | Saldo disponível. Aumenta ao ganhar pontos e diminui ao resgatar. |
| `total_accumulated` | Tudo que o cliente já ganhou nesta loja. Só aumenta. |
| `last_use_at` | Última movimentação. |

### `rewards` — catálogo de prêmios (novo no MVP)
| Coluna | Observação |
|---|---|
| `merchant_id` | Todo acesso filtra por essa coluna, então um lojista nunca vê prêmios de outro. |
| `points_cost` | Custo em pontos (inteiro positivo). |
| `active` | Prêmios inativos não aparecem para resgate nem na consulta pública. |

### `points_log` — histórico
| Coluna | Observação |
|---|---|
| `type` | `earn` (ganho), `redeem` (resgate) ou `reversal` (estorno de um ganho ou de um resgate, task 44). `quantity` é sempre positivo: o sinal vem do tipo (o estorno de resgate devolve pontos). |
| `reward_id` | Preenchido em resgates. Vira `NULL` se o prêmio for apagado. |
| `reverses_id` | Preenchido em estornos (`reversal`): o ganho ou resgate estornado. `UNIQUE`, então cada movimentação é estornada no máximo uma vez. |
| `ip_address` | IP de quem fez a operação (o do cliente, mesmo atrás de proxy confiável: `TRUSTED_PROXIES`). |
| índice `idx_points_log_card_created` | `(card_id, created_at)`: o extrato de um cartão é lido já na ordem de data, sem ordenar tudo a cada página. Também atende a chave estrangeira de `card_id`. |

### `rate_limit_hits` — tentativas (limite de abuso)
| Coluna | Observação |
|---|---|
| `bucket` | Tipo de limite: `login_pair` (erros de login do mesmo e-mail vindos do mesmo IP), `login_ip` (erros de login do mesmo IP em qualquer conta), `balance_ip` (consulta pública) e `password_change` (senha atual errada na troca de senha, por conta). Não existe bloqueio de login só por e-mail. |
| `key_hash` | SHA-256 da chave em minúsculas (e-mail + IP, IP ou id da conta, conforme o bucket; IP em IPv6 entra como a rede /64). O dado pessoal não é gravado. |
| `created_at` | Cada linha é uma tentativa; só contam as que estão dentro da janela. Linhas com mais de 1 dia são apagadas aos poucos. |

### `schema_migrations` — controle das migrations
| Coluna | Observação |
|---|---|
| `version` | Nome do arquivo de `database/migrations/` sem `.sql` (ex.: `003_lgpd`). Chave primária. |
| `applied_at` | Quando rodou (ou foi marcada como já aplicada pelo `baseline`). |

O `schema.sql` já insere todas as migrations existentes: banco novo nasce sem pendência. Não é dado de negócio; os testes não esvaziam esta tabela.

## Regras de consistência

- **Lançar e resgatar** rodam em transação: o saldo do cartão e a linha do `points_log` são gravados juntos, ou nenhum dos dois é gravado.
- **Resgate** trava a linha do cartão com `SELECT ... FOR UPDATE` antes de checar o saldo. Assim, dois resgates simultâneos não conseguem gastar os mesmos pontos.
- **Cartão novo** é criado com `INSERT ... ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)`, que devolve o id existente sem risco de duplicar.
