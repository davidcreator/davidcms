<?php
namespace Reamur\System\Library\Cache;

use Redis as RedisClient;
use RedisException;

/**
 * Class Redis - Cache handler for Redis
 * 
 * Provides caching functionality using Redis as backend storage
 * Compatible with OpenCart-based MVCL architecture
 * 
 * @package Reamur\System\Library\Cache
 * @author  Reamur System
 * @version 2.0
 */
class Redis {
    /** @var RedisClient|null Redis connection instance */
    private ?RedisClient $redis = null;

    /** @var int Default expiration time in seconds */
    private int $expire;

    /** @var bool Connection status flag */
    private bool $connected = false;

    /** @var array Default connection configuration */
    private array $config = [
        'hostname' => 'localhost',
        'port' => 6379,
        'prefix' => 'cache_',
        'timeout' => 2.5,
        'retry_interval' => 100,
        'read_timeout' => 2.5
    ];

    /**
     * Constructor - Initialize Redis connection
     * 
     * @param int $expire Default expiration time in seconds (default: 3600)
     * @throws RedisException If Redis connection fails
     */
    public function __construct(int $expire = 3600) {
        $this->expire = max(1, $expire); // Ensure minimum 1 second expiration
        $this->initializeConfig();
        $this->connect();
    }

    /**
     * Initialize configuration from constants or defaults
     */
    private function initializeConfig(): void {
        $this->config['hostname'] = defined('CACHE_HOSTNAME') ? CACHE_HOSTNAME : $this->config['hostname'];
        $this->config['port'] = defined('CACHE_PORT') ? (int)CACHE_PORT : $this->config['port'];
        $this->config['prefix'] = defined('CACHE_PREFIX') ? CACHE_PREFIX : $this->config['prefix'];
    }

    /**
     * Establish Redis connection
     * 
     * @throws RedisException If connection fails
     */
    private function connect(): void {
        try {
            $this->redis = new RedisClient();
            
            // Use persistent connection for better performance
            $connected = $this->redis->pconnect(
                $this->config['hostname'],
                $this->config['port'],
                $this->config['timeout'],
                null,
                $this->config['retry_interval'],
                $this->config['read_timeout']
            );

            if (!$connected) {
                throw new RedisException('Failed to connect to Redis server');
            }

            // Test connection
            $this->redis->ping();
            $this->connected = true;

        } catch (RedisException $e) {
            $this->connected = false;
            throw new RedisException(
                sprintf('Redis connection failed: %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Check if Redis is connected and available
     * 
     * @return bool Connection status
     */
    public function isConnected(): bool {
        if (!$this->connected || !$this->redis) {
            return false;
        }

        try {
            $this->redis->ping();
            return true;
        } catch (RedisException $e) {
            $this->connected = false;
            return false;
        }
    }

    /**
     * Get cached data by key
     * 
     * @param string $key Cache key
     * @return mixed|null Cached data or null if not found/error
     */
    public function get(string $key): mixed {
        if (!$this->isConnected() || empty($key)) {
            return null;
        }

        try {
            $data = $this->redis->get($this->config['prefix'] . $key);
            
            if ($data === false) {
                return null; // Key doesn't exist
            }

            $decoded = json_decode($data, true);
            
            // Return original data if JSON decode fails
            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $data;

        } catch (RedisException $e) {
            // Log error in production environment
            error_log(sprintf('Redis GET error for key "%s": %s', $key, $e->getMessage()));
            return null;
        }
    }

    /**
     * Set cached data with expiration
     * 
     * @param string $key Cache key
     * @param mixed $value Data to cache
     * @param int $expire Expiration time in seconds (0 = use default)
     * @return bool Success status
     */
    public function set(string $key, mixed $value, int $expire = 0): bool {
        if (!$this->isConnected() || empty($key)) {
            return false;
        }

        $expire = $expire > 0 ? $expire : $this->expire;

        try {
            // Handle different data types appropriately
            $encodedValue = is_string($value) ? $value : json_encode($value);
            
            if ($encodedValue === false) {
                error_log(sprintf('Failed to encode value for Redis key "%s"', $key));
                return false;
            }

            // Use SETEX for atomic set with expiration
            $result = $this->redis->setex(
                $this->config['prefix'] . $key,
                $expire,
                $encodedValue
            );

            return $result === true;

        } catch (RedisException $e) {
            error_log(sprintf('Redis SET error for key "%s": %s', $key, $e->getMessage()));
            return false;
        }
    }

    /**
     * Delete cached data by key
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool {
        if (!$this->isConnected() || empty($key)) {
            return false;
        }

        try {
            $result = $this->redis->del($this->config['prefix'] . $key);
            return $result > 0; // Returns number of keys deleted
            
        } catch (RedisException $e) {
            error_log(sprintf('Redis DELETE error for key "%s": %s', $key, $e->getMessage()));
            return false;
        }
    }

    /**
     * Check if key exists in cache
     * 
     * @param string $key Cache key
     * @return bool Key existence status
     */
    public function exists(string $key): bool {
        if (!$this->isConnected() || empty($key)) {
            return false;
        }

        try {
            return $this->redis->exists($this->config['prefix'] . $key) > 0;
        } catch (RedisException $e) {
            error_log(sprintf('Redis EXISTS error for key "%s": %s', $key, $e->getMessage()));
            return false;
        }
    }

    /**
     * Clear all cache entries with current prefix
     * 
     * @return bool Success status
     */
    public function flush(): bool {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $pattern = $this->config['prefix'] . '*';
            $keys = $this->redis->keys($pattern);
            
            if (empty($keys)) {
                return true; // No keys to delete
            }

            $result = $this->redis->del($keys);
            return $result > 0;
            
        } catch (RedisException $e) {
            error_log(sprintf('Redis FLUSH error: %s', $e->getMessage()));
            return false;
        }
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache statistics or empty array on error
     */
    public function getStats(): array {
        if (!$this->isConnected()) {
            return [];
        }

        try {
            $info = $this->redis->info();
            
            return [
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'uptime_in_seconds' => $info['uptime_in_seconds'] ?? 0
            ];
            
        } catch (RedisException $e) {
            error_log(sprintf('Redis STATS error: %s', $e->getMessage()));
            return [];
        }
    }

    /**
     * Close Redis connection
     */
    public function __destruct() {
        if ($this->redis && $this->connected) {
            try {
                $this->redis->close();
            } catch (RedisException $e) {
                // Ignore errors during cleanup
            }
        }
    }
}