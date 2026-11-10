<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Driver de sessão em arquivo: DIR_STORAGE/session/sess_{id}.
 */
namespace Reamur\System\Library\Session;

class File implements \SessionHandlerInterface {
    private string $directory;

    public function __construct(string $directory = '') {
        $this->directory = $directory !== ''
            ? rtrim($directory, '/\\') . '/'
            : DIR_STORAGE . 'session/';

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function read(string $session_id): string {
        $file = $this->sessionPath($session_id);
        if ($file === null || !is_file($file)) {
            return '';
        }

        $content = @file_get_contents($file);
        return is_string($content) ? $content : '';
    }

    public function write(string $session_id, string $data): bool {
        $file = $this->sessionPath($session_id);
        if ($file === null) {
            return false;
        }

        return file_put_contents($file, $data, LOCK_EX) !== false;
    }

    public function destroy(string $session_id): bool {
        $file = $this->sessionPath($session_id);
        if ($file === null) {
            return false;
        }

        return !is_file($file) || @unlink($file);
    }

    public function gc(int $maxlifetime): int {
        $count = 0;
        $cutoff = time() - max(0, $maxlifetime);

        foreach (glob($this->directory . 'sess_*') ?: [] as $file) {
            if (is_file($file) && (int)@filemtime($file) < $cutoff && @unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    private function sessionPath(string $session_id): ?string {
        if (preg_match('/^[a-zA-Z0-9,-]{1,256}$/', $session_id) !== 1) {
            return null;
        }

        return $this->directory . 'sess_' . $session_id;
    }
}
