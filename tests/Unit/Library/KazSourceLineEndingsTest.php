<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Design\Theme as AdminThemeController;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Language;
use Reamur\System\Library\Template\ThemeSandbox;
use Reamur\System\Library\Theme\ConditionEngine;
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\ThemeSourceMap;
use Reamur\System\Library\Theme\TokenRegistry;

/**
 * KZ-22 (ADR 0115): quebra de linha das fontes de um tema.
 *
 * O navegador envia cada quebra de linha de um <textarea> como CR LF. Ate a
 * KZ-21 a fonte era guardada como chegava: salvar o tema pelo formulario, sem
 * mexer em nada, reescrevia todas as fontes com um byte a mais por linha, e o
 * teto de tamanho contava esse byte. Agora a fonte e guardada com `\n` em todos
 * os caminhos (salvar, previa, importar, exportar) e o teto e medido depois.
 *
 * A tabela `tests/fixtures/kaz_source_size.json` e conferida aqui, sobre o
 * controller e o pacote, e em node, sobre a conta do script
 * (`tests/check_kaz_element_style.js`).
 */
final class KazSourceLineEndingsTest extends TestCase {
    private const FIXTURE = 'tests/fixtures/kaz_source_size.json';
    private const CONTROLLER = 'dev/admin/controller/design/theme.php';
    private const PACKAGE = 'dev/system/library/theme/theme_package.php';
    private const RULES = 'dev/admin/view/js/kaz-element-attrs.js';
    private const POST = ['name' => 'Tema', 'slug' => 'tema', 'primary' => '#2563eb', 'surface' => '#ffffff'];
    private const PACKAGES = [
        'docs/temas/Atlas-Studio-3D.reamurtheme.json',
        'docs/temas/Atlas-Studio.reamurtheme.json',
        'docs/temas/David-Creator.reamurtheme.json',
        'docs/temas/David-Universe-3D.reamurtheme.json',
        'docs/temas/Reamur-Reference.reamurtheme.json',
        'docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json',
        'tema/david_universe_3d_bo.reamurtheme.json',
    ];

