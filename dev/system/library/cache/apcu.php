<?php
namespace Reamur\System\Library\Cache;

/**
 * Class APCU
 * Cache implementation using APCu (User Cache)
 * 
 * @package Reamur\System\Library\Cache
 * @author  Reamur System
 * @version 1.1.0
 */
class APCU {
    /** @var int Default expiration time in seconds */
    private int $expire;

    /** @var bool Whether APCu is active and available */
    private bool $active;

    /**
     * Constructor
     * 
     * @param int $expire Default expiration time in seconds (default: 3600 = 1 hour)
     */
    public function __construct(int $expire = 3600) {
        $this->expire = max(1, $expire); // Ensure minimum 1 second
        $this->active = $this->isApucAvailable();
    }

    /**
     * Check if APCu is available and enabled
     * 
     * @return bool
     */
    private function isApucAvailable(): bool {
        return extension_loaded('apcu') && 
               function_exists('apcu_enabled') && 
               apcu_enabled() && 
               function_exists('apcu_fetch');
    }

    /**
     * Get cache value by key
     * 
     * @param string $key Cache key
     * @return mixed|null Returns cached value or null if not found/error
     */
    public function get(string $key): mixed {
        if (!$this->active || empty($key)) {
            return null;
        }

        $success = false;
        $result = apcu_fetch($this->getCacheKey($key), $success);
        
        return $success ? $result : null;
    }

    /**
     * Set cache value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache (arrays, strings, objects, etc.)
     * @param int $expire Expiration time in seconds (0 = use default)
     * @return bool True on success, false on failure
     */
    public function set(string $key, mixed $value, int $expire = 0): bool {
        if (!$this->active || empty($key)) {
            return false;
        }

        if ($expire <= 0) {
            $expire = $this->expire;
        }

        return apcu_store($this->getCacheKey($key), $value, $expire);
    }

    /**
     * Delete cache entry by key
     * 
     * @param string $key Cache key to delete
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool {
        if (!$this->active || empty($key)) {
            return false;
        }

        $cacheKey = $this->getCacheKey($key);
        
        // Try direct deletion first (more efficient)
        if (apcu_exists($cacheKey)) {
            return apcu_delete($cacheKey);
        }

        // Fallback: pattern matching deletion for partial keys
        return $this->deleteByPattern($key);
    }

    /**
     * Delete cache entries by pattern
     * 
     * @param string $pattern Pattern to match
     * @return bool True if at least one entry was deleted
     */
    private function deleteByPattern(string $pattern): bool {
        if (!function_exists('apcu_cache_info')) {
            return false;
        }

        $cache_info = apcu_cache_info();
        
        if (!isset($cache_info['cache_list']) || !is_array($cache_info['cache_list'])) {
            return false;
        }

        $deleted = false;
        $searchPattern = $this->getCacheKey($pattern);

        foreach ($cache_info['cache_list'] as $entry) {
            if (isset($entry['info']) && strpos($entry['info'], $searchPattern) === 0) {
                if (apcu_delete($entry['info'])) {
                    $deleted = true;
                }
            }
        }

        return $deleted;
    }

    /**
     * Clear all cache entries
     * 
     * @return bool True on success, false on failure
     */
    public function flush(): bool {
        if (!$this->active) {
            return false;
        }

        if (function_exists('apcu_clear_cache')) {
            return apcu_clear_cache();
        }

        return false;
    }

    /**
     * Check if a cache key exists
     * 
     * @param string $key Cache key
     * @return bool True if exists, false otherwise
     */
    public function exists(string $key): bool {
        if (!$this->active || empty($key)) {
            return false;
        }

        return apcu_exists($this->getCacheKey($key));
    }

    /**
     * Get cache statistics
     * 
     * @return array|null Cache info array or null if not available
     */
    public function getStats(): ?array {
        if (!$this->active || !function_exists('apcu_cache_info')) {
            return null;
        }

        return apcu_cache_info();
    }

    /**
     * Get the actual cache key with prefix
     * 
     * @param string $key Original key
     * @return string Prefixed cache key
     */
    private function getCacheKey(string $key): string {
        $prefix = defined('CACHE_PREFIX') ? CACHE_PREFIX : 'cache_';
        return $prefix . $key;
    }

    /**
     * Check if APCu is active
     * 
     * @return bool
     */
    public function isActive(): bool {
        return $this->active;
    }

    /**
     * Get default expiration time
     * 
     * @return int
     */
    public function getExpire(): int {
        return $this->expire;
    }

    /**
     * Set default expiration time
     * 
     * @param int $expire Expiration time in seconds
     * @return void
     */
    public function setExpire(int $expire): void {
        $this->expire = max(1, $expire);
    }
}