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
    private string $videoRoot;
    private bool $allowPageMedia;

    public function __construct(string $downloadRoot = '', string $audioRoot = '', string $videoRoot = '', bool $allowPageMedia = false) {
        $this->downloadRoot = $downloadRoot;
        $this->audioRoot = $audioRoot;
        $this->videoRoot = $videoRoot;
        $this->allowPageMedia = $allowPageMedia;
    }

    /**
     * @return array{valid:bool,html:string,errors:array,requires_threejs?:bool}
     */
    public function renderJson(string $json, ?int $storedFormatVersion = null, string $assetBase = ''): array {
        if ($json === '' || strlen($json) > self::MAX_JSON_BYTES) return ['valid' => false, 'html' => '', 'errors' => ['document_size_invalid']];
        $document = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($document)) return ['valid' => false, 'html' => '', 'errors' => ['invalid_json']];
        if ($storedFormatVersion !== null && $storedFormatVersion !== ($document['format_version'] ?? null)) return ['valid' => false, 'html' => '', 'errors' => ['stored_version_mismatch']];

        $validator = new BlockDocument($this->allowPageMedia);
        $validation = $validator->validateDocument($document);
        if (!$validation['valid']) return ['valid' => false, 'html' => '', 'errors' => $validation['errors']];

        return ['valid' => true, 'html' => $this->renderNodes($document['blocks'], $validator, $assetBase, (int)$document['format_version']), 'errors' => [], 'requires_threejs' => $this->containsThreeJsBlock($document['blocks'])];
    }

    private function containsThreeJsBlock(array $nodes): bool {
        foreach ($nodes as $node) if (($node['type'] ?? '') === 'core/threejs_thumb' || $this->containsThreeJsBlock($node['children'] ?? [])) return true;
        return false;
    }

    private function renderNodes(array $nodes, BlockDocument $validator, string $assetBase, int $version): string {
        $html = '';
        foreach ($nodes as $node) $html .= $this->renderNode($node, $validator, $assetBase, $version);
        return $html;
    }

    private function renderNode(array $node, BlockDocument $validator, string $assetBase, int $version): string {
        $html = $this->renderNodeMarkup($node, $validator, $assetBase, $version);
        $appearance = $node['attributes']['appearance'] ?? null;
        if ($html === '' || !is_array($appearance)) return $html;
        $style = $this->appearanceStyle($appearance);
        if ($style === '') return $html;
        return $this->injectAppearanceStyle($html, $style, ($node['type'] ?? '') === 'core/button');
    }

    private function appearanceStyle(array $appearance): string {
        $rules = [];
        foreach (['text_color'=>'color','background_color'=>'background-color'] as $key=>$property) {
            if (isset($appearance[$key])) $rules[] = $property . ':' . $appearance[$key];
        }
        $borderWidth = (int)($appearance['border_width'] ?? (isset($appearance['border_color']) ? 1 : 0));
        if ($borderWidth > 0) $rules[] = 'border:' . $borderWidth . 'px ' . (string)($appearance['border_style'] ?? 'solid') . ' ' . (string)($appearance['border_color'] ?? '#d0d5dd');
        if (!empty($appearance['border_radius'])) $rules[] = 'border-radius:' . (int)$appearance['border_radius'] . 'px';
        if (!empty($appearance['padding'])) $rules[] = 'padding:' . (int)$appearance['padding'] . 'px';
        if (!empty($appearance['margin'])) $rules[] = 'margin:' . (int)$appearance['margin'] . 'px';
        if (!empty($appearance['font_size'])) $rules[] = 'font-size:' . (int)$appearance['font_size'] . 'px';
        if (isset($appearance['text_align'])) $rules[] = 'text-align:' . $appearance['text_align'];
        return implode(';', $rules);
    }

    private function appearanceAttribute($appearance): string {
        if (!is_array($appearance) || !$appearance) return '';
        $style = $this->appearanceStyle($appearance);
        return $style === '' ? '' : ' style="' . $this->escape($style) . '"';
    }

    private function injectAppearanceStyle(string $html, string $style, bool $button): string {
        $pattern = $button ? '~<a(?=[^>]*class="[^"]*rms-block-button")[^>]*>~' : '~^<[A-Za-z][A-Za-z0-9:-]*[^>]*>~';
        $escaped = $this->escape($style);
        $updated = preg_replace_callback($pattern, static function (array $match) use ($escaped): string {
            return substr($match[0], 0, -1) . ' style="' . $escaped . '">';
        }, $html, 1);
        return is_string($updated) ? $updated : $html;
    }

    private function renderNodeMarkup(array $node, BlockDocument $validator, string $assetBase, int $version): string {
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
                foreach ($children as $child) $html .= '<li' . $this->appearanceAttribute($child['attributes']['appearance'] ?? null) . '>' . (is_array($child['content']) ? $this->renderInlineRuns($child['content']) : $this->escapeWithBreaks($child['content'])) . '</li>';
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

            case 'core/threejs_thumb':
                $label = $this->escape((string)$attributes['label']);
                $scene = json_decode((string)$attributes['scene_json'], true);
                $sceneJson = json_encode($scene, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (!is_string($sceneJson)) return '';
                $sceneAttr = $this->escape($sceneJson);
                $customScript = base64_encode((string)($attributes['custom_js'] ?? ''));
                return '<figure class="rms-block rms-block-threejs-thumb"><div class="rms-block-threejs-thumb__stage" data-threejs-thumb data-scene="' . $sceneAttr . '" data-custom-js="' . $customScript . '" role="img" aria-label="' . $label . '"><span class="rms-block-threejs-thumb__fallback">' . $label . '</span></div></figure>';

            case 'core/video_file':
                $srcPath = (string)$attributes['src'];
                if (!$this->videoFileExists($srcPath)) return '';
                $src = $this->escape($this->resolveLocalImageUrl($srcPath, $assetBase));
                $mime = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg'][strtolower(pathinfo($srcPath, PATHINFO_EXTENSION))];
                $alignment = (string)($attributes['align'] ?? 'center');
                $size = (string)($attributes['size'] ?? 'large');
                $mobileSize = (string)($attributes['mobile_size'] ?? 'full');
                $figureClass = 'rms-block rms-block-video rms-block-video--align-' . $alignment . ' rms-block-video--size-' . $size . ' rms-block-video--mobile-' . $mobileSize;
                $title = isset($attributes['title']) && $attributes['title'] !== '' ? ' title="' . $this->escape((string)$attributes['title']) . '"' : '';
                $controls = !array_key_exists('controls', $attributes) || $attributes['controls'] ? ' controls' : '';
                $loop = !empty($attributes['loop']) ? ' loop' : '';
                $muted = !empty($attributes['muted']) ? ' muted' : '';
                $autoplay = !empty($attributes['autoplay']) ? ' autoplay' : '';
                if ($autoplay !== '' && $muted === '') $muted = ' muted'; // autoplay is muted to comply with browser playback policies.
                $caption = isset($attributes['caption']) && $attributes['caption'] !== '' ? '<figcaption>' . $this->escape((string)$attributes['caption']) . '</figcaption>' : '';
                return '<figure class="' . $figureClass . '"><video class="rms-block-video__file"' . $title . $controls . ' preload="none" playsinline' . $loop . $muted . $autoplay . '><source src="' . $src . '" type="' . $mime . '"></video>' . $caption . '</figure>';

            case 'core/gallery':
                $columns = (int)$attributes['columns'];
                $align = (string)$attributes['align'];
                return '<div class="rms-block rms-block-gallery rms-block-gallery--columns-' . $columns . ' rms-block-gallery--align-' . $align . '">' . $this->renderNodes($children, $validator, $assetBase, $version) . '</div>';

            case 'core/table':
                $rows = $children;
                $hasHeader = !empty($attributes['header']);
                $headerRowCrossesGroup = false;
                if ($hasHeader && isset($rows[0]['children']) && is_array($rows[0]['children'])) {
                    foreach ($rows[0]['children'] as $headerCell) {
                        $span = (int)($headerCell['attributes']['rowspan'] ?? 1);
                        if ($span > 1) { $headerRowCrossesGroup = true; break; }
                    }
                }
                $useThead = $hasHeader && !$headerRowCrossesGroup;
                $renderRow = function (array $row, int $rowIndex) use ($attributes): string {
                    $html = '<tr' . $this->appearanceAttribute($row['attributes']['appearance'] ?? null) . '>';
                    foreach (($row['children'] ?? []) as $cell) {
                        $cellAttrs = $cell['attributes'] ?? [];
                        $isHeader = array_key_exists('header', $cellAttrs) ? (bool)$cellAttrs['header'] : (!empty($attributes['header']) && $rowIndex === 0);
                        $tag = $isHeader ? 'th' : 'td';
                        $html .= '<' . $tag . ($isHeader ? ' scope="col"' : '');
                        $rowspan = (int)($cellAttrs['rowspan'] ?? 1);
                        $colspan = (int)($cellAttrs['colspan'] ?? 1);
                        if ($rowspan > 1) $html .= ' rowspan="' . $rowspan . '"';
                        if ($colspan > 1) $html .= ' colspan="' . $colspan . '"';
                        $html .= $this->appearanceAttribute($cellAttrs['appearance'] ?? null) . '>' . $this->renderInlineRuns($cell['content']) . '</' . $tag . '>';
                    }
                    return $html . '</tr>';
                };
                $html = '<div class="rms-block rms-block-table-wrap"><table class="rms-block-table">';
                $rowStart = 0;
                if ($useThead && isset($rows[0])) {
                    $html .= '<thead>' . $renderRow($rows[0], 0) . '</thead>';
                    $rowStart = 1;
                }
                if ($rowStart < count($rows)) {
                    $html .= '<tbody>';
                    for ($rowIndex = $rowStart; $rowIndex < count($rows); $rowIndex++) $html .= $renderRow($rows[$rowIndex], $rowIndex);
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
                if (isset($attributes['src']) && $attributes['src'] !== '') {
                    $srcPath=(string)$attributes['src'];
                    if(!$this->videoFileExists($srcPath))return '';
                    $src=$this->escape($this->resolveLocalImageUrl($srcPath,$assetBase));
                    $mime=['mp4'=>'video/mp4','webm'=>'video/webm','ogv'=>'video/ogg'][strtolower(pathinfo($srcPath,PATHINFO_EXTENSION))];
                    return '<figure class="rms-block rms-block-video"><video class="rms-block-video__file" controls preload="none" playsinline><source src="'.$src.'" type="'.$mime.'"></video></figure>';
                }
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
                $lineStyle = (string)($attributes['style'] ?? 'solid');
                $width = (int)($attributes['width'] ?? 100);
                $thickness = (int)($attributes['thickness'] ?? 1);
                $align = (string)($attributes['align'] ?? 'center');
                $marginLeft = $align === 'right' ? 'auto' : ($align === 'center' ? 'auto' : '0');
                $marginRight = $align === 'left' ? 'auto' : ($align === 'center' ? 'auto' : '0');
                $separatorStyle = 'width:' . $width . '%;border:0;border-top:' . $thickness . 'px ' . $lineStyle . ' currentColor;margin-left:' . $marginLeft . ';margin-right:' . $marginRight . ';';
                return '<hr class="rms-block rms-block-separator rms-block-separator--' . $lineStyle . ' rms-block-separator--width-' . $width . ' rms-block-separator--thickness-' . $thickness . ' rms-block-separator--align-' . $align . '" style="' . $separatorStyle . '">';

            case 'core/group':
                return '<div class="rms-block-group">' . $this->renderNodes($children, $validator, $assetBase, $version) . '</div>';

            case 'core/columns':
                $count = (int)$attributes['count'];
                $html = '<div class="rms-block-columns rms-block-columns--' . $count . '">';
                foreach ($children as $column) $html .= '<div class="rms-block-column"' . $this->appearanceAttribute($column['attributes']['appearance'] ?? null) . '>' . $this->renderNodes($column['children'], $validator, $assetBase, $version) . '</div>';
                return $html . '</div>';

            case 'core/column':
                // Internal layout nodes are emitted only by core/columns.
                return '';
        }
        return '';
    }

    /** Resolve Blog legacy or CMS Pages media beneath their explicitly configured media roots. */
    private function audioFileExists(string $relative): bool {
        return $this->mediaFileExists($this->audioRoot, 'audio', $relative, ['mp3'=>['audio/mpeg'], 'ogg'=>['audio/ogg','application/ogg'], 'wav'=>['audio/wav','audio/x-wav','audio/wave','audio/vnd.wave']], 0);
    }

    private function videoFileExists(string $relative): bool {
        return $this->mediaFileExists($this->videoRoot, 'video', $relative, ['mp4'=>['video/mp4'], 'webm'=>['video/webm'], 'ogv'=>['video/ogg']], 104857600);
    }

    /** Plain-text label for accessible attributes; markup is separately rendered through the inline allowlist. */
    private function inlineRunsPlainText(array $runs): string {
        $text = '';
        foreach ($runs as $run) if (is_array($run) && is_string($run['text'] ?? null)) $text .= $run['text'];
        return trim($text);
    }

    /** Resolve Blog legacy or CMS Pages downloads within a trusted directory, validating MIME and size. */
    private function downloadFileExists(string $relative): bool {
        $mime = [
            'pdf'=>['application/pdf'], 'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], 'pptx'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'odt'=>['application/vnd.oasis.opendocument.text'], 'ods'=>['application/vnd.oasis.opendocument.spreadsheet'],
            'csv'=>['text/plain','text/csv','application/csv'], 'txt'=>['text/plain'],
        ];
        return $this->mediaFileExists($this->downloadRoot, 'download', $relative, $mime, 2097152);
    }

    /** @param array<string,list<string>> $mimeByExtension */
    private function mediaFileExists(string $configuredRoot, string $type, string $relative, array $mimeByExtension, int $maxBytes): bool {
        if ($configuredRoot === '' || str_contains($relative, '..') || !preg_match('#^view/' . preg_quote($type, '#') . '/(blog|cms/page)/([A-Za-z0-9][A-Za-z0-9._-]{0,179})$#D', $relative, $match)) return false;
        $extension = strtolower(pathinfo($match[2], PATHINFO_EXTENSION));
        if (!isset($mimeByExtension[$extension])) return false;
        if (basename(rtrim($configuredRoot, DIRECTORY_SEPARATOR)) === $type) $rootPath = rtrim($configuredRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $match[1]);
        elseif ($match[1] === 'blog' && basename(rtrim($configuredRoot, DIRECTORY_SEPARATOR)) === 'blog') $rootPath = rtrim($configuredRoot, DIRECTORY_SEPARATOR);
        else return false;
        $foundCatalog = false;
        for ($cursor = $rootPath; ; $cursor = dirname($cursor)) {
            if (is_link($cursor)) return false;
            if (basename($cursor) === 'catalog') { $foundCatalog = true; break; }
            if (dirname($cursor) === $cursor) break;
        }
        if (!$foundCatalog) return false;
        $root = realpath($rootPath); if ($root === false) return false;
        $candidate = $root . DIRECTORY_SEPARATOR . $match[2];
        if (is_link($candidate) || !is_file($candidate)) return false;
        $size = @filesize($candidate); if (!is_int($size) || $size < 1 || ($maxBytes > 0 && $size > $maxBytes)) return false;
        $resolved = realpath($candidate);
        if ($resolved === false || dirname($resolved) !== $root) return false;
        $finfo = new \finfo(FILEINFO_MIME_TYPE); $actual = (string)($finfo->file($resolved) ?: '');
        return in_array($actual, $mimeByExtension[$extension], true);
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
