<?php
namespace Reamur\System\Library\Security;

/**
 * Upload Security Class
 * 
 * Provides enhanced security checks for file uploads to prevent malicious files
 * and circumvention attempts.
 */
class UploadSecurity {
    private $config;
    private $logger;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration options
     * @param object $logger Optional logger object
     */
    public function __construct($config = [], $logger = null) {
        // Default configuration
        $this->config = array_merge([
            'max_file_size' => 2097152, // 2MB default max size
            'allowed_extensions' => [],
            'allowed_mime_types' => [],
            'disallowed_extensions' => [
                // Executable files
                'exe', 'bat', 'cmd', 'sh', 'com', 'bin', 'msi', 'dll', 'so',
                // Scripts
                'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'pht', 'phar',
                'pl', 'py', 'cgi', 'asp', 'aspx', 'jsp', 'js', 'vbs',
                // Other potentially dangerous files
                'htaccess', 'htpasswd', 'config', 'conf', 'ini'
            ],
            'scan_file_content' => true,
            'suspicious_patterns' => [
                // PHP code patterns
                '/<\?php/i',
                '/eval\s*\(/i',
                '/base64_decode\s*\(/i',
                '/system\s*\(/i',
                '/exec\s*\(/i',
                '/shell_exec\s*\(/i',
                '/passthru\s*\(/i',
                // Script injection patterns
                '/<script[^>]*>/i',
                // Iframe injection
                '/<iframe[^>]*>/i',
                // SQL injection patterns
                '/UNION\s+SELECT/i',
                // Shell patterns
                '/\$_GET\s*\[/i',
                '/\$_POST\s*\[/i',
                '/\$_REQUEST\s*\[/i',
                '/\$_SERVER\s*\[/i',
                // Obfuscation techniques
                '/\\x[0-9a-f]{2}/i',
                '/chr\s*\(/i'
            ],
            'verify_mime_type' => true,
            'mime_type_check_depth' => 4096, // Bytes to check for MIME validation
        ], $config);
        
        $this->logger = $logger;
    }
    
    /**
     * Validate a file upload
     * 
     * @param array $file The uploaded file array ($_FILES element)
     * @return array Result with status and messages
     */
    public function validateUpload($file) {
        $result = [
            'valid' => true,
            'messages' => [],
            'security_level' => 'high'
        ];
        
        // Check if file exists
        if (empty($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $result['valid'] = false;
            $result['messages'][] = 'Invalid file upload.'; 
            return $result;
        }
        
        // Check file size
        if ($file['size'] > $this->config['max_file_size']) {
            $result['valid'] = false;
            $result['messages'][] = 'File exceeds maximum allowed size.'; 
        }
        
        // Get file extension
        $filename = isset($file['name']) ? $file['name'] : '';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Check against disallowed extensions
        if (in_array($extension, $this->config['disallowed_extensions'])) {
            $result['valid'] = false;
            $result['messages'][] = 'File type not allowed for security reasons.'; 
            $this->logSecurityEvent('Blocked upload of disallowed extension: ' . $extension, $file);
        }
        
        // Check against allowed extensions if specified
        if (!empty($this->config['allowed_extensions']) && !in_array($extension, $this->config['allowed_extensions'])) {
            $result['valid'] = false;
            $result['messages'][] = 'File extension not allowed.'; 
        }
        
        // Check MIME type
        $declaredMimeType = isset($file['type']) ? $file['type'] : '';
        
        // Check against allowed MIME types if specified
        if (!empty($this->config['allowed_mime_types']) && !in_array($declaredMimeType, $this->config['allowed_mime_types'])) {
            $result['valid'] = false;
            $result['messages'][] = 'File type not allowed.'; 
        }
        
        // Verify actual MIME type matches declared type
        if ($this->config['verify_mime_type'] && $result['valid']) {
            $actualMimeType = $this->detectMimeType($file['tmp_name']);
            
            // If we couldn't detect the MIME type, be cautious
            if ($actualMimeType === false) {
                $result['security_level'] = 'medium';
                $result['messages'][] = 'Could not verify file type.'; 
            } 
            // Check if the detected MIME type matches the declared one
            else if ($actualMimeType !== $declaredMimeType) {
                $result['valid'] = false;
                $result['messages'][] = 'File type mismatch detected.'; 
                $this->logSecurityEvent('MIME type mismatch: declared=' . $declaredMimeType . ', actual=' . $actualMimeType, $file);
            }
        }
        
        // Scan file content for suspicious patterns
        if ($this->config['scan_file_content'] && $result['valid']) {
            $scanResult = $this->scanFileContent($file['tmp_name']);
            if (!$scanResult['valid']) {
                $result['valid'] = false;
                $result['messages'] = array_merge($result['messages'], $scanResult['messages']);
                $this->logSecurityEvent('Suspicious content detected: ' . implode(', ', $scanResult['patterns']), $file);
            }
        }
        
        return $result;
    }
    
    /**
     * Detect the actual MIME type of a file
     * 
     * @param string $filePath Path to the file
     * @return string|false The detected MIME type or false on failure
     */
    public function detectMimeType($filePath) {
        // Try using finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mimeType;
        }
        
        // Try using mime_content_type
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }
        
        // If we can't detect it, return false
        return false;
    }
    
    /**
     * Scan file content for suspicious patterns
     * 
     * @param string $filePath Path to the file
     * @return array Result with status and messages
     */
    public function scanFileContent($filePath) {
        $result = [
            'valid' => true,
            'messages' => [],
            'patterns' => []
        ];
        
        // Read file content
        $content = file_get_contents($filePath, false, null, 0, $this->config['mime_type_check_depth']);
        if ($content === false) {
            $result['valid'] = false;
            $result['messages'][] = 'Could not read file content for security scan.';
            return $result;
        }
        
        // Check for suspicious patterns
        foreach ($this->config['suspicious_patterns'] as $pattern) {
            if (preg_match($pattern, $content)) {
                $result['valid'] = false;
                $result['patterns'][] = $pattern;
            }
        }
        
        if (!$result['valid']) {
            $result['messages'][] = 'File contains potentially malicious code.';
        }
        
        return $result;
    }
    
    /**
     * Generate a secure filename
     * 
     * @param string $originalFilename The original filename
     * @return string A secure filename
     */
    public function secureFilename($originalFilename) {
        // Remove any directory paths
        $filename = basename($originalFilename);
        
        // Sanitize the filename - remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9\.\-\_\s]/', '', $filename);
        
        // Replace spaces with underscores
        $filename = str_replace(' ', '_', $filename);
        
        // Ensure the filename isn't too long
        if (strlen($filename) > 64) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($filenameWithoutExt, 0, 60) . '.' . $extension;
        }
        
        return $filename;
    }
    
    /**
     * Log a security event
     * 
     * @param string $message The message to log
     * @param array $file The file information
     */
    private function logSecurityEvent($message, $file) {
        if ($this->logger && method_exists($this->logger, 'write')) {
            $logMessage = 'UPLOAD SECURITY: ' . $message . ' | ';
            $logMessage .= 'File: ' . (isset($file['name']) ? $file['name'] : 'unknown') . ' | ';
            $logMessage .= 'Size: ' . (isset($file['size']) ? $file['size'] : 'unknown') . ' | ';
            $logMessage .= 'Type: ' . (isset($file['type']) ? $file['type'] : 'unknown') . ' | ';
            $logMessage .= 'IP: ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
            
            $this->logger->write($logMessage);
        }
    }
}