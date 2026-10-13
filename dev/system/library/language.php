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
 * Class Language
 * Handles language loading, caching and text retrieval for internationalization
 */
class Language {
    /** @var string Current language code */
    protected string $code;

    /** @var string Base directory for language files */
    protected string $directory = '';

    /** @var array<string, string> Namespace to directory mapping */
    protected array $path = [];

    /** @var array<string, string> Language data storage */
    protected array $data = [];

    /** @var array<string, array> File cache storage */
    protected array $cache = [];

    /** @var string Default language code */
    protected string $default = 'en-gb';

    /** @var array<string> Valid language code patterns */
    protected const VALID_LANGUAGE_PATTERNS = [
        '/^[a-z]{2}$/',           // ISO 639-1: en, pt, es
        '/^[a-z]{2}-[a-z]{2}$/',  // ISO 639-1 + ISO 3166-1: en-gb, pt-br
        '/^[a-z]{2}_[A-Z]{2}$/',  // Locale format: en_US, pt_BR
    ];

    /**
     * Constructor
     * @param string $code Language code
     * @throws \InvalidArgumentException If language code is invalid
     */
    public function __construct(string $code) {
        $this->code = $this->validateLanguageCode($code);
    }

    /**
     * Validates and sanitizes the language code
     * @param string $code Language code to validate
     * @return string Validated language code
     * @throws \InvalidArgumentException If code format is completely invalid
     */
    protected function validateLanguageCode(string $code): string {
        $code = trim($code);
        
        if (empty($code)) {
            return $this->default;
        }

        // Convert underscores to hyphens for consistency
        $normalizedCode = str_replace('_', '-', strtolower($code));
        
        // Remove invalid characters
        $sanitizedCode = preg_replace('/[^a-z0-9\-]/', '', $normalizedCode);
        
        // Validate against known patterns
        foreach (self::VALID_LANGUAGE_PATTERNS as $pattern) {
            if (preg_match($pattern, $sanitizedCode)) {
                return $sanitizedCode;
            }
        }

        // If no pattern matches, try to extract valid parts
        if (preg_match('/^([a-z]{2})/', $sanitizedCode, $matches)) {
            return $matches[1];
        }

        return $this->default;
    }

    /**
     * Add a language path for namespace resolution
     * @param string $namespace Namespace identifier
     * @param string $directory Directory path (empty means set base directory)
     * @return void
     */
    public function addPath(string $namespace, string $directory = ''): void {
        if ($directory === '') {
            $this->directory = $this->normalizePath($namespace);
        } else {
            $normalizedPath = $this->normalizePath($directory);
            
            // Gracefully skip missing language paths instead of throwing
            if (!is_dir($normalizedPath)) {
                error_log(sprintf('Language path skipped: missing directory "%s"', $normalizedPath));
                return;
            }

            $this->path[$namespace] = $normalizedPath;
        }
    }

    /**
     * Normalize directory path
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    protected function normalizePath(string $path): string {
        return rtrim(str_replace(['\\', '//'], ['/', '/'], $path), '/') . '/';
    }

    /**
     * Get language text string with parameter substitution
     * @param string $key Language key
     * @param array $params Parameters for substitution
     * @return string Translated text or key if not found
     */
    public function get(string $key, array $params = []): string {
        $text = $this->data[$key] ?? $key;
        
        if (!empty($params) && $text !== $key) {
            $text = $this->substituteParameters($text, $params);
        }
        
        return $text;
    }

    /**
     * Substitute parameters in text
     * @param string $text Text with placeholders
     * @param array $params Parameters to substitute
     * @return string Text with substituted parameters
     */
    protected function substituteParameters(string $text, array $params): string {
        foreach ($params as $key => $value) {
            $placeholder = '{' . $key . '}';
            $text = str_replace($placeholder, (string)$value, $text);
        }
        return $text;
    }

    /**
     * Set language text string
     * @param string $key Language key
     * @param string $value Language value
     * @return void
     */
    public function set(string $key, string $value): void {
        if (empty($key)) {
            return;
        }
        $this->data[$key] = $value;
    }

    /**
     * Check if a language key exists
     * @param string $key Language key
     * @return bool True if key exists
     */
    public function has(string $key): bool {
        return isset($this->data[$key]);
    }

