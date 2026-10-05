<?php

namespace Tests\Unit;

use App\Support\ClientIp;
use PHPUnit\Framework\TestCase;

final class ClientIpTest extends TestCase {
    public function testSemProxyConfiadoValeOIpDaConexao(): void {
        $server = ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'];

        // qualquer um pode mandar X-Forwarded-For: sem TRUSTED_PROXIES ele e ignorado
        $this->assertSame('203.0.113.9', ClientIp::resolve($server, ''));
    }

    public function testCabecalhoDeQuemNaoEProxyConfiadoEIgnorado(): void {
        $server = ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'];

        $this->assertSame('203.0.113.9', ClientIp::resolve($server, '10.0.0.1'));
    }

    public function testAtrasDeProxyConfiadoValeOIpDoCliente(): void {
        $server = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'];

        $this->assertSame('198.51.100.7', ClientIp::resolve($server, '10.0.0.1'));
    }

    public function testIpInventadoPeloClienteNoComecoDoCabecalhoNaoVale(): void {
        // o cliente mandou "X-Forwarded-For: 1.1.1.1" e o proxy acrescentou o ip real dele no fim
        $server = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 198.51.100.7'];

        $this->assertSame('198.51.100.7', ClientIp::resolve($server, '10.0.0.1'));
    }

    public function testCadeiaDeProxiesConfiadosEmFaixaCidr(): void {
        $server = ['REMOTE_ADDR' => '172.18.0.3', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, 172.18.0.9, 10.1.2.3'];

        $this->assertSame('198.51.100.7', ClientIp::resolve($server, '172.16.0.0/12, 10.0.0.0/8'));
    }

    public function testProxyConfiadoSemCabecalhoFicaComOIpDoProxy(): void {
        $this->assertSame('10.0.0.1', ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.1'], '10.0.0.1'));
    }

    public function testCabecalhoMalformadoNaoViraIp(): void {
        $server = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, <script>'];

        $this->assertSame('10.0.0.1', ClientIp::resolve($server, '10.0.0.1'));
    }

    public function testTodosOsSaltosConfiadosDevolveOMaisDistante(): void {
        $server = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '10.0.0.3, 10.0.0.2'];

        $this->assertSame('10.0.0.3', ClientIp::resolve($server, '10.0.0.0/24'));
    }

    public function testIpv6(): void {
        $server = ['REMOTE_ADDR' => 'fd00::1', 'HTTP_X_FORWARDED_FOR' => '2001:db8::7'];

        $this->assertSame('2001:db8::7', ClientIp::resolve($server, 'fd00::/8'));
        $this->assertFalse(ClientIp::inRange('10.0.0.1', 'fd00::/8'), 'ipv4 nao casa com faixa ipv6');
    }

    public function testSemRemoteAddr(): void {
        $this->assertSame('desconhecido', ClientIp::resolve([], '10.0.0.1'));
    }

    public function testFaixaCidr(): void {
        $this->assertTrue(ClientIp::inRange('172.31.255.255', '172.16.0.0/12'));
        $this->assertFalse(ClientIp::inRange('172.32.0.1', '172.16.0.0/12'));
        $this->assertTrue(ClientIp::inRange('192.168.1.130', '192.168.1.128/25'));
        $this->assertFalse(ClientIp::inRange('192.168.1.127', '192.168.1.128/25'));
        $this->assertFalse(ClientIp::inRange('nao-e-ip', '10.0.0.0/8'));
    }

    public function testEntradaInvalidaDaListaEDescartada(): void {
        $this->assertSame(
            ['10.0.0.1', '172.16.0.0/12'],
            ClientIp::parseList(' 10.0.0.1 , proxy.interno, 172.16.0.0/12, 10.0.0.0/99, ')
        );
    }

    public function testHttpsDireto(): void {
        $this->assertTrue(ClientIp::isHttps(['HTTPS' => 'on', 'REMOTE_ADDR' => '203.0.113.9'], ''));
        $this->assertFalse(ClientIp::isHttps(['HTTPS' => 'off', 'REMOTE_ADDR' => '203.0.113.9'], ''));
        $this->assertFalse(ClientIp::isHttps(['REMOTE_ADDR' => '203.0.113.9'], ''));
    }

    public function testHttpsAvisadoPeloProxySoValeSeOProxyForConfiado(): void {
        $server = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https'];

        $this->assertTrue(ClientIp::isHttps($server, '10.0.0.1'));
        $this->assertFalse(ClientIp::isHttps($server, ''), 'sem proxy confiado o cabecalho e ignorado');
        $this->assertFalse(ClientIp::isHttps(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'http'], '10.0.0.1'));
    }
}
