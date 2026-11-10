<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Cache em arquivo (driver padrão do config): DIR_STORAGE/cache/cache.{chave}.
 * Expiração por carimbo embutido no próprio payload.
 */
namespace Reamur\System\Library\Cache;

final class File {
    private string $directory;

    public function __construct(private int $expire = 3600, string $directory = '') {
        $this->expire = max(1, $expire);
        $this->directory = $directory !== ''
            ? rtrim($directory, '/\\') . '/'
            : DIR_STORAGE . 'cache/';

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function get(string $key): mixed {
        $file = $this->filePath($key);
        if ($file === null || !is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $payload = json_decode($raw, true);

        if (!is_array($payload) || (int)($payload['expire'] ?? 0) < time()) {
            $this->delete($key);

            return null;
        }

        return $payload['data'] ?? null;
    }

    public function set(string $key, mixed $value, int $expire = 0): bool {
        if ($expire < 0) {
            return false;
        }

        $file = $this->filePath($key);
        if ($file === null) {
            return false;
        }

        $ttl = $expire > 0 ? $expire : $this->expire;
        $encoded = json_encode([
            'expire' => time() + $ttl,
            'data'   => $value,
        ]);

        if ($encoded === false) {
            return false;
        }

        return file_put_contents($file, $encoded, LOCK_EX) !== false;
    }

    public function exists(string $key): bool {
        $file = $this->filePath($key);
        if ($file === null || !is_file($file)) {
            return false;
        }

        $raw = @file_get_contents($file);
        $payload = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($payload) || (int)($payload['expire'] ?? 0) < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool {
        $file = $this->filePath($key);
        if ($file === null) {
            return false;
        }

        return !is_file($file) || @unlink($file);
    }

    /**
     * Limpa TODOS os arquivos cache.* (botão "Limpar cache" do painel — F4-01).
     */
    public function clear(): bool {
        $ok = true;
        foreach (glob($this->directory . 'cache.*') ?: [] as $file) {
            if (is_file($file) && !@unlink($file)) {
                $ok = false;
            }
        }
        return $ok;
    }

    private function filePath(string $key): ?string {
        if ($key === '' || str_contains($key, '..')) {
            return null;
        }

        $safe = (string)preg_replace('/[^a-zA-Z0-9_.\-]/', '', $key);
        if ($safe === '' || str_contains($safe, '..')) {
            return null;
        }

        return $this->directory . 'cache.' . $safe;
    }
}
