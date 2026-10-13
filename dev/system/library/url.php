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
 * URL Generator Class
 * Handles URL generation and rewriting with improved security and flexibility
 */
class Url {
    /** @var string Base URL for the application */    
    private string $url;

    /** @var array<\Reamur\System\Engine\Controller> Array of URL rewrite controllers */
    private array $rewrite = [];

    /** @var bool Whether to use SSL for generated URLs */
    private bool $ssl;

    /** @var array Allowed route patterns for security */
    private array $allowedRoutePatterns = [
        '/^[a-zA-Z0-9_\/\-\.]+$/',  // Allow alphanumeric, underscore, slash, hyphen, dot
    ];

    /** 
     * Constructor
     * @param string $url Base URL
     * @param bool $ssl Whether to enforce SSL (optional)
     * @throws \InvalidArgumentException If URL is invalid
     */
    public function __construct(string $url, bool $ssl = false) {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid base URL provided');
        }
        
        $this->url = rtrim($url, '/') . '/';
        $this->ssl = $ssl;
    }

    /** 
     * Add a URL rewrite rule
     * @param \Reamur\System\Engine\Controller $rewrite Controller instance for URL rewriting
     * @return void
     * @throws \InvalidArgumentException If rewrite controller doesn't have rewrite method
     */
    public function addRewrite(\Reamur\System\Engine\Controller $rewrite): void {
        if (!method_exists($rewrite, 'rewrite')) {
            throw new \InvalidArgumentException('Rewrite controller must implement rewrite method');
        }
        
        $this->rewrite[] = $rewrite;
    }

    /**
     * Remove all rewrite rules
     * @return void
     */
    public function clearRewrites(): void {
        $this->rewrite = [];
    }

    /**
     * Get the number of active rewrite rules
     * @return int Number of rewrite rules
     */
    public function getRewriteCount(): int {
        return count($this->rewrite);
    }

    /**
     * Validate route against security patterns
     * @param string $route Route to validate
     * @return bool True if route is valid
     */
    private function validateRoute(string $route): bool {
        foreach ($this->allowedRoutePatterns as $pattern) {
            if (preg_match($pattern, $route)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sanitize and validate query parameters
     * @param string|array $args Query parameters
     * @return string Sanitized query string
     */
    private function sanitizeArgs(string|array $args): string {
        if (empty($args)) {
            return '';
        }

        if (is_array($args)) {
            // Filter out potentially dangerous parameters
            $filteredArgs = array_filter($args, function($key) {
                return !in_array(strtolower($key), ['script', 'iframe', 'object', 'embed']);
            }, ARRAY_FILTER_USE_KEY);
            
            return http_build_query($filteredArgs, '', '&', PHP_QUERY_RFC3986);
        } else {
            // Clean string arguments
            $args = trim($args, '&');
            // Remove potentially dangerous parameters
            $args = preg_replace('/(&|^)(script|iframe|object|embed)=[^&]*/i', '', $args);
            return $args;
        }
    }

    /**
     * Apply SSL enforcement if configured
     * @param string $url URL to potentially modify
     * @return string URL with SSL applied if needed
     */
    private function applySsl(string $url, bool $force = false): string {
        if (($force || $this->ssl) && strpos($url, 'http://') === 0) {
            return str_replace('http://', 'https://', $url);
        }
        return $url;
    }

    /**
     * Generate a URL
     * @param string $route Route path
     * @param string|array $args Query parameters (optional)
     * @param bool $js Whether URL is for JavaScript (disables HTML escaping)
     * @param bool $secure Force SSL for this specific URL (optional)
     * @return string Generated URL
     * @throws \InvalidArgumentException If route is invalid
     * @throws \RuntimeException If URL generation fails
     */
    public function link(string $route, string|array $args = '', bool $js = false, bool $secure = false): string {
        // Validate route
        if (empty($route)) {
            throw new \InvalidArgumentException('Route cannot be empty');
        }

        if (!$this->validateRoute($route)) {
            throw new \InvalidArgumentException('Route contains invalid characters: ' . $route);
        }

        try {
            // Build base URL
            $url = $this->url . 'index.php?route=' . rawurlencode($route);

            // Add query parameters if provided
            $sanitizedArgs = $this->sanitizeArgs($args);
            if (!empty($sanitizedArgs)) {
                $url .= '&' . $sanitizedArgs;
            }

            // Apply URL rewriting
            foreach ($this->rewrite as $rewrite) {
                try {
                    $rewrittenUrl = $rewrite->rewrite($url);
                    if (is_string($rewrittenUrl) && !empty($rewrittenUrl)) {
                        $url = $rewrittenUrl;
                    }
                } catch (\Exception $e) {
                    // Log error but continue with original URL
                    error_log("URL rewrite error: " . $e->getMessage());
                }
            }

            // Apply SSL if needed
            if ($secure || $this->ssl) {
                $url = $this->applySsl($url, $secure);
            }

            // Return URL with appropriate escaping
            return $js ? $url : htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to generate URL for route: ' . $route . '. Error: ' . $e->getMessage());
        }
    }

    /**
     * Generate a secure (HTTPS) URL
     * @param string $route Route path
     * @param string|array $args Query parameters (optional)
     * @param bool $js Whether URL is for JavaScript (optional)
     * @return string Generated secure URL
     */
    public function secureLink(string $route, string|array $args = '', bool $js = false): string {
        return $this->link($route, $args, $js, true);
    }

    /**
     * Get the base URL
     * @return string Base URL
     */
    public function getBaseUrl(): string {
        return $this->url;
    }

    /**
     * Check if SSL is enabled
     * @return bool True if SSL is enabled
     */
    public function isSslEnabled(): bool {
        return $this->ssl;
    }

    /**
     * Add a custom route validation pattern
     * @param string $pattern Regular expression pattern
     * @return void
     * @throws \InvalidArgumentException If pattern is invalid
     */
    public function addRoutePattern(string $pattern): void {
        if (@preg_match($pattern, '') === false) {
            throw new \InvalidArgumentException('Invalid regular expression pattern');
        }
        
        $this->allowedRoutePatterns[] = $pattern;
    }
}
