<?php
namespace Reamur\System\Library\Session;

/**
 * Class Redis - Session handler using Redis storage
 * 
 * @package Reamur\System\Library\Session
 */
class Redis {
    private object $config;
    private ?\Redis $redis;
    private string $prefix;

    /**
     * Constructor
     *
     * @param object $registry
     * @throws \RuntimeException If Redis connection fails
     */
    public function __construct(\Reamur\System\Engine\Registry $registry) {
        $this->config = $registry->get('config');
        $this->prefix = CACHE_PREFIX . '.session.';
        
        try {
            $this->redis = new \Redis();
            if (!$this->redis->pconnect(CACHE_HOSTNAME, (int)CACHE_PORT)) {
                throw new \RuntimeException('Failed to connect to Redis server');
            }
            
            // Test connection immediately
            $this->redis->ping();
        } catch (\RedisException $e) {
            throw new \RuntimeException('Redis connection error: ' . $e->getMessage());
        }
    }

    /**
     * Read session data
     *
     * @param string $session_id
     * @return array
     * @throws \RuntimeException If session data is corrupted
     */
    public function read(string $session_id): array {
        if (!$this->validateSessionId($session_id)) {
            return [];
        }
        
        $data = $this->redis->get($this->prefix . $session_id);
        if ($data === false || empty($data)) {
            return [];
        }
        
        $result = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid session data format');
        }
        
        return $result ?? [];
    }

    /**
     * Write session data
     *
     * @param string $session_id
     * @param array $data
     * @return bool
     * @throws \RuntimeException If session write fails
     */
    public function write(string $session_id, array $data): bool {
        if (!$this->validateSessionId($session_id)) {
            return false;
        }
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode session data');
        }
        
        $ttl = (int)$this->config->get('session_expire');
        $result = $this->redis->setEx($this->prefix . $session_id, $ttl, $json);
        
        if ($result === false) {
            throw new \RuntimeException('Failed to write session data to Redis');
        }
        
        return true;
    }

    /**
     * Destroy session
     *
     * @param string $session_id
     * @return bool
     */
    public function destroy(string $session_id): bool {
        if (!$this->validateSessionId($session_id)) {
            return false;
        }
        
        return (bool)$this->redis->unlink($this->prefix . $session_id);
    }

    /**
     * Garbage collection
     *
     * @return bool
     */
    public function gc(): bool {
        // Redis handles expiration automatically
        return true;
    }

    /**
     * Validate session ID format
     *
     * @param string $session_id
     * @return bool
     */
    private function validateSessionId(string $session_id): bool {
        return preg_match('/^[a-zA-Z0-9,-]{22,256}$/', $session_id) === 1;
    }
}
