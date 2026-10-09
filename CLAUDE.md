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
find src views public tests bin -name "*.php" -exec php -l {} \;   # checagem de sintaxe
composer test                          # phpunit (unidade + integração + fluxo); recria o banco fidelityx_test
composer test -- --testsuite Unit      # só os testes sem banco
tsc                                    # compila src/ts -> public/js (tsconfig.json)
php bin/migrate.php                    # roda as migrations pendentes (status | baseline NNN)
php bin/backup.php                     # backup do banco (mysqldump .sql.gz em BACKUP_DIR)
php bin/expire-points.php              # vence o saldo parado alem do prazo de cada loja (--dry-run so mostra)
docker compose up -d --build           # aplicacao + mysql em docker (docs/operacao.md)
```

Banco: `mysql -u root -p < database/schema.sql` em instalação nova. Banco já existente: `php bin/migrate.php` roda as migrations de `database/migrations/` que ainda não rodaram (banco anterior ao controle: antes, uma vez, `php bin/migrate.php baseline 003`); o `schema.sql` sempre reflete o estado final. Credenciais em `.env` ou em variáveis de ambiente (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`; `APP_URL` opcional: endereço público usado no QR do cartaz, vazio usa o host da requisição). No ambiente local do autor o PHP e o MySQL vêm do XAMPP (`C:\xampp\mysql\bin\mysql.exe`).

**Testes (PHPUnit 10.5)** em `tests/`: `Unit` (validators, sem banco), `Integration` (models e `RateLimiter` contra o banco) e `Feature` (fluxo completo por HTTP: o teste sobe um `php -S` próprio numa porta livre e usa cookie + `_csrf` como o navegador). O bootstrap **apaga e recria** o banco `fidelityx_test` a partir do `schema.sql` (o `phpunit.xml` força esse nome e o bootstrap recusa nome que não termine em `_test`); credenciais vêm do `.env` local ou das variáveis de ambiente no CI. Cada teste começa com as tabelas vazias (`DatabaseTestCase`). Regra nova de negócio ou bug corrigido = teste junto. O GitHub Actions (`.github/workflows/ci.yml`) roda lint + PHPUnit em PHP 8.1 e 8.3 com MySQL 8 em todo PR e push na `main`, **sem `.env`** (só variáveis de ambiente, como em produção), e um job à parte recompila o `masks.ts` e falha se o `masks.js` versionado não bater. O MySQL do XAMPP roda sem `sql_mode` estrito (trunca texto longo sem erro), o do CI é estrito: não escreva teste que dependa disso.

## Arquitetura

**Roteamento** — tudo entra por `public/index.php` com `?url=dominio/acao`. Um `switch` no domínio (`merchant`, `customer`, mais `home` e `privacy` sem ação) instancia o controller e um `match` na ação chama o método `render*()`. Rota nova = novo braço no `match` + método no controller. O `index.php` abre a conexão com o banco antes de rotear, então sem MySQL (ou sem as variáveis obrigatórias do banco) toda rota devolve 503.

**Configuração** — `App\Support\Env::load()` (chamado pelo `index.php`, pelo bootstrap dos testes e pelos scripts de `bin/`) põe tudo no `$_ENV`: variável de ambiente real primeiro, e o `.env`, se existir, só completa o que falta (`safeLoad`: a aplicação sobe sem o arquivo). Variável nova entra em `Env::KEYS` e no `.env.example`; leia com `Env::get()` ou `$_ENV`, nunca com `getenv()` direto.

**Padrão de controller** — cada rota tem um `renderX()` público: no GET chama `View::render(...)`, no POST delega para um `handleX()` privado. Todo `handle*` começa com `Csrf::verify()` (aborta com 403). Rotas privadas começam com `$merchantId = $this->authGuard()`; o `merchant_id` vem **sempre da sessão**, nunca do formulário, e todo model filtra por ele (isolamento entre lojas).

