<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Theme\ThemeSourceMap;

/**
 * KZ-05 (ADR 0098): editar atributos de um elemento pelo painel. Cobre a parte
 * do servidor (onde fica cada atributo na fonte Twig e quando a tag e fechada
 * para o editor) e a ligacao entre controller, idiomas e os dois scripts.
 * As regras de escrita ficam em `dev/admin/view/js/kaz-element-attrs.js` e sao
 * exercitadas por `tests/check_kaz_element_attrs.js`, chamado daqui.
 */
final class KazElementAttrsTest extends TestCase {
    /** @return array{tag:string,attrs:?list<array<string,mixed>>} */
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

    public function testFixedValueIsLocatedExactlyBetweenTheQuotes(): void {
        $source = "<a class='b' href='#topo' target=\"_blank\" rel=\"noopener me\">x</a>";
        $attrs = $this->first($source)['attrs'];

        self::assertIsArray($attrs);
        self::assertSame(['href', 'target', 'rel'], array_column($attrs, 'name'));
        self::assertSame(['fixed', 'fixed', 'fixed'], array_column($attrs, 'kind'));
        self::assertSame('#topo', $this->cut($source, $attrs[0], 'value'));
        self::assertSame("href='#topo'", $this->cut($source, $attrs[0], 'span'));
        self::assertSame('_blank', $this->cut($source, $attrs[1], 'value'));
        self::assertSame('noopener me', $this->cut($source, $attrs[2], 'value'));
        self::assertSame('rel="noopener me"', $this->cut($source, $attrs[2], 'span'));
    }

    public function testEmptyValueAndSpacesAroundTheEqualSignAreStillFixed(): void {
        $source = '<img src="a.png" alt="">';
        $attrs = $this->first($source)['attrs'];
        self::assertSame(['alt'], array_column((array)$attrs, 'name'));
        self::assertSame('fixed', $attrs[0]['kind']);
        self::assertSame('', $this->cut($source, $attrs[0], 'value'));
        self::assertSame($attrs[0]['value'][0], $attrs[0]['value'][1]);

        $source = '<a href = "/x">x</a>';
        $attrs = $this->first($source)['attrs'];
        self::assertSame('/x', $this->cut($source, $attrs[0], 'value'));
        self::assertSame('href = "/x"', $this->cut($source, $attrs[0], 'span'));
    }

    public function testOnlyTheClosedListOfNamesPerTagIsReported(): void {
        // href so em <a>, alt so em <img>, aria-label em qualquer tag; o resto nao entra.
        self::assertSame([], $this->first('<p alt="não é imagem" href="/x" title="t">x</p>')['attrs']);
        self::assertSame([], $this->first('<img src="a.png" href="/x" title="t">')['attrs']);
        self::assertSame(['href'], array_column((array)$this->first('<a href="/x" alt="y" title="t" class="c" data-href="/z">x</a>')['attrs'], 'name'));
        self::assertSame(['aria-label'], array_column((array)$this->first('<button type="button" aria-label="Fechar">x</button>')['attrs'], 'name'));
        self::assertSame(['aria-label', 'href'], array_column((array)$this->first('<a aria-label="Início" href="/">x</a>')['attrs'], 'name'));
        // Nome em maiusculas e o mesmo atributo; o trecho devolvido e o que esta escrito.
        $source = '<a HREF="/Maiúsculo" data-x="a > b">x</a>';
        $attrs = $this->first($source)['attrs'];
        self::assertSame('href', $attrs[0]['name']);
        self::assertSame('HREF="/Maiúsculo"', $this->cut($source, $attrs[0], 'span'));
    }

    public function testValueWithTwigIsVariableAndASingleExpressionIsReported(): void {
        $attrs = $this->first('<a href="{{ link }}">x</a>')['attrs'];
        self::assertSame(['variable', 'link'], [$attrs[0]['kind'], $attrs[0]['expr']]);

        $attrs = $this->first("<a href=\"{{ home|default('/') }}\">x</a>")['attrs'];
        self::assertSame(['variable', "home|default('/')"], [$attrs[0]['kind'], $attrs[0]['expr']]);

        // Texto fixo misturado com dado: variavel, sem expressao unica para mostrar.
        foreach (['<a href="{{ base }}/contato">x</a>', '<a href="/p/{{ id }}">x</a>', '<a href="{{ a }}{{ b }}">x</a>',
                  '<a href="{% if x %}/a{% else %}/b{% endif %}">x</a>', '<a href="/x{# nota #}">x</a>'] as $source) {
            $attrs = $this->first($source)['attrs'];
            self::assertSame(['variable', ''], [$attrs[0]['kind'], $attrs[0]['expr']], $source);
        }
    }

