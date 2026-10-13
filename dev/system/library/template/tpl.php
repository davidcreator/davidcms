<?php
namespace Reamur\System\Library\Template;

/**
 * Class Tpl
 * Provides TPL template engine with custom namespace handling and variable substitution
 */
class Tpl {
    protected string $root;
    protected string $directory;
    protected array $path = [];
    protected array $data = [];

    /**
     * Constructor
     * Initializes TPL template engine with root directory
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
    public function set(string $key, $value): void {
        $this->data[$key] = $value;
    }

    /**
     * Set multiple template variables
     * @param array $data Array of variables
     */
    public function setData(array $data): void {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Get template variable
     * @param string $key Variable name
     * @return mixed Variable value or null if not set
     */
    public function get(string $key) {
        return $this->data[$key] ?? null;
    }

    /**
     * Clear all template variables
     */
    public function clear(): void {
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

        // Merge provided data with existing data
        $templateData = array_merge($this->data, $data);

        if (!empty($code)) {
            return $this->parseTemplate($code, $templateData);
        }

        $file = $this->resolveTemplatePath($filename);
        
        if (!file_exists($file)) {
            throw new \RuntimeException("Template file not found: {$file}");
        }

        if (!is_readable($file)) {
            throw new \RuntimeException("Template file is not readable: {$file}");
        }

        try {
            $content = file_get_contents($file);
            if ($content === false) {
                throw new \RuntimeException("Failed to read template file: {$file}");
            }

            return $this->parseTemplate($content, $templateData);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to render template: ' . $e->getMessage());
        }
    }

    /**
     * Resolve template file path with namespace support
     * @param string $filename Template filename
     * @return string Full file path
     */
    protected function resolveTemplatePath(string $filename): string {
        $file = $this->directory . $filename . '.tpl';
        $namespace = '';

        $parts = explode('/', $filename);
        foreach ($parts as $part) {
            $namespace = $namespace ? $namespace . '/' . $part : $part;

            if (isset($this->path[$namespace])) {
                $file = $this->path[$namespace] . substr($filename, strlen($namespace) + 1) . '.tpl';
            }
        }

        // Remove root path if present
        if (strpos($file, $this->root) === 0) {
            return $file;
        }

        return $this->root . '/' . ltrim($file, '/');
    }

    /**
     * Parse template content and replace variables
     * @param string $content Template content
     * @param array $data Template variables
     * @return string Parsed content
     */
    protected function parseTemplate(string $content, array $data): string {
        // Extract variables to current scope
        extract($data, EXTR_OVERWRITE);

        // Start output buffering
        ob_start();

        try {
            // Parse simple variable substitution {{ $variable }}
            $content = preg_replace_callback('/\{\{\s*\$(\w+)\s*\}\}/', function($matches) use ($data) {
                $varName = $matches[1];
                return $data[$varName] ?? '';
            }, $content);

            // Parse variable with default value {{ $variable|default }}
            $content = preg_replace_callback('/\{\{\s*\$(\w+)\|([^}]+)\s*\}\}/', function($matches) use ($data) {
                $varName = $matches[1];
                $default = trim($matches[2]);
                return $data[$varName] ?? $default;
            }, $content);

            // Parse simple loops {% foreach $items as $item %}...{% endforeach %}
            $content = preg_replace_callback('/\{%\s*foreach\s+\$(\w+)\s+as\s+\$(\w+)\s*%\}(.*?)\{%\s*endforeach\s*%\}/s', 
                function($matches) use ($data) {
                    $arrayVar = $matches[1];
                    $itemVar = $matches[2];
                    $loopContent = $matches[3];
                    
                    if (!isset($data[$arrayVar]) || !is_array($data[$arrayVar])) {
                        return '';
                    }
                    
                    $result = '';
                    foreach ($data[$arrayVar] as $item) {
                        $loopData = array_merge($data, [$itemVar => $item]);
                        $result .= $this->parseTemplate($loopContent, $loopData);
                    }
                    
                    return $result;
                }, $content);

            // Parse conditional statements {% if $condition %}...{% endif %}
            $content = preg_replace_callback('/\{%\s*if\s+\$(\w+)\s*%\}(.*?)\{%\s*endif\s*%\}/s', 
                function($matches) use ($data) {
                    $varName = $matches[1];
                    $ifContent = $matches[2];
                    
                    if (!empty($data[$varName])) {
                        return $this->parseTemplate($ifContent, $data);
                    }
                    
                    return '';
                }, $content);

            // Parse include statements {% include 'template_name' %}
            $content = preg_replace_callback('/\{%\s*include\s+[\'"]([^\'"]+)[\'"]\s*%\}/', 
                function($matches) use ($data) {
                    $templateName = $matches[1];
                    try {
                        return $this->render($templateName, $data);
                    } catch (\Exception $e) {
                        return '<!-- Include error: ' . $e->getMessage() . ' -->';
                    }
                }, $content);

            // Evaluate PHP code (be careful with this in production)
            eval('?>' . $content);
            
            return ob_get_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Check if template file exists
     * @param string $filename Template filename
     * @return bool True if template exists
     */
    public function exists(string $filename): bool {
        try {
            $file = $this->resolveTemplatePath($filename);
            return file_exists($file);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get template modification time
     * @param string $filename Template filename
     * @return int|false Modification timestamp or false on failure
     */
    public function getModificationTime(string $filename) {
        try {
            $file = $this->resolveTemplatePath($filename);
            return filemtime($file);
        } catch (\Exception $e) {
            return false;
        }
    }
}