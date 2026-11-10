<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Log em arquivo (DIR_STORAGE/logs/). Nome do arquivo vem do config
 * (error_filename); nada de paths soltos fora do storage (ADR via Guia-Composer).
 */
namespace Reamur\System\Library;

final class Log {
    private string $file;

    public function __construct(string $filename = 'error.log', string $directory = '') {
        $baseDir = $directory !== ''
            ? rtrim($directory, '/\\') . '/'
            : (defined('DIR_STORAGE') ? DIR_STORAGE . 'logs/' : sys_get_temp_dir() . '/');

        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0750, true);
        }

        $cleanName = basename(str_replace(["\0", '\\'], ['', '/'], trim($filename)));
        if ($cleanName === '' || $cleanName === '.' || $cleanName === '..') {
            $cleanName = 'error.log';
        }

        $this->file = $baseDir . $cleanName;
    }

    public function getFilePath(): string {
        return $this->file;
    }

    public function write(string $message): void {
        $cleanMessage = str_replace("\0", '', $message);
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        @file_put_contents(
            $this->file,
            date('Y-m-d H:i:s') . ' - ' . $cleanMessage . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
