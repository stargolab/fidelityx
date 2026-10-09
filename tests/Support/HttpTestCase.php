<?php

namespace Tests\Support;

use RuntimeException;

// base dos testes de fluxo: sobe um php -S proprio (porta livre, banco de teste) e conversa com ele
// por http como um navegador, com cookie de sessao e o _csrf de cada formulario.
abstract class HttpTestCase extends DatabaseTestCase {
    private static $server = null;
    private static string $baseUrl;
    // e-mails "enviados" pelo servidor de teste (MAIL_DRIVER=log), uma linha json por mensagem
    private static string $mailLog;
    private string $cookieJar;
    protected string $lastBody = '';
    // cabecalhos da ultima resposta: nome em minusculas => lista de valores (set-cookie pode vir repetido)
    protected array $lastHeaders = [];
    // ip do cliente simulado nas proximas requisicoes (null = o ip real da conexao, 127.0.0.1)
    private ?string $clientIp = null;
    // cabecalhos extras das proximas requisicoes (ex.: X-Forwarded-Proto de um proxy https)
    private array $extraHeaders = [];

    public static function setUpBeforeClass(): void {
        $port = self::freePort();
        $root = dirname(__DIR__, 2);

        // credenciais e banco de teste vao pro servidor pelas variaveis de ambiente (lidas no router.php)
        $env = getenv();
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
            $env[$key] = (string)($_ENV[$key] ?? '');
        }
        // o teste e o "proxy confiavel" do servidor: com isso um teste pode se passar por clientes de
        // ips diferentes mandando X-Forwarded-For (ver fromIp). sem o cabecalho nada muda: vale o 127.0.0.1.
        $env['TRUSTED_PROXIES'] = '127.0.0.1';
        // e-mail transacional vai pra um arquivo que o teste le (sentMails), nunca pra fora
        self::$mailLog = tempnam(sys_get_temp_dir(), 'fx-mail');
        $env['MAIL_DRIVER'] = 'log';
        $env['MAIL_LOG_FILE'] = self::$mailLog;

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
        @unlink(self::$mailLog);
    }

    protected function setUp(): void {
        parent::setUp();
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'fx-cookie');
        $this->clientIp = null;
        file_put_contents(self::$mailLog, '');
        $this->extraHeaders = [];
    }

    // as proximas requisicoes levam estes cabecalhos (o servidor de teste confia no 127.0.0.1 como proxy)
    protected function withHeaders(array $headers): void {
        $this->extraHeaders = $headers;
    }

    // as proximas requisicoes chegam como se viessem deste ip (outra pessoa, em outra rede).
    // troca tambem a sessao: outro aparelho nao tem o cookie do anterior.
    protected function fromIp(string $ip): void {
        $this->clientIp = $ip;
        $this->newSession();
    }

    protected function tearDown(): void {
        @unlink($this->cookieJar);
    }

    // e-mails que o sistema mandou neste teste: [['to' =>, 'subject' =>, 'body' =>, 'at' =>], ...]
    protected function sentMails(): array {
        return \App\Mail\LogMailer::read(self::$mailLog);
    }

    // abre o link do ultimo e-mail de confirmacao mandado para $email (task 50) e devolve o destino do redirect
    protected function confirmEmailFromMail(string $email): ?string {
        foreach (array_reverse($this->sentMails()) as $mail) {
            if ($mail['to'] === $email && preg_match('#url=merchant%2Fverify-email&token=([a-f0-9]{64})#', $mail['body'], $m)) {
                return $this->get('merchant/verify-email&token=' . $m[1])[1];
            }
        }
        $this->fail("nenhum e-mail de confirmacao para $email");
    }

    // sessao nova (como apagar o cookie no navegador)
    protected function newSession(): void {
        @unlink($this->cookieJar);
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'fx-cookie');
    }

    // poe um cookie no "navegador" do teste (ex.: um id de sessao inventado, para testar session fixation)
    protected function setCookie(string $name, string $value): void {
        $host = parse_url(self::$baseUrl, PHP_URL_HOST);
        file_put_contents($this->cookieJar, "$host\tFALSE\t/\tFALSE\t0\t$name\t$value\n", FILE_APPEND);
    }

    protected function cookie(string $name): ?string {
        foreach (file($this->cookieJar, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $parts = explode("\t", $line);
            if (count($parts) === 7 && $parts[5] === $name) {
                return $parts[6];
            }
        }
        return null;
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
        $headers = $this->extraHeaders;
        if ($this->clientIp !== null) {
            $headers[] = 'X-Forwarded-For: ' . $this->clientIp;
        }
        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $body = curl_exec($ch);
        if ($body === false) {
            throw new RuntimeException('falha http: ' . curl_error($ch));
        }
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $redirect = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        // libera a conexao agora: e nesse momento que o curl grava os cookies no arquivo
        // (curl_close nao faz nada desde o PHP 8.0 e virou aviso de obsoleto no 8.5)
        unset($ch);

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
