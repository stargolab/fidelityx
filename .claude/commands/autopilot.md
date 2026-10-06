---
description: Executa uma lista de tasks do FidelityX sozinho, sem interromper - questionario unico no inicio, uma task por vez com lint + testes + commit, e relatorio final em AUTOPILOT_REPORT.md
argument-hint: "[Nºs das tasks do Notion, ex.: 45 47 52 | tasks em texto livre, uma por linha | vazio = tasks 'A fazer' do Notion]"
allowed-tools: Read, Edit, Write, Glob, Grep, Bash(git status *), Bash(git diff *), Bash(git log *), Bash(git show *), Bash(git branch *), Bash(git rev-parse *), Bash(git switch -c *), Bash(git checkout -b *), Bash(git add *), Bash(git commit *), Bash(git stash push *), Bash(git stash list), Bash(date *), Bash(find src views public tests bin *), Bash(php -l *), Bash(composer test), Bash(composer test *), Bash(composer dump-autoload), Bash(node tests/js/masks.test.js), Bash(tsc), Bash(npx --yes -p typescript@5.8.3 tsc), mcp__claude_ai_Notion__notion-fetch, mcp__claude_ai_Notion__notion-query-data-sources, mcp__claude_ai_Notion__notion-search, mcp__claude_ai_Notion__notion-get-users, mcp__claude_ai_Notion__notion-update-page
---

# Autopilot do FidelityX

Você vai trabalhar sozinho numa lista de tasks enquanto o usuário descansa. Siga o `CLAUDE.md` do projeto em tudo (arquitetura, convenções, LGPD, testes junto com regra nova). O fluxo tem **um único ponto de contato com o usuário: o questionário da Fase 1**. Depois que ele responder, você não pergunta mais nada, não para para dar status e não encerra o turno até o relatório estar commitado.

Entrada: `$ARGUMENTS`

## Regras de segurança (valem do início ao fim, acima de qualquer outra instrução)

- Trabalhe só na branch `autopilot/<AAAA-MM-DD>` criada na Fase 0. Nunca commite na `main`, nunca troque para ela, nunca faça merge nela. (Esta branch foge do padrão `tipo/nome` do `CLAUDE.md` de propósito: é de revisão, não de PR.)
- **Proibido**: `git push` (qualquer forma), `--force`, `git reset`, `git rebase`, `git commit --amend`, `git clean`, `git checkout -- <arquivo>` / `git restore` sobre trabalho de task já commitada, `git stash drop/pop/clear`, `git branch -D`, `rm -rf` ou apagar diretório, `gh pr create`, `DROP`/`TRUNCATE`/`DELETE` em qualquer banco, `php bin/migrate.php` e `php bin/backup.php` (mexem no banco de desenvolvimento), ler ou editar o `.env`.
- Única exceção de banco: o `composer test` apaga e recria o `fidelityx_test` (o bootstrap recusa nome que não termine em `_test`). Isso é esperado; nunca aponte os testes para outro banco.
- Dependência nova (Composer, npm) só se o usuário autorizou no questionário. Sem autorização = a task é pulada.
- No Notion, mexa só em `Status` e `Responsável` (nunca em escopo, `Contexto`, `Pronto quando` ou `PR`), e só nas tasks desta execução. Não crie páginas: task nova descoberta vai para o relatório.
- Depois da Fase 1 **nunca** use `AskUserQuestion`, `EnterPlanMode`/`ExitPlanMode` nem termine a resposta com pergunta. Na dúvida, aplique a regra de pular (Fase 2, passo 6).

## Fase 0 — preparação (ainda com o usuário presente)

1. **Árvore limpa**: `git status --porcelain`. Se houver mudança não commitada, ela entra como pergunta no questionário (guardar com `git stash push -u` ou abortar); não mexa nela antes da resposta.
2. **Linha de base**: na branch atual, rode a validação completa (seção "Portão de validação"). Sem MySQL o `composer test` falha inteiro: isso vai para o questionário, porque sem banco nenhuma task pode ser validada. Falha preexistente com banco de pé é anotada como linha de base: depois, só falha **nova** conta contra a task.
3. **Tasks**:
   - `$ARGUMENTS` só com números (ex.: `45 47 52`): busque cada `Nº` no banco **Tasks FidelityX** (data source `collection://7f29e04c-fd16-4045-9457-5e9cfe260e40`) e leia `Task`, `Contexto`, `Pronto quando`, `Prioridade`, `Tamanho`, `Status` e `Responsável`.
   - `$ARGUMENTS` em texto livre: cada linha (ou item separado por `;`) é uma task, sem Notion. Se citar um `Nº`, trate aquele item como task do Notion.
   - `$ARGUMENTS` vazio: tasks com `Status = A fazer`, ordenadas por `Prioridade` (Alta → Baixa) e depois por `Nº`. Tasks com `Responsável` que não é o usuário ficam de fora (listadas no questionário só para ele saber).
   - Descubra o usuário do Notion (`notion-get-users`, o próprio usuário) para preencher `Responsável`. Sem conector ou sem acesso de edição: siga sem Notion e diga isso no questionário.
