<?php

namespace Tests\Unit;

use App\Support\RequestGuard;
use PHPUnit\Framework\TestCase;

final class RequestGuardTest extends TestCase {
    public function testParametroEmFormatoDeListaViraTextoVazio(): void {
        $this->assertSame(
            ['phone' => '', 'url' => 'merchant/customer', 'q' => '', 'page' => '2'],
            RequestGuard::dropArrayParams(['phone' => ['119'], 'url' => 'merchant/customer', 'q' => ['a' => ['b']], 'page' => '2'])
        );
    }

    public function testParametrosNormaisNaoMudam(): void {
        $params = ['phone' => '(11) 99999-8888', 'name' => 'Ana', 'consent' => '1'];
        $this->assertSame($params, RequestGuard::dropArrayParams($params));
    }
}
