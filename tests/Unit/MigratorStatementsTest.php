<?php

namespace Tests\Unit;

use App\Support\Migrator;
use PHPUnit\Framework\TestCase;

// task 52: o ';' so separa comandos fora de texto e de comentario
final class MigratorStatementsTest extends TestCase {
    public function testSeparaComandosETiraUseECreateDatabase(): void {
        $sql = "CREATE DATABASE IF NOT EXISTS x;\nUSE x;\nALTER TABLE a ADD b INT;\n\nALTER TABLE a ADD c INT;\n";

        $this->assertSame(['ALTER TABLE a ADD b INT', 'ALTER TABLE a ADD c INT'], Migrator::statements($sql));
    }

    public function testPontoEVirgulaDentroDeTextoNaoCortaOComando(): void {
        $sql = "ALTER TABLE a ADD b VARCHAR(10) DEFAULT 'x;y' COMMENT \"um; dois\";\n"
             . "UPDATE a SET b = 'it''s; ok', c = 'barra \\'; ainda texto';\n"
             . "ALTER TABLE `t;1` ADD d INT;";

        $this->assertSame([
            "ALTER TABLE a ADD b VARCHAR(10) DEFAULT 'x;y' COMMENT \"um; dois\"",
            "UPDATE a SET b = 'it''s; ok', c = 'barra \\'; ainda texto'",
            'ALTER TABLE `t;1` ADD d INT',
        ], Migrator::statements($sql));
    }

    public function testComentariosSaemMesmoComPontoEVirgula(): void {
        $sql = "-- comentario; com ponto e virgula\n"
             . "ALTER TABLE a ADD b INT; -- no fim da linha; tambem\n"
             . "# estilo mysql; aqui\n"
             . "/* bloco; de\n varias linhas; */ ALTER TABLE a ADD c INT;";

        $this->assertSame(['ALTER TABLE a ADD b INT', 'ALTER TABLE a ADD c INT'], Migrator::statements($sql));
    }

    public function testSubtracaoNaoViraComentario(): void {
        // "--" sem espaco depois nao e comentario no mysql
        $this->assertSame(['UPDATE a SET b = c--1'], Migrator::statements('UPDATE a SET b = c--1;'));
    }

    public function testAsMigrationsDoProjetoContinuamComOsMesmosComandos(): void {
        foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') as $file) {
            $statements = Migrator::statements((string)file_get_contents($file));
            $this->assertNotEmpty($statements, basename($file));
            foreach ($statements as $statement) {
                $this->assertDoesNotMatchRegularExpression('/^\s*--/m', $statement, basename($file));
            }
        }
    }
}
