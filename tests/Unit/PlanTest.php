<?php

namespace Tests\Unit;

use App\Support\Plan;
use PHPUnit\Framework\TestCase;

// task 32: o que cada plano permite
final class PlanTest extends TestCase {
    public function testFreeTemLimiteEProNao(): void {
        $this->assertSame(100, Plan::limit(Plan::FREE, Plan::CUSTOMERS));
        $this->assertSame(3, Plan::limit(Plan::FREE, Plan::ACTIVE_REWARDS));
        $this->assertNull(Plan::limit(Plan::PRO, Plan::CUSTOMERS));
        $this->assertNull(Plan::limit(Plan::PRO, Plan::ACTIVE_REWARDS));
    }

    public function testCabeMaisUmAteOLimite(): void {
        $this->assertTrue(Plan::allowsOneMore(Plan::FREE, Plan::CUSTOMERS, 0));
        $this->assertTrue(Plan::allowsOneMore(Plan::FREE, Plan::CUSTOMERS, 99));
        $this->assertFalse(Plan::allowsOneMore(Plan::FREE, Plan::CUSTOMERS, 100));
        // loja que ja passou do limite (voltou do Pro pro Free) nao adiciona, mas tambem nao da erro
        $this->assertFalse(Plan::allowsOneMore(Plan::FREE, Plan::CUSTOMERS, 250));

        $this->assertTrue(Plan::allowsOneMore(Plan::FREE, Plan::ACTIVE_REWARDS, 2));
        $this->assertFalse(Plan::allowsOneMore(Plan::FREE, Plan::ACTIVE_REWARDS, 3));

        $this->assertTrue(Plan::allowsOneMore(Plan::PRO, Plan::CUSTOMERS, 1000000));
        $this->assertTrue(Plan::allowsOneMore(Plan::PRO, Plan::ACTIVE_REWARDS, 500));
    }

    // valor estranho no banco nunca pode virar "sem limite"
    public function testPlanoDesconhecidoValeComoFree(): void {
        foreach ([null, '', 'premium', 'PRO', 'enterprise'] as $plan) {
            $this->assertSame(Plan::FREE, Plan::normalize($plan));
            $this->assertSame(100, Plan::limit($plan, Plan::CUSTOMERS));
            $this->assertFalse(Plan::allowsOneMore($plan, Plan::ACTIVE_REWARDS, 3));
        }
        $this->assertFalse(Plan::isValid('premium'));
        $this->assertTrue(Plan::isValid('pro'));
    }

    public function testNomeDoPlano(): void {
        $this->assertSame('Free', Plan::label('free'));
        $this->assertSame('Pro', Plan::label('pro'));
        $this->assertSame('Free', Plan::label(null));
    }
}
