---
name: pr
description: Fecha o trabalho atual do FidelityX - lint, commit no padrão do projeto, push, PR para a main e atualização do backlog no Notion.
disable-model-invocation: true
---

# Abrir PR no FidelityX

Siga as convenções do `CLAUDE.md`. Argumento opcional: número da issue (`/pr 8`).

1. **Branch**: se estiver na `main`, crie `tipo/nome` (mesmo tipo do commit) antes de commitar. Nunca commite na `main`.
2. **Lint**: `php -l` em todos os `.php` alterados (`git diff --name-only main...HEAD` + working tree). Pare se houver erro.
3. **Revisão rápida**: confira o diff contra as regras do `CLAUDE.md` (CSRF em todo `handle*`, `merchant_id` só da sessão, `e()` nas views, código de flash novo registrado em `views/partials/flash.php`, placeholders SQL não repetidos). Aponte violações antes de seguir.
4. **Commit**: `tipo(escopo): descrição` em português, minúsculas. Commits pequenos e coesos; não inclua `.env`, `vendor/` nem arquivos temporários.
5. **Push e PR**: `git push -u origin <branch>` e `gh pr create --base main`. Título no padrão do commit; corpo com resumo, como validar (rodou `/smoke`?) e `close #N` se houver issue.
6. **Backlog**: pelo conector do Notion, no banco **Tasks FidelityX** indicado no `CLAUDE.md`, ache as tasks que o PR entrega pelo `Nº`, mude o Status para **Em revisão** e preencha o campo `PR` com o link. Cite os números das tasks no corpo do PR (ex.: "Tasks 45 e 47"). Sem o conector ou sem acesso de edição, deixe só a citação no PR.
7. Responda com o link do PR e o que foi marcado no backlog.