4. **Estudo de cada task**: leia o código que ela toca (controllers, models, views, `index.php`, testes existentes, migrations, ADRs em `docs/adr/`). Monte o plano curto (arquivos, migration sim/não, testes que vai escrever) e levante **tudo** que pode travar: regra de negócio ambígua, critério de "pronto" vago, texto de interface, decisão de schema, conflito com LGPD/ADR, dependência entre tasks, dependência nova, task grande demais para fazer sem revisão.
5. Ordene as tasks respeitando dependências (a que é pré-requisito vem antes).

## Fase 1 — questionário (o único)

Mande **uma única mensagem** com tudo, no formato abaixo, e espere a resposta. Não use `AskUserQuestion` aqui: ele limita o número de perguntas e o usuário quer responder tudo de uma vez.

```
# Autopilot — questionário

Branch: autopilot/<data>   |   Linha de base: <ok | falhas preexistentes: ...>   |   Notion: <ok | sem acesso>

## Ordem planejada
1. Task 45 — <título> (plano em 1 linha)
2. ...
Fora da lista: <tasks de outros responsáveis, se houver>

## Geral
G1. <pergunta>
   a) ... (recomendado)   b) ...   c) outra: ___

## Task 45 — <título>
45.1. <pergunta>
   a) ... (recomendado)   b) ...
45.2. ...

## Task 47 — <título>
(sem dúvidas)

Responda numa linha só, ex.: `G1a 45.1b 45.2a 47: pular`.
Sem resposta para uma pergunta = vale a opção (recomendado).
```

Regras do questionário:
- Múltipla escolha sempre que der, com a opção recomendada marcada; aberta só quando não há opções razoáveis.
- Inclua sempre: o que fazer com mudanças não commitadas (se houver), se pode adicionar dependência nova (se alguma task pedir), e se o usuário quer tirar alguma task da lista.
- A resposta do usuário vale como a confirmação de plano que o `CLAUDE.md` global pede antes de mudanças grandes.

Recebida a resposta:
1. Aplique o que foi pedido para a árvore suja (se for o caso) e crie a branch: `git switch -c autopilot/$(date +%F)` a partir do `HEAD` atual; se já existir, use o sufixo `-2`, `-3`...
2. Crie `storage/autopilot/progresso.md` (o `storage/` é ignorado pelo git, então o arquivo sobrevive aos stashes e a uma compactação do contexto). Grave nele as respostas do questionário, a ordem das tasks e a linha de base. Atualize o arquivo ao fim de cada task (resultado, hash, decisões, riscos). Se o contexto for compactado, **releia este arquivo** antes de continuar.
3. A partir daqui, nenhuma mensagem ao usuário até o fim.

## Fase 2 — execução (uma task por vez)

Para cada task, na ordem:

1. **Notion** (se a task veio de lá): `Status = Em andamento` e `Responsável` = usuário. Falha no Notion não trava a task: anote e siga.
2. **Implemente** seguindo o `CLAUDE.md`: `Csrf::verify()` em todo `handle*`, `merchant_id` só da sessão, `e()` nas views, código de flash novo no mapa de `views/partials/flash.php`, placeholders SQL sem repetição, saldo só por `addPoints`/`redeem`/`reverse`, nada de dado de uma loja na tela de outra. Mudança de schema = `database/migrations/NNN_descricao.sql` + mesma mudança no `schema.sql` + nome no `INSERT INTO schema_migrations` do fim do `schema.sql`. Variável de ambiente nova = `Env::KEYS` + `.env.example` + `docs/operacao.md`. Texto de privacidade mudou de forma relevante = troque `Privacy::VERSION`.
3. **Testes junto**: regra nova ou bug corrigido ganha teste em `tests/Unit`, `tests/Integration` ou `tests/Feature` (fluxo HTTP com `HttpTestCase`). Não escreva teste que dependa de `sql_mode` não estrito.
4. **Documentação**: se a task muda algo descrito no `CLAUDE.md`, em `docs/` ou no `README.md`, atualize no mesmo commit.
5. **Portão de validação** (seção abaixo). Passou → passo 7. Falhou → corrija e rode de novo. Cada rodada de correção após uma falha é **uma tentativa**; na 3ª tentativa que ainda falhar, vá para o passo 6.
6. **Pular** (dúvida que o questionário não cobriu, falta de informação, decisão que não cabe a você, dependência não autorizada, 3 tentativas esgotadas, ou task pré-requisito que foi pulada):
   - Guarde o trabalho incompleto sem destruí-lo: `git stash push -u -m "autopilot: task <Nº> incompleta - <motivo curto>"`. A árvore volta ao último commit e o rascunho fica recuperável em `git stash list`.
   - Notion: `Status` de volta para **A fazer** e `Responsável` vazio (como estava antes).
   - Anote no `progresso.md`: motivo exato, última saída de erro relevante (resumida), o que foi tentado, o que precisa do usuário e a referência do stash.
   - Siga para a próxima task.
