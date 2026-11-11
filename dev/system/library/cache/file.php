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

        $this->ensureDirectory($this->directory);
    }

    public function get(string $key): mixed {
        $file = $this->filePath($key);
        if ($file === null) {
            return null;
        }

        $raw = $this->readRaw($file);
        if ($raw === null || $raw === '') {
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
        if ($file === null || !$this->ensureDirectory($this->directory)) {
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

        return $this->writeRaw($file, $encoded);
    }

    public function exists(string $key): bool {
        $file = $this->filePath($key);
        if ($file === null) {
            return false;
        }

        $raw = $this->readRaw($file);
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

        set_error_handler(static fn (): bool => true);
        try {
            return !is_file($file) || unlink($file);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Limpa TODOS os arquivos cache.* (botão "Limpar cache" do painel — F4-01).
     */
    public function clear(): bool {
        $ok = true;
        set_error_handler(static fn (): bool => true);
        try {
            foreach (glob($this->directory . 'cache.*') ?: [] as $file) {
                if (is_file($file) && !unlink($file)) {
                    $ok = false;
                }
            }
        } finally {
            restore_error_handler();
        }

        return $ok;
    }

    private function readRaw(string $file): ?string {
        set_error_handler(static fn (): bool => true);
        try {
            if (!is_file($file)) {
                return null;
            }

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $fp = fopen($file, 'rb');
                if ($fp !== false) {
                    $data = null;
                    if (flock($fp, LOCK_SH)) {
                        $raw = stream_get_contents($fp);
                        if (is_string($raw)) {
                            $data = $raw;
                        }
                        flock($fp, LOCK_UN);
                    }
                    fclose($fp);
                    if ($data !== null) {
                        return $data;
                    }
                }
                if ($attempt < 4) {
                    usleep(5000);
                }
            }
        } finally {
            restore_error_handler();
        }

        return null;
    }

    private function writeRaw(string $file, string $payload): bool {
        set_error_handler(static fn (): bool => true);
        try {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $fp = fopen($file, 'cb');
                if ($fp !== false) {
                    $written = false;
                    if (flock($fp, LOCK_EX)) {
                        if (ftruncate($fp, 0) && rewind($fp)) {
                            $len = strlen($payload);
                            $bytes = $len === 0 ? 0 : fwrite($fp, $payload);
                            $written = ($bytes === $len);
                            fflush($fp);
                        }
                        flock($fp, LOCK_UN);
                    }
                    fclose($fp);
                    if ($written) {
                        return true;
                    }
                }
                if ($attempt < 4) {
                    usleep(5000);
                }
            }
        } finally {
            restore_error_handler();
        }

        return false;
    }

    private function ensureDirectory(string $dir): bool {
        set_error_handler(static fn (): bool => true);
        try {
            return is_dir($dir) || mkdir($dir, 0755, true) || is_dir($dir);
        } finally {
            restore_error_handler();
        }
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
