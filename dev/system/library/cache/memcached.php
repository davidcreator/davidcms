<?php
namespace Reamur\System\Library\Cache;

/**
 * Class Memcached
 * Cache wrapper for Memcached with improved error handling and validation
 * @package Reamur\System\Library\Cache
 */
class Memcached {
	/** @var \Memcached */
	private \Memcached $memcached;

	/** @var int */
	private int $expire;

	/** @var bool */
	private bool $connected = false;

	/** @var int */
	const CACHEDUMP_LIMIT = 9999;

	/**
	 * Constructor
	 * @param int $expire Default expiration time in seconds
	 * @throws \Exception When cache server connection fails
	 */
	public function __construct(int $expire = 3600) {
		$this->expire = $expire;

		// Validate constants are defined
		if (!defined('CACHE_HOSTNAME') || !defined('CACHE_PORT') || !defined('CACHE_PREFIX')) {
			throw new \Exception('Cache constants (CACHE_HOSTNAME, CACHE_PORT, CACHE_PREFIX) are not defined');
		}

		$this->memcached = new \Memcached();
		
		// Check if server is already added to avoid duplicates
		if (empty($this->memcached->getServerList())) {
			$this->connected = $this->memcached->addServer(CACHE_HOSTNAME, CACHE_PORT);
			
			if (!$this->connected) {
				throw new \Exception('Failed to connect to Memcached server at ' . CACHE_HOSTNAME . ':' . CACHE_PORT);
			}
		} else {
			$this->connected = true;
		}

		// Test connection
		if (!$this->testConnection()) {
			throw new \Exception('Memcached server is not responding');
		}
	}

	/**
	 * Test connection to Memcached server
	 * @return bool
	 */
	private function testConnection(): bool {
		$stats = $this->memcached->getStats();
		return !empty($stats);
	}

	/**
	 * Validate cache key
	 * @param string $key
	 * @throws \InvalidArgumentException
	 */
	private function validateKey(string $key): void {
		if (empty($key)) {
			throw new \InvalidArgumentException('Cache key cannot be empty');
		}

		if (strlen($key) > 250) {
			throw new \InvalidArgumentException('Cache key too long (max 250 characters)');
		}

		// Check for invalid characters
		if (preg_match('/[\s\x00-\x1f\x7f]/', $key)) {
			throw new \InvalidArgumentException('Cache key contains invalid characters');
		}
	}

	/**
	 * Get value from cache
	 * @param string $key
	 * @return array|string|null
	 * @throws \InvalidArgumentException
	 */
	public function get(string $key): array|string|null {
		$this->validateKey($key);

		if (!$this->connected) {
			return null;
		}

		$result = $this->memcached->get(CACHE_PREFIX . $key);
		
		// Check if get operation failed
		if ($result === false && $this->memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
			return null;
		}

		return $result;
	}

	/**
	 * Set value in cache
	 * @param string $key
	 * @param array|string|null $value
	 * @param int $expire Expiration time in seconds (0 = use default)
	 * @return bool Success status
	 * @throws \InvalidArgumentException
	 */
	public function set(string $key, array|string|null $value, int $expire = 0): bool {
		$this->validateKey($key);

		if (!$this->connected) {
			return false;
		}

		if ($expire < 0) {
			throw new \InvalidArgumentException('Expire time cannot be negative');
		}

		if (!$expire) {
			$expire = $this->expire;
		}

		$success = $this->memcached->set(CACHE_PREFIX . $key, $value, $expire);
		
		if (!$success) {
			error_log('Memcached set failed for key: ' . $key . ' - ' . $this->memcached->getResultMessage());
		}

		return $success;
	}

	/**
	 * Delete value from cache
	 * @param string $key
	 * @return bool Success status
	 * @throws \InvalidArgumentException
	 */
	public function delete(string $key): bool {
		$this->validateKey($key);

		if (!$this->connected) {
			return false;
		}

		$success = $this->memcached->delete(CACHE_PREFIX . $key);
		
		// Memcached returns false if key doesn't exist, which is not an error
		if (!$success && $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND) {
			error_log('Memcached delete failed for key: ' . $key . ' - ' . $this->memcached->getResultMessage());
		}

		return $success || $this->memcached->getResultCode() === \Memcached::RES_NOTFOUND;
	}

	/**
	 * Check if a key exists in cache
	 * @param string $key
	 * @return bool
	 * @throws \InvalidArgumentException
	 */
	public function exists(string $key): bool {
		$this->validateKey($key);

		if (!$this->connected) {
			return false;
		}

		$this->memcached->get(CACHE_PREFIX . $key);
		return $this->memcached->getResultCode() === \Memcached::RES_SUCCESS;
	}

	/**
	 * Flush all cache entries
	 * @return bool Success status
	 */
	public function flush(): bool {
		if (!$this->connected) {
			return false;
		}

		return $this->memcached->flush();
	}

	/**
	 * Get connection status
	 * @return bool
	 */
	public function isConnected(): bool {
		return $this->connected;
	}

	/**
	 * Get last error message
	 * @return string
	 */
	public function getLastError(): string {
		return $this->memcached->getResultMessage();
	}

	/**
	 * Get cache statistics
	 * @return array|false
	 */
	public function getStats(): array|false {
		if (!$this->connected) {
			return false;
		}

		return $this->memcached->getStats();
	}
}