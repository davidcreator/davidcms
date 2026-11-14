<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Template\ThemeSandbox;
use Reamur\System\Library\Theme\ThemeSourceMap;

/**
 * KZ-07 (ADR 0100): imagem sem texto alternativo e desenho (<svg>) sem
 * descricao. Cobre a parte do servidor (os atributos que o editor precisa ler
 * para julgar e o aviso de <title> dentro do <svg>) e a ligacao entre
 * controller, idiomas, tela e os dois scripts. As regras ficam em
 * `dev/admin/view/js/kaz-element-attrs.js` e sao exercitadas por
 * `tests/check_kaz_element_a11y.js`, chamado daqui.
 */
final class KazElementA11yTest extends TestCase {
    private const KEYS = ['el_alt_missing', 'el_svg_missing', 'el_svg_label', 'el_svg_label_help', 'el_label_new_help', 'el_decor', 'el_decor_on',
                          'el_decor_undo', 'el_a11y_hidden', 'el_svg_titled', 'el_a11y_labelledby', 'el_a11y_data', 'el_unlabel', 'el_svg_unlabel',
                          'a11y_title', 'a11y_help', 'a11y_none', 'a11y_more', 'a11y_unknown', 'a11y_img', 'a11y_svg', 'a11y_near'];

    /** @return list<array<string,mixed>> */
    private function scan(string $source): array {
        $els = ThemeSourceMap::scan($source);
        self::assertNotSame([], $els, $source);

        return $els;
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

    /**
     * A mesma regra do navegador (a11y() em kaz-element-attrs.js), escrita de
     * novo aqui sobre o que scan() devolve: 'missing', 'unknown' ou 'ok'.
     *
     * @param array<string,mixed> $el
     */
    private function judge(string $source, array $el): string {
        if ($el['tag'] !== 'img' && $el['tag'] !== 'svg') return 'ok';
        if (!is_array($el['attrs'])) return 'unknown';
        $by = [];
        foreach ($el['attrs'] as $attr) $by[$attr['name']] = $attr;
        $value = fn (string $name): string => trim($this->cut($source, $by[$name], 'value'));
        foreach (['aria-hidden', 'role', 'aria-label', 'aria-labelledby'] as $name) {
            if (!isset($by[$name])) continue;
            if ($by[$name]['kind'] === 'variable') return 'ok';
            if ($by[$name]['kind'] === 'bare' && preg_match('/\{[{%#]/', $value($name)) === 1) return 'ok';
            if ($name === 'aria-hidden' && strtolower($value($name)) === 'true') return 'ok';
            if ($name === 'role' && array_intersect(['presentation', 'none'], (array)preg_split('/\s+/', strtolower($value($name)))) !== []) return 'ok';
            if (($name === 'aria-label' || $name === 'aria-labelledby') && $value($name) !== '') return 'ok';
        }
        if ($el['tag'] === 'img') return isset($by['alt']) ? 'ok' : 'missing';

        return ($el['titled'] ?? false) === true ? 'ok' : 'missing';
    }

    public function testNamesTheEditorNeedsToJudgeAreReportedOnAnyTag(): void {
        $source = '<svg class="i" aria-hidden="true" role="img" aria-labelledby="t1 t2" focusable="false" viewBox="0 0 1 1"></svg>';
        $attrs = (array)$this->scan($source)[0]['attrs'];
        self::assertSame(['aria-hidden', 'role', 'aria-labelledby'], array_column($attrs, 'name'));
        self::assertSame(['fixed', 'fixed', 'fixed'], array_column($attrs, 'kind'));
        self::assertSame(['true', 'img', 't1 t2'], array_map(fn (array $a): string => $this->cut($source, $a, 'value'), $attrs));
        self::assertSame('aria-labelledby="t1 t2"', $this->cut($source, $attrs[2], 'span'));

        // Em imagem, junto com o `alt`; em link, junto com os atributos da KZ-05.
        self::assertSame(['alt', 'role'], array_column((array)$this->scan('<img src="a.png" alt="" role="presentation">')[0]['attrs'], 'name'));
        self::assertSame(['role', 'href', 'aria-hidden'], array_column((array)$this->scan('<a role="button" href="/x" aria-hidden="false">x</a>')[0]['attrs'], 'name'));
        self::assertSame(['role'], array_column((array)$this->scan('<div class="b" ROLE="navigation">x</div>')[0]['attrs'], 'name'));

        // Com Twig e sem aspas: o tipo diz que o editor nao escreve ali.
        $attrs = (array)$this->scan('<svg aria-hidden="{{ oculto }}" role=img aria-labelledby></svg>')[0]['attrs'];
        self::assertSame([['aria-hidden', 'variable'], ['role', 'bare'], ['aria-labelledby', 'bare']], array_map(static fn (array $a): array => [$a['name'], $a['kind']], $attrs));

        // Nomes parecidos e outros `aria-*` continuam fora; `title` (atributo) tambem.
        self::assertSame([], $this->scan('<svg aria-hiddenx="true" data-role="img" aria-describedby="d" aria-current="page" title="t" roles="x"></svg>')[0]['attrs']);
        // Nome repetido fecha a tag para o editor, como nos outros atributos.
        self::assertNull($this->scan('<svg role="img" role="presentation"></svg>')[0]['attrs']);
    }

    public function testSvgSaysWhetherThereIsATitleInside(): void {
        $titled = function (string $source): array {
            $out = [];
            foreach ($this->scan($source) as $el) $out[] = $el['tag'] . ':' . (array_key_exists('titled', $el) ? ($el['titled'] ? '1' : '0') : '-');

            return $out;
        };

        self::assertSame(['svg:1'], $titled('<svg viewBox="0 0 1 1"><title>Seta</title><path d="M0 0"/></svg>'));
        self::assertSame(['svg:0'], $titled('<svg viewBox="0 0 1 1"><path d="M0 0"/></svg>'));
        self::assertSame(['svg:0'], $titled('<svg/>'));
        self::assertSame(['svg:1'], $titled('<svg><g><TITLE>Seta</TITLE></g></svg>'));
        self::assertSame(['svg:1'], $titled('<svg><title>{{ nome }}</title></svg>'));
        // `<desc>` e `<text>` nao sao o nome do desenho.
        self::assertSame(['svg:0'], $titled('<svg><desc>Descrição longa</desc><text>a</text></svg>'));
        // O <title> vale para o <svg> mapeado que o contem; o vizinho nao herda.
        self::assertSame(['p:-', 'svg:1', 'svg:0', 'a:-', 'svg:0', 'svg:1'], $titled(
            '<p>a</p><svg><title>um</title></svg><svg><path/></svg><a href="/x"><svg><circle/></svg></a><svg><svg><title>dentro</title></svg></svg>'
        ));
        // Depois de fechar o desenho, um <title> solto nao marca ninguem.
        self::assertSame(['svg:0', 'p:-'], $titled('<svg><path/></svg><title>Página</title><p>x</p>'));
        // So o <svg> carrega a marca.
        foreach ($this->scan('<div><img src="a.png"><p>x</p></div>') as $el) self::assertArrayNotHasKey('titled', $el);
    }

    public function testUnquotedAndValuelessAttributesReachTheEditorWithTheirExactPlace(): void {
        // `aria-hidden=true` (sem aspas) esconde no navegador; o editor precisa do valor e do lugar para ler e para trocar.
        $source = '<svg aria-hidden=true role=img aria-label><path/></svg><img src="a.png" alt><img src=a.png alt=Foto aria-hidden={{ x }}>';
        $els = $this->scan($source);
        self::assertSame(['svg', 'img', 'img'], array_column($els, 'tag'));

        $read = function (array $el) use ($source): array {
            $out = [];
            foreach ($el['attrs'] as $attr) $out[$attr['name']] = [$attr['kind'], $this->cut($source, $attr, 'value'), $this->cut($source, $attr, 'span')];
            return $out;
        };
        self::assertSame([
            'aria-hidden' => ['bare', 'true', 'aria-hidden=true'],
            'role'        => ['bare', 'img', 'role=img'],
            'aria-label'  => ['bare', '', 'aria-label'],
        ], $read($els[0]));
        self::assertSame(['alt' => ['bare', '', 'alt']], $read($els[1]));
        self::assertSame([
            'alt'         => ['bare', 'Foto', 'alt=Foto'],
            'aria-hidden' => ['bare', '{{ x }}', 'aria-hidden={{ x }}'],
        ], $read($els[2]));
        // No formato compacto, o fim do valor sem aspas e o fim do atributo: e por ai que o editor confere o lugar.
        $map = ThemeSourceMap::describe($source, $els);
        foreach ($map['items'] as $item) foreach ($item[7] as $attr) {
            self::assertSame('bare', $attr[1]);
            self::assertSame($attr[3], $attr[5]);
        }
        // Escondido sem aspas, enfeite sem valor, nome sem aspas: nenhum dos tres e pendente.
        self::assertSame(['ok', 'ok', 'ok'], array_map(fn (array $el): string => $this->judge($source, $el), $els));
        // `aria-hidden` e `aria-label` sem valor nao escondem nem nomeiam.
        $empty = '<svg aria-hidden role=img aria-label><path/></svg>';
        self::assertSame(['missing'], array_map(fn (array $el): string => $this->judge($empty, $el), $this->scan($empty)));
    }

    public function testTitleOfADrawingBeyondTheMapLimitMarksNobody(): void {
        // O mapa para em 4000 elementos. Um <svg> que ficou de fora nao empresta o <title> ao ultimo <svg> mapeado.
        $source = '<svg><path/></svg>' . str_repeat('<i></i>', 3999) . '<svg><title>fora do mapa</title></svg><p>x</p>';
        $els = ThemeSourceMap::scan($source);

        self::assertCount(4000, $els);
        self::assertSame(range(0, 3999), array_keys($els));
        self::assertSame('svg', $els[0]['tag']);
        self::assertFalse($els[0]['titled']);
        self::assertSame(1, count(array_filter($els, static fn (array $el): bool => array_key_exists('titled', $el))));
        // Dentro do limite, o mesmo <title> vale.
        $inside = ThemeSourceMap::scan('<svg><path/></svg>' . str_repeat('<i></i>', 3998) . '<svg><title>dentro</title></svg>');
        self::assertTrue($inside[3999]['titled']);
        self::assertFalse($inside[0]['titled']);
    }

    public function testDescribeTellsTheBrowserAboutTheTitleOnlyOnSvg(): void {
        $source = "<h2>Ação 😀</h2>\r\n<svg class=\"é\" aria-hidden=\"false\"><title>Selo</title></svg><svg role=\"img\"></svg><img src=\"a.png\">";
        $normal = ThemeSourceMap::normalize($source);
        $map = ThemeSourceMap::describe($normal, ThemeSourceMap::scan($normal));
        $units = mb_convert_encoding($normal, 'UTF-16LE', 'UTF-8');
        $slice = static fn (int $a, int $b): string => mb_convert_encoding(substr($units, $a * 2, ($b - $a) * 2), 'UTF-8', 'UTF-16LE');

        self::assertSame(['h2', 'svg', 'svg', 'img'], array_column($map['items'], 0));
        self::assertSame([8, 9, 9, 8], array_map('count', $map['items']));
        self::assertSame(1, $map['items'][1][8]);
        self::assertSame(0, $map['items'][2][8]);
        // Posicoes em unidades do navegador, mesmo com acento e emoji antes.
        $hidden = $map['items'][1][7][0];
        self::assertSame(['aria-hidden', 'fixed'], [$hidden[0], $hidden[1]]);
        self::assertSame('false', $slice($hidden[2], $hidden[3]));
        self::assertSame('aria-hidden="false"', $slice($hidden[4], $hidden[5]));
        $role = $map['items'][2][7][0];
        self::assertSame('role="img"', $slice($role[4], $role[5]));
        // E o ponto em que o editor escreve o atributo novo: logo depois de `<svg` e de `<img`.
        self::assertSame('<svg', $slice($map['items'][1][2], $map['items'][1][2] + 4));
        self::assertSame('<img', $slice($map['items'][3][2], $map['items'][3][2] + 4));
    }

    public function testAnnotatedSourceKeepsEveryByte(): void {
        $source = '<a href="/x"><svg aria-hidden="true" role="img"><title>t</title><path/></svg></a><img src="a.png" role="presentation"><svg aria-labelledby="{{ i }}"/>';
        $annotated = ThemeSourceMap::annotate('partial/hero', $source, ThemeSourceMap::scan($source));

        self::assertSame($source, (string)preg_replace('/ data-kz-el="[^"]*"/', '', $annotated));
        self::assertSame(4, substr_count($annotated, ' data-kz-el="partial/hero:'));
        // Nada de dentro do <svg> e anotado: <title> e <path> ficam como estao.
        self::assertStringContainsString('<title>t</title><path/>', $annotated);
    }

    public function testPendingImagesAndDrawingsOfTheDeliveredPresets(): void {
        $seen = [];
        foreach ($this->presets() as $file) {
            $name = basename($file, '.reamurtheme.json');
            $seen[$name] = ['img' => 0, 'svg' => 0, 'missing' => 0, 'unknown' => 0, 'titled' => 0];
            $pack = json_decode((string)file_get_contents($file), true);
            foreach ((array)$pack['config']['templates'] as $source) {
                $source = ThemeSourceMap::normalize((string)$source);
                foreach (ThemeSourceMap::scan($source) as $el) {
                    if ($el['tag'] !== 'img' && $el['tag'] !== 'svg') continue;
                    $seen[$name][$el['tag']]++;
                    $verdict = $this->judge($source, $el);
                    if ($verdict !== 'ok') $seen[$name][$verdict]++;
                    if (($el['titled'] ?? false) === true) $seen[$name]['titled']++;
                }
            }
        }
        self::assertCount(6, $seen);
        $order = ['Atlas-Studio', 'Atlas-Studio-3D', 'David-Creator', 'David-Universe-3D', 'Reamur-Reference', 'Reamur-ThreeJS-Reference'];
        $column = static fn (string $what): array => array_map(static fn (string $name): int => $seen[$name][$what], $order);

        self::assertSame([0, 0, 0, 0, 11, 10], $column('img'));
        self::assertSame([15, 15, 12, 30, 0, 0], $column('svg'));
        // O que a lista do editor mostra hoje: so desenhos, em tres pacotes.
        self::assertSame([13, 13, 12, 0, 0, 0], $column('missing'));
        // Nenhuma imagem ou desenho entregue fica sem conferir, e nenhum usa <title>.
        self::assertSame([0, 0, 0, 0, 0, 0], $column('unknown'));
        self::assertSame([0, 0, 0, 0, 0, 0], $column('titled'));
    }

    public function testWhatTheEditorWritesPassesTheThemePolicy(): void {
        // As formas que putAttr(), decorate() e dropLabel() deixam (ver tests/check_kaz_element_a11y.js).
        foreach ([
            '<img alt="Foto da equipe" src="a.png">',
            '<img alt="" src="a.png" class="x" />',
            '<img alt="a &quot;b&quot; &lt;c&gt; &amp; &#123;&#123; d }} &#39;e&#39;" src="a.png">',
            '<img alt="x onerror&#61;alert(1)" src="a.png">',
            '<svg role="img" aria-label="Selo de qualidade" viewBox="0 0 1 1"><path d="M0 0"/></svg>',
            '<svg aria-hidden="true" viewBox="0 0 1 1"><path d="M0 0"/></svg>',
            '<a aria-label="Abrir o menu" href="/x"><i class="ico"></i></a>',
            '<nav aria-label="Principal" class="menu"></nav>',
        ] as $source) {
            self::assertFalse(ThemeSandbox::blocked($source), $source);
            self::assertNull(ThemeSandbox::check($source), $source);
        }
        // O mesmo texto, sem a troca que o editor faz, e o que a politica recusa.
        self::assertTrue(ThemeSandbox::blocked('<img alt="x onerror=alert(1)" src="a.png">'));
    }

    public function testEditorAsksOnlyForLabelsThatExistInTheThreeLanguages(): void {
        $controller = $this->repo('dev/admin/controller/design/theme.php');
        $canvas = $this->repo('dev/admin/view/js/kaz-canvas.js');

        self::assertStringContainsString("'-kaz-canvas-kz07'", $controller);
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
            foreach (self::KEYS as $key) {
                self::assertArrayHasKey('text_canvas_' . $key, $_, $language);
                self::assertNotSame('', trim((string)$_['text_canvas_' . $key]), $language . ' ' . $key);
            }
            // As frases com numero ou texto encaixado tem o lugar dele.
            foreach (['a11y_more', 'a11y_unknown', 'a11y_near'] as $key) {
                self::assertSame(1, substr_count((string)$_['text_canvas_' . $key], '%s'), $language . ' ' . $key);
            }
            // A frase antiga mandava editar o codigo a mao; agora o painel resolve.
            self::assertStringNotContainsString('(alt)', (string)$_['text_canvas_el_alt_missing'], $language);
        }
    }

