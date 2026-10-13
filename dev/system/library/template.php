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
 * Template Engine Adapter
 * Provides a unified interface for different template engines
 */
class Template {
    /** @var object|null Template engine adapter instance */
    private ?object $adaptor;
    
    /** @var array Supported template extensions and their corresponding engines */
    private array $extensionMap = [
        'twig' => 'Twig',
        'blade.php' => 'Blade',
        'phtml' => 'Phtml',
        'tpl' => 'Smarty',
        'php' => 'Native',
        'mustache' => 'Mustache',
        'hbs' => 'Handlebars',
        'latte' => 'Latte'
    ];
    
    /** @var array Global template variables */
    private array $globals = [];
    
    /** @var array Template paths for different namespaces */
    private array $paths = [];

    /**
     * Constructor
     * @param string $adaptor Template engine name (e.g., 'Twig', 'Blade', 'auto')
     * @throws \RuntimeException When template engine cannot be loaded
     */
    public function __construct(string $adaptor = 'auto') {
        if ($adaptor === 'auto') {
            // Auto-detection will be handled in render method
            $this->adaptor = null;
            return;
        }
        
        $this->loadAdaptor($adaptor);
    }

    /**
     * Load specific template engine adaptor
     * @param string $adaptor Template engine name
     * @throws \RuntimeException When template engine cannot be loaded
     */
    private function loadAdaptor(string $adaptor): void {
        $class = 'Reamur\System\Library\Template\\' . $adaptor;

        if (!class_exists($class)) {
            throw new \RuntimeException('Template engine "' . $adaptor . '" not found');
        }

        try {
            $this->adaptor = new $class();
            
            // Apply global variables and paths to the new adaptor
            $this->applyConfiguration();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to initialize template engine: ' . $e->getMessage());
        }
    }

    /**
     * Auto-detect template engine based on file extension
     * @param string $filename Template filename
     * @return string Template engine name
     * @throws \RuntimeException When extension is not supported
     */
    private function detectEngine(string $filename): string {
        // Check for compound extensions first (e.g., .blade.php)
        foreach ($this->extensionMap as $ext => $engine) {
            if (str_ends_with(strtolower($filename), '.' . $ext)) {
                return $engine;
            }
        }
        
        // Fallback to simple extension check
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!isset($this->extensionMap[$extension])) {
            throw new \RuntimeException('Unsupported template extension: .' . $extension);
        }
        
        return $this->extensionMap[$extension];
    }

    /**
     * Add template path namespace
     * @param string $namespace Namespace identifier
     * @param string $directory Path to template directory
     * @throws \RuntimeException When path cannot be added
     */
    public function addPath(string $namespace, string $directory = ''): void {
        $this->paths[$namespace] = $directory;
        
        if ($this->adaptor && method_exists($this->adaptor, 'addPath')) {
            $this->adaptor->addPath($namespace, $directory);
        }
    }

    /**
     * Set global template variable
     * @param string $name Variable name
     * @param mixed $value Variable value
     */
    public function setGlobal(string $name, $value): void {
        $this->globals[$name] = $value;
        
        if ($this->adaptor && method_exists($this->adaptor, 'setGlobal')) {
            $this->adaptor->setGlobal($name, $value);
        }
    }

    /**
     * Set multiple global variables
     * @param array $globals Array of global variables
     */
    public function setGlobals(array $globals): void {
        foreach ($globals as $name => $value) {
            $this->setGlobal($name, $value);
        }
    }

    /**
     * Add supported extension mapping
     * @param string $extension File extension (without dot)
     * @param string $engine Template engine name
     */
    public function addExtension(string $extension, string $engine): void {
        $this->extensionMap[strtolower($extension)] = $engine;
    }

    /**
     * Get supported extensions
     * @return array Array of supported extensions
     */
    public function getSupportedExtensions(): array {
        return array_keys($this->extensionMap);
    }

    /**
     * Apply configuration to current adaptor
     */
    private function applyConfiguration(): void {
        if (!$this->adaptor) {
            return;
        }
        
        // Apply paths
        foreach ($this->paths as $namespace => $directory) {
            if (method_exists($this->adaptor, 'addPath')) {
                $this->adaptor->addPath($namespace, $directory);
            }
        }
        
        // Apply globals
        foreach ($this->globals as $name => $value) {
            if (method_exists($this->adaptor, 'setGlobal')) {
                $this->adaptor->setGlobal($name, $value);
            }
        }
    }

    /**
     * Check if template file exists
     * @param string $filename Template file path
     * @return bool True if template exists
     */
    public function exists(string $filename): bool {
        if ($this->adaptor && method_exists($this->adaptor, 'exists')) {
            return $this->adaptor->exists($filename);
        }
        
        // Fallback to file_exists check
        return file_exists($filename);
    }

    /**
     * Render template
     * @param string $filename Template file path
     * @param array $data Template variables
     * @param string $code Optional template code string
     * @return string Rendered output
     * @throws \RuntimeException When template cannot be rendered
     */
    public function render(string $filename, array $data = [], string $code = ''): string {
        try {
            // Auto-detect and load adaptor if not already loaded
            if (!$this->adaptor) {
                $engine = $this->detectEngine($filename);
                $this->loadAdaptor($engine);
            }
            
            // Merge global variables with template data
            $mergedData = array_merge($this->globals, $data);
            
            return $this->adaptor->render($filename, $mergedData, $code);
        } catch (\Exception $e) {
            throw new \RuntimeException('Template rendering failed: ' . $e->getMessage());
        }
    }

    /**
     * Render template from string
     * @param string $template Template string
     * @param array $data Template variables
     * @param string $engine Optional specific engine to use
     * @return string Rendered output
     * @throws \RuntimeException When template cannot be rendered
     */
    public function renderString(string $template, array $data = [], string $engine = 'Native'): string {
        try {
            // Load specific engine if not current
            if (!$this->adaptor || get_class($this->adaptor) !== 'Reamur\System\Library\Template\\' . $engine) {
                $this->loadAdaptor($engine);
            }
            
            // Merge global variables with template data
            $mergedData = array_merge($this->globals, $data);
            
            if (method_exists($this->adaptor, 'renderString')) {
                return $this->adaptor->renderString($template, $mergedData);
            }
            
            // Fallback to render method with code parameter
            return $this->adaptor->render('', $mergedData, $template);
        } catch (\Exception $e) {
            throw new \RuntimeException('Template string rendering failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear template cache (if supported by engine)
     */
    public function clearCache(): void {
        if ($this->adaptor && method_exists($this->adaptor, 'clearCache')) {
            $this->adaptor->clearCache();
        }
    }

    /**
     * Get current template engine name
     * @return string|null Current engine name or null if auto-detection
     */
    public function getCurrentEngine(): ?string {
        if (!$this->adaptor) {
            return null;
        }
        
        $className = get_class($this->adaptor);
        return basename(str_replace('\\', '/', $className));
    }
}