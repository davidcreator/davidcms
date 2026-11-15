<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Template\ThemeSandbox;
use Reamur\System\Library\Theme\ThemeSourceMap;

/**
 * KZ-09 (ADR 0102): link e botao sem nome para o leitor de tela. Cobre a parte
 * do servidor (o resumo do que ha dentro de cada <a> e <button>, e o `title`
 * dessas duas tags), o que os pacotes entregues tem hoje, a limpeza dos 30
 * selos de `David-Universe-3D` e a ligacao entre controller, idiomas e os dois
 * scripts. As regras ficam em `dev/admin/view/js/kaz-element-attrs.js` e sao
 * exercitadas por `tests/check_kaz_element_name.js`, chamado daqui de dois
 * jeitos: com o leitor de tags do proprio teste e com os mapas que
 * `ThemeSourceMap::describe()` monta para os mesmos casos.
 */
final class KazElementNameTest extends TestCase {
    private const KEYS = ['el_link_noname', 'el_button_noname', 'el_name_waiting', 'el_name_hidden', 'el_name_title', 'a11y_link', 'a11y_button', 'a11y_to'];
    private const ORDER = ['Atlas-Studio', 'Atlas-Studio-3D', 'David-Creator', 'David-Universe-3D', 'Reamur-Reference', 'Reamur-ThreeJS-Reference'];
    private const CHECKER = 'tests/check_kaz_element_name.js';

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
     * O resumo `inner` de cada <a>/<button> de uma fonte, na ordem do codigo.
     *
     * @return list<list<int>>
     */
    private function inner(string $source): array {
        $out = [];
        foreach (ThemeSourceMap::scan($source) as $el) {
            if (isset($el['inner'])) $out[] = $el['inner'];
        }

        return $out;
    }

    private function node(): string {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            self::markTestSkipped('node ausente: rode `node ' . self::CHECKER . '` à mão.');
        }

