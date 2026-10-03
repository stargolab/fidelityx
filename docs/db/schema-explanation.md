# Schema do banco — FidelityX

MySQL 8.0+ / MariaDB 10.4+ · InnoDB · `utf8mb4_unicode_ci` · colunas `TIMESTAMP` (guardadas em UTC pelo MySQL e lidas no fuso da aplicação, `APP_TIMEZONE`, padrão `America/Sao_Paulo`).
Arquivo: [`database/schema.sql`](../../database/schema.sql). Bancos criados antes do MVP: rodar [`database/migrations/001_mvp.sql`](../../database/migrations/001_mvp.sql).

## Visão geral

```
merchants 1───N loyalty_cards N───1 customers
    │                 │
    1                 1
    │                 │
    N                 N
 rewards 1───N  points_log
```

- Um **cliente** é identificado pelo **telefone** e é único na plataforma inteira.
- Um **cartão** (`loyalty_cards`) liga um cliente a um lojista e guarda o saldo. Existe no máximo um cartão por par lojista × cliente.
- Toda movimentação de pontos gera uma linha em `points_log`, que funciona como trilha de auditoria.

## Tabelas

### `merchants` — lojistas
| Coluna | Observação |
|---|---|
| `email`, `cpf`, `cnpj` | `UNIQUE`. Só um dos dois documentos é preenchido, apenas com números. |
| `password_hash` | BCRYPT (`password_hash`). |
| `plan` | `free`/`pro`. Ainda não é usado pelo código (pós-MVP). |
| `status` | Padrão `active`. Contas `inactive` são barradas no login. |
| `category`, `state` | Validados contra as listas de `MerchantController::CATEGORIES` e `STATES`. |

### `customers` — clientes
| Coluna | Observação |
|---|---|
| `phone` | `UNIQUE`, 10 ou 11 dígitos sem máscara. É a chave de busca no balcão e na consulta pública. |
| `name` | Informado pelo lojista no primeiro lançamento de pontos. |
| `cpf`, `email`, `birth_date`, `gender` | Opcionais, para uso futuro. |

### `loyalty_cards` — cartões de fidelidade
| Coluna | Observação |
|---|---|
| `merchant_id`, `customer_id` | `UNIQUE (merchant_id, customer_id)`. |
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
| `type` | `earn` (ganho) ou `redeem` (resgate). `quantity` é sempre positivo. |
| `reward_id` | Preenchido em resgates. Vira `NULL` se o prêmio for apagado. |
| `ip_address` | IP de quem fez a operação. |

### `rate_limit_hits` — tentativas (limite de abuso)
| Coluna | Observação |
|---|---|
| `bucket` | Tipo de limite: `login_email`, `login_ip`, `balance_ip`. |
| `key_hash` | SHA-256 da chave (e-mail ou IP em minúsculas). O dado pessoal não é gravado. |
| `created_at` | Cada linha é uma tentativa; só contam as que estão dentro da janela. Linhas com mais de 1 dia são apagadas aos poucos. |

## Regras de consistência

- **Lançar e resgatar** rodam em transação: o saldo do cartão e a linha do `points_log` são gravados juntos, ou nenhum dos dois é gravado.
- **Resgate** trava a linha do cartão com `SELECT ... FOR UPDATE` antes de checar o saldo. Assim, dois resgates simultâneos não conseguem gastar os mesmos pontos.
- **Cartão novo** é criado com `INSERT ... ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)`, que devolve o id existente sem risco de duplicar.
