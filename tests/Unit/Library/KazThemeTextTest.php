<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Theme\ThemeSourceMap;
use Reamur\System\Library\Theme\ThemeStrings;

/**
 * KZ-21 (ADR 0114): texto do tema editado por idioma, no canvas do editor de tema.
 *
 * Um elemento que so imprime `{{ theme_text.chave }}` abre para edicao como um
 * texto fixo (campo do painel e dois cliques na pagina). O que e digitado vai
 * para o JSON dos textos do tema (`strings_json`), no idioma que o canvas
 * mostra. O servidor continua sendo quem valida; o script so nao pode dizer
 * outra coisa. Por isso os casos de `tests/fixtures/kaz_theme_text.json` sao
 * rodados dos dois lados: aqui sobre ThemeStrings, e em node, sobre as funcoes
 * do script, por `tests/check_kaz_theme_text.js`.
 */
final class KazThemeTextTest extends TestCase {
    private const CHECKER = 'tests/check_kaz_theme_text.js';
    private const FIXTURE = 'tests/fixtures/kaz_theme_text.json';
    private const SCRIPT = 'dev/admin/view/js/kaz-canvas.js';
    private const CONTROLLER = 'dev/admin/controller/design/theme.php';
    private const START = '  /* ---------------- KZ-21 (ADR 0114): texto do tema editado por idioma ---------------- */';
    private const END = '  /* ---------------- KZ-18 (ADR 0111): texto editado direto na pagina ---------------- */';
    private const PRETTY = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    /** Escritas aqui, e nao so contadas: uma traducao trocada ou deixada em outro idioma aparece. */
    private const TEXTS = [
        'pt-br' => [
            'theme_text' => 'Texto do tema em %s',
            'theme_text_help' => 'Texto próprio do tema, guardado por idioma. O que for digitado vale para o idioma do canvas, em todos os lugares que usam a chave',
            'theme_fallback' => 'Sem tradução neste idioma: o site mostra o texto de %s. Digite para criar a tradução.',
            'theme_bad_json' => 'O JSON dos textos do tema está inválido. Corrija-o no bloco "Textos do tema por idioma" para editar este texto.',
            'theme_empty' => 'O texto do tema não pode ficar vazio. O último texto foi mantido.',
            'theme_lines' => 'Este texto tem quebra de linha. Edite-o no bloco "Textos do tema por idioma".',
            'theme_full' => 'Os textos do tema chegaram ao limite. Este texto não foi gravado.',
            'theme_texts' => 'Textos do tema neste trecho (%s)',
            'theme_texts_help' => 'Textos próprios do tema, no idioma do canvas. Escolha um para editar.',
        ],
        'en-us' => [
            'theme_text' => 'Theme text in %s',
            'theme_text_help' => 'Text owned by the theme, stored per language. What you type applies to the canvas language, everywhere the theme uses the key',
            'theme_fallback' => 'No translation in this language: the site shows the %s text. Type to create the translation.',
            'theme_bad_json' => 'The theme texts JSON is invalid. Fix it in the "Theme texts per language" block to edit this text.',
            'theme_empty' => 'The theme text cannot be empty. The last text was kept.',
            'theme_lines' => 'This text has a line break. Edit it in the "Theme texts per language" block.',
            'theme_full' => 'The theme texts reached the limit. This text was not saved.',
            'theme_texts' => 'Theme texts in this piece (%s)',
            'theme_texts_help' => 'Texts owned by the theme, in the canvas language. Choose one to edit it.',
        ],
        'es-eu' => [
            'theme_text' => 'Texto del tema en %s',
            'theme_text_help' => 'Texto propio del tema, guardado por idioma. Lo que escribas vale para el idioma del canvas, en todos los lugares que usan la clave',
            'theme_fallback' => 'Sin traducción en este idioma: el sitio muestra el texto de %s. Escribe para crear la traducción.',
            'theme_bad_json' => 'El JSON de los textos del tema no es válido. Corrígelo en el bloque "Textos del tema por idioma" para editar este texto.',
            'theme_empty' => 'El texto del tema no puede quedar vacío. Se mantuvo el último texto.',
            'theme_lines' => 'Este texto tiene un salto de línea. Edítalo en el bloque "Textos del tema por idioma".',
            'theme_full' => 'Los textos del tema llegaron al límite. Este texto no se guardó.',
            'theme_texts' => 'Textos del tema en este fragmento (%s)',
            'theme_texts_help' => 'Textos propios del tema, en el idioma del canvas. Elige uno para editarlo.',
        ],
    ];