    public function testValueWithoutQuotesOrWithoutValueIsBare(): void {
        foreach (['<a href=/x>x</a>' => '/x', '<a href>x</a>' => '', '<img src=a.png alt=foto>' => 'foto'] as $source => $value) {
            $attrs = $this->first($source)['attrs'];
            self::assertSame('bare', $attrs[0]['kind'], $source);
            self::assertSame($value, $this->cut($source, $attrs[0], 'value'), $source);
        }
    }

    public function testTagTheReaderCannotTrustIsClosedToTheEditor(): void {
        foreach ([
            'Twig solto entre atributos' => '<a href="/x" {% if on %}class="a"{% endif %}>x</a>',
            'expressao solta'            => '<a href="/x" {{ attrs }}>x</a>',
            'nome repetido'              => '<a href="/x" href="/y">x</a>',
            'nome repetido (caixa)'      => '<a href="/x" HREF="/y">x</a>',
            'atributo colado'            => '<a href="/x"class="y">x</a>',
            'chave solta'                => '<a href="/x" { class="y">x</a>',
            'aspa solta'                 => '<a href="/x" " class="y" ">x</a>',
            'aspa no valor sem aspas'    => '<a href=a"b"c>x</a>',
        ] as $why => $source) {
            self::assertNull($this->first($source)['attrs'], $why);
        }
        // Uma tag fechada nao fecha as vizinhas.
        $els = ThemeSourceMap::scan('<p><a href="/x" {{ a }}>x</a><a href="/y">y</a></p>');
        self::assertSame(['p', 'a', 'a'], array_column($els, 'tag'));
        self::assertNull($els[1]['attrs']);
        self::assertSame(['href'], array_column((array)$els[2]['attrs'], 'name'));
    }

    public function testQuoteInsideAnotherAttributeDoesNotShiftThePositions(): void {
        $source = "<a title='it\"s > here' href=\"/x\" data-j='{\"a\":1}' aria-label=\"Olá\">x</a>";
        $attrs = $this->first($source)['attrs'];
        self::assertSame(['href', 'aria-label'], array_column((array)$attrs, 'name'));
        self::assertSame('/x', $this->cut($source, $attrs[0], 'value'));
        self::assertSame('Olá', $this->cut($source, $attrs[1], 'value'));
    }

    public function testDescribeDeliversAttributesInEditorOffsets(): void {
        $source = "<p>ação 😀</p>\n<img src=\"a.png\" alt=\"Foto da équipe 😀\" />";
        $map = ThemeSourceMap::describe($source, ThemeSourceMap::scan($source));
        $utf16 = mb_convert_encoding($source, 'UTF-16LE', 'UTF-8');
        $cut = static fn (int $from, int $to): string => mb_convert_encoding(substr($utf16, $from * 2, ($to - $from) * 2), 'UTF-8', 'UTF-16LE');

        self::assertCount(8, $map['items'][1]);
        self::assertSame([], $map['items'][0][7]);
        [$name, $kind, $valueStart, $valueEnd, $spanStart, $spanEnd, $expr] = $map['items'][1][7][0];
        self::assertSame(['alt', 'fixed', ''], [$name, $kind, $expr]);
        // Posicoes como o JavaScript do editor enxerga a string (UTF-16), nao em bytes.
        self::assertSame('Foto da équipe 😀', $cut($valueStart, $valueEnd));
        self::assertSame('alt="Foto da équipe 😀"', $cut($spanStart, $spanEnd));
        self::assertNotSame($this->first('<img src="a.png" alt="Foto da équipe 😀" />')['attrs'][0]['value'][0], $valueStart);

        // Tag fechada viaja como null; o cliente nao recebe posicoes dela.
        $closed = '<a href="/x" {{ a }}>x</a>';
        self::assertNull(ThemeSourceMap::describe($closed, ThemeSourceMap::scan($closed))['items'][0][7]);
    }

