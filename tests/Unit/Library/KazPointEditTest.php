<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Template\Twig;
use Reamur\System\Library\Theme\ThemeCanvasSample;
use Reamur\System\Library\Theme\ThemePackage;

/**
 * KZ-02 (ADR 0084): apontar e editar. Cobre os marcadores de origem que o
 * canvas usa para saber de qual fonte Twig veio cada trecho da pagina.
 */
final class KazPointEditTest extends TestCase {
    private const MARKER = '/<!--kz:([be]):([a-z0-9_\/-]+)-->/';

    /** KZ-04 (ADR 0086): o modo canvas tambem ancora cada elemento a sua fonte. */
    private const ANCHOR = '/ data-kz-el="[a-z0-9_\/-]+:\d+"/';

    private function twig(): Twig {
        return new Twig(DIR_TEMPLATE, false);
    }

    /** @return array<string,mixed> */
    private function context(string $route): array {
        return ThemeCanvasSample::context($route, ThemeCanvasSample::strings('en-us', $route));
    }

    /** @param array<string,mixed> $page */
    private function html(array $page): string {
        return $page['header'] . $page['main'] . $page['footer'];
    }

    public function testPlainRenderCarriesNoMarkersAndNoDefaults(): void {
        $page = $this->twig()->renderThemePage('common/home', [], $this->context('common/home'));

        self::assertStringNotContainsString('<!--kz:', $this->html($page));
        self::assertSame([], $page['defaults']);
    }

    public function testMarkersAreBalancedAllowlistedAndInvisibleOnEveryRoute(): void {
        $twig = $this->twig();
        foreach (ThemeCanvasSample::routes() as $route) {
            $plain = $twig->renderThemePage($route, [], $this->context($route));
            $marked = $twig->renderThemePage($route, [], $this->context($route), [], [], true);
            $html = $this->html($marked);

            self::assertSame([], $marked['errors'], $route);
            // Sem os marcadores e as ancoras de elemento, a pagina e byte a byte a mesma.
            self::assertSame($this->html($plain), preg_replace([self::MARKER, self::ANCHOR], '', $html), $route);

            preg_match_all(self::MARKER, $html, $found, PREG_SET_ORDER);
            self::assertNotEmpty($found, $route);
            $stack = [];
            foreach ($found as $hit) {
                self::assertContains($hit[2], ThemePackage::TEMPLATE_KEYS, $route);
                if ($hit[1] === 'b') {
                    $stack[] = $hit[2];
                    continue;
                }
                self::assertSame($hit[2], array_pop($stack), $route);
            }
            self::assertSame([], $stack, $route);
            // Nada alem dos marcadores conhecidos comeca com o prefixo reservado.
            self::assertSame(count($found), substr_count($html, '<!--kz:'), $route);
        }
    }

    public function testDefaultsListOnlyPiecesOfThePageTheThemeDoesNotRedefine(): void {
        $source = '<section id="about"><h2>{{ heading_title }}</h2></section>';
        $page = $this->twig()->renderThemePage('common/home', ['partial/about' => $source], $this->context('common/home'), [], [], true);

        self::assertSame('theme', $page['sources']['partial/about']);
        self::assertArrayNotHasKey('partial/about', $page['defaults']);
        self::assertArrayHasKey('partial/services', $page['defaults']);
        self::assertArrayHasKey('common/footer', $page['defaults']);
        // Fonte de outra rota nao acompanha a pagina inicial.
        self::assertArrayNotHasKey('blog/blog_info', $page['defaults']);
        self::assertSame(
            (string)file_get_contents(DIR_SYSTEM . '../catalog/view/template/partial/services.twig'),
            $page['defaults']['partial/services']
        );
        self::assertStringContainsString('<!--kz:b:partial/about--><section data-kz-el="partial/about:0" id="about">', $this->html($page));
    }

    public function testDefaultSourceRendersTheSamePageWhenAdoptedByTheTheme(): void {
        $twig = $this->twig();
        $before = $twig->renderThemePage('common/home', [], $this->context('common/home'), [], [], true);
        $adopted = ['partial/services' => $before['defaults']['partial/services']];
        $after = $twig->renderThemePage('common/home', $adopted, $this->context('common/home'), [], [], true);

        self::assertSame([], $after['errors']);
        self::assertSame('theme', $after['sources']['partial/services']);
        self::assertSame($this->html($before), $this->html($after));
    }

    public function testInheritedTemplateIsLeftUnmarkedAndStillRenders(): void {
        $child = "{% extends 'partial/about.twig' %}";
        $page = $this->twig()->renderThemePage('common/home', ['partial/services' => $child], $this->context('common/home'), [], [], true);

        self::assertSame([], $page['errors']);
        self::assertStringNotContainsString('<!--kz:b:partial/services-->', $this->html($page));
    }

    public function testMarkerTextComingFromPageDataIsEscaped(): void {
        $context = $this->context('common/home');
        $context['heading_title'] = '<!--kz:b:common/footer-->';
        $source = '<section><h2>{{ heading_title }}</h2></section>';
        $page = $this->twig()->renderThemePage('common/home', ['partial/about' => $source], $context, [], [], true);

        // Dado da pagina passa pelo autoescape: nunca vira um marcador.
        self::assertStringContainsString('<h2 data-kz-el="partial/about:1">&lt;!--kz:b:common/footer--&gt;</h2>', $this->html($page));
        self::assertSame(1, substr_count($this->html($page), '<!--kz:b:common/footer-->'));
    }

    public function testPhpWarningWhileRenderingBecomesTemplateErrorWithFallback(): void {
        // Lista impressa como texto: no Catalog o framework transforma o aviso em excecao.
        $source = '<section>{% for img in [["a"], ["b"]] %}{{ img }}{% endfor %}</section>';
        $handler = set_error_handler(null);
        restore_error_handler();
        // O PHPUnit reduz o nivel de erros durante os testes; o site roda com E_ALL.
        $level = error_reporting(E_ALL);

        try {
            $page = $this->twig()->renderThemePage('common/home', ['partial/about' => $source], $this->context('common/home'), [], [], true);
        } finally {
            error_reporting($level);
        }

        self::assertArrayHasKey('partial/about', $page['errors']);
        self::assertStringContainsString('Array to string conversion', $page['errors']['partial/about']);
        self::assertContains('partial/about', $page['fallbacks']);
        self::assertStringNotContainsString('Array to string conversion', $this->html($page));
        // O tratador de erros anterior volta a valer depois do render.
        $current = set_error_handler(null);
        restore_error_handler();
        self::assertSame($handler, $current);
    }
}
