<?php
/**
 * @package ReamurCMS
 * @license https://opensource.org/licenses/GPL-3.0
 * Biblioteca privada BDRE por administrador (F9-25). Nunca aceita owner ID do cliente.
 */
namespace Reamur\Admin\Controller\Cms;

class Block_pattern extends \Reamur\System\Engine\Controller {
    private ?array $jsonPost = null;
    private const MAX_DOCUMENT_BYTES = 4194304;
    private const MAX_NAME_CHARS = 80;
    private const ALLOWED_ROOT_TYPES = [
        'core/paragraph','core/heading','core/list','core/quote','core/callout','core/details','core/spacer','core/code',
        'core/image','core/video_embed','core/button','core/separator','core/group','core/columns','core/gallery',
        'core/cover','core/table','core/file','core/audio','core/video_file','core/threejs_thumb'
    ];

    public function listPages(): void { $this->listPatternsFor('pages'); }
    public function listBlog(): void { $this->listPatternsFor('blog'); }
    public function getPages(): void { $this->getPatternFor('pages'); }
    public function getBlog(): void { $this->getPatternFor('blog'); }
    public function savePages(): void { $this->savePatternFor('pages'); }
    public function saveBlog(): void { $this->savePatternFor('blog'); }
    public function renamePages(): void { $this->renamePatternFor('pages'); }
    public function renameBlog(): void { $this->renamePatternFor('blog'); }
    public function deletePages(): void { $this->deletePatternFor('pages'); }
    public function deleteBlog(): void { $this->deletePatternFor('blog'); }

    private function listPatternsFor(string $context): void {
        if (!$this->authorize('read', $context)) return;
        $this->load->model('cms/block_pattern');
        if (!$this->model_cms_block_pattern->tableExists()) { $this->json(['success' => true, 'patterns' => [], 'storage_available' => false]); return; }
        $owner = $this->user->getUserId();
        $patterns = [];
        foreach ($this->model_cms_block_pattern->getPatterns($owner) as $row) {
            $row = (array)$row;
            $patterns[] = [
                'pattern_id' => (int)$row['pattern_id'], 'name' => (string)$row['name'],
                'source_language' => (string)$row['source_language'], 'root_type' => (string)$row['root_type'],
                'date_modified' => (string)$row['date_modified']
            ];
        }
        $this->json(['success' => true, 'patterns' => $patterns, 'storage_available' => true]);
    }

    private function getPatternFor(string $context): void {
        if (!$this->authorize('read', $context)) return;
        $patternId = $this->inputId('pattern_id');
        $this->load->model('cms/block_pattern');
        $row = $this->model_cms_block_pattern->getPattern($patternId, $this->user->getUserId());
        if (!$row) { $this->json(['success' => false, 'error' => 'pattern_not_found'], 404); return; }
        $json = (string)$row['content_json'];
        $validation = $this->validator($context)->deserializeDocument($json);
        if (!$validation['valid'] || !$this->validStandaloneDocument($validation['document']) || $this->containsCustomJs($validation['document']['blocks'][0])) {
            $this->json(['success' => false, 'error' => 'pattern_incompatible_or_invalid'], 422); return;
        }
        if ((int)$row['format_version'] !== (int)$validation['document']['format_version'] || (string)$row['root_type'] !== (string)$validation['document']['blocks'][0]['type']) {
            $this->json(['success' => false, 'error' => 'pattern_version_mismatch'], 422); return;
        }
        $canonical = $this->validator($context)->serializeDocument($validation['document']);
        $this->json(['success' => true, 'pattern' => [
            'pattern_id' => (int)$row['pattern_id'], 'name' => (string)$row['name'],
            'source_language' => (string)$row['source_language'], 'root_type' => (string)$row['root_type'],
            'document_json' => $canonical
        ]]);
    }

