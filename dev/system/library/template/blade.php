<?php
namespace Reamur\System\Library\Template;

/**
 * Class Blade
 * Provides Blade template engine integration with custom namespace handling
 */
class Blade {
    protected string $root;
    protected string $directory;
    protected array $path = [];
    protected string $cacheDirectory;

    /**
     * Constructor
     * Initializes Blade template engine with root directory
     */
    public function __construct() {
        $this->root = rtrim(DIR_REAMUR, '/');
        $this->cacheDirectory = DIR_CACHE . 'template/blade/';
        
        // Ensure cache directory exists
        if (!is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0755, true);
        }
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
            if ($code) {
                // Render from code string
                return $this->renderFromString($code, $data);
            } else {
                // Render from file
                $file = $this->resolveTemplatePath($filename);
                return $this->renderFromFile($file, $data);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to render template: ' . $e->getMessage());
        }
    }

    /**
     * Resolve template file path using namespace logic
     * @param string $filename Template filename
     * @return string Resolved file path
     */
    protected function resolveTemplatePath(string $filename): string {
        $file = $this->directory . $filename . '.blade.php';
        $namespace = '';

        $parts = explode('/', $filename);
        foreach ($parts as $part) {
            $namespace = $namespace ? $namespace . '/' . $part : $part;

            if (isset($this->path[$namespace])) {
                $file = $this->path[$namespace] . substr($filename, strlen($namespace) + 1) . '.blade.php';
                break;
            }
        }

        // Make path relative to root if it starts with root
        if (strpos($file, $this->root) === 0) {
            $file = substr($file, strlen($this->root) + 1);
        }

        $fullPath = $this->root . '/' . ltrim($file, '/');
        
        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Template file not found: {$fullPath}");
        }

        return $fullPath;
    }

    /**
     * Render template from file
     * @param string $filePath Full path to template file
     * @param array $data Template variables
     * @return string Rendered template
     */
    protected function renderFromFile(string $filePath, array $data = []): string {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Unable to read template file: {$filePath}");
        }

        return $this->compileAndRender($content, $data, $filePath);
    }

    /**
     * Render template from string
     * @param string $code Template code
     * @param array $data Template variables
     * @return string Rendered template
     */
    protected function renderFromString(string $code, array $data = []): string {
        return $this->compileAndRender($code, $data);
    }

    /**
     * Compile Blade template and render with data
     * @param string $content Template content
     * @param array $data Template variables
     * @param string $filePath Optional file path for caching
     * @return string Rendered template
     */
    protected function compileAndRender(string $content, array $data = [], string $filePath = ''): string {
        // Generate cache key
        $cacheKey = $filePath ? md5($filePath) : md5($content);
        $cachePath = $this->cacheDirectory . $cacheKey . '.php';
        
        // Check if we need to recompile
        $needsCompile = true;
        if (file_exists($cachePath)) {
            if ($filePath) {
                $needsCompile = filemtime($filePath) > filemtime($cachePath);
            } else {
                $needsCompile = false; // For string templates, compile once
            }
        }

        // Compile template if needed
        if ($needsCompile) {
            $compiled = $this->compile($content);
            file_put_contents($cachePath, $compiled);
        }

        // Extract variables for template scope
        extract($data, EXTR_SKIP);

        // Render compiled template
        ob_start();
        try {
            include $cachePath;
            return ob_get_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Compile Blade syntax to PHP
     * @param string $content Blade template content
     * @return string Compiled PHP code
     */
    protected function compile(string $content): string {
        $content = $this->compileEchos($content);
        $content = $this->compileStatements($content);
        $content = $this->compileComments($content);
        
        return "<?php\n" . $content;
    }

    /**
     * Compile Blade echo statements
     * @param string $content Template content
     * @return string Compiled content
     */
    protected function compileEchos(string $content): string {
        // Compile escaped echos {{ }}
        $content = preg_replace('/\{\{\s*(.+?)\s*\}\}/', '<?php echo htmlspecialchars($1, ENT_QUOTES, \'UTF-8\'); ?>', $content);
        
        // Compile unescaped echos {!! !!}
        $content = preg_replace('/\{!!\s*(.+?)\s*!!\}/', '<?php echo $1; ?>', $content);
        
        return $content;
    }

    /**
     * Compile Blade control structures
     * @param string $content Template content
     * @return string Compiled content
     */
    protected function compileStatements(string $content): string {
        // @if statements
        $content = preg_replace('/@if\s*\((.+?)\)/', '<?php if ($1): ?>', $content);
        $content = preg_replace('/@elseif\s*\((.+?)\)/', '<?php elseif ($1): ?>', $content);
        $content = preg_replace('/@else/', '<?php else: ?>', $content);
        $content = preg_replace('/@endif/', '<?php endif; ?>', $content);

        // @foreach statements
        $content = preg_replace('/@foreach\s*\((.+?)\)/', '<?php foreach ($1): ?>', $content);
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);

        // @for statements
        $content = preg_replace('/@for\s*\((.+?)\)/', '<?php for ($1): ?>', $content);
        $content = preg_replace('/@endfor/', '<?php endfor; ?>', $content);

        // @while statements
        $content = preg_replace('/@while\s*\((.+?)\)/', '<?php while ($1): ?>', $content);
        $content = preg_replace('/@endwhile/', '<?php endwhile; ?>', $content);

        // @unless statements
        $content = preg_replace('/@unless\s*\((.+?)\)/', '<?php if (!($1)): ?>', $content);
        $content = preg_replace('/@endunless/', '<?php endif; ?>', $content);

        // @isset and @empty
        $content = preg_replace('/@isset\s*\((.+?)\)/', '<?php if (isset($1)): ?>', $content);
        $content = preg_replace('/@endisset/', '<?php endif; ?>', $content);
        $content = preg_replace('/@empty\s*\((.+?)\)/', '<?php if (empty($1)): ?>', $content);
        $content = preg_replace('/@endempty/', '<?php endif; ?>', $content);

        // @php statements
        $content = preg_replace('/@php/', '<?php', $content);
        $content = preg_replace('/@endphp/', '?>', $content);

        return $content;
    }

    /**
     * Compile Blade comments
     * @param string $content Template content
     * @return string Compiled content
     */
    protected function compileComments(string $content): string {
        // Remove Blade comments {{-- --}}
        return preg_replace('/\{\{--.*?--\}\}/s', '', $content);
    }

    /**
     * Clear template cache
     * @return bool Success status
     */
    public function clearCache(): bool {
        try {
            $files = glob($this->cacheDirectory . '*.php');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if template exists
     * @param string $filename Template filename
     * @return bool
     */
    public function exists(string $filename): bool {
        try {
            $this->resolveTemplatePath($filename);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }
}