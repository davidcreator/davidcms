<?php
/**
 * ReamurCMS — F9-13 deterministic, fail-closed legacy Blog HTML converter.
 * No persistence, UI, database, rendering, or automatic rewrite side effects.
 * Consumers must persist only after an explicit successful save.
 */
namespace Reamur\System\Library\Blog;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMComment;
use RuntimeException;

class BlockDocument {
    public const FORMAT_VERSION = 1;
    public const RICH_TEXT_FORMAT_VERSION = 2;
    public const MAX_INLINE_RUNS = 1000;
    public const MAX_INLINE_CONTENT_BYTES = 262144;
    public const MAX_MEDIA_TEXT_BYTES = 4096;
    public const MAX_GALLERY_IMAGES = 20;
    public const MAX_COVER_CHILDREN = 12;
    public const MAX_TABLE_ROWS = 20;
    public const MAX_TABLE_COLUMNS = 12;
    public const MAX_TABLE_TEXT_BYTES = 262144;
    public const MAX_SOURCE_BYTES = 1048576;
    public const MAX_BLOCKS = 1000;
    public const MAX_TOTAL_NODES = 2000;
    public const MAX_DEPTH = 16;

    /**
     * Convert only the deliberately small legacy HTML subset. Any unknown tag,
     * attribute, malformed source, or unsafe image path returns the untouched
     * source and no partial document. That is a review state, never a saveable tree.
     *
     * @return array{status:string,source_html:string,document:?array,diagnostics:array}
     */
    public function convertLegacy(string $html): array {
        $review = static function (string $reason) use ($html): array {
            return ['status' => 'review_required', 'source_html' => $html, 'document' => null, 'diagnostics' => [$reason]];
        };
        if (strlen($html) > self::MAX_SOURCE_BYTES) return $review('source_too_large');
        if (!preg_match('//u', $html)) return $review('invalid_utf8');
        if (!class_exists(DOMDocument::class)) return $review('dom_extension_unavailable');
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $html)) return $review('doctype_or_entity_not_supported');

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body><div data-f913-root="1">' . $html . '</div></body></html>', LIBXML_NONET);
        $parseErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) return $review('html_parse_failed');
        foreach ($parseErrors as $parseError) {
            if ($parseError->level >= LIBXML_ERR_WARNING) return $review('html_parse_warning');
        }

        $root = null;
        foreach ($dom->getElementsByTagName('div') as $candidate) {
            if ($candidate instanceof DOMElement && $candidate->getAttribute('data-f913-root') === '1') { $root = $candidate; break; }
        }
        if (!$root) return $review('wrapper_missing');

        try {
            $blocks = [];
            foreach ($root->childNodes as $i => $child) {
                if ($child instanceof DOMText && trim($child->nodeValue) === '') continue;
                if ($child instanceof DOMComment) throw new RuntimeException('comment_not_supported');
                $blocks[] = $this->convertBlock($child, $html, 'b' . $i, 0);
                if (count($blocks) > self::MAX_BLOCKS) throw new RuntimeException('too_many_blocks');
            }
            $document = ['format_version' => self::FORMAT_VERSION, 'blocks' => $blocks];
            $validation = $this->validateDocument($document);
            if (!$validation['valid']) throw new RuntimeException('generated_document_invalid');
            return ['status' => 'converted', 'source_html' => $html, 'document' => $document, 'diagnostics' => []];
        } catch (RuntimeException $e) {
            return $review($e->getMessage());
        }
    }

    /** Deterministic JSON serializer; rejects unknown/invalid nodes. */
    public function serializeDocument(array $document): string {
        $result = $this->validateDocument($document);
        if (!$result['valid']) throw new RuntimeException('invalid_block_document:' . implode(',', $result['errors']));
        $version = (int)$document['format_version'];
        $canonical = ['format_version' => $version, 'blocks' => []];
        foreach ($document['blocks'] as $node) $canonical['blocks'][] = $this->canonicalNode($node, $version);
        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException('json_encode_failed');
        if (strlen($json) > 4194304) throw new RuntimeException('document_too_large');
        return $json;
    }

    /** Strict JSON decoder; no coercion, unknown version, or unknown block. */
    public function deserializeDocument(string $json): array {
        if (strlen($json) > 4194304) return ['valid' => false, 'document' => null, 'errors' => ['document_too_large']];
        $document = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($document)) return ['valid' => false, 'document' => null, 'errors' => ['invalid_json']];
        $result = $this->validateDocument($document);
        return ['valid' => $result['valid'], 'document' => $result['valid'] ? $document : null, 'errors' => $result['errors']];
    }

    /** @return array{valid:bool,errors:array} */
    public function validateDocument(array $document): array {
        $errors = [];
        $rootKeys = array_keys($document);
        sort($rootKeys);
        if ($rootKeys !== ['blocks', 'format_version']) $errors[] = 'root_shape_invalid';
        $version = $document['format_version'] ?? null;
        if (!is_int($version) || !in_array($version, [self::FORMAT_VERSION, self::RICH_TEXT_FORMAT_VERSION], true)) {
            $errors[] = 'unsupported_format_version';
            $version = 0;
        }
        if (!isset($document['blocks']) || !is_array($document['blocks']) || !$this->isSequentialList($document['blocks']) || count($document['blocks']) > self::MAX_BLOCKS) $errors[] = 'blocks_invalid';
        $ids = []; $count = 0;
        if (isset($document['blocks']) && is_array($document['blocks'])) {
            foreach ($document['blocks'] as $node) $this->validateNode($node, 0, null, $ids, $errors, $count, $version ?? 0);
        }
        return ['valid' => !$errors, 'errors' => array_values(array_unique($errors))];
    }

    private function isSequentialList(array $value): bool {
        $expected = 0;
        foreach ($value as $key => $_) if ($key !== $expected++) return false;
        return true;
    }

    private function canonicalNode(array $node, int $version): array {
        $attributes = $node['attributes'];
        ksort($attributes, SORT_STRING);
        $children = [];
        foreach ($node['children'] as $child) $children[] = $this->canonicalNode($child, $version);
        $content = $version === self::RICH_TEXT_FORMAT_VERSION && is_array($node['content']) ? $this->canonicalRuns($node['content']) : $node['content'];
        return ['id' => $node['id'], 'type' => $node['type'], 'attributes' => $attributes, 'children' => $children, 'content' => $content];
    }

    private function validateNode($node, int $depth, ?string $parentType, array &$ids, array &$errors, int &$count, int $version): void {
        if ($depth > self::MAX_DEPTH || !is_array($node)) { $errors[] = 'node_depth_or_type_invalid'; return; }
        $count++;
        if ($count > self::MAX_TOTAL_NODES) { $errors[] = 'too_many_nodes'; return; }
        $keys = array_keys($node); sort($keys);
        if ($keys !== ['attributes', 'children', 'content', 'id', 'type']) { $errors[] = 'node_shape_invalid'; return; }
        if (!is_string($node['id']) || !preg_match('/^[A-Za-z0-9_-]{1,96}$/D', $node['id']) || isset($ids[$node['id']])) $errors[] = 'node_id_invalid_or_duplicate';
        else $ids[$node['id']] = true;
        $types = ['core/paragraph','core/heading','core/list','core/list-item','core/quote','core/callout','core/details','core/spacer','core/code','core/image','core/video_embed','core/button','core/separator','core/group','core/columns','core/column','core/gallery','core/cover','core/table','core/table-row','core/table-cell','core/file','core/audio'];
        if (!is_string($node['type']) || !in_array($node['type'], $types, true)) { $errors[] = 'unknown_block_type'; return; }
        if (!is_array($node['attributes']) || !is_array($node['children']) || !$this->isSequentialList($node['children'])) { $errors[] = 'node_value_type_invalid'; return; }
        $type = $node['type']; $attrs = $node['attributes'];
        if ($version === self::FORMAT_VERSION && in_array($type, ['core/gallery','core/cover'], true)) $errors[] = 'media_block_requires_v2';
        if ($version === self::FORMAT_VERSION && $type === 'core/callout') $errors[] = 'callout_requires_v2';
        if ($version === self::FORMAT_VERSION && $type === 'core/details') $errors[] = 'details_requires_v2';
        if ($version === self::FORMAT_VERSION && $type === 'core/spacer') $errors[] = 'spacer_requires_v2';
        if ($version === self::FORMAT_VERSION && $type === 'core/code') $errors[] = 'code_requires_v2';
        if ($version === self::FORMAT_VERSION && $type === 'core/table') $errors[] = 'table_requires_v2';
        $allowedAttrs = [
            'core/paragraph' => ['align'], 'core/heading' => ['level'], 'core/list' => ['ordered'],
            'core/list-item' => [], 'core/quote' => [], 'core/callout' => ['tone'], 'core/details' => ['summary'], 'core/spacer' => ['height'], 'core/code' => [], 'core/image' => ['src','alt','title'],
            'core/video_embed' => ['url'], 'core/button' => ['url'], 'core/separator' => [],
            'core/group' => [], 'core/columns' => ['count'], 'core/column' => [],
            'core/gallery' => ['columns','align'], 'core/cover' => ['src','alt','align'],
            'core/table' => ['header'], 'core/table-row' => [], 'core/table-cell' => [], 'core/file' => ['src'], 'core/audio' => ['src']
        ];
        if ($version === self::RICH_TEXT_FORMAT_VERSION) {
            $allowedAttrs['core/image'] = ['src','alt','title','caption','align','size','mobile_size'];
            $allowedAttrs['core/group'] = ['outline_label'];
            $allowedAttrs['core/table'] = ['header'];
            $allowedAttrs['core/file'] = ['src'];
            $allowedAttrs['core/audio'] = ['src'];
        }
        if (array_diff(array_keys($attrs), $allowedAttrs[$type])) $errors[] = 'attributes_not_allowlisted';
        if ($type === 'core/paragraph' && isset($attrs['align']) && !in_array($attrs['align'], ['left','center','right'], true)) $errors[] = 'alignment_invalid';
        if ($type === 'core/heading' && (!isset($attrs['level']) || !is_int($attrs['level']) || $attrs['level'] < 1 || $attrs['level'] > 6)) $errors[] = 'heading_level_invalid';
        if ($type === 'core/list' && (!isset($attrs['ordered']) || !is_bool($attrs['ordered']))) $errors[] = 'list_order_invalid';
        if ($type === 'core/file' && ($version !== self::RICH_TEXT_FORMAT_VERSION || !isset($attrs['src']) || !$this->isSafeDownloadPath($attrs['src']) || !is_array($node['content']) || !$this->hasVisibleInlineText($node['content']))) $errors[] = 'file_attributes_invalid';
        if ($type === 'core/audio' && ($version !== self::RICH_TEXT_FORMAT_VERSION || !isset($attrs['src']) || !$this->isSafeAudioPath($attrs['src']) || !is_array($node['content']) || !$this->hasVisibleInlineText($node['content']))) $errors[] = 'audio_attributes_invalid';
        if ($type === 'core/image' && (!isset($attrs['src']) || !$this->isSafeImagePath($attrs['src']) || !isset($attrs['alt']) || !is_string($attrs['alt']) || (isset($attrs['title']) && !is_string($attrs['title'])))) $errors[] = 'image_attributes_invalid';
        if ($type === 'core/group' && isset($attrs['outline_label']) && (!is_string($attrs['outline_label']) || strlen($attrs['outline_label']) > 160 || !preg_match('//u', $attrs['outline_label']))) $errors[] = 'outline_label_invalid';
        if ($type === 'core/image') {
            foreach (['alt','title','caption'] as $textKey) {
                if (isset($attrs[$textKey]) && (!is_string($attrs[$textKey]) || strlen($attrs[$textKey]) > self::MAX_MEDIA_TEXT_BYTES || !preg_match('//u', $attrs[$textKey]))) $errors[] = 'image_text_attribute_invalid';
            }
            if (isset($attrs['align']) && !in_array($attrs['align'], ['left','center','right','wide','full'], true)) $errors[] = 'image_alignment_invalid';
            if (isset($attrs['size']) && !in_array($attrs['size'], ['small','medium','large','full'], true)) $errors[] = 'image_size_invalid';
            if (isset($attrs['mobile_size']) && !in_array($attrs['mobile_size'], ['full','large','medium'], true)) $errors[] = 'image_mobile_size_invalid';
        }
        if ($type === 'core/gallery' && (!isset($attrs['columns']) || !is_int($attrs['columns']) || !in_array($attrs['columns'], [2,3,4], true) || !isset($attrs['align']) || !in_array($attrs['align'], ['content','wide','full'], true) || count($node['children']) < 1 || count($node['children']) > self::MAX_GALLERY_IMAGES)) $errors[] = 'gallery_attributes_or_count_invalid';
        if ($type === 'core/cover' && (!isset($attrs['src']) || !$this->isSafeImagePath($attrs['src']) || !isset($attrs['alt']) || !is_string($attrs['alt']) || strlen($attrs['alt']) > self::MAX_MEDIA_TEXT_BYTES || !preg_match('//u', (string)$attrs['alt']) || !isset($attrs['align']) || !in_array($attrs['align'], ['content','wide','full'], true) || count($node['children']) < 1 || count($node['children']) > self::MAX_COVER_CHILDREN)) $errors[] = 'cover_attributes_or_children_invalid';
        if ($type === 'core/video_embed' && (!isset($attrs['url']) || !$this->isSafeEmbedUrl($attrs['url']))) $errors[] = 'embed_url_invalid';
        if ($type === 'core/button' && (!isset($attrs['url']) || !$this->isSafeActionUrl($attrs['url']))) $errors[] = 'button_url_invalid';
        if ($type === 'core/callout' && (!isset($attrs['tone']) || !in_array($attrs['tone'], ['info','success','warning','danger'], true))) $errors[] = 'callout_tone_invalid';
        if ($type === 'core/details' && (!isset($attrs['summary']) || !is_string($attrs['summary']) || trim($attrs['summary']) === '' || strlen($attrs['summary']) > 512 || !preg_match('//u', $attrs['summary']))) $errors[] = 'details_summary_invalid';
        if ($type === 'core/spacer' && (!isset($attrs['height']) || !in_array($attrs['height'], [16,32,48,64,96], true))) $errors[] = 'spacer_height_invalid';
        if ($type === 'core/code' && (!is_string($node['content']) || strlen($node['content']) > self::MAX_INLINE_CONTENT_BYTES || !preg_match('//u', $node['content']))) $errors[] = 'code_content_invalid';
        if ($type === 'core/table' && (!isset($attrs['header']) || !is_bool($attrs['header']) || count($node['children']) < 1 || count($node['children']) > self::MAX_TABLE_ROWS)) $errors[] = 'table_shape_invalid';
        if ($type === 'core/table-row' && (count($node['children']) < 1 || count($node['children']) > self::MAX_TABLE_COLUMNS)) $errors[] = 'table_row_shape_invalid';
        if ($type === 'core/columns' && (!isset($attrs['count']) || !is_int($attrs['count']) || !in_array($attrs['count'], [2,3], true) || count($node['children']) !== $attrs['count'])) $errors[] = 'columns_count_invalid';
        $textTypes = ['core/paragraph','core/heading','core/list-item','core/quote','core/button','core/callout','core/details','core/table-cell','core/file','core/audio'];
        if ($version === self::FORMAT_VERSION) {
            if (!is_string($node['content'])) $errors[] = 'legacy_content_must_be_string';
            elseif (strlen($node['content']) > self::MAX_INLINE_CONTENT_BYTES) $errors[] = 'block_content_too_large';
        } elseif ($version === self::RICH_TEXT_FORMAT_VERSION && in_array($type, $textTypes, true)) {
            if (!is_array($node['content']) || !$this->isSequentialList($node['content'])) $errors[] = 'rich_content_must_be_run_list';
            else $this->validateRuns($node['content'], $type, $errors);
        } elseif ($version === self::RICH_TEXT_FORMAT_VERSION && $type === 'core/code') {
            if (!is_string($node['content']) || strlen($node['content']) > self::MAX_INLINE_CONTENT_BYTES || !preg_match('//u', $node['content'])) $errors[] = 'code_content_invalid';
        } elseif (!is_string($node['content']) || $node['content'] !== '') {
            $errors[] = 'non_text_content_invalid';
        }
        if (in_array($type, ['core/list','core/image','core/video_embed','core/separator','core/group','core/columns','core/column','core/gallery','core/cover','core/table','core/table-row'], true) && $node['content'] !== '') $errors[] = 'content_not_allowed';
        if ($type === 'core/list-item' && ($parentType !== 'core/list' || count($node['children']) > 0)) $errors[] = 'list_item_parent_invalid';
        if ($type === 'core/column' && $parentType !== 'core/columns') $errors[] = 'column_parent_invalid';
        if ($type === 'core/table-row' && $parentType !== 'core/table') $errors[] = 'table_row_parent_invalid';
        if ($type === 'core/table-cell' && $parentType !== 'core/table-row') $errors[] = 'table_cell_parent_invalid';
        if ($type === 'core/list') {
            if (count($node['children']) < 1) $errors[] = 'empty_list';
            foreach ($node['children'] as $child) if (!is_array($child) || ($child['type'] ?? null) !== 'core/list-item') $errors[] = 'list_child_invalid';
        } elseif ($type === 'core/columns') {
            foreach ($node['children'] as $child) if (!is_array($child) || ($child['type'] ?? null) !== 'core/column') $errors[] = 'columns_child_invalid';
        } elseif ($type === 'core/gallery') {
            foreach ($node['children'] as $child) if (!is_array($child) || ($child['type'] ?? null) !== 'core/image') $errors[] = 'gallery_child_invalid';
        } elseif ($type === 'core/table') {
            $columnCount = null; $tableTextBytes = 0;
            foreach ($node['children'] as $row) {
                if (!is_array($row) || ($row['type'] ?? null) !== 'core/table-row') { $errors[] = 'table_row_invalid'; continue; }
                $cells = $row['children'] ?? [];
                if ($columnCount === null) $columnCount = count($cells);
                if (count($cells) !== $columnCount) $errors[] = 'table_columns_mismatch';
                foreach ($cells as $cell) {
                    if (!is_array($cell) || ($cell['type'] ?? null) !== 'core/table-cell') { $errors[] = 'table_cell_invalid'; continue; }
                    foreach (($cell['content'] ?? []) as $run) if (is_array($run) && is_string($run['text'] ?? null)) $tableTextBytes += strlen($run['text']);
                }
            }
            if ($columnCount === null || $columnCount < 1 || $columnCount > self::MAX_TABLE_COLUMNS) $errors[] = 'table_column_count_invalid';
            if ($tableTextBytes > self::MAX_TABLE_TEXT_BYTES) $errors[] = 'table_text_too_large';
        } elseif ($type === 'core/table-row') {
            if ($parentType !== 'core/table' || count($attrs) > 0) $errors[] = 'table_row_invalid';
            foreach ($node['children'] as $cell) if (!is_array($cell) || ($cell['type'] ?? null) !== 'core/table-cell') $errors[] = 'table_cell_invalid';
        } elseif ($type === 'core/table-cell') {
            if ($parentType !== 'core/table-row' || count($attrs) > 0 || count($node['children']) > 0) $errors[] = 'table_cell_invalid';
        } elseif ($type === 'core/cover') {
            $coverChildTypes = ['core/heading','core/paragraph','core/quote','core/button'];
            foreach ($node['children'] as $child) if (!is_array($child) || !in_array(($child['type'] ?? null), $coverChildTypes, true)) $errors[] = 'cover_child_invalid';
        } elseif ($type === 'core/group' || $type === 'core/column') {
            if ($type === 'core/group' && array_diff(array_keys($attrs), $version === self::RICH_TEXT_FORMAT_VERSION ? ['outline_label'] : [])) $errors[] = 'group_attributes_invalid';
            if ($type === 'core/column' && count($attrs) > 0) $errors[] = 'column_attributes_invalid';
            if (count($node['children']) < 1) $errors[] = 'empty_container';
            foreach ($node['children'] as $child) if (is_array($child) && in_array(($child['type'] ?? null), ['core/list-item','core/column','core/table-row','core/table-cell'], true)) $errors[] = 'internal_node_parent_invalid';
        } elseif ($type !== 'core/list-item' && count($node['children']) > 0) {
            $errors[] = 'children_not_allowed';
        }
        foreach ($node['children'] as $child) $this->validateNode($child, $depth + 1, $type, $ids, $errors, $count, $version);
    }

    private function validateRuns(array $runs, string $blockType, array &$errors): void {
        if (count($runs) > self::MAX_INLINE_RUNS) { $errors[] = 'too_many_inline_runs'; return; }
        $bytes = 0;
        $allowedMarks = ['bold','italic','underline','strike','code'];
        foreach ($runs as $run) {
            if (!is_array($run)) { $errors[] = 'inline_run_invalid'; continue; }
            $keys = array_keys($run); sort($keys);
            if ($keys !== ['href','marks','text'] || !is_string($run['text'] ?? null) || !is_array($run['marks'] ?? null) || !$this->isSequentialList($run['marks'])) {
                $errors[] = 'inline_run_shape_invalid'; continue;
            }
            if ($run['text'] === '') $errors[] = 'inline_run_empty';
            if (!preg_match('//u', $run['text'])) $errors[] = 'inline_text_invalid_utf8';
            $bytes += strlen($run['text']);
            if ($bytes > self::MAX_INLINE_CONTENT_BYTES) $errors[] = 'block_content_too_large';
            $seen = [];
            foreach ($run['marks'] as $mark) {
                if (!is_string($mark) || !in_array($mark, $allowedMarks, true) || isset($seen[$mark])) $errors[] = 'inline_mark_not_allowlisted';
                else $seen[$mark] = true;
            }
            if ($run['href'] !== null && (!$this->isSafeActionUrl($run['href']) || in_array($blockType, ['core/button','core/file','core/audio'], true))) $errors[] = 'inline_link_not_allowlisted';
        }
    }

    private function canonicalRuns(array $runs): array {
        $order = ['bold' => 0, 'italic' => 1, 'underline' => 2, 'strike' => 3, 'code' => 4];
        $out = [];
        foreach ($runs as $run) {
            $marks = $run['marks'];
            usort($marks, static function ($a, $b) use ($order) { return ($order[$a] ?? 99) <=> ($order[$b] ?? 99); });
            $item = ['text' => $run['text'], 'marks' => array_values($marks), 'href' => $run['href']];
            $last = count($out) - 1;
            if ($last >= 0 && $out[$last]['marks'] === $item['marks'] && $out[$last]['href'] === $item['href']) $out[$last]['text'] .= $item['text'];
            else $out[] = $item;
        }
        return $out;
    }

    private function isSafeActionUrl($url): bool {
        if (!is_string($url) || $url === '' || preg_match('/[\x00-\x20\\\\]/', $url) || str_starts_with($url, '//')) return false;
        if (str_starts_with($url, '/')) return true;
        $parts = parse_url($url);
        return is_array($parts) && isset($parts['scheme'], $parts['host']) && in_array(strtolower($parts['scheme']), ['http','https'], true) && !isset($parts['user']) && !isset($parts['pass']);
    }

    /** Strict provider parsing; returns provider + opaque video ID only. */
    public function embedTarget(string $url): ?array {
        if (preg_match('/[\x00-\x20\\\\]/', $url)) return null;
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) return null;
        $host = strtolower((string)($parts['host'] ?? ''));
        $path = (string)($parts['path'] ?? '');
        if (in_array($host, ['youtube.com','www.youtube.com'], true) && $path === '/watch') {
            parse_str((string)($parts['query'] ?? ''), $query);
            $id = (string)($query['v'] ?? '');
            return preg_match('/^[A-Za-z0-9_-]{6,20}$/D', $id) ? ['provider' => 'youtube', 'id' => $id] : null;
        }
        if ($host === 'youtu.be' && preg_match('#^/([A-Za-z0-9_-]{6,20})/?$#D', $path, $m)) return ['provider' => 'youtube', 'id' => $m[1]];
        if (in_array($host, ['vimeo.com','www.vimeo.com'], true) && preg_match('#^/([0-9]{1,20})/?$#D', $path, $m)) return ['provider' => 'vimeo', 'id' => $m[1]];
        if ($host === 'player.vimeo.com' && preg_match('#^/video/([0-9]{1,20})/?$#D', $path, $m)) return ['provider' => 'vimeo', 'id' => $m[1]];
        return null;
    }

    private function isSafeEmbedUrl($url): bool {
        return is_string($url) && $this->embedTarget($url) !== null;
    }

    private function convertBlock(DOMNode $node, string $source, string $path, int $depth): array {
        if ($depth > self::MAX_DEPTH || !$node instanceof DOMElement) throw new RuntimeException('unsupported_node');
        $tag = strtolower($node->tagName); $attrs = []; $content = ''; $children = [];
        if ($tag === 'p' || preg_match('/^h[1-6]$/D', $tag) || $tag === 'blockquote') {
            $this->assertNoAttributes($node, []);
            $content = $this->readInlineText($node);
            $type = $tag === 'p' ? 'core/paragraph' : ($tag === 'blockquote' ? 'core/quote' : 'core/heading');
            if ($type === 'core/heading') $attrs['level'] = (int)substr($tag, 1);
        } elseif ($tag === 'ul' || $tag === 'ol') {
            $this->assertNoAttributes($node, []); $type = 'core/list'; $attrs['ordered'] = $tag === 'ol';
            foreach ($node->childNodes as $i => $child) {
                if ($child instanceof DOMText && trim($child->nodeValue) === '') continue;
                if (!$child instanceof DOMElement || strtolower($child->tagName) !== 'li') throw new RuntimeException('list_child_unsupported');
                $this->assertNoAttributes($child, []);
                $children[] = $this->makeNode('core/list-item', [], [], $this->readInlineText($child), $source, $path . '.i' . $i);
            }
        } elseif ($tag === 'img') {
            $type = 'core/image';
            foreach ($node->attributes as $attr) if (!in_array(strtolower($attr->name), ['src','alt','title'], true)) throw new RuntimeException('image_attribute_unsupported');
            $src = $node->getAttribute('src');
            if (!$this->isSafeImagePath($src)) throw new RuntimeException('image_path_not_allowlisted');
            $attrs = ['src' => $src, 'alt' => $node->getAttribute('alt')];
            if ($node->hasAttribute('title')) $attrs['title'] = $node->getAttribute('title');
        } elseif ($tag === 'hr') {
            $this->assertNoAttributes($node, []); $type = 'core/separator';
        } else {
            throw new RuntimeException('unsupported_tag_' . preg_replace('/[^a-z0-9_-]/', '', $tag));
        }
        return $this->makeNode($type, $attrs, $children, $content, $source, $path);
    }

    private function makeNode(string $type, array $attributes, array $children, string $content, string $source, string $path): array {
        return ['id' => 'f913-' . substr(hash('sha256', $source . "\0" . $path . "\0" . $type), 0, 48), 'type' => $type, 'attributes' => $attributes, 'children' => $children, 'content' => $content];
    }

    private function readInlineText(DOMElement $element): string {
        $out = '';
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMText) { $out .= $child->nodeValue; continue; }
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'br' && $child->attributes->length === 0 && $child->childNodes->length === 0) { $out .= "\n"; continue; }
            throw new RuntimeException('inline_markup_not_supported');
        }
        return $out;
    }

    private function assertNoAttributes(DOMElement $element, array $allowed): void {
        foreach ($element->attributes as $attr) if (!in_array(strtolower($attr->name), $allowed, true)) throw new RuntimeException('attribute_not_supported');
    }

    private function hasVisibleInlineText(array $runs): bool {
        $text = '';
        foreach ($runs as $run) if (is_array($run) && is_string($run['text'] ?? null)) $text .= $run['text'];
        if (!preg_match('//u', $text)) return false;
        return preg_match('/^[\s\p{Z}\p{C}]*$/u', $text) !== 1;
    }

    private function isSafeAudioPath($src): bool {
        if (!is_string($src) || !preg_match('#^view/audio/blog/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$#D', $src) || str_contains($src, '..')) return false;
        return in_array(strtolower(pathinfo($src, PATHINFO_EXTENSION)), ['mp3','ogg','wav'], true);
    }

    private function isSafeDownloadPath($src): bool {
        if (!is_string($src) || !preg_match('#^view/download/blog/(?:[A-Za-z0-9][A-Za-z0-9._-]{0,179})$#D', $src) || str_contains($src, '..') || preg_match('/[\x00-\x20\x7F]/', $src) || strpbrk($src, '?#') !== false) return false;
        $extension = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        return in_array($extension, ['pdf','docx','xlsx','pptx','odt','ods','csv','txt'], true);
    }

    private function isSafeImagePath($src): bool {
        if (!is_string($src) || $src === '' || !preg_match('#^/?(?:image|view/image/blog)/[A-Za-z0-9._~/-]+$#D', $src) || preg_match('/[\x00-\x20\\\\]/', $src)) return false;
        $parts = parse_url($src);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['query']) || isset($parts['fragment'])) return false;
        $path = ltrim((string)($parts['path'] ?? ''), '/');
        if (strpos($path, 'image/') !== 0 && strpos($path, 'view/image/blog/') !== 0) return false;
        foreach (explode('/', $path) as $segment) if ($segment === '..' || $segment === '.' || $segment === '') return false;
        return true;
    }
}
