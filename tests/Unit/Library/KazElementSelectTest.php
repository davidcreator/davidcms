<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Template\Twig;
use Reamur\System\Library\Theme\ThemeCanvasSample;
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\ThemeSourceMap;

/**
 * KZ-04 (ADR 0086): selecionar um elemento dentro do trecho. Cobre o mapa de
 * elementos de uma fonte Twig e a garantia de que a anotacao so existe no
 * canvas e nunca muda a pagina.
 */
final class KazElementSelectTest extends TestCase {
    private const ATTR = '/ data-kz-el="([a-z0-9_\/-]+):(\d+)"/';

    private const SAMPLE = <<<'TWIG'
<section class="hero" id="a">
  <h1>Olá, <b>mundo</b></h1>
  <h2 class="t">  Título fixo &amp; simples  </h2>
  <p>{{ heading_title }}</p>
  <p>Oi {{ name }}!</p>
  <a href="{{ url }}" title="a > b">Ver mais</a>
  <img src="x.png" alt="x"><br/>
  {% for i in items %}<li class="{{ i.c }}">{{ i.t|e }}</li>{% endfor %}
  <span></span>
  <!-- <p>comentado</p> -->
  <style>.a > .b { color: red } /* <p> */</style>
  <svg viewBox="0 0 1 1"><path d="M0 0"/><text>x</text></svg>
  {{ {'a': {'b': '<i>'}}|length }}<em>depois</em>
  {% verbatim %}<u>{{ nao }}</u>{% endverbatim %}<strong>fim</strong>
  <h{{ n }}>din</h{{ n }}>
  <button type="button" {% if x %}disabled{% endif %}>Enviar — ação 😀</button>
</section>
TWIG;

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

    /** @return list<string> */
    private function presets(): array {
        $files = glob(DIR_REAMUR . 'docs/temas/*.reamurtheme.json');

        return $files === false ? [] : $files;
    }

    public function testScanClassifiesEveryOpeningTagWrittenInTheSource(): void {
        $found = [];
        foreach (ThemeSourceMap::scan(self::SAMPLE) as $el) {
            $found[] = $el['tag'] . ':' . $el['kind'];
        }

        self::assertSame([
            'section:group', 'h1:group', 'b:fixed', 'h2:fixed', 'p:variable', 'p:mixed', 'a:fixed', 'img:void', 'br:void',
            'li:variable', 'span:empty', 'svg:group', 'em:fixed', 'strong:fixed', 'button:fixed',
        ], $found);
    }

    public function testScanNeverLooksInsideCommentsStyleSvgTwigOrVerbatim(): void {
        $tags = array_column(ThemeSourceMap::scan(self::SAMPLE), 'tag');

        // <p> do comentario e do <style>, <path>/<text> do svg, <i> da expressao, <u> do verbatim, <h{{ n }}>.
        self::assertSame(2, count(array_keys($tags, 'p', true)));
        foreach (['style', 'path', 'text', 'i', 'u', 'h'] as $tag) {
            self::assertNotContains($tag, $tags);
        }
    }

    public function testTextRangeIsExactlyTheWrittenTextAndExpressionIsReported(): void {
        $byTag = [];
        foreach (ThemeSourceMap::scan(self::SAMPLE) as $el) {
            $byTag[$el['tag']][] = $el;
        }
        $slice = static fn (array $el): string => substr(self::SAMPLE, $el['text'][0], $el['text'][1] - $el['text'][0]);

        self::assertSame('  Título fixo &amp; simples  ', $slice($byTag['h2'][0]));
        self::assertSame('Ver mais', $slice($byTag['a'][0]));
        self::assertSame('Enviar — ação 😀', $slice($byTag['button'][0]));
        self::assertSame('heading_title', $byTag['p'][0]['expr']);
        self::assertSame('i.t|e', $byTag['li'][0]['expr']);
        self::assertSame('', $byTag['p'][1]['expr']);
        self::assertNull($byTag['h1'][0]['text']);
    }

