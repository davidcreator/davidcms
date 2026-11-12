<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * ADR 0088 (S3 + S4): a resposta de erro 500.
 *
 * Esta classe NÃO usa banco, template, tema, idioma carregado nem autoloader
 * de terceiros: ela é chamada justamente quando uma dessas peças quebrou.
 * Tudo o que entra no HTML é escapado aqui.
 *
 * O detalhe da exceção (classe, mensagem, arquivo, linha) só aparece quando
 * quem chama passa `$detail`; quem chama só passa com `error_display` ligado.
 */
namespace Reamur\System\Library;

final class ErrorPage {
    public const STATUS = 'HTTP/1.1 500 Internal Server Error';

    /** Erros do PHP que encerram o script sem passar pelo tratador de exceções. */
    public const FATAL = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    private const TEXT = [
        'pt-br' => [
            'title' => 'Erro temporário',
            'body'  => 'Algo deu errado do nosso lado. Tente de novo em alguns instantes.',
            'home'  => 'Voltar para a página inicial',
            'ref'   => 'Referência',
        ],
        'en-us' => [
            'title' => 'Temporary error',
            'body'  => 'Something went wrong on our side. Please try again in a few moments.',
            'home'  => 'Back to the home page',
            'ref'   => 'Reference',
        ],
        'es-eu' => [
            'title' => 'Error temporal',
            'body'  => 'Algo ha fallado por nuestra parte. Inténtalo de nuevo en unos instantes.',
            'home'  => 'Volver a la página de inicio',
            'ref'   => 'Referencia',
        ],
    ];

    /** Memória guardada para o tratador de erro fatal conseguir responder após "memory exhausted". */
    private static ?string $reserve = null;

    public static function reserve(): void {
        self::$reserve = str_repeat('x', 65536);
    }

    public static function release(): void {
        self::$reserve = null;
    }

    /**
     * Joga fora o que estava retido em buffer de saída (meia página de quem
     * falhou). Só faz sentido enquanto o cabeçalho ainda não saiu.
     */
    public static function discardOutput(): void {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        while (ob_get_level() > 0 && @ob_end_clean()) {
            // esvazia todos os níveis
        }
    }

    /** Código curto que liga a página vista pelo visitante à linha do log. */
    public static function reference(): string {
        try {
            return strtoupper(bin2hex(random_bytes(4)));
        } catch (\Throwable $e) {
            return strtoupper(substr(md5(uniqid('', true)), 0, 8));
        }
    }

    /** "Classe: mensagem em arquivo:linha" — para o log e para a tela de desenvolvimento. */
    public static function describe(\Throwable $e): string {
        return get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine();
    }

    /**
     * O cliente espera JSON? (chamadas do admin mandam Accept: application/json
     * e/ou X-Requested-With: XMLHttpRequest.)
     *
     * @param array<string, mixed> $server
     */
    public static function wantsJson(array $server): bool {
        $accept = is_string($server['HTTP_ACCEPT'] ?? null) ? strtolower($server['HTTP_ACCEPT']) : '';
        $with   = is_string($server['HTTP_X_REQUESTED_WITH'] ?? null) ? strtolower($server['HTTP_X_REQUESTED_WITH']) : '';

        if ($with === 'xmlhttprequest') {
            return true;
        }

        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }

    /**
     * Cabeçalhos da resposta de erro. Sempre sem cache: uma falha passageira
     * não pode ficar guardada em proxy, CDN ou navegador.
     *
     * @return list<string>
     */
    public static function headers(bool $json): array {
        return [
            self::STATUS,
            $json ? 'Content-Type: application/json; charset=utf-8' : 'Content-Type: text/html; charset=utf-8',
            'Cache-Control: no-store',
            'X-Robots-Tag: noindex',
            'X-Content-Type-Options: nosniff',
        ];
    }

    /**
     * Nomes de cabeçalho que a resposta de erro define e que, por isso, não
     * podem sobrar de quem falhou.
     *
     * @param array<int, string> $headers
     * @return list<string>
     */
    public static function withoutOverridden(array $headers): array {
        $kept = [];

        foreach ($headers as $header) {
            $name = strtolower(trim((string)strstr($header . ':', ':', true)));

            if (str_starts_with($name, 'http/') || in_array($name, ['content-type', 'cache-control', 'x-robots-tag', 'x-content-type-options'], true)) {
                continue;
            }

            $kept[] = $header;
        }

        return $kept;
    }

