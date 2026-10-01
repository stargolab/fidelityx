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

### Funcionalidades do MVP

**Painel do lojista**
- ✅ **Cadastro de Lojistas** — Registro completo com validação de documentos (CPF/CNPJ), e-mail, telefone e confirmação de senha
- ✅ **Autenticação Segura** — Login com hash BCRYPT, renovação do ID de sessão, logout e bloqueio de contas inativas
- ✅ **Lançamento de Pontos** — Busca o cliente pelo telefone e cadastra na hora se ele for novo
- ✅ **Catálogo de Prêmios** — Cada loja cadastra prêmios com custo em pontos e ativa/desativa quando quiser
- ✅ **Resgate** — Confere o saldo e debita em transação, sem risco de gastar o mesmo ponto duas vezes
- ✅ **Dashboard e Clientes** — Indicadores, últimas movimentações e lista de clientes com saldo

**Área pública do cliente**
- ✅ **Consulta de Saldo** — O cliente informa o telefone e vê os pontos em cada loja e os prêmios disponíveis (sem login, com limite de consultas)

### Rotas

| Rota (`index.php?url=`) | Acesso | Descrição |
|---|---|---|
| `merchant/register` · `merchant/login` · `merchant/logout` | público | Conta do lojista |
| `merchant/dashboard` | lojista | Indicadores e últimas movimentações |
| `merchant/score` | lojista | Lançar pontos |
| `merchant/rewards` | lojista | Catálogo de prêmios |
| `merchant/redeem` | lojista | Resgatar prêmio |
| `merchant/customers` | lojista | Clientes e saldos |
| `customer/balance` | público | Consulta de saldo pelo telefone |

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
└── Support/           → View, CSRF e helpers (e(), redirect(), url())
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
git clone https://github.com/sousa7tz/fidelityx.git
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
- Tabelas `merchants`, `customers`, `loyalty_cards`, `rewards` e `points_log`
- Índices e chaves estrangeiras
- Charset UTF-8mb4

> **Já tinha o banco criado antes do MVP?** Rode uma vez a migration:
> `mysql -u root -p fidelityx < database/migrations/001_mvp.sql`
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
│   ├── Support/                # View, CSRF e helpers
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

### Checagem de sintaxe

Ainda não há testes automatizados nem ferramentas de qualidade configuradas (PHPUnit, PHPStan e PHP-CS-Fixer estão no roadmap). Por enquanto:

```bash
# verifica a sintaxe de todos os arquivos PHP
find src views public -name "*.php" -exec php -l {} \;
```

### Estrutura de Controllers

Cada rota tem um `render*()`: no GET ele mostra a view; no POST ele delega para o `handle*()` correspondente. Rotas privadas começam com `authGuard()`, que devolve o id do lojista logado (sempre da sessão, nunca do formulário).

```php
public function renderScore() {
    $merchantId = $this->authGuard();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $this->handleScore($merchantId); // Csrf::verify() + validação + model
        return;
    }

    View::render('merchant/score');
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
- ✅ **Isolamento entre lojas** — Consultas filtram pelo `merchant_id` da sessão
- ✅ **Erros** — Detalhes técnicos vão para o log; o usuário vê só as páginas de erro

---

## 📊 Roadmap

### MVP (atual)

- [x] Dashboard de Lojistas
- [x] Sistema de Pontos (earn/redeem) com catálogo de prêmios
- [x] Consulta pública de saldo

### Próximos passos

- [ ] Testes automatizados (PHPUnit) e análise estática (PHPStan)
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

1. **Issues**: [GitHub Issues](https://github.com/sousa7tz/fidelityx/issues)
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
- **Issues**: [GitHub Issues](https://github.com/sousa7tz/fidelityx/issues)

---

**Última atualização**: Setembro 2026 | **Versão**: 0.2.0-mvp