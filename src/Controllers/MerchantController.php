<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\EmailVerificationModel;
use App\Models\LoyaltyCardModel;
use App\Mail\MailerFactory;
use App\Mail\Message;
use App\Models\MerchantModel;
use App\Models\PasswordResetModel;
use App\Models\PointsLogModel;
use App\Models\RewardModel;
use App\Support\Csrf;
use App\Support\LoginGuard;
use App\Support\Money;
use App\Support\Paginator;
use App\Support\PasswordPolicy;
use App\Support\Period;
use App\Support\Plan;
use App\Support\Csv;
use App\Support\Privacy;
use App\Support\QrSvg;
use App\Support\RateLimiter;
use App\Support\RequestGuard;
use App\Support\RewardProgress;
use App\Support\SessionGuard;
use App\Support\View;
use App\Validators\DocumentValidator;
use App\Validators\PhoneValidator;

class MerchantController {
    // opcoes aceitas no cadastro (as mesmas exibidas nos <select> da view)
    public const CATEGORIES = [
        'alimentacao' => 'Alimentação / Bebidas',
        'beleza'      => 'Beleza & Estética',
        'saude'       => 'Saúde / Bem-estar',
        'varejo'      => 'Varejo / Comércio',
        'servicos'    => 'Serviços Gerais',
        'outros'      => 'Outros',
    ];

    public const STATES = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
        'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    private const MAX_POINTS_PER_ENTRY = 10000;
    private const PER_PAGE = 20;
    // a exportacao le o banco em blocos, pra loja grande nao carregar tudo na memoria de uma vez
    private const EXPORT_CHUNK = 500;
    // confirmacao do lancamento: o botao Desfazer fica UNDO_SECONDS na tela; o aviso so aparece
    // se o lancamento tiver ate UNDO_BANNER_SECONDS (o estorno em si vale por 24 h, pelo extrato)
    private const UNDO_SECONDS = 30;
    private const UNDO_BANNER_SECONDS = 120;
    private const RECENT_CUSTOMERS = 5;
    private const MAX_POINTS_RULE_CENTS = 100000000;
    // prazos de validade dos pontos que a loja pode escolher, em meses sem movimentacao (task 31)
    public const EXPIRY_MONTHS_OPTIONS = [3, 6, 12, 18, 24, 36];
    private const MAX_LOGIN_FAILURES = 5;         // mesmo e-mail + mesmo ip
    private const MAX_LOGIN_FAILURES_PER_IP = 20; // mesmo ip, somando todas as contas
    private const LOGIN_WINDOW_SECONDS = 900;
    // senha atual errada na troca de senha: mesmo limite do login, contado por conta
    private const MAX_PASSWORD_FAILURES = 5;
    private const PASSWORD_WINDOW_SECONDS = 900;
    // pedidos de link de senha (task 21): por ip (quem testa varios e-mails) e por e-mail (nao lotar a caixa de ninguem)
    private const MAX_RESET_REQUESTS_PER_IP = 10;
    private const MAX_RESET_REQUESTS_PER_EMAIL = 3;
    private const RESET_WINDOW_SECONDS = 3600;
    // reenvio do link de confirmacao do e-mail (task 50), por conta
    private const MAX_VERIFY_RESENDS = 3;

    private $db;
    private $merchantModel;
    // lojista logado, preenchido pelo authGuard
    private ?array $merchant = null;

    public function __construct($db) {
        $this->db = $db;
        $this->merchantModel = new MerchantModel($this->db);
    }

