# ADR 003 — Atendentes com login próprio

Status: **proposta** (2026-10-06, rascunho para o time decidir) · Task 58 do backlog · Sem código ainda

## Problema encontrado

A loja tem uma senha só. Quem está no balcão usa a conta do dono, então:

1. **Não fica registrado quem fez o quê.** `points_log.responsible_user` existe desde o MVP, mas nunca foi preenchido. Um lançamento errado, um resgate indevido ou um estorno não têm autor.
2. **Todo mundo pode tudo.** Quem lança pontos também apaga prêmio, muda a regra de pontos, troca a senha da conta e exclui dados de cliente.
3. **Tirar o acesso de alguém custa caro.** Quando um funcionário sai, o dono precisa trocar a senha e repassar a nova para todos os outros.

## Decisão proposta

### 1. Quem entra vira "usuário"; a loja continua sendo a loja

Tabela nova `users`, com e-mail e senha saindo de `merchants`:

| Coluna | Observação |
|---|---|
| `id` | |
| `merchant_id` | Loja a que o usuário pertence (FK). Um usuário, uma loja. Ver "Filiais" abaixo. |
| `role` | `owner` (dono) ou `attendant` (atendente). |
| `name`, `email` | E-mail `UNIQUE` na plataforma inteira: o login continua sendo só e-mail + senha. |
| `password_hash`, `email_verified_at`, `status` | Os mesmos de hoje em `merchants`, agora por pessoa. `status` = `active`/`inactive`. |

A migration cria um `owner` para cada loja existente, copiando `owner_name`, `email`, `password_hash` e `email_verified_at`, e só depois tira essas colunas de `merchants`. `merchants.status` continua valendo para a loja inteira: o admin (task 30) desativa a loja, e o dono desativa um atendente.

**Por que uma tabela única e não uma tabela `attendants` ao lado de `merchants`:** com duas tabelas, cada login, sessão, limite de tentativas, recuperação de senha (task 21), confirmação de e-mail (task 50) e o autor de cada movimentação precisariam saber "de qual tabela" é a pessoa. Com `users`, tudo isso passa a apontar para um `user_id`, e o caso do dono deixa de ser especial.

### 2. Sessão e permissões

- A sessão guarda `user_id`, `merchant_id` e `role`. O `authGuard` passa a conferir, a cada requisição, se o usuário **e** a loja estão ativos (desativar um atendente derruba a sessão dele na hora, como hoje acontece com a loja).
- `password_sig` passa a ser do usuário: trocar a senha de um atendente não derruba o dono.
- Um método `requireOwner()` no controller, chamado no começo das rotas só do dono. Rota nova nasce restrita ao dono; liberar para atendente é decisão explícita.

| Ação | Dono | Atendente |
|---|---|---|
| Buscar cliente, cadastro rápido, lançar, resgatar, estornar (24 h) | sim | sim |
| Extrato do cliente, registrar consentimento | sim | sim |
| Corrigir nome do cliente (task 43) | sim | sim *(a confirmar)* |
| Trocar telefone do cliente (task 43) | sim | não *(a confirmar)* |
| Excluir dados do cliente (LGPD) | sim | não |
| Prêmios, regra de pontos, perfil da loja, cartaz | sim | não |
| Relatórios e exportação CSV (task 59) | sim | não *(a confirmar)* |
| Cadastrar, desativar e reativar atendentes | sim | não |

### 3. Quem fez cada movimentação

`points_log.responsible_user VARCHAR(255)` vira `user_id BIGINT UNSIGNED NULL` com FK para `users` (`ON DELETE SET NULL`). `addPoints`, `redeem` e `reverse` recebem o `user_id` da sessão e gravam na mesma transação. Movimentações antigas ficam com `NULL` e aparecem como "—". O extrato, o histórico dos relatórios e o CSV ganham a coluna "Feito por".

O nome mostrado é o do usuário **hoje** (não uma cópia na hora do lançamento): se o atendente for removido (ver LGPD), o histórico passa a mostrar "Atendente removido".

### 4. Cadastro de atendente

O dono informa nome e e-mail numa tela nova (`merchant/team`). O sistema cria o usuário sem senha e manda por e-mail um link para ele criar a própria senha, reaproveitando o `MerchantTokenModel` (tasks 21 e 50): o dono nunca sabe a senha do atendente, e o e-mail já sai confirmado pelo uso do link. **Depende do provedor de e-mail (task 55)**, que ainda não foi escolhido.

### 5. LGPD

Os atendentes são titulares de dados (nome e e-mail) e a loja é a controladora. Desativar mantém o registro (o histórico precisa do autor). Para atender um pedido de exclusão, o dono "remove" o atendente: o usuário fica `inactive`, com nome trocado por "Atendente removido" e e-mail por um valor inválido único, e o `user_id` continua nas movimentações. Nenhuma tela de uma loja mostra atendente de outra.

## Consequências

- **Migration grande e com cópia de dados** (`merchants` → `users`). Precisa de teste de migration sobre um banco no formato antigo, como a 007.
- **Login, recuperação de senha, confirmação de e-mail e limite de tentativas** passam a falar de usuário, não de loja. Os buckets do `RateLimiter` continuam por e-mail, então nada muda para quem usa.
- **Toda rota do painel ganha uma decisão de permissão.** A tabela acima vira teste de fluxo: atendente recebe 403 em cada rota só do dono.
- **Abre caminho para filiais (task 60):** "um usuário, várias lojas" vira uma tabela de vínculo `user_stores` no lugar de `users.merchant_id`, sem mexer em login nem em `points_log`.

## Alternativas descartadas

- **Tabela `attendants` separada, dono continua em `merchants`:** menos migration agora, mas dois caminhos em tudo que envolve login e autoria (ver item 1).
- **PIN de 4 dígitos por atendente, sem login:** registra o autor e é rápido no balcão, mas a sessão continua sendo a do dono (o atendente vê e faz tudo) e PIN curto é fácil de adivinhar.
- **Só um campo "atendente" de texto livre no lançamento:** não controla acesso e qualquer um digita qualquer nome.

## Perguntas em aberto (para o time)

1. Atendente pode corrigir nome, trocar telefone e ver relatórios? (marcados "a confirmar" na tabela)
2. Limite de atendentes por loja? Liga com os planos Free/Pro (task 32).
3. Dono pode redefinir a senha de um atendente, ou só reenviar o link para ele criar outra?
4. A sessão do atendente expira em menos tempo que a do dono (hoje 8 h)?
5. Sem provedor de e-mail (task 55), aceitamos um caminho provisório (dono define a senha inicial e o atendente é obrigado a trocar no primeiro acesso)?
