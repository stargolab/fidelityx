<?php
// ponto de entrada unico: toda requisicao chega aqui com ?url=dominio/acao.
// ordem: cabecalhos de seguranca e limpeza dos parametros (RequestGuard), registro de erros (ErrorLog),
// configuracao (Env), sessao, conexao com o banco e, por fim, o roteamento:
// o switch escolhe o controller pelo dominio (ex.: merchant) e o match chama o metodo da acao (ex.: register).
// ex.: o formulario de cadastro manda POST para index.php?url=merchant/register -> MerchantController::renderRegister().

// autoload psr-4 do composer: as classes de src/ (namespace App\) carregam sozinhas, sem require

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ErrorController;
use App\Support\Env;
use App\Support\ErrorLog;
use App\Support\RequestGuard;
use App\Support\SessionGuard;

// inicializacao
// cabecalhos de seguranca em toda resposta e nada de parametro em formato de lista (ver RequestGuard)
RequestGuard::sendSecurityHeaders();
$_GET = RequestGuard::dropArrayParams($_GET);
$_POST = RequestGuard::dropArrayParams($_POST);

// qualquer excecao nao tratada ou erro fatal vira log + pagina 500 (nunca mostra o erro cru pro usuario)
ErrorLog::register();

// configuracao: variaveis de ambiente e, se existir, o .env da raiz (__DIR__ . '/..' sobe uma pasta).
// em producao (docker) nao ha .env, so variaveis de ambiente. tudo fica no $_ENV, usado no Database.php!
Env::load(__DIR__ . '/..');
ErrorLog::useFile(Env::get('LOG_FILE'));
RequestGuard::sendHsts();

// rota de saude do monitor e do healthcheck: antes da sessao, pra checagem nao criar arquivo de sessao
if (($_GET['url'] ?? '') === 'health') {
    \App\Support\Health::respond();
    exit;
}

// cookie de sessao so via http (js nao le), sem envio em POST vindo de outro site e, em https, so por https.
// use_strict_mode: id de sessao inventado por quem chega (session fixation) e trocado por um novo.
// gc_maxlifetime acompanha a expiracao por inatividade (SessionGuard), senao o PHP apagaria a sessao em 24 min.
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => RequestGuard::isHttps(),
    'use_strict_mode' => true,
    'gc_maxlifetime'  => SessionGuard::IDLE_SECONDS,
]);

// sem as variaveis do banco nao ha o que servir: avisa no log quais faltam e responde 503
$missing = Env::missing();
if ($missing !== []) {
    error_log('[config] variaveis obrigatorias sem valor: ' . implode(', ', $missing));
    (new ErrorController())->handle(503);
    exit;
}

// fuso fixo da aplicacao (o Database aplica o mesmo na conexao com o MySQL)
date_default_timezone_set(app_timezone());

// namespace
use App\Database;

// conexao com o banco
$db = Database::getConnection();

// rota da url, ex.: http://localhost:8000/index.php?url=merchant/register (sem url = home)
$url = filter_var(rtrim((string)($_GET['url'] ?? ''), '/'), FILTER_SANITIZE_URL);
$url = $url === '' ? 'home' : $url;
$urlParts = explode('/', $url);

$domain = $urlParts[0];
$action = $urlParts[1] ?? null;

switch ($domain) {

    case 'home':
        // apresentacao publica do produto (lojista logado vai direto pro painel)
        (new \App\Controllers\HomeController())->render();
        break;

    case 'privacy':
        (new \App\Controllers\HomeController())->renderPrivacy();
        break;

    case 'customer':
        RequestGuard::sendNoStore();
        // exemplo do psr-4 citado acima, sem require_once
        $controller = new \App\Controllers\CustomerController($db);

        // match é o novo switch do php, disponivel a partir do php8, deixa o codigo mais limpo
        match ($action ?? 'balance') {
            'balance' => $controller->renderBalance(),
            default   => (new ErrorController())->handle(404),
        };
        break;

    case 'merchant':
        RequestGuard::sendNoStore();
        $controller = new \App\Controllers\MerchantController($db);

        match ($action ?? 'dashboard') {
            'login'     => $controller->renderLogin(),
            'register'  => $controller->renderRegister(),
            'forgot'    => $controller->renderForgot(),
            'reset'     => $controller->renderReset(),
            'customer-new' => $controller->renderCustomerNew(),
            'logout'    => $controller->logout(),
            'dashboard' => $controller->renderDashboard(),
            'customer'  => $controller->renderCustomer(),
            'statement' => $controller->renderStatement(),
            'poster'    => $controller->renderPoster(),
            'points-rule' => $controller->renderPointsRule(),
            'reports'   => $controller->renderReports(),
            'export'    => $controller->renderExport(),
            'rewards'   => $controller->renderRewards(),
            'reward-edit' => $controller->renderRewardEdit(),
            'customers' => $controller->renderCustomers(),
            'profile'   => $controller->renderProfile(),
            default     => (new ErrorController())->handle(404),
        };
        break;

    default:
        // rota inexistente é 404. (a api fica pra depois do MVP)
        $controller = new ErrorController();
        $controller->handle(404);
        break;
}