    public function testAnnotatedSourceKeepsEveryAttributeByteForByte(): void {
        $source = '<a href="/x" target="_blank" rel="noopener">x</a><img src="a.png" alt="Foto">';
        $annotated = ThemeSourceMap::annotate('partial/hero', $source, ThemeSourceMap::scan($source));

        self::assertSame($source, preg_replace('/ data-kz-el="[a-z0-9_\/-]+:\d+"/', '', $annotated));
        self::assertSame(2, preg_match_all('/ data-kz-el="partial\/hero:\d+"/', $annotated));
    }

    public function testEveryDeliveredPresetHasReadableTagsAndTrustworthyFixedValues(): void {
        $presets = $this->presets();
        self::assertCount(6, $presets);
        $seen = [];
        foreach ($presets as $file) {
            $package = json_decode((string)file_get_contents($file), true);
            $name = basename($file, '.reamurtheme.json');
            $count = ['href' => [0, 0], 'alt' => [0, 0], 'aria-label' => [0, 0]];
            foreach ($package['config']['templates'] as $key => $source) {
                $source = ThemeSourceMap::normalize($source);
                foreach (ThemeSourceMap::scan($source) as $el) {
                    self::assertNotNull($el['attrs'], $name . ' ' . $key . ': tag fechada para o editor');
                    foreach ($el['attrs'] as $attr) {
                        $value = $this->cut($source, $attr, 'value');
                        if ($attr['kind'] === 'fixed') {
                            // O que o editor vai trocar e so texto: sem aspas, sem Twig, sem sinal de tag.
                            self::assertDoesNotMatchRegularExpression('/["\'<>]|\{[{%#]/', $value, $name . ' ' . $key);
                            self::assertSame('', $attr['expr']);
                        } else {
                            self::assertSame('variable', $attr['kind'], $name . ' ' . $key . ': ' . $value);
                        }
                        if (isset($count[$attr['name']])) $count[$attr['name']][$attr['kind'] === 'fixed' ? 0 : 1]++;
                    }
                }
            }
            $seen[$name] = $count;
        }

        // [fixos, com Twig] por pacote: e o que o painel oferece para editar hoje.
        self::assertSame([17, 86], $seen['Atlas-Studio']['href']);
        self::assertSame([17, 86], $seen['Atlas-Studio-3D']['href']);
        self::assertSame([95, 51], $seen['David-Creator']['href']);
        self::assertSame([45, 3], $seen['David-Universe-3D']['href']);
        self::assertSame([0, 18], $seen['Reamur-Reference']['href']);
        self::assertSame([0, 4], $seen['Reamur-ThreeJS-Reference']['href']);
        self::assertSame([50, 2], $seen['David-Universe-3D']['aria-label']);
        // Nenhum pacote entregue tem `alt` escrito a mao: so vindo do conteudo.
        self::assertSame(0, array_sum(array_map(static fn (array $c): int => $c['alt'][0], $seen)));
        self::assertSame([0, 11], $seen['Reamur-Reference']['alt']);
    }

    public function testEditorLoadsTheRulesBeforeTheCanvasAndEveryLabelExists(): void {
        $controller = $this->repo('dev/admin/controller/design/theme.php');
        $rules = strpos($controller, "view/js/kaz-element-attrs.js?v=");
        $canvas = strpos($controller, "view/js/kaz-canvas.js?v=");
        self::assertIsInt($rules);
        self::assertIsInt($canvas);
        self::assertLessThan($canvas, $rules, 'as regras precisam existir quando o canvas inicia');
        // A versao no endereco muda a cada etapa que mexe no canvas (KZ-06 foi a ultima).
        self::assertSame(1, preg_match("/'-kaz-canvas-kz\\d{2}'/", $controller));

        // Todo rotulo que o canvas pede existe na lista do controller e nos tres idiomas.
        self::assertSame(1, preg_match('/\$canvasLabels=\[\];\s*foreach\(\[(.*?)\] as \$canvasKey\)/s', $controller, $m));
        preg_match_all("/'([a-z0-9_]+)'/", $m[1], $listed);
        preg_match_all("/\blabel\('([a-z0-9_]+)'/", $this->repo('dev/admin/view/js/kaz-canvas.js'), $used);
        $asked = array_values(array_unique($used[1]));
        self::assertContains('el_stale', $asked);
        self::assertContains('el_newtab', $asked);
        self::assertContains('el_full', $asked);
        self::assertSame([], array_values(array_diff($asked, $listed[1])), 'rótulo usado no canvas e ausente do controller');
        // Os rotulos passados como argumento (campo, ajuda, "vem de") tambem.
        foreach (['el_link', 'el_link_help', 'el_link_variable', 'el_alt', 'el_alt_help', 'el_alt_variable', 'el_alt_missing',
                  'el_label', 'el_label_help', 'el_label_variable', 'el_attr_variable_help', 'el_attr_locked', 'el_attr_need_customize',
                  'el_newtab_help', 'el_newtab_locked', 'el_link_bad', 'el_links', 'el_links_help', 'el_links_more', 'el_in_link', 'el_go_link'] as $key) {
            self::assertContains($key, $listed[1], $key);
            self::assertStringContainsString("'" . $key . "'", $this->repo('dev/admin/view/js/kaz-canvas.js'), $key);
        }
        self::assertSame(count($listed[1]), count(array_unique($listed[1])), 'chave repetida na lista');
        foreach (['pt-br', 'en-us', 'es-eu'] as $language) {
            $_ = [];
            require DIR_REAMUR . 'dev/admin/language/' . $language . '/design/theme.php';
            foreach ($listed[1] as $key) {
                self::assertArrayHasKey('text_canvas_' . $key, $_, $language);
                self::assertNotSame('', trim((string)$_['text_canvas_' . $key]), $language . ' ' . $key);
            }
            self::assertSame(1, substr_count((string)$_['text_canvas_el_links_more'], '%s'), $language);
        }
    }

