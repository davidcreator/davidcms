<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Driver de sessão em Redis.
 * Implementa \SessionHandlerInterface para compatibilidade com session_set_save_handler().
 */
namespace Reamur\System\Library\Session;

class Redis implements \SessionHandlerInterface {
    private \Reamur\System\Engine\Config $config;
    private \Redis $redis;
    private string $prefix;

    /**
     * @throws \RuntimeException If Redis connection fails or Config is unavailable
     */
    public function __construct(\Reamur\System\Engine\Registry $registry) {
        $config = $registry->get('config');
        if (!$config instanceof \Reamur\System\Engine\Config) {
            throw new \RuntimeException('Session\\Redis requires config service in Registry');
        }

        $this->config = $config;

        $host   = defined('CACHE_HOSTNAME') ? (string)CACHE_HOSTNAME : '127.0.0.1';
        $port   = defined('CACHE_PORT') ? (int)CACHE_PORT : 6379;
        $prefix = defined('CACHE_PREFIX') ? (string)CACHE_PREFIX : 'rms_';

        $this->prefix = $prefix . '.session.';

        try {
            $this->redis = new \Redis();
            if (!$this->redis->pconnect($host, $port)) {
                throw new \RuntimeException('Failed to connect to Redis server');
            }

            $this->redis->ping();
        } catch (\RedisException $e) {
            throw new \RuntimeException('Redis connection error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        if (!$this->validateSessionId($id)) {
            return '';
        }

        $data = $this->redis->get($this->prefix . $id);

        return is_string($data) ? $data : '';
    }

    public function write(string $id, string $data): bool {
        if (!$this->validateSessionId($id)) {
            return false;
        }

        $ttl    = max(1, (int)$this->config->get('session_expire', 3600));
        $result = $this->redis->setEx($this->prefix . $id, $ttl, $data);

        return $result === true;
    }

    public function destroy(string $id): bool {
        if (!$this->validateSessionId($id)) {
            return false;
        }

        return $this->redis->unlink($this->prefix . $id) !== false;
    }

    public function gc(int $max_lifetime): int|false {
        // Redis handles key expiration automatically via TTL
        return 0;
    }

    private function validateSessionId(string $session_id): bool {
        return preg_match('/^[a-zA-Z0-9,-]{22,256}$/', $session_id) === 1;
    }
}
