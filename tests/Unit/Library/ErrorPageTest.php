<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Library\ErrorPage;

/**
 * ADR 0088: a pagina 500 e fixa, sem banco, tema ou template, e tudo que
 * entra nela e escapado.
 */
final class ErrorPageTest extends TestCase {
    public function testPageCarriesSiteNameHomeLinkAndReference(): void {
        $html = ErrorPage::html('pt-br', 'Meu Site', 'https://example.test/', 'AB12CD34');

        self::assertStringStartsWith('<!DOCTYPE html><html lang="pt">', $html);
        self::assertStringContainsString('<title>Erro temporário · Meu Site</title>', $html);
        self::assertStringContainsString('<h1>Erro temporário</h1>', $html);
        self::assertStringContainsString('<a href="https://example.test/">', $html);
        self::assertStringContainsString('AB12CD34', $html);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $html);
        self::assertStringNotContainsString('<pre>', $html, 'sem detalhe, sem bloco de detalhe');
    }

    public function testPageIsSelfContained(): void {
        $html = ErrorPage::html('pt-br', 'Meu Site', 'https://example.test/', 'AB12CD34');

        // Nada a buscar em outro lugar: a pagina precisa abrir com o site quebrado.
        self::assertStringNotContainsString('<link', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('url(', $html);
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function languages(): array {
        return [
            'portugues'        => ['pt-br', 'pt', 'Erro temporário'],
            'ingles'           => ['en-us', 'en', 'Temporary error'],
            'espanhol'         => ['es-eu', 'es', 'Error temporal'],
            'maiusculas'       => ['PT-BR', 'pt', 'Erro temporário'],
            'desconhecido'     => ['xx-yy', 'en', 'Temporary error'],
            'vazio'            => ['', 'en', 'Temporary error'],
            'tentativa de tag' => ['"><script>', 'en', 'Temporary error'],
        ];
    }

    #[DataProvider('languages')]
    public function testLanguageFallsBackToEnglish(string $language, string $lang, string $title): void {
        $html = ErrorPage::html($language, '', '/', 'AB12CD34');

        self::assertStringContainsString('<html lang="' . $lang . '">', $html);
        self::assertStringContainsString('<title>' . $title . '</title>', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    public function testEverythingThatEntersThePageIsEscaped(): void {
        $html = ErrorPage::html(
            'pt-br',
            'Loja <script>alert(1)</script> & "Cia"',
            'https://example.test/',
            '<img src=x>',
            "PDOException: <b>x</b> 'aspas' & \"duplas\""
        );

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<b>x</b>', $html);
        self::assertStringContainsString('Loja &lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;Cia&quot;', $html);
        self::assertStringContainsString('<pre>PDOException: &lt;b&gt;x&lt;/b&gt; &#039;aspas&#039; &amp; &quot;duplas&quot;</pre>', $html);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function homeLinks(): array {
        return [
            'https'                 => ['https://example.test/loja/', 'https://example.test/loja/'],
            'http'                  => ['http://example.test/', 'http://example.test/'],
            'caminho local'         => ['/catalog/', '/catalog/'],
            'javascript'            => ['javascript:alert(1)', '/'],
            'data'                  => ['data:text/html,<script>1</script>', '/'],
            'relativo ao protocolo' => ['//evil.example/', '/'],
            'com aspas'             => ['https://example.test/" onclick="x', '/'],
            'com espaco'            => ['https://example.test/ x', '/'],
            'vazio'                 => ['', '/'],
        ];
    }

    #[DataProvider('homeLinks')]
    public function testHomeLinkOnlyAcceptsHttpOrLocalPath(string $home, string $expected): void {
        $html = ErrorPage::html('en-us', '', $home, 'AB12CD34');

        self::assertSame(1, preg_match('/<a href="([^"]*)">/', $html, $m));
        self::assertSame($expected, $m[1]);
    }

    public function testInvalidUtf8InDetailDoesNotBlankThePage(): void {
        $html = ErrorPage::html('en-us', '', '/', 'AB12CD34', "falha \xC3\x28 binaria");

        self::assertStringContainsString('<pre>falha ', $html);
        self::assertStringContainsString('binaria</pre>', $html);
    }

    public function testJsonShape(): void {
        $payload = json_decode(ErrorPage::json('pt-br', 'AB12CD34'), true, 8, JSON_THROW_ON_ERROR);

        self::assertSame(['success' => false, 'error' => $payload['error'], 'ref' => 'AB12CD34'], $payload);
        self::assertStringStartsWith('Erro temporário. ', $payload['error']);

        $withDetail = json_decode(ErrorPage::json('pt-br', 'AB12CD34', 'X: y em /a.php:1'), true, 8, JSON_THROW_ON_ERROR);

        self::assertSame('X: y em /a.php:1', $withDetail['detail']);
        self::assertNotSame('', ErrorPage::json('en-us', 'AB12CD34', "bin \xC3\x28"));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: bool}> */
    public static function clients(): array {
        return [
            'navegador'              => [['HTTP_ACCEPT' => 'text/html,application/xhtml+xml,*/*;q=0.8'], false],
            'sem cabecalho'          => [[], false],
            'curl padrao'            => [['HTTP_ACCEPT' => '*/*'], false],
            'accept json'            => [['HTTP_ACCEPT' => 'application/json'], true],
            'accept json maiusculo'  => [['HTTP_ACCEPT' => 'Application/JSON'], true],
            'json e html juntos'     => [['HTTP_ACCEPT' => 'text/html, application/json'], false],
            'xhr'                    => [['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], true],
            'xhr pedindo html'       => [['HTTP_X_REQUESTED_WITH' => 'xmlhttprequest', 'HTTP_ACCEPT' => 'text/html'], true],
            'cabecalho nao texto'    => [['HTTP_ACCEPT' => ['application/json']], false],
        ];
    }

    /** @param array<string, mixed> $server */
    #[DataProvider('clients')]
    public function testWantsJson(array $server, bool $expected): void {
        self::assertSame($expected, ErrorPage::wantsJson($server));
    }

    public function testErrorHeadersForbidCachingAndIndexing(): void {
        foreach ([false, true] as $json) {
            $headers = ErrorPage::headers($json);

            self::assertSame('HTTP/1.1 500 Internal Server Error', $headers[0]);
            self::assertContains('Cache-Control: no-store', $headers);
            self::assertContains('X-Robots-Tag: noindex', $headers);
            self::assertContains('X-Content-Type-Options: nosniff', $headers);
        }

        self::assertContains('Content-Type: text/html; charset=utf-8', ErrorPage::headers(false));
        self::assertContains('Content-Type: application/json; charset=utf-8', ErrorPage::headers(true));
    }

    public function testWithoutOverriddenDropsOnlyWhatTheErrorResponseSets(): void {
        $kept = ErrorPage::withoutOverridden([
            'HTTP/1.1 404 Not Found',
            'X-Frame-Options: SAMEORIGIN',
            'content-type: application/xml',
            'Cache-Control: public, max-age=3600',
            'Referrer-Policy: strict-origin-when-cross-origin',
            'X-Robots-Tag: all',
            'X-Content-Type-Options: nosniff',
            'Content-Security-Policy: default-src \'self\'',
        ]);

        self::assertSame([
            'X-Frame-Options: SAMEORIGIN',
            'Referrer-Policy: strict-origin-when-cross-origin',
            'Content-Security-Policy: default-src \'self\'',
        ], $kept);
    }

    public function testReferenceIsShortAndDoesNotRepeat(): void {
        $a = ErrorPage::reference();
        $b = ErrorPage::reference();

        self::assertMatchesRegularExpression('/^[0-9A-F]{8}$/', $a);
        self::assertNotSame($a, $b);
    }

    public function testDescribeNamesClassMessageFileAndLine(): void {
        $e = new \DomainException('algo quebrou');

        self::assertSame('DomainException: algo quebrou em ' . __FILE__ . ':' . (__LINE__ - 2), ErrorPage::describe($e));
    }

    public function testSendWritesTheSamePageOutsideTheDispatch(): void {
        ob_start();
        ErrorPage::send(['HTTP_ACCEPT' => 'text/html'], 'es-eu', 'Mi Sitio', '/', 'AB12CD34');
        $html = (string)ob_get_clean();

        ob_start();
        ErrorPage::send(['HTTP_ACCEPT' => 'application/json'], 'es-eu', 'Mi Sitio', '/', 'AB12CD34');
        $json = (string)ob_get_clean();

        self::assertSame(ErrorPage::html('es-eu', 'Mi Sitio', '/', 'AB12CD34'), $html);
        self::assertSame(ErrorPage::json('es-eu', 'AB12CD34'), $json);
    }
}
