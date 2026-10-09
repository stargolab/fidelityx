## Problema encontrado

A validação de CPF/CNPJ baseada apenas na lógica matemática (dígitos verificadores) garante que o documento é estruturalmente válido, mas não assegura que ele exista de fato ou esteja vinculado a uma pessoa/empresa real.

Para validar existência, situação cadastral e titularidade, seria necessário integrar com serviços externos (APIs da Receita ou provedores privados). Isso implica custos, aumento de latência e maior complexidade na aplicação, o que não é adequado para o escopo atual do projeto.

## Decisão

Optou-se por não integrar APIs externas neste momento.

## Solução adotada

- [x] Sanitizar o input (remoção de máscara e caracteres inválidos)
- [x] Validar CPF/CNPJ usando dígitos verificadores
- [x] Rejeitar sequências inválidas (ex: 11111111111)
- [x] Armazenar apenas o valor normalizado (somente números; no CNPJ alfanumérico, números e letras maiúsculas)
- [x] Responsabilizar o usuário pela veracidade dos dados informados
- [x] Verificação de duplicidade no banco (UNIQUE)

## Atualização: CNPJ alfanumérico (task 57)

A partir de julho de 2026 a Receita Federal emite CNPJ com letras: as 12 primeiras posições (raiz + ordem) aceitam `0-9` e `A-Z`, e os 2 dígitos verificadores continuam numéricos. Os CNPJs só com números já emitidos continuam valendo, e o cálculo antigo é um caso particular do novo.

- **Cálculo**: cada caractere vale o seu código ASCII menos 48 (`0`–`9` = 0–9, `A` = 17 … `Z` = 42), com os mesmos pesos e o mesmo módulo 11 de antes. Exemplo oficial: `12.ABC.345/01DE-35`.
- **Normalização** (`DocumentValidator::normalize`): maiúsculas, só `0-9A-Z`. CPF continua só com números (11 dígitos); CNPJ = 12 alfanuméricos + 2 dígitos.
- **Banco**: a coluna `merchants.cnpj VARCHAR(14)` já comporta o formato, sem migration. O valor é gravado em maiúsculas; a collation `utf8mb4_unicode_ci` também trata `abc` e `ABC` como iguais no `UNIQUE`.
- **Tela**: a máscara (`src/ts/masks.ts`) aceita letras e as põe em maiúsculas; o campo do cadastro deixou de usar `inputmode="numeric"` (o teclado numérico do celular não tem letras). `format_document` usa a mesma máscara para os dois formatos.

## Possíveis melhorias futuras

- [ ] Integração com API externa para validação real (Receita/terceiros)
- [ ] Validação adicional via e-mail ou outro mecanismo de confirmação