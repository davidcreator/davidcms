<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

namespace Reamur\System\Library;

/** 
 * Class Log
 * Handles application logging functionality with improved error handling and performance
 */
class Log {
    private string $file;
    private $handle;
    private bool $autoclose;
    private int $maxFileSize;
    
    // Log levels constants
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';

    /**
     * Constructor
     * @param string $filename Log filename
     * @param bool $autoclose Whether to auto-close file handle after each write
     * @param int $maxFileSize Maximum file size in bytes before rotation (0 = no limit)
     * @throws \InvalidArgumentException If filename is invalid
     * @throws \RuntimeException If file cannot be opened
     */
    public function __construct(string $filename, bool $autoclose = true, int $maxFileSize = 0) {
        if (empty($filename)) {
            throw new \InvalidArgumentException('Filename must be a non-empty string');
        }

        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $filename)) {
            throw new \InvalidArgumentException('Invalid filename format');
        }

        $this->file = DIR_LOGS . $filename;
        $this->autoclose = $autoclose;
        $this->maxFileSize = $maxFileSize;
        
        // Ensure directory exists with proper error handling
        $this->ensureLogDirectory();
        
        // Initialize file handle if not auto-closing
        if (!$this->autoclose) {
            $this->openFileHandle();
        }
    }

    /**
     * Ensure log directory exists
     * @throws \RuntimeException If directory cannot be created
     */
    private function ensureLogDirectory(): void {
        if (!is_dir(DIR_LOGS)) {
            if (!mkdir(DIR_LOGS, 0755, true) && !is_dir(DIR_LOGS)) {
                throw new \RuntimeException('Cannot create log directory: ' . DIR_LOGS);
            }
        }

        // Check if directory is writable
        if (!is_writable(DIR_LOGS)) {
            throw new \RuntimeException('Log directory is not writable: ' . DIR_LOGS);
        }
    }

    /**
     * Open file handle for writing
     * @throws \RuntimeException If file cannot be opened
     */
    private function openFileHandle(): void {
        if ($this->handle) {
            return; // Already open
        }

        $this->handle = fopen($this->file, 'a');
        if ($this->handle === false) {
            throw new \RuntimeException('Cannot open log file for writing: ' . $this->file);
        }
    }

    /**
     * Check and rotate log file if needed
     */
    private function rotateLogIfNeeded(): void {
        if ($this->maxFileSize <= 0 || !file_exists($this->file)) {
            return;
        }

        if (filesize($this->file) >= $this->maxFileSize) {
            $rotatedFile = $this->file . '.' . date('Y-m-d_H-i-s') . '.old';
            rename($this->file, $rotatedFile);
            
            // Close and reopen handle if it was open
            if ($this->handle) {
                fclose($this->handle);
                $this->handle = null;
                if (!$this->autoclose) {
                    $this->openFileHandle();
                }
            }
        }
    }

    /**
     * Write message to log
     * @param string|array|object $message Message to log
     * @param string $level Log level
     * @param array $context Additional context data
     * @throws \InvalidArgumentException If message is invalid
     * @throws \RuntimeException If write fails
     */
    public function write($message, string $level = self::LEVEL_INFO, array $context = []): void {
        if ($message === null || (is_string($message) && trim($message) === '')) {
            throw new \InvalidArgumentException('Message cannot be null or empty');
        }

        try {
            // Check for log rotation
            $this->rotateLogIfNeeded();

            // Format message
            $formattedMessage = $this->formatMessage($message, $level, $context);
            
            if ($this->autoclose) {
                // Write with file locking for thread safety
                if (file_put_contents($this->file, $formattedMessage, FILE_APPEND | LOCK_EX) === false) {
                    throw new \RuntimeException('Failed to write to log file: ' . $this->file);
                }
            } else {
                // Use file handle for better performance with multiple writes
                if (!$this->handle) {
                    $this->openFileHandle();
                }
                
                if (flock($this->handle, LOCK_EX)) {
                    if (fwrite($this->handle, $formattedMessage) === false) {
                        throw new \RuntimeException('Failed to write to log file: ' . $this->file);
                    }
                    fflush($this->handle);
                    flock($this->handle, LOCK_UN);
                } else {
                    throw new \RuntimeException('Cannot acquire lock on log file: ' . $this->file);
                }
            }
        } catch (\Exception $e) {
            // Preserve original exception message and add context
            throw new \RuntimeException('Log write failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Format log message
     * @param mixed $message Message to format
     * @param string $level Log level
     * @param array $context Context data
     * @return string Formatted message
     */
    private function formatMessage($message, string $level, array $context): string {
        $timestamp = date('Y-m-d H:i:s');
        $pid = getmypid();
        $memory = memory_get_usage(true);
        
        // Convert message to string
        if (is_array($message) || is_object($message)) {
            $messageStr = print_r($message, true);
        } else {
            $messageStr = (string)$message;
        }

        $logLine = sprintf(
            "[%s] [%s] [PID:%d] [MEM:%s] %s",
            $timestamp,
            $level,
            $pid,
            $this->formatBytes($memory),
            trim($messageStr)
        );

        // Add context if provided
        if (!empty($context)) {
            $logLine .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        return $logLine . PHP_EOL;
    }

    /**
     * Format bytes for human readable output
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . $units[$pow];
    }

    /**
     * Convenience methods for different log levels
     */
    public function debug($message, array $context = []): void {
        $this->write($message, self::LEVEL_DEBUG, $context);
    }

    public function info($message, array $context = []): void {
        $this->write($message, self::LEVEL_INFO, $context);
    }

    public function warning($message, array $context = []): void {
        $this->write($message, self::LEVEL_WARNING, $context);
    }

    public function error($message, array $context = []): void {
        $this->write($message, self::LEVEL_ERROR, $context);
    }

    public function critical($message, array $context = []): void {
        $this->write($message, self::LEVEL_CRITICAL, $context);
    }

    /**
     * Clear log file
     * @throws \RuntimeException If clear fails
     */
    public function clear(): void {
        try {
            if ($this->handle) {
                fclose($this->handle);
                $this->handle = null;
            }
            
            if (file_exists($this->file)) {
                if (!unlink($this->file)) {
                    throw new \RuntimeException('Cannot clear log file: ' . $this->file);
                }
            }
            
            if (!$this->autoclose) {
                $this->openFileHandle();
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to clear log file: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get log file size
     * @return int File size in bytes
     */
    public function getFileSize(): int {
        return file_exists($this->file) ? filesize($this->file) : 0;
    }

    /**
     * Get log file path
     * @return string File path
     */
    public function getFilePath(): string {
        return $this->file;
    }

    /**
     * Destructor - close file handle if open
     */
    public function __destruct() {
        if ($this->handle && is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}