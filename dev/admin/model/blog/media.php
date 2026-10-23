<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 */
namespace Reamur\Admin\Model\Blog;

/** Biblioteca de mídia do blog: sistema de arquivos, sem tabela/schema próprio. */
class Media extends \Reamur\System\Engine\Model {

    private const ROOT_RELATIVE = 'view/image/blog/';
    private const PAGE_LIMIT = 24;
    private const MAX_FOLDER_DEPTH = 8;
    private const IMAGE_MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    /**
     * Lista pastas e imagens da pasta atual; busca e paginação aplicam-se aos arquivos.
     *
     * @return array<string, mixed>
     */
    public function getMediaPage(string $folder, string $search, int $page, int $limit = self::PAGE_LIMIT): array {
        $normalized = $this->normalizeFolder($folder);

        if ($normalized === null) {
            return ['ok' => false, 'error' => 'invalid_folder'];
        }

        $root = $this->rootPath();

        if (!is_dir($root)) {
            return $normalized === ''
                ? ['ok' => true, 'folder' => '', 'folders' => [], 'files' => [], 'total' => 0, 'page' => 1, 'pages' => 1]
                : ['ok' => false, 'error' => 'folder_not_found'];
        }

        $directory = $this->resolveDirectory($normalized);

        if ($directory === '') {
            return ['ok' => false, 'error' => 'folder_not_found'];
        }

        $folders = [];
        $files   = [];
        $usedImages = $this->referencedImagePaths();

        if ($usedImages === null) {
            return ['ok' => false, 'error' => 'reference_check_failed'];
        }

        $search  = trim(mb_substr($search, 0, 128));
        $entries = scandir($directory);

        if ($entries === false) {
            return ['ok' => false, 'error' => 'folder_unreadable'];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (is_link($path)) {
                continue;
            }

            if (is_dir($path)) {
                $depth = $normalized === '' ? 0 : count(explode('/', $normalized));
                if ($depth >= self::MAX_FOLDER_DEPTH || !$this->isSafeFolderSegment($entry)) {
                    continue;
                }

                if ($search === '' || mb_stripos($entry, $search) !== false) {
                    $relativeFolder = $normalized === '' ? $entry : $normalized . '/' . $entry;
                    $folders[] = [
                        'name' => $entry,
                        'folder' => $relativeFolder,
                        'empty' => $this->isEmptyDirectory($path),
                    ];
                }

                continue;
            }

            if (!is_file($path) || !$this->isAllowedImage($path, $entry)) {
                continue;
            }

            if ($search !== '' && mb_stripos($entry, $search) === false) {
                continue;
            }

            $relativePath = self::ROOT_RELATIVE . ($normalized !== '' ? $normalized . '/' : '') . $entry;
            $size = filesize($path);
            $modified = filemtime($path);

            $files[] = [
                'name' => $entry,
                'path' => $relativePath,
                'size' => $size === false ? 0 : (int)$size,
                'modified' => $modified === false ? 0 : (int)$modified,
                'referenced' => isset($usedImages[$relativePath]),
            ];
        }

        usort($folders, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        usort($files, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        $total = count($files);
        $limit = min(48, max(1, $limit));
        $pages = max(1, (int)ceil($total / $limit));
        $page  = min($pages, max(1, $page));

        return [
            'ok' => true,
            'folder' => $normalized,
            'folders' => $folders,
            'files' => array_slice($files, ($page - 1) * $limit, $limit),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * Retorna o target relativo para MediaUpload; subpastas precisam existir previamente.
     */
    public function getUploadTarget(string $folder): string {
        $normalized = $this->normalizeFolder($folder);

        if ($normalized === null || $this->rootHasSymlink()) {
            return '';
        }

        if ($normalized === '') {
            $imageRoot = dirname($this->rootPath());

            if (!is_dir($imageRoot) || !is_writable($imageRoot)) {
                return '';
            }

            $root = $this->rootPath();
            if (!is_dir($root) && !mkdir($root, 0755, false) && !is_dir($root)) {
                return '';
            }

            if (!$this->writeGuardFile($root)) {
                return '';
            }

            return self::ROOT_RELATIVE;
        }

        return $this->resolveDirectory($normalized) !== ''
            ? self::ROOT_RELATIVE . $normalized . '/'
            : '';
    }

    /** Cria uma subpasta direta, sem permitir traversal ou sobrescrita. */
    public function createFolder(string $parent, string $name): string {
        $normalizedParent = $this->normalizeFolder($parent);
        $name = trim($name);

        if ($normalizedParent === null
            || ($normalizedParent !== '' && count(explode('/', $normalizedParent)) >= self::MAX_FOLDER_DEPTH)) {
            return 'invalid_folder';
        }

        if (!$this->isSafeNewFolderName($name)) {
            return 'invalid_name';
        }

        $root = $this->rootPath();
        $imageRoot = dirname($root);

        if ($this->rootHasSymlink() || !is_dir($imageRoot) || !is_writable($imageRoot)) {
            return 'root_unavailable';
        }

        if (!is_dir($root)) {
            if (!mkdir($root, 0755, false) && !is_dir($root)) {
                return 'create_failed';
            }
        }

        if (!$this->writeGuardFile($root)) {
            return 'create_failed';
        }

        $parentPath = $normalizedParent === '' ? $this->resolveDirectory('') : $this->resolveDirectory($normalizedParent);

        if ($parentPath === '') {
            return 'folder_not_found';
        }

        if (!is_writable($parentPath)) {
            return 'permission';
        }

        $target = $parentPath . DIRECTORY_SEPARATOR . $name;

        if (file_exists($target) || is_link($target)) {
            return 'exists';
        }

        if (!mkdir($target, 0755, false)) {
            return 'create_failed';
        }

        if (!$this->writeGuardFile($target)) {
            $entries = scandir($target);
            if ($entries !== false && count(array_diff($entries, ['.', '..'])) === 0) {
                rmdir($target);
            }
            return 'create_failed';
        }

        return 'ok';
    }

    /**
     * Exclui uma imagem sem referência ou uma pasta realmente vazia.
     *
     * @param 'file'|'folder' $type
     */
    public function deleteMedia(string $type, string $path): string {
        if (!in_array($type, ['file', 'folder'], true)) {
            return 'invalid_type';
        }

        $segments = $this->mediaPathSegments($path);

        if ($segments === null || $segments === []) {
            return 'invalid_path';
        }

        if ($type === 'file') {
            $filename = array_pop($segments);

            if (!$this->isSafeImageFilename($filename)) {
                return 'invalid_path';
            }

            $parent = implode('/', $segments);
            $directory = $this->resolveDirectory($parent);

            if ($directory === '') {
                return 'not_found';
            }

            $file = $directory . DIRECTORY_SEPARATOR . $filename;

            if (is_link($file) || !is_file($file) || !$this->isAllowedImage($file, $filename)) {
                return 'not_found';
            }

            $relativePath = self::ROOT_RELATIVE . implode('/', array_merge($segments, [$filename]));

            if ($this->isImageInUse($relativePath)) {
                return 'in_use';
            }

            if (!is_writable($directory) || !unlink($file)) {
                return 'delete_failed';
            }

            return 'ok';
        }

        $folderName = array_pop($segments);

        if (!$this->isSafeFolderSegment($folderName)) {
            return 'invalid_path';
        }

        $parent = implode('/', $segments);
        $parentPath = $this->resolveDirectory($parent);

        if ($parentPath === '') {
            return 'not_found';
        }

        $directory = $parentPath . DIRECTORY_SEPARATOR . $folderName;

        if (is_link($directory) || !is_dir($directory)) {
            return 'not_found';
        }

        if (!$this->isEmptyDirectory($directory)) {
            return 'not_empty';
        }

        if (!is_writable($parentPath)) {
            return 'delete_failed';
        }

        $guard = $directory . DIRECTORY_SEPARATOR . 'index.html';
        if (is_file($guard) && !is_link($guard) && filesize($guard) === 0) {
            if (!is_writable($directory) || !unlink($guard)) {
                return 'delete_failed';
            }
        }

        if (!rmdir($directory)) {
            return 'delete_failed';
        }

        return 'ok';
    }

    /**
     * @return string|null Normalized folder relative to the blog media root; null if invalid.
     */
    private function normalizeFolder(string $folder): ?string {
        $folder = str_replace('\\', '/', trim($folder));

        if ($folder === '' || $folder === '/') {
            return '';
        }

        if (str_contains($folder, "\0") || preg_match('/[\x00-\x1F\x7F]/', $folder)) {
            return null;
        }

        if (str_starts_with($folder, '/') || str_ends_with($folder, '/')) {
            return null;
        }

        $segments = explode('/', $folder);

        if (count($segments) > self::MAX_FOLDER_DEPTH) {
            return null;
        }

        foreach ($segments as $segment) {
            if (!$this->isSafeFolderSegment($segment)) {
                return null;
            }
        }

        return implode('/', $segments);
    }

    private function isSafeFolderSegment(string $segment): bool {
        if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.') || str_contains($segment, '..')) {
            return false;
        }

        if (strlen($segment) > 128
            || str_contains($segment, '/')
            || str_contains($segment, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $segment)) {
            return false;
        }

        return preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _.-]{0,127}$/u', $segment) === 1
            && !str_ends_with($segment, '.')
            && !str_ends_with($segment, ' ');
    }

    private function isSafeNewFolderName(string $name): bool {
        return $name !== ''
            && $name !== '.'
            && $name !== '..'
            && !str_starts_with($name, '.')
            && strlen($name) <= 64
            && preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _-]{0,63}$/u', $name) === 1;
    }

    /** @return array<int, string>|null */
    private function mediaPathSegments(string $path): ?array {
        $path = str_replace('\\', '/', trim($path));

        if (!str_starts_with($path, self::ROOT_RELATIVE) || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return null;
        }

        $relative = substr($path, strlen(self::ROOT_RELATIVE));
        $segments = explode('/', $relative);

        foreach ($segments as $segment) {
            if (!$this->isSafeFolderSegment($segment)) {
                return null;
            }
        }

        return $segments;
    }

    private function isSafeImageFilename(string $filename): bool {
        if (!$this->isSafeFolderSegment($filename)) {
            return false;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return isset(self::IMAGE_MIME_BY_EXTENSION[$extension]);
    }

    private function isAllowedImage(string $path, string $filename): bool {
        if (!$this->isSafeImageFilename($filename)) {
            return false;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)($finfo->file($path) ?: '');

        return $mime === self::IMAGE_MIME_BY_EXTENSION[$extension];
    }

    private function isEmptyDirectory(string $directory): bool {
        $entries = scandir($directory);

        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $guardPath = $directory . DIRECTORY_SEPARATOR . $entry;
            if ($entry === 'index.html' && is_file($guardPath) && !is_link($guardPath) && filesize($guardPath) === 0) {
                continue; // guard vazio anti-listagem mantido pelo CMS
            }

            return false;
        }

        return true;
    }

    private function resolveDirectory(string $folder): string {
        $root = $this->rootPath();

        if ($this->rootHasSymlink() || !is_dir($root)) {
            return '';
        }

        $rootReal = realpath($root);

        if ($rootReal === false) {
            return '';
        }

        $normalized = $this->normalizeFolder($folder);

        if ($normalized === null) {
            return '';
        }

        $candidate = $rootReal;

        if ($normalized !== '') {
            foreach (explode('/', $normalized) as $segment) {
                $candidate .= DIRECTORY_SEPARATOR . $segment;

                if (is_link($candidate)) {
                    return '';
                }
            }
        }

        $resolved = realpath($candidate);

        if ($resolved === false || !is_dir($resolved)) {
            return '';
        }

        $prefix = rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return $resolved === $rootReal || str_starts_with($resolved, $prefix) ? $resolved : '';
    }

    private function rootPath(): string {
        return rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/image/blog';
    }

    /** Bloqueia links simbólicos em qualquer parte do caminho fixo da biblioteca. */
    private function rootHasSymlink(): bool {
        $base = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog';

        foreach ([$base, $base . '/view', $base . '/view/image', $base . '/view/image/blog'] as $path) {
            if (is_link($path)) {
                return true;
            }
        }

        return false;
    }

    /** Cria o index.html usado pelo CMS para impedir listagem de diretórios. */
    private function writeGuardFile(string $directory): bool {
        $guard = $directory . DIRECTORY_SEPARATOR . 'index.html';

        if (is_link($guard)) {
            return false;
        }

        if (is_file($guard)) {
            return true;
        }

        if (file_exists($guard)) {
            return false;
        }

        if (!is_writable($directory)) {
            return false;
        }

        return file_put_contents($guard, '', LOCK_EX) !== false;
    }

    /** @return array<string, bool>|null Paths referenciados; null sinaliza falha de consulta. */
    private function referencedImagePaths(): ?array {
        $query = $this->db->query(
            "SELECT `image` FROM `" . DB_PREFIX . "blog_post` WHERE `image` <> ''"
        );

        if (!$query instanceof \stdClass) {
            return null;
        }

        if (empty($query->rows)) {
            return [];
        }

        $used = [];

        foreach ($query->rows as $row) {
            $stored = trim((string)($row['image'] ?? ''));

            if ($stored === '') {
                continue;
            }

            $parsedPath = parse_url($stored, PHP_URL_PATH);
            if (is_string($parsedPath)) {
                $stored = $parsedPath;
            }

            $stored = rawurldecode(str_replace(chr(92), '/', $stored));
            $stored = ltrim($stored, '/');

            foreach (['catalog/', 'dev/catalog/'] as $prefix) {
                if (str_starts_with($stored, $prefix)) {
                    $stored = substr($stored, strlen($prefix));
                    break;
                }
            }

            if (str_starts_with($stored, self::ROOT_RELATIVE)) {
                $used[$stored] = true;
            }
        }

        return $used;
    }

    private function isImageInUse(string $relativePath): bool {
        $usedImages = $this->referencedImagePaths();

        return $usedImages === null || isset($usedImages[$relativePath]);
    }
}
