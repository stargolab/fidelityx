<?php

namespace Tests\Integration;

use App\Models\LoyaltyCardModel;
use App\Models\PointsLogModel;
use Tests\Support\DatabaseTestCase;

final class CustomerSearchTest extends DatabaseTestCase {
    private function addCustomer(int $merchant, string $name, string $phone): int {
        return $this->createCard($merchant, $name, $phone);
    }

    private function names(int $merchant, string $search, int $limit = 50, int $offset = 0): array {
        $rows = (new LoyaltyCardModel($this->db))->searchByMerchant($merchant, $search, $limit, $offset);
        $names = array_column($rows, 'name');
        sort($names);
        return $names;
    }

    public function testBuscaPorNomeIgnoraAcentoECaixa(): void {
        $m = $this->createMerchant();
        $this->addCustomer($m, 'João Silva', '11911110001');
        $this->addCustomer($m, 'Maria Souza', '11911110002');

        $this->assertSame(['João Silva'], $this->names($m, 'joao'));
        $this->assertSame(['Maria Souza'], $this->names($m, 'SOUZA'));
    }

    public function testBuscaPorTelefoneComOuSemMascara(): void {
        $m = $this->createMerchant();
        $this->addCustomer($m, 'Ana', '11911110001');
        $this->addCustomer($m, 'Bia', '11922220002');

        $this->assertSame(['Ana'], $this->names($m, '1111'));
        $this->assertSame(['Bia'], $this->names($m, '(11) 92222-0002'));
    }

    public function testCoringasDoLikeValemComoLetra(): void {
        $m = $this->createMerchant();
        $this->addCustomer($m, 'Ana', '11911110001');
        $this->addCustomer($m, '100% Pao', '11911110002');

        $this->assertSame(['100% Pao'], $this->names($m, '%'));
        $this->assertSame([], $this->names($m, '_na'), 'underscore nao e coringa de um caractere');
    }

    public function testBuscaSoEnxergaClientesDaPropriaLoja(): void {
        $a = $this->createMerchant('a@teste.test');
        $b = $this->createMerchant('b@teste.test');
        $this->addCustomer($a, 'Ana', '11911110001');
        $this->addCustomer($b, 'Ana Outra', '11922220002');

        $this->assertSame(['Ana'], $this->names($a, 'ana'));
        $this->assertSame(1, (new LoyaltyCardModel($this->db))->countByMerchant($a, 'ana'));
    }

    public function testPaginaOsClientesSemRepetirNemPerder(): void {
        $m = $this->createMerchant();
        for ($i = 1; $i <= 5; $i++) {
            $this->addCustomer($m, "Cliente $i", '1191111000' . $i);
        }
        $model = new LoyaltyCardModel($this->db);

        $this->assertSame(5, $model->countByMerchant($m));
        $seen = [];
        foreach ([0, 2, 4] as $offset) {
            foreach ($model->searchByMerchant($m, '', 2, $offset) as $row) {
                $seen[] = $row['name'];
            }
        }
        sort($seen);
        $this->assertSame(['Cliente 1', 'Cliente 2', 'Cliente 3', 'Cliente 4', 'Cliente 5'], $seen);
    }

    public function testHistoricoPaginado(): void {
        $m = $this->createMerchant();
        $card = $this->addCustomer($m, 'Ana', '11911110001');
        $cards = new LoyaltyCardModel($this->db);
        for ($i = 1; $i <= 5; $i++) {
            $cards->addPoints($card, $i, "Compra $i");
        }
        $log = new PointsLogModel($this->db);

        $this->assertSame(5, $log->countByMerchant($m));
        $this->assertSame(['Compra 5', 'Compra 4'], array_column($log->pageByMerchant($m, 2, 0), 'description'));
        $this->assertSame(['Compra 1'], array_column($log->pageByMerchant($m, 2, 4), 'description'));
    }
}
