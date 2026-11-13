<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\Csp;

/**
 * S8 (ADR 0093): a politica de conteudo e montada em UM ponto. Estes testes
 * travam o que ela promete: script so do site + nonce, sem eval, e nenhuma
 * linha de configuracao consegue reabrir script embutido.
 */
final class CspTest extends TestCase {
    /** @return array<string, list<string>> */
    private static function parse(string $policy): array {
        $out = [];

        foreach (explode(';', $policy) as $part) {
            $tokens = preg_split('/\s+/', trim($part), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($tokens !== []) {
                $out[(string)array_shift($tokens)] = array_values($tokens);
            }
        }

        return $out;
    }

    // ── Modo ──────────────────────────────────────────────────────────────

    /** @return array<string, array{0: mixed, 1: string}> */
    public static function modes(): array {
        return [
            'padrao'            => ['enforce', Csp::MODE_ENFORCE],
            'report'            => ['report', Csp::MODE_REPORT],
            'REPORT com espaco' => [' Report ', Csp::MODE_REPORT],
            'off'               => ['off', Csp::MODE_OFF],
            'erro de digitacao' => ['reprot', Csp::MODE_ENFORCE],
            'vazio'             => ['', Csp::MODE_ENFORCE],
            'false'             => [false, Csp::MODE_ENFORCE],
            'null'              => [null, Csp::MODE_ENFORCE],
            'lista'             => [['off'], Csp::MODE_ENFORCE],
            'zero'              => [0, Csp::MODE_ENFORCE],
        ];
    }

    #[DataProvider('modes')]
    public function testOnlyTheTwoKnownValuesLoosenThePolicy(mixed $value, string $expected): void {
        self::assertSame($expected, Csp::mode($value));
        self::assertSame($expected, (new Csp('catalog', $value))->getMode());
    }

    public function testHeaderNameFollowsTheMode(): void {
        self::assertStringStartsWith('Content-Security-Policy: ', (new Csp('catalog'))->header());
        self::assertStringStartsWith('Content-Security-Policy-Report-Only: ', (new Csp('catalog', 'report'))->header());
        self::assertSame('', (new Csp('catalog', 'off'))->header());
    }

    // ── Politica base ─────────────────────────────────────────────────────

    /** @return array<string, array{0: string}> */
    public static function apps(): array {
        return ['catalog' => ['catalog'], 'Catalog' => ['Catalog'], 'admin' => ['admin'], 'Admin' => ['Admin']];
    }

    #[DataProvider('apps')]
    public function testScriptsComeOnlyFromTheSiteAndTheNonce(string $app): void {
        $csp = new Csp($app);
        $d   = self::parse($csp->policy());

        self::assertSame(["'self'", "'nonce-" . $csp->nonce() . "'"], $d['script-src']);
        self::assertSame(["'self'"], $d['default-src']);
        self::assertSame(["'none'"], $d['object-src']);
        self::assertSame(["'self'"], $d['base-uri']);
        self::assertSame(["'self'"], $d['frame-ancestors']);
        self::assertStringNotContainsString('unsafe-eval', $csp->policy());
        self::assertStringNotContainsString('script-src-attr', $csp->policy());
        self::assertNotContains('*', array_merge(...array_values($d)));
        self::assertNotContains('https:', $d['script-src']);
        self::assertNotContains('https:', $d['connect-src']);
        self::assertNotContains('https:', $d['frame-src']);
    }

    public function testCatalogPolicyIsExactlyTheApprovedOne(): void {
        $csp = new Csp('catalog');

        self::assertSame(
            "default-src 'self'; script-src 'self' 'nonce-" . $csp->nonce() . "'; style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: blob: https:; font-src 'self' data:; connect-src 'self' blob:; media-src 'self' https:; "
            . "frame-src https://www.youtube-nocookie.com https://player.vimeo.com; object-src 'none'; base-uri 'self'; "
            . "form-action 'self'; frame-ancestors 'self'",
            $csp->policy()
        );
    }

    public function testAdminAddsOnlyWhatThePanelUses(): void {
        $admin   = self::parse((new Csp('admin'))->policy());
        $catalog = self::parse((new Csp('catalog'))->policy());

        self::assertSame(['https://fonts.googleapis.com'], array_values(array_diff($admin['style-src'], $catalog['style-src'])));
        self::assertSame(['https://fonts.gstatic.com'], array_values(array_diff($admin['font-src'], $catalog['font-src'])));
        self::assertSame(['https://auth.mercadopago.com.br'], array_values(array_diff($admin['form-action'], $catalog['form-action'])));
        self::assertSame(["'self'"], array_values(array_diff($admin['frame-src'], $catalog['frame-src'])));
        self::assertSame(['blob:'], array_values(array_diff($admin['media-src'], $catalog['media-src'])));
        self::assertSame($catalog['connect-src'], $admin['connect-src']);
        self::assertSame($catalog['img-src'], $admin['img-src']);
    }

    public function testTheInstallerGetsNoPolicy(): void {
        foreach (['install', 'Install', '', 'outro'] as $app) {
            self::assertSame('', (new Csp($app))->header(), $app);
            self::assertSame('', (new Csp($app, 'report'))->header(), $app);
            self::assertSame('', (new Csp($app))->policy(), $app);
        }
    }

    // ── Nonce ─────────────────────────────────────────────────────────────

    public function testNonceIsStableWithinARequestAndDiffersBetweenRequests(): void {
        $a = new Csp('catalog');
        $b = new Csp('catalog');

        self::assertSame($a->nonce(), $a->nonce());
        self::assertNotSame($a->nonce(), $b->nonce());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{24}$/', $a->nonce());
        self::assertStringContainsString("'nonce-" . $a->nonce() . "'", $a->header());
    }

    // ── Excecao do editor antigo ──────────────────────────────────────────

    public function testInlineScriptExceptionReplacesTheNonceOnlyOnThatInstance(): void {
        $legacy = new Csp('admin');
        $legacy->allowInlineScripts();
        $d = self::parse($legacy->policy());

        self::assertTrue($legacy->allowsInlineScripts());
        self::assertSame(["'self'", "'unsafe-inline'"], $d['script-src']);
        // Com nonce na lista o navegador ignoraria 'unsafe-inline'.
        self::assertStringNotContainsString("'nonce-", $legacy->policy());
        self::assertStringNotContainsString('unsafe-eval', $legacy->policy());
        self::assertStringNotContainsString('cke4.ckeditor.com', $legacy->policy());
        self::assertFalse((new Csp('admin'))->allowsInlineScripts());
    }

    // ── Origens adicionais (REAMUR_CSP_SOURCES) ───────────────────────────

    public function testExtraSourcesAreAppendedToTheCatalogOnly(): void {
        $extra = [
            'script-src'  => 'https://cdn.exemplo.com https://plausible.io/js/',
            'connect-src' => ['https://api.exemplo.com', 'https://api.exemplo.com'],
            'font-src'    => 'https://fonts.gstatic.com, https://*.exemplo.com:8443',
        ];
        $csp = new Csp('catalog', 'enforce', $extra);
        $d   = self::parse($csp->policy());

        self::assertSame(["'self'", "'nonce-" . $csp->nonce() . "'", 'https://cdn.exemplo.com', 'https://plausible.io/js/'], $d['script-src']);
        self::assertSame(["'self'", 'blob:', 'https://api.exemplo.com'], $d['connect-src']);
        self::assertSame(["'self'", 'data:', 'https://fonts.gstatic.com', 'https://*.exemplo.com:8443'], $d['font-src']);

        $admin = new Csp('admin', 'enforce', $extra);
        self::assertStringNotContainsString('exemplo.com', $admin->policy());
        self::assertStringNotContainsString('plausible', $admin->policy());
    }

    /** @return array<string, array{0: mixed}> */
    public static function rejectedSources(): array {
        return [
            'unsafe-inline'          => ["'unsafe-inline'"],
            'unsafe-eval'            => ["'unsafe-eval'"],
            'unsafe-hashes'          => ["'unsafe-hashes'"],
            'strict-dynamic'         => ["'strict-dynamic'"],
            'self'                   => ["'self'"],
            'none'                   => ["'none'"],
            'curinga'                => ['*'],
            'esquema https'          => ['https:'],
            'esquema data'           => ['data:'],
            'esquema blob'           => ['blob:'],
            'http'                   => ['http://exemplo.com'],
            'so curinga de host'     => ['https://*'],
            'curinga no meio'        => ['https://cdn.*.com'],
            'sem ponto'              => ['https://localhost'],
            'ponto e virgula'        => ["https://a.com; script-src 'unsafe-inline'"],
            'quebra de linha'        => ["https://a.com\nX-Injected: 1"],
            'aspas'                  => ["https://a.com/'unsafe-inline'"],
            'virgula no caminho'     => ['https://a.com/x,y'],
            'usuario'                => ['https://user@a.com'],
            'nonce'                  => ["'nonce-abc'"],
            'numero'                 => [42],
            'null'                   => [null],
            'lista aninhada'         => [[['https://a.com']]],
        ];
    }

    #[DataProvider('rejectedSources')]
    public function testConfigurationCannotReopenInlineScriptsOrInjectDirectives(mixed $source): void {
        $base   = new Csp('catalog');
        $csp    = new Csp('catalog', 'enforce', ['script-src' => is_array($source) ? $source : [$source]]);
        $expect = str_replace($base->nonce(), $csp->nonce(), $base->policy());

        self::assertSame($expect, $csp->policy());
        self::assertStringNotContainsString("\n", $csp->header());
    }

    public function testUnknownDirectivesAndBadShapesAreIgnored(): void {
        self::assertSame([], Csp::sources('https://a.com'));
        self::assertSame([], Csp::sources(null));
        self::assertSame([], Csp::sources([
            'default-src'     => 'https://a.com',
            'object-src'      => 'https://a.com',
            'base-uri'        => 'https://a.com',
            'frame-ancestors' => 'https://a.com',
            'script-src-attr' => 'https://a.com',
            'sandbox'         => 'https://a.com',
            0                 => 'https://a.com',
        ]));
        self::assertSame(['img-src' => ['https://a.com']], Csp::sources(['IMG-SRC ' => " https://a.com \t"]));
    }

    // ── HSTS ──────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: mixed, 2: string}> */
    public static function hstsCases(): array {
        $six = 'Strict-Transport-Security: max-age=15552000';

        return [
            'site https'              => ['https://davidalmeida.xyz/', 15552000, $six],
            'https com caminho'       => ['https://exemplo.com/loja/', 15552000, $six],
            'HTTPS maiusculo'         => ['HTTPS://Exemplo.com/', 15552000, $six],
            'prazo como texto'        => ['https://exemplo.com/', '86400', 'Strict-Transport-Security: max-age=86400'],
            'prazo invalido = padrao' => ['https://exemplo.com/', 'seis meses', $six],
            'prazo null = padrao'     => ['https://exemplo.com/', null, $six],
            'teto de dois anos'       => ['https://exemplo.com/', 999999999, 'Strict-Transport-Security: max-age=63072000'],
            'site http'               => ['http://exemplo.com/', 15552000, ''],
            'zero desliga'            => ['https://exemplo.com/', 0, ''],
            'negativo desliga'        => ['https://exemplo.com/', -1, ''],
            'localhost'               => ['https://localhost/', 15552000, ''],
            'localhost com porta'     => ['https://localhost:8443/', 15552000, ''],
            'subdominio localhost'    => ['https://site.localhost/', 15552000, ''],
            'ipv4'                    => ['https://192.168.0.10/', 15552000, ''],
            'ipv6'                    => ['https://[::1]/', 15552000, ''],
            'sem endereco'            => ['', 15552000, ''],
            'so barra'                => ['/', 15552000, ''],
            'lixo'                    => ['https://', 15552000, ''],
        ];
    }

    #[DataProvider('hstsCases')]
    public function testHstsOnlyForAConfiguredHttpsDomain(string $site, mixed $maxAge, string $expected): void {
        self::assertSame($expected, Csp::hsts($site, $maxAge));
    }

    public function testHstsNeverCoversSubdomainsNorPreload(): void {
        $header = Csp::hsts('https://exemplo.com/');

        self::assertSame('Strict-Transport-Security: max-age=' . Csp::HSTS_DEFAULT_MAX_AGE, $header);
        self::assertStringNotContainsStringIgnoringCase('includeSubDomains', $header);
        self::assertStringNotContainsStringIgnoringCase('preload', $header);
    }

    // ── Response::setHeader ───────────────────────────────────────────────

    public function testSetHeaderReplacesByNameAndIgnoresEmpty(): void {
        $response = new Response();
        $response->addHeader('X-Frame-Options: SAMEORIGIN');
        $response->setHeader('Content-Security-Policy: a');
        $response->setHeader('content-security-policy: b');
        $response->setHeader('');
        $response->setHeader('sem-dois-pontos');
        $response->setHeader(': vazio');
        $response->setHeader("X-Teste: 1\r\nX-Injected: 2");

        self::assertSame(
            ['X-Frame-Options: SAMEORIGIN', 'content-security-policy: b', 'X-Teste: 1X-Injected: 2'],
            array_values($response->getHeaders())
        );
    }

    public function testSetHeaderDoesNotTouchTheReportOnlyVariant(): void {
        $response = new Response();
        $response->setHeader('Content-Security-Policy-Report-Only: a');
        $response->setHeader('Content-Security-Policy: b');

        self::assertCount(2, $response->getHeaders());
    }
}
