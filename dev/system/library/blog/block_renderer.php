<?php declare(strict_types=1);
/**
 * ReamurCMS — F9-19 hardened allowlisted public renderer for Blog block documents.
 * Produces markup only after strict schema validation. Never accepts raw HTML.
 */
namespace Reamur\System\Library\Blog;

final class BlockRenderer {
    private const MAX_JSON_BYTES = 4194304;
    private string $downloadRoot;
    private string $audioRoot;

    public function __construct(string $downloadRoot = '', string $audioRoot = '') {
        $this->downloadRoot = $downloadRoot;
        $this->audioRoot = $audioRoot;
    }

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

        return ['valid' => true, 'html' => $this->renderNodes($document['blocks'], $validator, $assetBase, (int)$document['format_version']), 'errors' => []];
    }

    private function renderNodes(array $nodes, BlockDocument $validator, string $assetBase, int $version): string {
        $html = '';
        foreach ($nodes as $node) $html .= $this->renderNode($node, $validator, $assetBase, $version);
        return $html;
    }

    private function renderNode(array $node, BlockDocument $validator, string $assetBase, int $version): string {
        $type = $node['type'];
        $content = is_string($node['content']) ? $this->escapeWithBreaks($node['content']) : $this->renderInlineRuns($node['content']);
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
                foreach ($children as $child) $html .= '<li>' . (is_array($child['content']) ? $this->renderInlineRuns($child['content']) : $this->escapeWithBreaks($child['content'])) . '</li>';
                return $html . '</' . $tag . '>';

            case 'core/list-item':
                // Internal list nodes are emitted only by core/list.
                return '';

            case 'core/quote':
                return '<blockquote class="rms-block rms-block-quote"><p>' . $content . '</p></blockquote>';

            case 'core/callout':
                $tone = (string)$attributes['tone']; // validated enum; never interpolates arbitrary class data
                return '<aside class="rms-block rms-block-callout rms-block-callout--' . $tone . '" role="note">' . $content . '</aside>';

            case 'core/details':
                $summary = $this->escape((string)$attributes['summary']);
                return '<details class="rms-block rms-block-details"><summary>' . $summary . '</summary><div class="rms-block-details__content">' . $content . '</div></details>';

            case 'core/spacer':
                $height = (int)$attributes['height']; // closed numeric enum; styles map only to fixed CSS classes
                return '<div class="rms-block rms-block-spacer rms-block-spacer--' . $height . '" aria-hidden="true"></div>';

            case 'core/code':
                $plainCode = $this->escape((string)$node['content']);
                return '<pre class="rms-block rms-block-code"><code>' . $plainCode . '</code></pre>';

            case 'core/image':
                return $this->renderImageFigure($attributes, $assetBase);

            case 'core/file':
                if (!$this->downloadFileExists((string)$attributes['src'])) return '';
                $href = $this->escape($this->resolveLocalImageUrl((string)$attributes['src'], $assetBase));
                $label = $this->renderInlineRuns($node['content']);
                return '<p class="rms-block rms-block-file"><a href="' . $href . '" download>' . $label . '</a></p>';

            case 'core/audio':
                $srcPath = (string)$attributes['src'];
                if (!$this->audioFileExists($srcPath)) return '';
                $src = $this->escape($this->resolveLocalImageUrl($srcPath, $assetBase));
                $labelText = $this->escape($this->inlineRunsPlainText($node['content']));
                $labelHtml = $this->renderInlineRuns($node['content']);
                $mime = ['mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav'][strtolower(pathinfo($srcPath, PATHINFO_EXTENSION))];
                return '<figure class="rms-block rms-block-audio"><audio controls preload="none" aria-label="' . $labelText . '"><source src="' . $src . '" type="' . $mime . '"></audio><figcaption>' . $labelHtml . '</figcaption></figure>';

            case 'core/gallery':
                $columns = (int)$attributes['columns'];
                $align = (string)$attributes['align'];
                return '<div class="rms-block rms-block-gallery rms-block-gallery--columns-' . $columns . ' rms-block-gallery--align-' . $align . '">' . $this->renderNodes($children, $validator, $assetBase, $version) . '</div>';

            case 'core/table':
                $html = '<div class="rms-block rms-block-table-wrap"><table class="rms-block-table">';
                $rowStart = 0;
                if (!empty($attributes['header'])) {
                    $html .= '<thead><tr>';
                    foreach ($children[0]['children'] as $cell) $html .= '<th scope="col">' . $this->renderInlineRuns($cell['content']) . '</th>';
                    $html .= '</tr></thead>';
                    $rowStart = 1;
                }
                if ($rowStart < count($children)) {
                    $html .= '<tbody>';
                    for ($rowIndex = $rowStart; $rowIndex < count($children); $rowIndex++) {
                        $html .= '<tr>';
                        foreach ($children[$rowIndex]['children'] as $cell) $html .= '<td>' . $this->renderInlineRuns($cell['content']) . '</td>';
                        $html .= '</tr>';
                    }
                    $html .= '</tbody>';
                }
                return $html . '</table></div>';

            case 'core/table-row':
            case 'core/table-cell':
                // Internal table nodes are emitted only by core/table.
                return '';

            case 'core/cover':
                $src = $this->escape($this->resolveLocalImageUrl((string)$attributes['src'], $assetBase));
                $alt = $this->escape((string)$attributes['alt']);
                $align = (string)$attributes['align'];
                return '<section class="rms-block rms-block-cover rms-block-cover--align-' . $align . '"><img class="rms-block-cover__image" src="' . $src . '" alt="' . $alt . '" loading="lazy" decoding="async"><div class="rms-block-cover__content">' . $this->renderNodes($children, $validator, $assetBase, $version) . '</div></section>';

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
                return '<div class="rms-block-group">' . $this->renderNodes($children, $validator, $assetBase, $version) . '</div>';

            case 'core/columns':
                $count = (int)$attributes['count'];
                $html = '<div class="rms-block-columns rms-block-columns--' . $count . '">';
                foreach ($children as $column) $html .= '<div class="rms-block-column">' . $this->renderNodes($column['children'], $validator, $assetBase, $version) . '</div>';
                return $html . '</div>';

            case 'core/column':
                // Internal layout nodes are emitted only by core/columns.
                return '';
        }
        return '';
    }

    /** Resolves an existing local audio asset and rejects symlinks/MIME mismatches. */
    private function audioFileExists(string $relative): bool {
        if ($this->audioRoot === '' || !preg_match('#^view/audio/blog/([A-Za-z0-9][A-Za-z0-9._-]{0,179})$#D', $relative) || str_contains($relative, '..')) return false;
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (!in_array($extension, ['mp3','ogg','wav'], true)) return false;
        $cursor = $this->audioRoot;
        for ($depth = 0; $depth < 4; $depth++) { if (is_link($cursor)) return false; $cursor = dirname($cursor); }
        $root = realpath($this->audioRoot);
        if ($root === false) return false;
        $candidate = $root . DIRECTORY_SEPARATOR . basename($relative);
        if (is_link($candidate) || !is_file($candidate)) return false;
        $resolved = realpath($candidate);
        if ($resolved === false || !str_starts_with($resolved, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) return false;
        $finfo = new \finfo(FILEINFO_MIME_TYPE); $mime = (string)($finfo->file($resolved) ?: '');
        $allowed = ['mp3' => ['audio/mpeg'], 'ogg' => ['audio/ogg','application/ogg'], 'wav' => ['audio/wav','audio/x-wav','audio/wave','audio/vnd.wave']];
        return in_array($mime, $allowed[$extension], true);
    }

    /** Plain-text label for accessible attributes; markup is separately rendered through the inline allowlist. */
    private function inlineRunsPlainText(array $runs): string {
        $text = '';
        foreach ($runs as $run) if (is_array($run) && is_string($run['text'] ?? null)) $text .= $run['text'];
        return trim($text);
    }

    /** Resolve a local download only when the physical regular file remains inside the trusted root. */
    private function downloadFileExists(string $relative): bool {
        if ($this->downloadRoot === '' || !preg_match('#^view/download/blog/([A-Za-z0-9][A-Za-z0-9._-]{0,179})$#D', $relative) || str_contains($relative, '..')) return false;
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf','docx','xlsx','pptx','odt','ods','csv','txt'], true)) return false;
        $cursor = $this->downloadRoot;
        for ($depth = 0; $depth < 4; $depth++) {
            if (is_link($cursor)) return false;
            $cursor = dirname($cursor);
        }
        $root = realpath($this->downloadRoot);
        if ($root === false) return false;
        $candidate = $root . DIRECTORY_SEPARATOR . basename($relative);
        if (is_link($candidate) || !is_file($candidate)) return false;
        $size = filesize($candidate);
        if ($size === false || $size <= 0 || $size > 2097152) return false;
        $resolved = realpath($candidate);
        if ($resolved === false || !str_starts_with($resolved, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) return false;
        $extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        $mimeByExtension = [
            'pdf' => ['application/pdf'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'odt' => ['application/vnd.oasis.opendocument.text'],
            'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
            'csv' => ['text/plain', 'text/csv', 'application/csv'],
            'txt' => ['text/plain'],
        ];
        $finfo = new \finfo(FILEINFO_MIME_TYPE); $mime = (string)($finfo->file($resolved) ?: '');
        if (in_array($mime, $mimeByExtension[$extension] ?? [], true)) return true;
        if (!in_array($extension, ['docx','xlsx','pptx','odt','ods'], true) || $mime !== 'application/zip' || !class_exists(\ZipArchive::class)) return false;
        $zip = new \ZipArchive();
        if ($zip->open($resolved, \ZipArchive::CHECKCONS) !== true) return false;
        $required = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml', 'odt' => 'mimetype', 'ods' => 'mimetype'][$extension];
        $valid = $zip->locateName($required) !== false;
        if (in_array($extension, ['odt','ods'], true) && $valid) {
            $stat = $zip->statName('mimetype');
            $expected = $extension === 'odt' ? 'application/vnd.oasis.opendocument.text' : 'application/vnd.oasis.opendocument.spreadsheet';
            $valid = is_array($stat) && (int)($stat['size'] ?? 999) <= 128 && trim((string)$zip->getFromName('mimetype')) === $expected;
        } elseif ($valid) {
            $typeIndex = $zip->locateName('[Content_Types].xml');
            $stat = $typeIndex === false ? false : $zip->statIndex($typeIndex);
            $contentTypes = is_array($stat) && (int)($stat['size'] ?? 999999) <= 65536 ? (string)$zip->getFromIndex($typeIndex) : '';
            $expected = ['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml'][$extension];
            $valid = $contentTypes !== '' && str_contains($contentTypes, $expected);
        }
        $zip->close();
        return $valid;
    }

    /** Render a schema-validated image with only finite style tokens and escaped caption text. */
    private function renderImageFigure(array $attributes, string $assetBase): string {
        $src = $this->escape($this->resolveLocalImageUrl((string)$attributes['src'], $assetBase));
        $alt = $this->escape((string)$attributes['alt']);
        $title = isset($attributes['title']) && $attributes['title'] !== '' ? ' title="' . $this->escape((string)$attributes['title']) . '"' : '';
        $align = (string)($attributes['align'] ?? 'center');
        $size = (string)($attributes['size'] ?? 'large');
        $mobileSize = (string)($attributes['mobile_size'] ?? 'full');
        $class = 'rms-block rms-block-image rms-block-image--align-' . $align . ' rms-block-image--size-' . $size . ' rms-block-image--mobile-' . $mobileSize;
        $caption = isset($attributes['caption']) && $attributes['caption'] !== '' ? '<figcaption class="rms-block-image__caption">' . $this->escape((string)$attributes['caption']) . '</figcaption>' : '';
        return '<figure class="' . $class . '"><img src="' . $src . '" alt="' . $alt . '"' . $title . ' loading="lazy" decoding="async">' . $caption . '</figure>';
    }

    /** Render version-2 inline runs using fixed tags and contextual escaping only. */
    private function renderInlineRuns(array $runs): string {
        $tags = ['bold' => 'strong', 'italic' => 'em', 'underline' => 'u', 'strike' => 's', 'code' => 'code'];
        $html = '';
        foreach ($runs as $run) {
            $part = $this->escapeWithBreaks((string)$run['text']);
            foreach (array_reverse($run['marks']) as $mark) {
                if (isset($tags[$mark])) $part = '<' . $tags[$mark] . '>' . $part . '</' . $tags[$mark] . '>';
            }
            if (is_string($run['href']) && $run['href'] !== '') {
                $part = '<a href="' . $this->escape($run['href']) . '" rel="nofollow noopener noreferrer">' . $part . '</a>';
            }
            $html .= $part;
        }
        return $html;
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
