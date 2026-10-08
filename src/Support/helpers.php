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

// "52998224725" -> "529.982.247-25"; "11222333000181" -> "11.222.333/0001-81" (so pra exibicao)
function format_document($document): string {
    $digits = preg_replace('/\D/', '', (string)$document);
    if (strlen($digits) === 11) {
        return sprintf('%s.%s.%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 3), substr($digits, 9));
    }
    if (strlen($digits) === 14) {
        return sprintf('%s.%s.%s/%s-%s', substr($digits, 0, 2), substr($digits, 2, 3), substr($digits, 5, 3), substr($digits, 8, 4), substr($digits, 12));
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

// marca o campo que causou o erro: devolve os atributos pra colar dentro do <input> quando o
// codigo de erro da tela aponta pro campo $field (pelo name), ou texto vazio.
// aria-invalid pinta a borda (components.css) e avisa o leitor de tela; aria-describedby liga o
// campo a mensagem (o id="flash-error" do partials/flash.php).
// $code: por padrao o ?error= da url; tela que nao usa flash (consulta publica) passa o proprio codigo.
// codigo novo de erro ligado a um campo entra neste mapa.
function field_error_attr(string $field, ?string $code = null): string {
    static $fields = [
        'documento_invalido'        => ['document'],
        'email_invalido'            => ['email'],
        'senha_curta'               => ['password', 'new_password'],
        'senhas_diferentes'         => ['password', 'password_confirm', 'new_password', 'new_password_confirm'],
        'ja_cadastrado'             => ['email', 'document'],
        'credenciais_invalidas'     => ['email', 'password'],
        'senha_atual_incorreta'     => ['current_password'],
        'telefone_invalido'         => ['phone'],
        'cliente_nao_encontrado'    => ['phone'],
        'loja_invalida'             => ['loja'],
        'nome_obrigatorio'          => ['name'],
        'consentimento_obrigatorio' => ['consent'],
        'confirmacao_obrigatoria'   => ['confirm'],
        'pontos_invalidos'          => ['points'],
        'valor_invalido'            => ['amount'],
        'valor_sem_pontos'          => ['amount'],
        'regra_invalida'            => ['rule'],
    ];

    $code ??= $_GET['error'] ?? null;
    if (!is_string($code) || !in_array($field, $fields[$code] ?? [], true)) {
        return '';
    }
    return ' aria-invalid="true" aria-describedby="flash-error"';
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
