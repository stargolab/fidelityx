<?php

namespace Tests\Feature;

use App\Support\Health;
use Tests\Support\HttpTestCase;

// task 56: rota de saude sem sessao, conferindo o banco
final class HealthTest extends HttpTestCase {
    public function testRespondeOkSemAbrirSessao(): void {
        [$status] = $this->get('health');

        $this->assertSame(200, $status);
        $this->assertSame('{"status":"ok"}', $this->lastBody);
        $this->assertSame('application/json; charset=utf-8', $this->lastHeaders['content-type'][0]);
        $this->assertSame('no-store', $this->lastHeaders['cache-control'][0]);
        $this->assertArrayNotHasKey('set-cookie', $this->lastHeaders, 'checagem nao cria sessao');
        $this->assertNull($this->cookie('PHPSESSID'));
    }

    public function testBancoForaDoArDa503SemDetalhe(): void {
        // o motivo vai pro log (aqui, um arquivo temporario em vez da saida do teste)
        $log = tempnam(sys_get_temp_dir(), 'fx-health');
        $previous = ini_set('error_log', $log);
        try {
            [$status, $body] = Health::check(function () {
                throw new \PDOException('SQLSTATE[HY000] [2002] Connection refused (host db, usuario root)');
            });
        } finally {
            ini_set('error_log', (string)$previous);
        }
        $this->assertStringContainsString('[health] banco indisponivel', (string)file_get_contents($log));
        @unlink($log);

        $this->assertSame(503, $status);
        $this->assertSame(['status' => 'erro'], $body);
    }

    public function testBancoNoArDaOk(): void {
        $this->assertSame([200, ['status' => 'ok']], Health::check(fn() => $this->db));
    }
}
