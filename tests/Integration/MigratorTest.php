<?php

namespace Tests\Integration;

use App\Support\Migrator;
use RuntimeException;
use Tests\Support\DatabaseTestCase;

final class MigratorTest extends DatabaseTestCase {
    private string $dir;
    private array $registered;

    protected function setUp(): void {
        parent::setUp();
        // o banco de teste nasce do schema.sql, ja com as migrations registradas: guarda pra devolver no fim
        $this->registered = $this->db->query('SELECT version FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
        $this->dir = sys_get_temp_dir() . '/fx-migrations-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void {
        $this->db->exec('DROP TABLE IF EXISTS zz_migrator_a, zz_migrator_b');
        $this->db->exec('DELETE FROM schema_migrations');
        $stmt = $this->db->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
        foreach ($this->registered as $version) {
            $stmt->execute([':version' => $version]);
        }
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    // o schema.sql e o estado final: quem cria o banco por ele nao tem migration pendente.
    // falha aqui = migration nova sem a linha correspondente no INSERT do schema.sql (ou o contrario).
    public function testSchemaSqlRegistraTodasAsMigrationsDoProjeto(): void {
        $migrator = $this->realMigrator();

        $this->assertNotEmpty($migrator->available());
        $this->assertSame([], $migrator->pending(), 'migration sem registro no schema.sql');
        $this->assertSame(
            [],
            array_values(array_diff(array_keys($migrator->applied()), $migrator->available())),
            'schema.sql registra migration que nao existe em database/migrations'
        );
    }

    public function testRodaAsPendentesEmOrdemEUmaVezSo(): void {
        $this->writeMigration('902_teste_b', 'ALTER TABLE zz_migrator_a ADD COLUMN extra INT NULL;');
        $this->writeMigration('901_teste_a', 'CREATE TABLE zz_migrator_a (id INT PRIMARY KEY);');
        $migrator = new Migrator($this->db, $this->dir);

        $this->assertSame(['901_teste_a', '902_teste_b'], $migrator->pending());
        $this->assertSame(['901_teste_a', '902_teste_b'], $migrator->migrate());

        $this->assertSame('1', (string)$this->scalar(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'zz_migrator_a' AND column_name = 'extra'"
        ));
        $this->assertSame([], $migrator->pending());
        $this->assertSame([], $migrator->migrate(), 'rodar de novo nao reaplica nada');
    }

    // dois PRs abertos ao mesmo tempo: a de numero menor chega depois e nao pode ser pulada
    public function testMigrationComNumeroMenorQueChegaDepoisAindaRoda(): void {
        $this->writeMigration('906_teste_b', 'CREATE TABLE zz_migrator_b (id INT PRIMARY KEY);');
        $migrator = new Migrator($this->db, $this->dir);
        $migrator->migrate();

        $this->writeMigration('904_teste_a', 'CREATE TABLE zz_migrator_a (id INT PRIMARY KEY);');

        $this->assertSame(['904_teste_a'], $migrator->pending());
        $this->assertSame(['904_teste_a'], $migrator->migrate());
    }

    // os arquivos tem "USE fidelityx": a migration precisa rodar no banco da conexao, nao nesse
    public function testIgnoraOUseDoArquivoERodaNoBancoDaConexao(): void {
        $this->writeMigration('901_teste_a', "-- comentario\nUSE fidelityx;\n\nCREATE TABLE zz_migrator_a (id INT PRIMARY KEY);");
        (new Migrator($this->db, $this->dir))->migrate();

        $this->assertSame('1', (string)$this->scalar(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'zz_migrator_a'"
        ));
        $this->assertSame($_ENV['DB_NAME'], $this->scalar('SELECT DATABASE()'));
    }

    public function testMigrationQueFalhaNaoERegistrada(): void {
        $this->writeMigration('901_teste_a', 'CREATE TABLE zz_migrator_a (id INT PRIMARY KEY); ALTER TABLE tabela_que_nao_existe ADD COLUMN x INT;');
        $migrator = new Migrator($this->db, $this->dir);

        try {
            $migrator->migrate();
            $this->fail('era pra falhar');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('901_teste_a falhou no comando 2 de 2', $e->getMessage());
        }

        $this->assertSame(['901_teste_a'], $migrator->pending());
        // a trava foi liberada: da pra tentar de novo depois de corrigir
        $this->assertSame('1', (string)$this->scalar("SELECT IS_FREE_LOCK(CONCAT('fidelityx_migrate_', DATABASE()))"));
    }

    // banco criado antes do controle: tem as tabelas, mas nenhum registro. nao da pra adivinhar o que ja rodou
    public function testBancoAnteriorAoControlePedeBaseline(): void {
        $this->db->exec('DELETE FROM schema_migrations');
        $migrator = $this->realMigrator();

        $this->assertTrue($migrator->needsBaseline());
        try {
            $migrator->migrate();
            $this->fail('era pra pedir o baseline');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('baseline', $e->getMessage());
        }
        $this->assertSame([], $migrator->applied(), 'nada roda nem e registrado sem o baseline');
    }

    public function testBaselineMarcaAteAMigrationInformadaSemRodar(): void {
        $this->db->exec('DELETE FROM schema_migrations');
        $migrator = $this->realMigrator();

        $this->assertSame(['001_mvp', '002_rate_limit'], $migrator->baseline('002'));
        $this->assertFalse($migrator->needsBaseline());
        $this->assertNotContains('001_mvp', $migrator->pending());
        $this->assertContains('003_lgpd', $migrator->pending());

        $this->assertSame(['003_lgpd'], $migrator->baseline('003_lgpd'), 'aceita o nome completo e nao repete as ja marcadas');
    }

    public function testBaselineDeMigrationQueNaoExiste(): void {
        $this->expectException(RuntimeException::class);
        $this->realMigrator()->baseline('999');
    }

    public function testArquivoForaDoPadraoDeNomeEIgnorado(): void {
        $this->writeMigration('rascunho', 'CREATE TABLE zz_migrator_a (id INT PRIMARY KEY);');
        $this->writeMigration('901_teste_a', 'CREATE TABLE zz_migrator_b (id INT PRIMARY KEY);');

        $this->assertSame(['901_teste_a'], (new Migrator($this->db, $this->dir))->available());
    }

    private function realMigrator(): Migrator {
        return new Migrator($this->db, dirname(__DIR__, 2) . '/database/migrations');
    }

    private function writeMigration(string $name, string $sql): void {
        file_put_contents($this->dir . '/' . $name . '.sql', $sql);
    }
}
