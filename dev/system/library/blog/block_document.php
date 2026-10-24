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
        $canonical = ['format_version' => self::FORMAT_VERSION, 'blocks' => []];
        foreach ($document['blocks'] as $node) $canonical['blocks'][] = $this->canonicalNode($node);
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
        if (($document['format_version'] ?? null) !== self::FORMAT_VERSION) $errors[] = 'unsupported_format_version';
        if (!isset($document['blocks']) || !is_array($document['blocks']) || !$this->isSequentialList($document['blocks']) || count($document['blocks']) > self::MAX_BLOCKS) $errors[] = 'blocks_invalid';
        $ids = []; $count = 0;
        if (isset($document['blocks']) && is_array($document['blocks'])) {
            foreach ($document['blocks'] as $node) $this->validateNode($node, 0, null, $ids, $errors, $count);
        }
        return ['valid' => !$errors, 'errors' => array_values(array_unique($errors))];
    }

    private function isSequentialList(array $value): bool {
        $expected = 0;
        foreach ($value as $key => $_) if ($key !== $expected++) return false;
        return true;
    }

    private function canonicalNode(array $node): array {
        $attributes = $node['attributes'];
        ksort($attributes, SORT_STRING);
        $children = [];
        foreach ($node['children'] as $child) $children[] = $this->canonicalNode($child);
        return ['id' => $node['id'], 'type' => $node['type'], 'attributes' => $attributes, 'children' => $children, 'content' => $node['content']];
    }

    private function validateNode($node, int $depth, ?string $parentType, array &$ids, array &$errors, int &$count): void {
        if ($depth > self::MAX_DEPTH || !is_array($node)) { $errors[] = 'node_depth_or_type_invalid'; return; }
        $count++;
        if ($count > self::MAX_TOTAL_NODES) { $errors[] = 'too_many_nodes'; return; }
        $keys = array_keys($node); sort($keys);
        if ($keys !== ['attributes', 'children', 'content', 'id', 'type']) { $errors[] = 'node_shape_invalid'; return; }
        if (!is_string($node['id']) || !preg_match('/^[A-Za-z0-9_-]{1,96}$/D', $node['id']) || isset($ids[$node['id']])) $errors[] = 'node_id_invalid_or_duplicate';
        else $ids[$node['id']] = true;
        $types = ['core/paragraph','core/heading','core/list','core/list-item','core/quote','core/image','core/video_embed','core/button','core/separator','core/group','core/columns','core/column'];
        if (!is_string($node['type']) || !in_array($node['type'], $types, true)) { $errors[] = 'unknown_block_type'; return; }
        if (!is_array($node['attributes']) || !is_array($node['children']) || !$this->isSequentialList($node['children']) || !is_string($node['content'])) { $errors[] = 'node_value_type_invalid'; return; }
        $type = $node['type']; $attrs = $node['attributes'];
        $allowedAttrs = [
            'core/paragraph' => ['align'], 'core/heading' => ['level'], 'core/list' => ['ordered'],
            'core/list-item' => [], 'core/quote' => [], 'core/image' => ['src','alt','title'],
            'core/video_embed' => ['url'], 'core/button' => ['url'], 'core/separator' => [],
            'core/group' => [], 'core/columns' => ['count'], 'core/column' => []
        ];
        if (array_diff(array_keys($attrs), $allowedAttrs[$type])) $errors[] = 'attributes_not_allowlisted';
        if ($type === 'core/paragraph' && isset($attrs['align']) && !in_array($attrs['align'], ['left','center','right'], true)) $errors[] = 'alignment_invalid';
        if ($type === 'core/heading' && (!isset($attrs['level']) || !is_int($attrs['level']) || $attrs['level'] < 1 || $attrs['level'] > 6)) $errors[] = 'heading_level_invalid';
        if ($type === 'core/list' && (!isset($attrs['ordered']) || !is_bool($attrs['ordered']))) $errors[] = 'list_order_invalid';
        if ($type === 'core/image' && (!isset($attrs['src']) || !$this->isSafeImagePath($attrs['src']) || !isset($attrs['alt']) || !is_string($attrs['alt']) || (isset($attrs['title']) && !is_string($attrs['title'])))) $errors[] = 'image_attributes_invalid';
        if ($type === 'core/video_embed' && (!isset($attrs['url']) || !$this->isSafeEmbedUrl($attrs['url']))) $errors[] = 'embed_url_invalid';
        if ($type === 'core/button' && (!isset($attrs['url']) || !$this->isSafeActionUrl($attrs['url']))) $errors[] = 'button_url_invalid';
        if ($type === 'core/columns' && (!isset($attrs['count']) || !is_int($attrs['count']) || !in_array($attrs['count'], [2,3], true) || count($node['children']) !== $attrs['count'])) $errors[] = 'columns_count_invalid';
        if (strlen($node['content']) > 262144) $errors[] = 'block_content_too_large';
        if (in_array($type, ['core/list','core/image','core/video_embed','core/separator','core/group','core/columns','core/column'], true) && $node['content'] !== '') $errors[] = 'content_not_allowed';
        if ($type === 'core/list-item' && ($parentType !== 'core/list' || count($node['children']) > 0)) $errors[] = 'list_item_parent_invalid';
        if ($type === 'core/column' && $parentType !== 'core/columns') $errors[] = 'column_parent_invalid';
        if ($type === 'core/list') {
            if (count($node['children']) < 1) $errors[] = 'empty_list';
            foreach ($node['children'] as $child) if (!is_array($child) || ($child['type'] ?? null) !== 'core/list-item') $errors[] = 'list_child_invalid';
        } elseif ($type === 'core/columns') {
            foreach ($node['children'] as $child) if (!is_array($child) || ($child['type'] ?? null) !== 'core/column') $errors[] = 'columns_child_invalid';
        } elseif ($type === 'core/group' || $type === 'core/column') {
            if (count($node['children']) < 1) $errors[] = 'empty_container';
            foreach ($node['children'] as $child) if (is_array($child) && in_array(($child['type'] ?? null), ['core/list-item','core/column'], true)) $errors[] = 'internal_node_parent_invalid';
        } elseif ($type !== 'core/list-item' && count($node['children']) > 0) {
            $errors[] = 'children_not_allowed';
        }
        foreach ($node['children'] as $child) $this->validateNode($child, $depth + 1, $type, $ids, $errors, $count);
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
