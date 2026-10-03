---
name: smoke
description: Sobe o servidor PHP local e exercita as rotas principais do FidelityX com curl (cookie jar + _csrf) para validar uma mudança de ponta a ponta. Use depois de alterar controllers, models, views ou rotas.
---

# Smoke test do FidelityX

Não há testes automatizados; este é o roteiro padrão de validação. Rode tudo a partir da raiz do repositório.

## 1. Pré-checagens

- `find src views public -name "*.php" -exec php -l {} \;` sem erros.
- MySQL de pé (no ambiente do autor vem do XAMPP). Sem banco, toda rota devolve 503: avise o usuário em vez de seguir.

## 2. Servidor

Suba em background: `php -S localhost:8000 -t public/`. Ao final, **sempre** encerre o processo.

## 3. Rotas

Use um cookie jar novo por execução (no diretório temporário da sessão, nunca no repo). Antes de cada POST, faça o GET da mesma rota e extraia o `_csrf` do HTML.

| Rota (`?url=`) | Esperado |
|---|---|
| `merchant/login` (GET) | 200 com o formulário |
| `merchant/dashboard` sem sessão | redirect para `merchant/login` |
| `customer/balance` (GET) | 200 |
| `customer/balance` POST `phone` 6x em 1 min | a 6ª devolve 429 |
| `merchant/xyz` | 404 |
| POST em `merchant/login` sem `_csrf` | 403 |

Se o usuário fornecer credenciais de um lojista de teste (`email`/`password`), faça login e valide também, com a sessão: `merchant/dashboard`, `merchant/customers`, `merchant/rewards`, `merchant/score` (POST `phone`, `points`) e `merchant/redeem` (POST `phone`, `reward_id`). Nunca crie dados em banco que não seja local.

Inclua no teste as rotas tocadas pela mudança atual (veja `git diff main...HEAD --stat`).

## 4. Relatório

Tabela com rota, método, status obtido, esperado e ✅/❌. Para cada ❌, mostre o trecho relevante da resposta e o log do servidor.
