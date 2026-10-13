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
 * Class Request
 * Handles HTTP request data with input sanitization and validation
 */
class Request {
    public array $get = [];
    public array $post = [];
    public array $cookie = [];
    public array $files = [];
    public array $server = [];
    public array $request = [];
    
    /**
     * Maximum file size allowed (in bytes)
     */
    private const MAX_FILE_SIZE = 10485760; // 10MB
    
    /**
     * Allowed file extensions for uploads
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 
        'txt', 'zip', 'rar', 'mp4', 'mp3', 'webp'
    ];

    public function __construct() {
        $this->get = $this->clean($_GET ?? []);
        $this->post = $this->clean($_POST ?? []);
        $this->cookie = $this->clean($_COOKIE ?? []);
        $this->files = $this->cleanFiles($_FILES ?? []);
        $this->server = $this->cleanServer($_SERVER ?? []);
        $this->request = $this->clean($_REQUEST ?? []);
    }

    /**
     * Sanitizes input data recursively
     * @param mixed $data Input data to clean
     * @return mixed Sanitized data
     */
    public function clean(mixed $data): mixed {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleanKey = $this->cleanKey($key);
                if ($cleanKey !== null) {
                    $cleaned[$cleanKey] = $this->clean($value);
                }
            }
            return $cleaned;
        }

        if (!is_scalar($data) || is_bool($data)) {
            return $data;
        }

        // Convert to string and sanitize
        $data = (string) $data;
        
        // Remove null bytes and control characters
        $data = str_replace(["\0", "\x0B"], '', $data);
        $data = preg_replace('/[\x00-\x08\x0E-\x1F\x7F]/', '', $data);
        
        return trim(htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8'));
    }

    /**
     * Clean and validate array keys
     * @param mixed $key Array key to clean
     * @return string|null Cleaned key or null if invalid
     */
    private function cleanKey(mixed $key): ?string {
        if (!is_scalar($key)) {
            return null;
        }
        
        $key = (string) $key;
        
        // Remove dangerous characters from keys
        $key = preg_replace('/[^a-zA-Z0-9_\-\[\]]/', '', $key);
        
        // Treat '0' as a valid key; only reject truly empty strings
        return $key === '' ? null : $key;
    }

    /**
     * Special handling for server variables
     * @param array $server $_SERVER array
     * @return array Cleaned server array
     */
    private function cleanServer(array $server): array {
        $cleaned = [];
        $allowedKeys = [
            'REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING', 'HTTP_HOST',
            'HTTP_USER_AGENT', 'HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE',
            'HTTP_ACCEPT_ENCODING', 'HTTP_REFERER', 'HTTPS', 'SERVER_NAME',
            'SERVER_PORT', 'SCRIPT_NAME', 'PATH_INFO', 'REMOTE_ADDR',
            'REMOTE_HOST', 'REQUEST_TIME', 'REQUEST_TIME_FLOAT', 'argv'
        ];
        
        foreach ($server as $key => $value) {
            if (in_array($key, $allowedKeys) && (is_scalar($value) || $key === 'argv')) {
                $cleaned[$key] = $this->clean($value);
            }
        }
        
        return $cleaned;
    }

    /**
     * Enhanced file upload handling with validation
     * @param array $files $_FILES array
     * @return array Cleaned and validated files array
     */
    private function cleanFiles(array $files): array {
        $cleaned = [];
        
        foreach ($files as $key => $file) {
            $cleanKey = $this->cleanKey($key);
            if ($cleanKey === null) {
                continue;
            }
            
            if (is_array($file['name'])) {
                // Handle multiple file uploads
                $cleaned[$cleanKey] = [];
                foreach ($file['name'] as $i => $name) {
                    $fileData = [
                        'name' => $this->clean($name),
                        'type' => $file['type'][$i] ?? '',
                        'tmp_name' => $file['tmp_name'][$i] ?? '',
                        'error' => (int)($file['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                        'size' => (int)($file['size'][$i] ?? 0)
                    ];
                    
                    if ($this->validateFile($fileData)) {
                        $cleaned[$cleanKey][$i] = $fileData;
                    }
                }
            } else {
                $fileData = [
                    'name' => $this->clean($file['name'] ?? ''),
                    'type' => $file['type'] ?? '',
                    'tmp_name' => $file['tmp_name'] ?? '',
                    'error' => (int)($file['error'] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int)($file['size'] ?? 0)
                ];
                
                if ($this->validateFile($fileData)) {
                    $cleaned[$cleanKey] = $fileData;
                }
            }
        }
        
        return $cleaned;
    }

    /**
     * Validate uploaded file
     * @param array $file File data array
     * @return bool True if file is valid
     */
    private function validateFile(array $file): bool {
        // Skip validation if no file uploaded
        if ($file['error'] === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
            return true;
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE || $file['size'] <= 0) {
            return false;
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return false;
        }
        
        // Verify uploaded file exists and is valid
        if (!empty($file['tmp_name']) && !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Get a specific request parameter with type casting
     * @param string $key Parameter name
     * @param mixed $default Default value if not found
     * @param string $source Input source (get|post|cookie|server|request)
     * @param string $type Expected data type (string|int|float|bool|array)
     * @return mixed Parameter value or default
     */
    public function getParam(string $key, mixed $default = null, string $source = 'get', string $type = 'string'): mixed {
        $source = strtolower($source);
        if (!in_array($source, ['get', 'post', 'cookie', 'server', 'request'])) {
            return $default;
        }

        $value = $this->{$source}[$key] ?? $default;
        
        return $this->castType($value, $type, $default);
    }

    /**
     * Cast value to specific type
     * @param mixed $value Value to cast
     * @param string $type Target type
     * @param mixed $default Default value if casting fails
     * @return mixed Casted value or default
     */
    private function castType(mixed $value, string $type, mixed $default): mixed {
        if ($value === null) {
            return $default;
        }
        
        return match ($type) {
            'int', 'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : $default,
            'float', 'double' => filter_var($value, FILTER_VALIDATE_FLOAT) !== false ? (float)$value : $default,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default,
            'array' => is_array($value) ? $value : $default,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : $default,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : $default,
            default => (string)$value
        };
    }

    /**
     * Check if parameter exists in specified source
     * @param string $key Parameter name
     * @param string $source Input source
     * @return bool True if parameter exists
     */
    public function hasParam(string $key, string $source = 'get'): bool {
        $source = strtolower($source);
        if (!in_array($source, ['get', 'post', 'cookie', 'server', 'request'])) {
            return false;
        }

        return isset($this->{$source}[$key]);
    }

    /**
     * Get all parameters from specified source
     * @param string $source Input source
     * @return array All parameters from source
     */
    public function getAllParams(string $source = 'get'): array {
        $source = strtolower($source);
        if (!in_array($source, ['get', 'post', 'cookie', 'server', 'request'])) {
            return [];
        }

        return $this->{$source};
    }

    /**
     * Get request method
     * @return string HTTP request method
     */
    public function getMethod(): string {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Check if request is POST
     * @return bool True if POST request
     */
    public function isPost(): bool {
        return $this->getMethod() === 'POST';
    }

    /**
     * Check if request is AJAX
     * @return bool True if AJAX request
     */
    public function isAjax(): bool {
        return strtolower($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    /**
     * Check if connection is HTTPS
     * @return bool True if HTTPS
     */
    public function isHttps(): bool {
        return !empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
    }
}