    public function testAnnotateOnlyAddsTheClosedFormatAttribute(): void {
        $elements = ThemeSourceMap::scan(self::SAMPLE);
        $with = ThemeSourceMap::annotate('partial/hero', self::SAMPLE, $elements);

        self::assertSame(self::SAMPLE, preg_replace(self::ATTR, '', $with));
        preg_match_all(self::ATTR, $with, $found, PREG_SET_ORDER);
        self::assertCount(count($elements), $found);
        foreach ($found as $n => $hit) {
            self::assertSame('partial/hero', $hit[1]);
            self::assertSame((string)$n, $hit[2]);
        }
        self::assertStringContainsString('<button data-kz-el="partial/hero:14" type="button" {% if x %}disabled{% endif %}>', $with);
    }

    public function testAnnotateRefusesAKeyOutsideTheClosedFormat(): void {
        $elements = ThemeSourceMap::scan('<p>a</p>');

        foreach (['', 'a"b', 'Partial/Hero', 'x y', "a\n", str_repeat('a', 65)] as $key) {
            self::assertSame('<p>a</p>', ThemeSourceMap::annotate($key, '<p>a</p>', $elements), json_encode($key));
        }
    }

    public function testDescribeUsesEditorOffsetsAndASignatureOfTheSource(): void {
        $source = "<p>ação 😀</p>\n<h2>Título</h2>";
        $map = ThemeSourceMap::describe($source, ThemeSourceMap::scan($source));
        $utf16 = mb_convert_encoding($source, 'UTF-16LE', 'UTF-8');
        $cut = static fn (int $from, int $to): string => mb_convert_encoding(substr($utf16, $from * 2, ($to - $from) * 2), 'UTF-8', 'UTF-16LE');

        self::assertSame(sprintf('%08x:%d', crc32($source), strlen($utf16) / 2), $map['sig']);
        self::assertSame(ThemeSourceMap::signature($source), $map['sig']);
        self::assertCount(2, $map['items']);
        [$tag, $kind, $openStart, $openEnd, $textStart, $textEnd, $expr] = $map['items'][1];
        self::assertSame(['h2', 'fixed', ''], [$tag, $kind, $expr]);
        // As posicoes valem para a string como o JavaScript do editor a enxerga (UTF-16).
        self::assertSame('<h2>', $cut($openStart, $openEnd));
        self::assertSame('Título', $cut($textStart, $textEnd));
        self::assertSame('ação 😀', $cut($map['items'][0][4], $map['items'][0][5]));
    }

    public function testNormalizeMatchesWhatATextareaDelivers(): void {
        self::assertSame("a\nb\nc\n", ThemeSourceMap::normalize("a\r\nb\rc\n"));
        self::assertNotSame(ThemeSourceMap::signature("a\r\nb"), ThemeSourceMap::signature("a\nb"));
    }

    public function testScanIsCappedAndSurvivesBrokenMarkup(): void {
        self::assertCount(ThemeSourceMap::MAX_ELEMENTS, ThemeSourceMap::scan(str_repeat('<i>x</i>', ThemeSourceMap::MAX_ELEMENTS + 50)));

        foreach (['<', '<p', '<p class="x', '{{ a', '{% if', '{# c', '<p>{{ "}}" }}</p', '</p></p>', '<<<>>>', "<p\0>x</p>"] as $broken) {
            $elements = ThemeSourceMap::scan($broken);
            self::assertSame($broken, preg_replace(self::ATTR, '', ThemeSourceMap::annotate('common/home', $broken, $elements)), json_encode($broken));
        }
    }

    public function testPlainRenderNeverCarriesElementAnchors(): void {
        $twig = $this->twig();
        foreach (ThemeCanvasSample::routes() as $route) {
            $page = $twig->renderThemePage($route, [], $this->context($route));

            self::assertStringNotContainsString(ThemeSourceMap::ATTR, $this->html($page), $route);
            self::assertSame([], $page['elements'], $route);
        }
    }