    private function repo(string $path): string {
        $source = file_get_contents(DIR_REAMUR . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    /** @return array<string,string> */
    private function language(string $code): array {
        $_ = [];
        require DIR_REAMUR . 'dev/admin/language/' . $code . '/design/theme.php';

        return array_filter($_, 'is_string');
    }

    /** @return array<string,mixed> */
    private function fixture(): array {
        $data = json_decode($this->repo(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /** O bloco da KZ-21 em kaz-canvas.js, sem os comentarios. */
    private function block(): string {
        $script = $this->repo(self::SCRIPT);
        self::assertSame(1, substr_count($script, self::START));
        $from = strpos($script, self::START);
        $to = strpos($script, self::END);
        self::assertIsInt($from);
        self::assertIsInt($to);
        self::assertGreaterThan($from, $to);

        return (string)preg_replace('~/\*.*?\*/~s', '', substr($script, $from, $to - $from));
    }

    /**
     * O mapa de uma receita de limite (a mesma montagem de check_kaz_theme_text.js).
     * @param array<string,mixed> $recipe
     * @return array<string,array<string,string>>
     */
    private function make(array $recipe): array {
        $map = [];
        for ($i = 0; $i < $recipe['count']; $i++) {
            $row = [];
            foreach ($recipe['langs'] as $code) $row[$code] = str_repeat($recipe['fill'], $recipe['times']);
            $map[$recipe['prefix'] . sprintf('%03d', $i)] = $row;
        }
        foreach ($recipe['extra'] ?? [] as $key => $row) $map[$key] = $row;

        return $map;
    }

    /** O que o controller faz com o campo `strings_json` ao salvar: tamanho em bytes como chega, e o contrato. */
    private function serverAccepts(string $json): bool {
        $sent = str_replace("\n", "\r\n", $json);
        if (strlen(trim($sent)) > ThemeStrings::MAX_JSON_BYTES) return false;
        $decoded = json_decode($sent, true);

        return is_array($decoded) && ThemeStrings::normalize($decoded) !== null;
    }

    public function testTheScriptLimitsAreTheServerLimits(): void {
        $block = $this->block();
        self::assertStringContainsString(
            'var THEME_KEYS_MAX = ' . ThemeStrings::MAX_KEYS . ', THEME_LANGS_MAX = ' . ThemeStrings::MAX_LANGUAGES
            . ', THEME_TEXT_MAX = ' . ThemeStrings::MAX_LENGTH . ', THEME_JSON_MAX = ' . ThemeStrings::MAX_JSON_BYTES . ';',
            $block
        );
        self::assertStringContainsString("THEME_FALLBACK = '" . ThemeStrings::FALLBACK . "';", $block);
        // As gramaticas de chave e de idioma sao as do servidor (constantes privadas de ThemeStrings).
        $class = new \ReflectionClass(ThemeStrings::class);
        $key = (string)$class->getConstant('KEY');
        $language = (string)$class->getConstant('LANGUAGE');
        self::assertSame('/^[a-z][a-z0-9_]{0,63}$/D', $key);
        self::assertSame('/^[a-z]{2}-[a-z]{2}$/D', $language);
        self::assertStringContainsString('var THEME_KEY = ' . substr($key, 0, -1) . ', THEME_LANG = ' . substr($language, 0, -1) . ',', $block);
        // A variavel que as fontes imprimem, e a mesma chave dentro da expressao.
        self::assertSame('theme_text', ThemeStrings::GLOBAL);
        self::assertStringContainsString('var THEME_REF = /^' . ThemeStrings::GLOBAL . '\.([a-z][a-z0-9_]{0,63})(?: ?\| ?(upper|lower|nl2br))?$/;', $block); // KZ-24: nl2br aceito
        // A expressao mais longa que o script aceita cabe inteira no mapa: uma expressao cortada pelo
        // servidor tem exatamente MAX_EXPR caracteres e, por isso, nunca e lida como outra chave.
        $longest = strlen(ThemeStrings::GLOBAL . '.' . str_repeat('k', 64) . ' | upper');
        self::assertSame(83, $longest);
        self::assertLessThan(ThemeSourceMap::MAX_EXPR, $longest);
        // O campo do formulario e o que o controller le.
        self::assertStringContainsString("var el = form.elements['strings_json'];", $block);
        self::assertStringContainsString("if (array_key_exists('strings_json',\$post)) {", $this->repo(self::CONTROLLER));
        self::assertStringContainsString('name="strings_json"', $this->repo('dev/admin/view/template/design/kaz_theme_editor.twig'));
    }

    public function testTheSourceMapDescribesAThemeTextElementTheWayTheScriptReadsIt(): void {
        $source = '<section><h3>{{ theme_text.hero_title }}</h3><b>{{ theme_text.control_mouse|upper }}</b>'
            . '<i>{{  theme_text.hero_title  |  lower  }}</i><p>{{ theme_text.hero_title }} fixo</p><span>{{ project.title }}</span>'
            . '<em>{{ theme_text.' . str_repeat('k', 64) . ' }}</em><u>{{ theme_text.a ~ theme_text.b ~ "' . str_repeat('x', 130) . '" }}</u></section>';
        $items = ThemeSourceMap::describe($source, ThemeSourceMap::scan($source))['items'];
        $by = [];
        foreach ($items as $item) $by[$item[0]] = [$item[1], $item[6] ?? null];
        self::assertSame(['variable', 'theme_text.hero_title'], $by['h3']);
        self::assertSame(['variable', 'theme_text.control_mouse|upper'], $by['b']);
        self::assertSame('variable', $by['i'][0]);
        self::assertMatchesRegularExpression('/^theme_text\.hero_title ?\| ?lower$/', (string)$by['i'][1]);
        // Texto do tema junto de texto fixo nao e "so um texto do tema": continua misto.
        self::assertSame('mixed', $by['p'][0]);
        self::assertSame(['variable', 'project.title'], $by['span']);
        self::assertSame(['variable', 'theme_text.' . str_repeat('k', 64)], $by['em']);
        // Expressao longa chega cortada em MAX_EXPR: nao casa com a forma que o script aceita.
        self::assertSame(ThemeSourceMap::MAX_EXPR, strlen((string)$by['u'][1]));
    }

    public function testReadingCasesGiveTheTextTheSiteShows(): void {
        $fixture = $this->fixture();
        self::assertGreaterThanOrEqual(11, count($fixture['read']));
        foreach ($fixture['read'] as $case) {
            $resolved = ThemeStrings::resolve($fixture['strings'], $case['language']);
            self::assertSame($case['text'], $resolved[$case['key']] ?? '', $case['name']);
            if ($case['from'] !== '') {
                self::assertSame($case['text'], $fixture['strings'][$case['key']][$case['from']], $case['name']);
            }
        }
    }

    public function testTextCasesAreAcceptedOrRefusedByTheServerTheSameWay(): void {
        $fixture = $this->fixture();
        self::assertGreaterThanOrEqual(14, count($fixture['text']));
        foreach ($fixture['text'] as $case) {
            $text = str_repeat($case['fill'], $case['times']);
            self::assertSame($case['ok'], ThemeStrings::isText($text), json_encode($case['fill']) . ' x ' . $case['times']);
        }
    }

    public function testWrittenJsonIsWhatTheServerShowsAndAccepts(): void {
        $fixture = $this->fixture();
        self::assertGreaterThanOrEqual(18, count($fixture['write']));
        $accepted = 0;
        foreach ($fixture['write'] as $case) {
            if (isset($case['json'])) {
                $accepted++;
                $decoded = json_decode($case['json'], true);
                self::assertIsArray($decoded, $case['name']);
                // Mesmo formato em que o editor mostra o JSON: quem edita na pagina nao reformata o campo.
                self::assertSame($case['json'], json_encode($decoded, self::PRETTY), $case['name']);
                self::assertTrue($this->serverAccepts($case['json']), $case['name']);
                self::assertSame($case['text'], ThemeStrings::resolve($decoded, $case['language'])[$case['key']] ?? null, $case['name']);
                // So aquela chave e aquele idioma mudaram.
                $before = trim($case['raw']) === '' ? [] : json_decode($case['raw'], true);
                self::assertIsArray($before, $case['name']);
                $expected = $before;
                $expected[$case['key']][$case['language']] = $case['text'];
                self::assertSame($expected, $decoded, $case['name']);
            } elseif (isset($case['forced'])) {
                // O script recusou: o servidor tambem recusaria o JSON com aquele texto.
                self::assertFalse($this->serverAccepts($case['forced']), $case['name']);
            } elseif ($case['why'] === 'theme_bad_json') {
                $decoded = json_decode($case['raw'], true);
                self::assertTrue(!is_array($decoded) || ThemeStrings::normalize($decoded) === null, $case['name']);
            }
            if (isset($case['why'])) {
                self::assertContains($case['why'], ['theme_bad_json', 'theme_empty', 'theme_full'], $case['name']);
            }
        }
        self::assertGreaterThanOrEqual(7, $accepted);
    }

    public function testLimitCasesAgreeWithTheServerAtEveryEdge(): void {
        $fixture = $this->fixture();
        self::assertGreaterThanOrEqual(14, count($fixture['limits']));
        $seen = [true => 0, false => 0];
        foreach ($fixture['limits'] as $case) {
            $map = $this->make($case['make']);
            $map[$case['key']][$case['language']] = str_repeat($case['fill'], $case['times']);
            $json = (string)json_encode($map, self::PRETTY);
            // A receita monta o mesmo JSON dos dois lados; o tamanho e o que o formulario envia (CR LF).
            self::assertSame($case['bytes'], strlen(str_replace("\n", "\r\n", $json)), $case['name']);
            self::assertSame($case['ok'], $this->serverAccepts($json), $case['name']);
            $seen[$case['ok']]++;
        }
        self::assertGreaterThanOrEqual(6, $seen[true]);
        self::assertGreaterThanOrEqual(7, $seen[false]);
        // As duas bordas do teto em bytes estao na tabela.
        $sizes = array_column($fixture['limits'], 'bytes');
        self::assertContains(ThemeStrings::MAX_JSON_BYTES, $sizes);
        self::assertContains(ThemeStrings::MAX_JSON_BYTES + 1, $sizes);
    }

    public function testOnlyOnePlaceWritesTheFormFieldAndTheThemeSourceIsNeverTouched(): void {
        $script = $this->repo(self::SCRIPT);
        $block = $this->block();
        self::assertSame(1, substr_count($block, 'field.value = '));
        self::assertStringContainsString("    themeWriting = true;\n    field.value = json;\n    try { field.dispatchEvent(new Event('input', { bubbles: true })); } finally { themeWriting = false; }", $block);
        self::assertSame(3, substr_count($script, 'themeCommit('));
        self::assertStringContainsString('themeCommit(field, res.json);', $block);
        self::assertStringContainsString('themeCommit(field, st.theme.raw);', $block);
        self::assertDoesNotMatchRegularExpression('/writeSource\(|commitSource\(|encodeText\(|sourceField\(|templates\[/', $block);
        self::assertDoesNotMatchRegularExpression("/innerHTML|insertAdjacent|outerHTML|document\\.write|createElement\\('(script|style|iframe)'|contenteditable|eval\\(|new Function/", $block);
        // A previa imediata usa o mesmo caminho do texto fixo: textContent, so em folhas.
        self::assertStringContainsString('if (ref && ref.key === themeKey) setElementText(doc, key, n, themeShown(text, ref.filter));', $block);
        // Quem chama: o campo do painel (fora do modo leitura) e a caixa na pagina.
        self::assertSame(3, substr_count($script, 'onThemeInput('));
        self::assertStringContainsString("if (t.getAttribute('data-kaz-canvas-theme-text') !== null) { if (!t.readOnly) onThemeInput(t); return; }", $script);
        self::assertStringContainsString("var why = !sameElement(picked, st) ? 'el_stale' : (st.theme ? onThemeInput({ value: value }) : onTextInput({ value: value }));", $script);
        // O painel: texto do tema ganha campo; qualquer outra variavel continua so informada.
        self::assertStringContainsString("} else if (kind === 'variable' && themeRef(it)) {", $script);
        self::assertStringContainsString("    } else if (kind === 'variable') {\n      var p = make('p', 'kaz-canvas__sidehelp', label('el_variable', '') + ' ');", $script);
        // Sem elemento escolhido, o painel lista os textos do tema do trecho, no idioma do canvas.
        self::assertStringContainsString('else { paintTextList(key, host); paintThemeList(key, host); paintLinkList(key, host); }', $script);
        self::assertStringContainsString('rows.push([n, it[0], themeRead(dict, ref.key, lang).text || ref.key]);', $block);
        // Na pagina: a decisao e de themeWhy; as outras variaveis continuam recusadas por inlineWhy.
        self::assertStringContainsString('if (ref) return themeWhy(own, mapFresh(sel.key), leaf, themeState(ref.key));', $script);
        self::assertStringContainsString("if (it[1] === 'variable') return 'inline_variable';", $script);
    }

    public function testEscLanguageChangeAndOutsideEditsAreHandled(): void {
        $script = $this->repo(self::SCRIPT);
        $block = $this->block();
        // Esc devolve o JSON guardado ao abrir, byte a byte.
        self::assertStringContainsString('theme: ref ? { key: ref.key, filter: ref.filter, raw: tfield.value } : null };', $script);
        self::assertStringContainsString("if (st.theme) themeInlineStop(st, keep);\n    else if (!keep && st.changed) {", $script);
        self::assertStringContainsString('if (!keep && st.changed && field && field.value !== st.theme.raw) {', $block);
        // O idioma em que se grava e o que a pagina esta mostrando.
        self::assertStringContainsString("if (!fresh || st.lang !== st.shown) return 'el_stale';", $block);
        self::assertStringContainsString("var asked = langSel ? String(langSel.value) : 'en-us';\n    body.set('canvas_language', asked);", $script);
        self::assertStringContainsString("if (liveTheme && mine > liveTheme.after) liveTheme = null;\n      shownLang = json.source === 'saved' ? '' : asked;", $script);
        self::assertStringContainsString("if (inline && inline.theme) inlineStop(true);\n    liveTheme = null;\n    if (!picked || themeRef(elementItem(picked.key, picked.n))) paintElementBlock();\n    refresh();", $script);
        // JSON mudado no bloco proprio: a caixa fecha como esta; o que o canvas escreve nao dispara isso.
        self::assertStringContainsString("if (themeWriting || !ev.target || ev.target.name !== 'strings_json') return;", $script);
        // Fonte com filtro de caixa: caixa propria, com o texto guardado.
        self::assertStringContainsString("st.mode = st.theme && st.theme.filter ? 'box' : inlineMode(m);", $script);
    }

    public function testLabelsExistInTheControllerAndInThreeLanguages(): void {
        $controller = $this->repo(self::CONTROLLER);
        self::assertSame(1, preg_match('/\$canvasLabels=\[\];\s*foreach\(\[(.*?)\] as \$canvasKey\)/s', $controller, $m));
        foreach (array_keys(self::TEXTS['pt-br']) as $key) {
            self::assertSame(1, substr_count($m[1], "'" . $key . "'"), $key);
        }
        // A versao no endereco muda a cada etapa que mexe no canvas.
        self::assertSame(1, preg_match("/'-kaz-canvas-kz(\\d{2})'/", $controller, $v));
        self::assertGreaterThanOrEqual(21, (int)$v[1]);

        $seen = [];
        foreach (self::TEXTS as $code => $texts) {
            $lang = $this->language($code);
            foreach ($texts as $key => $text) {
                self::assertSame($text, $lang['text_canvas_' . $key] ?? null, $code . ' ' . $key);
                $seen[$key][] = $text;
            }
            // Os avisos citam o bloco do JSON pelo nome que ele tem naquele idioma.
            foreach (['theme_bad_json', 'theme_lines'] as $key) {
                self::assertStringContainsString('"' . $lang['text_kaz_strings_summary'] . '"', $lang['text_canvas_' . $key], $code . ' ' . $key);
            }
            // O idioma entra no rotulo do campo e na nota do idioma emprestado.
            self::assertSame(1, substr_count($lang['text_canvas_theme_text'], '%s'), $code);
            self::assertSame(1, substr_count($lang['text_canvas_theme_fallback'], '%s'), $code);
            self::assertSame(1, substr_count($lang['text_canvas_theme_texts'], '%s'), $code);
        }
        foreach ($seen as $key => $three) {
            self::assertCount(3, array_unique($three), $key . ': uma frase por idioma');
        }
    }

    public function testNodeCheckerPassesWhenNodeIsAvailable(): void {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') self::markTestSkipped('node ausente: rode `node ' . self::CHECKER . '` à mão.');
        $output = [];
        $status = 1;
        exec(escapeshellarg($node) . ' ' . escapeshellarg(DIR_REAMUR . self::CHECKER) . ' 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", array_slice($output, 0, 12)));
        self::assertMatchesRegularExpression('/^Kaz theme text checker: \d{3,} PASS$/', (string)end($output));
    }
}