**Post/Redirect/Get com códigos de flash** — handlers terminam com `redirect('rota', ['error' => 'codigo'])` ou `['success' => 'codigo']` (`redirect()` tem tipo `never`, faz `exit`). `views/partials/flash.php` traduz o código via um mapa fixo; **código novo precisa ser adicionado nesse mapa**, senão nada aparece. Erro que aponta um campo também entra no mapa de `field_error_attr()` (`helpers.php`), e o `<input>` leva `<?= field_error_attr('nome_do_campo') ?>`: o campo sai com `aria-invalid` (borda de erro) ligado à mensagem. Nunca exibir o valor cru da query string.

**Views** — PHP puro em `views/`, recebem variáveis via `extract()` do `View::render($view, $data)`. Toda saída passa por `e()`. Links e actions usam `url('rota', [...])`; formulários POST incluem `<?= Csrf::field() ?>`. Páginas do painel incluem `partials/merchant-header.php` / `merchant-footer.php` (definir `$title` antes). Helpers globais (`e`, `redirect`, `url`, `format_phone`, `format_document`, `format_datetime`) ficam em `src/Support/helpers.php`, carregado pelo autoload `files` do Composer.

**Fuso horário** — `app_timezone()` (`APP_TIMEZONE` do `.env`, padrão `America/Sao_Paulo`) é aplicado no PHP pelo `index.php` e pelo `tests/bootstrap.php`, e o `Database` faz `SET time_zone` com o mesmo offset em toda conexão. Não dependa do fuso do `php.ini` nem do servidor MySQL.

**Sessão do lojista** — o `authGuard` confere a cada requisição se a conta ainda existe e está `active` (desativada encerra a sessão na hora, com `conta_inativa`), expira a sessão parada há mais de `SessionGuard::IDLE_SECONDS` (8 h) e atualiza nome da loja e `last_seen`. O login guarda na sessão `password_sig` (`SessionGuard::passwordSignature`) e o `authGuard` compara com a senha do banco: senha trocada derruba as sessões abertas com a antiga. Quem altera `password_hash` precisa atualizar o `password_sig` da própria sessão (ver `handlePasswordChange`), senão se desloga.

**Perfil** — `merchant/profile` tem dois formulários na mesma rota: dados da loja (padrão) e troca de senha (`action=password`, exige a senha atual, com limite de erros no bucket `password_change`). E-mail e CPF/CNPJ aparecem só para leitura e nunca são lidos do POST. A sessão usa `use_strict_mode` (id inventado não é aceito), `cookie_secure` quando a requisição é HTTPS e `gc_maxlifetime` igual ao limite de inatividade.

**Proteções de toda requisição** — o `index.php` chama `RequestGuard` antes de rotear: cabeçalhos de segurança (`X-Frame-Options: DENY`, `frame-ancestors 'none'`, `nosniff`, `Referrer-Policy: same-origin`, sem `X-Powered-By`) e parâmetros em formato de lista (`campo[]=`) viram texto vazio, então nenhum `(string)` de `$_GET`/`$_POST` gera warning. O logout é POST com CSRF (o "Sair" do menu é um formulário). Os testes de fluxo rodam com `display_errors` ligado (`tests/Support/router.php`): qualquer warning aparece no HTML e quebra o teste.

**Erros** — `ErrorController::handle($code)` renderiza `views/errors/{code}.php` (fallback `default.php`) e entrega às views o botão de voltar (`$backUrl`, `$backLabel`): lojista logado volta ao painel, qualquer outra pessoa à home pública; página de erro nova usa essas variáveis, nunca link fixo. Exceções não tratadas e erros fatais são logados e viram 500 pelo `App\Support\ErrorLog` (registrado no `index.php`): uma linha por erro com um código curto, que a página 500 também mostra. Do contexto da requisição o log leva só método e rota, nunca query string, corpo ou argumentos (têm telefone e senha). Detalhes técnicos vão só para `error_log`, cujo destino é `LOG_FILE` ou o do `php.ini`.

