<?php
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

namespace Reamur\System\Library;

/**
 * Class Response
 * Stores the response so the correct headers can go out before the response output is shown.
 */
class Response {
    private array $headers = [];
    private int $level = 0;
    private string $output = '';

    public function addHeader(string $header): void {
        if (!in_array($header, $this->headers, true)) {
            $this->headers[] = $header;
        }
    }

    public function getHeaders(): array {
        return $this->headers;
    }

    public function redirect(string $url, int $status = 302): void {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL provided for redirect');
        }

        $cleanUrl = str_replace(['&amp;', "\n", "\r"], ['&', '', ''], $url);
        header('Location: ' . $cleanUrl, true, $status);
        exit();
    }

    public function setCompression(int $level): void {
        if ($level < -1 || $level > 9) {
            throw new \InvalidArgumentException('Compression level must be between -1 and 9');
        }
        $this->level = $level;
    }

    public function setOutput(string $output): void {
        $this->output = $output;
    }

    public function getOutput(): string {
        return $this->output;
    }

    private function compress(string $data, int $level = 0): string {
        if (!isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            return $data;
        }

        $encoding = null;
        if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
            $encoding = 'gzip';
        } elseif (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false) {
            $encoding = 'x-gzip';
        }

        if (!$encoding || $level < -1 || $level > 9) {
            return $data;
        }

        if (!extension_loaded('zlib') || ini_get('zlib.output_compression')) {
            return $data;
        }

        if (headers_sent() || connection_status()) {
            return $data;
        }

        $this->addHeader('Content-Encoding: ' . $encoding);
        return gzencode($data, $level);
    }

    public function output(): void {
        if (!$this->output) {
            return;
        }

        $output = $this->level ? $this->compress($this->output, $this->level) : $this->output;

        if (!headers_sent()) {
            foreach ($this->headers as $header) {
                header($header, true);
            }
        }

        echo $output;
    }
}