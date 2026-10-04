# FidelityX

> **Plataforma de Fidelização para Comerciantes Locais** — Sistema SaaS moderno, desacoplado e escalável, construído com arquitetura MVC manual em PHP.

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql)
![Architecture](https://img.shields.io/badge/Architecture-MVC-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Beta-orange?style=flat-square)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)

---

## 📋 Visão Geral

**FidelityX** é uma plataforma SaaS (Software-as-a-Service) que capacita comerciantes locais a implementar programas de fidelização robustos e inteligentes. Desenvolvido com foco em **escalabilidade**, **segurança** e **arquitetura limpa**, o sistema oferece uma base sólida para crescimento futuro.

Recentemente refatorado para adotar o padrão **MVC com separação clara de responsabilidades**, FidelityX demonstra as melhores práticas de engenharia de software: prepared statements, validação de entrada, hash de senhas e uma estrutura que facilita testes e manutenção.

---

## ✨ Features

### Funcionalidades

**Painel do lojista**
- ✅ **Cadastro de Lojistas** — Registro completo com validação de documentos (CPF/CNPJ), e-mail, telefone e confirmação de senha; máscaras de telefone e documento
- ✅ **Autenticação Segura** — Login com hash BCRYPT, renovação do ID de sessão, logout, bloqueio de contas inativas e limite de 5 erros em 15 min
- ✅ **Atendimento pelo telefone** — A home é um único campo de telefone: cliente da loja vai direto para a tela do cliente; qualquer outro telefone vai para o cadastro rápido
- ✅ **Tela do cliente** — Nome e saldo no topo, progresso até o próximo prêmio, lançar pontos (atalhos +1/+5/+10) e resgatar os prêmios que o saldo já paga
- ✅ **Cadastro rápido** — Telefone já preenchido, só o nome e o consentimento do cliente (gravado com data e versão do texto)
- ✅ **Prêmios** — Criar, editar, ativar/desativar e excluir (prêmio já resgatado só é desativado)
- ✅ **Resgate** — Confere o saldo e debita em transação, sem risco de gastar o mesmo ponto duas vezes
- ✅ **Clientes** — Lista paginada com busca por nome ou telefone; extrato de pontos de cada cliente
- ✅ **Relatórios** — Clientes, pontos emitidos, resgates, pontos em circulação e histórico paginado
- ✅ **Cartaz com QR code** — Pronto para imprimir, leva o cliente à consulta de saldo da loja
- ✅ **Primeiros passos** — Guia para a loja nova até o primeiro prêmio e os primeiros pontos
- ✅ **Funciona do celular ao PC** — Layout mobile-first (360 px a 1440 px), menu de celular e alvos de toque de 44 px

**Área pública**
- ✅ **Página inicial** — Apresenta o produto e leva ao cadastro da loja e à consulta de saldo
- ✅ **Consulta de Saldo** — Pelo QR do cartaz (ou código da loja) e o telefone: saldo naquela loja, prêmios e quanto falta para o próximo (sem login, limite de 5 consultas por minuto por IP)
- ✅ **Privacidade (LGPD)** — Cada loja só vê o que o cliente informou a ela; consentimento registrado; exclusão dos dados a pedido do cliente; política de privacidade publicada (rascunho pendente de revisão jurídica). Detalhes em [`docs/adr/002-lgpd-dados-por-loja.md`](docs/adr/002-lgpd-dados-por-loja.md)

### Rotas

| Rota (`index.php?url=`) | Acesso | Descrição |
|---|---|---|
| `home` (ou raiz) · `privacy` | público | Página inicial e política de privacidade |
| `merchant/register` · `merchant/login` · `merchant/logout` | público | Conta do lojista |
| `merchant/dashboard` | lojista | Home: guia de primeiros passos e busca pelo telefone (`?phone=`) |
| `merchant/customer?phone=` | lojista | Tela do cliente: lançar pontos, resgatar, registrar consentimento e excluir dados |
| `merchant/customer-new?phone=` | lojista | Cadastro rápido de cliente nesta loja |
| `merchant/statement?phone=` | lojista | Extrato de pontos do cliente |
| `merchant/customers?q=&page=` | lojista | Clientes e saldos, com busca e paginação |
| `merchant/rewards` · `merchant/reward-edit?id=` | lojista | Catálogo de prêmios e edição |
| `merchant/reports?page=` | lojista | Indicadores e histórico de movimentações |
| `merchant/poster` | lojista | Cartaz com QR code e código da loja |
| `customer/balance?loja=` | público | Consulta de saldo pelo telefone, na loja do código |

---

## 🛠️ Tech Stack

| Componente | Tecnologia | Versão |
|---|---|---|
| **Linguagem** | PHP | 8.1+ |
| **Banco de Dados** | MySQL / MariaDB | 8.0+ / 10.4+ |
| **ORM** | PDO (prepared statements) | nativa |
| **Autenticação** | BCRYPT | nativa |
| **Dependency Manager** | Composer | 2.0+ |
| **Charset** | UTF-8mb4 | unicode_ci |
| **Engine** | InnoDB | transacional |

---

## 🏗️ Arquitetura

### Padrão MVC Desacoplado

FidelityX implementa uma arquitetura MVC **manual** que prioriza clareza, testabilidade e escalabilidade:

```
src/
├── Controllers/       → Orquestração de requisições HTTP
├── Models/            → Lógica de persistência (BD)
├── Validators/        → Validação de dados (regras de negócio)
└── Support/           → View, CSRF, RateLimiter e helpers (e(), redirect(), url())
```

### Fluxo de Requisição

```
[HTTP Request]
      ↓
[Controller] → Recebe entrada, delega validação
      ↓
[Validator] → Valida regras de negócio
      ↓
[Model] → Persiste dados com prepared statements
      ↓
[View] → Renderiza resposta
```

### Exemplo: Cadastro de Lojista

**MerchantController.php** orquestra o fluxo:
- `handleRegister()` → captura entrada, sanitiza dados
- `DocumentValidator::isValid()` → valida CPF/CNPJ
- `MerchantModel::create()` → persiste com prepared statements

```php
// Validação rigorosa
if (!DocumentValidator::isValid($document)) {
    redirect('merchant/register', ['error' => 'documento_invalido']);
}

// Persistência segura com prepared statements
$data = [
    ':email' => $email,
    ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
    // ... outros campos
];
$this->merchantModel->create($data);
```

### Validação de Documentos

O módulo `DocumentValidator` implementa algoritmos de validação official:
- **CPF**: Validação de dígitos verificadores com regra do módulo 11
- **CNPJ**: Validação completa com pesos decrescentes

```php
DocumentValidator::isValid($document); // true/false
```

---

## 🚀 Getting Started

### Pré-requisitos

- PHP 8.1 ou superior
- MySQL 8.0 ou superior
- Composer 2.0+
- Git

### Instalação

#### 1. Clone o Repositório

```bash
git clone https://github.com/stargolab/fidelityx.git
cd fidelityx
```

#### 2. Instale as Dependências

```bash
composer install
```

#### 3. Configure o Banco de Dados

```bash
# Crie o banco e importe o schema
mysql -u root -p < database/schema.sql
```

O arquivo [database/schema.sql](database/schema.sql) cria automaticamente:
- Database `fidelityx`
- Tabelas `merchants`, `customers`, `loyalty_cards`, `rewards`, `points_log` e `rate_limit_hits`
- Índices e chaves estrangeiras
- Charset UTF-8mb4

> **Já tinha o banco criado antes?** Rode uma vez cada migration que ainda não aplicou, em ordem:
> `mysql -u root -p fidelityx < database/migrations/001_mvp.sql` (bancos anteriores ao MVP)
> `mysql -u root -p fidelityx < database/migrations/002_rate_limit.sql` (limite de tentativas de login e da consulta pública)
> `mysql -u root -p fidelityx < database/migrations/003_lgpd.sql` (dados do cliente por loja, consentimento e código público da loja)
> `mysql -u root -p fidelityx < database/migrations/004_estorno.sql` (estorno de lançamento de pontos)
>
> Detalhes das tabelas em [docs/db/schema-explanation.md](docs/db/schema-explanation.md).

#### 4. Configure Variáveis de Ambiente

```bash
# Copie o arquivo de exemplo
cp .env.example .env

# Edite com suas credenciais
nano .env
```

`.env`:
```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=fidelityx
DB_USER=root
DB_PASS=sua_senha
```

Opcional: `APP_TIMEZONE=America/Sao_Paulo` (fuso da aplicação; vazio usa esse) e `APP_URL=https://seu-dominio` (endereço público do sistema, usado no QR code do cartaz; vazio usa o host da requisição).

> Depois de atualizar o projeto (`git pull`), rode `composer dump-autoload` para registrar arquivos novos do autoload.

#### 5. Inicie o Servidor Local

```bash
# PHP Built-in Server
php -S localhost:8000 -t public/

# Ou com Nginx/Apache (configure document root para ./public/)
```

Acesse: `http://localhost:8000` (redireciona para o login do lojista). A consulta pública do cliente fica em `http://localhost:8000/index.php?url=customer/balance`.

---

## 📚 Estrutura de Diretórios

```
fidelityx/
├── database/
│   ├── schema.sql              # Schema do banco de dados
│   └── migrations/             # Alterações para bancos já existentes
├── docs/
│   ├── adr/                    # Architecture Decision Records
│   └── db/                     # Documentação de banco de dados
├── public/
│   ├── index.php               # Entry point
│   ├── assets/
│   ├── css/                    # Estilos (SCSS compilado)
│   └── js/                     # Frontend (TypeScript compilado)
├── src/
│   ├── Controllers/            # Orquestração de requisições
│   ├── Models/                 # Camada de dados
│   ├── Validators/             # Validação de regras de negócio
│   ├── Support/                # View, CSRF, RateLimiter e helpers
│   └── Database.php            # Singleton de conexão PDO
├── views/
│   ├── auth/                   # Templates de autenticação
│   ├── merchant/               # Painel do lojista
│   ├── customer/               # Consulta pública do cliente
│   ├── partials/               # Cabeçalho, navegação e mensagens
│   └── errors/                 # Templates de erro (400, 404, 500...)
├── composer.json               # Dependências PHP
├── tsconfig.json               # Configuração TypeScript
└── README.md                   # Este arquivo
```

---

## 🔧 Desenvolvimento

### Testes

PHPUnit 10.5, em três suítes:

| Suíte | O que cobre | Banco |
|---|---|---|
| `Unit` | `DocumentValidator` (CPF/CNPJ) e `PhoneValidator` | não |
| `Integration` | `LoyaltyCardModel` (pontos, resgate, transação), `CustomerModel` (cadastro simultâneo) e `RateLimiter` | sim |
| `Feature` | fluxo completo por HTTP: cadastro da loja, busca pelo telefone, cadastro rápido, lançar e resgatar, cliente de outra loja, isolamento entre lojas, CSRF e limites de tentativas | sim |

```bash
composer test                       # todas as suítes
composer test -- --testsuite Unit   # só as que não usam banco

# verifica a sintaxe de todos os arquivos PHP
find src views public tests -name "*.php" -exec php -l {} \;
```

Os testes com banco usam o banco **`fidelityx_test`**, que é apagado e recriado a partir do `schema.sql` a cada rodada (as credenciais vêm do seu `.env`; o usuário precisa poder criar banco). O banco do `.env` nunca é tocado.

O **GitHub Actions** roda lint + testes em PHP 8.1 e 8.3 com MySQL 8 em todo PR e todo push na `main`.

### Estrutura de Controllers

Cada rota tem um `render*()`: no GET ele mostra a view; no POST ele delega para o `handle*()` correspondente. Rotas privadas começam com `authGuard()`, que devolve o id do lojista logado (sempre da sessão, nunca do formulário).

```php
public function renderCustomer() {
    $merchantId = $this->authGuard();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $this->handleCustomer($merchantId); // Csrf::verify() + validação + model
        return;
    }

    // ... busca o cartão do cliente desta loja pelo telefone
    View::render('merchant/customer', ['card' => $card, 'rewards' => $rewards]);
}
```

---

## 🔐 Segurança

- ✅ **Prepared Statements** — Proteção contra SQL Injection
- ✅ **BCRYPT Hashing** — Senhas armazenadas com hash seguro
- ✅ **Input Sanitization** — `filter_input()` para todos os dados
- ✅ **Validação de Negócio** — Documentos validados com algoritmos oficiais
- ✅ **CSRF** — Token por sessão em todos os formulários POST
- ✅ **XSS** — Toda saída nas views passa por `e()` (`htmlspecialchars`)
- ✅ **Sessão** — Cookie `HttpOnly` + `SameSite=Lax` e `session_regenerate_id()` no login
- ✅ **Limite de tentativas** — Login bloqueia com 429 após 5 erros em 15 min por e-mail ou IP; consulta pública limitada a 5/min por IP. Contagem no banco (sobrevive a apagar o cookie), com e-mail e IP guardados só como hash SHA-256
- ✅ **Isolamento entre lojas** — Consultas filtram pelo `merchant_id` da sessão
- ✅ **Erros** — Detalhes técnicos vão para o log; o usuário vê só as páginas de erro

---

## 📊 Roadmap

### MVP (atual)

- [x] Dashboard de Lojistas
- [x] Sistema de Pontos (earn/redeem) com catálogo de prêmios
- [x] Consulta pública de saldo

### Pós-MVP — Onda 1 (prioridade alta)

- [x] Home com busca pelo telefone, tela do cliente e cadastro rápido
- [x] Limite de tentativas no login e limite da consulta pública por IP
- [x] Cadastro simultâneo do mesmo cliente tratado sem erro 500
- [x] Paleta de cores em variáveis CSS (contraste WCAG AA)
- [x] Layout responsivo e mobile-first
- [x] Testes automatizados e CI
- [ ] Docker e configuração de produção

### Próximos passos

- [ ] Análise estática (PHPStan) e padronização de código (PHP-CS-Fixer)
- [ ] Edição de perfil do lojista e recuperação de senha
- [ ] Máscaras de input no front-end (TypeScript)
- [ ] Planos Free/Pro com limites
- [ ] Área do cliente com login
- [ ] API REST para integrações

### Futuro

- [ ] Analytics e insights
- [ ] Integração com gateways de pagamento
- [ ] Webhooks para eventos de loja
- [ ] Mobile App

---

## 🤝 Contribuindo

Contribuições são bem-vindas! Para reportar bugs ou sugerir features:

1. **Issues**: [GitHub Issues](https://github.com/stargolab/fidelityx/issues)
2. **Pull Requests**: Siga o padrão de branch `feature/nome-da-feature`

### Padrão de Commits

```
feat: adiciona nova feature
fix: corrige bug
refactor: refatora código
docs: atualiza documentação
test: adiciona/atualiza testes
```

---

## 📄 Documentação

- [ADR - Decisões Arquiteturais](docs/adr/001-documents-validation.md)
- [Schema do Banco de Dados](docs/db/schema-explanation.md)

---

## 📝 Licença

MIT License.

---

## 👤 Autor

**Emanuel Sousa** — [@sousa7tz](https://github.com/sousa7tz)

---

## 📞 Suporte

- **Email**: suporte@fidelityx.com
- **Docs**: [fidelityx.dev](https://fidelityx.dev)
- **Issues**: [GitHub Issues](https://github.com/stargolab/fidelityx/issues)

---

**Última atualização**: Setembro 2026 | **Versão**: 0.2.0-mvp