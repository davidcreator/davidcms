<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Design\Theme as AdminThemeController;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Document;
use Reamur\System\Library\Language;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Session;
use Reamur\System\Library\Template\Twig;
use Reamur\System\Library\Theme\ConditionEngine;
use Reamur\System\Library\Theme\ThemeCanvasSample;
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\ThemePreviewState;
use Reamur\System\Library\Theme\ThemeSnapshot;
use Reamur\System\Library\Theme\ThemeStrings;
use Reamur\System\Library\Theme\TokenRegistry;
use Reamur\System\Library\Url;

/**
 * KZ-19 (ADR 0112): textos proprios do tema por idioma (`config.strings`).
 *
 * Cobre o caminho inteiro: gramatica, pacote (validar, exportar, importar,
 * herdar), estado efetivo por idioma, entrega a fonte do tema no site
 * publicado e nas previas do editor, e o save pelo painel.
 */
final class KazThemeStringsTest extends TestCase {
    private const LANGUAGES = ['en-us', 'pt-br', 'es-eu'];
    private const STRINGS = [
        'hero_start' => ['en-us' => 'Start mission', 'pt-br' => 'Iniciar missão', 'es-eu' => 'Iniciar misión'],
        'only_english' => ['en-us' => 'Only English'],
        'only_portuguese' => ['pt-br' => 'Só português'],
    ];

    private function package(): ThemePackage {
        return new ThemePackage(new ConditionEngine(), new TokenRegistry());
    }

    private function snapshot(): ThemeSnapshot {
        return new ThemeSnapshot(new ConditionEngine(), new TokenRegistry(), $this->package());
    }

