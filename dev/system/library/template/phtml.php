<?php
namespace Reamur\System\Library\Template;

/**
 * Class Phtml
 * Provides PHTML template engine with custom namespace handling
 */
class Phtml {
    protected string $root;
    protected string $directory;
    protected array $path = [];
    protected array $data = [];

    /**
     * Constructor
     * Initializes PHTML template engine with root directory
     */
    public function __construct() {
        $this->root = rtrim(DIR_REAMUR, '/');
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
     * Set template variable
     * @param string $key Variable name
     * @param mixed $value Variable value
     */
    public function assign(string $key, $value): void {
        $this->data[$key] = $value;
    }

    /**
     * Set multiple template variables
     * @param array $data Array of variables
     */
    public function assignMultiple(array $data): void {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Get template variable
     * @param string $key Variable name
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Variable value
     */
    public function get(string $key, $default = null) {
        return $this->data[$key] ?? $default;
    }

    /**
     * Clear all template variables
     */
    public function clearData(): void {
        $this->data = [];
    }

    /**
     * Render template
     * @param string $filename Template filename
     * @param array $data Template variables (optional)
     * @param string $code Optional template code to render instead of file
     * @return string Rendered template
     * @throws \RuntimeException If template cannot be loaded or rendered
     */
    public function render(string $filename, array $data = [], string $code = ''): string {
        if (empty($filename)) {
            throw new \InvalidArgumentException('Filename cannot be empty');
        }

        // Merge passed data with assigned data
        $templateData = array_merge($this->data, $data);

        // If code is provided, render it directly
        if ($code) {
            return $this->renderCode($code, $templateData);
        }

        // Resolve template file path
        $file = $this->resolveTemplatePath($filename);

        if (!file_exists($file)) {
            throw new \RuntimeException("Template file not found: {$file}");
        }

        if (!is_readable($file)) {
            throw new \RuntimeException("Template file is not readable: {$file}");
        }

        return $this->renderFile($file, $templateData);
    }

    /**
     * Resolve template file path based on namespace
     * @param string $filename Template filename
     * @return string Full file path
     */
    protected function resolveTemplatePath(string $filename): string {
        $file = $this->directory . $filename . '.phtml';
        $namespace = '';

        $parts = explode('/', $filename);
        foreach ($parts as $part) {
            $namespace = $namespace ? $namespace . '/' . $part : $part;

            if (isset($this->path[$namespace])) {
                $file = $this->path[$namespace] . substr($filename, strlen($namespace) + 1) . '.phtml';
                break;
            }
        }

        // If path starts with root, use it as is, otherwise prepend root
        if (strpos($file, $this->root) === 0) {
            return $file;
        }

        return $this->root . '/' . ltrim($file, '/');
    }

    /**
     * Render template file
     * @param string $file Template file path
     * @param array $data Template variables
     * @return string Rendered content
     * @throws \RuntimeException If rendering fails
     */
    protected function renderFile(string $file, array $data): string {
        try {
            // Extract variables to current scope
            extract($data, EXTR_OVERWRITE);

            // Start output buffering
            ob_start();

            // Include the template file
            include $file;

            // Get the rendered content
            $content = ob_get_clean();

            if ($content === false) {
                throw new \RuntimeException('Failed to capture template output');
            }

            return $content;
        } catch (\Exception $e) {
            // Clean output buffer if something went wrong
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            throw new \RuntimeException('Failed to render template: ' . $e->getMessage());
        }
    }

    /**
     * Render template code directly
     * @param string $code Template code
     * @param array $data Template variables
     * @return string Rendered content
     * @throws \RuntimeException If rendering fails
     */
    protected function renderCode(string $code, array $data): string {
        try {
            // Extract variables to current scope
            extract($data, EXTR_OVERWRITE);

            // Start output buffering
            ob_start();

            // Evaluate the template code
            eval('?>' . $code);

            // Get the rendered content
            $content = ob_get_clean();

            if ($content === false) {
                throw new \RuntimeException('Failed to capture template output');
            }

            return $content;
        } catch (\Exception $e) {
            // Clean output buffer if something went wrong
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            throw new \RuntimeException('Failed to render template code: ' . $e->getMessage());
        }
    }

    /**
     * Check if template file exists
     * @param string $filename Template filename
     * @return bool True if template exists
     */
    public function templateExists(string $filename): bool {
        try {
            $file = $this->resolveTemplatePath($filename);
            return file_exists($file) && is_readable($file);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Include a partial template
     * @param string $filename Partial template filename
     * @param array $data Additional data for the partial
     * @return string Rendered partial content
     */
    public function partial(string $filename, array $data = []): string {
        return $this->render($filename, $data);
    }

    /**
     * Escape HTML content for safe output
     * @param string $content Content to escape
     * @param int $flags HTML entities flags
     * @param string $encoding Character encoding
     * @return string Escaped content
     */
    public function escape(string $content, int $flags = ENT_QUOTES, string $encoding = 'UTF-8'): string {
        return htmlspecialchars($content, $flags, $encoding);
    }

    /**
     * Get all assigned template data
     * @return array All template variables
     */
    public function getData(): array {
        return $this->data;
    }
}