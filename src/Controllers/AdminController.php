<?php

namespace App\Controllers;

use App\Models\AdminModel;
use App\Models\MerchantModel;
use App\Support\Csrf;
use App\Support\LoginGuard;
use App\Support\Paginator;
use App\Support\RateLimiter;
use App\Support\SessionGuard;
use App\Support\View;

// painel administrativo (task 30): o admin ve todas as lojas e ativa ou desativa um lojista.
// login e sessao proprios (admin_id na sessao, tabela admins), separados dos do lojista.
// LGPD: o admin ve so os dados da loja e quantos clientes ela tem, nunca os clientes.
class AdminController {
    private const PER_PAGE = 20;
    // admin tem mais poder que o lojista: sessao parada expira em 1 hora
    private const IDLE_SECONDS = 3600;
    private const MAX_LOGIN_FAILURES = 5;
    private const MAX_LOGIN_FAILURES_PER_IP = 20;
    private const LOGIN_WINDOW_SECONDS = 900;

    private $db;
    private AdminModel $admins;

    public function __construct($db) {
        $this->db = $db;
        $this->admins = new AdminModel($db);
    }

    public function renderLogin() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleLogin();
            return;
        }
        if (isset($_SESSION['admin_id'])) {
            redirect('admin/dashboard');
        }
        View::render('admin/login');
    }

    // mesmas protecoes do login do lojista: limite por e-mail + ip e por ip, mesmo tempo pra e-mail inexistente
    private function handleLogin() {
        Csrf::verify();

        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($email === '' || $password === '') {
            redirect('admin/login', ['error' => 'campos_obrigatorios']);
        }

        $limiter = new RateLimiter($this->db);
        $ip = RateLimiter::clientKey();
        $pair = $email . '|' . $ip;
        if ($limiter->tooMany('admin_login_pair', $pair, self::MAX_LOGIN_FAILURES, self::LOGIN_WINDOW_SECONDS)
            || $limiter->tooMany('admin_login_ip', $ip, self::MAX_LOGIN_FAILURES_PER_IP, self::LOGIN_WINDOW_SECONDS)) {
            (new ErrorController())->handle(429);
            exit;
        }

        $admin = $this->admins->findByEmail($email) ?: null;
        if (!LoginGuard::verify($admin, $password)) {
            $limiter->hit('admin_login_pair', $pair);
            $limiter->hit('admin_login_ip', $ip);
            redirect('admin/login', ['error' => 'credenciais_invalidas']);
        }
        $limiter->clear('admin_login_pair', $pair);

        session_regenerate_id(true);
        Csrf::regenerate();
        $_SESSION['admin_id']        = (int)$admin['id'];
        $_SESSION['admin_name']      = $admin['name'];
        $_SESSION['admin_last_seen'] = time();
        $_SESSION['admin_sig']       = SessionGuard::passwordSignature($admin['password_hash']);

        redirect('admin/dashboard');
    }

    public function logout() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(isset($_SESSION['admin_id']) ? 'admin/dashboard' : 'admin/login');
        }
        Csrf::verify();
        SessionGuard::destroy();
        redirect('admin/login', ['success' => 'logout']);
    }

    // lista das lojas, paginada; POST ativa ou desativa uma, ou troca o plano dela (action=plan)
    public function renderDashboard() {
        $this->adminGuard();
        $merchants = new MerchantModel($this->db);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $merchantId = filter_var($_POST['merchant_id'] ?? '', FILTER_VALIDATE_INT);
            $status = (string)($_POST['status'] ?? '');
            $page = filter_var($_POST['page'] ?? '', FILTER_VALIDATE_INT) ?: 1;

            // troca de plano (task 32): Free <-> Pro. o limite novo vale na proxima requisicao do lojista
            if (($_POST['action'] ?? '') === 'plan') {
                if (!$merchantId || !$merchants->setPlan($merchantId, (string)($_POST['plan'] ?? ''))) {
                    redirect('admin/dashboard', ['page' => $page, 'error' => 'loja_invalida']);
                }
                redirect('admin/dashboard', ['page' => $page, 'success' => 'plano_alterado']);
            }

            if (!$merchantId || !$merchants->setStatus($merchantId, $status)) {
                redirect('admin/dashboard', ['page' => $page, 'error' => 'loja_invalida']);
            }
            redirect('admin/dashboard', ['page' => $page, 'success' => $status === 'active' ? 'loja_ativada' : 'loja_desativada']);
        }

        $paginator = new Paginator($merchants->countAll(), $_GET['page'] ?? 1, self::PER_PAGE);
        View::render('admin/dashboard', [
            'merchants' => $merchants->pageForAdmin($paginator->perPage, $paginator->offset()),
            'paginator' => $paginator,
        ]);
    }

    // barra quem nao e admin logado; a cada requisicao confere se o admin existe, se a sessao nao
    // ficou parada demais e se a senha nao mudou desde o login
    private function adminGuard(): int {
        if (!isset($_SESSION['admin_id'])) {
            redirect('admin/login', ['error' => 'sessao_expirada']);
        }

        $admin = $this->admins->findById((int)$_SESSION['admin_id']);
        if (!$admin
            || SessionGuard::isExpired($_SESSION['admin_last_seen'] ?? null, time(), self::IDLE_SECONDS)
            || SessionGuard::passwordChanged($_SESSION['admin_sig'] ?? null, $admin['password_hash'])) {
            SessionGuard::destroy();
            redirect('admin/login', ['error' => 'sessao_expirada']);
        }

        $_SESSION['admin_last_seen'] = time();
        return (int)$admin['id'];
    }
}
