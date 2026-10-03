---
name: pr
description: Fecha o trabalho atual do FidelityX - lint, commit no padrão do projeto, push, PR para a main e atualização do backlog.
disable-model-invocation: true
---

# Abrir PR no FidelityX

Siga as convenções do `CLAUDE.md`. Argumento opcional: número da issue (`/pr 8`).

1. **Branch**: se estiver na `main`, crie `tipo/nome` (mesmo tipo do commit) antes de commitar. Nunca commite na `main`.
2. **Lint**: `php -l` em todos os `.php` alterados (`git diff --name-only main...HEAD` + working tree). Pare se houver erro.
3. **Revisão rápida**: confira o diff contra as regras do `CLAUDE.md` (CSRF em todo `handle*`, `merchant_id` só da sessão, `e()` nas views, código de flash novo registrado em `views/partials/flash.php`, placeholders SQL não repetidos). Aponte violações antes de seguir.
4. **Commit**: `tipo(escopo): descrição` em português, minúsculas. Commits pequenos e coesos; não inclua `.env`, `vendor/` nem arquivos temporários.
5. **Push e PR**: `git push -u origin <branch>` e `gh pr create --base main`. Título no padrão do commit; corpo com resumo, como validar (rodou `/smoke`?) e `close #N` se houver issue.
6. **Backlog**: leia o artifact do backlog (URL no `CLAUDE.md`) e marque as tasks concluídas com o link do PR, republicando na mesma URL. Sem acesso de edição, liste as tasks no corpo do PR.
7. Responda com o link do PR e o que foi marcado no backlog.
