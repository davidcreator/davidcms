<?php
/** CMS Pages image library. No entity field, database access, delete, or folders. */
namespace Reamur\Admin\Model\Cms;

class PageMedia extends \Reamur\System\Engine\Model {
    private const RELATIVE_ROOT = 'view/image/cms/page/';
    private const MAX_BYTES = 2097152;
    private const PAGE_SIZE = 48;
    private const MIME_EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    /** @return array<string, mixed> */
    public function listImages(string $search, int $page): array {
        $root = $this->rootPath();
        $catalog = $this->catalogRoot();
        foreach ([$catalog, $catalog . DIRECTORY_SEPARATOR . 'view', $catalog . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR . 'image', $catalog . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . 'cms', $root] as $component) {
            if (is_link($component)) return ['success' => false];
        }
        if (!is_dir($root)) return ['success' => true, 'files' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
        $safeRoot = realpath($root);
        if ($safeRoot === false || !$this->rootIsContained($safeRoot)) return ['success' => false];
        $items = [];
        $handle = @opendir($safeRoot);
        if ($handle === false) return ['success' => false];
        while (($name = readdir($handle)) !== false) {
            if (!$this->safeFilename($name) || !is_file($safeRoot . DIRECTORY_SEPARATOR . $name) || is_link($safeRoot . DIRECTORY_SEPARATOR . $name)) continue;
            if ($search !== '' && stripos($name, substr($search, 0, 128)) === false) continue;
            if (!$this->isAllowedImage($safeRoot . DIRECTORY_SEPARATOR . $name)) continue;
            $items[] = ['name' => $name, 'path' => self::RELATIVE_ROOT . $name];
        }
        closedir($handle);
        sort($items, SORT_NATURAL | SORT_FLAG_CASE);
        $total = count($items); $pages = max(1, (int)ceil($total / self::PAGE_SIZE)); $page = min(max(1, $page), $pages);
        return ['success' => true, 'files' => array_slice($items, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE), 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    /** @param array<string, mixed> $file @return array<string, mixed> */
    public function storeImage(array $file): array {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) return ['success' => false, 'error' => 'no_file'];
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) return ['success' => false, 'error' => 'size'];
        if ($error !== UPLOAD_ERR_OK || !isset($file['tmp_name'], $file['size']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return ['success' => false, 'error' => 'type'];
        $actual = @filesize($file['tmp_name']);
        if (!is_int($actual) || $actual < 1 || $actual > self::MAX_BYTES || (int)$file['size'] !== $actual) return ['success' => false, 'error' => 'size'];
        $info = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = $info ? @finfo_file($info, $file['tmp_name']) : false;
        if ($info) finfo_close($info);
        if (!is_string($mime) || !isset(self::MIME_EXT[$mime])) return ['success' => false, 'error' => 'type'];
        $realRoot = $this->ensureRoot();
        if ($realRoot === '') return ['success' => false, 'error' => 'root'];
        // MediaUpload is reused only after the fixed Pages root and all ancestors pass symlink/containment checks.
        $catalog = $this->catalogRoot();
        $uploader = new \Reamur\System\Library\MediaUpload(rtrim($catalog, '/\\') . DIRECTORY_SEPARATOR);
        $result = $uploader->store($file, self::RELATIVE_ROOT);
        if (empty($result['ok']) || !is_string($result['path'] ?? null)) return ['success' => false, 'error' => ($result['error'] ?? '') === 'size' ? 'size' : (($result['error'] ?? '') === 'type' ? 'type' : 'upload')];
        $path = $result['path'];
        if (!preg_match('#^view/image/cms/page/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$#D', $path)) return ['success' => false, 'error' => 'upload'];
        $absolute = $realRoot . DIRECTORY_SEPARATOR . substr($path, strlen(self::RELATIVE_ROOT));
        if (is_link($absolute) || !is_file($absolute) || realpath($absolute) === false || dirname(realpath($absolute)) !== $realRoot) return ['success' => false, 'error' => 'upload'];
        return ['success' => true, 'path' => $path, 'name' => basename($path)];
    }

    private function catalogRoot(): string { return rtrim(dirname(DIR_APPLICATION), '/\\') . DIRECTORY_SEPARATOR . 'catalog'; }
    private function rootPath(): string { return $this->catalogRoot() . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . 'cms' . DIRECTORY_SEPARATOR . 'page'; }

    private function rootIsContained(string $realRoot): bool {
        $catalog = realpath($this->catalogRoot());
        if ($catalog === false || is_link($this->catalogRoot())) return false;
        $prefix = rtrim($catalog, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($realRoot . DIRECTORY_SEPARATOR, $prefix);
    }

    private function ensureRoot(): string {
        $catalog = $this->catalogRoot();
        $view = $catalog . DIRECTORY_SEPARATOR . 'view'; $image = $view . DIRECTORY_SEPARATOR . 'image';
        $cms = $image . DIRECTORY_SEPARATOR . 'cms'; $root = $cms . DIRECTORY_SEPARATOR . 'page';
        foreach ([$catalog, $view, $image, $cms, $root] as $path) if (is_link($path)) return '';
        if (!is_dir($catalog) || !is_dir($view) || !is_dir($image)) return '';
        foreach ([$cms, $root] as $path) if (!is_dir($path) && !@mkdir($path, 0755)) return '';
        foreach ([$cms, $root] as $path) {
            $guard = $path . DIRECTORY_SEPARATOR . 'index.html';
            if (is_link($guard)) return '';
            if (!is_file($guard) && @file_put_contents($guard, '') === false) return '';
        }
        foreach ([$catalog, $view, $image, $cms, $root] as $path) if (is_link($path)) return '';
        $realRoot = realpath($root);
        return $realRoot !== false && $this->rootIsContained($realRoot) ? $realRoot : '';
    }

    private function safeFilename(string $name): bool {
        return $name !== '.' && $name !== '..' && (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,179}$/D', $name) && !str_contains($name, '..');
    }

    private function isAllowedImage(string $path): bool {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedExt = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
        if (!isset($allowedExt[$ext])) return false;
        $finfo = @finfo_open(FILEINFO_MIME_TYPE); $mime = $finfo ? @finfo_file($finfo, $path) : false;
        if ($finfo) finfo_close($finfo);
        return $mime === $allowedExt[$ext];
    }
}
