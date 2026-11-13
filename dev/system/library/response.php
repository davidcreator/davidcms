<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Resposta HTTP (Arquitetura-MVCL §4): headers + corpo + redirect.
 * Em CLI (testes headless), redirect NÃO dá exit — apenas registra o destino.
 */
namespace Reamur\System\Library;

final class Response {
    /** @var array<int, string> */
    private array $headers = [];

    private string $output = '';

    private int $compression = 0;

    public function addHeader(string $header): void {
        $clean = trim(str_replace(["\r", "\n", "\0"], '', $header));

        if ($clean !== '' && !in_array($clean, $this->headers, true)) {
            $this->headers[] = $clean;
        }
    }

    /**
     * Define um cabeçalho de valor único: remove as linhas anteriores com o
     * mesmo nome e acrescenta esta. Use quando um controller precisa trocar
     * um cabeçalho que o framework já pôs (ADR 0093: Content-Security-Policy).
     * Linha vazia ou sem nome é ignorada.
     */
    public function setHeader(string $header): void {
        $clean = trim(str_replace(["\r", "\n", "\0"], '', $header));
        $colon = strpos($clean, ':');

        if ($colon === false || $colon === 0) {
            return;
        }

        $name = strtolower(trim(substr($clean, 0, $colon)));

        $this->headers = array_values(array_filter(
            $this->headers,
            static fn (string $line): bool => strtolower(trim((string)strstr($line . ':', ':', true))) !== $name
        ));

        $this->headers[] = $clean;
    }

    /** @return array<int, string> */
    public function getHeaders(): array {
        return $this->headers;
    }

    /**
     * ADR 0088 (S4): descarta o que um controller que falhou deixou para trás
     * (Content-Type de XML ou JSON, Cache-Control público, Location, corpo
     * parcial) e recomeça dos cabeçalhos informados.
     *
     * @param array<int, string> $headers
     */
    public function reset(array $headers = []): void {
        $this->headers = [];
        $this->output  = '';

        foreach ($headers as $header) {
            $this->addHeader((string)$header);
        }
    }

    public function setCompression(int $level): void {
        $this->compression = $level;
    }

    public function setOutput(string $output): void {
        $this->output = $output;
    }

    public function getOutput(): string {
        return $this->output;
    }

    /** Sanitiza destino de redirect contra CRLF/NUL e esquemas não-HTTP (javascript:, data:). */
    public static function sanitizeRedirectTarget(string $url): string {
        $head  = substr($url, 0, strcspn($url, "\r\n\0"));
        $clean = trim(str_replace('&amp;', '&', $head));

        if ($clean === '') {
            return 'index.php';
        }

        if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $clean, $m) === 1) {
            if (!in_array(strtolower($m[1]), ['http', 'https'], true)) {
                return 'index.php';
            }
        }

        return $clean;
    }

    /** Redirect HTTP; em web, encerra a execução (padrão OpenCart) */
    public function redirect(string $url, int $status = 302): void {
        $safeUrl = self::sanitizeRedirectTarget($url);

        if (PHP_SAPI !== 'cli') {
            header('Location: ' . $safeUrl, true, $status);
            exit();
        }

        $this->addHeader('Location: ' . $safeUrl);
    }

    /** Envia headers + corpo (com compressão opcional) */
    public function output(): void {
        if (PHP_SAPI !== 'cli') {
            foreach ($this->headers as $header) {
                header($header);
            }
        }

        if ($this->compression > 0 && extension_loaded('zlib') && PHP_SAPI !== 'cli') {
            ob_start('ob_gzhandler');
        }

        echo $this->output;

        if ($this->compression > 0 && extension_loaded('zlib') && PHP_SAPI !== 'cli' && ob_get_level() > 0) {
            ob_end_flush();
        }
    }
}
