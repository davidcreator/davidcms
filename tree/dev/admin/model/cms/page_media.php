<?php
/** CMS Pages media library. Dedicated filesystem roots; no DB, folders, or deletion. */
namespace Reamur\Admin\Model\Cms;

class PageMedia extends \Reamur\System\Engine\Model {
    private const PAGE_SIZE = 48;
    private const SPECS = [
        'image' => ['root' => 'view/image/cms/page/', 'max' => 2097152, 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'], 'gif' => ['image/gif']]],
        'audio' => ['root' => 'view/audio/cms/page/', 'max' => 20971520, 'types' => ['mp3' => ['audio/mpeg'], 'ogg' => ['audio/ogg', 'application/ogg'], 'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave']]],
        'video' => ['root' => 'view/video/cms/page/', 'max' => 104857600, 'types' => ['mp4' => ['video/mp4'], 'webm' => ['video/webm'], 'ogv' => ['video/ogg']]],
        'download' => ['root' => 'view/download/cms/page/', 'max' => 2097152, 'types' => ['pdf' => ['application/pdf'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], 'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'], 'odt' => ['application/vnd.oasis.opendocument.text'], 'ods' => ['application/vnd.oasis.opendocument.spreadsheet'], 'csv' => ['text/plain', 'text/csv', 'application/csv'], 'txt' => ['text/plain']]],
    ];

    /** @return array<string,mixed> */
    public function listPageMedia(string $kind, string $search, int $page): array {
        if (!isset(self::SPECS[$kind])) return ['success' => false];
        $root = $this->rootPath($kind); $parts = $this->components($kind);
        foreach ($parts as $path) if (is_link($path)) return ['success' => false];
        if (!is_dir($root)) return ['success' => true, 'files' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
        $safeRoot = realpath($root);
        if ($safeRoot === false || !$this->contained($safeRoot)) return ['success' => false];
        $handle = @opendir($safeRoot); if ($handle === false) return ['success' => false];
        $items = []; $needle = substr(trim($search), 0, 128);
        while (($name = readdir($handle)) !== false) {
            $path = $safeRoot . DIRECTORY_SEPARATOR . $name;
            if (!$this->safeName($name) || is_link($path) || !is_file($path) || ($needle !== '' && stripos($name, $needle) === false)) continue;
            $size = @filesize($path); if (!is_int($size) || $size < 1 || $size > self::SPECS[$kind]['max'] || !$this->allowed($path, $kind)) continue;
            $items[] = ['name' => $name, 'path' => self::SPECS[$kind]['root'] . $name, 'size' => $size];
        }
        closedir($handle); sort($items, SORT_NATURAL | SORT_FLAG_CASE);
        $total = count($items); $pages = max(1, (int)ceil($total / self::PAGE_SIZE)); $page = min(max(1, $page), $pages);
        return ['success' => true, 'files' => array_slice($items, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE), 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    /** @param array<string,mixed> $file @return array<string,mixed> */
    public function storePageMedia(string $kind, array $file): array {
        if (!isset(self::SPECS[$kind])) return ['success' => false, 'error' => 'type'];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) return ['success' => false, 'error' => 'no_file'];
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) return ['success' => false, 'error' => 'size'];
        if ($error !== UPLOAD_ERR_OK || !isset($file['tmp_name'], $file['size'], $file['name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return ['success' => false, 'error' => 'type'];
        $actual = @filesize($file['tmp_name']);
        if (!is_int($actual) || $actual < 1 || $actual > self::SPECS[$kind]['max'] || (int)$file['size'] !== $actual) return ['success' => false, 'error' => 'size'];
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!isset(self::SPECS[$kind]['types'][$ext])) return ['success' => false, 'error' => 'type'];
        $finfo = @finfo_open(FILEINFO_MIME_TYPE); $mime = $finfo ? @finfo_file($finfo, $file['tmp_name']) : false; if ($finfo) finfo_close($finfo);
        if (!is_string($mime) || !in_array($mime, self::SPECS[$kind]['types'][$ext], true)) return ['success' => false, 'error' => 'type'];
        $safeRoot = $this->ensureRoot($kind); if ($safeRoot === '') return ['success' => false, 'error' => 'root'];
        try { $name = bin2hex(random_bytes(16)) . '.' . $ext; } catch (\Throwable $e) { return ['success' => false, 'error' => 'upload']; }
        $destination = $safeRoot . DIRECTORY_SEPARATOR . $name;
        if (file_exists($destination) || is_link($destination) || !@move_uploaded_file($file['tmp_name'], $destination)) return ['success' => false, 'error' => 'upload'];
        @chmod($destination, 0644);
        $real = realpath($destination);
        if ($real === false || is_link($destination) || dirname($real) !== $safeRoot || !$this->allowed($real, $kind) || @filesize($real) !== $actual) {
            if ($real !== false && dirname($real) === $safeRoot && !is_link($destination)) @unlink($real);
            return ['success' => false, 'error' => 'upload'];
        }
        return ['success' => true, 'path' => self::SPECS[$kind]['root'] . $name, 'name' => $name, 'size' => $actual];
    }

    /** Backward-compatible image API for existing Page controller callers. */
    public function listImages(string $search, int $page): array { return $this->listPageMedia('image', $search, $page); }
    public function storeImage(array $file): array { return $this->storePageMedia('image', $file); }

    private function catalogRoot(): string { return rtrim(dirname(DIR_APPLICATION), '/\\') . DIRECTORY_SEPARATOR . 'catalog'; }
    private function rootPath(string $kind): string { return $this->catalogRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, rtrim(self::SPECS[$kind]['root'], '/')); }
    private function components(string $kind): array {
        $root = $this->rootPath($kind); $catalog = $this->catalogRoot(); $view = $catalog . DIRECTORY_SEPARATOR . 'view'; $segments = explode('/', self::SPECS[$kind]['root']); $type = $view . DIRECTORY_SEPARATOR . ($segments[1] ?? ''); $cms = $type . DIRECTORY_SEPARATOR . 'cms';
        return [$catalog, $view, $type, $cms, $root];
    }
    private function contained(string $root): bool { $catalog = realpath($this->catalogRoot()); return $catalog !== false && !is_link($this->catalogRoot()) && str_starts_with($root . DIRECTORY_SEPARATOR, rtrim($catalog, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR); }
    private function ensureRoot(string $kind): string {
        $paths = $this->components($kind); $catalog = $paths[0];
        if (!is_dir($catalog) || !is_dir($paths[1])) return '';
        foreach ($paths as $path) if (is_link($path)) return '';
        foreach (array_slice($paths, 2) as $path) if (!is_dir($path) && !@mkdir($path, 0755)) return '';
        foreach (array_slice($paths, 2) as $path) {
            $guard = $path . DIRECTORY_SEPARATOR . 'index.html'; if (is_link($guard)) return '';
            if (!is_file($guard) && @file_put_contents($guard, '') === false) return '';
        }
        foreach ($paths as $path) if (is_link($path)) return '';
        $real = realpath(end($paths)); return $real !== false && $this->contained($real) ? $real : '';
    }
    private function safeName(string $name): bool { return $name !== '.' && $name !== '..' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,179}$/D', $name) === 1 && !str_contains($name, '..'); }
    private function allowed(string $path, string $kind): bool {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); if (!isset(self::SPECS[$kind]['types'][$ext])) return false;
        $finfo = @finfo_open(FILEINFO_MIME_TYPE); $mime = $finfo ? @finfo_file($finfo, $path) : false; if ($finfo) finfo_close($finfo);
        return is_string($mime) && in_array($mime, self::SPECS[$kind]['types'][$ext], true);
    }
}
