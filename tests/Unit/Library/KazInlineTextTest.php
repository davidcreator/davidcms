<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

/**
 * KZ-18 (ADR 0111): texto editado direto na pagina, no canvas do editor de tema.
 *
 * Dois cliques em um texto fixo abrem uma caixa de edicao sobre o proprio
 * texto. A caixa fica no documento do admin (o quadro continua sem script e
 * sem eventos) e escreve pelo mesmo caminho do campo "Texto" do painel.
 *
 * As funcoes que decidem sao rodadas em node por
 * `tests/check_kaz_inline_text.js`; aqui a fonte e lida, para que as regras
 * continuem conferidas mesmo onde nao ha node.
 */
final class KazInlineTextTest extends TestCase {
    private const CHECKER = 'tests/check_kaz_inline_text.js';
    private const SCRIPT = 'dev/admin/view/js/kaz-canvas.js';
    private const CSS = 'dev/admin/view/css/kaz-canvas.css';
    private const CONTROLLER = 'dev/admin/controller/design/theme.php';
    private const START = '  /* ---------------- KZ-18 (ADR 0111): texto editado direto na pagina ---------------- */';
    private const END = '  /* ---------------- KZ-05: atributos do elemento ---------------- */';
    /** Escritas aqui, e nao so contadas: uma traducao trocada ou deixada em outro idioma aparece. */
    private const TEXTS = [
        'pt-br' => [
            'inline_help' => 'Dois cliques no texto, na própria página, também abrem a edição. Enter conclui; Esc desfaz.',
            'inline_label' => 'Texto do elemento, editado na página. Enter conclui; Esc desfaz.',
            'inline_variable' => 'Este texto vem dos dados do site (conteúdo ou idioma) e não é editado aqui.',
            'inline_need_customize' => 'Este trecho ainda usa o padrão do sistema. Use "Personalizar este trecho", no painel, para editar o texto.',
            'frame_hint' => 'Duplo clique para focar o quadro; em um texto, para editar',
        ],
        'en-us' => [
            'inline_help' => 'Double-clicking the text on the page itself also opens it for editing. Enter finishes; Esc undoes.',
            'inline_label' => 'Text of the element, edited on the page. Enter finishes; Esc undoes.',
            'inline_variable' => 'This text comes from the site data (content or language) and is not edited here.',
            'inline_need_customize' => 'This piece still uses the system default. Use "Customize this piece", in the panel, to edit the text.',
            'frame_hint' => 'Double-click to focus the frame; on a text, to edit it',
        ],
        'es-eu' => [
            'inline_help' => 'Hacer doble clic en el texto, en la propia página, también abre la edición. Intro termina; Esc deshace.',
            'inline_label' => 'Texto del elemento, editado en la página. Intro termina; Esc deshace.',
            'inline_variable' => 'Este texto viene de los datos del sitio (contenido o idioma) y no se edita aquí.',
            'inline_need_customize' => 'Este fragmento todavía usa el estándar del sistema. Usa "Personalizar este fragmento", en el panel, para editar el texto.',
            'frame_hint' => 'Doble clic para enfocar el marco; en un texto, para editarlo',
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

    /** O bloco da KZ-18 em kaz-canvas.js, sem os comentarios. */
    private function block(): string {
        $script = $this->repo(self::SCRIPT);
        $from = strpos($script, self::START);
        $to = strpos($script, self::END);
        self::assertIsInt($from);
        self::assertIsInt($to);
        self::assertGreaterThan($from, $to);

        return (string)preg_replace('~/\*.*?\*/~s', '', substr($script, $from, $to - $from));
    }

    public function testTheBoxIsATextareaInTheAdminDocumentAndTheFrameStaysScriptless(): void {
        $script = $this->repo(self::SCRIPT);
        $block = $this->block();
        // O quadro continua como era: sem script, fora da ordem de tabulacao, escondido do leitor de tela.
        self::assertSame(1, substr_count($script, "iframe.setAttribute('sandbox', 'allow-same-origin');"));
        self::assertDoesNotMatchRegularExpression("/setAttribute\\('sandbox', '[^']*allow-scripts/", $script);
        self::assertSame(1, substr_count($script, "setAttribute('sandbox'"));
        self::assertSame(1, substr_count($script, "shield.className = 'kaz-canvas__shield';"));
        // A caixa e um campo de verdade (o teclado do editor de cena ignora INPUT e TEXTAREA), nunca contenteditable.
        self::assertStringContainsString("var box = document.createElement('textarea'), text = decodeText(field.value.slice(it[4], it[5]));", $block);
        self::assertDoesNotMatchRegularExpression('/contenteditable|contentEditable|designMode/', $script);
        // Ela entra no corpo do quadro, neste documento; a regua, no fim da pagina. Nada entra no documento do quadro.
        self::assertSame(2, substr_count($block, 'appendChild('));
        self::assertStringContainsString('f.body.appendChild(box);', $block);
        self::assertStringContainsString('document.body.appendChild(probe);', $block);
        self::assertStringContainsString('document.body.removeChild(probe);', $block);
        self::assertDoesNotMatchRegularExpression("/innerHTML|insertAdjacent|createElement\\('(script|style|iframe)'|\\.cssText|setAttribute\\('style'|removeAttribute\\('style'/", $block);
    }

    public function testOnlyTwoPaintPropertiesAreSetOnThePageElementAndBothAreGivenBack(): void {
        $script = $this->repo(self::SCRIPT);
        $block = $this->block();
        self::assertStringContainsString("var INLINE_HIDE = [['-webkit-text-fill-color', 'transparent'], ['text-shadow', 'none']];", $script);
        self::assertSame(2, substr_count($block, '.style.setProperty('));
        self::assertSame(1, substr_count($block, '.style.removeProperty('));
        // Antes de esconder, o valor e a prioridade que o elemento tinha sao guardados...
        self::assertStringContainsString('st.paint = INLINE_HIDE.map(function (pair) { return [pair[0], node.style.getPropertyValue(pair[0]), node.style.getPropertyPriority(pair[0])]; });', $block);
        self::assertStringContainsString("INLINE_HIDE.forEach(function (pair) { node.style.setProperty(pair[0], pair[1], 'important'); });", $block);
        // ... e ao fechar cada um volta.
        self::assertStringContainsString("if (was[1] === '') st.node.style.removeProperty(was[0]); else st.node.style.setProperty(was[0], was[1], was[2]);", $block);
        // So quando a caixa cobre o texto; na caixa propria a pagina continua mostrando o texto.
        self::assertStringContainsString("if (st.node && st.mode === 'place') st.paint.forEach(function (was) {", $block);
    }

    public function testTypingGoesThroughThePanelTextRulesAndNowhereElse(): void {
        $script = $this->repo(self::SCRIPT);
        $block = $this->block();
        self::assertSame(1, substr_count($block, 'onTextInput('));
        self::assertStringContainsString("var why = sameElement(picked, st) ? onTextInput({ value: value }) : 'el_stale';", $block);
        self::assertDoesNotMatchRegularExpression('/writeSource\(|encodeText\(|trimWs\(|overLimit\(/', $block);
        // A unica escrita direta e a devolucao da fonte guardada ao abrir (Esc), com o mapa daquela fonte.
        self::assertSame(1, substr_count($block, 'commitSource('));
        self::assertStringContainsString('elements[st.key] = st.map;', $block);
        self::assertStringContainsString('commitSource(st.key, st.map, st.src);', $block);
        self::assertStringContainsString('src: field.value, map: JSON.parse(JSON.stringify(elements[sel.key]))', $block);
        self::assertStringContainsString('if (!keep && st.changed) {', $block);

        // O campo do painel continua com as mesmas regras; so passou a dizer por que recusou.
        self::assertSame(1, preg_match('/  function onTextInput\(t\) \{.*?\n  \}\n/s', $script, $m));
        $fn = $m[0];
        self::assertStringContainsString("if (!picked) return 'el_stale';", $fn);
        self::assertStringContainsString("|| (it[1] !== 'fixed' && it[1] !== 'empty') || it[4] < 0) return 'el_stale';", $fn);
        self::assertStringContainsString("if (map.sig !== sigOf(src)) { if (warn) { warn.hidden = false; warn.textContent = label('el_stale', ''); } return 'el_stale'; }", $fn);
        self::assertStringContainsString("if (overLimit(next)) { if (warn) { warn.hidden = false; warn.textContent = label('el_full', ''); } return 'el_full'; }", $fn);
        self::assertStringContainsString('var parts = trimWs(src.slice(it[4], it[5])), enc = trimWs(encodeText(t.value)).body;', $fn);
        self::assertSame(2, substr_count($fn, "return 'el_stale'; }") + substr_count($fn, "return 'el_full'; }"));
        self::assertStringNotContainsString("return '';", $fn);
        // O campo do painel continua chamando a mesma funcao.
        self::assertStringContainsString("if (t.getAttribute('data-kaz-canvas-text') !== null) { if (!t.readOnly) onTextInput(t); return; }", $script);
    }

    public function testDoubleClickOpensATextAndKeepsTheOldGestureEverywhereElse(): void {
        $script = $this->repo(self::SCRIPT);
        self::assertSame(1, preg_match("/viewport\\.addEventListener\\('dblclick', function \\(ev\\) \\{.*?\\n  \\}\\);/s", $script, $m));
        $order = [];
        foreach ([
            'var onEl = hit && wy >= 0 ? elementAt(wx, wy) : null, why = onEl ? inlineReason(onEl, hit) : null;',
            "if (why === '' && inlineStart(onEl, hit, false)) return;",
            'if (why) { hint(why); return; }',
            'if (hit) focusFrame(hit); else fitAll();',
        ] as $step) {
            $at = strpos($m[0], $step);
            self::assertIsInt($at, $step);
            $order[] = $at;
        }
        $sorted = $order;
        sort($sorted);
        self::assertSame($sorted, $order, 'texto que abre, texto que avisa, e so entao o gesto antigo');

        // Quais textos abrem: os mesmos que o painel deixa editar.
        self::assertSame(1, preg_match('/  function inlineWhy\(it, own, fresh, leaf\) \{.*?\n  \}\n/s', $script, $why));
        self::assertStringContainsString("if (it[1] === 'variable') return 'inline_variable';", $why[0]);
        self::assertStringContainsString("if (it[1] === 'mixed') return 'el_mixed';", $why[0]);
        self::assertStringContainsString("if ((it[1] !== 'fixed' && it[1] !== 'empty') || it[4] < 0 || !leaf) return null;", $why[0]);
        self::assertStringContainsString("if (!own) return 'inline_need_customize';", $why[0]);
        self::assertStringContainsString("return fresh ? '' : 'el_stale';", $why[0]);

        // Teclado: Enter ou F2 com o elemento escolhido; dentro da caixa, nenhuma tecla chega aos atalhos.
        self::assertStringContainsString("else if ((ev.key === 'Enter' || ev.key === 'F2') && !ctrl && picked) {", $script);
        self::assertSame(1, preg_match('/  function inlineKey\(ev\) \{\n    ev\.stopPropagation\(\);\n.*?\n  \}\n/s', $script, $key));
        self::assertStringContainsString('if (ev.isComposing || ev.keyCode === 229) return;', $key[0]);
        self::assertStringContainsString("if (ev.key === 'Enter') { ev.preventDefault(); inlineStop(true, true); }", $key[0]);
        self::assertStringContainsString("else if (ev.key === 'Escape') { ev.preventDefault(); inlineStop(false, true); }", $key[0]);
        self::assertStringContainsString("['pointerdown', 'pointerup', 'mousedown', 'click', 'dblclick'].forEach(function (name) {", $script);
        self::assertStringContainsString("box.addEventListener('blur', function () { if (inline && inline.box === box) inlineStop(true); });", $script);
        // A pagina redesenhada com a caixa aberta: a caixa espera e depois acompanha.
        self::assertStringContainsString('frames[id].busy = true; frames[id].iframe.srcdoc = doc;', $script);
        self::assertStringContainsString("f.busy = false;\n    if (inline && inline.frame === f) inlinePlace();", $script);
        self::assertStringContainsString('if (f.busy) return;', $script);
    }

    public function testLabelsExistInTheControllerAndInThreeLanguages(): void {
        $controller = $this->repo(self::CONTROLLER);
        self::assertSame(1, preg_match('/\$canvasLabels=\[\];\s*foreach\(\[(.*?)\] as \$canvasKey\)/s', $controller, $m));
        foreach (['inline_help', 'inline_label', 'inline_variable', 'inline_need_customize', 'el_mixed', 'el_stale', 'el_full', 'frame_hint'] as $key) {
            self::assertSame(1, substr_count($m[1], "'" . $key . "'"), $key);
        }
        // A versao no endereco muda a cada etapa que mexe no canvas.
        self::assertSame(1, preg_match("/'-kaz-canvas-kz(\\d{2})'/", $controller, $v));
        self::assertGreaterThanOrEqual(18, (int)$v[1]);

        $seen = [];
        foreach (self::TEXTS as $code => $texts) {
            $lang = $this->language($code);
            foreach ($texts as $key => $text) {
                self::assertSame($text, $lang['text_canvas_' . $key] ?? null, $code . ' ' . $key);
                $seen[$key][] = $text;
            }
            // O aviso cita o botao do painel pelo nome que ele tem naquele idioma.
            self::assertStringContainsString('"' . $lang['text_canvas_customize'] . '"', $lang['text_canvas_inline_need_customize'], $code);
            // A linha de ajuda do canvas deixou de dizer so "foca um quadro".
            self::assertMatchesRegularExpression('/text/i', $lang['text_canvas_hint'], $code);
            self::assertDoesNotMatchRegularExpression('/Duplo clique foca um quadro|Double-click focuses a frame|Doble clic enfoca un marco/', $lang['text_canvas_hint'], $code);
            // A linha de ajuda fica sob o canvas em uma linha so: o texto novo nao pode ser mais longo que o anterior (210, 202 e 226 caracteres).
            self::assertLessThanOrEqual(['pt-br' => 210, 'en-us' => 202, 'es-eu' => 226][$code], mb_strlen($lang['text_canvas_hint']), $code);
        }
        foreach ($seen as $key => $three) {
            self::assertCount(3, array_unique($three), $key . ': uma frase por idioma');
        }
        // O script pede os rotulos pelo nome.
        $script = $this->repo(self::SCRIPT);
        self::assertStringContainsString("box.setAttribute('aria-label', label('inline_label', ''));", $script);
        self::assertStringContainsString("if (own && fresh) host.appendChild(make('p', 'kaz-canvas__sidehelp', label('inline_help', '')));", $script);
    }

    public function testStylesOfTheBoxAndOfTheNotice(): void {
        $css = $this->repo(self::CSS);
        self::assertSame(1, preg_match('/\n\.kaz-canvas \.kaz-canvas__inline \{([^}]*)\}/', $css, $m));
        foreach (['position: absolute', 'box-sizing: content-box', 'padding: 0', 'border: 0', 'background: transparent', 'resize: none', 'overflow: hidden', 'user-select: text'] as $rule) {
            self::assertStringContainsString($rule, $m[1], $rule);
        }
        // O contorno de foco geral do admin (.admin-shell :focus-visible) nao troca o da caixa.
        self::assertStringContainsString('.kaz-canvas .kaz-canvas__inline:focus, .kaz-canvas .kaz-canvas__inline:focus-visible { outline: calc(2px / var(--kaz-canvas-k, 1)) solid #a78bfa;', $css);
        self::assertStringContainsString('[data-kaz-canvas-inline="1"] .kaz-canvas__mark--el, [data-kaz-canvas-inline="1"] .kaz-canvas__mark--elhover { display: none; }', $css);
        // O aviso tem classe propria: `.kaz-canvas__hint` ja era a linha de ajuda no pe do canvas.
        self::assertSame(1, preg_match('/\n\.kaz-canvas__toast \{([^}]*)\}/', $css, $t));
        self::assertStringContainsString('pointer-events: none', $t[1]);
        self::assertStringContainsString('.kaz-canvas__toast[hidden] { display: none !important; }', $css);
        self::assertSame(2, substr_count($css, '.kaz-canvas__hint {'));
        $script = $this->repo(self::SCRIPT);
        self::assertStringContainsString("hintEl = make('div', 'kaz-canvas__toast', '');\n  hintEl.setAttribute('role', 'status');", $script);
        self::assertStringNotContainsString("make('div', 'kaz-canvas__hint'", $script);
        self::assertSame(1, preg_match('/\n\.kaz-canvas__inlineprobe \{([^}]*)\}/', $css, $p));
        self::assertStringContainsString('visibility: hidden', $p[1]);
    }

    public function testInlineTextCheckerPasses(): void {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') self::markTestSkipped('node ausente: rode `node ' . self::CHECKER . '` à mão.');
        $output = [];
        $status = 1;
        exec(escapeshellarg($node) . ' ' . escapeshellarg(DIR_REAMUR . self::CHECKER) . ' 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", array_slice($output, 0, 12)));
        self::assertMatchesRegularExpression('/^Kaz inline text checker: \d{3,} PASS$/', (string)end($output));
    }
}
