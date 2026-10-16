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

    public function __construct(string $filename = 'error.log') {
        $this->file = DIR_STORAGE . 'logs/' . basename($filename);
    }

    public function write(string $message): void {
        file_put_contents($this->file, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
    }
}