**Modelo de dados** (detalhes em `docs/db/schema-explanation.md`):
- `customers` é **só o telefone**, global e único (só dígitos, 10–11, via `PhoneValidator::sanitize`); o mesmo cliente pode ter cartão em várias lojas.
- `loyalty_cards` = par (merchant, customer) único. Guarda o que o cliente deu **a esta loja** (`customer_name`, `consent_at`, `consent_version`), o saldo (`current_points`, `total_accumulated`) e `anonymized_at`. `findOrCreate` usa `ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)` e nunca troca nome nem consentimento de cartão existente.
- **LGPD (regra da task 11, `docs/adr/002-lgpd-dados-por-loja.md`)**: nenhuma tela de uma loja mostra dado vindo de outra. Telefone sem cartão nesta loja vai sempre para o cadastro rápido, seja novo ou de outra loja: nunca crie caminho que diferencie os dois. Consentimento é gravado no cadastro com `Privacy::VERSION` (mudou o texto de `views/privacy.php` de forma relevante, troque a versão). Exclusão = `LoyaltyCardModel::anonymize` (cartão fica só para os relatórios, telefone some quando não há mais cartão).
- `points_log` é o histórico (`earn`/`redeem`/`reversal`/`expire`, `reward_id` nos resgates, `reverses_id` nos estornos). Toda mudança de saldo passa por `LoyaltyCardModel::addPoints` / `redeem` / `reverse`, que atualizam o cartão e gravam o log na mesma transação; `redeem` usa `SELECT ... FOR UPDATE` para impedir gasto duplo. Não altere saldo fora desses métodos.
- **Validade dos pontos (task 31)**: cada loja tem `merchants.points_expiry_months` (padrão 12, `NULL` = não vence, escolhido na tela Regra de pontos). O saldo inteiro vence depois desse tempo sem movimentação, contado de `loyalty_cards.last_use_at`; a data não é gravada, é sempre calculada. Quem vence é `LoyaltyCardModel::expire` / `expireIdle` (linha `expire` no `points_log`), chamado pelo `bin/expire-points.php`, que precisa estar agendado (`docs/operacao.md`).
- `merchants` guarda CPF **ou** CNPJ (normalizados, UNIQUE). Validação só por dígito verificador, sem API externa (ver `docs/adr/001-documents-validation.md`).

**Área pública** — a home (`HomeController`, view `views/home.php`, `public/css/home.css`) apresenta o produto e leva ao cadastro; lojista logado vai direto ao painel. `customer/balance&loja=CODIGO` consulta o saldo pelo telefone sem login, **só na loja do código** (`merchants.public_code`, impresso no cartaz e embutido no QR), com limite por IP (5/min → 429) e exibindo só o primeiro nome do cartão daquela loja. Sem código válido, a página pede o código (nunca lista lojas). `privacy` é a política de privacidade (rascunho pendente de revisão jurídica).

**Limite de tentativas** — `App\Support\RateLimiter` conta tentativas na tabela `rate_limit_hits` (chave guardada só como hash SHA-256), então o limite sobrevive a apagar o cookie. Usos: login (em 15 min, 5 erros do mesmo e-mail vindos do mesmo IP ou 20 erros do mesmo IP em qualquer conta → 429), troca de senha (5 senhas atuais erradas por conta) e consulta pública (5/min por IP). **O login nunca bloqueia só pelo e-mail**: isso deixaria qualquer pessoa trancar a dona da conta errando a senha de propósito (task 38). Nos buckets a chave do IP é `RateLimiter::clientKey()`, que em IPv6 vale pela rede /64 (um assinante tem o bloco inteiro e trocaria de endereço a cada tentativa). O endereço exato vem de `RateLimiter::clientIp()` (`App\Support\ClientIp`): é o `REMOTE_ADDR`, e o `X-Forwarded-For` só vale quando a requisição chega de um proxy listado em `TRUSTED_PROXIES` (o mesmo vale para o `X-Forwarded-Proto` em `RequestGuard::isHttps()`). Nunca leia esses cabeçalhos direto.

