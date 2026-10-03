<?php
// ----------------------------------------------------------------------------------------
// explicação breve fluxo de dados, com exemplo do registro

// navegador: vê o action="index.php?url=merchant/register" e manda o POST pra lá (ele cai aqui no index.php, e o switch guia ele pro merchant, depois o match guia pro controller, e assim vai.)

// index.php: lê o $_GET['url'], vê que é merchant/register.

// roteador (switch/match): o seu código no index.php vê que o domínio é merchant e a ação é register.

// namespace: index.php usa o namespace para chamar o controller certo: new \App\Controllers\MerchantController($db).
// -----------------------------------------------------------------------------------------

// inicializacao
// cookie de sessao so via http (js nao le) e sem envio em POST vindo de outro site
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

// autoloading psr-4 , carrega as classes necessárias, não tem necessidade de ficar puxando com require, include, etc.
// pro nosso caso especifico, substitui massivamente os requires, deixa o código mais limpo e combinado com o singleton
// aumenta muito o desempenho e otimizaçao pra larga escala.

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Controllers\ErrorController;

// qualquer excecao nao tratada vira log + pagina 500 (nunca mostra o erro cru pro usuario)
set_exception_handler(function (\Throwable $e) {
    error_log('[uncaught] ' . $e);
    (new ErrorController())->handle(500);
});

// criando instancia da biblioteca dotenv
// __DIR__ . '/..' diz para o php subir uma pasta para encontrar o .env na raiz
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');

// load() lê a env e guarda as informações na memoria ($_ENV)
$dotenv->load();
// a .env será usada no Database.php!

// namespace
use App\Database;

// conexao com o banco
$db = Database::getConnection();

// sistema tratamento da url. exemplo resultado final: http://localhost:8080/index.php?url=merchant/register
$url = filter_var(rtrim((string)($_GET['url'] ?? ''), '/'), FILTER_SANITIZE_URL);
$url = $url === '' ? 'home' : $url;
$urlParts = explode('/', $url);

$domain = $urlParts[0];
$action = $urlParts[1] ?? null;

switch ($domain) {

    case 'home':
        // por enquanto a "home" é o login do lojista
        redirect('merchant/login');

    case 'customer':
        // exemplo do psr-4 citado acima, sem require_once
        $controller = new \App\Controllers\CustomerController($db);

        // match é o novo switch do php, disponivel a partir do php8, deixa o codigo mais limpo
        match ($action ?? 'balance') {
            'balance' => $controller->renderBalance(),
            default   => (new ErrorController())->handle(404),
        };
        break;

    case 'merchant':
        $controller = new \App\Controllers\MerchantController($db);

        match ($action ?? 'dashboard') {
            'login'     => $controller->renderLogin(),
            'register'  => $controller->renderRegister(),
            'customer-new' => $controller->renderCustomerNew(),
            'logout'    => $controller->logout(),
            'dashboard' => $controller->renderDashboard(),
            'customer'  => $controller->renderCustomer(),
            'statement' => $controller->renderStatement(),
            'rewards'   => $controller->renderRewards(),
            'reward-edit' => $controller->renderRewardEdit(),
            'customers' => $controller->renderCustomers(),
            default     => (new ErrorController())->handle(404),
        };
        break;

    default:
        // rota inexistente é 404. (a api fica pra depois do MVP)
        $controller = new ErrorController();
        $controller->handle(404);
        break;
}
