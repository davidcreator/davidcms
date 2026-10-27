<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Driver MySQLi (utf8mb4). Erros de conexão/SQL viram \Exception com mensagem clara —
 * a camada de handlers do framework decide exibir ou logar (config error_display).
 */
namespace Reamur\System\Library\Db;

final class Mysqli {
    private \mysqli $connection;

    public function __construct(string $hostname, string $username, string $password, string $database, string $port = '3306') {
        try {
            $this->connection = new \mysqli($hostname, $username, $password, $database, (int)$port);
        } catch (\mysqli_sql_exception $e) {
            throw new \Exception('MySQLi: falha na conexão — ' . $e->getMessage());
        }

        $this->connection->set_charset('utf8mb4');

        // Alinha a sessão do MySQL ao fuso do PHP (mesmo contrato do driver mariadb):
        // sem isto, NOW() segue o fuso do servidor (SYSTEM) e diverge de date() do PHP,
        // fazendo registros recém-criados parecerem futuros (ex.: blog filter date_published <= NOW()).
        $this->connection->query("SET time_zone = '" . date('P') . "'");
    }

    public function query(string $sql): \stdClass|bool {
        try {
            $result = $this->connection->query($sql);
        } catch (\mysqli_sql_exception $e) {
            throw new \Exception('MySQLi: ' . $e->getMessage() . ' | SQL: ' . $sql);
        }

        if ($result instanceof \mysqli_result) {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();

            $data           = new \stdClass();
            $data->row      = $rows[0] ?? [];
            $data->rows     = $rows;
            $data->num_rows = count($rows);

            return $data;
        }

        return $result;
    }

    public function escape(string $value): string {
        return $this->connection->real_escape_string($value);
    }

    public function countAffected(): int {
        return $this->connection->affected_rows;
    }

    public function getLastId(): int {
        return (int)$this->connection->insert_id;
    }
}