**Migrations** — `App\Support\Migrator` + `bin/migrate.php` registram em `schema_migrations` o nome de cada arquivo aplicado (o controle é pelo nome, não pelo maior número: migration de outro PR com número menor ainda roda). Migration nova = arquivo `NNN_descricao.sql` + a mesma mudança no `schema.sql` + o nome no `INSERT INTO schema_migrations` do fim do `schema.sql` (o `MigratorTest` cobra).

**Produção** — `Dockerfile` + `docker-compose.yml` (Apache, PHP 8.3, MySQL 8; `docker/php.ini` desliga `display_errors`), backup por `bin/backup.php` (`App\Support\Backup`) e o guia em `docs/operacao.md`. Mudou variável de ambiente, comando de operação ou volume: atualize esse guia.

## Convenções

- Commits no padrão `tipo(escopo): descrição` (`feat`, `fix`, `refactor`, `docs`, `test`, `chore`); branches `tipo/nome` com o mesmo tipo do commit (ex.: `feat/logout`, `fix/csrf-token`). Nunca commitar direto na `main`: tudo entra por PR, e o PR referencia a issue com `close #N` quando houver.
- SQL sempre com prepared statements; o PDO usa `ATTR_EMULATE_PREPARES = false`, então o mesmo placeholder não pode aparecer duas vezes na query (use `:p1`, `:p2`).
- Máscaras de telefone e CPF/CNPJ ficam em `src/ts/masks.ts` (`data-mask="phone"` / `"document"` no input), compiladas com `tsc` para `public/js/masks.js`, que é versionado: rode `tsc` ao mexer no `.ts`. É só apresentação; o back-end continua limpando os dígitos.
- `src/ts/forms.ts` (compilado para `public/js/forms.js`, também versionado) vale para todo `<form>`: no envio, o botão clicado ganha a classe `is-loading` (`components.css`) e um segundo envio do mesmo formulário é barrado. Página nova com formulário carrega `/js/forms.js`; o botão não recebe `disabled`.

## Backlog

A fonte oficial das tasks é o banco **Tasks FidelityX** no Notion da StargoLab, na página **FidelityX**: https://app.notion.com/p/3f102726fca98194b5b5f608ffc824fe

Leia e edite pelo conector do Notion (`fetch`, `query`, `update-page`, `create-pages`), usando o data source `collection://7f29e04c-fd16-4045-9457-5e9cfe260e40`. O antigo doc do Claude Docs (FidelityX — Backlog pós-MVP) é só histórico: não atualize mais.

- Cada task é uma linha com `Nº`, `Task`, `Status` (A fazer → Em andamento → Em revisão → Feito), `Prioridade` (Alta/Média/Baixa), `Tamanho` (P/M/G), `Tema` (os 7 temas, de "1. Atendimento no balcão" a "7. Administração e negócio"), `Responsável` (pessoa do Notion), `PR` (link), `Contexto` e `Pronto quando`. A task é citada pelo `Nº` (ex.: task 45), inclusive em commits, PRs e ADRs.
- Ao começar uma task: leia `Contexto` e `Pronto quando`, coloque o responsável e mude o Status para **Em andamento**.
- PR aberto: **Em revisão**, com o link no campo `PR`. PR mergeado: **Feito**. Mexa só em Status, Responsável e PR; o escopo da task só muda com o time de acordo.
- Task nova descoberta durante o trabalho entra no banco (próximo `Nº` livre, com Tema, Prioridade, Tamanho, Contexto e Pronto quando) em vez de ficar só na conversa.
- Sem o conector do Notion ou sem acesso de edição: liste no corpo do PR as tasks (`Nº`) que ele conclui, para quem tiver acesso marcar.

## Claude Code no time

- `.claude/settings.json` (versionado) traz as permissões comuns, bloqueia leitura do `.env` e tem um hook que roda `php -l` em todo `.php` editado; erro de sintaxe volta para o Claude corrigir. Preferências pessoais vão em `.claude/settings.local.json` / `CLAUDE.local.md` (ignorados pelo git).
- Skills do projeto em `.claude/skills/`: `/smoke` (sobe o servidor e testa as rotas principais) e `/pr` (lint, commit, push, PR e atualização do backlog).
