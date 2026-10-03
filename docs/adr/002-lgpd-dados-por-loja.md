# ADR 002 — LGPD: dados do cliente por loja

Status: aceita (2026-10-03) · Task 11 do backlog · Migration: `database/migrations/003_lgpd.sql`

## Problema encontrado

O cliente (`customers`) era um registro só para todas as lojas, com o nome digitado pela primeira loja que o cadastrou. Isso abria três problemas:

1. **Oráculo telefone → nome.** O cadastro de loja é aberto e gratuito, e a busca por telefone no balcão não tem limite. Ao digitar o telefone de um cliente de outra loja, o sistema mostrava o primeiro nome para confirmar; depois de confirmar, a tela do cliente mostrava o nome completo. Qualquer pessoa que criasse uma loja conseguia descobrir nomes a partir de telefones.
2. **Consulta pública revelava hábitos.** Quem soubesse o telefone de alguém via em quais lojas a pessoa tem pontos. O limite de 5 consultas por minuto por IP só atrasa uma varredura.
3. **Consentimento sem registro.** O checkbox era obrigatório, mas nada era gravado. Não havia como provar o consentimento nem como excluir os dados de um cliente.

## Decisão

| Tema | Regra |
|---|---|
| Nome entre lojas | **Cadastro por loja.** O nome fica no cartão (`loyalty_cards.customer_name`). Telefone de cliente de outra loja segue exatamente o caminho de telefone novo (cadastro rápido com nome e consentimento): a loja não descobre que o número existe em outro lugar. |
| Consulta pública | **Só a loja do código.** O QR do cartaz leva a `customer/balance&loja=CODIGO` (`merchants.public_code`). Sem código válido, a página pede o código; não lista lojas. |
| Exclusão | **Feita pelo lojista**, a pedido do cliente, na tela do cliente. |
| Consentimento | **Por loja**, com data e versão do texto (`consent_at`, `consent_version` = `App\Support\Privacy::VERSION`). |

O telefone continua global (`customers.phone`, único): é o identificador que o cliente usa em qualquer loja. Nenhuma tela de uma loja mostra dado vindo de outra.

## Como a exclusão funciona

Em uma transação (`LoyaltyCardModel::anonymize`):

- o cartão perde nome, vínculo com o telefone (`customer_id = NULL`), consentimento e saldo, e ganha `anonymized_at`;
- as descrições de ganho no `points_log` (texto livre do lojista, que pode conter dado pessoal) viram "Cliente excluído"; os resgates ficam como estão ("Resgate: <prêmio>" não identifica ninguém);
- o telefone (`customers`) é apagado quando não sobra cartão dele em nenhuma loja.

O cartão anonimizado continua existindo só para os relatórios: os totais de pontos emitidos e resgates não mudam, e o histórico mostra "Cliente excluído". O mesmo telefone pode ser cadastrado de novo depois, do zero.

## Consequências

- **Uma digitação a mais no balcão** para cliente que já existe em outra loja (nome no cadastro rápido). Isso desfaz o "só confirma o nome" da task 1, de propósito.
- **Cartões anteriores à migration** ficam com `consent_at = NULL`. A tela do cliente mostra um aviso para o lojista perguntar e registrar o consentimento na próxima visita.
- **Cartazes impressos antes da mudança** apontam para a consulta sem código, que continua abrindo e pede o código da loja. Vale reimprimir.
- **Texto de privacidade** (`views/privacy.php`) é um rascunho do time de produto e está marcado como pendente de revisão jurídica. Precisa de aprovação antes de produção. Mudou o texto de forma relevante: troque `Privacy::VERSION`.

## Alternativas descartadas

- **Só o primeiro nome para outras lojas:** continua revelando que o telefone existe e o primeiro nome.
- **Nome compartilhado, avisado no consentimento:** mantém o oráculo.
- **Consulta com verificação por SMS:** depende de provedor e custo (mesma decisão da task 29). Pode voltar junto com ela.
- **Exclusão pelo próprio cliente na consulta:** sem verificar o telefone, qualquer um apagaria os dados de outra pessoa.
