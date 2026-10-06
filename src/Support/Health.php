<?php

namespace App\Support;

use App\Database;

// rota de saude (?url=health, task 56) pro healthcheck do container e pro monitor externo.
// roda antes do session_start no index.php: checar a cada 30 s nao cria arquivo de sessao no volume.
// responde so "ok" ou "erro" (nada de detalhe do banco na resposta; o motivo vai pro log).
final class Health {
    // [status http, corpo json]. $connect abre a conexao (nos testes, um falso).
    public static function check(?callable $connect = null): array {
        if (Env::missing() !== []) {
            error_log('[health] variaveis obrigatorias do banco sem valor');
            return [503, ['status' => 'erro']];
        }

        try {
            $db = $connect ? $connect() : Database::connect();
            $db->query('SELECT 1')->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[health] banco indisponivel: ' . $e->getMessage());
            return [503, ['status' => 'erro']];
        }

        return [200, ['status' => 'ok']];
    }

    public static function respond(): void {
        [$status, $body] = self::check();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body);
    }
}