        return $node;
    }

    /**
     * Roda o conferidor com argumentos e devolve [codigo de saida, linhas].
     *
     * @param list<string> $args
     * @return array{0:int,1:list<string>}
     */
    private function checker(array $args): array {
        $command = escapeshellarg($this->node()) . ' ' . escapeshellarg(DIR_REAMUR . self::CHECKER);
        foreach ($args as $arg) $command .= ' ' . escapeshellarg($arg);
        $output = [];
        $status = 1;
        exec($command . ' 2>&1', $output, $status);

        return [$status, $output];
    }

    /** Arquivo temporario com um JSON, apagado no fim do teste. */
    private function jsonFile(mixed $data): string {
        $file = tempnam(sys_get_temp_dir(), 'kz09');
        self::assertIsString($file);
        self::assertNotFalse(file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)));

        return $file;
    }

    public function testSummaryTellsWhatIsInsideEachLinkAndButton(): void {
        $text = ThemeSourceMap::INNER_TEXT;
        $twig = ThemeSourceMap::INNER_TWIG;
        $hidden = ThemeSourceMap::INNER_HIDDEN;
        $unsure = ThemeSourceMap::INNER_UNSURE;
        self::assertSame([1, 2, 4, 8], [$text, $twig, $hidden, $unsure]);

        // Link-folha: o texto e dele (o `kind` responde); o resumo fica so com as marcas zeradas.
        self::assertSame([[0]], $this->inner('<a href="/x">Texto</a>'));
        self::assertSame([[0]], $this->inner('<a href="/x">{{ nome }}</a>'));
        self::assertSame([[0]], $this->inner('<button></button>'));
        // Elementos de dentro, em qualquer profundidade, pelo `n` de cada um.
        self::assertSame([[0, 1, 2, 3]], $this->inner('<a href="/x"><span><b>a</b></span><i></i></a>'));
        self::assertSame([[0, 2]], $this->inner('<p>a</p><button><i></i></button><p>z</p>'));
        // Texto solto e Twig solto (fora de um elemento-folha).
        self::assertSame([[$text, 1]], $this->inner('<a href="/x"><i></i> Ver</a>'));
        self::assertSame([[$text, 1]], $this->inner('<a href="/x">Ver <i></i></a>'));
        self::assertSame([[$twig, 1]], $this->inner('<a href="/x">{% if a %}<i></i>{% endif %}</a>'));
        self::assertSame([[$text | $twig, 1]], $this->inner('<a href="/x">{{ a }} e <i></i></a>'));
        // O texto de um elemento-folha de dentro nao e texto solto: quem responde e o `kind` dele.
        self::assertSame([[0, 1]], $this->inner('<a href="/x"><span>Ver</span></a>'));
        self::assertSame([[0, 1]], $this->inner('<a href="/x"><span>{{ t }}</span></a>'));
        // Comentario (HTML ou Twig) e espaco nao sao texto.
        self::assertSame([[0, 1]], $this->inner("<a href=\"/x\"> <!-- c -->\n<i></i>{# c #}\t</a>"));
        // Nada de dentro de <svg> conta: nem o texto, nem o <a> que o desenho tem.
        self::assertSame([[0, 1]], $this->inner('<a href="/x"><svg><a href="#i"><text>oi</text></a>{{ x }}</svg></a>'));
        // O </a> do desenho nao fecha o link em volta: o texto que vem depois ainda e do link.
        self::assertSame([[$text, 1]], $this->inner('<a href="/x"><svg aria-hidden="true"><a href="#i"><path/></a></svg> Ver</a>'));
        self::assertSame([[0, 1, 2]], $this->inner('<button><svg><a href="#i"><path/></a></svg><i></i></button>'));
        // <script> e <style> nao dao nome.
        self::assertSame([[0]], $this->inner('<a href="/x"><script>var a = 1;</script><style>a{}</style></a>'));
    }

    public function testWhatIsHiddenFromTheScreenReaderGivesNoName(): void {
        $text = ThemeSourceMap::INNER_TEXT;
        $hidden = ThemeSourceMap::INNER_HIDDEN;
        $unsure = ThemeSourceMap::INNER_UNSURE;

        // Elemento escondido de dentro: nem ele, nem o que ha nele, nem o texto dele entram.
        self::assertSame([[0]], $this->inner('<a href="/x"><span aria-hidden="true">×</span></a>'));
        self::assertSame([[0]], $this->inner('<a href="/x"><span aria-hidden="true"><b>x</b> y</span></a>'));
        self::assertSame([[0, 3]], $this->inner('<a href="/x"><span aria-hidden="true"><span>a</span> b</span><i></i></a>'));
        self::assertSame([[$text]], $this->inner('<a href="/x"><span aria-hidden="true">a</span> b</a>'));
        // Sem aspas e com maiusculas valem como o navegador le; "false" e valor de Twig nao escondem.
        self::assertSame([[0]], $this->inner('<a href="/x"><span aria-hidden=TRUE>a</span></a>'));
        self::assertSame([[0, 1]], $this->inner('<a href="/x"><span aria-hidden="false">a</span></a>'));
        self::assertSame([[0, 1]], $this->inner('<a href="/x"><span aria-hidden="{{ h }}">a</span></a>'));
        // Imagem e desenho entram mesmo escondidos: a marca deles muda no painel, e quem a le e o navegador.
        self::assertSame([[0, 1, 2]], $this->inner('<a href="/x"><img src="a.png" aria-hidden="true"><svg aria-hidden="true"><path/></svg></a>'));
        // ... mas nao quando estao dentro de outro elemento escondido.
        self::assertSame([[0]], $this->inner('<a href="/x"><span aria-hidden="true"><img src="a.png" alt="Foto"></span></a>'));

        // Link ou botao DENTRO de um trecho escondido: marcado, e sem lista.
        self::assertSame([[$hidden], [$hidden], [0, 6]], $this->inner('<div aria-hidden="true"><a href="/x"><i></i></a><div><button>y</button></div></div><a href="/z"><i></i></a>'));
        // O proprio elemento escondido nao leva a marca (o atributo esta nele, o navegador le), e o que ha nele nao conta.
        self::assertSame([[0], [0, 3]], $this->inner('<a href="/x" aria-hidden="true">x <i></i></a><a href="/y"><i></i></a>'));
        // O fechamento do link encerra o trecho escondido que abriu dentro dele e ficou sem fechar.
        self::assertSame([[0], [0, 3]], $this->inner('<a href="/x"><span aria-hidden="true">x</a><a href="/y"><i></i></a>'));

        // Tag de fechamento opcional: esconde o que vem dentro, mas o fim e incerto para o link que esta em volta.
        self::assertSame([[$hidden]], $this->inner('<ul><li aria-hidden="true"><a href="/x"><i></i></a></li></ul>'));
        self::assertSame([[$unsure | $text]], $this->inner('<a href="/x"><p aria-hidden="true">x</p>y</a>'));
    }

    public function testTheEditorNeverConcludesAboutWhatItCannotSee(): void {
        $unsure = ThemeSourceMap::INNER_UNSURE;

        // Sem fechamento nesta fonte (o link fecha em outro trecho).
        self::assertSame([[$unsure, 1]], $this->inner('<a href="/x"><i></i>'));
        self::assertSame([[0, 1], [$unsure, 3]], $this->inner('<a href="/x"><i></i></a><button><i></i>'));
        // <a><button></a>: o de dentro ficou sem fechamento; o de fora fechou.
        self::assertSame([[0, 1, 2], [$unsure, 2]], $this->inner('<a href="/x"><button><i></i></a>'));
        // Um </a> a mais nao fecha nada.
        self::assertSame([[0]], $this->inner('</a><a href="/x"></a></a>'));
        // Nome de tag montado com Twig dentro do link: nao entra no mapa, e pode ser dele o nome.
        self::assertSame([[$unsure | ThemeSourceMap::INNER_TEXT]], $this->inner('<a href="/x"><h{{ n }}>Título</h{{ n }}></a>'));
        // Tag ilegivel: os atributos vem null, e o navegador nao julga.
        $els = ThemeSourceMap::scan('<a href="/x" {{ extra }}><i></i></a>');
        self::assertNull($els[0]['attrs']);
        self::assertSame([0, 1], $els[0]['inner']);

        // Alem do limite do mapa: o que ha dentro do ultimo link mapeado nao pode ser olhado.
        $source = str_repeat('<i></i>', ThemeSourceMap::MAX_ELEMENTS - 1) . '<a href="/x"><img src="a.png" alt="Foto"></a><a href="/y"></a>';
        $els = ThemeSourceMap::scan($source);
        self::assertCount(ThemeSourceMap::MAX_ELEMENTS, $els);
        self::assertSame('a', $els[ThemeSourceMap::MAX_ELEMENTS - 1]['tag']);
        self::assertSame([$unsure], $els[ThemeSourceMap::MAX_ELEMENTS - 1]['inner']);
    }

    public function testTitleIsReadOnLinksAndButtonsOnly(): void {
        $names = static fn (string $source): array => array_column((array)ThemeSourceMap::scan($source)[0]['attrs'], 'name');

        self::assertSame(['href', 'title'], $names('<a href="/x" title="Dica"><i></i></a>'));
        self::assertSame(['title', 'aria-label'], $names('<button title="Dica" aria-label="Fechar" type="button"></button>'));
        self::assertSame([], $names('<p title="Dica">x</p>'));
        self::assertSame([], $names('<img src="a.png" title="Dica">'));
        self::assertSame([], $names('<svg title="Dica"><path/></svg>'));

        $source = '<a href="/x" title="{{ dica }}"><i></i></a>';
        $attr = ThemeSourceMap::scan($source)[0]['attrs'][1];
        self::assertSame(['title', 'variable'], [$attr['name'], $attr['kind']]);
    }

    public function testDescribeSendsTheSummaryOnlyOnLinksAndButtons(): void {
        $source = '<nav><a href="/é">Início 😀</a><a href="/b"><svg><title>t</title></svg> b</a><button type="button"><img src="c.png"></button><p>z</p></nav>';
        $map = ThemeSourceMap::describe($source, ThemeSourceMap::scan($source));
        $ninth = [];
        foreach ($map['items'] as $item) $ninth[] = [$item[0], count($item), $item[8] ?? null];

        self::assertSame([
            ['nav', 8, null],
            ['a', 9, [0]],
            ['a', 9, [ThemeSourceMap::INNER_TEXT, 3]],
            ['svg', 9, 1],
            ['button', 9, [0, 5]],
            ['img', 8, null],
            ['p', 8, null],
        ], $ninth);
        // No JSON o resumo e uma lista de numeros (nunca um objeto), e os indices sao os da lista de itens.
        $json = (string)json_encode($map, JSON_THROW_ON_ERROR);
        self::assertStringContainsString(',[1,3]]', $json);
        self::assertStringContainsString(',[0,5]]', $json);
        self::assertSame('svg', $map['items'][3][0]);
        self::assertSame('img', $map['items'][5][0]);
    }

    public function testAnnotatedSourceKeepsEveryByte(): void {
        $source = '<a href="/x" title="Dica"><span aria-hidden="true">×</span><svg aria-hidden="true"><path/></svg></a><button aria-hidden="true"><i></i></button>';
        $annotated = ThemeSourceMap::annotate('partial/hero', $source, ThemeSourceMap::scan($source));

        self::assertSame($source, (string)preg_replace('/ data-kz-el="[^"]*"/', '', $annotated));
        self::assertSame(5, substr_count($annotated, ' data-kz-el="partial/hero:'));
    }

    public function testLinksAndButtonsOfTheDeliveredPresets(): void {
        $list = [];
        foreach ($this->presets() as $file) {
            $pack = json_decode((string)file_get_contents($file), true);
            foreach ((array)$pack['config']['templates'] as $source) {
                $source = ThemeSourceMap::normalize((string)$source);
                $list[] = ['pack' => basename($file, '.reamurtheme.json'), 'src' => $source, 'map' => ThemeSourceMap::describe($source, ThemeSourceMap::scan($source))];
            }
        }
        $file = $this->jsonFile($list);
        try {
            [$status, $output] = $this->checker(['--count', $file]);
        } finally {
            unlink($file);
        }
        self::assertSame(0, $status, implode("\n", array_slice($output, -8)));
        $seen = json_decode(implode('', $output), true);
        self::assertIsArray($seen);
        self::assertSame(self::ORDER, array_values(array_intersect(self::ORDER, array_keys($seen))));
        $column = static fn (string $what): array => array_map(static fn (string $name): int => $seen[$name][$what], self::ORDER);

        self::assertSame([103, 103, 146, 48, 18, 4], $column('link'));
        self::assertSame([0, 0, 0, 3, 0, 0], $column('button'));
        // Nome escrito no tema (texto ou rotulo) e nome que vem de dado do site.
        self::assertSame([90, 90, 140, 49, 0, 0], $column('named'));
        self::assertSame([13, 13, 6, 2, 18, 4], $column('data'));
        // Pacote entregue sai sem link ou botao pendente, e sem nenhum que o editor nao consiga conferir.
        self::assertSame([0, 0, 0, 0, 0, 0], $column('missing'));
        self::assertSame([0, 0, 0, 0, 0, 0], $column('waiting'));
        self::assertSame([0, 0, 0, 0, 0, 0], $column('unknown'));
        self::assertSame([0, 0, 0, 0, 0, 0], $column('hidden'));
    }

    public function testBadgesOfDavidUniverseAreOnlyDecoration(): void {
        $pack = json_decode($this->repo('docs/temas/David-Universe-3D.reamurtheme.json'), true);
        $seen = [];
        foreach ((array)$pack['config']['templates'] as $key => $source) {
            $source = (string)$source;
            // O rotulo que nunca era lido (a mesma tag tinha aria-hidden="true") saiu de todos os modelos.
            self::assertStringNotContainsString('Selo do projeto', $source, (string)$key);
            foreach (ThemeSourceMap::scan($source) as $el) {
                if ($el['tag'] !== 'svg') continue;
                self::assertIsArray($el['attrs'], (string)$key);
                $marks = [];
                foreach ($el['attrs'] as $attr) $marks[$attr['name']] = substr($source, $attr['value'][0], $attr['value'][1] - $attr['value'][0]);
                // So a marca de enfeite: sem `role`, sem `aria-label`.
                self::assertSame(['aria-hidden' => 'true'], $marks, $key . ' #' . $el['n']);
                self::assertFalse($el['titled'] ?? false);
                // O resto da tag ficou como era.
                self::assertSame('<svg viewBox="0 0 160 120" fill="none" aria-hidden="true">', substr($source, $el['open'][0], $el['open'][1] - $el['open'][0]), $key . ' #' . $el['n']);
                $seen[(string)$key] = ($seen[(string)$key] ?? 0) + 1;
            }
        }
        ksort($seen);
        self::assertSame(['common/home' => 15, 'partial/news' => 6, 'partial/portfolio' => 9], $seen);
    }

    public function testWhatTheEditorWritesPassesTheThemePolicy(): void {
        // As formas que putAttr() escreve num link e num botao (ver tests/check_kaz_element_name.js, bloco 5).
        foreach ([
            '<nav><a aria-label="Página inicial" href="/x" class="b"><svg aria-hidden="true" class="i"><path/></svg></a><p>z</p></nav>',
            '<button aria-label="Fechar &quot;tudo&quot; &lt;já&gt;" type="button" class="x"><span class="icon"></span></button>',
        ] as $source) {
            self::assertFalse(ThemeSandbox::blocked($source), $source);
            self::assertNull(ThemeSandbox::check($source), $source);
        }
    }

    public function testEditorAsksOnlyForLabelsThatExistInTheThreeLanguages(): void {
        $controller = $this->repo('dev/admin/controller/design/theme.php');
        $canvas = $this->repo('dev/admin/view/js/kaz-canvas.js');

        // O navegador busca os dois scripts novos: a versao no endereco e a desta etapa.
        self::assertStringContainsString("'-kaz-canvas-kz09'", $controller);
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
            foreach (array_merge(self::KEYS, ['a11y_title', 'a11y_help', 'a11y_none', 'a11y_unknown', 'a11y_img', 'a11y_svg']) as $key) {
                self::assertArrayHasKey('text_canvas_' . $key, $_, $language);
                $texts[$key] = trim((string)$_['text_canvas_' . $key]);
                self::assertNotSame('', $texts[$key], $language . ' ' . $key);
            }
            // Quatro linhas diferentes na lista, e duas frases diferentes no painel.
            self::assertCount(4, array_unique([$texts['a11y_img'], $texts['a11y_svg'], $texts['a11y_link'], $texts['a11y_button']]), $language);
            self::assertNotSame($texts['el_link_noname'], $texts['el_button_noname'], $language);
            // As frases dizem o atributo de que falam; a contagem dos nao conferidos continua com o lugar do numero.
            self::assertStringContainsString('aria-hidden', $texts['el_name_hidden'], $language);
            self::assertStringContainsString('(title)', $texts['el_name_title'], $language);
            self::assertSame(1, substr_count($texts['a11y_unknown'], '%s'), $language);
            self::assertSame(1, substr_count($texts['a11y_to'], '%s'), $language);
        }
        // O titulo e a ajuda da lista deixaram de falar so de imagem e desenho.
        $_ = [];
        require DIR_REAMUR . 'dev/admin/language/pt-br/design/theme.php';
        self::assertSame('Sem descrição ou sem nome', $_['text_canvas_a11y_title']);
        self::assertStringContainsString('links e botões sem nome', $_['text_canvas_a11y_help']);
        self::assertStringContainsString('link ou botão sem nome', $_['text_canvas_a11y_none']);
    }

    public function testCanvasShowsLinksAndButtonsThroughTheSameListAndRules(): void {
        $canvas = $this->repo('dev/admin/view/js/kaz-canvas.js');
        $rules = $this->repo('dev/admin/view/js/kaz-element-attrs.js');

        // A lista: uma linha por tag, com o rotulo tirado de uma tabela fechada.
        self::assertStringContainsString("var A11Y_ROWS = { img: 'a11y_img', svg: 'a11y_svg', a: 'a11y_link', button: 'a11y_button' };", $canvas);
        self::assertStringContainsString("label(A11Y_ROWS[it[0]] || 'a11y_svg', '')", $canvas);
        self::assertStringNotContainsString("it[0] === 'img' ? 'a11y_img' : 'a11y_svg'", $canvas);
        // O link e reconhecido pelo destino escrito no codigo; o que veio do codigo nunca e lido como padrao de troca.
        self::assertStringContainsString("var to = it[0] === 'a' ? ATTRS.find(it, 'href') : null;", $canvas);
        self::assertStringContainsString("label('a11y_to', '').replace('%s', function () { return dest; })", $canvas);
        // Destino comprido e cortado; sem destino (botao, ou link de `href` sem valor), vale o texto ao lado, como nas imagens.
        self::assertStringContainsString("if (dest.length > A11Y_NEAR_MAX) dest = dest.slice(0, A11Y_NEAR_MAX) + '…';", $canvas);
        self::assertStringContainsString("var near = dest === '' ? a11yNear(g.key, n) : '';", $canvas);
        // O painel: frase propria, sem botao de enfeite; quem resolve e o campo do rotulo.
        self::assertStringContainsString("var A11Y_NONAME = { link: 'el_link_noname', button: 'el_button_noname' };", $canvas);
        self::assertStringContainsString("if (st.state === 'missing') note(A11Y_NONAME[st.kind], true);", $canvas);
        self::assertStringContainsString("else if (st.state === 'waiting') note('el_name_waiting', true);", $canvas);
        self::assertStringContainsString("else if (st.state === 'hidden') note('el_name_hidden', false);", $canvas);
        self::assertStringContainsString("else if (st.state === 'named' && st.via === 'title') note('el_name_title', false);", $canvas);
        self::assertStringContainsString("else if (st.state === 'named' && st.via === 'aria-labelledby') note('el_a11y_labelledby', false);", $canvas);
        // ... e as frases de imagem e de desenho (com o botao de enfeite) ficam no `else`: nunca junto com as do link.
        self::assertSame(1, preg_match('/note\\(\'el_a11y_labelledby\', false\\);\\s*\\}\\s*else if \\(st\\.state === \'missing\'\\) \\{ note\\(st\\.kind === \'img\'/', $canvas));
        self::assertSame(1, substr_count($canvas, "act('decor', 'el_decor');"));
        // No link ou botao sem nome a ajuda do campo nao diz "opcional"; o nome e julgado com o mapa inteiro do trecho.
        self::assertStringContainsString("ATTRS.a11y(src, elements[key], picked.n)", $canvas);
        self::assertStringContainsString("(hasLabel || nameless ? 'el_label_help' : 'el_label_new_help')", $canvas);
        self::assertStringContainsString("var nameless = !!named && (named.state === 'missing' || named.state === 'waiting');", $canvas);
        // Apagar o texto de um link atualiza a frase do painel (a lista ja acompanha por commitSource).
        self::assertSame(1, preg_match('/commitSource\(key, map, next\);\s*\/\*[^*]*\*\/\s*paintA11yState\(\);\s*\}/', $canvas));
        // Nenhuma escrita nova: o rotulo continua passando por putAttr(), e nada escreve `title`.
        self::assertSame(1, substr_count($canvas, 'ATTRS.putAttr('));
        self::assertStringContainsString("var ATTR_FIELDS = ['href', 'alt', 'aria-label'];", $canvas);
        self::assertStringContainsString("var EDITABLE = { href: 'a', alt: 'img', 'aria-label': '' };", $rules);

        // As regras: so as duas tags, as marcas do servidor e a lista de atributos lidos.
        self::assertStringContainsString("var HOLDER_TAGS = ['a', 'button'];", $rules);
        self::assertStringContainsString('var INNER_TEXT = 1, INNER_TWIG = 2, INNER_HIDDEN = 4, INNER_UNSURE = 8;', $rules);
        self::assertStringContainsString("var NAMES = ['href', 'target', 'rel', 'alt', 'aria-label', 'style', 'aria-hidden', 'aria-labelledby', 'role', 'title'];", $rules);
        self::assertStringContainsString('HOLDER_TAGS: HOLDER_TAGS.slice(),', $rules);
    }

    public function testNodeCheckerOfTheNameRulesPasses(): void {
        [$status, $output] = $this->checker([]);

        self::assertSame(0, $status, implode("\n", array_slice($output, -8)));
        self::assertMatchesRegularExpression('/^Kaz element name checker: \d{3,} PASS$/', (string)end($output));
    }

    public function testRulesGiveTheSameAnswerWithTheMapsOfTheServer(): void {
        [$status, $output] = $this->checker(['--dump']);
        self::assertSame(0, $status, implode("\n", array_slice($output, -8)));
        $sources = json_decode(implode("\n", $output), true);
        self::assertIsArray($sources);
        self::assertGreaterThanOrEqual(75, count($sources));

        $maps = [];
        foreach ($sources as $source) {
            self::assertIsString($source);
            // Os casos ja estao como o editor guarda a fonte.
            self::assertSame($source, ThemeSourceMap::normalize($source));
            $maps[] = ThemeSourceMap::describe($source, ThemeSourceMap::scan($source));
        }
        $file = $this->jsonFile($maps);
        try {
            [$status, $output] = $this->checker(['--maps', $file]);
        } finally {
            unlink($file);
        }
        self::assertSame(0, $status, implode("\n", array_slice($output, -8)));
        self::assertMatchesRegularExpression('/^Kaz element name checker \(mapas do servidor\): \d{3,} PASS$/', (string)end($output));
    }
}
