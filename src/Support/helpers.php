<?php
// funcoes globais carregadas pelo composer (autoload "files").
// ficam fora de classe pra poder usar direto nas views, ex: e($nome)

// escapa texto antes de imprimir no html (protecao contra XSS)
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// redireciona pra uma rota interna e encerra a requisicao.
// exemplo: redirect('merchant/login', ['error' => 'credenciais_invalidas'])
function redirect(string $route, array $params = []): never {
    $query = http_build_query(array_merge(['url' => $route], $params));
    header('Location: index.php?' . $query);
    exit; // sempre dar exit após redir
}

// "11999999999" -> "(11) 99999-9999" (so pra exibicao; no banco fica so numero)
function format_phone($phone): string {
    $digits = preg_replace('/\D/', '', (string)$phone);
    if (strlen($digits) === 11) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
    }
    if (strlen($digits) === 10) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
    }
    return $digits;
}

// fuso da aplicacao: APP_TIMEZONE do .env (nome IANA) ou horario de Brasilia.
// o index.php e o bootstrap dos testes aplicam no PHP, e o Database usa o mesmo na conexao,
// entao PHP e MySQL nunca ficam em fusos diferentes (independe do php.ini e do servidor de banco).
function app_timezone(): string {
    $configured = (string)($_ENV['APP_TIMEZONE'] ?? '');
    return in_array($configured, timezone_identifiers_list(), true) ? $configured : 'America/Sao_Paulo';
}

// timestamp do banco -> "30/09/2026 14:05"
function format_datetime($value): string {
    return $value ? date('d/m/Y H:i', strtotime($value)) : '—';
}

// monta a url de uma rota interna, pra usar em links e actions de form
function url(string $route, array $params = []): string {
    return '/index.php?' . http_build_query(array_merge(['url' => $route], $params));
}

// como cada tipo de movimentacao aparece no extrato e nos relatorios: [rotulo, classe do selo, sinal]
function log_type_view(string $type): array {
    return match ($type) {
        'earn'     => ['Ganhou', 'badge-earn', '+'],
        'redeem'   => ['Resgatou', 'badge-redeem', '−'],
        'reversal' => ['Estorno', 'badge-reversal', '−'],
        default    => [$type, '', ''],
    };
}