    private function savePatternFor(string $context): void {
        if (!$this->authorize('write', $context)) return;
        if (!$this->csrfValid()) { $this->json(['success' => false, 'error' => 'invalid_csrf'], 403); return; }
        $name = $this->cleanName($this->postValue('name'));
        $documentJson = $this->postValue('document_json');
        $sourceLanguage = $this->postValue('source_language') ?? '';
        if ($name === null) { $this->json(['success' => false, 'error' => 'invalid_name'], 422); return; }
        if (!is_string($documentJson) || strlen($documentJson) > self::MAX_DOCUMENT_BYTES) { $this->json(['success' => false, 'error' => 'document_too_large_or_invalid'], 413); return; }
        if (!is_string($sourceLanguage) || strlen($sourceLanguage) > 8 || !preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/iD', $sourceLanguage)) { $sourceLanguage = ''; }
        $validator = $this->validator($context);
        $decoded = $validator->deserializeDocument($documentJson);
        if (!$decoded['valid'] || !$this->validStandaloneDocument($decoded['document'])) {
            $this->json(['success' => false, 'error' => 'invalid_or_non_standalone_document'], 422); return;
        }
        $node = $decoded['document']['blocks'][0];
        if ($this->containsCustomJs($node)) { $this->json(['success' => false, 'error' => 'custom_js_not_allowed'], 422); return; }
        $rootType = (string)$node['type'];
        if (!in_array($rootType, self::ALLOWED_ROOT_TYPES, true)) { $this->json(['success' => false, 'error' => 'unsupported_pattern_root'], 422); return; }
        try { $canonicalJson = $validator->serializeDocument($decoded['document']); }
        catch (\Throwable $exception) { $this->json(['success' => false, 'error' => 'invalid_or_non_standalone_document'], 422); return; }
        if (strlen($canonicalJson) > self::MAX_DOCUMENT_BYTES) { $this->json(['success' => false, 'error' => 'document_too_large_or_invalid'], 413); return; }
        $this->load->model('cms/block_pattern');
        if (!$this->model_cms_block_pattern->tableExists()) { $this->json(['success' => false, 'error' => 'storage_unavailable'], 503); return; }
        $owner = $this->user->getUserId();
        if ($this->model_cms_block_pattern->getTotalPatterns($owner) >= \Reamur\Admin\Model\Cms\Block_pattern::MAX_PATTERNS_PER_USER) {
            $this->json(['success' => false, 'error' => 'pattern_limit_reached'], 409); return;
        }
        if ($this->model_cms_block_pattern->nameTaken($owner, $name)) { $this->json(['success' => false, 'error' => 'name_already_used'], 409); return; }
        try {
            $id = $this->model_cms_block_pattern->addPattern($owner, $name, strtolower($sourceLanguage), $rootType, (int)$decoded['document']['format_version'], $canonicalJson);
        } catch (\Throwable $exception) { $id = 0; }
        if ($id < 1) { $this->json(['success' => false, 'error' => 'save_failed_or_name_taken'], 409); return; }
        $this->json(['success' => true, 'pattern_id' => $id]);
    }

    private function renamePatternFor(string $context): void {
        if (!$this->authorize('write', $context)) return;
        if (!$this->csrfValid()) { $this->json(['success' => false, 'error' => 'invalid_csrf'], 403); return; }
        $name = $this->cleanName($this->postValue('name'));
        $patternId = $this->inputId('pattern_id', true);
        if ($name === null || $patternId < 1) { $this->json(['success' => false, 'error' => 'invalid_request'], 422); return; }
        $this->load->model('cms/block_pattern');
        $owner = $this->user->getUserId();
        if (!$this->model_cms_block_pattern->getPattern($patternId, $owner)) { $this->json(['success' => false, 'error' => 'pattern_not_found'], 404); return; }
        if ($this->model_cms_block_pattern->nameTaken($owner, $name, $patternId)) { $this->json(['success' => false, 'error' => 'name_already_used'], 409); return; }
        try { $ok = $this->model_cms_block_pattern->renamePattern($patternId, $owner, $name); }
        catch (\Throwable $exception) { $ok = false; }
        $this->json(['success' => $ok, 'error' => $ok ? '' : 'rename_failed']);
    }

    private function deletePatternFor(string $context): void {
        if (!$this->authorize('write', $context)) return;
        if (!$this->csrfValid()) { $this->json(['success' => false, 'error' => 'invalid_csrf'], 403); return; }
        $patternId = $this->inputId('pattern_id', true);
        if ($patternId < 1) { $this->json(['success' => false, 'error' => 'invalid_request'], 422); return; }
        $this->load->model('cms/block_pattern');
        $owner = $this->user->getUserId();
        if (!$this->model_cms_block_pattern->getPattern($patternId, $owner)) { $this->json(['success' => false, 'error' => 'pattern_not_found'], 404); return; }
        try { $ok = $this->model_cms_block_pattern->deletePattern($patternId, $owner); }
        catch (\Throwable $exception) { $ok = false; }
        $this->json(['success' => $ok, 'error' => $ok ? '' : 'delete_failed']);
    }

    private function authorize(string $operation, string $context): bool {
        $contextRoute = $context === 'blog' ? 'blog/blog' : 'cms/page';
        $allowed = $this->user->hasPermission('access', 'cms/block_pattern')
            && $this->user->hasPermission('modify', $contextRoute);
        if ($operation === 'write') $allowed = $allowed && $this->user->hasPermission('modify', 'cms/block_pattern');
        if (!$allowed) { $this->json(['success' => false, 'error' => 'permission_denied'], 403); return false; }
        return true;
    }

    private function inputId(string $key, bool $postOnly = false): int {
        $source = $postOnly ? ['value' => $this->postValue($key)] : ($this->request->get ?? []);
        $value = $postOnly ? ($source['value'] ?? 0) : ($source[$key] ?? 0);
        return is_scalar($value) && ctype_digit((string)$value) ? (int)$value : 0;
    }

    private function cleanName($value): ?string {
        if (!is_string($value) || !preg_match('//u', $value)) return null;
        $name = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($name === '' || mb_strlen($name, 'UTF-8') > self::MAX_NAME_CHARS || preg_match('/[\x00-\x1F\x7F]/u', $name)) return null;
        return $name;
    }

    private function validator(string $context): \Reamur\System\Library\Blog\BlockDocument {
        return new \Reamur\System\Library\Blog\BlockDocument($context === 'pages');
    }

    private function validStandaloneDocument($document): bool {
        if (!is_array($document) || !isset($document['blocks']) || count($document['blocks']) !== 1 || !is_array($document['blocks'][0])) return false;
        return in_array((string)($document['blocks'][0]['type'] ?? ''), self::ALLOWED_ROOT_TYPES, true);
    }

    private function containsCustomJs(array $node): bool {
        if (isset($node['attributes']) && is_array($node['attributes']) && array_key_exists('custom_js', $node['attributes'])) return true;
        foreach (($node['children'] ?? []) as $child) if (is_array($child) && $this->containsCustomJs($child)) return true;
        return false;
    }

    private function postValue(string $key) {
        if (isset($this->request->post[$key])) return $this->request->post[$key];
        if ($this->jsonPost === null) {
            $raw = file_get_contents('php://input');
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $this->jsonPost = is_array($decoded) ? $decoded : [];
        }
        return $this->jsonPost[$key] ?? null;
    }

    private function csrfValid(): bool {
        $token = $this->postValue('csrf_token');
        return \Reamur\System\Library\Security\Csrf::check($this->session, is_string($token) ? $token : '');
    }

    private function json(array $payload, int $status = 200): void {
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->addHeader('Cache-Control: no-store, private');
        $this->response->setOutput(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
    }
}
