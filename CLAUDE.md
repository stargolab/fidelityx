# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projeto

FidelityX: SaaS de fidelidade para lojistas locais. PHP 8.1+ sem framework (MVC manual), MySQL/MariaDB via PDO, dependências Composer de produção: `vlucas/phpdotenv` e `chillerlan/php-qrcode` (QR do cartaz). Código, comentários, mensagens e commits em português (comentários em minúsculas e sem acento, no estilo existente).

**Estado: só desenvolvimento, nada em produção ainda.** Mudanças de schema e de comportamento podem ser feitas sem período de transição nem compatibilidade com dados de produção; ainda assim, mudança de schema vem com migration em `database/migrations/` para os bancos locais do time.

## Comandos

```bash
composer install                       # dependencias
composer dump-autoload                 # obrigatorio apos criar classe nova em src/ ou mudar autoload "files"
php -S localhost:8000 -t public/       # servidor local (document root = public/)
find src views public tests -name "*.php" -exec php -l {} \;   # checagem de sintaxe
composer test                          # phpunit (unidade + integração + fluxo); recria o banco fidelityx_test
composer test -- --testsuite Unit      # só os testes sem banco
tsc                                    # compila src/ts -> public/js (tsconfig.json)
```

Banco: `mysql -u root -p < database/schema.sql` em instalação nova. Banco já existente: rode, em ordem e uma vez cada, as migrations de `database/migrations/` que ainda não rodou (`001_mvp`, `002_rate_limit`, `003_lgpd`, `004_estorno`); o `schema.sql` sempre reflete o estado final. Credenciais em `.env` (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`; `APP_URL` opcional: endereço público usado no QR do cartaz, vazio usa o host da requisição). No ambiente local do autor o PHP e o MySQL vêm do XAMPP (`C:\xampp\mysql\bin\mysql.exe`).

**Testes (PHPUnit 10.5)** em `tests/`: `Unit` (validators, sem banco), `Integration` (models e `RateLimiter` contra o banco) e `Feature` (fluxo completo por HTTP: o teste sobe um `php -S` próprio numa porta livre e usa cookie + `_csrf` como o navegador). O bootstrap **apaga e recria** o banco `fidelityx_test` a partir do `schema.sql` (o `phpunit.xml` força esse nome e o bootstrap recusa nome que não termine em `_test`); credenciais vêm do `.env` local ou das variáveis de ambiente no CI. Cada teste começa com as tabelas vazias (`DatabaseTestCase`). Regra nova de negócio ou bug corrigido = teste junto. O GitHub Actions (`.github/workflows/ci.yml`) roda lint + PHPUnit em PHP 8.1 e 8.3 com MySQL 8 em todo PR e push na `main`. O MySQL do XAMPP roda sem `sql_mode` estrito (trunca texto longo sem erro), o do CI é estrito: não escreva teste que dependa disso.

## Arquitetura

**Roteamento** — tudo entra por `public/index.php` com `?url=dominio/acao`. Um `switch` no domínio (`merchant`, `customer`, mais `home` e `privacy` sem ação) instancia o controller e um `match` na ação chama o método `render*()`. Rota nova = novo braço no `match` + método no controller. O `index.php` abre a conexão com o banco antes de rotear, então sem MySQL toda rota devolve 503.

**Padrão de controller** — cada rota tem um `renderX()` público: no GET chama `View::render(...)`, no POST delega para um `handleX()` privado. Todo `handle*` começa com `Csrf::verify()` (aborta com 403). Rotas privadas começam com `$merchantId = $this->authGuard()`; o `merchant_id` vem **sempre da sessão**, nunca do formulário, e todo model filtra por ele (isolamento entre lojas).

**Post/Redirect/Get com códigos de flash** — handlers terminam com `redirect('rota', ['error' => 'codigo'])` ou `['success' => 'codigo']` (`redirect()` tem tipo `never`, faz `exit`). `views/partials/flash.php` traduz o código via um mapa fixo; **código novo precisa ser adicionado nesse mapa**, senão nada aparece. Nunca exibir o valor cru da query string.

**Views** — PHP puro em `views/`, recebem variáveis via `extract()` do `View::render($view, $data)`. Toda saída passa por `e()`. Links e actions usam `url('rota', [...])`; formulários POST incluem `<?= Csrf::field() ?>`. Páginas do painel incluem `partials/merchant-header.php` / `merchant-footer.php` (definir `$title` antes). Helpers globais (`e`, `redirect`, `url`, `format_phone`, `format_datetime`) ficam em `src/Support/helpers.php`, carregado pelo autoload `files` do Composer.

**Fuso horário** — `app_timezone()` (`APP_TIMEZONE` do `.env`, padrão `America/Sao_Paulo`) é aplicado no PHP pelo `index.php` e pelo `tests/bootstrap.php`, e o `Database` faz `SET time_zone` com o mesmo offset em toda conexão. Não dependa do fuso do `php.ini` nem do servidor MySQL.

**Sessão do lojista** — o `authGuard` confere a cada requisição se a conta ainda existe e está `active` (desativada encerra a sessão na hora, com `conta_inativa`), expira a sessão parada há mais de `SessionGuard::IDLE_SECONDS` (8 h) e atualiza nome da loja e `last_seen`. A sessão usa `use_strict_mode` (id inventado não é aceito), `cookie_secure` quando a requisição é HTTPS e `gc_maxlifetime` igual ao limite de inatividade.

**Proteções de toda requisição** — o `index.php` chama `RequestGuard` antes de rotear: cabeçalhos de segurança (`X-Frame-Options: DENY`, `frame-ancestors 'none'`, `nosniff`, `Referrer-Policy: same-origin`, sem `X-Powered-By`) e parâmetros em formato de lista (`campo[]=`) viram texto vazio, então nenhum `(string)` de `$_GET`/`$_POST` gera warning. O logout é POST com CSRF (o "Sair" do menu é um formulário). Os testes de fluxo rodam com `display_errors` ligado (`tests/Support/router.php`): qualquer warning aparece no HTML e quebra o teste.

**Erros** — `ErrorController::handle($code)` renderiza `views/errors/{code}.php` (fallback `default.php`). Exceções não tratadas são logadas e viram 500 pelo `set_exception_handler` do `index.php`; detalhes técnicos vão só para `error_log`.

**Modelo de dados** (detalhes em `docs/db/schema-explanation.md`):
- `customers` é **só o telefone**, global e único (só dígitos, 10–11, via `PhoneValidator::sanitize`); o mesmo cliente pode ter cartão em várias lojas.
- `loyalty_cards` = par (merchant, customer) único. Guarda o que o cliente deu **a esta loja** (`customer_name`, `consent_at`, `consent_version`), o saldo (`current_points`, `total_accumulated`) e `anonymized_at`. `findOrCreate` usa `ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)` e nunca troca nome nem consentimento de cartão existente.
- **LGPD (regra da task 11, `docs/adr/002-lgpd-dados-por-loja.md`)**: nenhuma tela de uma loja mostra dado vindo de outra. Telefone sem cartão nesta loja vai sempre para o cadastro rápido, seja novo ou de outra loja: nunca crie caminho que diferencie os dois. Consentimento é gravado no cadastro com `Privacy::VERSION` (mudou o texto de `views/privacy.php` de forma relevante, troque a versão). Exclusão = `LoyaltyCardModel::anonymize` (cartão fica só para os relatórios, telefone some quando não há mais cartão).
- `points_log` é o histórico (`earn`/`redeem`/`reversal`, `reward_id` nos resgates, `reverses_id` nos estornos). Toda mudança de saldo passa por `LoyaltyCardModel::addPoints` / `redeem` / `reverse`, que atualizam o cartão e gravam o log na mesma transação; `redeem` usa `SELECT ... FOR UPDATE` para impedir gasto duplo. Não altere saldo fora desses métodos.
- `merchants` guarda CPF **ou** CNPJ (normalizados, UNIQUE). Validação só por dígito verificador, sem API externa (ver `docs/adr/001-documents-validation.md`).

**Área pública** — a home (`HomeController`, view `views/home.php`, `public/css/home.css`) apresenta o produto e leva ao cadastro; lojista logado vai direto ao painel. `customer/balance&loja=CODIGO` consulta o saldo pelo telefone sem login, **só na loja do código** (`merchants.public_code`, impresso no cartaz e embutido no QR), com limite por IP (5/min → 429) e exibindo só o primeiro nome do cartão daquela loja. Sem código válido, a página pede o código (nunca lista lojas). `privacy` é a política de privacidade (rascunho pendente de revisão jurídica).

**Limite de tentativas** — `App\Support\RateLimiter` conta tentativas na tabela `rate_limit_hits` (chave guardada só como hash SHA-256), então o limite sobrevive a apagar o cookie. Usos: login (5 erros em 15 min por e-mail ou IP → 429) e consulta pública (5/min por IP). O IP vem de `REMOTE_ADDR`; atrás de proxy/load balancer isso precisa ser revisto.

## Convenções

- Commits no padrão `tipo(escopo): descrição` (`feat`, `fix`, `refactor`, `docs`, `test`, `chore`); branches `tipo/nome` com o mesmo tipo do commit (ex.: `feat/logout`, `fix/csrf-token`). Nunca commitar direto na `main`: tudo entra por PR, e o PR referencia a issue com `close #N` quando houver.
- SQL sempre com prepared statements; o PDO usa `ATTR_EMULATE_PREPARES = false`, então o mesmo placeholder não pode aparecer duas vezes na query (use `:p1`, `:p2`).
- Máscaras de telefone e CPF/CNPJ ficam em `src/ts/masks.ts` (`data-mask="phone"` / `"document"` no input), compiladas com `tsc` para `public/js/masks.js`, que é versionado: rode `tsc` ao mexer no `.ts`. É só apresentação; o back-end continua limpando os dígitos.

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
