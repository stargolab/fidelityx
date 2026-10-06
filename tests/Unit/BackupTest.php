<?php

namespace Tests\Unit;

use App\Support\Backup;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BackupTest extends TestCase {
    public function testNomeDoArquivoTemBancoDataEHora(): void {
        $now = new DateTimeImmutable('2026-10-05 11:45:00');

        $this->assertSame('fidelityx-20261005-114500.sql.gz', Backup::fileName('fidelityx', $now));
        $this->assertSame('fidelityx-20261005-114500.sql', Backup::fileName('fidelityx', $now, false));
    }

    public function testVencidosSaoSoOsBackupsDesteBancoMaisAntigosQueOPrazo(): void {
        $now = new DateTimeImmutable('2026-10-15 03:00:00');
        $files = [
            '.', '..',
            'fidelityx-20261001-030000.sql.gz',   // 14 dias: no limite, fica
            'fidelityx-20261001-025959.sql.gz',   // 1 segundo alem do prazo
            'fidelityx-20260901-030000.sql',      // antigo, sem compactar
            'fidelityx-20261014-030000.sql.gz',   // recente
            'outrobanco-20200101-030000.sql.gz',  // de outro banco
            'fidelityx-20200101-030000.sql.gz.part', // backup interrompido
            'anotacoes.txt',
        ];

        $this->assertSame(
            ['fidelityx-20261001-025959.sql.gz', 'fidelityx-20260901-030000.sql'],
            Backup::expired($files, 'fidelityx', $now, 14)
        );
    }

    public function testPrazoZeroNuncaApaga(): void {
        $now = new DateTimeImmutable('2026-10-15 03:00:00');

        $this->assertSame([], Backup::expired(['fidelityx-20200101-030000.sql.gz'], 'fidelityx', $now, 0));
    }

    // argumento de linha de comando aparece na lista de processos: a senha vai por variavel de ambiente
    public function testSenhaNaoEntraNoComando(): void {
        $command = Backup::dumpCommand('mysqldump', [
            'host' => 'db', 'port' => '3306', 'name' => 'fidelityx', 'user' => 'app', 'pass' => 'senha-do-banco',
        ]);

        $this->assertSame('mysqldump', $command[0]);
        $this->assertSame('fidelityx', end($command));
        $this->assertContains('--single-transaction', $command);
        $this->assertStringNotContainsString('senha-do-banco', implode(' ', $command));
    }
}