    /**
     * Get all language data, optionally filtered by prefix
     * @param string $prefix Optional prefix filter
     * @return array<string, string> Language data
     */
    public function all(string $prefix = ''): array {
        if ($prefix === '') {
            return $this->data;
        }

        $result = [];
        $prefixLength = strlen($prefix);
        $searchPrefix = $prefix . '_';
        
        foreach ($this->data as $key => $value) {
            if (strpos($key, $searchPrefix) === 0) {
                $newKey = substr($key, $prefixLength + 1);
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Clear all language data
     * @return void
     */
    public function clear(): void {
        $this->data = [];
    }

    /**
     * Clear cache for specific file or all cache
     * @param string $filename Optional filename to clear specific cache
     * @param string $code Optional language code
     * @return void
     */
    public function clearCache(string $filename = '', string $code = ''): void {
        if ($filename === '') {
            $this->cache = [];
        } else {
            $targetCode = $code ?: $this->code;
            unset($this->cache[$targetCode][$filename]);
        }
    }

    /**
     * Get current language code
     * @return string Current language code
     */
    public function getCode(): string {
        return $this->code;
    }

    /**
     * Set language code
     * @param string $code New language code
     * @return void
     */
    public function setCode(string $code): void {
        $this->code = $this->validateLanguageCode($code);
    }

    /**
     * Load a language file with improved error handling and fallback
     * @param string $filename Filename without extension
     * @param string $prefix Optional prefix for keys
     * @param string $code Optional language code override
     * @return array<string, string> Loaded language data
     * @throws \RuntimeException If file loading fails critically
     */
    public function load(string $filename, string $prefix = '', string $code = ''): array {
        $targetCode = $code ?: $this->code;
        $cacheKey = $targetCode . ':' . $filename;

        // Check cache first
        if (isset($this->cache[$targetCode][$filename])) {
            return $this->mergeAndReturnData($this->cache[$targetCode][$filename], $prefix);
        }

        $languageData = [];

        try {
            // Load default language as fallback
            if ($targetCode !== $this->default) {
                $languageData = $this->loadLanguageFile($this->default, $filename);
            }

            // Load target language file (overwrites default)
            $targetData = $this->loadLanguageFile($targetCode, $filename);
            $languageData = array_merge($languageData, $targetData);

            // Cache the result
            $this->cache[$targetCode][$filename] = $languageData;

        } catch (\Exception $e) {
            // Log error but don't break execution
            error_log("Language file loading error: " . $e->getMessage());
            
            // If target language fails, try default
            if ($targetCode !== $this->default && empty($languageData)) {
                try {
                    $languageData = $this->loadLanguageFile($this->default, $filename);
                    $this->cache[$targetCode][$filename] = $languageData;
                } catch (\Exception $fallbackError) {
                    error_log("Default language fallback failed: " . $fallbackError->getMessage());
                    $this->cache[$targetCode][$filename] = [];
                }
            }
        }

        return $this->mergeAndReturnData($languageData, $prefix);
    }

    /**
     * Load individual language file
     * @param string $code Language code
     * @param string $filename Filename
     * @return array<string, string> Language data
     * @throws \RuntimeException If file cannot be loaded
     */
    protected function loadLanguageFile(string $code, string $filename): array {
        $filePath = $this->resolveFilePath($code, $filename);
        
        if (!is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        // Capture any output and suppress warnings
        ob_start();
        $_ = [];
        
        try {
            $result = include $filePath;
            
            // Some language files return the array, others set $_
            if (is_array($result)) {
                $_ = $result;
            }
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \RuntimeException("Failed to load language file: {$filePath}. Error: " . $e->getMessage());
        } finally {
            ob_end_clean();
        }

        return is_array($_) ? $_ : [];
    }

    /**
     * Resolve the full file path for a language file
     * @param string $code Language code
     * @param string $filename Filename
     * @return string Full file path
     */
    protected function resolveFilePath(string $code, string $filename): string {
        $filePath = $this->directory . $code . '/' . $filename . '.php';

        // Check for namespace-specific paths
        $parts = explode('/', $filename);
        $namespace = '';
        
        foreach ($parts as $part) {
            $namespace = $namespace === '' ? $part : $namespace . '/' . $part;
            
            if (isset($this->path[$namespace])) {
                $remainingPath = substr($filename, strlen($namespace));
                $filePath = $this->path[$namespace] . $code . $remainingPath . '.php';
                break;
            }
        }

        return $filePath;
    }

    /**
     * Merge language data with prefix and return merged result
     * @param array $languageData Language data to process
     * @param string $prefix Optional prefix
     * @return array<string, string> Processed language data
     */
    protected function mergeAndReturnData(array $languageData, string $prefix = ''): array {
        if ($prefix) {
            $prefixed = [];
            foreach ($languageData as $key => $value) {
                $prefixed[$prefix . '_' . $key] = $value;
            }
            $languageData = $prefixed;
        }

        $this->data = array_merge($this->data, $languageData);
        return $this->data;
    }
}