    // RENDERS (publicas) -------------------------------------------------
    public function renderRegister() {
            // a array $_SERVER guarda tudo que vem na requisição.
            // se for POST, é porque o usuário clicou o botão (está tentando registrar).
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->handleRegister(); // então, joga pro método de registro.
                return;
            }
            // se o método não for POST, vai ser GET, só carrega ou recarrega a página normalmente.
            View::render('auth/merchant/register', [
                'categories' => self::CATEGORIES,
                'states'     => self::STATES,
            ]);
    }

    public function renderLogin() {
        // explicação geral no método renderRegister().
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleLogin();
            return;
        }

        // quem ja esta logado vai direto pro painel
        if (isset($_SESSION['merchant_id'])) {
            redirect('merchant/dashboard');
        }

        View::render('auth/merchant/login');
    }

    // esqueci a senha (task 21): pede o e-mail e manda o link. a resposta e sempre a mesma, exista
    // o e-mail ou nao: a tela nao revela quais e-mails sao lojas.
    public function renderForgot() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleForgot();
            return;
        }

        View::render('auth/merchant/forgot');
    }

    private function handleForgot() {
        Csrf::verify();

        $email = trim((string)($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('merchant/forgot', ['error' => 'email_invalido']);
        }

        $limiter = new RateLimiter($this->db);
        $ip = RateLimiter::clientKey();
        if ($limiter->tooMany('password_reset_ip', $ip, self::MAX_RESET_REQUESTS_PER_IP, self::RESET_WINDOW_SECONDS)
            || $limiter->tooMany('password_reset_email', $email, self::MAX_RESET_REQUESTS_PER_EMAIL, self::RESET_WINDOW_SECONDS)) {
            redirect('merchant/forgot', ['error' => 'muitos_pedidos']);
        }
        $limiter->hit('password_reset_ip', $ip);
        $limiter->hit('password_reset_email', $email);

        // conta desativada nao recebe link (nao conseguiria entrar mesmo)
        $merchant = $this->merchantModel->findByEmail($email);
        if ($merchant && $merchant['status'] === 'active') {
            $token = (new PasswordResetModel($this->db))->create((int)$merchant['id']);
            $link = $this->publicBaseUrl() . url('merchant/reset', ['token' => $token]);
            MailerFactory::fromEnv()->send(new Message(
                $email,
                'FidelityX: criar uma nova senha',
                "Olá, {$merchant['owner_name']}.\n\n"
                . "Recebemos um pedido para criar uma nova senha no painel da loja {$merchant['store_name']}.\n"
                . "Abra o link abaixo em até 1 hora (ele só funciona uma vez):\n\n$link\n\n"
                . "Se não foi você, ignore este e-mail: a senha atual continua valendo."
            ));
        }

        redirect('merchant/forgot', ['success' => 'reset_enviado']);
    }

    // link do e-mail: escolhe a senha nova. o token vem na url (GET) e volta num campo escondido (POST).
    public function renderReset() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleReset();
            return;
        }

        $token = (string)($_GET['token'] ?? '');
        if (!(new PasswordResetModel($this->db))->isValid($token)) {
            redirect('merchant/forgot', ['error' => 'link_invalido']);
        }

        View::render('auth/merchant/reset', [
            'token'       => $token,
            'minPassword' => PasswordPolicy::MIN_BYTES,
            'maxPassword' => PasswordPolicy::MAX_BYTES,
        ]);
    }

    private function handleReset() {
        Csrf::verify();

        $token = (string)($_POST['token'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');
        $resets = new PasswordResetModel($this->db);

        if (!$resets->isValid($token)) {
            redirect('merchant/forgot', ['error' => 'link_invalido']);
        }

        $passwordProblem = PasswordPolicy::problem($new);
        if ($passwordProblem !== null) {
            redirect('merchant/reset', ['token' => $token, 'error' => $passwordProblem]);
        }
        if (!hash_equals($new, $confirm)) {
            redirect('merchant/reset', ['token' => $token, 'error' => 'senhas_diferentes']);
        }

        // a senha nova derruba as sessoes abertas com a antiga (password_sig, ver authGuard)
        if ($resets->resetPassword($token, password_hash($new, PASSWORD_BCRYPT)) === null) {
            redirect('merchant/forgot', ['error' => 'link_invalido']);
        }

        redirect('merchant/login', ['success' => 'senha_redefinida']);
    }

    // RENDERS (privadas, exigem login) -----------------------------------

    // home do lojista: um campo de telefone que decide o destino.
    // cliente desta loja -> tela do cliente; qualquer outro -> cadastro rapido.
    // telefone novo e telefone de cliente de outra loja seguem EXATAMENTE o mesmo caminho:
    // a loja nao descobre se o numero existe em outro lugar nem o nome dado la (LGPD, docs/adr/002).
    public function renderDashboard() {
        $merchantId = $this->authGuard();

        if (!isset($_GET['phone'])) {
            View::render('merchant/dashboard', [
                'steps'  => $this->onboardingSteps($merchantId),
                'recent' => $this->recentCustomers($merchantId),
            ]);
            return;
        }

        $phone = PhoneValidator::sanitize($_GET['phone']);
        if (!PhoneValidator::isValid($phone)) {
            redirect('merchant/dashboard', ['error' => 'telefone_invalido']);
        }

        if ((new LoyaltyCardModel($this->db))->findByMerchantAndPhone($merchantId, $phone)) {
            redirect('merchant/customer', ['phone' => $phone]);
        }

        redirect('merchant/customer-new', ['phone' => $phone]);
    }

    // plano da loja logada (task 32). so depois do authGuard.
    private function plan(): string {
        return Plan::normalize($this->merchant['plan'] ?? null);
    }

    // true se o plano da loja ainda deixa cadastrar mais um cliente.
    // o limite e conferido na hora do cadastro: duas abas ao mesmo tempo podem passar por 1, e tudo bem.
    private function canAddCustomer(int $merchantId): bool {
        $customers = (new LoyaltyCardModel($this->db))->countByMerchant($merchantId);
        return Plan::allowsOneMore($this->plan(), Plan::CUSTOMERS, $customers);
    }

    // true se o plano da loja ainda deixa ter mais um premio ativo (premio novo ja nasce ativo)
    private function canActivateReward(int $merchantId, RewardModel $rewardModel): bool {
        return Plan::allowsOneMore($this->plan(), Plan::ACTIVE_REWARDS, $rewardModel->countActiveByMerchant($merchantId));
    }

    // quanto do plano a loja ja usou, pro cartao "Plano" das telas (partials/plan-usage.php).
    // cada item: rotulo, quanto usa, o limite (null = sem limite) e a porcentagem pra barra (0 a 100).
    private function planUsage(int $merchantId): array {
        $plan = $this->plan();
        $used = [
            Plan::CUSTOMERS      => ['clientes', (new LoyaltyCardModel($this->db))->countByMerchant($merchantId)],
            Plan::ACTIVE_REWARDS => ['prêmios ativos', (new RewardModel($this->db))->countActiveByMerchant($merchantId)],
        ];

        $items = [];
        foreach ($used as $resource => [$label, $count]) {
            $limit = Plan::limit($plan, $resource);
            $items[$resource] = [
                'label'   => $label,
                'used'    => $count,
                'limit'   => $limit,
                'percent' => $limit === null ? null : (int)min(100, round($count * 100 / $limit)),
                'full'    => !Plan::allowsOneMore($plan, $resource, $count),
            ];
        }

        return ['plan' => $plan, 'label' => Plan::label($plan), 'items' => $items];
    }

    // regra de pontos da loja logada, em centavos por ponto (null = sem regra). so depois do authGuard.
    private function pointsRule(): ?int {
        $rule = $this->merchant['points_rule_cents'] ?? null;
        return $rule === null ? null : (int)$rule;
    }

    // regra de pontos pelo valor da compra (task 6): "a cada R$ X em compras, 1 ponto"
    public function renderPointsRule() {
        $merchantId = $this->authGuard();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePointsRule($merchantId);
            return;
        }

        View::render('merchant/points-rule', [
            'ruleCents'     => $this->pointsRule(),
            'expiryMonths'  => $this->pointsExpiryMonths(),
            'expiryOptions' => self::EXPIRY_MONTHS_OPTIONS,
        ]);
    }

    // validade dos pontos da loja logada, em meses sem movimentacao (null = nao vencem). so depois do authGuard.
    private function pointsExpiryMonths(): ?int {
        $months = $this->merchant['points_expiry_months'] ?? null;
        return $months === null ? null : (int)$months;
    }

    private function handlePointsRule(int $merchantId) {
        Csrf::verify();

        // validade dos pontos (task 31): um dos prazos da lista, ou "never" pra desligar o vencimento
        if (($_POST['action'] ?? '') === 'expiry') {
            $choice = (string)($_POST['expiry_months'] ?? '');
            if ($choice !== 'never' && !in_array($choice, array_map('strval', self::EXPIRY_MONTHS_OPTIONS), true)) {
                redirect('merchant/points-rule', ['error' => 'validade_invalida']);
            }

            $this->merchantModel->updatePointsExpiry($merchantId, $choice === 'never' ? null : (int)$choice);
            redirect('merchant/points-rule', ['success' => 'validade_salva']);
        }

        if (($_POST['action'] ?? '') === 'clear') {
            $this->merchantModel->updatePointsRule($merchantId, null);
            redirect('merchant/points-rule', ['success' => 'regra_removida']);
        }

        // de R$ 0,01 (1 centavo = 1 ponto) ate R$ 1.000.000,00 por ponto
        $cents = Money::toCents($_POST['rule'] ?? '');
        if ($cents === null || $cents < 1 || $cents > self::MAX_POINTS_RULE_CENTS) {
            redirect('merchant/points-rule', ['error' => 'regra_invalida']);
        }

        $this->merchantModel->updatePointsRule($merchantId, $cents);
        redirect('merchant/points-rule', ['success' => 'regra_salva']);
    }

    // atalho da home: os ultimos clientes atendidos (lancamento, resgate ou estorno), para quem volta no mesmo dia.
    // cadastro sem nenhuma movimentacao ainda nao conta como atendimento.
    private function recentCustomers(int $merchantId): array {
        $rows = (new LoyaltyCardModel($this->db))->searchByMerchant($merchantId, '', self::RECENT_CUSTOMERS, 0);
        return array_values(array_filter($rows, fn($row) => $row['last_use_at'] !== null));
    }

    // guia de primeiros passos da home: cada passo sabe se ja foi feito pelos dados da propria loja.
    // devolve lista vazia quando tudo esta feito (a view some com o guia).
    private function onboardingSteps(int $merchantId): array {
        $steps = [
            [
                'label' => 'Cadastre seu primeiro prêmio',
                'hint'  => 'É o que o cliente vai querer conquistar com os pontos.',
                'url'   => url('merchant/rewards'),
                'cta'   => 'Cadastrar prêmio',
                'done'  => (new RewardModel($this->db))->countByMerchant($merchantId) > 0,
            ],
            [
                'label' => 'Lance os primeiros pontos',
                'hint'  => 'Digite o telefone de um cliente no campo abaixo e lance os pontos da compra.',
                'url'   => url('merchant/dashboard') . '#phone',
                'cta'   => 'Buscar cliente',
                'done'  => (new PointsLogModel($this->db))->countByMerchant($merchantId) > 0,
            ],
            [
                // opcional: lancar pontos direto continua valendo
                'label'    => 'Defina a regra de pontos',
                'hint'     => 'Ex.: a cada R$ 1,00 em compras, 1 ponto. Aí é só digitar o valor da compra.',
                'url'      => url('merchant/points-rule'),
                'cta'      => 'Definir regra',
                'done'     => $this->pointsRule() !== null,
                'optional' => true,
            ],
            [
                // nao da pra saber se imprimiu: e uma dica (opcional) que nao segura o guia na tela
                'label'    => 'Imprima o cartaz com o QR code',
                'hint'     => 'O cliente aponta o celular e consulta os pontos sozinho.',
                'url'      => url('merchant/poster'),
                'cta'      => 'Abrir cartaz',
                'done'     => false,
                'optional' => true,
            ],
        ];

        foreach ($steps as $step) {
            if (!$step['done'] && empty($step['optional'])) {
                return $steps;
            }
        }

        return [];
    }

    // cartaz para imprimir: nome da loja + qr code que leva a consulta publica de saldo.
    // o qr e gerado no servidor (svg), sem servico externo.
    public function renderPoster() {
        $merchantId = $this->authGuard();

        // o qr leva a consulta DESTA loja (a consulta so mostra o saldo da loja do codigo)
        $code = $this->merchantModel->findPublicCode($merchantId);
        $balanceUrl = $this->publicBaseUrl() . url('customer/balance', ['loja' => $code]);

        View::render('merchant/poster', [
            'balanceUrl' => $balanceUrl,
            'publicCode' => $code,
            'qrSvg'      => QrSvg::svg($balanceUrl),
        ]);
    }

    // endereco publico do sistema, para o qr apontar pro lugar certo.
    // APP_URL do .env manda (atras de proxy o host da requisicao pode nao ser o publico);
    // sem ele usa o host da requisicao.
    private function publicBaseUrl(): string {
        $configured = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)
            && in_array(parse_url($configured, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return $configured;
        }

        $scheme = RequestGuard::isHttps() ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if (!preg_match('/^[A-Za-z0-9.-]+(:\d{1,5})?$/', $host)) {
            $host = 'localhost';
        }

        return $scheme . '://' . $host;
    }



    // tela do cliente: saldo no topo, lancar pontos e resgatar sem sair dela
    public function renderCustomer() {
        $merchantId = $this->authGuard();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleCustomer($merchantId);
            return;
        }

        $phone = PhoneValidator::sanitize($_GET['phone'] ?? '');
        $card = PhoneValidator::isValid($phone)
            ? (new LoyaltyCardModel($this->db))->findByMerchantAndPhone($merchantId, $phone)
            : null;

        if (!$card) {
            redirect('merchant/dashboard', ['error' => 'cliente_nao_encontrado']);
        }

        // so os premios que o saldo ja paga; o progresso olha todos os ativos (o proximo ainda nao pago)
        $balance = (int)$card['current_points'];
        $activeRewards = (new RewardModel($this->db))->listByMerchant($merchantId, true);
        $rewards = array_values(array_filter(
            $activeRewards,
            fn($reward) => (int)$reward['points_cost'] <= $balance
        ));

        // confirmacao do lancamento que acabou de ser feito (task 5). so aparece logo depois de lancar:
        // recarregar a pagina minutos depois nao mostra de novo nem oferece Desfazer.
        $launchId = filter_var($_GET['lancamento'] ?? '', FILTER_VALIDATE_INT);
        $launch = $launchId
            ? (new PointsLogModel($this->db))->findFreshEarn($launchId, (int)$card['id'], self::UNDO_BANNER_SECONDS)
            : false;

        View::render('merchant/customer', [
            'card'        => $card,
            'rewards'     => $rewards,
            'progress'    => RewardProgress::next($balance, $activeRewards),
            'launch'      => $launch ?: null,
            'undoSeconds' => self::UNDO_SECONDS,
            'pointsRule'  => $this->pointsRule(),
        ]);
    }

    // relatorios: indicadores da loja + historico das movimentacoes, paginado, os dois pelo periodo escolhido (task 59)
    public function renderReports() {
        $merchantId = $this->authGuard();

        $period = Period::fromQuery($_GET);
        $logModel = new PointsLogModel($this->db);
        $paginator = new Paginator($logModel->countByMerchant($merchantId, $period), $_GET['page'] ?? 1, self::PER_PAGE);

        View::render('merchant/reports', [
            'stats'     => $logModel->statsByMerchant($merchantId, $period),
            'entries'   => $logModel->pageByMerchant($merchantId, $paginator->perPage, $paginator->offset(), $period),
            'paginator' => $paginator,
            'period'    => $period,
        ]);
    }

    // planilha (csv) do historico do periodo ou da lista de clientes (com a busca), so desta loja (task 59)
    public function renderExport() {
        $merchantId = $this->authGuard();
        $today = date('Y-m-d');

        if (($_GET['tipo'] ?? '') === 'clientes') {
            $search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
            $cardModel = new LoyaltyCardModel($this->db);
            Csv::sendHeaders("clientes-$today.csv");
            echo Csv::BOM . Csv::line(['Cliente', 'Telefone', 'Saldo', 'Total acumulado', 'Última visita']);
            for ($offset = 0; $rows = $cardModel->searchByMerchant($merchantId, $search, self::EXPORT_CHUNK, $offset); $offset += self::EXPORT_CHUNK) {
                foreach ($rows as $row) {
                    echo Csv::line([
                        (string)$row['name'], format_phone($row['phone']), (int)$row['current_points'],
                        (int)$row['total_accumulated'], format_datetime($row['last_use_at']),
                    ]);
                }
            }
            return;
        }

        $period = Period::fromQuery($_GET);
        $logModel = new PointsLogModel($this->db);
        Csv::sendHeaders("historico-$today.csv");
        echo Csv::BOM . Csv::line(['Data', 'Cliente', 'Telefone', 'Tipo', 'Pontos', 'Descrição']);
        for ($offset = 0; $rows = $logModel->pageByMerchant($merchantId, self::EXPORT_CHUNK, $offset, $period); $offset += self::EXPORT_CHUNK) {
            foreach ($rows as $row) {
                [$label, , $sign] = log_type_view($row['type'], $row['reversed_type'] ?? null);
                $anonymized = $row['phone'] === null;
                echo Csv::line([
                    format_datetime($row['created_at']),
                    $anonymized ? 'Cliente excluído' : (string)$row['customer_name'],
                    $anonymized ? '' : format_phone($row['phone']),
                    $label,
                    $sign === '+' ? (int)$row['quantity'] : -(int)$row['quantity'],
                    (string)$row['description'],
                ]);
            }
        }
    }

    // extrato do cliente: todas as movimentacoes dele nesta loja, paginadas.
    // o cartao e buscado pelo lojista da sessao + telefone, entao nao ha como ver extrato de outra loja.
    public function renderStatement() {
        $merchantId = $this->authGuard();

        $phone = PhoneValidator::sanitize($_GET['phone'] ?? '');
        $card = PhoneValidator::isValid($phone)
            ? (new LoyaltyCardModel($this->db))->findByMerchantAndPhone($merchantId, $phone)
            : null;

        if (!$card) {
            redirect('merchant/dashboard', ['error' => 'cliente_nao_encontrado']);
        }

        $logModel = new PointsLogModel($this->db);
        $paginator = new Paginator($logModel->countByCard((int)$card['id']), $_GET['page'] ?? 1, self::PER_PAGE);

        View::render('merchant/statement', [
            'card'      => $card,
            'entries'   => $logModel->pageByCard((int)$card['id'], $paginator->perPage, $paginator->offset()),
            'paginator' => $paginator,
        ]);
    }

    public function renderRewards() {
        $merchantId = $this->authGuard();
        $rewardModel = new RewardModel($this->db);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleRewards($merchantId, $rewardModel);
            return;
        }

        View::render('merchant/rewards', [
            'rewards'    => $rewardModel->listByMerchant($merchantId),
            'pointsRule' => $this->pointsRule(),
            'planUsage'  => $this->planUsage($merchantId),
        ]);
    }

    public function renderCustomers() {
        $merchantId = $this->authGuard();

        $search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);

        $cardModel = new LoyaltyCardModel($this->db);
        $paginator = new Paginator($cardModel->countByMerchant($merchantId, $search), $_GET['page'] ?? 1, self::PER_PAGE);

        View::render('merchant/customers', [
            'customers' => $cardModel->searchByMerchant($merchantId, $search, $paginator->perPage, $paginator->offset()),
            'paginator' => $paginator,
            'search'    => $search,
        ]);
    }

    // cadastro rapido: para todo telefone que ainda nao tem cartao NESTA loja
    // (novo na plataforma ou cliente de outra loja: a tela e a mesma, sem pista nenhuma)
    public function renderCustomerNew() {
        $merchantId = $this->authGuard();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleCustomerNew($merchantId);
            return;
        }

        $phone = PhoneValidator::sanitize($_GET['phone'] ?? '');
        if (!PhoneValidator::isValid($phone)) {
            redirect('merchant/dashboard', ['error' => 'telefone_invalido']);
        }

        // ja e cliente desta loja: nao refaz o cadastro
        if ((new LoyaltyCardModel($this->db))->findByMerchantAndPhone($merchantId, $phone)) {
            redirect('merchant/customer', ['phone' => $phone]);
        }

        // plano no limite de clientes: avisa antes de o lojista preencher o cadastro
        if (!$this->canAddCustomer($merchantId)) {
            redirect('merchant/dashboard', ['error' => 'limite_clientes']);
        }

        View::render('merchant/customer-new', ['phone' => $phone]);
    }

    private function handleCustomerNew($merchantId) {
        Csrf::verify();

        $phone = PhoneValidator::sanitize($_POST['phone'] ?? '');
        $name  = trim((string)($_POST['name'] ?? ''));

        if (!PhoneValidator::isValid($phone)) {
            redirect('merchant/dashboard', ['error' => 'telefone_invalido']);
        }

        if ($name === '' || mb_strlen($name) > 255) {
            redirect('merchant/customer-new', ['phone' => $phone, 'error' => 'nome_obrigatorio']);
        }

        if (($_POST['consent'] ?? '') !== '1') {
            redirect('merchant/customer-new', ['phone' => $phone, 'error' => 'consentimento_obrigatorio']);
        }

        $cardModel = new LoyaltyCardModel($this->db);

        // ja e cliente desta loja (ex.: formulario enviado duas vezes): nao troca nome nem consentimento
        if ($cardModel->findByMerchantAndPhone($merchantId, $phone)) {
            redirect('merchant/customer', ['phone' => $phone]);
        }

        // limite de clientes do plano (task 32): quem ja e cliente continua sendo atendido, so nao entra cliente novo
        if (!$this->canAddCustomer($merchantId)) {
            redirect('merchant/dashboard', ['error' => 'limite_clientes']);
        }

        // o telefone e global; nome e consentimento ficam no cartao desta loja
        $customerId = (new CustomerModel($this->db))->findOrCreate($phone);
        $cardModel->findOrCreate($merchantId, $customerId, $name, Privacy::VERSION);

        redirect('merchant/customer', ['phone' => $phone, 'success' => 'cliente_cadastrado']);
    }

    // so por POST com csrf: um link ou uma imagem em outro site nao consegue deslogar o lojista
    public function logout() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(isset($_SESSION['merchant_id']) ? 'merchant/dashboard' : 'merchant/login');
        }
        Csrf::verify();

        SessionGuard::destroy();

        redirect('merchant/login', ['success' => 'logout']);
    }

    // HANDLES -----------------------------------------------------------

    public function handleRegister(){
        Csrf::verify();

        // trazer dados do input
        $owner_name =   trim((string)filter_input(INPUT_POST, 'owner_name'));
        $store_name =   trim((string)filter_input(INPUT_POST, 'shop_name'));
        $address    =   trim((string)filter_input(INPUT_POST, 'address'));
        $state      =   filter_input(INPUT_POST, 'state');
        $city       =   trim((string)filter_input(INPUT_POST, 'city'));
        $email      =   trim((string)filter_input(INPUT_POST, 'email'));
        $phone      =   filter_input(INPUT_POST, 'phone');
        $category   =   filter_input(INPUT_POST, 'category');
        $document   =   filter_input(INPUT_POST, 'document');
        $password   =   $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // tratamento dos input masks vindos do front-end.
        // cpf so numeros; cnpj pode ter letras (alfanumerico, task 57): fica so letra maiuscula e digito
        $document   = DocumentValidator::normalize($document);
        $phone      = PhoneValidator::sanitize($phone);

        // verificacao dos dados: primeiro campos vazios/fora da lista, depois as regras especificas
        if ($owner_name === '' || $store_name === '' || $address === '' || $city === '' || $document === ''
            || !in_array($state, self::STATES, true)
            || !array_key_exists((string)$category, self::CATEGORIES)) {
            redirect('merchant/register', ['error' => 'campos_invalidos']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('merchant/register', ['error' => 'email_invalido']);
        }

        if (!PhoneValidator::isValid($phone)) {
            redirect('merchant/register', ['error' => 'telefone_invalido']);
        }

        // se nao for valido, ja para a requisicao
        if (!DocumentValidator::isValid($document)) {
            redirect('merchant/register', ['error' => 'documento_invalido']);
        }

        $passwordProblem = PasswordPolicy::problem((string)$password);
        if ($passwordProblem !== null) {
            redirect('merchant/register', ['error' => $passwordProblem]);
        }

        if (!hash_equals($password, (string)$passwordConfirm)) {
            redirect('merchant/register', ['error' => 'senhas_diferentes']);
        }

        // segurança e salvar
        // fazendo hash da senha
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        try { // salvando no banco
            $cpf = strlen($document) === 11 ? $document : null;
            $cnpj = strlen($document) === 14 ? $document : null;

            // prepared statements
            $data = [
                ':on'        => $owner_name,
                ':sn'        => $store_name,
                ':address'   => $address,
                ':state'     => $state,
                ':city'      => $city,
                ':email'     => $email,
                ':phone'     => $phone,
                ':category'  => $category,
                ':cpf'       => $cpf,
                ':cnpj'      => $cnpj,
                ':password_hash' => $hashedPassword
            ];

            $this->merchantModel->create($data);
            $merchantId = (int)$this->db->lastInsertId();
        } catch (\PDOException $e) {
            $sqlState = $e->errorInfo[0] ?? null;
            $driverCode = (int)($e->errorInfo[1] ?? 0);

            error_log('[MerchantController::handleRegister] PDOException: ' . $e->getMessage());

            // duplicidade (email/cpf/cnpj já cadastrados)
            if ($sqlState === '23000' && $driverCode === 1062) {
                redirect('merchant/register', ['error' => 'ja_cadastrado']);
            }

            // valor nulo em campo obrigatório no banco
            if ($sqlState === '23000' && $driverCode === 1048) {
                redirect('merchant/register', ['error' => 'campos_obrigatorios']);
            }

            // valor maior que o tamanho da coluna
            if ($sqlState === '22001' || $driverCode === 1406) {
                redirect('merchant/register', ['error' => 'dados_muito_longos']);
            }

            // formato incompativel com o tipo da coluna
            if ($sqlState === '22007' || $sqlState === '22018' || $driverCode === 1292) {
                redirect('merchant/register', ['error' => 'formato_invalido']);
            }

            // falha de conexao com o banco
            if ($driverCode === 2002 || $driverCode === 2006 || $sqlState === 'HY000') {
                redirect('merchant/register', ['error' => 'banco_indisponivel']);
            }

            // erros nao mapeados
            redirect('merchant/register', ['error' => 'erro_servidor']);
        }

        // conta nova so usa o painel depois de confirmar o e-mail (task 50)
        $this->sendEmailVerification($merchantId, $email, $owner_name);

        // se deu certo, redireciona usuario para o login
        redirect('merchant/login', ['success' => 'cadastrado']);
    }

    // manda (ou reenvia) o link de confirmacao do e-mail; o anterior deixa de valer
    private function sendEmailVerification(int $merchantId, string $email, string $ownerName): void {
        $token = (new EmailVerificationModel($this->db))->create($merchantId);
        $link = $this->publicBaseUrl() . url('merchant/verify-email', ['token' => $token]);
        MailerFactory::fromEnv()->send(new Message(
            $email,
            'FidelityX: confirme seu e-mail',
            "Olá, $ownerName.\n\n"
            . "Para começar a usar o painel do FidelityX, confirme que este e-mail é seu abrindo o link abaixo\n"
            . "(ele vale por 24 horas):\n\n$link\n\n"
            . "Se você não criou uma conta no FidelityX, ignore este e-mail."
        ));
    }

    // tela de quem entrou mas ainda nao confirmou o e-mail: explica e reenvia o link (task 50)
    public function renderConfirmEmail() {
        $merchantId = $this->authGuard(true);
        if ($this->merchant['email_verified_at'] !== null) {
            redirect('merchant/dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $limiter = new RateLimiter($this->db);
            $key = 'merchant:' . $merchantId;
            if ($limiter->tooMany('email_verify_resend', $key, self::MAX_VERIFY_RESENDS, self::RESET_WINDOW_SECONDS)) {
                redirect('merchant/confirm-email', ['error' => 'muitos_pedidos']);
            }
            $limiter->hit('email_verify_resend', $key);
            $this->sendEmailVerification($merchantId, $this->merchant['email'], $this->merchant['owner_name']);
            redirect('merchant/confirm-email', ['success' => 'confirmacao_reenviada']);
        }

        View::render('merchant/confirm-email', ['email' => $this->merchant['email']]);
    }

    // link do e-mail de confirmacao (nao exige login: pode ser aberto no celular, fora da sessao do painel)
    public function renderVerifyEmail() {
        $verified = (new EmailVerificationModel($this->db))->verify((string)($_GET['token'] ?? ''));
        $logged = isset($_SESSION['merchant_id']);

        if ($verified === null) {
            redirect($logged ? 'merchant/confirm-email' : 'merchant/login', ['error' => 'confirmacao_invalida']);
        }

        redirect($logged ? 'merchant/dashboard' : 'merchant/login', ['success' => 'email_confirmado']);
    }

    public function handleLogin(){
        Csrf::verify();

        $email      =    filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $password   =    $_POST['password'] ?? '';

        if (!$email || !$password) {
            redirect('merchant/login', ['error' => 'campos_obrigatorios']);
        }

        // limite de erros em 15 min, ate a janela passar:
        // - 5 pro mesmo e-mail vindos do mesmo ip: quem erra a senha trava so a si mesmo naquela conta.
        //   o bloqueio nunca e so pelo e-mail, senao qualquer pessoa que soubesse o e-mail de uma loja
        //   trancaria a dona pra fora errando a senha de proposito.
        // - 20 pro mesmo ip, em qualquer conta: segura quem testa senhas de varias lojas.
        $limiter = new RateLimiter($this->db);
        $ip = RateLimiter::clientKey();
        $pair = $email . '|' . $ip;
        if ($limiter->tooMany('login_pair', $pair, self::MAX_LOGIN_FAILURES, self::LOGIN_WINDOW_SECONDS)
            || $limiter->tooMany('login_ip', $ip, self::MAX_LOGIN_FAILURES_PER_IP, self::LOGIN_WINDOW_SECONDS)) {
            (new ErrorController())->handle(429);
            exit;
        }

        $merchant = $this->merchantModel->findByEmail($email) ?: null;

        // e-mail inexistente tambem roda o password_verify (LoginGuard): mesmo tempo da senha errada
        if (!LoginGuard::verify($merchant, (string)$password)) {
            $limiter->hit('login_pair', $pair);
            $limiter->hit('login_ip', $ip);
            // teto por conta somando todos os ips: so avisa no log, nunca bloqueia (ver LoginGuard)
            $limiter->hit('login_account', $email);
            $alert = LoginGuard::accountAlert($limiter->count('login_account', $email, self::LOGIN_WINDOW_SECONDS), $email);
            if ($alert !== null) {
                error_log($alert);
            }
            redirect('merchant/login', ['error' => 'credenciais_invalidas']);
        }

        // login certo zera os erros deste e-mail neste ip (os do ip em geral continuam contando)
        $limiter->clear('login_pair', $pair);

        if($merchant['status'] === 'inactive'){
            redirect('merchant/login', ['error' => 'conta_inativa']);
        }

        // gera um novo id de sessao no login (evita session fixation)
        session_regenerate_id(true);
        // e token csrf novo: o da pagina de login (visto antes de entrar) nao vale na sessao logada
        Csrf::regenerate();

        $_SESSION['merchant_id']     = (int)$merchant['id'];
        $_SESSION['merchant_name']   = $merchant['owner_name'];
        $_SESSION['store_name']      = $merchant['store_name'];
        $_SESSION['last_seen']       = time();
        $_SESSION['password_sig']    = SessionGuard::passwordSignature($merchant['password_hash']);

        redirect('merchant/dashboard', ['success' => 'logged']);
    }

    // post da tela do cliente: lancar pontos ou resgatar premio.
    // o cartao e sempre buscado pelo lojista da sessao + telefone, nunca por id vindo do form.
    private function handleCustomer($merchantId) {
        Csrf::verify();

        $phone = PhoneValidator::sanitize($_POST['phone'] ?? '');
        $cardModel = new LoyaltyCardModel($this->db);
        $card = PhoneValidator::isValid($phone) ? $cardModel->findByMerchantAndPhone($merchantId, $phone) : null;

        if (!$card) {
            redirect('merchant/dashboard', ['error' => 'cliente_nao_encontrado']);
        }

        // cartao antigo, sem consentimento gravado: o lojista pergunta e registra
        if (($_POST['action'] ?? '') === 'consent') {
            if (($_POST['consent'] ?? '') !== '1') {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'consentimento_obrigatorio']);
            }
            $cardModel->recordConsent($card['id'], Privacy::VERSION);
            redirect('merchant/customer', ['phone' => $phone, 'success' => 'consentimento_registrado']);
        }

        // corrigir o nome dado a esta loja (task 43)
        if (($_POST['action'] ?? '') === 'rename') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 255) {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'nome_invalido']);
            }
            $cardModel->rename($card['id'], $merchantId, $name);
            redirect('merchant/customer', ['phone' => $phone, 'success' => 'nome_corrigido']);
        }

        // cliente trocou de numero (task 43): o cartao desta loja vai para o telefone novo, com saldo e historico.
        // telefone que ja tem cartao nesta loja e recusado; o que ele tem em outras lojas nao aparece nem muda.
        if (($_POST['action'] ?? '') === 'change_phone') {
            $newPhone = PhoneValidator::sanitize($_POST['new_phone'] ?? '');
            if (!PhoneValidator::isValid($newPhone)) {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'telefone_novo_invalido']);
            }
            if ($newPhone === $phone) {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'telefone_igual']);
            }
            if ($cardModel->changePhone($card['id'], $merchantId, $newPhone) !== 'ok') {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'telefone_ja_cliente']);
            }
            redirect('merchant/customer', ['phone' => $newPhone, 'success' => 'telefone_trocado']);
        }

        // exclusao dos dados a pedido do cliente (LGPD). a confirmacao e obrigatoria: nao tem volta.
        if (($_POST['action'] ?? '') === 'anonymize') {
            if (($_POST['confirm'] ?? '') !== '1') {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'confirmacao_obrigatoria']);
            }
            $cardModel->anonymize($card['id'], $merchantId);
            redirect('merchant/dashboard', ['success' => 'cliente_excluido']);
        }

        // estorno de lancamento (do extrato ou do Desfazer da confirmacao) ou de resgate (do extrato, task 44).
        // volta pra tela de onde veio.
        if (($_POST['action'] ?? '') === 'reverse') {
            $back = ($_POST['back'] ?? '') === 'statement' ? 'merchant/statement' : 'merchant/customer';
            $logId = filter_var($_POST['log_id'] ?? '', FILTER_VALIDATE_INT);
            // a movimentacao precisa ser do cartao deste cliente (o model confere tambem a loja)
            $type = $logId ? (new PointsLogModel($this->db))->typeForCard($logId, (int)$card['id']) : null;
            $result = $type !== null ? $cardModel->reverse($logId, $merchantId) : 'not_found';

            redirect($back, ['phone' => $phone] + match ($result) {
                'ok'           => ['success' => $type === 'redeem' ? 'resgate_estornado' : 'lancamento_estornado'],
                'already'      => ['error' => 'estorno_repetido'],
                'expired'      => ['error' => 'estorno_expirado'],
                'insufficient' => ['error' => 'estorno_sem_saldo'],
                default        => ['error' => 'estorno_invalido'],
            });
        }

        if (($_POST['action'] ?? '') === 'redeem') {
            $rewardId = filter_var($_POST['reward_id'] ?? '', FILTER_VALIDATE_INT);
            $reward = $rewardId ? (new RewardModel($this->db))->findActiveForMerchant($rewardId, $merchantId) : null;

            if (!$reward) {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'premio_invalido']);
            }

            if (!$cardModel->redeem($card['id'], $reward)) {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'saldo_insuficiente']);
            }

            redirect('merchant/customer', ['phone' => $phone, 'success' => 'resgate_realizado']);
        }

        // com regra de pontos e valor da compra digitado, os pontos saem do valor (task 6), sempre
        // arredondando pra baixo e calculados aqui (a previa da tela e so ajuda). sem valor, vale o campo de pontos.
        $amount = trim((string)($_POST['amount'] ?? ''));
        $rule = $this->pointsRule();
        $defaultDescription = 'Compra';

        if ($amount !== '' && $rule) {
            $cents = Money::toCents($amount);
            if ($cents === null || $cents < 1) {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'valor_invalido']);
            }
            $points = Money::pointsFor($cents, $rule);
            if ($points < 1) {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'valor_sem_pontos']);
            }
            if ($points > self::MAX_POINTS_PER_ENTRY) {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'valor_pontos_demais']);
            }
            $defaultDescription = 'Compra de ' . Money::format($cents);
        } else {
            $points = filter_var($_POST['points'] ?? '', FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => self::MAX_POINTS_PER_ENTRY],
            ]);
        }

        $description = trim((string)($_POST['description'] ?? ''));
        $description = $description === '' ? $defaultDescription : mb_substr($description, 0, 255);

        if ($points === false) {
            redirect('merchant/customer', ['phone' => $phone, 'error' => 'pontos_invalidos']);
        }

        $logId = $cardModel->addPoints($card['id'], $points, $description);

        // a tela do cliente mostra a confirmacao (novo saldo, quanto falta, Desfazer) a partir do lancamento
        redirect('merchant/customer', ['phone' => $phone, 'lancamento' => $logId]);
    }

    // criar, ativar/desativar ou excluir premio
    private function handleRewards($merchantId, RewardModel $rewardModel) {
        Csrf::verify();

        if (($_POST['action'] ?? '') === 'toggle') {
            $rewardId = filter_var($_POST['reward_id'] ?? '', FILTER_VALIDATE_INT);
            $reward = $rewardId ? $rewardModel->findForMerchant($rewardId, $merchantId) : false;
            if ($reward) {
                // desativar sempre pode; reativar conta no limite de premios ativos do plano (task 32)
                if (!$reward['active'] && !$this->canActivateReward($merchantId, $rewardModel)) {
                    redirect('merchant/rewards', ['error' => 'limite_premios']);
                }
                $rewardModel->toggleActive($rewardId, $merchantId);
            }
            redirect('merchant/rewards', ['success' => 'premio_atualizado']);
        }

        if (($_POST['action'] ?? '') === 'delete') {
            $rewardId = filter_var($_POST['reward_id'] ?? '', FILTER_VALIDATE_INT);
            $result = $rewardId ? $rewardModel->deleteOrDeactivate($rewardId, $merchantId) : null;

            redirect('merchant/rewards', match ($result) {
                'deleted'     => ['success' => 'premio_excluido'],
                'deactivated' => ['success' => 'premio_desativado_resgatado'],
                default       => ['error' => 'premio_invalido'],
            });
        }

        $fields = $this->readRewardFields();
        if ($fields === null) {
            redirect('merchant/rewards', ['error' => 'campos_invalidos']);
        }

        // premio novo nasce ativo: so entra se o plano ainda tem vaga (task 32)
        if (!$this->canActivateReward($merchantId, $rewardModel)) {
            redirect('merchant/rewards', ['error' => 'limite_premios']);
        }

        $rewardModel->create($merchantId, $fields['name'], $fields['description'], $fields['cost']);

        redirect('merchant/rewards', ['success' => 'premio_criado']);
    }

    // tela de edicao do premio (nome, descricao e custo); o id vem na url e o dono e checado pela sessao
    public function renderRewardEdit() {
        $merchantId = $this->authGuard();
        $rewardModel = new RewardModel($this->db);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleRewardEdit($merchantId, $rewardModel);
            return;
        }

        $rewardId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
        $reward = $rewardId ? $rewardModel->findForMerchant($rewardId, $merchantId) : null;

        if (!$reward) {
            redirect('merchant/rewards', ['error' => 'premio_invalido']);
        }

        View::render('merchant/reward-edit', ['reward' => $reward]);
    }

    private function handleRewardEdit($merchantId, RewardModel $rewardModel) {
        Csrf::verify();

        $rewardId = filter_var($_POST['reward_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$rewardId || !$rewardModel->findForMerchant($rewardId, $merchantId)) {
            redirect('merchant/rewards', ['error' => 'premio_invalido']);
        }

        $fields = $this->readRewardFields();
        if ($fields === null) {
            redirect('merchant/reward-edit', ['id' => $rewardId, 'error' => 'campos_invalidos']);
        }

        $rewardModel->update($rewardId, $merchantId, $fields['name'], $fields['description'], $fields['cost']);

        redirect('merchant/rewards', ['success' => 'premio_editado']);
    }

    // perfil do lojista: dados da loja e troca de senha (dois formularios na mesma tela)
    public function renderProfile() {
        $merchantId = $this->authGuard();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleProfile($merchantId);
            return;
        }

        View::render('merchant/profile', [
            'profile'     => $this->merchantModel->findProfile($merchantId),
            'planUsage'   => $this->planUsage($merchantId),
            'categories'  => self::CATEGORIES,
            'states'      => self::STATES,
            'minPassword' => PasswordPolicy::MIN_BYTES,
            'maxPassword' => PasswordPolicy::MAX_BYTES,
        ]);
    }

    private function handleProfile($merchantId) {
        Csrf::verify();

        if (($_POST['action'] ?? '') === 'password') {
            $this->handlePasswordChange($merchantId);
        }

        // as mesmas regras do cadastro. e-mail e cpf/cnpj nao sao editaveis aqui: mesmo que venham no post, sao ignorados.
        $fields = [
            'owner_name' => trim((string)($_POST['owner_name'] ?? '')),
            'store_name' => trim((string)($_POST['shop_name'] ?? '')),
            'phone'      => PhoneValidator::sanitize($_POST['phone'] ?? ''),
            'category'   => (string)($_POST['category'] ?? ''),
            'address'    => trim((string)($_POST['address'] ?? '')),
            'city'       => trim((string)($_POST['city'] ?? '')),
            'state'      => (string)($_POST['state'] ?? ''),
        ];

        if ($fields['owner_name'] === '' || $fields['store_name'] === '' || $fields['address'] === '' || $fields['city'] === ''
            || !in_array($fields['state'], self::STATES, true)
            || !array_key_exists($fields['category'], self::CATEGORIES)) {
            redirect('merchant/profile', ['error' => 'campos_invalidos']);
        }

        // tamanho das colunas conferido aqui: o mysql sem modo estrito cortaria o texto sem avisar
        if (mb_strlen($fields['owner_name']) > 255 || mb_strlen($fields['store_name']) > 255
            || mb_strlen($fields['address']) > 255 || mb_strlen($fields['city']) > 100) {
            redirect('merchant/profile', ['error' => 'dados_muito_longos']);
        }

        if (!PhoneValidator::isValid($fields['phone'])) {
            redirect('merchant/profile', ['error' => 'telefone_invalido']);
        }

        $this->merchantModel->updateProfile($merchantId, $fields);

        redirect('merchant/profile', ['success' => 'perfil_atualizado']);
    }

    // troca de senha: so com a senha atual (sessao aberta num aparelho esquecido nao basta pra tomar a conta)
    private function handlePasswordChange($merchantId): never {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            redirect('merchant/profile', ['error' => 'campos_obrigatorios']);
        }

        // limite de erros da senha atual, pra ninguem ficar testando senhas por este formulario
        $limiter = new RateLimiter($this->db);
        $key = 'merchant:' . $merchantId;
        if ($limiter->tooMany('password_change', $key, self::MAX_PASSWORD_FAILURES, self::PASSWORD_WINDOW_SECONDS)) {
            redirect('merchant/profile', ['error' => 'muitas_tentativas']);
        }

        $merchant = $this->merchantModel->findById($merchantId);
        if (!$merchant || !password_verify($current, $merchant['password_hash'])) {
            $limiter->hit('password_change', $key);
            redirect('merchant/profile', ['error' => 'senha_atual_incorreta']);
        }

        $passwordProblem = PasswordPolicy::problem($new);
        if ($passwordProblem !== null) {
            redirect('merchant/profile', ['error' => $passwordProblem]);
        }

        // trocar pela mesma senha nao derruba quem pegou a senha antiga: precisa ser outra
        if (password_verify($new, $merchant['password_hash'])) {
            redirect('merchant/profile', ['error' => 'senha_igual']);
        }

        if (!hash_equals($new, $confirm)) {
            redirect('merchant/profile', ['error' => 'senhas_diferentes']);
        }

        $hash = password_hash($new, PASSWORD_BCRYPT);
        $this->merchantModel->updatePasswordHash($merchantId, $hash);
        $limiter->clear('password_change', $key);

        // esta sessao continua valendo (com id novo); as outras, abertas com a senha antiga, caem no authGuard
        session_regenerate_id(true);
        $_SESSION['password_sig'] = SessionGuard::passwordSignature($hash);

        redirect('merchant/profile', ['success' => 'senha_alterada']);
    }

    // nome, descricao e custo do formulario de premio; null se algo estiver invalido
    private function readRewardFields(): ?array {
        $name        = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $cost        = filter_var($_POST['points_cost'] ?? '', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000000],
        ]);

        if ($name === '' || mb_strlen($name) > 120 || mb_strlen($description) > 255 || $cost === false) {
            return null;
        }

        return ['name' => $name, 'description' => $description === '' ? null : $description, 'cost' => $cost];
    }

    // barra quem nao esta logado e devolve o id do lojista da sessao.
    // o id SEMPRE vem da sessao, nunca do formulario.
    // a cada requisicao: sessao parada demais expira, e a conta e conferida no banco
    // (lojista desativado perde o acesso na hora, nao so no proximo login).
    // $allowUnverified: so a tela de confirmar o e-mail aceita conta que ainda nao confirmou (task 50)
    private function authGuard(bool $allowUnverified = false): int {
        if(!isset($_SESSION['merchant_id'])){
            redirect('merchant/login', ['error' => 'sessao_expirada']);
        }

        if (SessionGuard::isExpired($_SESSION['last_seen'] ?? null, time())) {
            SessionGuard::destroy();
            redirect('merchant/login', ['error' => 'sessao_expirada']);
        }

        $merchant = $this->merchantModel->findById((int)$_SESSION['merchant_id']);
        if (!$merchant || $merchant['status'] !== 'active') {
            SessionGuard::destroy();
            redirect('merchant/login', ['error' => 'conta_inativa']);
        }

        // a senha foi trocada depois que esta sessao abriu (em outro aparelho): precisa entrar de novo
        if (SessionGuard::passwordChanged($_SESSION['password_sig'] ?? null, $merchant['password_hash'])) {
            SessionGuard::destroy();
            redirect('merchant/login', ['error' => 'sessao_expirada']);
        }

        // a loja da requisicao fica a mao (ex.: regra de pontos), sem consultar o banco de novo
        $this->merchant = $merchant;

        // conta nova que ainda nao confirmou o e-mail so ve a tela de confirmar
        if (!$allowUnverified && $merchant['email_verified_at'] === null) {
            redirect('merchant/confirm-email');
        }

        // nome da loja sempre atualizado no menu (pode ter mudado desde o login)
        $_SESSION['last_seen']     = time();
        $_SESSION['merchant_name'] = $merchant['owner_name'];
        $_SESSION['store_name']    = $merchant['store_name'];

        return (int)$merchant['id'];
    }
}
