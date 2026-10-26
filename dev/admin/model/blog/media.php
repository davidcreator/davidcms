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
    private const DOWNLOAD_ROOT_RELATIVE = 'view/download/blog/';
    private const AUDIO_ROOT_RELATIVE = 'view/audio/blog/';
    private const VIDEO_ROOT_RELATIVE = 'view/video/blog/';
    private const AUDIO_UPLOAD_MAX_BYTES = 20971520;
    private const VIDEO_UPLOAD_MAX_BYTES = 104857600;
    private const AUDIO_MIME_BY_EXTENSION = [
        'mp3' => ['audio/mpeg'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
    ];
    private const VIDEO_MIME_BY_EXTENSION = [
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
        'ogv' => ['video/ogg'],
    ];
    private const DOWNLOAD_MAX_BYTES = 2097152;
    private const DOWNLOAD_MIME_BY_EXTENSION = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'odt' => ['application/vnd.oasis.opendocument.text'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'txt' => ['text/plain'],
    ];
    private const PAGE_LIMIT = 24;
    private const MAX_FOLDER_DEPTH = 8;
    private const IMAGE_MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    /** Lista arquivos de áudio preexistentes da raiz dedicada; este incremento não envia nem exclui áudio. */
    public function getAudioPage(string $search, int $page, int $limit = self::PAGE_LIMIT): array {
        $root = $this->audioRootPath();
        $base = rtrim(dirname(DIR_APPLICATION), '/\\\\') . '/catalog';
        foreach ([$base, $base . '/view', $base . '/view/audio', $root] as $path) if (is_link($path)) return ['ok' => false, 'error' => 'audio_root_unavailable'];
        if (!is_dir($root)) return ['ok' => true, 'files' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        $entries = scandir($root);
        if ($entries === false) return ['ok' => false, 'error' => 'audio_root_unreadable'];
        $search = trim(mb_substr($search, 0, 128)); $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.html' || str_starts_with($entry, '.')) continue;
            $path = $root . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path) || !is_file($path) || !$this->isAllowedAudio($path, $entry)) continue;
            if ($search !== '' && mb_stripos($entry, $search) === false) continue;
            $size = filesize($path);
            $files[] = ['name' => $entry, 'path' => self::AUDIO_ROOT_RELATIVE . $entry, 'size' => $size === false ? 0 : (int)$size];
        }
        usort($files, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        $total = count($files); $limit = min(48, max(1, $limit)); $pages = max(1, (int)ceil($total / $limit)); $page = min($pages, max(1, $page));
        return ['ok' => true, 'files' => array_slice($files, ($page - 1) * $limit, $limit), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Lista arquivos locais de vídeo já cadastrados na raiz dedicada. */
    public function getVideoPage(string $search, int $page, int $limit = self::PAGE_LIMIT): array {
        $root = $this->videoRootPath();
        $base = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog';
        foreach ([$base, $base . '/view', $base . '/view/video', $root] as $path) if (is_link($path)) return ['ok' => false, 'error' => 'video_root_unavailable'];
        if (!is_dir($root)) return ['ok' => true, 'files' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        $entries = scandir($root);
        if ($entries === false) return ['ok' => false, 'error' => 'video_root_unreadable'];
        $search = trim(mb_substr($search, 0, 128)); $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.html' || str_starts_with($entry, '.')) continue;
            $path = $root . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path) || !is_file($path) || !$this->isAllowedVideo($path, $entry)) continue;
            if ($search !== '' && mb_stripos($entry, $search) === false) continue;
            $size = filesize($path);
            $files[] = ['name' => $entry, 'path' => self::VIDEO_ROOT_RELATIVE . $entry, 'size' => $size === false ? 0 : (int)$size];
        }
        usort($files, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        $total = count($files); $limit = min(48, max(1, $limit)); $pages = max(1, (int)ceil($total / $limit)); $page = min($pages, max(1, $page));
        return ['ok' => true, 'files' => array_slice($files, ($page - 1) * $limit, $limit), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Upload de áudio/vídeo limitado por allowlist de extensão + MIME real e raiz dedicada. */
    public function uploadLocalBlogMedia(array $file, string $kind): array {
        if (!in_array($kind, ['audio', 'video'], true)) return ['ok' => false, 'error' => 'type'];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE); $tmp = (string)($file['tmp_name'] ?? '');
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return ['ok' => false, 'error' => 'size'];
        if ($tmp === '' || $error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) return ['ok' => false, 'error' => 'no_file'];
        $limit = $kind === 'audio' ? self::AUDIO_UPLOAD_MAX_BYTES : self::VIDEO_UPLOAD_MAX_BYTES;
        $actualSize = filesize($tmp); $declaredSize = (int)($file['size'] ?? 0);
        if ($actualSize === false || $declaredSize <= 0 || $actualSize !== $declaredSize || $actualSize > $limit) return ['ok' => false, 'error' => 'size'];
        $types = $kind === 'audio' ? self::AUDIO_MIME_BY_EXTENSION : self::VIDEO_MIME_BY_EXTENSION;
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!isset($types[$extension])) return ['ok' => false, 'error' => 'type'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE); $mime = (string)($finfo->file($tmp) ?: '');
        if (!in_array($mime, $types[$extension], true)) return ['ok' => false, 'error' => 'type'];
        $catalog = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog';
        $view = $catalog . '/view'; $kindParent = $view . '/' . $kind; $root = $kindParent . '/blog';
        foreach ([$catalog, $view, $kindParent, $root] as $path) if (is_link($path)) return ['ok' => false, 'error' => 'root'];
        if (!is_dir($catalog) || !is_dir($view)) return ['ok' => false, 'error' => 'root'];
        if (!is_dir($kindParent) && !@mkdir($kindParent, 0755, true) && !is_dir($kindParent)) return ['ok' => false, 'error' => 'root'];
        if (!is_dir($root) && !@mkdir($root, 0755, true) && !is_dir($root)) return ['ok' => false, 'error' => 'root'];
        foreach ([$catalog, $view, $kindParent, $root] as $path) if (is_link($path)) return ['ok' => false, 'error' => 'root'];
        $indexFile = $root . DIRECTORY_SEPARATOR . 'index.html';
        if (is_link($indexFile)) return ['ok' => false, 'error' => 'root'];
        if (!is_file($indexFile) && @file_put_contents($indexFile, '', LOCK_EX) === false) return ['ok' => false, 'error' => 'root'];
        $realCatalog = realpath($catalog); $realRoot = realpath($root);
        if ($realCatalog === false || $realRoot === false || !str_starts_with($realRoot, rtrim($realCatalog, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) return ['ok' => false, 'error' => 'root'];
        try { $filename = bin2hex(random_bytes(16)) . '.' . $extension; } catch (\Throwable $e) { return ['ok' => false, 'error' => 'move']; }
        $destination = $realRoot . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($destination) || is_link($destination) || !move_uploaded_file($tmp, $destination)) return ['ok' => false, 'error' => 'move'];
        @chmod($destination, 0644);
        return ['ok' => true, 'name' => $filename, 'path' => ($kind === 'audio' ? self::AUDIO_ROOT_RELATIVE : self::VIDEO_ROOT_RELATIVE) . $filename, 'size' => $actualSize];
    }

    /** Confirma que referência local de vídeo fica na raiz dedicada e tem MIME válido. */
    public function videoReferenceExists(string $relative): bool {
        if (!preg_match('#^view/video/blog/([A-Za-z0-9][A-Za-z0-9._-]{0,179})$#D', $relative) || str_contains($relative, '..')) return false;
        $root = $this->videoRootPath(); $base = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog';
        foreach ([$base, $base . '/view', $base . '/view/video', $root] as $path) if (is_link($path)) return false;
        return $this->isAllowedVideo($root . DIRECTORY_SEPARATOR . basename($relative), basename($relative));
    }

    private function isAllowedVideo(string $path, string $filename): bool {
        if ($filename === '' || strlen($filename) > 180 || preg_match('/[\\x00-\\x20\\x7F]/', $filename)
            || str_contains($filename, '/') || str_contains($filename, chr(92)) || str_contains($filename, '..')
            || is_link($path) || !is_file($path)) return false;
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!isset(self::VIDEO_MIME_BY_EXTENSION[$extension])) return false;
        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > self::VIDEO_UPLOAD_MAX_BYTES) return false;
        $finfo = new \finfo(FILEINFO_MIME_TYPE); $mime = (string)($finfo->file($path) ?: '');
        return in_array($mime, self::VIDEO_MIME_BY_EXTENSION[$extension], true);
    }

    private function videoRootPath(): string {
        return rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/video/blog';
    }

    /** Confirma referência local existente, sem upload/exclusão, symlink ou traversal. */
    public function audioReferenceExists(string $relative): bool {
        if (!preg_match('#^view/audio/blog/([A-Za-z0-9][A-Za-z0-9._-]{0,179})$#D', $relative) || str_contains($relative, '..')) return false;
        $root = $this->audioRootPath(); $base = rtrim(dirname(DIR_APPLICATION), '/\\\\') . '/catalog';
        foreach ([$base, $base . '/view', $base . '/view/audio', $root] as $path) if (is_link($path)) return false;
        $file = $root . DIRECTORY_SEPARATOR . basename($relative);
        return $this->isAllowedAudio($file, basename($relative));
    }

    private function isAllowedAudio(string $path, string $filename): bool {
        if (!$this->isSafeAudioFilename($filename) || is_link($path) || !is_file($path)) return false;
        $finfo = new \finfo(FILEINFO_MIME_TYPE); $mime = (string)($finfo->file($path) ?: '');
        return in_array($mime, self::AUDIO_MIME_BY_EXTENSION[strtolower(pathinfo($filename, PATHINFO_EXTENSION))], true);
    }

    private function isSafeAudioFilename(string $filename): bool {
        if ($filename === '' || strlen($filename) > 180 || preg_match('/[\\x00-\\x20\\x7F]/', $filename)
            || str_contains($filename, '/') || str_contains($filename, chr(92)) || str_contains($filename, '..')) return false;
        return isset(self::AUDIO_MIME_BY_EXTENSION[strtolower(pathinfo($filename, PATHINFO_EXTENSION))]);
    }

    private function audioRootPath(): string {
        return rtrim(dirname(DIR_APPLICATION), '/\\\\') . '/catalog/view/audio/blog';
    }

    /** Confirma que a referência de um bloco resolve para arquivo regular permitido dentro da raiz. */
    public function downloadReferenceExists(string $relative): bool {
        if (!preg_match('#^view/download/blog/([A-Za-z0-9][A-Za-z0-9._-]{0,179})$#D', $relative) || str_contains($relative, '..')) return false;
        $root = $this->downloadRootPath();
        $base = rtrim(dirname(DIR_APPLICATION), '/\\\\') . '/catalog';
        foreach ([$base, $base . '/view', $base . '/view/download', $root] as $path) if (is_link($path)) return false;
        $file = $root . DIRECTORY_SEPARATOR . basename($relative);
        return $this->isAllowedDownload($file, basename($relative));
    }

    /** Lista apenas documentos validados da raiz de downloads, sem pastas nem exclusão. */
    public function getDownloadPage(string $search, int $page, int $limit = self::PAGE_LIMIT): array {
        $root = $this->downloadRootPath();
        $base = rtrim(dirname(DIR_APPLICATION), '/\\\\') . '/catalog';
        foreach ([$base, $base . '/view', $base . '/view/download', $root] as $path) if (is_link($path)) return ['ok' => false, 'error' => 'download_root_unavailable'];
        if (!is_dir($root)) return ['ok' => true, 'files' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        $entries = scandir($root);
        if ($entries === false) return ['ok' => false, 'error' => 'folder_unreadable'];
        $search = trim(mb_substr($search, 0, 128));
        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.html' || str_starts_with($entry, '.')) continue;
            $path = $root . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path) || !is_file($path) || !$this->isSafeDownloadFilename($entry) || !$this->isAllowedDownload($path, $entry)) continue;
            if ($search !== '' && mb_stripos($entry, $search) === false) continue;
            $size = filesize($path); $modified = filemtime($path);
            $files[] = ['name' => $entry, 'path' => self::DOWNLOAD_ROOT_RELATIVE . $entry, 'size' => $size === false ? 0 : (int)$size, 'modified' => $modified === false ? 0 : (int)$modified];
        }
        usort($files, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        $total = count($files); $limit = min(48, max(1, $limit)); $pages = max(1, (int)ceil($total / $limit)); $page = min($pages, max(1, $page));
        return ['ok' => true, 'files' => array_slice($files, ($page - 1) * $limit, $limit), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Upload restrito à raiz dedicada; nenhum nome fornecido pelo cliente é usado no destino. */
    public function uploadDownload(array $file): array {
        $tmp = (string)($file['tmp_name'] ?? ''); $size = (int)($file['size'] ?? 0); $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return ['ok' => false, 'error' => 'size'];
        if ($tmp === '' || $error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) return ['ok' => false, 'error' => 'no_file'];
        $actualSize = filesize($tmp);
        if ($size <= 0 || $actualSize === false || $actualSize !== $size || $actualSize > self::DOWNLOAD_MAX_BYTES) return ['ok' => false, 'error' => 'size'];
        $clientName = (string)($file['name'] ?? ''); $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if (!$this->isSafeDownloadFilename('x.' . $extension) || !$this->isAllowedDownload($tmp, 'x.' . $extension)) return ['ok' => false, 'error' => 'type'];
        if (!$this->ensureDownloadRoot()) return ['ok' => false, 'error' => 'move'];
        $stem = (string)pathinfo($clientName, PATHINFO_FILENAME);
        $stem = preg_replace('/[^A-Za-z0-9._-]+/', '-', $stem) ?? '';
        $stem = preg_replace('/\.{2,}/', '-', $stem) ?? $stem;
        $stem = trim(substr($stem, 0, 80), '.-_');
        if ($stem === '') $stem = 'download';
        $filename = $stem . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = $this->downloadRootPath() . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($target) || is_link($target) || !move_uploaded_file($tmp, $target)) return ['ok' => false, 'error' => 'move'];
        return ['ok' => true, 'path' => self::DOWNLOAD_ROOT_RELATIVE . $filename, 'name' => $filename];
    }

    private function isSafeDownloadFilename(string $filename): bool {
        if ($filename === '' || strlen($filename) > 180
            || preg_match('/[\x00-\x20\x7F]/', $filename)
            || str_contains($filename, '/') || str_contains($filename, chr(92))
            || str_contains($filename, '..')) return false;
        return isset(self::DOWNLOAD_MIME_BY_EXTENSION[strtolower(pathinfo($filename, PATHINFO_EXTENSION))]);
    }

    private function isAllowedDownload(string $path, string $filename): bool {
        if (!$this->isSafeDownloadFilename($filename) || is_link($path) || !is_file($path)) return false;
        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > self::DOWNLOAD_MAX_BYTES) return false;
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $finfo = new \finfo(FILEINFO_MIME_TYPE); $mime = (string)($finfo->file($path) ?: '');
        $allowed = self::DOWNLOAD_MIME_BY_EXTENSION[$extension];
        if (in_array($mime, $allowed, true)) return true;
        if (!in_array($extension, ['docx','xlsx','pptx','odt','ods'], true) || $mime !== 'application/zip' || !class_exists(\ZipArchive::class)) return false;
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CHECKCONS) !== true) return false;
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

    private function downloadRootPath(): string {
        return rtrim(dirname(DIR_APPLICATION), '/\\\\') . '/catalog/view/download/blog';
    }

    private function ensureDownloadRoot(): bool {
        $base = rtrim(dirname(DIR_APPLICATION), '/\\\\') . '/catalog';
        foreach ([$base, $base . '/view', $base . '/view/download', $base . '/view/download/blog'] as $path) if (is_link($path)) return false;
        $view = $base . '/view'; $parent = $base . '/view/download'; $root = $this->downloadRootPath();
        if (!is_dir($view) || !is_writable($view)) return false;
        if (!is_dir($parent) && !mkdir($parent, 0755, false) && !is_dir($parent)) return false;
        if (!is_dir($root) && !mkdir($root, 0755, false) && !is_dir($root)) return false;
        if (!is_writable($root)) return false;
        $guard = $root . DIRECTORY_SEPARATOR . 'index.html';
        if (is_link($guard) || (file_exists($guard) && !is_file($guard))) return false;
        if (!is_file($guard) && file_put_contents($guard, '', LOCK_EX) === false) return false;
        return true;
    }

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