    public function testAnnotatedRenderIsTheSamePagePlusAnchorsOnEveryRoute(): void {
        $twig = $this->twig();
        foreach (ThemeCanvasSample::routes() as $route) {
            $plain = $twig->renderThemePage($route, [], $this->context($route), [], [], false);
            $marked = $twig->renderThemePage($route, [], $this->context($route), [], [], true);
            $strip = static fn (string $html): string => (string)preg_replace(['/<!--kz:[be]:[a-z0-9_\/-]+-->/', self::ATTR], '', $html);

            self::assertSame([], $marked['errors'], $route);
            self::assertSame($this->html($plain), $strip($this->html($marked)), $route);
            self::assertNotEmpty($marked['elements'], $route);

            preg_match_all(self::ATTR, $this->html($marked), $found, PREG_SET_ORDER);
            self::assertNotEmpty($found, $route);
            foreach ($found as $hit) {
                self::assertContains($hit[1], ThemePackage::TEMPLATE_KEYS, $route);
                self::assertArrayHasKey($hit[1], $marked['elements'], $route);
                self::assertArrayHasKey((int)$hit[2], $marked['elements'][$hit[1]]['items'], $route);
            }
        }
    }

    public function testEveryDeliveredPresetKeepsItsTwigAndGetsAMap(): void {
        $twig = $this->twig();
        $presets = $this->presets();
        self::assertNotEmpty($presets);

        foreach ($presets as $file) {
            $package = (array)json_decode((string)file_get_contents($file), true);
            $templates = [];
            foreach ((array)($package['config']['templates'] ?? []) as $key => $source) {
                if (is_string($source) && $source !== '') {
                    $templates[(string)$key] = $source;
                }
            }
            $plain = $twig->renderThemePage('common/home', $templates, $this->context('common/home'), [], [], false);
            $marked = $twig->renderThemePage('common/home', $templates, $this->context('common/home'), [], [], true);
            $strip = static fn (string $html): string => (string)preg_replace(['/<!--kz:[be]:[a-z0-9_\/-]+-->/', self::ATTR], '', $html);
            $name = basename($file);

            self::assertSame($marked['errors'], $plain['errors'], $name);
            self::assertSame($this->html($plain), $strip($this->html($marked)), $name);
            foreach ($marked['elements'] as $key => $map) {
                $source = ThemeSourceMap::normalize((string)($templates[$key] ?? $marked['defaults'][$key] ?? ''));
                self::assertSame(ThemeSourceMap::signature($source), $map['sig'], $name . ' ' . $key);
            }
        }
    }

    public function testAuthorWrittenAnchorIsKeptAsWrittenAndNeverTrusted(): void {
        $source = '<p data-kz-el="common/home:9999">a</p>';
        $page = $this->twig()->renderThemePage('common/home', ['common/footer' => $source], $this->context('common/home'), [], [], true);

        // O atributo do autor fica depois do nosso (o navegador usa o primeiro) e aponta para um item que nao existe.
        self::assertStringContainsString('<p data-kz-el="common/footer:0" data-kz-el="common/home:9999">a</p>', $this->html($page));
        self::assertArrayNotHasKey(9999, $page['elements']['common/home']['items']);
        self::assertSame(['p', 'fixed'], array_slice($page['elements']['common/footer']['items'][0], 0, 2));
    }

    public function testPageDataCannotForgeAnAnchor(): void {
        $context = $this->context('common/home');
        $context['heading_title'] = '<b data-kz-el="common/home:0">x</b>';
        $page = $this->twig()->renderThemePage('common/home', [], $context, [], [], true);
        $html = $this->html($page);

        self::assertStringContainsString('&lt;b data-kz-el=&quot;common/home:0&quot;&gt;x&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b data-kz-el="common/home:0">x</b>', $html);
    }
}
