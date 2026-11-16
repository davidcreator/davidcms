<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Template\ThemeSandbox;
use Reamur\System\Library\Theme\ThemeSourceMap;

/**
 * KZ-08 (ADR 0101): alinhamento do texto e espacamento (os quatro lados de
 * margem e de espaco interno) de um elemento, escritos no atributo `style` da
 * tag. O servidor nao ganhou codigo novo: o `style` de cada tag ja vinha no
 * mapa desde a KZ-06. Aqui ficam o que os pacotes entregues tem hoje, a
 * ligacao entre controller, idiomas e os dois scripts, e a limpeza dos 38
 * desenhos dos pacotes. As regras de escrita ficam em
 * `dev/admin/view/js/kaz-element-attrs.js` e sao exercitadas por
 * `tests/check_kaz_element_space.js`, chamado daqui.
 */
final class KazElementSpaceTest extends TestCase {
    private const KEYS = ['el_space_title', 'el_align', 'el_align_left', 'el_align_center', 'el_align_right', 'el_align_justify', 'el_margin', 'el_padding',
                          'el_side_top', 'el_side_right', 'el_side_bottom', 'el_side_left', 'el_space_bad', 'el_space_cover', 'el_space_blocked',
                          'el_space_inline', 'el_space_help'];
    private const ORDER = ['Atlas-Studio', 'Atlas-Studio-3D', 'David-Creator', 'David-Universe-3D', 'Reamur-Reference', 'Reamur-ThreeJS-Reference'];

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

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function canonical(array $data): array {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) $data[$key] = $this->canonical($value);
        }

        return $data;
    }

    public function testEverySideWrittenInTheDeliveredPresetsShowsUpInItsField(): void {
        $seen = [];
        foreach ($this->presets() as $file) {
            $name = basename($file, '.reamurtheme.json');
            $seen[$name] = ['side' => 0, 'short' => 0, 'align' => 0];
            $pack = json_decode((string)file_get_contents($file), true);
            foreach ((array)$pack['config']['templates'] as $key => $source) {
                $source = ThemeSourceMap::normalize((string)$source);
                foreach (ThemeSourceMap::scan($source) as $el) {
                    foreach ((array)$el['attrs'] as $attr) {
                        if ($attr['name'] !== 'style' || $attr['kind'] !== 'fixed') continue;
                        $where = $name . ' ' . $key . ' #' . $el['n'];
                        $list = [];
                        foreach (explode(';', substr($source, $attr['value'][0], $attr['value'][1] - $attr['value'][0])) as $decl) {
                            if (trim($decl) === '') continue;
                            [$prop, $value] = array_map('trim', explode(':', $decl, 2));
                            $list[] = [strtolower($prop), $value];
                        }
                        // Ultima forma curta (ou logica) de cada grupo, como em layoutScan().
                        $last = ['margin' => -1, 'padding' => -1];
                        foreach ($list as $i => [$prop]) {
                            if (preg_match('/^(margin|padding)(?:-(?:block|inline)(?:-(?:start|end))?)?$/D', $prop, $m) !== 1) continue;
                            $last[$m[1]] = $i;
                            $seen[$name]['short']++;
                        }
                        $props = [];
                        foreach ($list as $i => [$prop, $value]) {
                            if ($prop === 'text-align') {
                                $seen[$name]['align']++;
                                self::assertContains($value, ['left', 'center', 'right', 'justify'], $where);
                                $props[] = $prop;
                            }
                            if (preg_match('/^(margin|padding)-(?:top|right|bottom|left)$/D', $prop, $m) !== 1) continue;
                            $seen[$name]['side']++;
                            $props[] = $prop;
                            // O campo mostra o numero: `0` ou `Npx` dentro da faixa (pxOf()).
                            self::assertSame(1, preg_match('/^(?:0|\d{1,3}(?:\.\d{1,2})?px)$/D', $value), $where . ' ' . $prop . ': ' . $value);
                            self::assertLessThanOrEqual(400, (float)$value, $where);
                            // Nenhum lado escrito antes de uma forma curta do mesmo grupo (ficaria travado).
                            self::assertGreaterThan($last[$m[1]], $i, $where . ' ' . $prop);
                        }
                        // Nenhuma das nove repetida (o grupo inteiro ficaria travado).
                        self::assertSame(count($props), count(array_unique($props)), $where);
                    }
                }
            }
        }
        self::assertCount(6, $seen);
        $column = static fn (string $what): array => array_map(static fn (string $name): int => $seen[$name][$what], self::ORDER);

        self::assertSame([22, 22, 11, 13, 0, 0], $column('side'));
        self::assertSame([3, 3, 2, 1, 0, 0], $column('short'));
        self::assertSame([1, 1, 1, 0, 0, 0], $column('align'));
    }

    public function testDeliveredPresetsHaveNoDrawingLeftToDescribe(): void {
        $hidden = [];
        $first = [];
        $named = [];
        foreach ($this->presets() as $file) {
            $name = basename($file, '.reamurtheme.json');
            $hidden[$name] = 0;
            $first[$name] = 0;
            $named[$name] = 0;
            $pack = json_decode((string)file_get_contents($file), true);
            self::assertIsArray($pack, $name);
            // O pacote continua importavel: o checksum e o do conteudo que esta no arquivo.
            self::assertSame(
                hash('sha256', (string)json_encode($this->canonical($pack['config']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                $pack['checksum'],
                $name
            );
            foreach ((array)$pack['config']['templates'] as $key => $source) {
                $source = (string)$source;
                foreach (ThemeSourceMap::scan($source) as $el) {
                    if ($el['tag'] !== 'svg') continue;
                    $where = $name . ' ' . $key . ' #' . $el['n'];
                    self::assertIsArray($el['attrs'], $where);
                    $marks = [];
                    foreach ($el['attrs'] as $attr) {
                        $marks[$attr['name']] = substr($source, $attr['value'][0], $attr['value'][1] - $attr['value'][0]);
                    }
                    // Todo desenho entregue e enfeite ao lado de um texto visivel: o leitor de tela o pula.
                    self::assertSame('true', $marks['aria-hidden'] ?? '', $where);
                    $hidden[$name]++;
                    // Marca de enfeite junto com um nome que nunca e lido: achado da ADR 0101, corrigido no KZ-09 (ADR 0102).
                    if (count($marks) > 1) $named[$name]++;
                    // Onde decorate() escreve a marca: logo depois de `<svg`.
                    if (substr($source, $el['open'][0], 23) === '<svg aria-hidden="true"') $first[$name]++;
                }
            }
        }
        self::assertSame([15, 15, 12, 12, 0, 0], array_map(static fn (string $name): int => $hidden[$name], self::ORDER));
        self::assertSame([0, 0, 0, 0, 0, 0], array_map(static fn (string $name): int => $named[$name], self::ORDER));
        // Os 38 marcados nesta etapa (13, 13 e 12) tem a marca no lugar em que o editor a poe.
        self::assertSame([13, 13, 12, 0, 0, 0], array_map(static fn (string $name): int => $first[$name], self::ORDER));
    }

    public function testWhatTheEditorWritesPassesTheThemePolicy(): void {
        // As formas que setStyle() escreve (ver tests/check_kaz_element_space.js, "forma exata").
        foreach ([
            '<h2 style="margin-top:0px">Título</h2>',
            '<p style="text-align:justify" class="lead">Texto</p>',
            '<section style="padding-top:400px;padding-right:12.5px;padding-bottom:0px;padding-left:4px" class="x">a</section>',
            '<p style="color:var(--at-muted);margin:0 auto 26px;max-width:52ch;margin-top:40px;text-align:center">x</p>',
            '<img style="margin-bottom:24px" src="a.png" alt="Foto">',
            '<a class="b" href="/novo-destino" target="_blank" rel="noopener" style="color:#000;margin:0 0 8px;margin-top:12px;text-align:center;font-size:18px">Ir</a>',
        ] as $source) {
            self::assertFalse(ThemeSandbox::blocked($source), $source);
            self::assertNull(ThemeSandbox::check($source), $source);
        }
    }

    public function testEditorAsksOnlyForLabelsThatExistInTheThreeLanguages(): void {
        $controller = $this->repo('dev/admin/controller/design/theme.php');
        $canvas = $this->repo('dev/admin/view/js/kaz-canvas.js');

        // O navegador busca o canvas novo: a versao no endereco e a desta etapa.
        self::assertMatchesRegularExpression("/'-kaz-canvas-kz\\d{2}'/", $controller);
        self::assertSame(1, preg_match('/\$canvasLabels=\[\];\s*foreach\(\[(.*?)\] as \$canvasKey\)/s', $controller, $m));
        preg_match_all("/'([a-z0-9_]+)'/", $m[1], $listed);
        self::assertSame(count($listed[1]), count(array_unique($listed[1])));
        foreach (self::KEYS as $key) {
            self::assertContains($key, $listed[1], $key);
            self::assertStringContainsString("'" . $key . "'", $canvas, $key);
        }
        foreach (['pt-br', 'en-us', 'es-eu'] as $language) {
            $_ = [];
            require DIR_REAMUR . 'dev/admin/language/' . $language . '/design/theme.php';
            $texts = [];
            foreach (self::KEYS as $key) {
                self::assertArrayHasKey('text_canvas_' . $key, $_, $language);
                $texts[$key] = trim((string)$_['text_canvas_' . $key]);
                self::assertNotSame('', $texts[$key], $language . ' ' . $key);
            }
            // A faixa dita ao autor e a mesma das regras.
            self::assertMatchesRegularExpression('/\b0\b.*\b400\b/', $texts['el_space_bad'], $language);
            self::assertStringContainsString('!important', $texts['el_space_help'], $language);
            self::assertStringContainsString('style', $texts['el_space_help'], $language);
            // Os dois grupos dizem a unidade; os quatro lados e os quatro alinhamentos tem nomes diferentes.
            self::assertStringContainsString('(px)', $texts['el_margin'], $language);
            self::assertStringContainsString('(px)', $texts['el_padding'], $language);
            self::assertNotSame($texts['el_margin'], $texts['el_padding'], $language);
            self::assertCount(4, array_unique([$texts['el_side_top'], $texts['el_side_right'], $texts['el_side_bottom'], $texts['el_side_left']]), $language);
            self::assertCount(4, array_unique([$texts['el_align_left'], $texts['el_align_center'], $texts['el_align_right'], $texts['el_align_justify']]), $language);
            // Frases que terminam em dois-pontos recebem o trecho do codigo em seguida.
            self::assertStringEndsWith(':', $texts['el_space_cover'], $language);
        }
    }

    public function testCanvasWritesSpacingOnlyThroughTheRulesModule(): void {
        $canvas = $this->repo('dev/admin/view/js/kaz-canvas.js');
        $rules = $this->repo('dev/admin/view/js/kaz-element-attrs.js');
        $css = $this->repo('dev/admin/view/css/kaz-canvas.css');

        // Uma unica escrita de `style` no canvas, para as doze propriedades.
        self::assertSame(1, substr_count($canvas, 'ATTRS.setStyle('));
        self::assertStringContainsString('var res = ATTRS.setStyle(at.src, at.map, at.n, prop, value);', $canvas);
        // O canvas nao monta `style="..."` nem nome de propriedade fora das listas.
        self::assertStringNotContainsString('style="', $canvas);
        self::assertStringNotContainsString('.cssText', $canvas);
        self::assertStringContainsString("var SPACE_GROUPS = ['margin', 'padding'], SPACE_SIDES = ['top', 'right', 'bottom', 'left'];", $canvas);
        self::assertStringContainsString("var LAYOUT_FIELDS = ['text-align'];", $canvas);
        self::assertStringContainsString("var INLINE_DEAD = ['text-align', 'margin-top', 'margin-bottom'];", $canvas);
        self::assertStringContainsString("if (!at || (STYLE_FIELDS.indexOf(prop) === -1 && !layout)) return;", $canvas);
        // A previa copia para o quadro o `style` lido de volta do codigo, nao o que foi digitado: uma
        // unica chamada, e so ela toca no atributo (retirar um lado faz a forma curta voltar a valer).
        self::assertSame(1, substr_count($canvas, 'previewStyle(at.key'));
        self::assertSame(1, substr_count($canvas, "setAttribute('style'"));
        self::assertSame(1, substr_count($canvas, "removeAttribute('style')"));
        self::assertStringContainsString("if (raw === null) list[i].removeAttribute('style');\n        else list[i].setAttribute('style', raw);", $canvas);
        self::assertStringContainsString("var kept = ATTRS.find(at.map.items[at.n], 'style');", $canvas);
        // Os campos entram depois de cor e tamanho, e contam para o aviso "personalize este trecho".
        self::assertStringContainsString("if (paintStyle(key, host, it, own, fresh)) hasFields = true;\n    if (paintSpace(key, host, it, own, fresh)) hasFields = true;", $canvas);
        // Faixa e limites vem das regras, nao de numeros soltos no canvas.
        self::assertStringContainsString('num.min = String(ATTRS.SPACE_MIN);', $canvas);
        self::assertStringContainsString('num.max = String(ATTRS.SPACE_MAX);', $canvas);
        self::assertStringContainsString("[''].concat(ATTRS.ALIGN_VALUES).forEach(", $canvas);

        // As regras: nove propriedades, faixa e listas fechadas.
        self::assertStringContainsString("var SPACE_PROPS = ['margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left'];", $rules);
        self::assertStringContainsString("var ALIGN_VALUES = ['left', 'center', 'right', 'justify'];", $rules);
        self::assertStringContainsString("var LAYOUT_PROPS = SPACE_PROPS.concat(['text-align']);", $rules);
        self::assertStringContainsString('var SPACE_MIN = 0, SPACE_MAX = 400;', $rules);
        self::assertStringContainsString("var SPACE_SKIP = ['br', 'wbr', 'source', 'track', 'param', 'area', 'map', 'col'];", $rules);
        self::assertStringContainsString("var ALIGN_SKIP = ['svg', 'hr'];", $rules);
        // As tres da KZ-06 continuam as mesmas.
        self::assertStringContainsString("var STYLE_PROPS = ['color', 'background-color', 'font-size'];", $rules);
        foreach (['spaceOk', 'pxOf', 'layoutOf'] as $export) {
            self::assertStringContainsString($export . ': ' . $export, $rules, $export);
        }
        self::assertStringContainsString('SPACE_PROPS: SPACE_PROPS.slice(), ALIGN_VALUES: ALIGN_VALUES.slice(),', $rules);
        // O teto da fonte continua conferido nos mesmos cinco pontos: nenhuma escrita nova por fora deles.
        self::assertSame(5, substr_count($rules, 'if (tooLong(next)) return'));
        // Continua sem tocar em pagina, rede nem armazenamento.
        self::assertDoesNotMatchRegularExpression('/\b(document|fetch|XMLHttpRequest|localStorage|sessionStorage|eval|innerHTML)\b/', (string)preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $rules));

        // Os oito campos cabem em duas grades de quatro.
        self::assertStringContainsString('.kaz-canvas__space { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));', $css);
        foreach (['kaz-canvas__space', 'kaz-canvas__spacecell', 'kaz-canvas__spacename'] as $class) {
            self::assertStringContainsString("'" . $class . "'", $canvas, $class);
            self::assertStringContainsString('.' . $class . ' ', $css, $class);
        }
    }

    public function testNodeCheckerOfTheSpacingRulesPasses(): void {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            self::markTestSkipped('node ausente: rode `node tests/check_kaz_element_space.js` à mão.');
        }
        $output = [];
        $status = 1;
        exec(escapeshellarg($node) . ' ' . escapeshellarg(DIR_REAMUR . 'tests/check_kaz_element_space.js') . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", array_slice($output, -8)));
        self::assertMatchesRegularExpression('/^Kaz element space checker: \d{4,} PASS$/', (string)end($output));
    }
}
