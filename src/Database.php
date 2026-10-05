<?php
// issue no. #3
namespace App;

use App\Controllers\ErrorController;
use PDO;
use PDOException;

class Database {
    // onde a conexao fica guardada
    private static $instance = null;

    // a funcao geral pra pegar o banco
    public static function getConnection() {
        // se ainda nao tem conexao, cria uma
        if (self::$instance === null) {
            try {
                // self vai no que é estatico.

                // pra nao precisar ficar chamando objeto toda hora,
                // ja pega os valores prontos.

                // aqui usamos pra pegar os dados estaticos e guardar na $instance
                self::$instance = self::connect();
            } catch (PDOException $e) {
                // o detalhe do erro vai pro log, nunca pra tela (pode expor usuario/host do banco)
                error_log('[Database::getConnection] ' . $e->getMessage());
                (new ErrorController())->handle(503);
                exit;
            }
        }

        // entrega a conexão pronta pra uso
        return self::$instance;
    }

    // abre uma conexao nova e devolve; se falhar, lanca PDOException.
    // os scripts de linha de comando (bin/) usam direto, pra mostrar o erro no terminal em vez da pagina 503.
    public static function connect(): PDO {
        // sets do .env (ou das variaveis de ambiente)
        $host = $_ENV['DB_HOST'] ?? '';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $db   = $_ENV['DB_NAME'] ?? '';
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';

        // configs de seguranca do pdo
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user,
            $pass,
            $options
        );

        // a sessao do mysql no mesmo fuso do PHP. usa o offset ("-03:00") e nao o nome:
        // o nome exige as tabelas de fuso carregadas no mysql, o que o XAMPP e o CI nao garantem.
        // (o offset e calculado na hora da conexao, entao respeita horario de verao se o fuso tiver)
        $offset = (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get())))->format('P');
        $pdo->exec("SET time_zone = '$offset'");

        return $pdo;
    }
}
