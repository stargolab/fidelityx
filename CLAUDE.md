# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projeto

FidelityX: SaaS de fidelidade para lojistas locais. PHP 8.1+ sem framework (MVC manual), MySQL/MariaDB via PDO, única dependência Composer é `vlucas/phpdotenv`. Código, comentários, mensagens e commits em português (comentários em minúsculas e sem acento, no estilo existente).

## Comandos

```bash
composer install                       # dependencias
composer dump-autoload                 # obrigatorio apos criar classe nova em src/ ou mudar autoload "files"
php -S localhost:8000 -t public/       # servidor local (document root = public/)
find src views public -name "*.php" -exec php -l {} \;   # checagem de sintaxe (único "lint" existente)
tsc                                    # compila src/ts -> public/js (tsconfig.json)
```

Banco: `mysql -u root -p < database/schema.sql` em instalação nova; bancos anteriores ao MVP precisam de `database/migrations/001_mvp.sql` uma vez. Credenciais em `.env` (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`). No ambiente local do autor o PHP e o MySQL vêm do XAMPP (`C:\xampp\mysql\bin\mysql.exe`).

Não há testes automatizados (PHPUnit/PHPStan/PHP-CS-Fixer estão no roadmap). Para validar mudanças, suba o servidor e exercite as rotas com `curl` usando cookie jar e extraindo o campo `_csrf` do HTML do GET antes de cada POST.

## Arquitetura

**Roteamento** — tudo entra por `public/index.php` com `?url=dominio/acao`. Um `switch` no domínio (`merchant`, `customer`) instancia o controller e um `match` na ação chama o método `render*()`. Rota nova = novo braço no `match` + método no controller. O `index.php` abre a conexão com o banco antes de rotear, então sem MySQL toda rota devolve 503.

**Padrão de controller** — cada rota tem um `renderX()` público: no GET chama `View::render(...)`, no POST delega para um `handleX()` privado. Todo `handle*` começa com `Csrf::verify()` (aborta com 403). Rotas privadas começam com `$merchantId = $this->authGuard()`; o `merchant_id` vem **sempre da sessão**, nunca do formulário, e todo model filtra por ele (isolamento entre lojas).

**Post/Redirect/Get com códigos de flash** — handlers terminam com `redirect('rota', ['error' => 'codigo'])` ou `['success' => 'codigo']` (`redirect()` tem tipo `never`, faz `exit`). `views/partials/flash.php` traduz o código via um mapa fixo; **código novo precisa ser adicionado nesse mapa**, senão nada aparece. Nunca exibir o valor cru da query string.

**Views** — PHP puro em `views/`, recebem variáveis via `extract()` do `View::render($view, $data)`. Toda saída passa por `e()`. Links e actions usam `url('rota', [...])`; formulários POST incluem `<?= Csrf::field() ?>`. Páginas do painel incluem `partials/merchant-header.php` / `merchant-footer.php` (definir `$title` antes). Helpers globais (`e`, `redirect`, `url`, `format_phone`, `format_datetime`) ficam em `src/Support/helpers.php`, carregado pelo autoload `files` do Composer.

**Erros** — `ErrorController::handle($code)` renderiza `views/errors/{code}.php` (fallback `default.php`). Exceções não tratadas são logadas e viram 500 pelo `set_exception_handler` do `index.php`; detalhes técnicos vão só para `error_log`.

**Modelo de dados** (detalhes em `docs/db/schema-explanation.md`):
- `customers` é global e único por telefone (só dígitos, 10–11, via `PhoneValidator::sanitize`); o mesmo cliente pode ter cartão em várias lojas.
- `loyalty_cards` = par (merchant, customer) único, guarda `current_points` e `total_accumulated`. `findOrCreate` usa `ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)`.
- `points_log` é o histórico (`earn`/`redeem`, `reward_id` nos resgates). Toda mudança de saldo passa por `LoyaltyCardModel::addPoints` / `redeem`, que atualizam o cartão e gravam o log na mesma transação; `redeem` usa `SELECT ... FOR UPDATE` para impedir gasto duplo. Não altere saldo fora desses métodos.
- `merchants` guarda CPF **ou** CNPJ (normalizados, UNIQUE). Validação só por dígito verificador, sem API externa (ver `docs/adr/001-documents-validation.md`).

**Área pública** — `customer/balance` consulta saldo pelo telefone sem login, com rate limit por sessão (5/min → 429) e exibindo só o primeiro nome do cliente.

## Convenções

- Commits no padrão `tipo(escopo): descrição` (`feat`, `fix`, `refactor`, `docs`, `test`, `chore`); branches `tipo/nome` com o mesmo tipo do commit (ex.: `feat/logout`, `fix/csrf-token`). Nunca commitar direto na `main`: tudo entra por PR, e o PR referencia a issue com `close #N` quando houver.
- SQL sempre com prepared statements; o PDO usa `ATTR_EMULATE_PREPARES = false`, então o mesmo placeholder não pode aparecer duas vezes na query (use `:p1`, `:p2`).
- O front-end em TypeScript (`src/ts/`) ainda é placeholder; a sanitização de máscaras é feita no back-end.

## Backlog

As tasks pós-MVP ficam no doc **FidelityX — Backlog pós-MVP** (Claude Docs): https://claude.ai/artifact/4WYQTvbJf5j4oevnFbfSbm

É um documento do Claude Docs, não um artifact HTML: leia e edite **só pelo conector Claude Docs** (`read`/`update`). Nunca republique o artifact nem crie outro doc.

- Cada tema tem uma tabela com as colunas `#`, Task, Prioridade, Tamanho, Responsável e Status (dropdown: A fazer → Em andamento → Em revisão → Feito). A lista "Detalhes" abaixo de cada tabela traz o escopo de cada task pelo número.
- Ao começar uma task: leia a linha e os detalhes dela, coloque o responsável e mude o Status para **Em andamento**.
- PR aberto: **Em revisão**. PR mergeado: **Feito**. Altere só a célula de Status (e Responsável); o resto do doc fica como está.
- Task nova descoberta durante o trabalho entra na tabela do tema certo em vez de ficar só na conversa.
- Sem o conector Claude Docs ou sem acesso de edição: liste no corpo do PR as tasks (`#`) que ele conclui, para quem tiver acesso marcar.

## Claude Code no time

- `.claude/settings.json` (versionado) traz as permissões comuns, bloqueia leitura do `.env` e tem um hook que roda `php -l` em todo `.php` editado; erro de sintaxe volta para o Claude corrigir. Preferências pessoais vão em `.claude/settings.local.json` / `CLAUDE.local.md` (ignorados pelo git).
- Skills do projeto em `.claude/skills/`: `/smoke` (sobe o servidor e testa as rotas principais) e `/pr` (lint, commit, push, PR e atualização do backlog).
