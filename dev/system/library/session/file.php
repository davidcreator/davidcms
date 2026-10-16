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

    public function __construct() {
        $this->directory = DIR_STORAGE . 'session/';

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function read(string $session_id): string {
        $file = $this->directory . 'sess_' . $session_id;

        return is_file($file) ? (string)file_get_contents($file) : '';
    }

    public function write(string $session_id, string $data): bool {
        return file_put_contents($this->directory . 'sess_' . $session_id, $data, LOCK_EX) !== false;
    }

    public function destroy(string $session_id): bool {
        $file = $this->directory . 'sess_' . $session_id;

        return !is_file($file) || unlink($file);
    }

    public function gc(int $maxlifetime): int {
        $count = 0;

        foreach (glob($this->directory . 'sess_*') ?: [] as $file) {
            if (filemtime($file) < time() - $maxlifetime && unlink($file)) {
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
}
