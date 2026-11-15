<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

/**
 * KZ-15 (ADR 0108): pendencias anotadas nas etapas KZ-10 a KZ-14, fechadas aqui.
 *  - a contagem de camadas com uma camada so ("1 camadas");
 *  - o aviso de quando o editor nao inicia, que mandava rodar um arquivo de
 *    `tools/` que nao existe;
 *  - os rotulos do construtor de condicoes, que nao estavam ligados aos campos;
 *  - regras da folha do editor que nenhuma tela e nenhum script usam.
 * A marca de versao de `ThemeReleaseProbe` esta em `ThemeReleaseProbeTest`.
 */
final class KazSmallFixesTest extends TestCase {
    private const LANGUAGES = ['pt-br', 'en-us', 'es-eu'];
    private const CHECKER = 'tests/check_condition_builder_labels.js';
    private const SHEETS = ['kaz-theme-editor.css', 'kaz-canvas.css', 'theme-condition-builder.css'];
    /** Classes que um script monta juntando um prefixo a um valor: o nome inteiro nao aparece escrito. */
    private const COMPOSED = [
        'kaz-canvas__note--' => ["'kaz-canvas__note kaz-canvas__note--' + line.kind", 'kaz-canvas.js'],
        'tcb-group--' => ["'tcb-group tcb-group--' + kind", 'theme-condition-builder.js'],
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

    public function testOneLayerIsWrittenInTheSingular(): void {
        $expected = [
            'pt-br' => ['1 camada', '%s camadas'],
            'en-us' => ['1 layer', '%s layers'],
            'es-eu' => ['1 capa', '%s capas'],
        ];
        foreach ($expected as $code => [$one, $many]) {
            $texts = $this->language($code);
            self::assertSame($one, $texts['text_kaz_one_layer'] ?? null, $code);
            self::assertSame($many, $texts['text_kaz_n_layers'] ?? null, $code);
            // O singular nao leva `%s`: o numero ja esta escrito.
            self::assertStringNotContainsString('%', $texts['text_kaz_one_layer']);
        }
        self::assertStringContainsString("'no_layer', 'n_layers', 'one_layer', 'flag_hidden',", $this->repo('dev/admin/controller/design/theme.php'));
    }

    public function testNoEditorTextSendsTheReaderToAToolThatDoesNotExist(): void {
        $seen = 0;
        foreach (self::LANGUAGES as $code) {
            foreach ($this->language($code) as $key => $text) {
                if (preg_match_all('~\btools/[A-Za-z0-9_./-]+~', $text, $m) === 0) continue;
                foreach ($m[0] as $path) {
                    $seen++;
                    self::assertFileExists(DIR_REAMUR . rtrim($path, '.,;:'), $code . ' ' . $key);
                }
            }
            $warning = $this->language($code)['text_kaz_boot_warning'] ?? '';
            self::assertStringNotContainsString('kaz-theme-editor-health', $warning, $code);
            self::assertStringContainsString('kaz-theme-editor.js', $warning, $code);
            self::assertStringContainsString('scripts_deferred', $warning, $code);
            self::assertStringEndsWith('.', $warning, $code);
        }
        self::assertStringNotContainsString('kaz-theme-editor-health', $this->repo('dev/admin/view/js/kaz-theme-editor.js'));
        // Hoje nenhuma frase cita `tools/`; se uma voltar a citar, o arquivo precisa existir.
        self::assertSame(0, $seen);
    }

    public function testConditionBuilderLabelsAreTiedToTheirFields(): void {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') self::markTestSkipped('node ausente: rode `node ' . self::CHECKER . '` à mão.');
        $output = [];
        $status = 1;
        exec(escapeshellarg($node) . ' ' . escapeshellarg(DIR_REAMUR . self::CHECKER) . ' 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", array_slice($output, 0, 12)));
        self::assertMatchesRegularExpression('/^Condition builder labels checker: \d{3,} PASS$/', (string)end($output));
    }

    public function testGroupTitleOfTheConditionBuilderLooksLikeALabel(): void {
        // O titulo de grupo e um <span>; para a tela nao mudar ele repete o que admin.css da a todo <label>.
        self::assertSame(1, preg_match('/\.admin-content \.field label,\s*\.admin-content label \{([^}]*)\}/', $this->repo('dev/admin/view/css/admin.css'), $label));
        self::assertSame(1, preg_match('/^\.admin-content \.tcb-field__label--group \{([^}]*)\}/m', $this->repo('dev/admin/view/css/theme-condition-builder.css'), $group));
        $declarations = static function (string $block): array {
            $out = [];
            foreach (explode(';', $block) as $part) {
                if (trim($part) === '') continue;
                [$name, $value] = array_map('trim', explode(':', $part, 2));
                $out[$name] = $value;
            }
            ksort($out);

            return $out;
        };
        $wanted = $declarations($label[1]);
        self::assertSame(['display' => 'block', 'font-size' => '.85rem', 'font-weight' => '600', 'margin-bottom' => '5px'], $wanted);
        // `cursor: default` e o que o navegador da a um <label> e nao da a um <span>.
        $expected = $wanted + ['cursor' => 'default'];
        ksort($expected);
        self::assertSame($expected, $declarations($group[1]));
    }

    public function testChangedFilesGotANewAddress(): void {
        $controller = $this->repo('dev/admin/controller/design/theme.php');
        // Folha e script do construtor de condicoes mudaram: o endereco muda junto, para o navegador nao usar a copia antiga.
        self::assertSame(1, preg_match("/Header::ASSET_V \\. '-theme-builder-([c-z])';/", $controller));
        self::assertStringNotContainsString('-theme-builder-b', $controller);
        // O mesmo para a folha e o script do editor.
        self::assertSame(1, preg_match("/Header::ASSET_V \\. '-kaz-theme-editor-kz(1[5-9]|[2-9]\\d)';/", $controller)); // KZ-15 em diante
    }

    public function testEveryClassOfTheEditorSheetsIsUsedSomewhere(): void {
        $used = '';
        foreach ((array)glob(DIR_REAMUR . 'dev/admin/view/template/design/*.twig') as $file) {
            $used .= (string)file_get_contents((string)$file) . "\n";
        }
        $scripts = [];
        foreach (array_merge((array)glob(DIR_REAMUR . 'dev/admin/view/js/kaz-*.js'), (array)glob(DIR_REAMUR . 'dev/admin/view/js/theme-*.js')) as $file) {
            $scripts[basename((string)$file)] = (string)file_get_contents((string)$file);
        }
        $used .= implode("\n", $scripts) . "\n" . $this->repo('dev/admin/controller/design/theme.php');
        foreach (self::COMPOSED as $prefix => [$code, $script]) {
            self::assertStringContainsString($code, $scripts[$script] ?? '', $prefix);
        }

        $unused = [];
        $total = 0;
        foreach (self::SHEETS as $sheet) {
            $css = (string)preg_replace('~/\*.*?\*/~s', '', $this->repo('dev/admin/view/css/' . $sheet));
            self::assertGreaterThan(0, preg_match_all('/\.((?:kaz|tcb)-[a-zA-Z0-9_-]+)/', $css, $m), $sheet);
            foreach (array_unique($m[1]) as $class) {
                $total++;
                if (preg_match('/(?<![a-zA-Z0-9_-])' . preg_quote($class, '/') . '(?![a-zA-Z0-9_-])/', $used) === 1) continue;
                foreach (array_keys(self::COMPOSED) as $prefix) {
                    if (str_starts_with($class, $prefix)) continue 2;
                }
                $unused[] = $sheet . ': .' . $class;
            }
        }
        self::assertGreaterThan(270, $total);
        // Regra que nenhuma tela e nenhum script usam nao faz nada: ou ganha uso, ou sai.
        self::assertSame([], $unused);

        // As seis que sairam nesta etapa.
        $sheet = $this->repo('dev/admin/view/css/kaz-theme-editor.css');
        foreach (['.kaz-btn--danger', '.kaz-tools', '.kaz-blocks', '.kaz-blocks__row', '.kaz-block ', '.kaz-block:', '.kaz-nojs'] as $gone) {
            self::assertStringNotContainsString($gone, $sheet);
        }
        // A cor `--kaz-danger` fica na paleta do editor, sem regra que a leia por enquanto.
        self::assertSame(1, substr_count($sheet, '--kaz-danger: #ff6b6b;'));
        self::assertSame(0, substr_count($sheet, 'var(--kaz-danger)'));
    }
}
