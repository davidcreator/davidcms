<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Theme\ConditionEngine;
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\ThemeSourceMap;
use Reamur\System\Library\Theme\TokenRegistry;

/**
 * KZ-10 (ADR 0103): pendencias anotadas nas etapas anteriores, fechadas aqui.
 *  - o desenho quebrado de quatro selos de `David-Universe-3D` (uma cor no
 *    lugar de um comando do `<path>`);
 *  - os 30 selos do tema "Cena Real" (`tema/`), que passam a ser so enfeite,
 *    como os de `docs/temas` desde a KZ-09;
 *  - o `pattern` do ID de regra de condicao, que o navegador descartava;
 *  - classes escritas na tela do editor que nenhuma folha de estilo conhece.
 * Cada teste tambem serve de guarda: o mesmo defeito, em outro arquivo ou em
 * outro campo, faz o teste falhar.
 */
final class KazLooseEndsTest extends TestCase {
    private const UNIVERSE = 'docs/temas/David-Universe-3D.reamurtheme.json';
    private const BO_PACKAGE = 'tema/david_universe_3d_bo.reamurtheme.json';
    private const BO_CONFIG = 'tema/bo_config.json';
    private const ENVELOPE = 'M40 24h80v84H40z';
    private const BADGE = '<svg viewBox="0 0 160 120" fill="none" aria-hidden="true">';
    private const CHECKER = 'tests/check_form_patterns.js';
    private const SCREEN = 'dev/admin/view/template/design/kaz_theme_editor.twig';

