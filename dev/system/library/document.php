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
 * Class Document
 * Manages document metadata, links, styles and scripts for HTML document head
 * Compatible with MVCL architecture based on OpenCart structure
 */
class Document {
    /** @var string Document title */
    private string $title = '';
    
    /** @var string Document meta description */
    private string $description = '';
    
    /** @var string Document meta keywords */
    private string $keywords = '';
    
    /** @var array Document meta tags */
    private array $meta = [];
    
    /** @var array Link elements (canonical, alternate, etc.) */
    private array $links = [];
    
    /** @var array CSS stylesheets */
    private array $styles = [];
    
    /** @var array JavaScript files */
    private array $scripts = [];
    
    /** @var string Document language */
    private string $language = 'en';
    
    /** @var string Document charset */
    private string $charset = 'UTF-8';

    /**
     * Set document title with proper sanitization
     * @param string $title Document title
     * @return self For method chaining
     */
    public function setTitle(string $title): self {
        $this->title = htmlspecialchars(trim($title), ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Get document title
     * @return string Current document title
     */
    public function getTitle(): string {
        return $this->title;
    }

    /**
     * Set meta description with length validation
     * @param string $description Meta description
     * @return self For method chaining
     * @throws \InvalidArgumentException If description is too long
     */
    public function setDescription(string $description): self {
        $description = trim($description);
        if (strlen($description) > 160) {
            throw new \InvalidArgumentException('Meta description should not exceed 160 characters');
        }
        $this->description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Get meta description
     * @return string Current meta description
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * Set meta keywords with validation
     * @param string $keywords Comma-separated keywords
     * @return self For method chaining
     */
    public function setKeywords(string $keywords): self {
        $keywords = trim($keywords);
        // Clean up multiple commas and spaces
        $keywords = preg_replace('/\s*,\s*/', ', ', $keywords);
        $keywords = preg_replace('/,+/', ',', $keywords);
        $this->keywords = htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8');
        return $this;
    }

    /**
     * Get meta keywords
     * @return string Current meta keywords
     */
    public function getKeywords(): string {
        return $this->keywords;
    }

    /**
     * Set document language
     * @param string $language Language code (e.g., 'pt-BR', 'en-US')
     * @return self For method chaining
     */
    public function setLanguage(string $language): self {
        if (preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $language)) {
            $this->language = $language;
        } else {
            throw new \InvalidArgumentException('Invalid language format. Use format like "en" or "pt-BR"');
        }
        return $this;
    }

    /**
     * Get document language
     * @return string Current document language
     */
    public function getLanguage(): string {
        return $this->language;
    }

    /**
     * Set document charset
     * @param string $charset Character encoding
     * @return self For method chaining
     */
    public function setCharset(string $charset): self {
        $this->charset = strtoupper(trim($charset));
        return $this;
    }

    /**
     * Get document charset
     * @return string Current charset
     */
    public function getCharset(): string {
        return $this->charset;
    }

    /**
     * Add custom meta tag
     * @param string $name Meta tag name
     * @param string $content Meta tag content
     * @param string $type Meta tag type ('name', 'property', 'http-equiv')
     * @return self For method chaining
     */
    public function addMeta(string $name, string $content, string $type = 'name'): self {
        if (!in_array($type, ['name', 'property', 'http-equiv'], true)) {
            throw new \InvalidArgumentException('Invalid meta type. Must be: name, property, or http-equiv');
        }
        
        $this->meta[$name] = [
            'type' => $type,
            'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8')
        ];
        return $this;
    }

    /**
     * Get all meta tags
     * @return array All custom meta tags
     */
    public function getMeta(): array {
        return $this->meta;
    }

    /**
     * Add link element (canonical, alternate, etc.)
     * @param string $href Link URL
     * @param string $rel Link relationship
     * @param array $attributes Additional link attributes
     * @return self For method chaining
     * @throws \InvalidArgumentException If href is empty or invalid
     */
    public function addLink(string $href, string $rel, array $attributes = []): self {
        if (empty(trim($href))) {
            throw new \InvalidArgumentException('Link href cannot be empty');
        }

        $sanitizedHref = filter_var(trim($href), FILTER_SANITIZE_URL);
        if ($sanitizedHref === false) {
            throw new \InvalidArgumentException('Invalid URL format');
        }

        $this->links[$href] = [
            'href' => $sanitizedHref,
            'rel' => htmlspecialchars($rel, ENT_QUOTES, 'UTF-8'),
            'attributes' => $this->sanitizeAttributes($attributes)
        ];
        return $this;
    }

    /**
     * Get all link elements
     * @return array All link elements
     */
    public function getLinks(): array {
        return $this->links;
    }

    /**
     * Add CSS stylesheet with enhanced validation
     * @param string $href URL to stylesheet
     * @param string $rel Link relationship (default: 'stylesheet')
     * @param string $media Media query (default: 'screen')
     * @param array $attributes Additional attributes
     * @return self For method chaining
     * @throws \InvalidArgumentException If href is empty or invalid
     */
    public function addStyle(string $href, string $rel = 'stylesheet', string $media = 'screen', array $attributes = []): self {
        if (empty(trim($href))) {
            throw new \InvalidArgumentException('Style href cannot be empty');
        }

        $sanitizedHref = filter_var(trim($href), FILTER_SANITIZE_URL);
        if ($sanitizedHref === false) {
            throw new \InvalidArgumentException('Invalid stylesheet URL format');
        }

        $this->styles[$href] = [
            'href' => $sanitizedHref,
            'rel' => htmlspecialchars($rel, ENT_QUOTES, 'UTF-8'),
            'media' => htmlspecialchars($media, ENT_QUOTES, 'UTF-8'),
            'attributes' => $this->sanitizeAttributes($attributes)
        ];
        return $this;
    }

    /**
     * Remove stylesheet
     * @param string $href URL of stylesheet to remove
     * @return self For method chaining
     */
    public function removeStyle(string $href): self {
        unset($this->styles[$href]);
        return $this;
    }

    /**
     * Get all stylesheets
     * @return array All stylesheets
     */
    public function getStyles(): array {
        return $this->styles;
    }

    /**
     * Add JavaScript file with enhanced validation
     * @param string $href URL to script
     * @param string $position Where to load script ('header' or 'footer')
     * @param array $attributes Additional script attributes (async, defer, etc.)
     * @return self For method chaining
     * @throws \InvalidArgumentException If href is empty, invalid, or position is invalid
     */
    public function addScript(string $href, string $position = 'header', array $attributes = []): self {
        if (empty(trim($href))) {
            throw new \InvalidArgumentException('Script href cannot be empty');
        }
        
        if (!in_array($position, ['header', 'footer'], true)) {
            throw new \InvalidArgumentException('Invalid script position. Must be: header or footer');
        }

        $sanitizedHref = filter_var(trim($href), FILTER_SANITIZE_URL);
        if ($sanitizedHref === false) {
            throw new \InvalidArgumentException('Invalid script URL format');
        }

        if (!isset($this->scripts[$position])) {
            $this->scripts[$position] = [];
        }

        $this->scripts[$position][$href] = [
            'href' => $sanitizedHref,
            'attributes' => $this->sanitizeAttributes($attributes)
        ];
        return $this;
    }

    /**
     * Remove JavaScript file
     * @param string $href URL of script to remove
     * @param string $position Position where script was added
     * @return self For method chaining
     */
    public function removeScript(string $href, string $position = 'header'): self {
        if (isset($this->scripts[$position][$href])) {
            unset($this->scripts[$position][$href]);
        }
        return $this;
    }

    /**
     * Get scripts by position
     * @param string $position Script position ('header' or 'footer')
     * @return array Scripts for the specified position
     */
    public function getScripts(string $position = 'header'): array {
        return $this->scripts[$position] ?? [];
    }

    /**
     * Get all scripts
     * @return array All scripts grouped by position
     */
    public function getAllScripts(): array {
        return $this->scripts;
    }

    /**
     * Clear all document data
     * @return self For method chaining
     */
    public function clear(): self {
        $this->title = '';
        $this->description = '';
        $this->keywords = '';
        $this->meta = [];
        $this->links = [];
        $this->styles = [];
        $this->scripts = [];
        return $this;
    }

    /**
     * Check if document has any content
     * @return bool True if document has content
     */
    public function hasContent(): bool {
        return !empty($this->title) || 
               !empty($this->description) || 
               !empty($this->keywords) ||
               !empty($this->meta) ||
               !empty($this->links) || 
               !empty($this->styles) || 
               !empty($this->scripts);
    }

    /**
     * Sanitize HTML attributes array
     * @param array $attributes Raw attributes
     * @return array Sanitized attributes
     */
    private function sanitizeAttributes(array $attributes): array {
        $sanitized = [];
        foreach ($attributes as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $sanitized[htmlspecialchars($key, ENT_QUOTES, 'UTF-8')] = 
                    htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            }
        }
        return $sanitized;
    }

