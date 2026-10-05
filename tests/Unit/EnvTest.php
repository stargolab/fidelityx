<?php

namespace Tests\Unit;

use App\Support\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase {
    private array $envBackup;
    private array $serverBackup;
    private string $dir;

    protected function setUp(): void {
        $this->envBackup = $_ENV;
        $this->serverBackup = $_SERVER;
        $this->dir = sys_get_temp_dir() . '/fx-env-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void {
        $_ENV = $this->envBackup;
        $_SERVER = $this->serverBackup;
        putenv('LOG_FILE');
        @unlink($this->dir . '/.env');
        rmdir($this->dir);
    }

    // producao (docker) nao tem .env: a aplicacao precisa subir so com variaveis de ambiente
    public function testSemArquivoEnvNaoDaErro(): void {
        Env::load($this->dir);

        $this->assertFileDoesNotExist($this->dir . '/.env');
    }

    // o php.ini de producao nao poe as variaveis de ambiente no $_ENV sozinho
    public function testVariavelDeAmbienteRealEntraNoEnv(): void {
        unset($_ENV['LOG_FILE']);
        putenv('LOG_FILE=/var/log/fidelityx.log');

        Env::load($this->dir);

        $this->assertSame('/var/log/fidelityx.log', $_ENV['LOG_FILE']);
    }

    public function testArquivoEnvSoCompletaOQueFalta(): void {
        file_put_contents($this->dir . '/.env', "APP_URL=https://do-arquivo.test\nBACKUP_KEEP_DAYS=7\n");
        $_ENV['APP_URL'] = 'https://do-ambiente.test';
        unset($_ENV['BACKUP_KEEP_DAYS'], $_SERVER['BACKUP_KEEP_DAYS']);

        Env::load($this->dir);

        $this->assertSame('https://do-ambiente.test', $_ENV['APP_URL'], 'o ambiente tem prioridade sobre o .env');
        $this->assertSame('7', $_ENV['BACKUP_KEEP_DAYS']);
    }

    public function testObrigatoriasSemValor(): void {
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = '  ';
        unset($_ENV['DB_USER']);

        $this->assertSame(['DB_NAME', 'DB_USER'], Env::missing());

        $_ENV['DB_NAME'] = 'fidelityx';
        $_ENV['DB_USER'] = 'root';
        unset($_ENV['DB_PASS']); // senha vazia e aceita (XAMPP)
        $this->assertSame([], Env::missing());
    }

    public function testGetUsaOPadraoQuandoVazio(): void {
        $_ENV['BACKUP_DIR'] = '';
        $this->assertSame('/padrao', Env::get('BACKUP_DIR', '/padrao'));

        $_ENV['BACKUP_DIR'] = ' /backups ';
        $this->assertSame('/backups', Env::get('BACKUP_DIR', '/padrao'));
    }
}