    public function testCanvasNeverWritesAttributesWithoutTheRulesModule(): void {
        $canvas = $this->repo('dev/admin/view/js/kaz-canvas.js');
        $rules = $this->repo('dev/admin/view/js/kaz-element-attrs.js');

        self::assertStringContainsString('root.KazElementAttrs = {', $rules);
        self::assertStringContainsString("})(typeof window !== 'undefined' ? window : globalThis);", $rules);
        self::assertStringContainsString('window.KazElementAttrs', $canvas);
        // As tres escritas passam pelo modulo de regras; o canvas nao monta atributo por conta propria.
        self::assertStringContainsString('ATTRS.setValue(', $canvas);
        self::assertStringContainsString('ATTRS.setNewTab(', $canvas);
        self::assertStringNotContainsString('target="_blank"', $canvas);
        self::assertStringNotContainsString("setAttribute('href'", $canvas);
        // O modulo de regras nao toca em pagina, rede nem armazenamento.
        self::assertDoesNotMatchRegularExpression('/\b(document|fetch|XMLHttpRequest|localStorage|sessionStorage|eval|innerHTML)\b/', (string)preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $rules));
    }

    public function testNodeCheckerOfTheWritingRulesPasses(): void {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            self::markTestSkipped('node ausente: rode `node tests/check_kaz_element_attrs.js` à mão.');
        }
        $output = [];
        $status = 1;
        exec(escapeshellarg($node) . ' ' . escapeshellarg(DIR_REAMUR . 'tests/check_kaz_element_attrs.js') . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", array_slice($output, -8)));
        self::assertMatchesRegularExpression('/^Kaz element attrs checker: \d{3,} PASS$/', (string)end($output));
    }

    public function testSaveButtonIsNotBlockedByScreenOnlyControls(): void {
        $editor = $this->repo('dev/admin/view/js/kaz-theme-editor.js');
        $twig = $this->repo('dev/admin/view/template/design/kaz_theme_editor.twig');

        // A conferencia antes do envio olha so campos com `name` (os que o servidor recebe).
        self::assertStringContainsString('form.noValidate = true;', $editor);
        self::assertStringContainsString('els[i].name && els[i].willValidate && !els[i].checkValidity()', $editor);
        self::assertStringContainsString("'-kaz-theme-editor-kz05'", $this->repo('dev/admin/controller/design/theme.php'));
        // Os controles que travavam o envio continuam sem `name`: nunca viajam no POST.
        self::assertSame(1, preg_match('/<input[^>]*id="kaz-screen-h"[^>]*>/', $twig, $m));
        self::assertStringNotContainsString('name=', $m[0]);
        self::assertSame(1, preg_match('/<input[^>]*id="kaz-w"[^>]*>/', $twig, $m));
        self::assertStringNotContainsString('name=', $m[0]);
        // O padrao do slug precisa ser uma expressao que o navegador aceita (hifen escapado) e igual a regra do servidor.
        self::assertStringContainsString('name="slug" required pattern="[a-z0-9_\-]{1,48}"', $twig);
        self::assertSame(1, preg_match('/^[a-z0-9_\-]{1,48}$/D', 'tema_novo-2'));
    }
}