    /**
     * Generate HTML meta tags
     * @return string HTML meta tags
     */
    public function renderMeta(): string {
        $html = '';
        
        // Charset
        $html .= '<meta charset="' . $this->charset . '">' . PHP_EOL;
        
        // Title
        if (!empty($this->title)) {
            $html .= '<title>' . $this->title . '</title>' . PHP_EOL;
        }
        
        // Description
        if (!empty($this->description)) {
            $html .= '<meta name="description" content="' . $this->description . '">' . PHP_EOL;
        }
        
        // Keywords
        if (!empty($this->keywords)) {
            $html .= '<meta name="keywords" content="' . $this->keywords . '">' . PHP_EOL;
        }
        
        // Custom meta tags
        foreach ($this->meta as $meta) {
            $html .= '<meta ' . $meta['type'] . '="' . $meta['name'] . '" content="' . $meta['content'] . '">' . PHP_EOL;
        }
        
        return $html;
    }

    /**
     * Generate HTML link tags
     * @return string HTML link tags
     */
    public function renderLinks(): string {
        $html = '';
        foreach ($this->links as $link) {
            $html .= '<link rel="' . $link['rel'] . '" href="' . $link['href'] . '"';
            foreach ($link['attributes'] as $attr => $value) {
                $html .= ' ' . $attr . '="' . $value . '"';
            }
            $html .= '>' . PHP_EOL;
        }
        return $html;
    }

    /**
     * Generate HTML style tags
     * @return string HTML style tags
     */
    public function renderStyles(): string {
        $html = '';
        foreach ($this->styles as $style) {
            $html .= '<link rel="' . $style['rel'] . '" href="' . $style['href'] . '" media="' . $style['media'] . '"';
            foreach ($style['attributes'] as $attr => $value) {
                $html .= ' ' . $attr . '="' . $value . '"';
            }
            $html .= '>' . PHP_EOL;
        }
        return $html;
    }

    /**
     * Generate HTML script tags for specific position
     * @param string $position Script position
     * @return string HTML script tags
     */
    public function renderScripts(string $position = 'header'): string {
        $html = '';
        $scripts = $this->getScripts($position);
        
        foreach ($scripts as $script) {
            $html .= '<script src="' . $script['href'] . '"';
            foreach ($script['attributes'] as $attr => $value) {
                $html .= ' ' . $attr . '="' . $value . '"';
            }
            $html .= '></script>' . PHP_EOL;
        }
        return $html;
    }
}