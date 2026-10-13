<?php
namespace Reamur\System\Library\Template;

/**
 * Class Handlebars
 * Provides Handlebars template engine integration with custom namespace handling
 */
class Handlebars {
    protected string $root;
    protected string $directory;
    protected array $path = [];
    protected array $helpers = [];
    protected array $partials = [];

    /**
     * Constructor
     * Initializes Handlebars with root directory
     */
    public function __construct() {
        $this->root = rtrim(DIR_REAMUR, '/');
        $this->registerDefaultHelpers();
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
     * Register a helper function
     * @param string $name Helper name
     * @param callable $callback Helper function
     */
    public function registerHelper(string $name, callable $callback): void {
        $this->helpers[$name] = $callback;
    }

    /**
     * Register a partial template
     * @param string $name Partial name
     * @param string $template Partial template content
     */
    public function registerPartial(string $name, string $template): void {
        $this->partials[$name] = $template;
    }

    /**
     * Register default helpers
     */
    protected function registerDefaultHelpers(): void {
        // if helper
        $this->registerHelper('if', function($condition, $options) {
            if ($condition) {
                return $options['fn'] ?? '';
            }
            return $options['inverse'] ?? '';
        });

        // unless helper
        $this->registerHelper('unless', function($condition, $options) {
            if (!$condition) {
                return $options['fn'] ?? '';
            }
            return $options['inverse'] ?? '';
        });

        // each helper
        $this->registerHelper('each', function($items, $options) {
            if (!is_array($items) && !is_object($items)) {
                return '';
            }

            $result = '';
            $index = 0;
            foreach ($items as $key => $item) {
                $context = [
                    '@index' => $index,
                    '@key' => $key,
                    '@first' => $index === 0,
                    '@last' => $index === count((array)$items) - 1
                ];
                
                if (is_array($item)) {
                    $item = array_merge($item, $context);
                } else {
                    $item = (object)array_merge((array)$item, $context);
                }
                
                $result .= $this->renderBlock($options['fn'] ?? '', $item);
                $index++;
            }
            return $result;
        });

        // with helper
        $this->registerHelper('with', function($context, $options) {
            return $this->renderBlock($options['fn'] ?? '', $context);
        });
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
                $template = $code;
            } else {
                $file = $this->resolveTemplatePath($filename);
                $template = $this->loadTemplate($file);
            }

            return $this->compile($template, $data);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to render template: ' . $e->getMessage());
        }
    }

    /**
     * Resolve template file path
     * @param string $filename Template filename
     * @return string Resolved file path
     */
    protected function resolveTemplatePath(string $filename): string {
        $file = $this->directory . $filename . '.hbs';
        $namespace = '';

        $parts = explode('/', $filename);
        foreach ($parts as $part) {
            $namespace = $namespace ? $namespace . '/' . $part : $part;

            if (isset($this->path[$namespace])) {
                $file = $this->path[$namespace] . substr($filename, strlen($namespace) + 1) . '.hbs';
                break;
            }
        }

        return $this->root . '/' . ltrim($file, '/');
    }

    /**
     * Load template from file
     * @param string $file Template file path
     * @return string Template content
     * @throws \RuntimeException If file cannot be read
     */
    protected function loadTemplate(string $file): string {
        if (!file_exists($file)) {
            throw new \RuntimeException("Template file not found: {$file}");
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new \RuntimeException("Cannot read template file: {$file}");
        }

        return $content;
    }

    /**
     * Compile Handlebars template
     * @param string $template Template content
     * @param array $data Template variables
     * @return string Compiled template
     */
    protected function compile(string $template, array $data): string {
        // Load partials
        $template = $this->loadPartials($template);
        
        // Process helpers
        $template = $this->processHelpers($template, $data);
        
        // Process variables
        $template = $this->processVariables($template, $data);

        return $template;
    }

    /**
     * Load and process partials
     * @param string $template Template content
     * @return string Template with partials loaded
     */
    protected function loadPartials(string $template): string {
        return preg_replace_callback('/\{\{\s*>\s*([a-zA-Z0-9_\-\/]+)\s*\}\}/', function($matches) {
            $partialName = $matches[1];
            
            if (isset($this->partials[$partialName])) {
                return $this->partials[$partialName];
            }

            // Try to load partial from file
            try {
                $partialFile = $this->resolveTemplatePath('partials/' . $partialName);
                if (file_exists($partialFile)) {
                    return file_get_contents($partialFile);
                }
            } catch (\Exception $e) {
                // Ignore partial loading errors
            }

            return '';
        }, $template);
    }

    /**
     * Process helpers
     * @param string $template Template content
     * @param array $data Template data
     * @return string Processed template
     */
    protected function processHelpers(string $template, array $data): string {
        // Process block helpers like {{#if}}, {{#each}}, etc.
        $template = preg_replace_callback('/\{\{\s*#(\w+)\s*([^}]*)\}\}(.*?)\{\{\s*\/\1\s*\}\}/s', function($matches) use ($data) {
            $helper = $matches[1];
            $params = trim($matches[2]);
            $block = $matches[3];

            if (isset($this->helpers[$helper])) {
                $value = $this->resolveValue($params, $data);
                $options = [
                    'fn' => $block,
                    'inverse' => '' // TODO: Handle {{else}} blocks
                ];
                return $this->helpers[$helper]($value, $options);
            }

            return $matches[0];
        }, $template);

        return $template;
    }

    /**
     * Process variables
     * @param string $template Template content
     * @param array $data Template data
     * @return string Processed template
     */
    protected function processVariables(string $template, array $data): string {
        return preg_replace_callback('/\{\{\s*([^#\/>\s][^}]*)\s*\}\}/', function($matches) use ($data) {
            $variable = trim($matches[1]);
            $value = $this->resolveValue($variable, $data);
            return $this->escapeValue($value);
        }, $template);
    }

    /**
     * Resolve variable value from data
     * @param string $variable Variable name/path
     * @param mixed $data Data context
     * @return mixed Variable value
     */
    protected function resolveValue(string $variable, $data) {
        if ($variable === '.') {
            return $data;
        }

        $parts = explode('.', $variable);
        $value = $data;

        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } elseif (is_object($value) && property_exists($value, $part)) {
                $value = $value->$part;
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Render block with context
     * @param string $block Block content
     * @param mixed $context Context data
     * @return string Rendered block
     */
    protected function renderBlock(string $block, $context): string {
        return $this->processVariables($block, $context);
    }

    /**
     * Escape value for output
     * @param mixed $value Value to escape
     * @return string Escaped value
     */
    protected function escapeValue($value): string {
        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}