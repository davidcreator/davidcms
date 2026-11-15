<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Template\ThemeSandbox;
use Reamur\System\Library\Theme\ThemeSourceMap;

/**
 * KZ-06 (ADR 0099): cor do texto, cor de fundo e tamanho do texto de um
 * elemento, escritos no atributo `style` da tag. Cobre a parte do servidor
 * (onde fica o `style` de cada tag e quando a tag e fechada para o editor) e a
 * ligacao entre controller, idiomas e os dois scripts. As regras de escrita
 * ficam em `dev/admin/view/js/kaz-element-attrs.js` e sao exercitadas por
 * `tests/check_kaz_element_style.js`, chamado daqui.
 */
final class KazElementStyleTest extends TestCase {
    private const KEYS = ['el_style_title', 'el_color', 'el_bg', 'el_size', 'el_style_auto', 'el_style_theme', 'el_style_custom',
                          'el_style_written', 'el_style_help', 'el_style_variable', 'el_style_locked', 'el_size_bad', 'el_color_bad', 'el_style_forced'];

    /** @return array<string,mixed> */
    private function first(string $source): array {
        $els = ThemeSourceMap::scan($source);
        self::assertNotSame([], $els, $source);

        return $els[0];
    }

    /** @param array<string,mixed> $attr */
    private function cut(string $source, array $attr, string $what): string {
        return substr($source, $attr[$what][0], $attr[$what][1] - $attr[$what][0]);
    }

    /** @return list<string> */
    private function presets(): array {
        $files = glob(DIR_REAMUR . 'docs/temas/*.reamurtheme.json');

        return $files === false ? [] : $files;
    }

