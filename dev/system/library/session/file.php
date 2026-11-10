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
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0750, true);
        }
    }

    public function open(string $path, string $name): bool {
        if ($path !== '' && is_dir($path)) {
            $this->directory = rtrim($path, '/\\') . '/';
        }
        return is_dir($this->directory) || @mkdir($this->directory, 0750, true);
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $file = $this->sessionPath($id);
        if ($file === null || !@is_file($file) || !@is_readable($file)) {
            return '';
        }

        // Em Windows/WAMP, leituras concorrentes podem colidir com LOCK_EX (errno=13).
        // Usa fopen + flock(LOCK_SH) com breve retry sem emitir ErrorException.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $fp = @fopen($file, 'rb');
            if ($fp !== false) {
                $data = '';
                if (@flock($fp, LOCK_SH)) {
                    $raw = @stream_get_contents($fp);
                    if (is_string($raw)) {
                        $data = $raw;
                    }
                    @flock($fp, LOCK_UN);
                }
                @fclose($fp);
                return $data;
            }
            if ($attempt < 2) {
                usleep(10000);
            }
        }

        return '';
    }

    public function write(string $id, string $data): bool {
        $file = $this->sessionPath($id);
        if ($file === null) {
            return false;
        }
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            return false;
        }

        $fp = @fopen($file, 'cb');
        if ($fp === false) {
            return false;
        }

        $written = false;
        if (@flock($fp, LOCK_EX)) {
            if (@ftruncate($fp, 0) && @rewind($fp)) {
                $len = strlen($data);
                $bytes = $len === 0 ? 0 : @fwrite($fp, $data);
                $written = ($bytes === $len);
                @fflush($fp);
            }
            @flock($fp, LOCK_UN);
        }
        @fclose($fp);

        return $written;
    }

    public function destroy(string $id): bool {
        $file = $this->sessionPath($id);
        if ($file === null) {
            return false;
        }
        if (@is_file($file)) {
            return @unlink($file);
        }
        return true;
    }

    public function gc(int $maxlifetime): int {
        $count = 0;
        $cutoff = time() - max(0, $maxlifetime);

        foreach (glob($this->directory . 'sess_*') ?: [] as $file) {
            clearstatcache(true, $file);
            if (!@is_file($file)) {
                continue;
            }
            $mtime = @filemtime($file);
            if (is_int($mtime) && $mtime < $cutoff && @unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    private function sessionPath(string $id): ?string {
        if (preg_match('/^[a-zA-Z0-9,-]{1,256}$/', $id) !== 1) {
            return null;
        }
        return $this->directory . 'sess_' . $id;
    }
}