    private function repo(string $path): string {
        $source = file_get_contents(DIR_REAMUR . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    // ---------------------------------------------------------------- gramatica

    public function testKeyLanguageAndTextGrammarsAreClosed(): void {
        foreach (['a', 'hero_start', 'x9', 'a' . str_repeat('b', 63)] as $key) self::assertTrue(ThemeStrings::isKey($key), $key);
        foreach (['', 'Hero', '9a', '_a', 'a-b', 'a.b', 'a b', "a\n", 'a' . str_repeat('b', 64), 7, null, ['a']] as $key) self::assertFalse(ThemeStrings::isKey($key), var_export($key, true));

        foreach (self::LANGUAGES as $code) self::assertTrue(ThemeStrings::isLanguage($code), $code);
        foreach (['', 'en', 'en_us', 'EN-US', 'en-usa', 'pt-br ', "pt-br\n", '../..', 1, null] as $code) self::assertFalse(ThemeStrings::isLanguage($code), var_export($code, true));

        self::assertTrue(ThemeStrings::isText("Linha um\nLinha dois — “aspas” <b>não é HTML</b>"));
        self::assertTrue(ThemeStrings::isText(str_repeat('ç', ThemeStrings::MAX_LENGTH)), 'o limite conta caracteres, nao bytes');
        self::assertFalse(ThemeStrings::isText(str_repeat('ç', ThemeStrings::MAX_LENGTH + 1)));
        foreach (['', "a\0b", "a\tb", "a\rb", "a\x1Bb", "a\x7Fb", "\xC3\x28", 5, null, ['x']] as $text) self::assertFalse(ThemeStrings::isText($text), var_export($text, true));
    }

    public function testValidateNamesTheFirstProblemAndNormalizeSortsOrRefuses(): void {
        self::assertSame([], ThemeStrings::validate([]));
        self::assertSame([], ThemeStrings::validate(self::STRINGS));
        self::assertSame(['strings_type'], ThemeStrings::validate('x'));
        self::assertSame(['strings_key'], ThemeStrings::validate(['Bad-Key' => ['en-us' => 'x']]));
        self::assertSame(['strings_key'], ThemeStrings::validate([['en-us' => 'x']]), 'lista (chave numerica) nao e um dicionario');
        self::assertSame(['strings_languages'], ThemeStrings::validate(['a' => 'texto solto']));
        self::assertSame(['strings_languages'], ThemeStrings::validate(['a' => []]));
        self::assertSame(['strings_language'], ThemeStrings::validate(['a' => ['english' => 'x']]));
        self::assertSame(['strings_text'], ThemeStrings::validate(['a' => ['en-us' => '']]));
        self::assertSame(['strings_text'], ThemeStrings::validate(['a' => ['en-us' => ['x']]]));

        $many = [];
        for ($i = 0; $i < ThemeStrings::MAX_KEYS; $i++) $many['k' . $i] = ['en-us' => 'x'];
        self::assertSame([], ThemeStrings::validate($many));
        self::assertSame(['strings_count'], ThemeStrings::validate($many + ['one_more' => ['en-us' => 'x']]));

        $codes = [];
        foreach (range('a', 'm') as $letter) $codes['x' . $letter . '-zz'] = 'x';
        self::assertCount(13, $codes);
        self::assertSame(['strings_languages'], ThemeStrings::validate(['a' => $codes]));

        self::assertNull(ThemeStrings::normalize(['a' => ['en-us' => '']]));
        self::assertSame(
            ['a' => ['en-us' => '1', 'pt-br' => '2'], 'b' => ['es-eu' => '3']],
            ThemeStrings::normalize(['b' => ['es-eu' => '3'], 'a' => ['pt-br' => '2', 'en-us' => '1']])
        );
        self::assertSame(
            ['max_keys' => 600, 'max_languages' => 12, 'max_length' => 2000, 'max_json_bytes' => 262144, 'fallback' => 'en-us', 'global' => 'theme_text'],
            ThemeStrings::spec()
        );
    }

    public function testResolveUsesTheRequestedLanguageThenEnglishThenWhateverExists(): void {
        self::assertSame(
            ['hero_start' => 'Iniciar missão', 'only_english' => 'Only English', 'only_portuguese' => 'Só português'],
            ThemeStrings::resolve(self::STRINGS, 'pt-br')
        );
        self::assertSame(
            ['hero_start' => 'Iniciar misión', 'only_english' => 'Only English', 'only_portuguese' => 'Só português'],
            ThemeStrings::resolve(self::STRINGS, 'es-eu'),
            'sem espanhol: ingles; sem ingles: o idioma que houver. O texto nunca some.'
        );
        self::assertSame('Start mission', ThemeStrings::resolve(self::STRINGS, 'fr-fr')['hero_start']);
        // Entrada estragada (banco editado a mao) e ignorada linha a linha, sem excecao.
        self::assertSame([], ThemeStrings::resolve(null, 'pt-br'));
        self::assertSame([], ThemeStrings::resolve('x', 'pt-br'));
        self::assertSame(
            ['good' => 'ok'],
            ThemeStrings::resolve(['Bad Key' => ['en-us' => 'x'], 'loose' => 'x', 'empty' => ['en-us' => ''], 'good' => ['pt-br' => "a\0b", 'en-us' => 'ok']], 'pt-br')
        );
    }

    public function testMissingListsTheKeysWithoutTextInEachLanguage(): void {
        self::assertSame(
            ['en-us' => ['only_portuguese'], 'pt-br' => ['only_english'], 'es-eu' => ['only_english', 'only_portuguese']],
            ThemeStrings::missing(self::STRINGS, self::LANGUAGES)
        );
        self::assertSame(['en-us' => []], ThemeStrings::missing(null, ['en-us', 'not a code']));
    }

    // ------------------------------------------------------------------ pacote

    public function testPackageAcceptsStringsAndRefusesAnythingOutsideTheContract(): void {
        $package = $this->package();
        $base = ['primary' => '#2563eb', 'surface' => '#ffffff'];

        self::assertSame([], $package->validateConfig($base), 'tema sem textos proprios continua valido');
        self::assertSame([], $package->validateConfig($base + ['strings' => self::STRINGS]));
        self::assertSame([], $package->validateConfig($base + ['strings' => []]));
        foreach ([
            'texto'        => 'x',
            'chave'        => ['Hero' => ['en-us' => 'x']],
            'idioma'       => ['hero' => ['en' => 'x']],
            'vazio'        => ['hero' => ['en-us' => '']],
            'controle'     => ['hero' => ['en-us' => "a\0b"]],
            'longo'        => ['hero' => ['en-us' => str_repeat('a', ThemeStrings::MAX_LENGTH + 1)]],
            'sem idiomas'  => ['hero' => []],
        ] as $case => $strings) {
            self::assertContains('strings_invalid', $package->validateConfig($base + ['strings' => $strings]), $case);
        }
        self::assertContains('strings', $package->spec()['config_keys']);
        self::assertSame(ThemeStrings::spec(), $package->spec()['strings']);
    }

    public function testExportedFileCarriesTheStringsAndImportGivesThemBack(): void {
        $package = $this->package();
        $config = ['primary' => '#2563eb', 'surface' => '#ffffff', 'strings' => self::STRINGS,
                   'templates' => ['error/not_found' => '<h1>{{ theme_text.hero_start }}</h1>']];

        $file = $package->encode($package->export('Tema', 'tema', $config));
        // O arquivo e o que se guarda como copia de seguranca do tema: os textos vao escritos nele.
        self::assertStringContainsString('"Iniciar missão"', $file);
        self::assertStringContainsString('"Iniciar misión"', $file);

        $back = $package->decode($file);
        self::assertTrue($back['ok'], implode(',', $back['errors']));
        self::assertSame(self::STRINGS, $back['config']['strings']);

        // Os textos entram na soma de conferencia: arquivo alterado a mao nao e importado.
        $tampered = str_replace('"Iniciar missão"', '"Outro texto"', $file);
        self::assertNotSame($file, $tampered);
        self::assertSame(['checksum_invalid'], $package->decode($tampered)['errors']);

        // Arquivo com a soma certa e texto fora do contrato: reprovado inteiro.
        $bad = $package->export('Tema', 'tema', ['primary' => '#2563eb', 'surface' => '#ffffff']);
        $bad['config']['strings'] = ['hero' => ['english' => 'x']];
        $bad['checksum'] = $package->checksum($bad['config']);
        $result = $package->decode($package->encode($bad));
        self::assertFalse($result['ok']);
        self::assertSame(['strings_invalid'], $result['errors']);
        self::assertSame([], $result['config']);
    }

    public function testChildThemeInheritsTheParentStringsAndReplacesKeyByKey(): void {
        $parent = ['primary' => '#111111', 'surface' => '#ffffff', 'strings' => self::STRINGS];
        $child = ['primary' => '#222222', 'surface' => '#ffffff', 'parent' => 'pai',
                  'strings' => ['hero_start' => ['pt-br' => 'Começar'], 'child_only' => ['en-us' => 'Child']]];

        $merged = $this->package()->resolveChain($child, ['pai' => $parent, 'filho' => $child], 'filho');
        self::assertSame(['hero_start', 'only_english', 'only_portuguese', 'child_only'], array_keys($merged['strings']));
        // A chave do filho substitui a do pai inteira (o filho responde por todos os idiomas dela).
        self::assertSame(['pt-br' => 'Começar'], $merged['strings']['hero_start']);

        $state = $this->snapshot()->resolve($child, ['route' => 'common/home', 'language' => 'es-eu'], ['pai' => $parent, 'filho' => $child], 'filho');
        self::assertSame('Começar', $state['strings']['hero_start']);
        self::assertSame('Only English', $state['strings']['only_english']);
        self::assertSame('Child', $state['strings']['child_only']);
    }

    // ------------------------------------------------------------ estado efetivo

    public function testSnapshotDeliversOneLanguageAtATime(): void {
        $config = ['primary' => '#2563eb', 'surface' => '#ffffff', 'strings' => self::STRINGS];
        $expected = ['en-us' => 'Start mission', 'pt-br' => 'Iniciar missão', 'es-eu' => 'Iniciar misión'];
        foreach ($expected as $code => $text) {
            $state = $this->snapshot()->resolve($config, ['route' => 'common/home', 'language' => $code]);
            self::assertSame($code, $state['language']);
            self::assertSame($text, $state['strings']['hero_start']);
            self::assertCount(3, $state['strings'], $code . ': nenhuma chave some por falta de traducao');
        }
        foreach ([[], ['language' => ''], ['language' => '../../x'], ['language' => ['pt-br']]] as $context) {
            $state = $this->snapshot()->resolve($config, $context + ['route' => 'common/home']);
            self::assertSame('en-us', $state['language']);
            self::assertSame('Start mission', $state['strings']['hero_start']);
        }
        $plain = $this->snapshot()->resolve(['primary' => '#2563eb', 'surface' => '#ffffff'], ['route' => 'common/home', 'language' => 'pt-br']);
        self::assertSame([], $plain['strings']);
        self::assertSame('pt-br', $plain['language']);
    }

    // ----------------------------------------------------------- site publicado

    public function testPublishedSiteHandsTheTextsToTheThemeSourceEscaped(): void {
        $long = str_repeat('Parágrafo do tema. ', 100); // 1900 caracteres: bem acima do corte de 256 bytes das variaveis de regra
        $source = '<h1>{{ theme_text.hero_start }}</h1><p>{{ theme_text.long }}</p><i>{{ theme_lang }}</i><b>{{ theme_text.html }}</b><u>{{ theme_text.absent|default(\'recuo\') }}</u>';
        $data = ['header' => '', 'footer' => ''];

        foreach (['antes das fontes', 'depois das fontes'] as $order) {
            $twig = new Twig('', false);
            $strings = ['hero_start' => 'Iniciar missão', 'long' => $long, 'html' => '<script>alert(1)</script> & "aspas"'];
            if ($order === 'antes das fontes') $twig->setThemeStrings($strings, 'pt-br');
            $twig->setThemeTemplates(['error/not_found' => $source]);
            if ($order === 'depois das fontes') $twig->setThemeStrings($strings, 'pt-br');

            self::assertSame(
                '<h1>Iniciar missão</h1><p>' . $long . '</p><i>pt-br</i><b>&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;aspas&quot;</b><u>recuo</u>',
                $twig->render('error/not_found', $data),
                $order
            );
        }
    }

    public function testThemeGlobalsAndThemeTextsDoNotEraseEachOther(): void {
        $twig = new Twig('', false);
        $twig->setThemeTemplates(['error/not_found' => '{{ theme_brand }}|{{ theme_text.a }}|{{ theme_lang }}']);
        $twig->setThemeStrings(['a' => 'texto'], 'es-eu');
        $twig->setThemeGlobals(['theme_brand' => 'Atlas']);
        self::assertSame('Atlas|texto|es-eu', $twig->render('error/not_found', ['header' => '', 'footer' => '']));

        // Um global de regra nao consegue se passar pelos textos do tema.
        $twig->setThemeGlobals(['theme_brand' => 'Atlas', 'theme_text' => 'tomado', 'theme_lang' => 'tomado']);
        self::assertSame('Atlas|texto|es-eu', $twig->render('error/not_found', ['header' => '', 'footer' => '']));

        // Idioma fora da gramatica vira o de recuo; entradas fora do contrato nao entram.
        $twig->setThemeStrings(['a' => 'novo', 'Bad Key' => 'x', 'b' => ['lista'], 'c' => "a\0b"], '../../x');
        $twig->setThemeTemplates(['error/not_found' => '{{ theme_text|length }}|{{ theme_text.a }}|{{ theme_lang }}']);
        self::assertSame('1|novo|en-us', $twig->render('error/not_found', ['header' => '', 'footer' => '']));
    }

    public function testThemeWithoutStringsStillRendersAndTheVariableIsAnEmptyMap(): void {
        $twig = new Twig('', false);
        $twig->setThemeTemplates(['error/not_found' => '[{{ theme_text.anything }}][{{ theme_text|length }}][{{ theme_lang }}]']);
        self::assertSame('[][0][en-us]', $twig->render('error/not_found', ['header' => '', 'footer' => '']));
    }

    // --------------------------------------------------------- previas do editor

    public function testEditorCanvasAndFragmentPreviewShowTheLanguageBeingPreviewed(): void {
        $config = ['primary' => '#2563eb', 'surface' => '#ffffff', 'strings' => self::STRINGS];
        $source = '<section id="kz-s">{{ theme_text.hero_start }} / {{ theme_text.only_english }} / {{ theme_lang }}</section>';
        $expected = [
            'en-us' => 'Start mission / Only English / en-us',
            'pt-br' => 'Iniciar missão / Only English / pt-br',
            'es-eu' => 'Iniciar misión / Only English / es-eu',
        ];
        $twig = new Twig(DIR_TEMPLATE, false);
        foreach ($expected as $code => $text) {
            $state = $this->snapshot()->resolve($config, ['route' => 'common/home', 'language' => $code]);

            $globals = ThemePreviewState::globals($state);
            self::assertSame($state['strings'], $globals['theme_text']);
            self::assertSame($code, $globals['theme_lang']);

            // Canvas (pagina inteira do rascunho).
            $page = $twig->renderThemePage('common/home', ['partial/hero-3d' => $source], ThemeCanvasSample::context('common/home', ThemeCanvasSample::strings($code, 'common/home')), [], $state);
            self::assertSame([], $page['errors'], $code);
            self::assertStringContainsString('<section id="kz-s">' . $text . '</section>', $page['main'], $code);

            // Previa isolada de uma fonte.
            self::assertStringContainsString('<section id="kz-s">' . $text . '</section>', $twig->renderSandboxPreview('partial/hero', $source, [], $state), $code);
        }
        // Estado sem a chave (versao antiga gravada em sessao): mapa vazio e idioma de recuo.
        $old = ThemePreviewState::globals(['primary' => '#2563eb', 'surface' => '#ffffff']);
        self::assertSame([], $old['theme_text']);
        self::assertSame('en-us', $old['theme_lang']);
    }

    // ------------------------------------------------------------------ painel

    private function registry(): Registry {
        $registry = new Registry();
        $language = new Language('en-us');
        foreach (['error_validation' => 'Validation error', 'error_theme_strings' => 'Theme texts invalid'] as $key => $text) $language->set($key, $text);
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

        return $registry;
    }

    public function testEditorFieldIsValidatedOnTheServer(): void {
        $theme = new AdminThemeController($this->registry());
        $build = new \ReflectionMethod($theme, 'buildDraftConfig');
        $post = ['name' => 'Tema', 'slug' => 'tema', 'primary' => '#2563eb', 'surface' => '#ffffff'];
        $json = static fn (mixed $value): string => (string)json_encode($value, JSON_UNESCAPED_UNICODE);

        // Aceito: gravado na forma canonica (chaves e idiomas em ordem).
        $ok = $build->invoke($theme, $post + ['strings_json' => $json(['b' => ['pt-br' => 'B', 'en-us' => 'B en'], 'a' => ['es-eu' => 'A']])], true);
        self::assertSame('', $ok['error']);
        self::assertSame(['a' => ['es-eu' => 'A'], 'b' => ['en-us' => 'B en', 'pt-br' => 'B']], $ok['config']['strings']);
        self::assertSame([], $this->package()->validateConfig($ok['config']));

        // Sem textos: a chave nao e gravada (tema antigo sai igual ao que entrou).
        foreach ([[], ['strings_json' => ''], ['strings_json' => '   '], ['strings_json' => '{}'], ['strings_json' => '[]']] as $extra) {
            $none = $build->invoke($theme, $post + $extra, true);
            self::assertSame('', $none['error'], $json($extra));
            self::assertArrayNotHasKey('strings', $none['config'], $json($extra));
        }

        // Reprovado: nada e montado, com a mensagem propria.
        foreach ([
            'nao e JSON'     => '{"a": ',
            'texto solto'    => '"x"',
            'chave'          => $json(['Hero' => ['en-us' => 'x']]),
            'idioma'         => $json(['hero' => ['en' => 'x']]),
            'texto vazio'    => $json(['hero' => ['en-us' => '']]),
            'texto longo'    => $json(['hero' => ['en-us' => str_repeat('a', ThemeStrings::MAX_LENGTH + 1)]]),
            'lista'          => $json([['en-us' => 'x']]),
            'campo em lista' => ['x'],
            // JSON valido, so que maior do que o teto do campo (os espacos ficam NO MEIO: as pontas sao aparadas).
            'acima do teto'  => '{' . str_repeat(' ', ThemeStrings::MAX_JSON_BYTES) . '"hero":{"en-us":"x"}}',
        ] as $case => $raw) {
            $bad = $build->invoke($theme, $post + ['strings_json' => $raw], true);
            self::assertSame('Theme texts invalid', $bad['error'], $case);
            self::assertSame([], $bad['config'], $case);
        }
    }

    public function testSaveStoresTheTextsShowsThemAgainAndAnOldFormDoesNotEraseThem(): void {
        $registry = $this->registry();
        $request = new Request();
        $response = new Response();
        /** @var Session $session */
        $session = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $session->data = [];

        $model = new class(['primary' => '#2563eb', 'surface' => '#ffffff', 'strings' => self::STRINGS]) {
            /** @var list<array<string, mixed>> */
            public array $saved = [];
            /** @param array<string, mixed> $config */
            public function __construct(private array $config) {}
            /** @return array<string, mixed> */
            public function getTheme(int $id): array { return ['theme_id' => $id, 'name' => 'Tema', 'slug' => 'tema', 'draft_config' => json_encode($this->config)]; }
            public function slugExists(string $slug, int $id): bool { return false; }
            /** @param array<string, mixed> $data */
            public function save(int $id, array $data, int $userId): int { $this->saved[] = $data; return $id; }
            /** @param array<int, mixed> $args */
            public function __call(string $name, array $args): mixed { return []; }
        };
        $load = new class {
            /** @var array<string, mixed> */
            public array $data = [];
            public function language(string $route): void {}
            public function model(string $route): void {}
            public function controller(string $route, mixed ...$args): string { return ''; }
            /** @param array<string, mixed> $data */
            public function view(string $route, array $data = []): string { $this->data = $data; return ''; }
        };
        $config = new Config();
        $config->set('site_url', 'https://example.test/admin/');
        $registry->set('config', $config);
        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('url', new Url('https://example.test/admin/'));
        $registry->set('document', new Document());
        $registry->set('session', $session);
        $registry->set('load', $load);
        $registry->set('model_design_theme', $model);
        $registry->set('theme_snapshot', new ThemeSnapshot($registry->get('theme_condition_engine'), $registry->get('theme_token_registry'), $registry->get('theme_package')));
        $registry->set('user', new class {
            public function hasPermission(string $type, string $route): bool { return true; }
            public function getUserId(): int { return 1; }
        });
        $theme = new AdminThemeController($registry);

        // Abrir o editor: o campo mostra o que esta gravado e quantos textos faltam por idioma.
        $request->server['REQUEST_METHOD'] = 'GET';
        $theme->edit('1');
        self::assertSame(3, $load->data['strings_count']);
        self::assertSame(ThemeStrings::normalize(self::STRINGS), json_decode((string)$load->data['strings_json'], true));
        self::assertStringContainsString('Iniciar missão', (string)$load->data['strings_json'], 'acentos legiveis, sem \\u00e3');
        self::assertSame([['code' => 'en-us', 'count' => 1], ['code' => 'pt-br', 'count' => 1], ['code' => 'es-eu', 'count' => 2]], $load->data['strings_missing']);

        $post = ['csrf_token' => Csrf::token($session), 'name' => 'Tema', 'slug' => 'tema', 'primary' => '#2563eb', 'surface' => '#ffffff'];
        $request->server['REQUEST_METHOD'] = 'POST';

        // 1) Formulario do editor com o campo: grava o que foi enviado.
        $edited = ['hero_start' => ['en-us' => 'Go', 'pt-br' => 'Vai', 'es-eu' => 'Va']];
        $request->post = $post + ['strings_json' => (string)json_encode($edited)];
        $load->data = [];
        $theme->edit('1');
        self::assertCount(1, $model->saved);
        self::assertSame(['hero_start' => ['en-us' => 'Go', 'es-eu' => 'Va', 'pt-br' => 'Vai']], $model->saved[0]['config']['strings'], 'forma canonica: idiomas em ordem');
        self::assertSame([], $load->data, 'save aceito redireciona');

        // 2) Formulario SEM o campo (integracao antiga): os textos gravados ficam.
        $request->post = $post;
        $theme->edit('1');
        self::assertCount(2, $model->saved);
        self::assertSame(ThemeStrings::normalize(self::STRINGS), $model->saved[1]['config']['strings']);

        // 3) Campo enviado vazio: e um pedido para apagar, e apaga.
        $request->post = $post + ['strings_json' => '{}'];
        $theme->edit('1');
        self::assertCount(3, $model->saved);
        self::assertArrayNotHasKey('strings', $model->saved[2]['config']);

        // 4) Campo invalido: nada e salvo e o que a pessoa digitou volta para a tela.
        $typed = '{"Hero": {"en-us": "x"}}';
        $request->post = $post + ['strings_json' => $typed];
        $load->data = [];
        $theme->edit('1');
        self::assertCount(3, $model->saved);
        self::assertSame('Theme texts invalid', $load->data['error']);
        self::assertSame($typed, $load->data['strings_json']);
    }

    public function testEditorScreenHasTheFieldInsideTheFormInTheThreeLanguages(): void {
        $view = $this->repo('dev/admin/view/template/design/kaz_theme_editor.twig');
        $field = strpos($view, 'name="strings_json"');
        self::assertIsInt($field);
        self::assertSame(1, substr_count($view, 'name="strings_json"'));
        // Um campo so (uma variavel de formulario): nao depende de max_input_vars.
        self::assertStringNotContainsString('name="strings[', $view);
        // Abre com altura para ler (o CSS do bloco so garante 3 linhas).
        self::assertStringContainsString('name="strings_json" data-kaz-strings-json rows="16"', $view);
        // O ultimo <form> aberto antes do campo e o do tema, e ele ainda nao foi fechado.
        $before = substr($view, 0, $field);
        $open = strrpos($before, '<form');
        self::assertIsInt($open);
        self::assertStringStartsWith('<form id="kaz-theme-form" method="post"', substr($view, $open));
        self::assertStringNotContainsString('</form>', substr($before, $open), 'o campo fica dentro do formulario do tema');
        self::assertStringContainsString('{{ strings_json }}', $view, 'impresso com escape (sem |raw)');
        self::assertStringNotContainsString('strings_json|raw', $view);

        $keys = ['error_theme_strings', 'text_kaz_strings_summary', 'text_kaz_strings_note', 'text_kaz_strings_key', 'text_kaz_strings_missing', 'text_kaz_strings_field'];
        $seen = [];
        foreach (self::LANGUAGES as $code) {
            $_ = [];
            require DIR_REAMUR . 'dev/admin/language/' . $code . '/design/theme.php';
            foreach ($keys as $key) {
                self::assertIsString($_[$key] ?? null, $code . ' ' . $key);
                self::assertNotSame('', trim($_[$key]), $code . ' ' . $key);
                $seen[$key][$code] = $_[$key];
            }
            // A nota tem dois encaixes: o exemplo de uso e o idioma de recuo.
            self::assertSame(2, substr_count($_['text_kaz_strings_note'], '%s'), $code);
        }
        // Traduzido de verdade: a frase de resumo e diferente nos tres idiomas.
        self::assertCount(3, array_unique($seen['text_kaz_strings_summary']));
        self::assertCount(3, array_unique($seen['error_theme_strings']));
        foreach (array_diff($keys, ['error_theme_strings']) as $key) self::assertSame(1, preg_match('/\{[{%][^{}]*\b' . $key . '\b/', $view), $key);
    }

    public function testCatalogSendsTheNegotiatedLanguageTextsToTheTemplateEngine(): void {
        $startup = $this->repo('dev/catalog/controller/startup/theme.php');
        // O idioma negociado entra no contexto antes do estado efetivo ser calculado...
        $context = strpos($startup, "\$context['language'] = \$neg['code'];");
        $resolve = strpos($startup, '$this->theme_snapshot->resolve(');
        $deliver = strpos($startup, "\$this->template->setThemeStrings((array)(\$snapshot['strings'] ?? []), (string)(\$snapshot['language'] ?? ''));");
        self::assertIsInt($context);
        self::assertIsInt($resolve);
        self::assertIsInt($deliver);
        self::assertLessThan($resolve, $context);
        // ...e os textos sao entregues antes das fontes do tema.
        self::assertLessThan(strpos($startup, '$this->template->setThemeTemplates('), $deliver);
        self::assertSame(1, substr_count($startup, 'setThemeStrings('));
    }
}