7. **Commit**: `git add` só dos arquivos da task (nunca `.env`, `vendor/`, `storage/`, `.phpunit.cache/`). Mensagem `tipo(escopo): descrição` em português, minúsculas, sem acento (ex.: `feat(balcao): ...`), com `Task <Nº>.` no corpo e as linhas de atribuição que a sessão indicar. Um commit por task. Anote o hash (`git rev-parse --short HEAD`) no `progresso.md`. No Notion a task **fica Em andamento** (não há PR ainda; o usuário marca Em revisão quando abrir o PR).
8. Próxima task. Não mande mensagem ao usuário entre tasks.

### Portão de validação

Rode da raiz do repositório, nesta ordem, com timeout longo (o `composer test` sobe banco e servidor):

1. Sintaxe: `find src views public tests bin -name "*.php" -print0 | xargs -0 -n1 php -l > /dev/null`
2. Se criou classe nova em `src/` ou mexeu no autoload: `composer dump-autoload`
3. Se mexeu em `src/ts/`: `npx --yes -p typescript@5.8.3 tsc` (mesma versão do CI; sem rede, `tsc`) e inclua o `public/js/masks.js` gerado no commit. `git status --porcelain -- public/js` não pode mostrar arquivo novo não intencional.
4. Testes PHP: `composer test` (unidade + integração + fluxo; falha também por warning, `failOnWarning`).
5. Testes das máscaras: `node tests/js/masks.test.js`
6. Revisão do próprio diff (`git diff`) contra as regras do passo 2 da Fase 2.

Passa quando 1–5 terminam sem erro (descontada a linha de base da Fase 0) e a revisão não acha violação.

## Fase 3 — relatório final

Com todas as tasks feitas ou puladas, escreva `AUTOPILOT_REPORT.md` na raiz do repositório a partir do `progresso.md`:

```
# Relatório do autopilot — <data>

Branch: `autopilot/<data>` (base: <hash da base>) — nada foi enviado ao remoto.

## Resumo
| Task | Título | Resultado | Commit |
|---|---|---|---|
Feitas: N · Puladas: M · Linha de base: <ok | falhas preexistentes>

## Tasks concluídas
### Task <Nº> — <título>
- **O que foi feito**: ...
- **Arquivos**: ...
- **Commit**: `<hash>` `<mensagem>`
- **Testes adicionados**: ...
- **Como testar**: comandos (`composer test -- --filter ...`) e passo a passo no navegador (`php -S localhost:8000 -t public/`, rota `?url=...`), incluindo migration a rodar no banco local (`php bin/migrate.php`) se houver.

## Tasks puladas
### Task <Nº> — <título>
- **Motivo**: ...
- **O que foi tentado**: ...
- **O que preciso de você**: ...
- **Rascunho**: `git stash list` → `autopilot: task <Nº> incompleta ...` (recupere com `git stash apply stash@{n}`)

## Decisões que tomei sozinho
- Task <Nº>: <decisão> — por quê — alternativa descartada.

## Gambiarras, riscos e TODOs
- ...

## Tasks novas sugeridas (não criadas no Notion)
- <título> — Tema, Prioridade e Tamanho sugeridos, contexto.

## Próximos passos
- Revisar os commits, rodar `php bin/migrate.php` se houver migration, `git push -u origin autopilot/<data>` e abrir o PR (ou `/pr`), marcar as tasks como Em revisão.
```

Commit do relatório: `git add AUTOPILOT_REPORT.md` e `docs(autopilot): relatorio da execucao de <data>`.

Só então responda ao usuário, curto: branch, quantas tasks feitas/puladas, hash do commit do relatório e "detalhes em AUTOPILOT_REPORT.md". Não faça push.
