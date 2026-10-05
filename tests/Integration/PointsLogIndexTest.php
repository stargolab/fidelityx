<?php

namespace Tests\Integration;

use App\Models\LoyaltyCardModel;
use App\Models\PointsLogModel;
use App\Support\Migrator;
use Tests\Support\DatabaseTestCase;

// indice do extrato paginado (task 40): schema.sql e migration 006 precisam criar o mesmo indice
final class PointsLogIndexTest extends DatabaseTestCase {
    public function testIndiceDoExtratoComecaPeloCartaoESegueAData(): void {
        $this->assertSame(['card_id', 'created_at'], $this->indexColumns('idx_points_log_card_created'));
        $this->assertSame([], $this->indexColumns('idx_points_log_card'), 'o indice antigo so de card_id foi substituido');
    }

    public function testMigrationLevaOBancoAntigoAoMesmoIndiceDoSchema(): void {
        // volta a tabela ao estado anterior a migration 006 (so o indice em card_id)
        $this->db->exec('ALTER TABLE points_log ADD KEY idx_points_log_card (card_id), DROP KEY idx_points_log_card_created');

        $sql = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/006_points_log_extrato.sql');
        foreach (Migrator::statements($sql) as $statement) {
            $this->db->exec($statement);
        }

        $this->assertSame(['card_id', 'created_at'], $this->indexColumns('idx_points_log_card_created'));
        $this->assertSame([], $this->indexColumns('idx_points_log_card'));
    }

    // a pagina do extrato sai ja na ordem do indice: sem ordenar todas as linhas do cartao a cada pagina
    public function testExtratoUsaOIndiceSemOrdenarDeNovo(): void {
        $merchantId = $this->createMerchant();
        $cardId = $this->createCard($merchantId, 'Ana', '11911110001');
        $cards = new LoyaltyCardModel($this->db);
        for ($i = 0; $i < 30; $i++) {
            $cards->addPoints($cardId, 1, 'compra');
        }
        // movimentacoes de outros clientes: o cartao consultado e uma parte pequena da tabela, como na vida real
        $insert = $this->db->prepare(
            "INSERT INTO points_log (card_id, type, quantity, description)
             SELECT :card_id, 'earn', 1, 'compra' FROM points_log LIMIT 30"
        );
        for ($i = 2; $i <= 11; $i++) {
            $insert->execute([':card_id' => $this->createCard($merchantId, "Cliente $i", sprintf('119111100%02d', $i))]);
        }
        $this->db->query('ANALYZE TABLE points_log')->fetchAll();

        $plan = $this->db->query(
            'EXPLAIN SELECT type, quantity, description, created_at FROM points_log
             WHERE card_id = ' . (int)$cardId . ' ORDER BY created_at DESC, id DESC LIMIT 20 OFFSET 0'
        )->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('idx_points_log_card_created', $plan['key']);
        $this->assertStringNotContainsStringIgnoringCase('filesort', (string)$plan['Extra']);

        // e a ordem continua a mesma: da mais recente pra mais antiga
        $page = (new PointsLogModel($this->db))->pageByCard($cardId, 20, 0);
        $this->assertCount(20, $page);
    }

    private function indexColumns(string $index): array {
        $stmt = $this->db->prepare(
            'SELECT column_name FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index
             ORDER BY seq_in_index'
        );
        $stmt->execute([':table' => 'points_log', ':index' => $index]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