    private function repo(string $path): string {
        $source = file_get_contents(DIR_REAMUR . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    public function testStyleOfAnyTagIsLocatedExactlyBetweenTheQuotes(): void {
        foreach (['h2', 'p', 'section', 'span', 'button', 'li'] as $tag) {
            $source = '<' . $tag . ' class="x" style="color:var(--at-muted);max-width:52ch" id="y">t</' . $tag . '>';
            $attrs = $this->first($source)['attrs'];

            self::assertIsArray($attrs, $tag);
            self::assertSame(['style'], array_column($attrs, 'name'), $tag);
            self::assertSame('fixed', $attrs[0]['kind'], $tag);
            self::assertSame('color:var(--at-muted);max-width:52ch', $this->cut($source, $attrs[0], 'value'), $tag);
            self::assertSame('style="color:var(--at-muted);max-width:52ch"', $this->cut($source, $attrs[0], 'span'), $tag);
        }

        // Em um link, o `style` vem junto com os atributos da KZ-05, na ordem do codigo.
        $source = "<a style='font-size:18px' href=\"/x\" STYLE2=\"n\" aria-label=\"Ir\">x</a>";
        $attrs = $this->first($source)['attrs'];
        self::assertSame(['style', 'href', 'aria-label'], array_column((array)$attrs, 'name'));
        self::assertSame('font-size:18px', $this->cut($source, $attrs[0], 'value'));
        self::assertSame("style='font-size:18px'", $this->cut($source, $attrs[0], 'span'));

        // Nome em maiusculas e valor vazio.
        $attrs = $this->first('<p STYLE="">x</p>')['attrs'];
        self::assertSame([['style', 'fixed']], array_map(static fn (array $a): array => [$a['name'], $a['kind']], (array)$attrs));
        self::assertSame($attrs[0]['value'][0], $attrs[0]['value'][1]);

        // `data-style` e `styles` nao sao `style`.
        self::assertSame([], $this->first('<p data-style="color:red" styles="x">x</p>')['attrs']);
    }

    public function testStyleWithTwigIsVariableAndWithoutQuotesIsBare(): void {
        $source = '<p style="color:{{ cor }}">x</p>';
        $attrs = $this->first($source)['attrs'];
        self::assertSame('variable', $attrs[0]['kind']);
        self::assertSame('', $attrs[0]['expr'], 'Twig misturado com texto nao vira expressao');
        self::assertSame('color:{{ cor }}', $this->cut($source, $attrs[0], 'value'));

        $attrs = $this->first('<p style="{{ bloco.estilo }}">x</p>')['attrs'];
        self::assertSame(['variable', 'bloco.estilo'], [$attrs[0]['kind'], $attrs[0]['expr']]);

        $attrs = $this->first('<p style="{% if destaque %}color:red{% endif %}">x</p>')['attrs'];
        self::assertSame('variable', $attrs[0]['kind']);

        self::assertSame('bare', $this->first('<p style=color:red>x</p>')['attrs'][0]['kind']);
        self::assertSame('bare', $this->first('<p style>x</p>')['attrs'][0]['kind']);
    }

    public function testTagTheReaderCannotTrustGetsNoStyleFields(): void {
        foreach ([
            'style repetido'        => '<p style="color:red" style="color:blue">x</p>',
            'style repetido (caixa)' => '<p style="color:red" STYLE="color:blue">x</p>',
            'twig solto'            => '<p {% if x %}style="color:red"{% endif %}>x</p>',
            'twig solto depois'     => '<p style="color:red" {{ extra }}>x</p>',
            'colado no anterior'    => '<p class="a"style="color:red">x</p>',
            'aspa sem fim'          => '<p style="color:red>x</p>',
            'aspa solta'            => '<p style="color:red" ">x</p>',
        ] as $what => $source) {
            self::assertNull($this->first($source)['attrs'], $what);
        }
    }

    public function testDescribeDeliversStyleInEditorOffsets(): void {
        // Acento e emoji antes da tag: o navegador conta em unidades UTF-16, o PHP em bytes.
        $source = "<h2>Ação 😀</h2>\r\n<p class=\"é\" style=\"color:#4338ca\">x</p>";
        $normal = ThemeSourceMap::normalize($source);
        $map = ThemeSourceMap::describe($normal, ThemeSourceMap::scan($normal));
        $units = mb_convert_encoding($normal, 'UTF-16LE', 'UTF-8');
        $slice = static fn (int $a, int $b): string => mb_convert_encoding(substr($units, $a * 2, ($b - $a) * 2), 'UTF-8', 'UTF-16LE');

        self::assertSame(ThemeSourceMap::signature($normal), $map['sig']);
        self::assertSame('p', $map['items'][1][0]);
        $style = $map['items'][1][7][0];
        self::assertSame(['style', 'fixed'], [$style[0], $style[1]]);
        self::assertSame('color:#4338ca', $slice($style[2], $style[3]));
        self::assertSame('style="color:#4338ca"', $slice($style[4], $style[5]));
        // O editor escreve um `style` novo logo depois do nome da tag: `<h2` + o que vier.
        self::assertSame('<h2', $slice($map['items'][0][2], $map['items'][0][2] + 3));
        self::assertSame([], $map['items'][0][7]);
    }

    public function testNewStyleGoesWhereTheServerItselfWritesItsMarker(): void {
        // O editor insere ` style="..."` em `abre_ini + 1 + tamanho do nome`. E o mesmo ponto em que
        // annotate() escreve ` data-kz-el`, ja provado seguro para toda tag mapeada.
        $total = 0;
        foreach ($this->presets() as $file) {
            $pack = json_decode((string)file_get_contents($file), true);
            foreach ((array)$pack['config']['templates'] as $key => $source) {
                $source = ThemeSourceMap::normalize((string)$source);
                foreach (ThemeSourceMap::scan($source) as $el) {
                    self::assertSame($el['open'][0] + 1 + strlen($el['tag']), $el['at'], basename($file) . ' ' . $key . ' #' . $el['n']);
                    self::assertSame('<' . $el['tag'], strtolower(substr($source, $el['open'][0], 1 + strlen($el['tag']))));
                    self::assertContains($source[$el['at']], [' ', "\n", "\t", '>', '/'], basename($file) . ' ' . $key . ' #' . $el['n']);
                    $total++;
                }
            }
        }
        self::assertGreaterThan(2000, $total);
    }

    public function testAnnotatedSourceKeepsEveryStyleByteForByte(): void {
        $source = '<div style="margin:0"><p class="a" style=\'color:red\'>x</p><b style="{{ s }}">y</b></div>';
        $annotated = ThemeSourceMap::annotate('partial/hero', $source, ThemeSourceMap::scan($source));

        self::assertSame($source, (string)preg_replace('/ data-kz-el="[^"]*"/', '', $annotated));
        self::assertSame(3, substr_count($annotated, ' data-kz-el="partial/hero:'));
    }

    public function testEveryStyleInTheDeliveredPresetsIsOneTheEditorCanRead(): void {
        $seen = [];
        foreach ($this->presets() as $file) {
            $name = basename($file, '.reamurtheme.json');
            $seen[$name] = ['fixed' => 0, 'other' => 0, 'color' => 0, 'font-size' => 0, 'background-color' => 0];
            $pack = json_decode((string)file_get_contents($file), true);
            foreach ((array)$pack['config']['templates'] as $key => $source) {
                $source = ThemeSourceMap::normalize((string)$source);
                foreach (ThemeSourceMap::scan($source) as $el) {
                    self::assertIsArray($el['attrs'], $name . ' ' . $key . ' #' . $el['n']);
                    foreach ($el['attrs'] as $attr) {
                        if ($attr['name'] !== 'style') continue;
                        if ($attr['kind'] !== 'fixed') { $seen[$name]['other']++; continue; }
                        $seen[$name]['fixed']++;
                        $value = $this->cut($source, $attr, 'value');
                        // O que faria o leitor do navegador (styleParse) desistir da tag.
                        self::assertSame(0, preg_match('~[&"\'\\\\{}<>]|/\*~', $value), $name . ' ' . $key . ': ' . $value);
                        $props = [];
                        foreach (explode(';', $value) as $decl) {
                            if (trim($decl) === '') continue;
                            self::assertSame(1, preg_match('/^\s*([a-zA-Z-]+)\s*:\s*\S/', $decl, $m), $name . ' ' . $key . ': ' . $value);
                            $props[] = strtolower($m[1]);
                        }
                        self::assertSame(count($props), count(array_unique($props)), 'propriedade repetida: ' . $value);
                        foreach (['color', 'font-size', 'background-color'] as $prop) {
                            if (in_array($prop, $props, true)) $seen[$name][$prop]++;
                        }
                    }
                }
            }
        }
        self::assertCount(6, $seen);
        self::assertSame([26, 26, 23, 31, 0, 0], array_column([
            $seen['Atlas-Studio'], $seen['Atlas-Studio-3D'], $seen['David-Creator'], $seen['David-Universe-3D'],
            $seen['Reamur-Reference'], $seen['Reamur-ThreeJS-Reference'],
        ], 'fixed'));
        // Nenhum pacote entregue tem `style` com Twig.
        self::assertSame(0, array_sum(array_column($seen, 'other')));
        self::assertSame(7, array_sum(array_column($seen, 'color')));
        self::assertSame(2, array_sum(array_column($seen, 'font-size')));
        self::assertSame(0, array_sum(array_column($seen, 'background-color')));
    }

    public function testWhatTheEditorWritesPassesTheThemePolicy(): void {
        // As formas que setStyle() escreve (ver tests/check_kaz_element_style.js, "forma exata").
        foreach ([
            '<h2 style="color:#4338ca">Título</h2>',
            '<p style="background-color:var(--at-soft)" class="lead">Texto</p>',
            '<a style="color:#fff;background-color:#101828;font-size:18.5px" href="/contato">Ir</a>',
            '<p style="margin:0;color:var(--onclick)">x</p>',
            '<p style="font-size:400px">x</p>',
        ] as $source) {
            self::assertFalse(ThemeSandbox::blocked($source), $source);
            self::assertNull(ThemeSandbox::check($source), $source);
        }
    }

    public function testEditorAsksOnlyForLabelsThatExistInTheThreeLanguages(): void {
        $controller = $this->repo('dev/admin/controller/design/theme.php');
        $canvas = $this->repo('dev/admin/view/js/kaz-canvas.js');

        self::assertMatchesRegularExpression("/'-kaz-canvas-kz\\d{2}'/", $controller);
        self::assertSame(1, preg_match('/\$canvasLabels=\[\];\s*foreach\(\[(.*?)\] as \$canvasKey\)/s', $controller, $m));
        preg_match_all("/'([a-z0-9_]+)'/", $m[1], $listed);
        foreach (self::KEYS as $key) {
            self::assertContains($key, $listed[1], $key);
            self::assertStringContainsString("'" . $key . "'", $canvas, $key);
        }
        foreach (['pt-br', 'en-us', 'es-eu'] as $language) {
            $_ = [];
            require DIR_REAMUR . 'dev/admin/language/' . $language . '/design/theme.php';
            foreach (self::KEYS as $key) {
                self::assertArrayHasKey('text_canvas_' . $key, $_, $language);
                self::assertNotSame('', trim((string)$_['text_canvas_' . $key]), $language . ' ' . $key);
            }
            // A faixa dita ao autor e a mesma das regras.
            self::assertStringContainsString('6', (string)$_['text_canvas_el_size_bad'], $language);
            self::assertStringContainsString('400', (string)$_['text_canvas_el_size_bad'], $language);
            self::assertStringContainsString('!important', (string)$_['text_canvas_el_style_help'], $language);
            self::assertStringContainsString('!important', (string)$_['text_canvas_el_style_forced'], $language);
        }
    }

    public function testCanvasWritesStyleOnlyThroughTheRulesModule(): void {
        $canvas = $this->repo('dev/admin/view/js/kaz-canvas.js');
        $rules = $this->repo('dev/admin/view/js/kaz-element-attrs.js');

        self::assertSame(1, substr_count($canvas, 'ATTRS.setStyle('));
        // O canvas nao monta `style="..."` por conta propria.
        self::assertStringNotContainsString('style="', $canvas);
        self::assertStringNotContainsString('.cssText', $canvas);
        self::assertStringNotContainsString('.style.setProperty(prop', $canvas);
        self::assertStringContainsString("var STYLE_FIELDS = ['color', 'background-color', 'font-size'];", $canvas);
        // KZ-08 (ADR 0101): a previa no quadro copia o `style` inteiro, lido de volta do codigo que as regras
        // escreveram (antes, mexia em uma propriedade so e errava quando a tag tinha forma curta).
        self::assertSame(1, substr_count($canvas, "setAttribute('style'"));
        self::assertStringContainsString("else list[i].setAttribute('style', raw);", $canvas);
        self::assertStringContainsString("if (!kept || kept[1] === 'fixed') previewStyle(at.key, at.n, kept ? res.src.slice(kept[2], kept[3]) : null);", $canvas);
        // A lista de cores do tema tem teto: no maximo 60 linhas, lendo no maximo 600 variaveis da pagina.
        self::assertStringContainsString('var STYLE_LIST_MAX = 60, VAR_SCAN_MAX = 600;', $canvas);
        self::assertStringContainsString('i < names.length && out.length < STYLE_LIST_MAX; i++', $canvas);
        self::assertSame(2, substr_count($canvas, '&& count < VAR_SCAN_MAX;'));
        // As regras: tres propriedades, faixa do tamanho e gramatica fechada dos valores.
        self::assertStringContainsString("var STYLE_PROPS = ['color', 'background-color', 'font-size'];", $rules);
        self::assertStringContainsString('var SIZE_MIN = 6, SIZE_MAX = 400;', $rules);
        self::assertStringContainsString("var NAMES = ['href', 'target', 'rel', 'alt', 'aria-label', 'style', 'aria-hidden', 'aria-labelledby', 'role'];", $rules);
        foreach (['colorOk', 'sizeOk', 'styleParse', 'styleOf', 'setStyle'] as $export) {
            self::assertStringContainsString($export . ': ' . $export, $rules, $export);
        }
        // Continua sem tocar em pagina, rede nem armazenamento.
        self::assertDoesNotMatchRegularExpression('/\b(document|fetch|XMLHttpRequest|localStorage|sessionStorage|eval|innerHTML)\b/', (string)preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $rules));
    }

    /**
     * O teto de 131072 e medido pelo servidor em bytes, como o formulario chega
     * (UTF-8, quebra de linha em CR LF). O editor tem de fazer a mesma conta:
     * contando caracteres, uma fonte com acentos passava no navegador e era
     * recusada em seguida pelo servidor.
     */
    public function testSizeLimitIsCountedTheWayTheServerCountsIt(): void {
        $canvas = $this->repo('dev/admin/view/js/kaz-canvas.js');
        $rules = $this->repo('dev/admin/view/js/kaz-element-attrs.js');

        self::assertSame(131072, ThemeSandbox::MAX_SOURCE);
        self::assertStringContainsString('var SOURCE_MAX = 131072;', $rules);
        self::assertStringContainsString('tooLong: tooLong', $rules);
        // Tres pontos da KZ-05/KZ-06 e dois da KZ-07 (atributo novo logo depois do nome da tag; atributo sem aspas reescrito).
        self::assertSame(5, substr_count($rules, 'if (tooLong(next)) return'));
        self::assertStringNotContainsString('.length > SOURCE_MAX', (string)preg_replace('~function tooLong\(s\) \{.*?\n  \}~s', '', $rules));
        // O canvas pergunta ao arquivo de regras nos dois pontos em que ele mesmo escreve.
        self::assertStringContainsString("return ATTRS && typeof ATTRS.tooLong === 'function' ? ATTRS.tooLong(s) : s.length > SOURCE_MAX;", $canvas);
        self::assertSame(1, substr_count($canvas, 'if (overLimit(next)) {'));
        self::assertSame(1, substr_count($canvas, 'rewritten === null || overLimit(rewritten)'));
        self::assertSame(1, substr_count($canvas, '.length > SOURCE_MAX'));
        self::assertStringNotContainsString('.length > 131072', $canvas);

        // A mesma fonte, do jeito que o formulario a entrega: no teto passa, um byte acima nao.
        $fill = str_repeat("\u{e9}", 65534) . "\r\n";
        self::assertSame(131070, strlen($fill));
        self::assertFalse(ThemeSandbox::blocked($fill . 'ab'));
        self::assertTrue(ThemeSandbox::blocked($fill . 'abc'));
    }

    public function testNodeCheckerOfTheStyleRulesPasses(): void {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            self::markTestSkipped('node ausente: rode `node tests/check_kaz_element_style.js` à mão.');
        }
        $output = [];
        $status = 1;
        exec(escapeshellarg($node) . ' ' . escapeshellarg(DIR_REAMUR . 'tests/check_kaz_element_style.js') . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", array_slice($output, -8)));
        self::assertMatchesRegularExpression('/^Kaz element style checker: \d{3,} PASS$/', (string)end($output));
    }
}
