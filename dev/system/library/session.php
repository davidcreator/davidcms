<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

namespace Reamur\System\Library;

use Reamur\System\Engine\Registry;
use RuntimeException;
use Throwable;

/**
 * Class Session
 * 
 * Manages session handling with pluggable adapters for different storage backends
 */
class Session {
    /** @var object|null Session adapter instance */
    protected ?object $adaptor = null;
    
    /** @var string|null Current session ID */
    protected ?string $session_id = null;
    
    /** @var array Session data storage */
    public array $data = [];
    
    /** @var bool Flag to track if session has been started */
    private bool $started = false;
    
    /** @var bool Flag to track if shutdown functions are registered */
    private bool $shutdown_registered = false;

    /**
     * Constructor
     * 
     * @param string $adaptor The session adapter name
     * @param Registry|null $registry The registry instance (optional for backward compatibility)
     * @throws RuntimeException If adapter class doesn't exist or initialization fails
     */
    public function __construct(string $adaptor, ?Registry $registry = null) {
        if (empty($adaptor)) {
            throw new RuntimeException('Session adapter name cannot be empty');
        }

        $class = 'Reamur\\System\\Library\\Session\\' . ucfirst($adaptor);
        
        if (!class_exists($class)) {
            throw new RuntimeException("Session adapter class '{$class}' not found");
        }

        try {
            $this->adaptor = $registry !== null ? new $class($registry) : new $class();
            $this->registerShutdownFunctions();
        } catch (Throwable $e) {
            throw new RuntimeException("Session initialization failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Register shutdown functions once
     */
    private function registerShutdownFunctions(): void {
        if (!$this->shutdown_registered) {
            register_shutdown_function([$this, 'close']);
            register_shutdown_function([$this, 'gc']);
            $this->shutdown_registered = true;
        }
    }

    /**
     * Get current session ID
     * 
     * @return string The session ID
     * @throws RuntimeException If session is not started
     */
    public function getId(): string {
        if (!$this->started || empty($this->session_id)) {
            throw new RuntimeException('Session not started');
        }
        return $this->session_id;
    }

    /**
     * Start session with optional custom session ID
     * 
     * @param string $session_id Optional custom session ID
     * @return string The active session ID
     * @throws RuntimeException If session start fails or invalid session ID
     */
    public function start(string $session_id = ''): string {
        if ($this->started) {
            return $this->session_id ?? '';
        }

        // Check if session is already active
        $session_active = (session_status() === PHP_SESSION_ACTIVE);
        
        // If session is not active, we can set custom ID and configure settings
        if (!$session_active) {
            // Set custom session ID if provided and session is not active
            if (!empty($session_id)) {
                if (!$this->isValidSessionId($session_id)) {
                    throw new RuntimeException('Invalid custom session ID format');
                }
                session_id($session_id);
            }

            // Configure session settings before starting
            $this->configureSessionSettings();

            // Start the session
            if (!session_start()) {
                throw new RuntimeException('Failed to start session');
            }
        }

        $this->session_id = session_id();
        
        // Validate the session ID format - only regenerate if session wasn't already active
        if (!$this->isValidSessionId($this->session_id)) {
            if (!$session_active) {
                session_regenerate_id(true);
                $this->session_id = session_id();
            } else {
                // If session was already active with invalid ID, log warning but continue
                error_log("Warning: Active session has invalid ID format: {$this->session_id}");
            }
        }

        // Load existing session data or initialize empty array
        $this->data = $_SESSION ?? [];
        $this->started = true;

        return $this->session_id;
    }

    /**
     * Configure session security settings
     */
    private function configureSessionSettings(): void {
        // Only configure if session hasn't started yet
        if (session_status() === PHP_SESSION_NONE) {
            // Use ini_set with error checking
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? '1' : '0');
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.cookie_samesite', 'Lax');
        }
    }

    /**
     * Validate session ID format
     * 
     * @param string $sessionId The session ID to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidSessionId(string $sessionId): bool {
        // Standard session ID format: alphanumeric, comma, dash, 22-128 characters
        return preg_match('/^[a-zA-Z0-9,-]{22,128}$/', $sessionId) === 1;
    }

    /**
     * Close and save session data
     */
    public function close(): void {
        if (!$this->started || empty($this->session_id) || $this->adaptor === null) {
            return;
        }

        try {
            // Sync data to $_SESSION
            $_SESSION = $this->data;
            
            // Write session data using adapter
            $this->adaptor->write($this->session_id, $this->data);
            
        } catch (Throwable $e) {
            // Log error but don't throw to avoid breaking shutdown process
            error_log("Session close failed: {$e->getMessage()}");
        }
    }

    /**
     * Destroy session and clean up data
     */
    public function destroy(): void {
        try {
            // Clear session data
            $this->data = [];
            $_SESSION = [];

            // Destroy PHP session if active
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            
            // Remove session from storage using adapter
            if (!empty($this->session_id) && $this->adaptor !== null) {
                $this->adaptor->destroy($this->session_id);
            }

            // Reset internal state
            $this->session_id = null;
            $this->started = false;

        } catch (Throwable $e) {
            // Log error but continue cleanup
            error_log("Session destroy failed: {$e->getMessage()}");
            
            // Still reset internal state
            $this->session_id = null;
            $this->started = false;
        }
    }

    /**
     * Perform garbage collection
     */
    public function gc(): void {
        if ($this->adaptor === null) {
            return;
        }

        try {
            // Call adapter's garbage collection method
            if (method_exists($this->adaptor, 'gc')) {
                if (!empty($this->session_id)) {
                    $this->adaptor->gc($this->session_id);
                } else {
                    // Call without session ID for general cleanup
                    $this->adaptor->gc();
                }
            }
        } catch (Throwable $e) {
            // Log error but don't throw to avoid breaking shutdown process
            error_log("Session garbage collection failed: {$e->getMessage()}");
        }
    }

    /**
     * Regenerate session ID for security
     * 
     * @param bool $delete_old_session Whether to delete the old session
     * @return string The new session ID
     * @throws RuntimeException If session is not started
     */
    public function regenerateId(bool $delete_old_session = true): string {
        if (!$this->started) {
            throw new RuntimeException('Cannot regenerate ID: session not started');
        }

        $old_session_id = $this->session_id;

        if (session_regenerate_id($delete_old_session)) {
            $this->session_id = session_id();
            
            // If we have an adapter and old session ID, handle cleanup
            if ($delete_old_session && $old_session_id && $this->adaptor !== null) {
                try {
                    $this->adaptor->destroy($old_session_id);
                } catch (Throwable $e) {
                    error_log("Failed to cleanup old session: {$e->getMessage()}");
                }
            }
        }

        return $this->session_id ?? '';
    }

    /**
     * Check if session is started
     * 
     * @return bool True if session is started
     */
    public function isStarted(): bool {
        return $this->started && !empty($this->session_id);
    }

    /**
     * Set session data value
     * 
     * @param string $key The data key
     * @param mixed $value The data value
     */
    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
        if ($this->started) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Get session data value
     * 
     * @param string $key The data key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The data value or default
     */
    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if session data key exists
     * 
     * @param string $key The data key
     * @return bool True if key exists
     */
    public function has(string $key): bool {
        return array_key_exists($key, $this->data);
    }

    /**
     * Remove session data key
     * 
     * @param string $key The data key to remove
     */
    public function remove(string $key): void {
        unset($this->data[$key]);
        if ($this->started && isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
}
