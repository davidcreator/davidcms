<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Driver PDO (mysql, utf8mb4). Mesma API do driver MySQLi.
 */
namespace Reamur\System\Library\Db;

final class Pdo {
    private \PDO $connection;

    private int $affected = 0;

    public function __construct(string $hostname, string $username, string $password, string $database, string $port = '3306') {
        try {
            $this->connection = new \PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $hostname, (int)$port, $database),
                $username,
                $password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\PDOException $e) {
            throw new \Exception('PDO: falha na conexão — ' . $e->getMessage());
        }
    }

    public function query(string $sql): \stdClass|bool {
        try {
            $stmt = $this->connection->query($sql);
        } catch (\PDOException $e) {
            throw new \Exception('PDO: ' . $e->getMessage() . ' | SQL: ' . $sql);
        }

        if ($stmt instanceof \PDOStatement && $stmt->columnCount() > 0) {
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $this->affected = count($rows);

            $data           = new \stdClass();
            $data->row      = $rows[0] ?? [];
            $data->rows     = $rows;
            $data->num_rows = count($rows);

            return $data;
        }

        $this->affected = $stmt instanceof \PDOStatement ? $stmt->rowCount() : 0;

        return true;
    }

    public function escape(string $value): string {
        return substr((string)$this->connection->quote($value), 1, -1);
    }

    public function countAffected(): int {
        return $this->affected;
    }

    public function getLastId(): int {
        return (int)$this->connection->lastInsertId();
    }
}