    private function repo(string $path): string {
        $source = file_get_contents(DIR_REAMUR . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    /**
     * Fontes dos modelos de um arquivo de tema (pacote ou so o `config`).
     *
     * @return array<string,string>
     */
    private function templates(string $path): array {
        $data = json_decode($this->repo($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data['config']['templates'] ?? null, $path);
        $out = [];
        foreach ($data['config']['templates'] as $key => $source) {
            self::assertIsString($source, $path . ' ' . $key);
            $out[(string)$key] = $source;
        }

        return $out;
    }

    /**
     * Todo arquivo de tema entregue no repositorio.
     *
     * @return list<string>
     */
    private function themeFiles(): array {
        $out = [];
        foreach ((array)glob(DIR_REAMUR . 'docs/temas/*.reamurtheme.json') as $file) $out[] = 'docs/temas/' . basename((string)$file);
        foreach ((array)glob(DIR_REAMUR . 'tema/*.json') as $file) $out[] = 'tema/' . basename((string)$file);
        sort($out);

        return $out;
    }

    /**
     * Os selos (desenhos 160x120) de um arquivo, na ordem: "modelo#n" => tag inteira.
     *
     * @return array<string,string>
     */
    private function badges(string $path): array {
        $out = [];
        foreach ($this->templates($path) as $key => $source) {
            $n = 0;
            foreach (ThemeSourceMap::scan($source) as $el) {
                if ($el['tag'] !== 'svg' || !str_contains(substr($source, $el['open'][0], $el['open'][1] - $el['open'][0]), 'viewBox="0 0 160 120"')) continue;
                // Um <svg> nao tem outro dentro: o desenho vai ate o primeiro fechamento.
                $end = strpos($source, '</svg>', $el['open'][1]);
                self::assertIsInt($end, $path . ' ' . $key);
                $out[$key . '#' . $n++] = substr($source, $el['open'][0], $end + 6 - $el['open'][0]);
            }
        }

        return $out;
    }

    public function testEveryPathOfTheDeliveredThemesIsOneTheBrowserCanDraw(): void {
        $files = $this->themeFiles();
        self::assertCount(8, $files, implode(', ', $files));
        $paths = 0;
        foreach ($files as $file) {
            foreach ($this->templates($file) as $key => $source) {
                $found = preg_match_all('/<path\b[^>]*?\sd="([^"]*)"/', $source, $m);
                self::assertIsInt($found);
                foreach ($m[1] as $d) {
                    $paths++;
                    // Um `d` e feito de comandos (letras da lista) e numeros. Qualquer outra coisa — a cor
                    // `#eb767e` que estava em quatro selos — faz o navegador parar de desenhar naquele ponto.
                    self::assertMatchesRegularExpression('/^\s*[Mm][MmZzLlHhVvCcSsQqTtAa0-9eE\s,.+\-]*$/', $d, $file . ' ' . $key);
                    self::assertDoesNotMatchRegularExpression('/[eE](?![+\-]?\d)/', $d, $file . ' ' . $key . ': "e" so vale como expoente de numero');
                }
                // Nenhum `d` deixado de fora por estar escrito de outro jeito (aspas simples, sem aspas).
                self::assertSame($found, preg_match_all('/<path\b[^>]*?\sd\s*=/', $source), $file . ' ' . $key);
            }
        }
        // Contagem fixa: se o leitor acima deixar de enxergar os desenhos, o teste nao passa calado.
        self::assertSame(133, $paths);
    }

    public function testEnvelopeBadgeIsAClosedRectangleAgain(): void {
        foreach ([self::UNIVERSE, self::BO_PACKAGE, self::BO_CONFIG] as $file) {
            $seen = [];
            foreach ($this->templates($file) as $key => $source) {
                self::assertStringNotContainsString('M40 24h80v84#', $source, $file . ' ' . $key);
                $n = substr_count($source, '<path d="' . self::ENVELOPE . '" fill="none"');
                if ($n > 0) $seen[$key] = $n;
                // Nao ha outra versao do mesmo desenho por perto.
                self::assertSame($n, substr_count($source, 'M40 24h80v84'), $file . ' ' . $key);
            }
            ksort($seen);
            self::assertSame(['common/home' => 2, 'partial/news' => 1, 'partial/portfolio' => 1], $seen, $file);
        }
    }

    public function testBadgesOfCenaRealAreOnlyDecoration(): void {
        foreach ([self::BO_PACKAGE, self::BO_CONFIG] as $file) {
            $seen = [];
            foreach ($this->templates($file) as $key => $source) {
                // O rotulo nunca era lido: a mesma tag tinha aria-hidden="true".
                self::assertStringNotContainsString('Selo do projeto', $source, $file . ' ' . $key);
                foreach (ThemeSourceMap::scan($source) as $el) {
                    if ($el['tag'] !== 'svg') continue;
                    self::assertIsArray($el['attrs'], $file . ' ' . $key);
                    $marks = [];
                    foreach ($el['attrs'] as $attr) $marks[$attr['name']] = substr($source, $attr['value'][0], $attr['value'][1] - $attr['value'][0]);
                    self::assertSame(['aria-hidden' => 'true'], $marks, $file . ' ' . $key . ' #' . $el['n']);
                    self::assertSame(self::BADGE, substr($source, $el['open'][0], $el['open'][1] - $el['open'][0]), $file . ' ' . $key . ' #' . $el['n']);
                    $seen[$key] = ($seen[$key] ?? 0) + 1;
                }
            }
            ksort($seen);
            self::assertSame(['common/home' => 15, 'partial/news' => 6, 'partial/portfolio' => 9], $seen, $file);
        }
    }

    public function testTheTwoThemesCarryTheSameThirtyBadges(): void {
        $universe = $this->badges(self::UNIVERSE);
        self::assertCount(30, $universe);
        self::assertSame($universe, $this->badges(self::BO_PACKAGE));
        self::assertSame($universe, $this->badges(self::BO_CONFIG));
        foreach ($universe as $where => $badge) self::assertStringStartsWith(self::BADGE, $badge, $where);
    }

    public function testEveryDeliveredPackageIsAcceptedByTheImporter(): void {
        $packages = new ThemePackage(new ConditionEngine(), new TokenRegistry());
        $checked = 0;
        foreach ($this->themeFiles() as $file) {
            if (!str_ends_with($file, '.reamurtheme.json')) continue;
            $json = $this->repo($file);
            $result = $packages->decode($json);
            self::assertTrue($result['ok'], $file . ': ' . implode(',', $result['errors']));
            // O `checksum` gravado no arquivo e o do conteudo que esta nele.
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($packages->checksum($data['config']), $data['checksum'], $file);
            $checked++;
        }
        self::assertSame(7, $checked);
    }

    public function testConfigOnlyFileOfCenaRealIsThePackageWithoutTheEnvelope(): void {
        $packages = new ThemePackage(new ConditionEngine(), new TokenRegistry());
        $package = json_decode($this->repo(self::BO_PACKAGE), true, 512, JSON_THROW_ON_ERROR);
        $config = json_decode($this->repo(self::BO_CONFIG), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['name', 'slug', 'config'], array_keys($config));
        self::assertSame('david_universe_3d_bo', $config['slug']);
        self::assertSame($package['name'], $config['name']);
        self::assertSame($package['slug'], $config['slug']);
        // Mesmo conteudo; so a ordem das chaves e diferente entre os dois arquivos.
        self::assertSame($packages->canonical($package['config']), $packages->canonical($config['config']));
        self::assertSame([], $packages->validateConfig($config['config']));
    }

    public function testImportGuideQuotesTheSizeThePackageReallyHas(): void {
        // Tamanho com fim de linha LF (o do repositorio), mesmo num checkout que converta para CRLF.
        $bytes = strlen(str_replace("\r\n", "\n", $this->repo(self::BO_PACKAGE)));
        self::assertStringContainsString(
            'Envie `' . self::BO_PACKAGE . '` (' . number_format($bytes, 0, ',', '.') . ' bytes).',
            $this->repo('docs/historico/GUIA-BO.md')
        );
    }

    public function testConditionRuleIdCarriesAPatternTheBrowserKeeps(): void {
        $js = $this->repo('dev/admin/view/js/theme-condition-builder.js');
        // No arquivo: '[a-z0-9_\\-]{1,48}' (literal JS) = [a-z0-9_\-]{1,48} para o navegador.
        self::assertSame(1, substr_count($js, "idInput.setAttribute('pattern', '[a-z0-9_\\\\-]{1,48}');"));
        self::assertStringNotContainsString("'[a-z0-9_-]{1,48}'", $js);
        // O campo continua sem `name`: e controle de tela, nao trava o Salvar (ADR 0098).
        self::assertStringNotContainsString("idInput.name", $js);
        self::assertStringNotContainsString("idInput.setAttribute('name'", $js);

        // A regra gemea, no servidor: mesmas letras, mesmo limite.
        $engine = $this->repo('dev/system/library/theme/condition_engine.php');
        self::assertStringContainsString("\$this->safeName(\$id, '/^[a-z0-9_-]+\$/')", $engine);
        self::assertStringContainsString("return \$v !== '' && strlen(\$v) <= 48 && preg_match(\$pattern, \$v) === 1;", $engine);

        // O aviso na tela, e a versao nova dos dois arquivos para o navegador nao servir os antigos.
        self::assertSame(1, substr_count(
            $this->repo('dev/admin/view/css/theme-condition-builder.css'),
            '.tcb-rule__head .tcb-rule__id.form-control:invalid { border-color: var(--bs-danger); box-shadow: 0 0 0 1px var(--bs-danger); }'
        ));
        $controller = $this->repo('dev/admin/controller/design/theme.php');
        self::assertSame(1, substr_count($controller, "Header::ASSET_V . '-theme-builder-b';"));
        self::assertStringNotContainsString('-theme-builder-a', $controller);
        self::assertStringContainsString("'view/css/theme-condition-builder.css?v=' . \$builderAssetV", $controller);
        self::assertStringContainsString("'view/js/theme-condition-builder.js?v=' . \$builderAssetV", $controller);
    }

    public function testNodeCheckerOfTheFormPatternsPasses(): void {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        if ($node === '') self::markTestSkipped('node ausente: rode `node ' . self::CHECKER . '` à mão.');
        $output = [];
        $status = 1;
        exec(escapeshellarg($node) . ' ' . escapeshellarg(DIR_REAMUR . self::CHECKER) . ' 2>&1', $output, $status);
        $last = (string)end($output);
        if (str_contains($last, 'SKIP')) self::markTestSkipped($last);
        self::assertSame(0, $status, implode("\n", $output));
        self::assertMatchesRegularExpression('/^Form pattern checker: \d+ PASS$/', $last);
    }

    public function testEveryKazClassOfTheEditorScreenHasARuleOrAScript(): void {
        $twig = $this->repo(self::SCREEN);
        // As folhas e os scripts que o controller carrega nesta tela.
        $css = '';
        foreach (['kaz-theme-editor.css', 'kaz-canvas.css', 'theme-condition-builder.css'] as $sheet) {
            $css .= preg_replace('~/\*.*?\*/~s', '', $this->repo('dev/admin/view/css/' . $sheet)) . "\n";
        }
        $js = '';
        foreach (array_merge((array)glob(DIR_REAMUR . 'dev/admin/view/js/kaz-*.js'), (array)glob(DIR_REAMUR . 'dev/admin/view/js/theme-*.js')) as $file) {
            $js .= (string)file_get_contents((string)$file) . "\n";
        }

        $classes = [];
        self::assertGreaterThan(300, preg_match_all('/\sclass="([^"]*)"/', $twig, $m));
        foreach ($m[1] as $value) {
            // Trechos de Twig dentro do atributo ({% if %}…) nao sao nomes de classe.
            $value = (string)preg_replace('/\{[{%].*?[%}]\}/s', ' ', $value);
            foreach (preg_split('/\s+/', trim($value)) ?: [] as $class) {
                if (preg_match('/^kaz-[a-z0-9_-]+$/', $class) === 1) $classes[$class] = true;
            }
        }
        self::assertGreaterThan(140, count($classes));

        $orphans = [];
        foreach (array_keys($classes) as $class) {
            $quoted = preg_quote($class, '/');
            $styled = preg_match('/\.' . $quoted . '(?![a-zA-Z0-9_-])/', $css) === 1;
            $scripted = preg_match('/(?<![a-zA-Z0-9_-])' . $quoted . '(?![a-zA-Z0-9_-])/', $js) === 1;
            if (!$styled && !$scripted) $orphans[] = $class;
        }
        // Classe que nenhuma folha estiliza e nenhum script procura nao faz nada: ou ganha regra, ou sai.
        self::assertSame([], $orphans);

        // As quatro que sairam nesta etapa, e os ganchos que ficaram no lugar delas.
        foreach (['kaz-canvas__hiddenlist', 'kaz-btn--publish', 'kaz-asset__thumb--hero', 'kaz-note--state'] as $gone) {
            self::assertStringNotContainsString($gone, $twig);
            self::assertStringNotContainsString($gone, $css . $js);
        }
        self::assertSame(1, substr_count($twig, '<div data-kaz-canvas-hidden hidden></div>'));
        // KZ-11 (ADR 0104): o texto do botao passou a vir do idioma; a classe continua so `kaz-btn`.
        self::assertSame(1, substr_count($twig, '<summary class="kaz-btn" title="{{ text_kaz_publish_hint|e(\'html_attr\') }}">{{ text_kaz_publish }}</summary>'));
        // KZ-13 (ADR 0106): o nome e a dica do bloco passaram a vir do idioma; a classe da miniatura continua a mesma.
        self::assertSame(1, substr_count($twig, 'data-kaz-block="hero" title="{{ text_kaz_block_hero_hint|e(\'html_attr\') }}"><span class="kaz-asset__thumb" aria-hidden="true"></span>{{ text_kaz_block_hero }}</button>'));
        self::assertStringContainsString("root.querySelector('[data-kaz-canvas-hidden]')", $this->repo('dev/admin/view/js/kaz-canvas.js'));
    }
}
