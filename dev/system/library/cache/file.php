<?php
namespace Reamur\System\Library\Cache;

/**
 * Class File
 *
 * File-based cache implementation compatible with OpenCart MVCL architecture
 *
 * @package Reamur\System\Library\Cache
 */
class File {
    /**
     * @var int Default expiration time in seconds
     */
    private int $expire;

    /**
     * @var string Cache file prefix
     */
    private const CACHE_PREFIX = 'cache.';

    /**
     * @var string Cache file extension separator
     */
    private const EXTENSION_SEPARATOR = '.';

    /**
     * @var int Maximum retry attempts for file operations
     */
    private const MAX_RETRY_ATTEMPTS = 5;

    /**
     * @var int Cleanup probability (1 in X chance)
     */
    private const CLEANUP_PROBABILITY = 100;

    /**
     * @var int Retry delay in microseconds (200ms)
     */
    private const RETRY_DELAY = 200000;

    /**
     * Constructor
     *
     * @param int $expire Default expiration time in seconds (default: 3600 = 1 hour)
     */
    public function __construct(int $expire = 3600) {
        $this->expire = max(1, $expire); // Ensure minimum 1 second expiration
    }

    /**
     * Get cached value by key
     *
     * @param string $key Cache key
     * @return array|string|null Cached value or null if not found/expired
     */
    public function get(string $key): array|string|null {
        if (empty($key)) {
            return null;
        }

        $sanitizedKey = $this->sanitizeKey($key);
        $files = glob(DIR_CACHE . self::CACHE_PREFIX . $sanitizedKey . self::EXTENSION_SEPARATOR . '*');

        if (empty($files) || !is_file($files[0])) {
            return null;
        }

        $file = $files[0];
        
        // Check if file is expired based on filename
        if ($this->isFileExpired($file)) {
            $this->deleteFile($file);
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            error_log("[Cache] Failed to read cache file: $file");
            return null;
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[Cache] Invalid JSON in cache file: $file - " . json_last_error_msg());
            $this->deleteFile($file);
            return null;
        }

        return $decoded;
    }

    /**
     * Set cached value
     *
     * @param string $key Cache key
     * @param array|string|null $value Value to cache
     * @param int $expire Expiration time in seconds (0 = use default)
     * @return bool Success status
     */
    public function set(string $key, array|string|null $value, int $expire = 0): bool {
        if (empty($key)) {
            return false;
        }

        // Delete existing cache entries for this key
        $this->delete($key);

        $expireTime = $expire > 0 ? $expire : $this->expire;
        $cacheDir = DIR_CACHE;

        // Ensure cache directory exists
        if (!$this->ensureCacheDirectory($cacheDir)) {
            return false;
        }

        $sanitizedKey = $this->sanitizeKey($key);
        $filename = $cacheDir . self::CACHE_PREFIX . $sanitizedKey . self::EXTENSION_SEPARATOR . (time() + $expireTime);

        $jsonData = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonData === false) {
            error_log("[Cache] Failed to encode value for key: $key - " . json_last_error_msg());
            return false;
        }

        $result = @file_put_contents($filename, $jsonData, LOCK_EX);
        if ($result === false) {
            error_log("[Cache] Failed to write cache file: $filename");
            return false;
        }

        // Set appropriate permissions
        @chmod($filename, 0644);
        
        return true;
    }

    /**
     * Delete cached value by key
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool {
        if (empty($key)) {
            return false;
        }

        $sanitizedKey = $this->sanitizeKey($key);
        $files = glob(DIR_CACHE . self::CACHE_PREFIX . $sanitizedKey . self::EXTENSION_SEPARATOR . '*');

        if (empty($files)) {
            return true; // Nothing to delete
        }

        $success = true;
        foreach ($files as $file) {
            if (!$this->deleteFile($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Clear all cache files
     *
     * @return bool Success status
     */
    public function clear(): bool {
        $files = glob(DIR_CACHE . self::CACHE_PREFIX . '*');
        
        if (empty($files)) {
            return true;
        }

        $success = true;
        foreach ($files as $file) {
            if (!$this->deleteFile($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Check if cache key exists and is not expired
     *
     * @param string $key Cache key
     * @return bool True if key exists and is valid
     */
    public function has(string $key): bool {
        return $this->get($key) !== null;
    }

    /**
     * Destructor - performs garbage collection
     */
    public function __destruct() {
        // Run cleanup with probability to avoid performance impact
        if (rand(1, self::CLEANUP_PROBABILITY) === 1) {
            $this->cleanup();
        }
    }

    /**
     * Manual cleanup of expired cache files
     *
     * @return int Number of files cleaned up
     */
    public function cleanup(): int {
        $files = glob(DIR_CACHE . self::CACHE_PREFIX . '*');
        
        if (empty($files)) {
            return 0;
        }

        $cleaned = 0;
        $currentTime = time();

        foreach ($files as $file) {
            if ($this->isFileExpired($file, $currentTime)) {
                if ($this->deleteFile($file)) {
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }

    /**
     * Sanitize cache key to prevent directory traversal and invalid characters
     *
     * @param string $key Raw cache key
     * @return string Sanitized key
     */
    private function sanitizeKey(string $key): string {
        // Remove any path traversal attempts and invalid characters
        $sanitized = preg_replace('/[^A-Z0-9\._-]/i', '', $key);
        
        // Ensure key is not empty after sanitization
        if (empty($sanitized)) {
            $sanitized = md5($key);
        }

        // Limit key length to prevent filesystem issues
        return substr($sanitized, 0, 100);
    }

    /**
     * Check if cache file is expired based on filename timestamp
     *
     * @param string $file File path
     * @param int|null $currentTime Current timestamp (optional)
     * @return bool True if file is expired
     */
    private function isFileExpired(string $file, ?int $currentTime = null): bool {
        $currentTime = $currentTime ?? time();
        $expireTime = substr(strrchr($file, self::EXTENSION_SEPARATOR), 1);
        
        return is_numeric($expireTime) && (int)$expireTime < $currentTime;
    }

    /**
     * Delete a single file with retry mechanism
     *
     * @param string $file File path to delete
     * @return bool Success status
     */
    private function deleteFile(string $file): bool {
        if (!file_exists($file)) {
            return true;
        }

        for ($attempts = 0; $attempts < self::MAX_RETRY_ATTEMPTS; $attempts++) {
            if (@unlink($file)) {
                return true;
            }
            
            clearstatcache(false, $file);
            usleep(self::RETRY_DELAY);
        }

        error_log("[Cache] Failed to delete cache file after " . self::MAX_RETRY_ATTEMPTS . " attempts: $file");
        return false;
    }

    /**
     * Ensure cache directory exists and is writable
     *
     * @param string $directory Directory path
     * @return bool Success status
     */
    private function ensureCacheDirectory(string $directory): bool {
        if (is_dir($directory) && is_writable($directory)) {
            return true;
        }

        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0755, true)) {
                error_log("[Cache] Failed to create cache directory: $directory");
                return false;
            }
        }

        if (!is_writable($directory)) {
            error_log("[Cache] Cache directory is not writable: $directory");
            return false;
        }

        return true;
    }
}