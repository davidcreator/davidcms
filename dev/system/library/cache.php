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
 * Class Cache
 * Provides a unified interface for various cache storage adapters
 * 
 * @package Reamur\System\Library
 * @version 1.1.0
 */
class Cache {
    /** @var object Cache adapter instance */
    private object $adaptor;
    
    /** @var int Default expiration time in seconds */
    private int $defaultExpire;
    
    /** @var array Valid cache adapter types */
    private const VALID_ADAPTORS = ['file', 'redis', 'memcached', 'apcu', 'database'];

    /**
     * Constructor
     * 
     * @param string $adaptor Cache adapter type (e.g., file, redis, memcached)
     * @param int $expire Default expiration time in seconds (default: 3600)
     * 
     * @throws InvalidArgumentException If adapter is empty or invalid
     * @throws RuntimeException If adapter class cannot be loaded or initialized
     */
    public function __construct(string $adaptor, int $expire = 3600) {
        $this->validateAdaptor($adaptor);
        $this->validateExpire($expire);
        
        $this->defaultExpire = $expire;
        $this->initializeAdaptor($adaptor);
    }

    /**
     * Gets a cached value by key
     * 
     * @param string $key The cache key
     * @return mixed Cached value or null if not found
     * 
     * @throws InvalidArgumentException If key is empty or invalid
     */
    public function get(string $key): mixed {
        $this->validateKey($key);
        
        try {
            return $this->adaptor->get($key);
        } catch (Exception $e) {
            // Log error but don't throw - cache miss should be graceful
            error_log('Cache get error for key "' . $key . '": ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sets a value in cache
     * 
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int $expire Optional expiration time in seconds (0 = use default)
     * 
     * @throws InvalidArgumentException If key is empty or expire is invalid
     */
    public function set(string $key, mixed $value, int $expire = 0): bool {
        $this->validateKey($key);
        
        if ($expire < 0) {
            throw new InvalidArgumentException('Expire time cannot be negative');
        }
        
        $expireTime = $expire === 0 ? $this->defaultExpire : $expire;
        
        try {
            $this->adaptor->set($key, $value, $expireTime);
            return true;
        } catch (Exception $e) {
            error_log('Cache set error for key "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a cached value by key
     * 
     * @param string $key The cache key
     * @return bool True if deleted successfully, false otherwise
     * 
     * @throws InvalidArgumentException If key is empty
     */
    public function delete(string $key): bool {
        $this->validateKey($key);
        
        try {
            $this->adaptor->delete($key);
            return true;
        } catch (Exception $e) {
            error_log('Cache delete error for key "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Checks if a key exists in cache
     * 
     * @param string $key The cache key
     * @return bool True if key exists, false otherwise
     * 
     * @throws InvalidArgumentException If key is empty
     */
    public function exists(string $key): bool {
        $this->validateKey($key);
        
        try {
            return method_exists($this->adaptor, 'exists') ? 
                   $this->adaptor->exists($key) : 
                   $this->get($key) !== null;
        } catch (Exception $e) {
            error_log('Cache exists check error for key "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clears all cached data (if supported by adapter)
     * 
     * @return bool True if cleared successfully, false otherwise
     */
    public function clear(): bool {
        try {
            if (method_exists($this->adaptor, 'clear')) {
                $this->adaptor->clear();
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log('Cache clear error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gets cache statistics (if supported by adapter)
     * 
     * @return array Cache statistics or empty array if not supported
     */
    public function getStats(): array {
        try {
            return method_exists($this->adaptor, 'getStats') ? 
                   $this->adaptor->getStats() : 
                   [];
        } catch (Exception $e) {
            error_log('Cache stats error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Gets the current cache adapter name
     * 
     * @return string Adapter name
     */
    public function getAdaptorName(): string {
        $className = get_class($this->adaptor);
        return strtolower(substr($className, strrpos($className, '\\') + 1));
    }
    
    /**
     * Validates cache adapter name
     * 
     * @param string $adaptor Adapter name
     * 
     * @throws InvalidArgumentException If adapter is invalid
     */
    private function validateAdaptor(string $adaptor): void {
        if (empty($adaptor)) {
            throw new InvalidArgumentException('Cache adapter cannot be empty');
        }
        
        if (!in_array(strtolower($adaptor), self::VALID_ADAPTORS, true)) {
            throw new InvalidArgumentException(
                'Invalid cache adapter "' . $adaptor . '". Valid adapters: ' . 
                implode(', ', self::VALID_ADAPTORS)
            );
        }
    }
    
    /**
     * Validates expiration time
     * 
     * @param int $expire Expiration time
     * 
     * @throws InvalidArgumentException If expire is invalid
     */
    private function validateExpire(int $expire): void {
        if ($expire < 0) {
            throw new InvalidArgumentException('Expire time cannot be negative');
        }
        
        if ($expire > 31536000) { // 1 year in seconds
            throw new InvalidArgumentException('Expire time cannot exceed 1 year (31536000 seconds)');
        }
    }
    
    /**
     * Validates cache key
     * 
     * @param string $key Cache key
     * 
     * @throws InvalidArgumentException If key is invalid
     */
    private function validateKey(string $key): void {
        if (empty($key)) {
            throw new InvalidArgumentException('Cache key cannot be empty');
        }
        
        if (strlen($key) > 250) {
            throw new InvalidArgumentException('Cache key cannot exceed 250 characters');
        }
        
        if (preg_match('/[{}()\/@:"]/', $key)) {
            throw new InvalidArgumentException('Cache key contains invalid characters');
        }
    }
    
    /**
     * Initializes the cache adapter
     * 
     * @param string $adaptor Adapter name
     * 
     * @throws RuntimeException If adapter cannot be initialized
     */
    private function initializeAdaptor(string $adaptor): void {
        $class = 'Reamur\\System\\Library\\Cache\\' . ucfirst(strtolower($adaptor));

        if (!class_exists($class)) {
            throw new RuntimeException('Cache adapter class "' . $class . '" not found');
        }

        try {
            $this->adaptor = new $class($this->defaultExpire);
        } catch (Exception $e) {
            throw new RuntimeException(
                'Failed to initialize cache adapter "' . $adaptor . '": ' . $e->getMessage(), 
                0, 
                $e
            );
        }
        
        // Verify adapter implements required methods
        $requiredMethods = ['get', 'set', 'delete'];
        foreach ($requiredMethods as $method) {
            if (!method_exists($this->adaptor, $method)) {
                throw new RuntimeException(
                    'Cache adapter "' . $adaptor . '" does not implement required method: ' . $method
                );
            }
        }
    }
}