<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

/**
 * Pagination class
 * Generates accessible pagination links with improved security and performance
 */
class Pagination {
    public int $total = 0;
    public int $page = 1;
    public int $limit = 20;
    public int $num_links = 8;
    public string $url = '';
    public string $text_first = '« First';
    public string $text_last = 'Last »';
    public string $text_next = 'Next ›';
    public string $text_prev = '‹ Prev';
    
    // Configurações adicionais para melhor controle
    public bool $show_first_last = true;
    public bool $show_prev_next = true;
    public string $css_class = 'pagination';
    public string $active_class = 'active';
    
    // Cache para evitar recálculos
    private ?int $num_pages = null;
    private ?string $sanitized_url = null;

    /**
     * Render pagination HTML
     * @return string HTML markup for pagination
     * @throws InvalidArgumentException Se os parâmetros forem inválidos
     */
    public function render(): string {
        // Validação de entrada mais robusta
        if ($this->total <= 0 || $this->limit <= 0) {
            return '';
        }
        
        if ($this->total <= $this->limit) {
            return '';
        }

        $this->validateAndSanitizeInputs();
        
        $page = $this->page;
        $limit = $this->limit;
        $num_pages = $this->getNumPages();
        $num_links = max(2, $this->num_links);

        if ($page > $num_pages) {
            $page = $num_pages;
        }

        $output = '<nav aria-label="Pagination Navigation" role="navigation">';
        $output .= '<ul class="' . htmlspecialchars($this->css_class, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">';

        // First/Previous links
        if ($page > 1) {
            if ($this->show_first_last) {
                $output .= $this->createLink($this->text_first, 1, 'first', 'Go to first page');
            }
            if ($this->show_prev_next) {
                $output .= $this->createLink($this->text_prev, $page - 1, 'prev', 'Go to previous page');
            }
        }

        // Ellipsis before current range
        $range = $this->calculatePageRange($page, $num_pages, $num_links);
        if ($range['start'] > 1) {
            $output .= $this->createLink('1', 1, null, 'Go to page 1');
            if ($range['start'] > 2) {
                $output .= '<li class="ellipsis" aria-hidden="true"><span>…</span></li>';
            }
        }

        // Page number links
        for ($i = $range['start']; $i <= $range['end']; $i++) {
            if ($page == $i) {
                $output .= '<li class="' . htmlspecialchars($this->active_class, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" aria-current="page">';
                $output .= '<span aria-label="Current page, page ' . $i . '">' . $i . '</span></li>';
            } else {
                $output .= $this->createLink((string)$i, $i, null, 'Go to page ' . $i);
            }
        }

        // Ellipsis after current range
        if ($range['end'] < $num_pages) {
            if ($range['end'] < $num_pages - 1) {
                $output .= '<li class="ellipsis" aria-hidden="true"><span>…</span></li>';
            }
            $output .= $this->createLink((string)$num_pages, $num_pages, null, 'Go to page ' . $num_pages);
        }

        // Next/Last links
        if ($page < $num_pages) {
            if ($this->show_prev_next) {
                $output .= $this->createLink($this->text_next, $page + 1, 'next', 'Go to next page');
            }
            if ($this->show_first_last) {
                $output .= $this->createLink($this->text_last, $num_pages, 'last', 'Go to last page');
            }
        }

        $output .= '</ul></nav>';

        return $output;
    }

    /**
     * Create a pagination link with improved accessibility
     * @param string $text Link text
     * @param int $pageNum Page number
     * @param string|null $rel Link relationship (prev/next/first/last)
     * @param string|null $ariaLabel Accessibility label
     * @return string HTML link
     */
    private function createLink(string $text, int $pageNum, ?string $rel = null, ?string $ariaLabel = null): string {
        $href = $this->buildHref($pageNum);
        $relAttr = $rel ? ' rel="' . htmlspecialchars($rel, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"' : '';
        $ariaAttr = $ariaLabel ? ' aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"' : '';
        
        return '<li><a href="' . $href . '"' . $relAttr . $ariaAttr . '>' 
            . htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8') 
            . '</a></li>';
    }

    /**
     * Build href URL for a specific page
     * @param int $pageNum Page number
     * @return string Sanitized URL
     */
    private function buildHref(int $pageNum): string {
        $url = $this->getSanitizedUrl();
        
        if ($pageNum === 1) {
            // Remove page parameter for first page (cleaner URLs)
            $href = preg_replace('/[?&]page=\{page\}/', '', $url);
            $href = str_replace(['{page}', '%7Bpage%7D'], '', $href);
        } else {
            $href = str_replace(['{page}', '%7Bpage%7D'], (string)$pageNum, $url);
        }

        return htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Calculate the range of page numbers to display
     * @param int $currentPage Current page
     * @param int $totalPages Total number of pages
     * @param int $numLinks Number of links to show
     * @return array Start and end page numbers
     */
    private function calculatePageRange(int $currentPage, int $totalPages, int $numLinks): array {
        $start = max(1, $currentPage - floor($numLinks / 2));
        $end = min($totalPages, $start + $numLinks - 1);
        $start = max(1, $end - $numLinks + 1);

        // Ajustar para não mostrar página 1 duas vezes
        if ($start <= 2) {
            $start = 1;
        }
        
        // Ajustar para não mostrar última página duas vezes
        if ($end >= $totalPages - 1) {
            $end = $totalPages;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Get number of pages (cached)
     * @return int Number of pages
     */
    private function getNumPages(): int {
        if ($this->num_pages === null) {
            $this->num_pages = (int)ceil($this->total / $this->limit);
        }
        return $this->num_pages;
    }

    /**
     * Get sanitized URL (cached)
     * @return string Sanitized URL
     */
    private function getSanitizedUrl(): string {
        if ($this->sanitized_url === null) {
            $this->sanitized_url = htmlspecialchars(
                str_replace('%7Bpage%7D', '{page}', $this->url),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }
        return $this->sanitized_url;
    }

    /**
     * Validate and sanitize input parameters
     * @throws InvalidArgumentException Se os parâmetros forem inválidos
     */
    private function validateAndSanitizeInputs(): void {
        $this->page = max(1, (int)$this->page);
        $this->limit = max(1, (int)$this->limit);
        $this->total = max(0, (int)$this->total);
        $this->num_links = max(2, (int)$this->num_links);

        if (empty($this->url)) {
            throw new InvalidArgumentException('URL cannot be empty');
        }

        // Reset cache when inputs change
        $this->num_pages = null;
        $this->sanitized_url = null;
    }

    /**
     * Set pagination configuration
     * @param array $config Configuration array
     * @return self For method chaining
     */
    public function setConfig(array $config): self {
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        return $this;
    }

    /**
     * Get pagination information
     * @return array Pagination data
     */
    public function getInfo(): array {
        $num_pages = $this->getNumPages();
        $start_record = (($this->page - 1) * $this->limit) + 1;
        $end_record = min($this->page * $this->limit, $this->total);

        return [
            'total' => $this->total,
            'page' => $this->page,
            'limit' => $this->limit,
            'num_pages' => $num_pages,
            'start_record' => $start_record,
            'end_record' => $end_record,
            'has_prev' => $this->page > 1,
            'has_next' => $this->page < $num_pages
        ];
    }
}