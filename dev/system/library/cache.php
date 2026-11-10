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

/**
 * Class Cache
 * Provides a unified interface for various cache storage adapters
 *
 * @package Reamur\System\Library
 * @version 1.2.0
 */
class Cache {
    /** @var object Cache adapter instance */
    private object $adaptor;

    /** @var int Default expiration time in seconds */
    private int $defaultExpire;

    /** @var array<int, string> Valid cache adapter types */
    private const VALID_ADAPTORS = ['file', 'redis', 'memcached', 'mem', 'apcu', 'apc'];

    public function __construct(string $adaptor, int $expire = 3600) {
        $this->validateAdaptor($adaptor);
        $this->validateExpire($expire);

        $this->defaultExpire = $expire;
        $this->initializeAdaptor($adaptor);
    }

    public function get(string $key): mixed {
        $this->validateKey($key);

        try {
            return $this->adaptor->get($key);
        } catch (\Throwable $e) {
            error_log('Cache get error for key "' . $key . '": ' . $e->getMessage());
            return null;
        }
    }

    public function set(string $key, mixed $value, int $expire = 0): bool {
        $this->validateKey($key);

        if ($expire < 0) {
            throw new InvalidArgumentException('Expire time cannot be negative');
        }

        $expireTime = $expire === 0 ? $this->defaultExpire : $expire;

        try {
            $result = $this->adaptor->set($key, $value, $expireTime);
            return is_bool($result) ? $result : true;
        } catch (\Throwable $e) {
            error_log('Cache set error for key "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }

    public function delete(string $key): bool {
        $this->validateKey($key);

        try {
            $result = $this->adaptor->delete($key);
            return is_bool($result) ? $result : true;
        } catch (\Throwable $e) {
            error_log('Cache delete error for key "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }

    public function exists(string $key): bool {
        $this->validateKey($key);

        try {
            return method_exists($this->adaptor, 'exists')
                ? (bool)$this->adaptor->exists($key)
                : $this->get($key) !== null;
        } catch (\Throwable $e) {
            error_log('Cache exists check error for key "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }

    public function clear(): bool {
        try {
            if (method_exists($this->adaptor, 'clear')) {
                $result = $this->adaptor->clear();
                return is_bool($result) ? $result : true;
            }
            if (method_exists($this->adaptor, 'flush')) {
                return (bool)$this->adaptor->flush();
            }
            return false;
        } catch (\Throwable $e) {
            error_log('Cache clear error: ' . $e->getMessage());
            return false;
        }
    }

    /** @return array<string, mixed> */
    public function getStats(): array {
        try {
            if (!method_exists($this->adaptor, 'getStats')) {
                return [];
            }
            $stats = $this->adaptor->getStats();
            return is_array($stats) ? $stats : [];
        } catch (\Throwable $e) {
            error_log('Cache stats error: ' . $e->getMessage());
            return [];
        }
    }

    public function getAdaptorName(): string {
        $className = get_class($this->adaptor);
        $pos = strrpos($className, '\\');
        return strtolower($pos !== false ? substr($className, $pos + 1) : $className);
    }

    private function validateAdaptor(string $adaptor): void {
        if ($adaptor === '') {
            throw new InvalidArgumentException('Cache adapter cannot be empty');
        }

        if (!in_array(strtolower($adaptor), self::VALID_ADAPTORS, true)) {
            throw new InvalidArgumentException(
                'Invalid cache adapter "' . $adaptor . '". Valid adapters: '
                . implode(', ', self::VALID_ADAPTORS)
            );
        }
    }

    private function validateExpire(int $expire): void {
        if ($expire < 0) {
            throw new InvalidArgumentException('Expire time cannot be negative');
        }

        if ($expire > 31536000) {
            throw new InvalidArgumentException('Expire time cannot exceed 1 year (31536000 seconds)');
        }
    }

    private function validateKey(string $key): void {
        if ($key === '') {
            throw new InvalidArgumentException('Cache key cannot be empty');
        }

        if (strlen($key) > 250) {
            throw new InvalidArgumentException('Cache key cannot exceed 250 characters');
        }

        if (str_contains($key, '..') || preg_match('#[{}()\\\\/@:"\x00-\x1f\x7f]#', $key) === 1) {
            throw new InvalidArgumentException('Cache key contains invalid characters');
        }
    }

    private function initializeAdaptor(string $adaptor): void {
        $normalized = strtolower($adaptor);
        $driver = ($normalized === 'apc' || $normalized === 'apcu') ? 'APCU' : ucfirst($normalized);
        $class = 'Reamur\\System\\Library\\Cache\\' . $driver;

        if (!class_exists($class)) {
            throw new RuntimeException('Cache adapter class "' . $class . '" not found');
        }

        try {
            $this->adaptor = new $class($this->defaultExpire);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Failed to initialize cache adapter "' . $adaptor . '": ' . $e->getMessage(),
                0,
                $e
            );
        }

        foreach (['get', 'set', 'delete'] as $method) {
            if (!method_exists($this->adaptor, $method)) {
                throw new RuntimeException(
                    'Cache adapter "' . $adaptor . '" does not implement required method: ' . $method
                );
            }
        }
    }
}
