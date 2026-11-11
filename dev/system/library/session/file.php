<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Driver de sessão em arquivos (storage/session/sess_<id>).
 */
namespace Reamur\System\Library\Session;

class File implements \SessionHandlerInterface {
    private string $directory;

    public function __construct(string $directory = '') {
        $base = $directory !== '' ? $directory : (defined('DIR_STORAGE') ? DIR_STORAGE . 'session/' : sys_get_temp_dir() . '/');
        $this->directory = rtrim($base, '/\\') . '/';
        $this->ensureDirectory($this->directory);
    }

    public function open(string $path, string $name): bool {
        if ($path !== '') {
            set_error_handler(static fn (): bool => true);
            try {
                if (is_dir($path)) {
                    $this->directory = rtrim($path, '/\\') . '/';
                }
            } finally {
                restore_error_handler();
            }
        }

        return $this->ensureDirectory($this->directory);
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $file = $this->sessionPath($id);
        if ($file === null) {
            return '';
        }

        set_error_handler(static fn (): bool => true);
        try {
            if (!is_file($file) || !is_readable($file)) {
                return '';
            }

            // Em Windows/WAMP, leituras/escritas concorrentes colidem com LOCK_EX (errno=13 Permission denied).
            // Usa fopen + flock(LOCK_SH) com retry em fopen, flock e stream_get_contents sem gerar ErrorException.
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
                    usleep(10000);
                }
            }
        } finally {
            restore_error_handler();
        }

        return '';
    }

    public function write(string $id, string $data): bool {
        $file = $this->sessionPath($id);
        if ($file === null || !$this->ensureDirectory($this->directory)) {
            return false;
        }

        set_error_handler(static fn (): bool => true);
        try {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $fp = fopen($file, 'cb');
                if ($fp !== false) {
                    $written = false;
                    if (flock($fp, LOCK_EX)) {
                        if (ftruncate($fp, 0) && rewind($fp)) {
                            $len = strlen($data);
                            $bytes = $len === 0 ? 0 : fwrite($fp, $data);
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
                    usleep(10000);
                }
            }
        } finally {
            restore_error_handler();
        }

        return false;
    }

    public function destroy(string $id): bool {
        $file = $this->sessionPath($id);
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

    public function gc(int $maxlifetime): int {
        $count = 0;
        $cutoff = time() - max(0, $maxlifetime);

        set_error_handler(static fn (): bool => true);
        try {
            foreach (glob($this->directory . 'sess_*') ?: [] as $file) {
                clearstatcache(true, $file);
                if (!is_file($file)) {
                    continue;
                }
                $mtime = filemtime($file);
                if (is_int($mtime) && $mtime < $cutoff && unlink($file)) {
                    $count++;
                }
            }
        } finally {
            restore_error_handler();
        }

        return $count;
    }

    private function ensureDirectory(string $dir): bool {
        set_error_handler(static fn (): bool => true);
        try {
            return is_dir($dir) || mkdir($dir, 0750, true) || is_dir($dir);
        } finally {
            restore_error_handler();
        }
    }

    private function sessionPath(string $id): ?string {
        if (preg_match('/^[a-zA-Z0-9,-]{1,256}$/', $id) !== 1) {
            return null;
        }

        return $this->directory . 'sess_' . $id;
    }
}
