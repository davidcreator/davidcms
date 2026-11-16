<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

/**
 * KZ-16 (ADR 0109): todo campo da tela do editor de tema tem nome.
 *
 * Ate a KZ-15 havia 98 `<label>` escritos no Twig ao lado do campo, sem `for`
 * (Geral, Tokens, Three.js, Midia, Sites, Componentes, Comparar), 25 campos de
 * texto sem rotulo (Templates), duas setas do painel de camadas so com o
 * simbolo, o campo de numero ao lado de cada barra do painel "Design" e os
 * campos da parte "Quando" de cada regra de condicao.
 *
 * O que e montado por script e conferido rodando o script em node; o Twig e
 * conferido aqui, lendo a fonte.
 */
final class KazFieldNamesTest extends TestCase {
    private const LANGUAGES = ['pt-br', 'en-us', 'es-eu'];
    private const TWIG = 'dev/admin/view/template/design/kaz_theme_editor.twig';
    private const CONTROLLER = 'dev/admin/controller/design/theme.php';
    /** Pedaco de tag que aceita `>` dentro de `{{ ... }}` e de `{% ... %}`. */
    private const ATTRS = '((?:\{\{.*?\}\}|\{%.*?%\}|[^>])*)';
    /** O mesmo campo escrito em mais de um ramo de um `if`: so um deles vai para a pagina. */
    private const BRANCHES = ['token-{{ f.key }}' => 3];
    /** Escritos aqui, e nao so contados: uma traducao trocada ou deixada em portugues aparece. */
    private const BUILDER_TEXTS = [
        'pt-br' => ['cond_group' => 'Tipo de grupo', 'cond_field' => 'Campo', 'cond_op' => 'Operador', 'cond_value' => 'Valor',
            'cond_from' => 'Valor inicial', 'cond_to' => 'Valor final', 'var_name' => 'Nome da variável', 'var_value' => 'Valor da variável'],
        'en-us' => ['cond_group' => 'Group type', 'cond_field' => 'Field', 'cond_op' => 'Operator', 'cond_value' => 'Value',
            'cond_from' => 'Start value', 'cond_to' => 'End value', 'var_name' => 'Variable name', 'var_value' => 'Variable value'],
        'es-eu' => ['cond_group' => 'Tipo de grupo', 'cond_field' => 'Campo', 'cond_op' => 'Operador', 'cond_value' => 'Valor',
            'cond_from' => 'Valor inicial', 'cond_to' => 'Valor final', 'var_name' => 'Nombre de la variable', 'var_value' => 'Valor de la variable'],
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

    private function attr(string $attrs, string $name): ?string {
        return preg_match('/(?:^|\s)' . preg_quote($name, '/') . '="((?:\{\{.*?\}\}|[^"])*)"/s', $attrs, $m) === 1 ? $m[1] : null;
    }

    /** @return list<array{tag:string,attrs:string,at:int}> */
    private function tags(string $twig, string $names): array {
        preg_match_all('/<(' . $names . ')\b' . self::ATTRS . '>/s', $twig, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $out = [];
        foreach ($found as $m) {
            $out[] = ['tag' => $m[1][0], 'attrs' => $m[2][0], 'at' => $m[0][1]];
        }

        return $out;
    }

    /** @return list<array{attrs:string,inner:string,from:int,to:int}> */
    private function labels(string $twig): array {
        preg_match_all('/<label\b' . self::ATTRS . '>(.*?)<\/label>/s', $twig, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $out = [];
        foreach ($found as $m) {
            $out[] = ['attrs' => $m[1][0], 'inner' => $m[2][0], 'from' => $m[0][1], 'to' => $m[0][1] + strlen($m[0][0])];
        }

        return $out;
    }

    /** @return array<string,int> id escrito no Twig => quantas vezes */
    private function ids(string $twig): array {
        $ids = [];
        foreach ($this->tags($twig, '[a-z][a-z0-9]*') as $tag) {
            $id = $this->attr($tag['attrs'], 'id');
            if ($id !== null) $ids[$id] = ($ids[$id] ?? 0) + 1;
        }

        return $ids;
    }

    public function testEveryLabelOfTheEditorPointsToAField(): void {
        $twig = $this->repo(self::TWIG);
        $fieldIds = [];
        foreach ($this->tags($twig, 'input|select|textarea') as $tag) {
            $id = $this->attr($tag['attrs'], 'id');
            if ($id !== null) $fieldIds[$id] = true;
        }
        $labels = $this->labels($twig);
        // Contados na fonte: um rotulo escrito dentro de um laco vale por um.
        self::assertGreaterThan(50, count($labels));
        self::assertSame(substr_count($twig, '<label'), count($labels), 'todo <label> do Twig foi lido');
        $linked = 0;
        $wrapping = 0;
        foreach ($labels as $label) {
            $for = $this->attr($label['attrs'], 'for');
            if ($for !== null) {
                self::assertArrayHasKey($for, $fieldIds, 'o rótulo aponta para um campo que existe: for="' . $for . '"');
                $linked++;
                continue;
            }
            self::assertMatchesRegularExpression('/<(input|select|textarea)\b/', $label['inner'],
                'rótulo sem `for` e sem campo dentro: ' . substr(trim(strip_tags($label['inner'])), 0, 60));
            $wrapping++;
        }
        // Os 23 que estavam soltos na fonte ate a KZ-15 (98 na pagina, por causa dos lacos)
        // somam-se aos que ja estavam ligados.
        self::assertGreaterThanOrEqual(23 + 20, $linked);
        self::assertGreaterThan(0, $wrapping);
    }

    public function testEveryFieldOfTheEditorHasAName(): void {
        $twig = $this->repo(self::TWIG);
        $labels = $this->labels($twig);
        $for = [];
        foreach ($labels as $label) {
            $target = $this->attr($label['attrs'], 'for');
            if ($target !== null) $for[$target] = true;
        }
        $ids = $this->ids($twig);
        $fields = 0;
        $how = ['rotulo' => 0, 'dentro' => 0, 'aria-label' => 0, 'aria-labelledby' => 0, 'title' => 0];
        foreach ($this->tags($twig, 'input|select|textarea') as $tag) {
            if ($this->attr($tag['attrs'], 'type') === 'hidden') continue;
            $fields++;
            $what = $tag['tag'] . ' ' . substr(trim((string)preg_replace('/\s+/', ' ', $tag['attrs'])), 0, 90);
            $id = $this->attr($tag['attrs'], 'id');
            if ($id !== null && isset($for[$id])) { $how['rotulo']++; continue; }
            $inside = false;
            foreach ($labels as $label) {
                if ($tag['at'] > $label['from'] && $tag['at'] < $label['to']) $inside = true;
            }
            if ($inside) { $how['dentro']++; continue; }
            $named = false;
            foreach (['aria-label', 'aria-labelledby', 'title'] as $name) {
                $value = $this->attr($tag['attrs'], $name);
                if ($value === null || trim($value) === '') continue;
                if ($name === 'aria-labelledby') self::assertArrayHasKey($value, $ids, 'aria-labelledby aponta para um id que existe: ' . $what);
                $how[$name]++;
                $named = true;
                break;
            }
            self::assertTrue($named, 'campo sem nome no Twig do editor: ' . $what);
        }
        self::assertGreaterThan(60, $fields);
        self::assertSame($fields, array_sum($how));
        // Templates: o nome do campo e o nome do arquivo que ele edita.
        self::assertStringContainsString('name="templates[{{ key }}]" aria-label="{{ key }}.twig"', $twig);
    }

    public function testNoIdIsWrittenTwiceInTheEditorTemplate(): void {
        $repeated = array_filter($this->ids($this->repo(self::TWIG)), static fn(int $n): bool => $n > 1);
        self::assertSame(self::BRANCHES, $repeated);
    }

    public function testAButtonWithOnlyASymbolHasAName(): void {
        $twig = $this->repo(self::TWIG);
        preg_match_all('/<button\b' . self::ATTRS . '>(.*?)<\/button>/s', $twig, $buttons, PREG_SET_ORDER);
        self::assertGreaterThan(60, count($buttons));
        self::assertSame(substr_count($twig, '<button'), count($buttons), 'todo <button> do Twig foi lido');
        $symbols = 0;
        foreach ($buttons as $button) {
            $text = trim(strip_tags($button[2]));
            // Texto vindo de `{{ ... }}` ou com letra ou numero: o proprio texto e o nome.
            if (str_contains($text, '{{') || preg_match('/[\p{L}\p{N}]/u', $text) === 1) continue;
            $symbols++;
            $name = $this->attr($button[1], 'aria-label');
            self::assertNotNull($name, 'botão só com símbolo e sem aria-label: ' . substr(trim($button[1]), 0, 90));
            self::assertStringContainsString('{{ text_', $name);
        }
        self::assertGreaterThanOrEqual(2, $symbols);
        // As duas setas do painel de camadas fazem o mesmo que os botoes da barra: mesmo nome.
        foreach (['up' => 'text_kaz_tool_front', 'down' => 'text_kaz_tool_back'] as $action => $key) {
            self::assertSame(2, preg_match_all('/data-kaz-action="' . $action . '" title="\{\{ ' . $key . '\|e\(\'html_attr\'\) \}\}" aria-label="\{\{ ' . $key . '\|e\(\'html_attr\'\) \}\}"/', $twig), $action);
        }
    }

    public function testCardsOfMediaSitesAndComponentsAreNamedGroups(): void {
        $twig = $this->repo(self::TWIG);
        $cards = ['tmm-card' => ['data-media', 'm', 'med'], 'tms-card' => ['data-site', 's', 'site'], 'tcl-card' => ['data-component', 'c', 'cmp']];
        foreach ($cards as $class => [$data, $var, $prefix]) {
            $title = $prefix . '-title-{{ ' . $var . '.id }}';
            // Dentro do cartao os nomes se repetem ("Variante", "Tom"): o cartao diz de qual item sao.
            self::assertStringContainsString('<div class="' . $class . '" ' . $data . '="{{ ' . $var . '.id }}" role="group" aria-labelledby="' . $title . '">', $twig);
            self::assertStringContainsString('<label class="form-check-label" id="' . $title . '" for="' . $prefix . '-{{ ' . $var . '.id }}"><strong>', $twig);
        }
    }

    public function testConditionFieldsWithoutAVisibleLabelGetTheirNameFromTheLanguageFile(): void {
        $controller = $this->repo(self::CONTROLLER);
        $script = $this->repo('dev/admin/view/js/theme-condition-builder.js');
        $accents = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ã' => 'a', 'õ' => 'o', 'ç' => 'c', 'ê' => 'e', 'â' => 'a'];
        self::assertSame(self::LANGUAGES, array_keys(self::BUILDER_TEXTS));
        foreach (self::BUILDER_TEXTS as $code => $expected) {
            $texts = $this->language($code);
            foreach ($expected as $key => $text) {
                self::assertSame($text, $texts['text_builder_' . $key] ?? null, $code . ' ' . $key);
            }
        }
        foreach (self::BUILDER_TEXTS['pt-br'] as $key => $portuguese) {
            self::assertNotSame($portuguese, self::BUILDER_TEXTS['en-us'][$key], 'en-us ' . $key);
            self::assertSame(1, substr_count($controller, "'" . $key . "'=>'text_builder_" . $key . "'"), $key);
            // A reserva escrita no script e o texto em portugues, sem acento (como as outras do arquivo).
            self::assertStringContainsString("L('" . $key . "', '" . strtr($portuguese, $accents) . "')", $script);
        }
        self::assertMatchesRegularExpression('/function named\(node, text\) \{ node\.setAttribute\(\'aria-label\', text\); return node; \}/', $script);
    }

    public function testFieldNameCheckersPass(): void {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') self::markTestSkipped('node ausente: rode os conferidores `tests/check_*_names.js` e `tests/check_condition_builder_labels.js` à mão.');
        $checkers = [
            'tests/check_kaz_canvas_field_names.js' => '/^Kaz canvas field names checker: \d{2,} PASS$/',
            'tests/check_condition_builder_labels.js' => '/^Condition builder labels checker: \d{4,} PASS$/',
        ];
        foreach ($checkers as $checker => $last) {
            $output = [];
            $status = 1;
            exec(escapeshellarg($node) . ' ' . escapeshellarg(DIR_REAMUR . $checker) . ' 2>&1', $output, $status);
            self::assertSame(0, $status, $checker . "\n" . implode("\n", array_slice($output, 0, 12)));
            self::assertMatchesRegularExpression($last, (string)end($output));
        }
    }

    public function testTheScreenCallsTheEditorByOneName(): void {
        // O nome do produto e "KAZEditor-Theme" (admin/model/tool/internal-tools.json); "KAZTheme-Editor" e o nome antigo.
        $tools = json_decode($this->repo('dev/admin/model/tool/internal-tools.json'), true);
        self::assertIsArray($tools);
        self::assertStringContainsString('"name": "KAZEditor-Theme"', $this->repo('dev/admin/model/tool/internal-tools.json'));
        $seen = 0;
        foreach (self::LANGUAGES as $code) {
            foreach ($this->language($code) as $key => $text) {
                self::assertStringNotContainsString('KAZTheme-Editor', $text, $code . ' ' . $key);
                if (str_contains($text, 'KAZEditor-Theme')) $seen++;
            }
        }
        self::assertGreaterThanOrEqual(3 * 6, $seen);
        self::assertStringNotContainsString('KAZTheme-Editor', $this->repo(self::TWIG));
        // No script, o nome antigo so pode aparecer em comentario e no texto da camada de exemplo da cena inicial.
        $script = $this->repo('dev/admin/view/js/kaz-theme-editor.js');
        self::assertSame(0, preg_match_all("/TXT\('[a-z0-9_]+', '[^']*KAZTheme-Editor/", $script));
        self::assertSame(2, preg_match_all("/TXT\('(msg_ready|boot_warning)', 'KAZEditor-Theme /", $script));
    }

    public function testChangedFilesGotANewAddress(): void {
        $controller = $this->repo(self::CONTROLLER);
        self::assertMatchesRegularExpression("/'-theme-builder-[d-z]'/", $controller);
        self::assertMatchesRegularExpression("/'-kaz-theme-editor-kz(1[6-9]|[2-9]\d)'/", $controller);
        self::assertMatchesRegularExpression("/'-kaz-canvas-kz(1[6-9]|[2-9]\d)'/", $controller);
    }
}
