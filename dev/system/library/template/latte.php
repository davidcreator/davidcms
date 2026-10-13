<?php
namespace Reamur\System\Library\Template;

use Latte\Engine;
use Latte\Loaders\FileLoader;
use Latte\Loaders\StringLoader;

/**
 * Class Latte
 * Provides Latte template engine integration with custom namespace handling
 */
class Latte {
    protected string $root;
    protected Engine $engine;
    protected string $directory;
    protected array $path = [];
    protected array $config;

    /**
     * Constructor
     * Initializes Latte engine with default configuration
     */
    public function __construct() {
        $this->root = rtrim(DIR_REAMUR, '/');
        $this->engine = new Engine();
        
        // Default Latte configuration
        $this->config = [
            'tempDirectory' => DIR_CACHE . 'template/',
            'autoRefresh' => true,
            'strictMode' => false,
            'strictTypes' => false
        ];
        
        $this->configureEngine();
    }

    /**
     * Configure Latte engine with default settings
     */
    protected function configureEngine(): void {
        // Set temporary directory for compiled templates
        $this->engine->setTempDirectory($this->config['tempDirectory']);
        
        // Enable auto-refresh in development
        $this->engine->setAutoRefresh($this->config['autoRefresh']);
        
        // Add common filters and functions
        $this->addCustomFilters();
        $this->addCustomFunctions();
    }

    /**
     * Add custom filters to Latte engine
     */
    protected function addCustomFilters(): void {
        // Add a simple truncate filter
        $this->engine->addFilter('truncate', function (string $text, int $length = 100, string $suffix = '...'): string {
            return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . $suffix : $text;
        });

        // Add a currency filter
        $this->engine->addFilter('currency', function (float $amount, string $currency = 'BRL'): string {
            return number_format($amount, 2, ',', '.') . ' ' . $currency;
        });
    }

    /**
     * Add custom functions to Latte engine
     */
    protected function addCustomFunctions(): void {
        // Add a function to generate URLs
        $this->engine->addFunction('url', function (string $route, array $params = []): string {
            // This would typically integrate with your routing system
            $query = $params ? '?' . http_build_query($params) : '';
            return $route . $query;
        });

        // Add a function to check user permissions
        $this->engine->addFunction('hasPermission', function (string $permission): bool {
            // This would typically integrate with your authentication system
            return true; // Placeholder implementation
        });
    }

    /**
     * Add template path namespace
     * @param string $namespace Path namespace
     * @param string $directory Directory path (optional)
     * @throws \InvalidArgumentException If namespace is empty
     */
    public function addPath(string $namespace, string $directory = ''): void {
        if (empty($namespace)) {
            throw new \InvalidArgumentException('Namespace cannot be empty');
        }

        if (!$directory) {
            $this->directory = $namespace;
        } else {
            $this->path[$namespace] = rtrim($directory, '/');
        }
    }

    /**
     * Set Latte configuration option
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     */
    public function setConfig(string $key, mixed $value): void {
        $this->config[$key] = $value;
        
        // Apply specific configurations to engine
        switch ($key) {
            case 'tempDirectory':
                $this->engine->setTempDirectory($value);
                break;
            case 'autoRefresh':
                $this->engine->setAutoRefresh($value);
                break;
            case 'strictMode':
                $this->engine->setStrictMode($value);
                break;
        }
    }

    /**
     * Add custom filter to Latte engine
     * @param string $name Filter name
     * @param callable $callback Filter callback
     */
    public function addFilter(string $name, callable $callback): void {
        $this->engine->addFilter($name, $callback);
    }

    /**
     * Add custom function to Latte engine
     * @param string $name Function name
     * @param callable $callback Function callback
     */
    public function addFunction(string $name, callable $callback): void {
        $this->engine->addFunction($name, $callback);
    }

    /**
     * Resolve template file path with namespace support
     * @param string $filename Template filename
     * @return string Resolved file path
     */
    protected function resolveTemplatePath(string $filename): string {
        $file = $this->directory . $filename . '.latte';
        $namespace = '';

        $parts = explode('/', $filename);
        foreach ($parts as $part) {
            $namespace = $namespace ? $namespace . '/' . $part : $part;

            if (isset($this->path[$namespace])) {
                $remainingPath = substr($filename, strlen($namespace));
                $remainingPath = ltrim($remainingPath, '/');
                $file = $this->path[$namespace] . '/' . $remainingPath . '.latte';
                break;
            }
        }

        // Make path relative to root if it starts with root
        if (strpos($file, $this->root) === 0) {
            $file = substr($file, strlen($this->root) + 1);
        }

        return $file;
    }

    /**
     * Render template
     * @param string $filename Template filename
     * @param array $data Template variables
     * @param string $code Optional template code to render instead of file
     * @return string Rendered template
     * @throws \RuntimeException If template cannot be loaded or rendered
     */
    public function render(string $filename, array $data = [], string $code = ''): string {
        if (empty($filename) && empty($code)) {
            throw new \InvalidArgumentException('Filename or code must be provided');
        }

        try {
            if (!empty($code)) {
                // Render from string
                $this->engine->setLoader(new StringLoader());
                return $this->engine->renderToString($code, $data);
            } else {
                // Render from file
                $file = $this->resolveTemplatePath($filename);
                $fullPath = $this->root . '/' . $file;
                
                // Check if template file exists
                if (!file_exists($fullPath)) {
                    throw new \RuntimeException("Template file not found: {$fullPath}");
                }
                
                $this->engine->setLoader(new FileLoader($this->root));
                return $this->engine->renderToString($file, $data);
            }
        } catch (\Latte\CompileException $e) {
            throw new \RuntimeException('Failed to compile template: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to render template: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Render template to file
     * @param string $filename Template filename
     * @param string $outputFile Output file path
     * @param array $data Template variables
     * @param string $code Optional template code to render instead of file
     * @throws \RuntimeException If template cannot be rendered to file
     */
    public function renderToFile(string $filename, string $outputFile, array $data = [], string $code = ''): void {
        try {
            $content = $this->render($filename, $data, $code);
            
            $outputDir = dirname($outputFile);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            
            if (file_put_contents($outputFile, $content) === false) {
                throw new \RuntimeException("Failed to write rendered template to: {$outputFile}");
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to render template to file: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if template exists
     * @param string $filename Template filename
     * @return bool True if template exists
     */
    public function exists(string $filename): bool {
        $file = $this->resolveTemplatePath($filename);
        $fullPath = $this->root . '/' . $file;
        return file_exists($fullPath);
    }

    /**
     * Clear template cache
     * @param string|null $filename Optional specific template to clear
     */
    public function clearCache(?string $filename = null): void {
        $cacheDir = $this->config['tempDirectory'];
        
        if ($filename) {
            // Clear specific template cache
            $file = $this->resolveTemplatePath($filename);
            $cacheFile = $cacheDir . '/' . str_replace(['/', '.latte'], ['--', '.php'], $file);
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        } else {
            // Clear all template cache
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*.php');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }
    }

    /**
     * Get Latte engine instance for advanced usage
     * @return Engine Latte engine instance
     */
    public function getEngine(): Engine {
        return $this->engine;
    }
}