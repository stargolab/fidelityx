<?php

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

// issue #24: PHP e MySQL precisam estar no mesmo fuso, definido pela aplicacao
final class TimezoneTest extends DatabaseTestCase {
    public function testPhpUsaOFusoDaAplicacao(): void {
        $this->assertSame(app_timezone(), date_default_timezone_get());
    }

    public function testFusoPadraoEHorarioDeBrasilia(): void {
        $previous = $_ENV['APP_TIMEZONE'] ?? null;
        unset($_ENV['APP_TIMEZONE']);
        $this->assertSame('America/Sao_Paulo', app_timezone());

        $_ENV['APP_TIMEZONE'] = 'Fuso/Inexistente';
        $this->assertSame('America/Sao_Paulo', app_timezone(), 'nome invalido cai no padrao');

        $_ENV['APP_TIMEZONE'] = 'America/Manaus';
        $this->assertSame('America/Manaus', app_timezone());

        if ($previous === null) {
            unset($_ENV['APP_TIMEZONE']);
        } else {
            $_ENV['APP_TIMEZONE'] = $previous;
        }
    }

    public function testConexaoMysqlNoMesmoOffsetDoPhp(): void {
        $expected = (new \DateTimeImmutable('now'))->format('P');
        $this->assertSame($expected, $this->scalar('SELECT @@session.time_zone'));
    }

    public function testHorarioGravadoPeloBancoAparecePeloRelogioDoPhp(): void {
        $merchant = $this->createMerchant();
        $created = $this->scalar('SELECT created_at FROM merchants WHERE id = :id', [':id' => $merchant]);

        // o que a tela mostra (format_datetime) bate com o relogio do PHP, com folga de 1 minuto
        $shown = \DateTimeImmutable::createFromFormat('d/m/Y H:i', format_datetime($created));
        $this->assertLessThanOrEqual(60, abs($shown->getTimestamp() - (new \DateTimeImmutable())->setTime((int)date('H'), (int)date('i'))->getTimestamp()));
    }
}
