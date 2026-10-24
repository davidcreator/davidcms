<?php declare(strict_types=1);
/**
 * ReamurCMS — F9-19 hardened allowlisted public renderer for Blog block documents.
 * Produces markup only after strict schema validation. Never accepts raw HTML.
 */
namespace Reamur\System\Library\Blog;

final class BlockRenderer {
    private const MAX_JSON_BYTES = 4194304;

    /**
     * @return array{valid:bool,html:string,errors:array}
     */
    public function renderJson(string $json, ?int $storedFormatVersion = null, string $assetBase = ''): array {
        if ($json === '' || strlen($json) > self::MAX_JSON_BYTES) return ['valid' => false, 'html' => '', 'errors' => ['document_size_invalid']];
        $document = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($document)) return ['valid' => false, 'html' => '', 'errors' => ['invalid_json']];
        if ($storedFormatVersion !== null && $storedFormatVersion !== ($document['format_version'] ?? null)) return ['valid' => false, 'html' => '', 'errors' => ['stored_version_mismatch']];

        $validator = new BlockDocument();
        $validation = $validator->validateDocument($document);
        if (!$validation['valid']) return ['valid' => false, 'html' => '', 'errors' => $validation['errors']];

        return ['valid' => true, 'html' => $this->renderNodes($document['blocks'], $validator, $assetBase), 'errors' => []];
    }

    private function renderNodes(array $nodes, BlockDocument $validator, string $assetBase): string {
        $html = '';
        foreach ($nodes as $node) $html .= $this->renderNode($node, $validator, $assetBase);
        return $html;
    }

    private function renderNode(array $node, BlockDocument $validator, string $assetBase): string {
        $type = $node['type'];
        $content = $this->escapeWithBreaks($node['content']);
        $children = $node['children'];
        $attributes = $node['attributes'];

        switch ($type) {
            case 'core/paragraph':
                $align = (string)($attributes['align'] ?? 'left');
                $class = $align === 'left' ? 'rms-block rms-block-paragraph' : 'rms-block rms-block-paragraph rms-block-align-' . $align;
                return '<p class="' . $class . '">' . $content . '</p>';

            case 'core/heading':
                $level = (int)$attributes['level'];
                return '<h' . $level . ' class="rms-block rms-block-heading">' . $content . '</h' . $level . '>';

            case 'core/list':
                $tag = $attributes['ordered'] ? 'ol' : 'ul';
                $html = '<' . $tag . ' class="rms-block rms-block-list">';
                foreach ($children as $child) $html .= '<li>' . $this->escapeWithBreaks($child['content']) . '</li>';
                return $html . '</' . $tag . '>';

            case 'core/list-item':
                // Internal list nodes are emitted only by core/list.
                return '';

            case 'core/quote':
                return '<blockquote class="rms-block rms-block-quote"><p>' . $content . '</p></blockquote>';

            case 'core/image':
                $src = $this->escape($this->resolveLocalImageUrl((string)$attributes['src'], $assetBase));
                $alt = $this->escape((string)$attributes['alt']);
                $title = isset($attributes['title']) && $attributes['title'] !== '' ? ' title="' . $this->escape((string)$attributes['title']) . '"' : '';
                return '<figure class="rms-block rms-block-image"><img src="' . $src . '" alt="' . $alt . '"' . $title . ' loading="lazy" decoding="async"></figure>';

            case 'core/video_embed':
                $target = $validator->embedTarget((string)$attributes['url']);
                if ($target === null) return '';
                $src = $target['provider'] === 'youtube'
                    ? 'https://www.youtube-nocookie.com/embed/' . $target['id']
                    : 'https://player.vimeo.com/video/' . $target['id'];
                $title = $target['provider'] === 'youtube' ? 'YouTube video' : 'Vimeo video';
                return '<figure class="rms-block rms-block-video"><div class="rms-block-video__frame"><iframe src="' . $this->escape($src) . '" title="' . $this->escape($title) . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-same-origin allow-presentation" allow="fullscreen; picture-in-picture" allowfullscreen></iframe></div></figure>';

            case 'core/button':
                $url = (string)$attributes['url'];
                return '<p class="rms-block rms-block-button-wrap"><a class="rms-block-button" href="' . $this->escape($url) . '" rel="nofollow noopener noreferrer">' . $content . '</a></p>';

            case 'core/separator':
                return '<hr class="rms-block rms-block-separator">';

            case 'core/group':
                return '<div class="rms-block-group">' . $this->renderNodes($children, $validator, $assetBase) . '</div>';

            case 'core/columns':
                $count = (int)$attributes['count'];
                $html = '<div class="rms-block-columns rms-block-columns--' . $count . '">';
                foreach ($children as $column) $html .= '<div class="rms-block-column">' . $this->renderNodes($column['children'], $validator, $assetBase) . '</div>';
                return $html . '</div>';

            case 'core/column':
                // Internal layout nodes are emitted only by core/columns.
                return '';
        }
        return '';
    }

    /** Resolve an allowlisted local path against trusted catalog configuration, never document input. */
    private function resolveLocalImageUrl(string $src, string $assetBase): string {
        $path = '/' . ltrim($src, '/');
        if ($assetBase === '') return $path;
        $parts = parse_url($assetBase);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return $path;
        }
        return rtrim($assetBase, '/') . $path;
    }

    private function escapeWithBreaks(string $value): string {
        return str_replace(["\r\n", "\r", "\n"], '<br>', $this->escape($value));
    }

    private function escape(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