    public static function html(string $language, string $siteName, string $home, string $reference, ?string $detail = null): string {
        $text = self::TEXT[strtolower($language)] ?? self::TEXT['en-us'];
        $lang = isset(self::TEXT[strtolower($language)]) ? strtolower($language) : 'en-us';
        $e    = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Só http(s) ou caminho local entram no link; o resto vira "/".
        if (preg_match('~^(https?://|/(?!/))[^\s"\'<>]*$~i', $home) !== 1) {
            $home = '/';
        }

        $html  = '<!DOCTYPE html><html lang="' . $e(substr($lang, 0, 2)) . '"><head><meta charset="utf-8">';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">';
        $html .= '<title>' . $e($text['title']) . ($siteName !== '' ? ' · ' . $e($siteName) : '') . '</title>';
        $html .= '<style>html{color-scheme:light dark}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
            . 'font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f6f7f9;color:#1b1f24}'
            . 'main{max-width:34rem;padding:2rem}h1{font-size:1.6rem;margin:0 0 .5rem}p{margin:.5rem 0}'
            . '.s{font-size:.85rem;opacity:.7;letter-spacing:.04em;text-transform:uppercase}.r{font-size:.85rem;opacity:.7}'
            . 'a{color:inherit}pre{white-space:pre-wrap;word-break:break-word;font-size:.85rem;padding:1rem;border:1px solid;border-radius:.5rem;opacity:.9}'
            . '@media (prefers-color-scheme:dark){body{background:#14171a;color:#e8eaed}}</style></head><body><main>';

        if ($siteName !== '') {
            $html .= '<p class="s">' . $e($siteName) . '</p>';
        }

        $html .= '<h1>' . $e($text['title']) . '</h1><p>' . $e($text['body']) . '</p>';
        $html .= '<p><a href="' . $e($home) . '">' . $e($text['home']) . '</a></p>';
        $html .= '<p class="r">' . $e($text['ref']) . ': ' . $e($reference) . '</p>';

        if ($detail !== null && $detail !== '') {
            $html .= '<pre>' . $e($detail) . '</pre>';
        }

        return $html . '</main></body></html>';
    }

    public static function json(string $language, string $reference, ?string $detail = null): string {
        $text    = self::TEXT[strtolower($language)] ?? self::TEXT['en-us'];
        $payload = ['success' => false, 'error' => $text['title'] . '. ' . $text['body'], 'ref' => $reference];

        if ($detail !== null && $detail !== '') {
            $payload['detail'] = $detail;
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? '{"success":false}' : $json;
    }

    /** 404 mínimo para a instalação em que nem a rota de erro da aplicação existe. */
    public static function notFound(): string {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
            . '<title>404 Not Found</title></head><body><h1>404 Not Found</h1></body></html>';
    }

    /**
     * Envia a resposta de erro direto ao cliente, sem passar pelo Response.
     * Usado fora do dispatch: tratador global de exceções e erro fatal.
     * Se o cabeçalho já saiu, não há como trocar o status.
     *
     * @param array<string, mixed> $server
     */
    public static function send(array $server, string $language, string $siteName, string $home, string $reference, ?string $detail = null): void {
        $json = self::wantsJson($server);

        if (PHP_SAPI !== 'cli' && headers_sent()) {
            // A resposta já começou a sair (falha depois da saída): colar uma
            // página inteira no fim de outra não ajuda ninguém. Só o detalhe
            // de desenvolvimento, quando pedido, ainda é escrito.
            if ($detail !== null && $detail !== '') {
                echo $json ? self::json($language, $reference, $detail) : '<pre>' . htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
            }

            return;
        }

        if (PHP_SAPI !== 'cli') {
            foreach (self::headers($json) as $header) {
                header($header);
            }
        }

        echo $json
            ? self::json($language, $reference, $detail)
            : self::html($language, $siteName, $home, $reference, $detail);
    }
}