    public function testCanvasCreatesAndRemovesOnlyThroughTheRulesModule(): void {
        $canvas = $this->repo('dev/admin/view/js/kaz-canvas.js');
        $rules = $this->repo('dev/admin/view/js/kaz-element-attrs.js');
        $screen = $this->repo('dev/admin/view/template/design/kaz_theme_editor.twig');
        $css = $this->repo('dev/admin/view/css/kaz-canvas.css');

        foreach (['ATTRS.putAttr(', 'ATTRS.decorate(', 'ATTRS.dropLabel(', 'ATTRS.pending('] as $call) {
            self::assertSame(1, substr_count($canvas, $call), $call);
        }
        // `href` continua so sendo trocado; `alt` e `aria-label` passam por putAttr().
        self::assertStringContainsString("var res = A11Y_OK && name !== 'href' ? ATTRS.putAttr(at.src, at.map, at.n, name, t.value) : ATTRS.setValue(at.src, at.map, at.n, name, t.value);", $canvas);
        // O canvas nao monta atributo por conta propria.
        foreach (['alt="', 'aria-hidden="', 'aria-label="', 'role="img"'] as $piece) {
            self::assertStringNotContainsString($piece, $canvas, $piece);
        }
        // Trecho que ainda e o padrao do sistema: botoes travados e nenhuma escrita.
        self::assertStringContainsString('b.disabled = !own;', $canvas);
        self::assertStringContainsString("if (!map || !field || field.value === '') return null;", $canvas);
        // A lista tem teto e so entra trecho com o mapa em dia.
        self::assertStringContainsString('var A11Y_LIST_MAX = 60, A11Y_SNIP_MAX = 60, A11Y_NEAR_MAX = 40, A11Y_NEAR_HOPS = 3;', $canvas);
        self::assertSame(2, substr_count($canvas, 'if (shown >= A11Y_LIST_MAX) return;'));
        self::assertStringContainsString('if (!map || !mapFresh(r.key)) return;', $canvas);
        // O que aparece na lista e no painel e texto, nunca HTML.
        self::assertSame(1, preg_match('~/\* -+ KZ-07: texto alternativo.*?\n  /\*\* Linha da lista -> elemento.*?\n  \}\n~s', $canvas, $block));
        self::assertStringContainsString('function paintA11y()', $block[0]);
        self::assertStringNotContainsString('innerHTML', $block[0]);
        self::assertStringNotContainsString('insertAdjacentHTML', $canvas);

        // A tela so ganha o lugar da lista; nenhum controle novo tem `name`.
        self::assertSame(1, substr_count($screen, '<div class="kaz-canvas__a11y" data-kaz-canvas-a11y hidden></div>'));
        self::assertStringContainsString('.kaz-canvas__a11y[hidden] { display: none; }', $css);
        self::assertStringNotContainsString(".name = '", $canvas);

        // As regras: listas fechadas, nomes lidos e o que o arquivo oferece.
        self::assertStringContainsString("var NAMES = ['href', 'target', 'rel', 'alt', 'aria-label', 'style', 'aria-hidden', 'aria-labelledby', 'role'];", $rules);
        self::assertStringContainsString("var LABEL_TAGS = ['a', 'button', 'svg', 'nav', 'section', 'aside', 'form', 'header', 'footer', 'main'];", $rules);
        self::assertStringContainsString("var DECOR_ROLES = ['presentation', 'none'];", $rules);
        self::assertStringContainsString("if (!it || !Array.isArray(it[7]) || (name !== 'alt' && name !== 'aria-label')) return { error: 'locked' };", $rules);
        foreach (['a11y', 'pending', 'canLabel', 'putAttr', 'decorate', 'dropLabel'] as $export) {
            self::assertStringContainsString($export . ': ' . $export, $rules, $export);
        }
        // O servidor manda exatamente os nomes que as regras aceitam.
        $server = array_values(array_unique(array_merge(...array_values((array)(new \ReflectionClass(ThemeSourceMap::class))->getConstant('ATTRS')))));
        preg_match("/var NAMES = \[(.*?)\];/", $rules, $m);
        preg_match_all("/'([a-z-]+)'/", $m[1], $names);
        sort($server);
        sort($names[1]);
        self::assertSame($server, $names[1]);
    }

    public function testNodeCheckerOfTheA11yRulesPasses(): void {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            self::markTestSkipped('node ausente: rode `node tests/check_kaz_element_a11y.js` à mão.');
        }
        $output = [];
        $status = 1;
        exec(escapeshellarg($node) . ' ' . escapeshellarg(DIR_REAMUR . 'tests/check_kaz_element_a11y.js') . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", array_slice($output, -8)));
        self::assertMatchesRegularExpression('/^Kaz element a11y checker: \d{3,} PASS$/', (string)end($output));
    }
}
