<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Template\Twig;
use Reamur\System\Library\Theme\ThemeCanvasSample;
use Reamur\System\Library\Theme\ThemePackage;

/**
 * KZ-01 (ADR 0083): canvas do KAZEditor-Theme com a pagina real do rascunho.
 * Cobre os dados de amostra e o render em sandbox da pagina inteira.
 */
final class KazCanvasPageTest extends TestCase {
    private function twig(): Twig {
        return new Twig(DIR_TEMPLATE, false);
    }

    public function testRoutesComeFromTheTemplateAllowlistAndExcludeFragments(): void {
        $routes = ThemeCanvasSample::routes();

        self::assertNotEmpty($routes);
        self::assertContains('common/home', $routes);
        self::assertNotContains('common/footer', $routes);
        foreach ($routes as $route) {
            self::assertContains($route, ThemePackage::TEMPLATE_KEYS);
            self::assertStringStartsNotWith('partial/', $route);
            self::assertTrue(ThemeCanvasSample::isRoute($route));
        }
        self::assertFalse(ThemeCanvasSample::isRoute('partial/hero'));
        self::assertFalse(ThemeCanvasSample::isRoute('../../etc/passwd'));
        self::assertSame(['desktop', 'tablet', 'mobile'], array_keys(ThemeCanvasSample::devices()));
    }

    public function testSampleContextIsPlainDataWithoutPageShell(): void {
        $context = ThemeCanvasSample::context('common/home', ['heading_title' => 'From language', 'bad' => 'x']);

        self::assertArrayNotHasKey('header', $context);
        self::assertArrayNotHasKey('footer', $context);
        self::assertSame('From language', $context['heading_title']);
        self::assertNotEmpty($context['projects']);
        self::assertStringStartsWith('data:image/svg+xml;base64,', $context['projects'][0]['image']);
        array_walk_recursive($context, static function (mixed $value): void {
            self::assertTrue(is_scalar($value) || $value === null);
        });
        // Rota fora da allowlist nunca chega ao contexto.
        self::assertSame('common/home', ThemeCanvasSample::context('nope/nope')['canvas_route']);
    }

    public function testLanguageStringsOnlyReadAllowlistedCatalogFiles(): void {
        self::assertSame([], ThemeCanvasSample::strings('en-us', '../../system/config/admin'));
        $strings = ThemeCanvasSample::strings('../..', 'common/home');
        self::assertNotEmpty($strings);
        foreach ($strings as $value) {
            self::assertIsString($value);
        }
    }

    public function testEveryRouteRendersWithDefaultTemplatesAndNoErrors(): void {
        $twig = $this->twig();
        foreach (ThemeCanvasSample::routes() as $route) {
            $page = $twig->renderThemePage($route, [], ThemeCanvasSample::context($route, ThemeCanvasSample::strings('en-us', $route)));

            self::assertSame([], $page['errors'], $route);
            self::assertSame([], $page['fallbacks'], $route);
            self::assertNotSame('', $page['header'], $route);
            self::assertNotSame('', $page['main'], $route);
            self::assertStringNotContainsStringIgnoringCase('</body>', $page['footer'], $route);
            self::assertStringNotContainsStringIgnoringCase('<script', $page['header'] . $page['main'] . $page['footer'], $route);
        }
    }

    public function testThemeSourceOverridesIncludedPartialAndEscapesContext(): void {
        $context = ThemeCanvasSample::context('common/home');
        $context['text_tagline'] = '<b>bold</b>';
        $page = $this->twig()->renderThemePage(
            'common/home',
            ['partial/hero-3d' => '<section id="kz-hero">{{ text_tagline }}</section>'],
            $context
        );

        self::assertSame([], $page['errors']);
        self::assertSame('theme', $page['sources']['partial/hero-3d']);
        self::assertSame('default', $page['sources']['partial/about']);
        self::assertStringContainsString('<section id="kz-hero">&lt;b&gt;bold&lt;/b&gt;</section>', $page['main']);
    }

    public function testBrokenOrForbiddenThemeSourceFallsBackToDefaultTemplate(): void {
        $context = ThemeCanvasSample::context('common/home');
        $baseline = $this->twig()->renderThemePage('common/home', [], $context);
        $cases = [
            'syntax'   => '<p>{{ nope|unknownfilter }}</p>',
            'constant' => "<p>KZ-LEAK {{ constant('DIR_SYSTEM') }}</p>",
            'source'   => "<p>KZ-LEAK {{ source('common/header.twig') }}</p>",
            'include'  => "KZ-LEAK {% include '/etc/passwd' %}",
            'script'   => '<script>alert(1)</script>',
            'handler'  => '<img src="x" onerror="alert(1)">',
            'raw'      => '{{ description|raw }}',
        ];
        foreach ($cases as $name => $source) {
            $page = $this->twig()->renderThemePage('common/home', ['partial/hero-3d' => $source], $context);

            self::assertArrayHasKey('partial/hero-3d', $page['errors'], $name);
            self::assertContains('partial/hero-3d', $page['fallbacks'], $name);
            self::assertSame('default', $page['sources']['partial/hero-3d'], $name);
            self::assertSame($baseline['main'], $page['main'], $name);
            self::assertStringNotContainsString('KZ-LEAK', $page['main'], $name);
        }
    }

    public function testObjectsNeverReachTheSandboxContext(): void {
        $context = ThemeCanvasSample::context('common/home');
        $context['text_tagline'] = new \Twig\Markup('<i>trusted?</i>', 'UTF-8');
        $page = $this->twig()->renderThemePage('common/home', ['partial/hero-3d' => '<p id="kz">[{{ text_tagline }}]</p>'], $context);

        self::assertStringContainsString('<p id="kz">[]</p>', $page['main']);
    }

    public function testRouteOutsideTheCanvasAllowlistIsRejected(): void {
        foreach (['partial/hero', 'common/footer', 'common/header', '../x'] as $route) {
            try {
                $this->twig()->renderThemePage($route, [], []);
                self::fail('Route should have been rejected: ' . $route);
            } catch (\InvalidArgumentException $e) {
                self::assertSame('Route outside the canvas allowlist.', $e->getMessage());
            }
        }
    }
}
