<?php

namespace App\Support;

use App\Controllers\ErrorController;

// registro de erros: tudo que da errado vai pro log do PHP (nunca pra tela) e o usuario ve a pagina 500
// com um codigo curto, que e o mesmo gravado na linha do log (da pra achar o erro que a pessoa relatou).
//
// destino do log: LOG_FILE, se configurado; senao o error_log do php.ini (no docker, a saida do container).
// warnings, notices e erros fatais o proprio PHP grava no mesmo lugar (log_errors).
final class ErrorLog {
    private const FATAL = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    private const TRACE_LIMIT = 15;

    public static function register(): void {
        ini_set('log_errors', '1');
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    // manda o log pra um arquivo. se a pasta nao existir ou nao der pra escrever, fica o destino
    // do php.ini e o problema e avisado la (melhor do que perder os erros em silencio).
    public static function useFile(string $path): bool {
        if ($path === '') {
            return false;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (!is_dir($dir) || !is_writable($dir) || (file_exists($path) && !is_writable($path))) {
            error_log("[ErrorLog] LOG_FILE sem permissao de escrita: $path");
            return false;
        }

        ini_set('error_log', $path);
        return true;
    }

    // excecao que ninguem tratou: log com contexto + pagina 500
    public static function handleException(\Throwable $e): void {
        $id = self::newId();
        error_log(self::format($e, $id, $_SERVER, $_GET));
        self::renderFailure($id);
    }

    // erro fatal (memoria, funcao inexistente...): o PHP ja gravou o erro; aqui entra o contexto
    // da requisicao e a pagina 500 no lugar da tela em branco.
    public static function handleShutdown(): void {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], self::FATAL, true)) {
            return;
        }

        $id = self::newId();
        error_log(sprintf(
            '[fatal %s] %s em %s:%d | %s',
            $id,
            $error['message'],
            self::relativePath($error['file']),
            $error['line'],
            self::requestSummary($_SERVER, $_GET)
        ));
        self::renderFailure($id);
    }

    // uma linha por erro: codigo, classe, mensagem, onde aconteceu, requisicao e o caminho das chamadas.
    // da requisicao entra so o metodo e a rota: a query string e o corpo podem ter telefone e senha.
    // do caminho das chamadas entram so arquivo, linha e funcao, nunca os argumentos (mesmo motivo).
    public static function format(\Throwable $e, string $id, array $server, array $query): string {
        $frames = [];
        foreach (array_slice($e->getTrace(), 0, self::TRACE_LIMIT) as $frame) {
            $where = isset($frame['file']) ? self::relativePath($frame['file']) . ':' . ($frame['line'] ?? 0) : '[interno]';
            $frames[] = $where . ' ' . ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'] . '()';
        }

        return sprintf(
            '[uncaught %s] %s: %s em %s:%d | %s | %s',
            $id,
            get_class($e),
            self::oneLine($e->getMessage()),
            self::relativePath($e->getFile()),
            $e->getLine(),
            self::requestSummary($server, $query),
            $frames === [] ? 'sem chamadas' : implode(' <- ', $frames)
        );
    }

    // "POST merchant/customer" (ou "cli" fora de requisicao http)
    private static function requestSummary(array $server, array $query): string {
        if (!isset($server['REQUEST_METHOD'])) {
            return 'cli';
        }
        $route = $query['url'] ?? '';
        $route = is_string($route) && preg_match('#^[A-Za-z0-9/_-]{1,60}$#D', $route) ? $route : '-';
        return preg_replace('/[^A-Z]/', '', strtoupper((string)$server['REQUEST_METHOD'])) . ' ' . $route;
    }

    private static function renderFailure(string $id): void {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }
        // descarta o pedaco de pagina que ja estava montado
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        (new ErrorController())->handle(500, $id);
    }

    private static function newId(): string {
        return bin2hex(random_bytes(4));
    }

    private static function relativePath(string $file): string {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $file = str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
        return str_replace('\\', '/', $file);
    }

    private static function oneLine(string $text): string {
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
