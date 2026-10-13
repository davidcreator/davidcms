<?php
namespace Reamur\System\Library\Template;

/**
 * Class Mustache
 * Provides Mustache template engine integration with custom namespace handling
 */
class Mustache {
    protected string $root;
    protected object $loader;
    protected string $directory;
    protected array $path = [];
    protected object $mustache;

    /**
     * Constructor
     * Initializes Mustache engine with filesystem loader
     */
    public function __construct() {
        $this->root = rtrim(DIR_REAMUR, '/');
        
        // Configure Mustache loader
        $this->loader = new \Mustache_Loader_FilesystemLoader($this->root);
        
        // Initialize Mustache engine
        $config = [
            'loader' => $this->loader,
            'partials_loader' => $this->loader,
            'cache' => DIR_CACHE . 'template/',
            'cache_file_mode' => 0666,
            'escape' => function($value) {
                return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
            },
            'charset' => 'UTF-8',
            'logger' => null,
            'strict_callables' => false,
            'pragmas' => [\Mustache_Engine::PRAGMA_FILTERS]
        ];
        
        $this->mustache = new \Mustache_Engine($config);
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
            $this->path[$namespace] = $directory;
        }
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
        if (empty($filename)) {
            throw new \InvalidArgumentException('Filename cannot be empty');
        }

        try {
            // If code is provided, render directly from string
            if (!empty($code)) {
                return $this->mustache->render($code, $data);
            }

            // Resolve template file path
            $file = $this->resolveTemplatePath($filename);
            
            // Check if file exists
            if (!file_exists($file)) {
                throw new \RuntimeException("Template file not found: {$file}");
            }

            // Load and render template
            $template = file_get_contents($file);
            return $this->mustache->render($template, $data);

        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to render template: ' . $e->getMessage());
        }
    }

    /**
     * Resolve template file path using namespace mapping
     * @param string $filename Template filename
     * @return string Full file path
     */
    protected function resolveTemplatePath(string $filename): string {
        $file = $this->directory . $filename . '.mustache';
        $namespace = '';

        $parts = explode('/', $filename);
        foreach ($parts as $part) {
            $namespace = $namespace ? $namespace . '/' . $part : $part;

            if (isset($this->path[$namespace])) {
                $file = $this->path[$namespace] . substr($filename, strlen($namespace)) . '.mustache';
                break;
            }
        }

        // Ensure absolute path
        if (!str_starts_with($file, $this->root)) {
            $file = $this->root . '/' . ltrim($file, '/');
        }

        return $file;
    }

    /**
     * Add custom helper/filter
     * @param string $name Helper name
     * @param callable $helper Helper function
     */
    public function addHelper(string $name, callable $helper): void {
        $this->mustache->addHelper($name, $helper);
    }

    /**
     * Load partial template
     * @param string $name Partial name
     * @return string Partial content
     */
    public function loadPartial(string $name): string {
        try {
            $file = $this->resolveTemplatePath($name);
            
            if (!file_exists($file)) {
                throw new \RuntimeException("Partial not found: {$name}");
            }

            return file_get_contents($file);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to load partial: ' . $e->getMessage());
        }
    }

    /**
     * Set template directory
     * @param string $directory Directory path
     */
    public function setDirectory(string $directory): void {
        $this->directory = rtrim($directory, '/') . '/';
    }

    /**
     * Get all registered paths
     * @return array Registered namespace paths
     */
    public function getPaths(): array {
        return $this->path;
    }

    /**
     * Clear template cache
     * @return bool Success status
     */
    public function clearCache(): bool {
        $cacheDir = DIR_CACHE . 'template/';
        
        if (!is_dir($cacheDir)) {
            return true;
        }

        $files = glob($cacheDir . '*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Check if template exists
     * @param string $filename Template filename
     * @return bool Template exists
     */
    public function exists(string $filename): bool {
        try {
            $file = $this->resolveTemplatePath($filename);
            return file_exists($file);
        } catch (\Exception $e) {
            return false;
        }
    }
}