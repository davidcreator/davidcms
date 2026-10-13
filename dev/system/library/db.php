<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

declare(strict_types=1);

namespace Reamur\System\Library;

use InvalidArgumentException;
use RuntimeException;
use Exception;

/**
 * Class DB
 * Database adapter wrapper providing a consistent interface for different database systems
 * 
 * This class serves as a facade for different database adapters, providing a unified
 * interface for database operations while maintaining compatibility with OpenCart-style
 * MVC architecture.
 */
class DB
{
    /**
     * Database adapter instance
     */
    private object $adaptor;

    /**
     * Connection parameters for debugging/logging
     */
    private array $connectionInfo;

    /**
     * Constructor
     * 
     * @param string $adaptor Database adapter type (e.g., 'mysqli', 'pdo')
     * @param string $hostname Database server hostname
     * @param string $username Database username
     * @param string $password Database password
     * @param string $database Database name
     * @param string $port Database server port (optional)
     * @param array $options Additional connection options (optional)
     * 
     * @throws InvalidArgumentException If connection parameters are invalid
     * @throws RuntimeException If adapter class cannot be loaded or connection fails
     */
    public function __construct(
        string $adaptor,
        string $hostname,
        string $username,
        string $password,
        string $database,
        string $port = '',
        array $options = []
    ) {
        $this->validateConnectionParameters($adaptor, $hostname, $database);
        
        $this->connectionInfo = [
            'adaptor' => $adaptor,
            'hostname' => $hostname,
            'username' => $username,
            'database' => $database,
            'port' => $port,
            'connected_at' => date('Y-m-d H:i:s')
        ];

        $this->initializeAdapter($adaptor, $hostname, $username, $password, $database, $port, $options);
    }