    private function repo(string $path): string {
        $source = file_get_contents(DIR_REAMUR . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    /** @return array{max:int,cases:list<array{name:string,unit:string,times:int,tail:string,bytes:int,bytes_sent:int,ok:bool}>} */
    private function fixture(): array {
        $fixture = json_decode($this->repo(self::FIXTURE), true);
        self::assertIsArray($fixture);

        return $fixture;
    }

    /** O que o navegador faz com o valor de um <textarea> ao enviar o formulario. */
    private static function sent(string $typed): string {
        return (string)preg_replace('/\r\n|\r|\n/', "\r\n", $typed);
    }

    private function package(): ThemePackage {
        return new ThemePackage(new ConditionEngine(), new TokenRegistry());
    }

    private function controller(): AdminThemeController {
        $registry = new Registry();
        $language = new Language('en-us');
        foreach (['error_validation' => 'Validation error', 'error_template_source' => 'Source refused.', 'error_template_source_detail' => 'Source %s, line %d: %s'] as $key => $text) $language->set($key, $text);
        $registry->set('language', $language);
        $registry->set('cms_page_layout', new \Reamur\System\Library\Cms\PageLayout());
        $registry->set('theme_condition_engine', new ConditionEngine());
        $registry->set('theme_token_registry', new TokenRegistry());
        $registry->set('theme_section_registry', new \Reamur\System\Library\Theme\SectionRegistry($registry->get('cms_page_layout')));
        $registry->set('theme_component_registry', new \Reamur\System\Library\Theme\ComponentRegistry());
        $registry->set('theme_schedule', new \Reamur\System\Library\Theme\ThemeSchedule());
        $registry->set('theme_media', new \Reamur\System\Library\Theme\ThemeMedia());
        $registry->set('theme_sites', new \Reamur\System\Library\Theme\ThemeSites());
        $registry->set('theme_package', new ThemePackage($registry->get('theme_condition_engine'), $registry->get('theme_token_registry')));
        $registry->set('three_scene_config', new \Reamur\System\Library\Three\SceneConfig());
        $registry->set('three_asset_manifest', new \Reamur\System\Library\Three\AssetManifest());
        $registry->set('three_scene_preset', new \Reamur\System\Library\Three\ScenePresetRegistry());

        return new AdminThemeController($registry);
    }

    /**
     * @param array<string, mixed> $templates
     * @return array{name:string,slug:string,config:array<string,mixed>,error:string}
     */
    private function build(array $templates, bool $checkPolicy = true): array {
        $theme = $this->controller();
        $method = new \ReflectionMethod($theme, 'buildDraftConfig');

        return $method->invoke($theme, self::POST + ['templates' => $templates], $checkPolicy);
    }

    // ------------------------------------------------------------- formulario

    public function testSavingStoresEverySourceWithLineFeedOnly(): void {
        $typed = [
            'common/home' => "<main>\n  <h1>{{ heading_title }}</h1>\n\n  <p>Olá, é só um teste.</p>\n</main>\n",
            'partial/hero' => "<section>\n{% if hero %}\n  <p>{{ hero.title }}</p>\n{% endif %}\n</section>",
            'common/footer' => '<footer>uma linha só</footer>',
        ];
        foreach ([true, false] as $checkPolicy) {
            // Como o navegador envia: CR LF em cada quebra.
            $built = $this->build(array_map([self::class, 'sent'], $typed), $checkPolicy);
            self::assertSame('', $built['error']);
            self::assertSame($typed, $built['config']['templates']);
            self::assertStringNotContainsString("\r", (string)json_encode($built['config']['templates']));
            self::assertStringNotContainsString('\r', (string)json_encode($built['config']['templates']));

            // Salvar de novo o que foi guardado (o editor reabre com as fontes guardadas): os mesmos bytes.
            $again = $this->build(array_map([self::class, 'sent'], $built['config']['templates']), $checkPolicy);
            self::assertSame($built['config'], $again['config']);
            self::assertSame($this->package()->checksum($built['config']), $this->package()->checksum($again['config']));

            // Uma fonte que chega ja com `\n`, ou com CR solto, da no mesmo.
            self::assertSame($built['config'], $this->build($typed, $checkPolicy)['config']);
            self::assertSame($built['config'], $this->build(array_map(static fn (string $s): string => str_replace("\n", "\r", $s), $typed), $checkPolicy)['config']);
        }
        // O numero de linhas nao muda: so o fim de cada uma.
        self::assertSame(substr_count($typed['common/home'], "\n"), substr_count($built['config']['templates']['common/home'], "\n"));
    }

    public function testFormMeasuresTheSizeTheWayTheSourceIsStored(): void {
        $fixture = $this->fixture();
        self::assertSame(ThemeSandbox::MAX_SOURCE, $fixture['max']);
        self::assertCount(20, $fixture['cases']);
        $seen = ['ok' => 0, 'refused' => 0, 'old' => 0];
        foreach ($fixture['cases'] as $case) {
            $typed = str_repeat($case['unit'], $case['times']) . $case['tail'];
            $stored = ThemeSourceMap::normalize($typed);
            $sent = self::sent($typed);
            self::assertSame($case['bytes'], strlen($stored), $case['name']);
            self::assertSame($case['bytes_sent'], strlen($sent), $case['name']);
            self::assertSame($case['ok'], $case['bytes'] <= $fixture['max'], $case['name']);

            // O servidor so recebe a forma enviada; as outras duas tem de dar a mesma resposta.
            foreach (['enviada' => $sent, 'montada' => $typed, 'no campo' => $stored] as $how => $source) {
                $built = $this->build(['common/home' => $source], $how === 'enviada');
                if ($case['ok']) {
                    self::assertSame('', $built['error'], $case['name'] . ' (' . $how . ')');
                    self::assertSame($stored, $built['config']['templates']['common/home'], $case['name'] . ' (' . $how . ')');
                    self::assertLessThanOrEqual($fixture['max'], strlen($built['config']['templates']['common/home']));
                } else {
                    self::assertStringStartsWith('Source refused.', $built['error'], $case['name'] . ' (' . $how . ')');
                    self::assertSame([], $built['config'], $case['name'] . ' (' . $how . ')');
                }
            }
            // O pacote usa a mesma medida ao importar.
            $pkg = $this->package();
            $config = $this->build([])['config'];
            $config['templates'] = ['common/home' => $sent];
            $file = $pkg->encode(['format' => ThemePackage::FORMAT, 'version' => ThemePackage::VERSION, 'name' => 'Tema', 'slug' => 'tema', 'config' => $config, 'checksum' => $pkg->checksum($config)]);
            $decoded = $pkg->decode($file);
            if (strlen($file) > ThemePackage::MAX_BYTES) {
                self::assertSame(['package_size_invalid'], $decoded['errors'], $case['name']);
            } else {
                self::assertSame($case['ok'], $decoded['ok'], $case['name'] . ' (pacote): ' . implode(',', $decoded['errors']));
                if ($case['ok']) self::assertSame($stored, $decoded['config']['templates']['common/home'], $case['name']);
            }

            $seen[$case['ok'] ? 'ok' : 'refused']++;
            if ($case['ok'] && $case['bytes_sent'] > $fixture['max']) {
                $seen['old']++;
                // Era recusada so por causa do CR que o navegador acrescenta.
                self::assertTrue(ThemeSandbox::blocked($sent), $case['name']);
                self::assertFalse(ThemeSandbox::blocked($stored), $case['name']);
            }
        }
        self::assertSame(['ok' => 11, 'refused' => 9, 'old' => 6], $seen);
    }

    public function testControllerNormalizesBeforeItMeasuresAndBeforeItStores(): void {
        $controller = $this->repo(self::CONTROLLER);
        $normalize = '$source=\Reamur\System\Library\Theme\ThemeSourceMap::normalize($source);';
        self::assertSame(1, substr_count($controller, $normalize));
        $start = strpos($controller, 'private function buildDraftConfig(');
        self::assertIsInt($start);
        $loop = substr($controller, $start, (int)strpos($controller, "if (\$invalidTemplateSource) return \$bad(", $start) - $start);
        $at = strpos($loop, $normalize);
        self::assertIsInt($at);
        // Antes da medida, da conferencia da politica e da gravacao; depois de saber que e texto.
        self::assertGreaterThan(strpos($loop, '!is_string($source)'), $at);
        self::assertLessThan(strpos($loop, '$sandbox::blocked($source)'), $at);
        self::assertLessThan(strpos($loop, '$sandbox::check($source)'), $at);
        self::assertLessThan(strpos($loop, '$safeTemplates[(string)$templateKey]=$source;'), $at);
        self::assertSame(1, substr_count($loop, '$safeTemplates[(string)$templateKey]='));
        // Os tres caminhos do formulario (salvar, previa do canvas, previa do site) montam o tema pelo mesmo metodo.
        self::assertSame(3, substr_count($controller, '$this->buildDraftConfig('));
        self::assertSame(1, substr_count($controller, "'templates'=>\$safeTemplates"));
        // A fonte devolvida ao formulario depois de um erro usa a mesma medida.
        self::assertStringContainsString(
            "if (is_string(\$posted)) \$posted=\\Reamur\\System\\Library\\Theme\\ThemeSourceMap::normalize(\$posted);\n                    if (is_string(\$posted) && strlen(\$posted)<=131072) \$data['templates'][\$templateKey]=\$posted;",
            $controller
        );
        // O script do canvas e servido com outra versao: a conta do tamanho mudou.
        self::assertStringContainsString("ASSET_V.'-kaz-canvas-kz22';", $controller);

        // E a conta do script: uma quebra de linha, um byte.
        $rules = $this->repo(self::RULES);
        self::assertSame(1, preg_match('~function tooLong\(s\) \{.*?\n  \}~s', $rules, $m));
        self::assertStringContainsString('if (c === 13) { bytes += 1; if (i + 1 < n && s.charCodeAt(i + 1) === 10) i++; }', $m[0]);
        self::assertStringNotContainsString('bytes += 2; if', $m[0]);
        self::assertStringNotContainsString('c === 10', $m[0]);
    }

    // ----------------------------------------------------------------- pacote

    public function testPackageNormalizesOnExportAndOnImport(): void {
        $pkg = $this->package();
        self::assertSame(
            ['common/home' => "a\nb\nc\n", 'partial/hero' => 'sem quebra', 'x' => 7, 'y' => null, 'z' => ["a\r\n"]],
            ThemePackage::normalizeTemplates(['common/home' => "a\r\nb\rc\n", 'partial/hero' => 'sem quebra', 'x' => 7, 'y' => null, 'z' => ["a\r\n"]])
        );
        self::assertSame([], ThemePackage::normalizeTemplates([]));

        $lf = $this->build(['common/home' => "<main>\n  <p>é</p>\n</main>\n", 'common/footer' => "<footer>\n</footer>"])['config'];
        self::assertSame([], $pkg->validateConfig($lf));
        $crlf = $lf;
        $crlf['templates'] = array_map([self::class, 'sent'], $lf['templates']);
        self::assertNotSame($pkg->checksum($lf), $pkg->checksum($crlf));

        // Limpar a configuracao ja normaliza, e fazer de novo nao muda nada.
        self::assertSame($pkg->sanitizeConfig($lf), $pkg->sanitizeConfig($crlf));
        self::assertSame($pkg->sanitizeConfig($lf), $pkg->sanitizeConfig($pkg->sanitizeConfig($crlf)));
        self::assertSame($lf['templates'], $pkg->sanitizeConfig($lf)['templates']);
        // Templates que nao sao um mapa continuam fora, como antes.
        self::assertArrayNotHasKey('templates', $pkg->sanitizeConfig(['templates' => "a\r\n"] + $lf));

        // Exportar um tema guardado com CR LF (salvo antes da KZ-22): o arquivo sai com `\n` e com o checksum do que leva.
        $old = $pkg->export('Tema', 'tema', $crlf);
        $new = $pkg->export('Tema', 'tema', $lf);
        self::assertSame($new, $old);
        self::assertSame($pkg->checksum($lf), $old['checksum']);
        self::assertSame($pkg->checksum($old['config']), $old['checksum']);
        $file = $pkg->encode($old);
        self::assertStringNotContainsString('\r', $file);
        self::assertStringNotContainsString("\r", $file);

        // Importar um arquivo escrito antes da KZ-22 (fontes com CR LF, checksum sobre elas): aceito, guardado com `\n`.
        $legacy = $pkg->encode(['format' => ThemePackage::FORMAT, 'version' => ThemePackage::VERSION, 'name' => 'Tema', 'slug' => 'tema', 'config' => $crlf, 'checksum' => $pkg->checksum($crlf)]);
        self::assertStringContainsString('\r\n', $legacy);
        $decoded = $pkg->decode($legacy);
        self::assertTrue($decoded['ok'], implode(',', $decoded['errors']));
        self::assertSame($pkg->sanitizeConfig($lf), $decoded['config']);
        // Exportar o que foi importado e importar de novo: ponto fixo.
        $round = $pkg->decode($pkg->encode($pkg->export('Tema', 'tema', $decoded['config'])));
        self::assertTrue($round['ok']);
        self::assertSame($decoded['config'], $round['config']);

        // O checksum continua valendo para o arquivo como foi escrito: trocar o fim de linha a mao, sem refazer o checksum, e recusado.
        $tampered = $pkg->encode(['format' => ThemePackage::FORMAT, 'version' => ThemePackage::VERSION, 'name' => 'Tema', 'slug' => 'tema', 'config' => $crlf, 'checksum' => $pkg->checksum($lf)]);
        self::assertSame(['checksum_invalid'], $pkg->decode($tampered)['errors']);
        // E a fonte normalizada ainda passa pela conferencia de sempre.
        $bad = $crlf;
        $bad['templates']['common/home'] = "<main>\r\n{{ constant('X') }}\r\n</main>";
        $refused = $pkg->decode($pkg->encode(['format' => ThemePackage::FORMAT, 'version' => ThemePackage::VERSION, 'name' => 'Tema', 'slug' => 'tema', 'config' => $bad, 'checksum' => $pkg->checksum($bad)]));
        self::assertFalse($refused['ok']);
        self::assertSame([], $refused['config']);

        $source = $this->repo(self::PACKAGE);
        self::assertSame(1, substr_count($source, "if (isset(\$out['templates'])) \$out['templates']=self::normalizeTemplates(\$out['templates']);"));
        $decode = substr($source, (int)strpos($source, 'public function decode('));
        self::assertLessThan(strpos($decode, '$this->validateConfig($config)'), strpos($decode, "\$config['templates']=self::normalizeTemplates("));
        self::assertGreaterThan(strpos($decode, 'hash_equals($this->checksum($config),$sum)'), strpos($decode, "\$config['templates']=self::normalizeTemplates("));
    }

    public function testPackagesOfTheRepositoryAreNotTouchedByTheRule(): void {
        $pkg = $this->package();
        foreach (self::PACKAGES as $path) {
            $raw = $this->repo($path);
            $file = json_decode($raw, true);
            self::assertIsArray($file, $path);
            self::assertNotSame([], $file['config']['templates'], $path);
            foreach ($file['config']['templates'] as $key => $template) self::assertStringNotContainsString("\r", $template, $path . ' ' . $key);

            $decoded = $pkg->decode(str_replace("\r\n", "\n", $raw));
            self::assertTrue($decoded['ok'], $path . ': ' . implode(',', $decoded['errors']));
            self::assertSame($file['config']['templates'], $decoded['config']['templates'], $path);
            // Exportar de novo da o checksum que o arquivo ja traz: a etapa nao pede pacote novo.
            self::assertSame($file['checksum'], $pkg->export((string)$file['name'], (string)$file['slug'], $decoded['config'])['checksum'], $path);
        }
    }
}
