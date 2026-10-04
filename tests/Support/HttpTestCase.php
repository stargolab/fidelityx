<?php

namespace Tests\Support;

use RuntimeException;

// base dos testes de fluxo: sobe um php -S proprio (porta livre, banco de teste) e conversa com ele
// por http como um navegador, com cookie de sessao e o _csrf de cada formulario.
abstract class HttpTestCase extends DatabaseTestCase {
    private static $server = null;
    private static string $baseUrl;
    private string $cookieJar;
    protected string $lastBody = '';
    // cabecalhos da ultima resposta: nome em minusculas => lista de valores (set-cookie pode vir repetido)
    protected array $lastHeaders = [];

    public static function setUpBeforeClass(): void {
        $port = self::freePort();
        $root = dirname(__DIR__, 2);

        // credenciais e banco de teste vao pro servidor pelas variaveis de ambiente (lidas no router.php)
        $env = getenv();
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
            $env[$key] = (string)($_ENV[$key] ?? '');
        }

        // comando em array: roda o php direto, sem shell no meio (assim o proc_terminate mata o servidor mesmo)
        self::$server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', "$root/public", __DIR__ . '/router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', self::nullDevice(), 'w'], 2 => ['file', self::nullDevice(), 'w']],
            $pipes,
            $root,
            $env
        );
        self::$baseUrl = "http://127.0.0.1:$port";

        // espera o servidor aceitar conexao (ate 5s)
        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($socket) {
                fclose($socket);
                return;
            }
            usleep(100000);
        }
        throw new RuntimeException("servidor de teste nao subiu na porta $port");
    }

    public static function tearDownAfterClass(): void {
        if (self::$server) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    protected function setUp(): void {
        parent::setUp();
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'fx-cookie');
    }

    protected function tearDown(): void {
        @unlink($this->cookieJar);
    }

    // sessao nova (como apagar o cookie no navegador)
    protected function newSession(): void {
        @unlink($this->cookieJar);
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'fx-cookie');
    }

    // GET sem seguir redirect. devolve [status, destino do redirect sem o host]
    protected function get(string $route): array {
        return $this->request('GET', $route);
    }

    // POST de formulario. com $withCsrf, busca antes o _csrf no GET da $csrfFrom (ou da propria rota)
    protected function post(string $route, array $fields, bool $withCsrf = true, ?string $csrfFrom = null): array {
        if ($withCsrf) {
            $this->get($csrfFrom ?? $route);
            $fields['_csrf'] = $this->csrfToken();
        }
        return $this->request('POST', $route, $fields);
    }

    protected function csrfToken(): string {
        if (!preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->lastBody, $m)) {
            // mostra o comeco da resposta: quase sempre e uma pagina de erro (500/503) e nao o formulario
            $title = preg_match('/<title>(.*?)<\/title>/s', $this->lastBody, $t) ? trim($t[1]) : '';
            throw new RuntimeException("pagina sem campo _csrf (title: '$title'): " . substr(strip_tags($this->lastBody), 0, 200));
        }
        return $m[1];
    }

    protected function loginAs(string $email, string $password = 'teste123'): void {
        [$status, $location] = $this->post('merchant/login', ['email' => $email, 'password' => $password]);
        $this->assertSame(302, $status);
        $this->assertSame('merchant/dashboard&success=logged', $location, 'login falhou');
    }

    private function request(string $method, string $route, array $fields = []): array {
        $ch = curl_init(self::$baseUrl . '/index.php?url=' . $route);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $headers = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $line) use (&$headers) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))][] = trim($value);
            }
            return strlen($line);
        });
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }

        $body = curl_exec($ch);
        if ($body === false) {
            throw new RuntimeException('falha http: ' . curl_error($ch));
        }
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $redirect = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        $this->lastBody = $body;
        $this->lastHeaders = $headers;

        // "http://127.0.0.1:1234/index.php?url=merchant%2Flogin&error=x" -> "merchant/login&error=x"
        $location = $redirect === '' ? null : urldecode(preg_replace('#^.*index\.php\?url=#', '', $redirect));
        return [$status, $location];
    }

    private static function freePort(): int {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        return $port;
    }

    private static function nullDevice(): string {
        return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    }
}
