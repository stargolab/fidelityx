<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\LoyaltyCardModel;
use App\Models\MerchantModel;
use App\Models\PointsLogModel;
use App\Models\RewardModel;
use App\Support\Csrf;
use App\Support\Paginator;
use App\Support\Privacy;
use App\Support\QrSvg;
use App\Support\RateLimiter;
use App\Support\RewardProgress;
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
    private const MAX_LOGIN_FAILURES = 5;
    private const LOGIN_WINDOW_SECONDS = 900;

    private $db;
    private $merchantModel;

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

    // RENDERS (privadas, exigem login) -----------------------------------

    // home do lojista: um campo de telefone que decide o destino.
    // cliente desta loja -> tela do cliente; qualquer outro -> cadastro rapido.
    // telefone novo e telefone de cliente de outra loja seguem EXATAMENTE o mesmo caminho:
    // a loja nao descobre se o numero existe em outro lugar nem o nome dado la (LGPD, docs/adr/002).
    public function renderDashboard() {
        $merchantId = $this->authGuard();

        if (!isset($_GET['phone'])) {
            View::render('merchant/dashboard', ['steps' => $this->onboardingSteps($merchantId)]);
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

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
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

        View::render('merchant/customer', [
            'card'     => $card,
            'rewards'  => $rewards,
            'progress' => RewardProgress::next($balance, $activeRewards),
        ]);
    }

    // relatorios: indicadores da loja + historico de todas as movimentacoes, paginado
    public function renderReports() {
        $merchantId = $this->authGuard();

        $logModel = new PointsLogModel($this->db);
        $paginator = new Paginator($logModel->countByMerchant($merchantId), $_GET['page'] ?? 1, self::PER_PAGE);

        View::render('merchant/reports', [
            'stats'     => $logModel->statsByMerchant($merchantId),
            'entries'   => $logModel->pageByMerchant($merchantId, $paginator->perPage, $paginator->offset()),
            'paginator' => $paginator,
        ]);
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
            'rewards' => $rewardModel->listByMerchant($merchantId),
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

        // o telefone e global; nome e consentimento ficam no cartao desta loja
        $customerId = (new CustomerModel($this->db))->findOrCreate($phone);
        $cardModel->findOrCreate($merchantId, $customerId, $name, Privacy::VERSION);

        redirect('merchant/customer', ['phone' => $phone, 'success' => 'cliente_cadastrado']);
    }

    public function logout() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

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
        $document   = preg_replace('/\D/', '', (string)$document);
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

        if (strlen($password) < 6) {
            redirect('merchant/register', ['error' => 'senha_curta']);
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

             // se deu certo, redireciona usuario para o login
            redirect('merchant/login', ['success' => 'cadastrado']);
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

    }

    public function handleLogin(){
        Csrf::verify();

        $email      =    filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $password   =    $_POST['password'] ?? '';

        if (!$email || !$password) {
            redirect('merchant/login', ['error' => 'campos_obrigatorios']);
        }

        // 5 erros em 15 min pro mesmo e-mail ou ip bloqueiam o login ate a janela passar
        $limiter = new RateLimiter($this->db);
        $ip = RateLimiter::clientIp();
        if ($limiter->tooMany('login_email', $email, self::MAX_LOGIN_FAILURES, self::LOGIN_WINDOW_SECONDS)
            || $limiter->tooMany('login_ip', $ip, self::MAX_LOGIN_FAILURES, self::LOGIN_WINDOW_SECONDS)) {
            (new ErrorController())->handle(429);
            exit;
        }

        $merchant = $this->merchantModel->findByEmail($email);

        if(!$merchant || !password_verify($password, $merchant['password_hash'])){
            $limiter->hit('login_email', $email);
            $limiter->hit('login_ip', $ip);
            redirect('merchant/login', ['error' => 'credenciais_invalidas']);
        }

        // login certo zera os erros do e-mail (os do ip continuam contando)
        $limiter->clear('login_email', $email);

        if($merchant['status'] === 'inactive'){
            redirect('merchant/login', ['error' => 'conta_inativa']);
        }

        // gera um novo id de sessao no login (evita session fixation)
        session_regenerate_id(true);

        $_SESSION['merchant_id']     = (int)$merchant['id'];
        $_SESSION['merchant_name']   = $merchant['owner_name'];
        $_SESSION['store_name']      = $merchant['store_name'];

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

        // exclusao dos dados a pedido do cliente (LGPD). a confirmacao e obrigatoria: nao tem volta.
        if (($_POST['action'] ?? '') === 'anonymize') {
            if (($_POST['confirm'] ?? '') !== '1') {
                redirect('merchant/customer', ['phone' => $phone, 'error' => 'confirmacao_obrigatoria']);
            }
            $cardModel->anonymize($card['id'], $merchantId);
            redirect('merchant/dashboard', ['success' => 'cliente_excluido']);
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

        $points = filter_var($_POST['points'] ?? '', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => self::MAX_POINTS_PER_ENTRY],
        ]);
        $description = trim((string)($_POST['description'] ?? ''));
        $description = $description === '' ? 'Compra' : mb_substr($description, 0, 255);

        if ($points === false) {
            redirect('merchant/customer', ['phone' => $phone, 'error' => 'pontos_invalidos']);
        }

        $cardModel->addPoints($card['id'], $points, $description);

        redirect('merchant/customer', ['phone' => $phone, 'success' => 'pontos_lancados']);
    }

    // criar, ativar/desativar ou excluir premio
    private function handleRewards($merchantId, RewardModel $rewardModel) {
        Csrf::verify();

        if (($_POST['action'] ?? '') === 'toggle') {
            $rewardId = filter_var($_POST['reward_id'] ?? '', FILTER_VALIDATE_INT);
            if ($rewardId) {
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
    private function authGuard(): int {
        if(!isset($_SESSION['merchant_id'])){
            redirect('merchant/login', ['error' => 'sessao_expirada']);
        }
        return (int)$_SESSION['merchant_id'];
    }
}