    /**
     * Validate connection parameters
     * 
     * @param string $adaptor
     * @param string $hostname
     * @param string $database
     * @throws InvalidArgumentException
     */
    private function validateConnectionParameters(string $adaptor, string $hostname, string $database): void
    {
        if (empty(trim($adaptor))) {
            throw new InvalidArgumentException('Database adapter cannot be empty');
        }

        if (empty(trim($hostname))) {
            throw new InvalidArgumentException('Database hostname cannot be empty');
        }

        if (empty(trim($database))) {
            throw new InvalidArgumentException('Database name cannot be empty');
        }

        // Validate adapter name format (alphanumeric and underscore only)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $adaptor)) {
            throw new InvalidArgumentException('Invalid adapter name format');
        }
    }

    /**
     * Initialize database adapter
     * 
     * @param string $adaptor
     * @param string $hostname
     * @param string $username
     * @param string $password
     * @param string $database
     * @param string $port
     * @param array $options
     * @throws RuntimeException
     */
    private function initializeAdapter(
        string $adaptor,
        string $hostname,
        string $username,
        string $password,
        string $database,
        string $port,
        array $options
    ): void {
        $className = 'Reamur\\System\\Library\\DB\\' . ucfirst(strtolower($adaptor));

        if (!class_exists($className)) {
            throw new RuntimeException("Database adapter '{$adaptor}' not found. Expected class: {$className}");
        }

        try {
            $this->adaptor = new $className($hostname, $username, $password, $database, $port, $options);
            
            // Verify connection was established
            if (!$this->isConnected()) {
                throw new RuntimeException('Database connection could not be established');
            }
            
        } catch (Exception $e) {
            throw new RuntimeException(
                "Failed to initialize database adapter '{$adaptor}': " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Execute SQL query
     * 
     * @param string $sql SQL statement to be executed
     * @return bool|object Query result object or false on failure
     * @throws InvalidArgumentException If SQL is empty
     * @throws RuntimeException If query execution fails
     */
    public function query(string $sql): bool|object
    {
        if (empty(trim($sql))) {
            throw new InvalidArgumentException('SQL query cannot be empty');
        }

        if (!$this->isConnected()) {
            throw new RuntimeException('Database connection is not active');
        }

        try {
            $result = $this->adaptor->query($sql);
            
            if ($result === false) {
                throw new RuntimeException('Query execution returned false');
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw new RuntimeException(
                'Query execution failed: ' . $e->getMessage() . ' | SQL: ' . substr($sql, 0, 100) . '...',
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Escape value to prevent SQL injection
     * 
     * @param string $value Value to be escaped
     * @return string Escaped value safe for SQL queries
     * @throws RuntimeException If connection is not active
     */
    public function escape(string $value): string
    {
        if (!$this->isConnected()) {
            throw new RuntimeException('Database connection is not active');
        }

        return $this->adaptor->escape($value);
    }

    /**
     * Get the number of affected rows from the last query
     * 
     * @return int Number of affected rows
     * @throws RuntimeException If connection is not active
     */
    public function countAffected(): int
    {
        if (!$this->isConnected()) {
            throw new RuntimeException('Database connection is not active');
        }

        return $this->adaptor->countAffected();
    }

    /**
     * Get the last inserted ID
     * 
     * @return int Last inserted auto-increment ID
     * @throws RuntimeException If connection is not active
     */
    public function getLastId(): int
    {
        if (!$this->isConnected()) {
            throw new RuntimeException('Database connection is not active');
        }

        return $this->adaptor->getLastId();
    }

    /**
     * Check if database connection is active
     * 
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool
    {
        return isset($this->adaptor) && $this->adaptor->isConnected();
    }

    /**
     * Get connection information (without sensitive data)
     * 
     * @return array Connection information
     */
    public function getConnectionInfo(): array
    {
        return [
            'adaptor' => $this->connectionInfo['adaptor'] ?? 'unknown',
            'hostname' => $this->connectionInfo['hostname'] ?? 'unknown',
            'database' => $this->connectionInfo['database'] ?? 'unknown',
            'connected_at' => $this->connectionInfo['connected_at'] ?? null
        ];
    }

    /**
     * Execute a prepared statement (if supported by adapter)
     * 
     * @param string $sql SQL statement with placeholders
     * @param array $params Parameters for the prepared statement
     * @return bool|object Query result
     * @throws RuntimeException If adapter doesn't support prepared statements
     */
    public function prepare(string $sql, array $params = []): bool|object
    {
        if (!$this->isConnected()) {
            throw new RuntimeException('Database connection is not active');
        }

        if (!method_exists($this->adaptor, 'prepare')) {
            throw new RuntimeException('Current database adapter does not support prepared statements');
        }

        try {
            return $this->adaptor->prepare($sql, $params);
        } catch (Exception $e) {
            throw new RuntimeException('Prepared statement execution failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Start database transaction (if supported)
     * 
     * @return bool Success status
     * @throws RuntimeException If adapter doesn't support transactions
     */
    public function beginTransaction(): bool
    {
        if (!$this->isConnected()) {
            throw new RuntimeException('Database connection is not active');
        }

        if (!method_exists($this->adaptor, 'beginTransaction')) {
            throw new RuntimeException('Current database adapter does not support transactions');
        }

        return $this->adaptor->beginTransaction();
    }

    /**
     * Commit database transaction (if supported)
     * 
     * @return bool Success status
     * @throws RuntimeException If adapter doesn't support transactions
     */
    public function commit(): bool
    {
        if (!$this->isConnected()) {
            throw new RuntimeException('Database connection is not active');
        }

        if (!method_exists($this->adaptor, 'commit')) {
            throw new RuntimeException('Current database adapter does not support transactions');
        }

        return $this->adaptor->commit();
    }

    /**
     * Rollback database transaction (if supported)
     * 
     * @return bool Success status
     * @throws RuntimeException If adapter doesn't support transactions
     */
    public function rollback(): bool
    {
        if (!$this->isConnected()) {
            throw new RuntimeException('Database connection is not active');
        }

        if (!method_exists($this->adaptor, 'rollback')) {
            throw new RuntimeException('Current database adapter does not support transactions');
        }

        return $this->adaptor->rollback();
    }

    /**
     * Close database connection
     * 
     * @return bool Success status
     */
    public function close(): bool
    {
        if (isset($this->adaptor) && method_exists($this->adaptor, 'close')) {
            return $this->adaptor->close();
        }

        return true;
    }

    /**
     * Destructor - ensures proper cleanup
     */
    public function __destruct()
    {
        $this->close();
    }
}