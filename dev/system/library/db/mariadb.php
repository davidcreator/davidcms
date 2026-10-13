<?php
namespace Reamur\System\Library\DB;

/**
 * MariaDB adapter using PDO
 */
class Mariadb {
	/**
	 * @var \PDO|null
	 */
	private ?\PDO $connection = null;

	/**
	 * @var int
	 */
	private int $affected = 0;

	/**
	 * Constructor
	 *
	 * @param string $hostname
	 * @param string $username
	 * @param string $password
	 * @param string $database
	 * @param string $port
	 * @param array  $options
	 */
	public function __construct(
		string $hostname,
		string $username,
		string $password,
		string $database,
		string $port = '3306',
		array $options = []
	) {
		$port = $port ?: '3306';

		$defaultOptions = [
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
			\PDO::ATTR_EMULATE_PREPARES => false,
			\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_general_ci'
		];

		// User supplied options override defaults
		$options = $options + $defaultOptions;

		$dsn = 'mysql:host=' . $hostname . ';port=' . $port . ';dbname=' . $database . ';charset=utf8mb4';

		try {
			$this->connection = new \PDO($dsn, $username, $password, $options);

			$this->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");
			$this->query("SET FOREIGN_KEY_CHECKS = 0");
			$this->query("SET `time_zone` = '" . $this->escape(date('P')) . "'");
		} catch (\PDOException $e) {
			throw new \Exception('Error: Could not make a database link using ' . $username . '@' . $hostname . '!<br/>Message: ' . $e->getMessage());
		}
	}

	/**
	 * Execute a SQL query
	 *
	 * @param string $sql
	 *
	 * @return bool|object
	 */
	public function query(string $sql): bool|object {
		$this->ensureConnection();

		try {
			$statement = $this->connection->query($sql);

			if ($statement === false) {
				return false;
			}

			$this->affected = $statement->rowCount();

			if ($statement->columnCount() > 0) {
				$data = $statement->fetchAll();

				$result = new \stdClass();
				$result->num_rows = count($data);
				$result->row = $data[0] ?? [];
				$result->rows = $data;

				$statement->closeCursor();

				return $result;
			}

			$statement->closeCursor();
			return true;
		} catch (\PDOException $e) {
			throw new \Exception('Error: ' . $e->getMessage() . '<br/>Error Code: ' . $e->getCode() . '<br/>' . $sql);
		}
	}

	/**
	 * Execute a prepared statement with bound parameters
	 *
	 * @param string $sql
	 * @param array  $params
	 *
	 * @return bool|object
	 */
	public function prepare(string $sql, array $params = []): bool|object {
		$this->ensureConnection();

		try {
			$statement = $this->connection->prepare($sql);
			$statement->execute($params);

			$this->affected = $statement->rowCount();

			if ($statement->columnCount() > 0) {
				$data = $statement->fetchAll();

				$result = new \stdClass();
				$result->num_rows = count($data);
				$result->row = $data[0] ?? [];
				$result->rows = $data;

				$statement->closeCursor();

				return $result;
			}

			$statement->closeCursor();
			return true;
		} catch (\PDOException $e) {
			throw new \Exception('Prepared statement failed: ' . $e->getMessage() . '<br/>Error Code: ' . $e->getCode() . '<br/>' . $sql);
		}
	}

	/**
	 * Escape value for safe use in SQL
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	public function escape(string $value): string {
		$this->ensureConnection();

		$quoted = $this->connection->quote($value);

		return substr($quoted, 1, -1);
	}

	/**
	 * Get the number of affected rows from the last write operation
	 *
	 * @return int
	 */
	public function countAffected(): int {
		return $this->affected;
	}

	/**
	 * Get the last inserted ID
	 *
	 * @return int
	 */
	public function getLastId(): int {
		$this->ensureConnection();

		return (int)$this->connection->lastInsertId();
	}

	/**
	 * Check if database connection is alive
	 *
	 * @return bool
	 */
	public function isConnected(): bool {
		return $this->connection instanceof \PDO;
	}

	/**
	 * Begin transaction
	 *
	 * @return bool
	 */
	public function beginTransaction(): bool {
		$this->ensureConnection();

		return $this->connection->beginTransaction();
	}

	/**
	 * Commit transaction
	 *
	 * @return bool
	 */
	public function commit(): bool {
		$this->ensureConnection();

		return $this->connection->commit();
	}

	/**
	 * Rollback transaction
	 *
	 * @return bool
	 */
	public function rollback(): bool {
		$this->ensureConnection();

		return $this->connection->rollBack();
	}

	/**
	 * Close database connection
	 *
	 * @return bool
	 */
	public function close(): bool {
		$this->connection = null;

		return true;
	}

	/**
	 * Ensure connection is available
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function ensureConnection(): void {
		if (!$this->connection) {
			throw new \Exception('Database connection is not available');
		}
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		$this->close();
	}
}
