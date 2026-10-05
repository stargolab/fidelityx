<?php

namespace Tests\Integration;

use App\Support\Backup;
use App\Support\Env;
use DateTimeImmutable;
use RuntimeException;
use Tests\Support\DatabaseTestCase;

// backup de verdade do banco de teste. precisa do mysqldump (PATH, MYSQLDUMP_BIN ou XAMPP);
// sem ele o teste e pulado (no CI o cliente do mysql ja vem instalado).
final class BackupRunTest extends DatabaseTestCase {
    private string $dir;
    private string $bin;

    protected function setUp(): void {
        parent::setUp();
        $bin = Backup::findDump(Env::get('MYSQLDUMP_BIN'));
        if ($bin === null) {
            $this->markTestSkipped('mysqldump nao encontrado');
        }
        $this->bin = $bin;
        $this->dir = sys_get_temp_dir() . '/fx-backup-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void {
        if (isset($this->dir) && is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            rmdir($this->dir);
        }
    }

    public function testBackupTemAEstruturaEOsDadosDoBanco(): void {
        $this->createMerchant('backup@teste.test');
        $now = new DateTimeImmutable('2026-10-05 03:00:00');

        $result = Backup::run($this->config(), $this->dir, 14, $this->bin, $now);

        $this->assertSame(Backup::fileName($_ENV['DB_NAME'], $now, function_exists('gzopen')), basename($result['file']));
        $sql = $this->read($result['file']);
        $this->assertStringContainsString('CREATE TABLE `merchants`', $sql);
        $this->assertStringContainsString('CREATE TABLE `schema_migrations`', $sql);
        $this->assertStringContainsString('backup@teste.test', $sql);
        $this->assertSame([], glob($this->dir . '/*.part'), 'nao sobra arquivo parcial');
    }

    public function testApagaSoOsBackupsVencidosDepoisDeGravarONovo(): void {
        mkdir($this->dir);
        $db = $_ENV['DB_NAME'];
        touch("$this->dir/$db-20260901-030000.sql.gz"); // vencido
        touch("$this->dir/$db-20261001-030000.sql.gz"); // dentro do prazo
        touch("$this->dir/anotacoes.txt");

        $result = Backup::run($this->config(), $this->dir, 14, $this->bin, new DateTimeImmutable('2026-10-05 03:00:00'));

        $this->assertSame(["$db-20260901-030000.sql.gz"], $result['removed']);
        $this->assertFileDoesNotExist("$this->dir/$db-20260901-030000.sql.gz");
        $this->assertFileExists("$this->dir/$db-20261001-030000.sql.gz");
        $this->assertFileExists("$this->dir/anotacoes.txt");
        $this->assertFileExists($result['file']);
    }

    // credencial errada: nao pode ficar arquivo com cara de backup, nem apagar os antigos
    public function testFalhaNaoDeixaArquivoNemApagaBackupAntigo(): void {
        mkdir($this->dir);
        $old = $this->dir . '/' . $_ENV['DB_NAME'] . '-20260901-030000.sql.gz';
        touch($old);

        try {
            Backup::run(['user' => 'usuario_que_nao_existe', 'pass' => 'errada'] + $this->config(), $this->dir, 14, $this->bin);
            $this->fail('era pra falhar');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('mysqldump falhou', $e->getMessage());
        }

        $this->assertSame([$old], glob($this->dir . '/*'));
    }

    private function config(): array {
        return [
            'host' => $_ENV['DB_HOST'],
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'name' => $_ENV['DB_NAME'],
            'user' => $_ENV['DB_USER'],
            'pass' => $_ENV['DB_PASS'] ?? '',
        ];
    }

    private function read(string $file): string {
        return str_ends_with($file, '.gz') ? implode('', gzfile($file)) : (string)file_get_contents($file);
    }
}
