<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Design\Theme as AdminThemeController;
use Reamur\Catalog\Controller\Startup\Theme as CatalogStartupTheme;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Cache\File as FileCache;
use Reamur\System\Library\Document;
use Reamur\System\Library\Language;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Session;
use Reamur\System\Library\Template\ThemeSandbox;
use Reamur\System\Library\Template\Twig;
use Reamur\System\Library\Theme\ConditionEngine;
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\TokenRegistry;
use Reamur\System\Library\Url;

/**
 * S2 (ADR 0087): fonte Twig de tema roda em sandbox TAMBEM no site publicado.
 *
 * Antes, Twig::setThemeTemplates() punha as fontes num Environment comum,
 * protegido so por uma regex: `constant()` lia constantes do PHP (senha do
 * banco) e `map('system')` executava comando. Os testes usam chamadas
 * inofensivas (`strtoupper`, DB_PREFIX) para provar o mesmo caminho.
 */
final class ThemeSandboxPublishedTest extends TestCase {
    private const MARK = 'kzmark';

    /** @return array<string, array{0: string}> fontes que NAO podem produzir efeito no site publicado */
    public static function hostileSources(): array {
        return [
            'constante do PHP'             => ["{{ constant('DB_PREFIX') }}" . self::MARK],
            'callable em map'              => ["{{ ['" . self::MARK . "']|map('strtoupper')|join }}"],
            'callable em filter'           => ["{{ ['" . self::MARK . "']|filter('strlen')|join }}"],
            'callable em sort'             => ["{{ ['" . self::MARK . "', 'a']|sort('strcmp')|join }}"],
            'callable em reduce'           => ["{{ ['" . self::MARK . "']|reduce('max', 0) }}" . self::MARK],
            'arrow function'               => ["{{ ['" . self::MARK . "']|map(v => v)|join }}"],
            'source() le arquivo'          => ["{{ source('common/header.twig')|length }}" . self::MARK],
            'include() como funcao'        => ["{{ include('common/footer.twig') }}" . self::MARK],
            'raw escondido por comentario' => ["{{ unsafe|# c\n raw }}" . self::MARK],
            'filtro fora da lista'         => ["{{ '" . self::MARK . "'|replace({'k': 'K'}) }}"],
            'chaves do contexto'           => ["{{ _context|keys|join(',') }}" . self::MARK],
            'tag apply'                    => ["{% apply upper %}" . self::MARK . "{% endapply %}"],
            'tag do'                       => ["{% do 1 %}" . self::MARK],
            'tag import'                   => ["{% import 'common/footer.twig' as m %}" . self::MARK],
            'tag use'                      => ["{% use 'common/footer.twig' %}" . self::MARK],
            'tag sandbox'                  => ["{% sandbox %}{% include 'common/footer.twig' %}{% endsandbox %}" . self::MARK],
            'funcao attribute'             => ["{{ attribute(theme, 'primary') }}" . self::MARK],
            'funcao block'                 => ["{% block a %}x{% endblock %}{{ block('a') }}" . self::MARK],
            'funcao do shell confiavel'    => ["{{ theme_partial('partial/about', {}) }}" . self::MARK],
            'inclui o shell confiavel'     => ["{% include 'common/header.twig' with {cms_route_content: unsafe} %}" . self::MARK],
        ];
    }

    #[DataProvider('hostileSources')]
    public function testHostileSourceFallsBackToTheDefaultTemplateOnThePublishedSite(string $source): void {
        $data     = $this->notFoundData();
        $expected = (new Twig('', false))->render('error/not_found', $data);

        $reports = [];
        $twig    = new Twig('', false);
        $twig->setThemeTemplates(['error/not_found' => $source], static function (string $key, string $message) use (&$reports): void {
            $reports[] = [$key, $message];
        });

        $html = $twig->render('error/not_found', $data);

        self::assertSame($expected, $html, 'a pagina tem de ser o template padrao, inteiro');
        self::assertStringNotContainsStringIgnoringCase(self::MARK, $html);
        self::assertStringNotContainsString('rms_', $html);
        self::assertStringNotContainsString('<b>unsafe</b>', $html);
        self::assertNotSame([], $reports, 'a troca pelo padrao tem de ser reportada');
        self::assertSame('error/not_found', $reports[0][0]);
        self::assertNotSame('', $reports[0][1]);
    }

    #[DataProvider('hostileSources')]
    public function testHostileSourceIsRefusedWhenSavingOrImporting(string $source): void {
        $violation = ThemeSandbox::check($source);

        // 'inclui o shell confiavel' e Twig valido e dentro da politica: so falha
        // quando o include roda. Fica para o sandbox do render.
        if (str_contains($source, "{% include 'common/header.twig'")) {
            self::assertNull($violation);

            return;
        }

        self::assertIsArray($violation, 'a fonte teria sido salva');
        self::assertNotSame('', $violation['message']);
        self::assertGreaterThanOrEqual(1, $violation['line']);
    }

    public function testValidThemeSourceStillRendersWithDataAndGlobals(): void {
        $twig = new Twig('', false);
        $twig->setThemeGlobals(['theme_brand' => 'Atlas']);
        $twig->setThemeTemplates(['error/not_found' => "<h1>{{ heading_title|upper }}</h1><p>{{ theme_brand }}</p>{% for n in range(1, 3) %}{{ n }}{% endfor %}{{ unsafe }}"]);

        $html = $twig->render('error/not_found', $this->notFoundData());

        self::assertSame('<h1>NOT FOUND</h1><p>Atlas</p>123&lt;b&gt;unsafe&lt;/b&gt;', $html);
    }

    public function testOnlyTheOffendingSourceIsReplacedTheRestOfTheThemeStays(): void {
        $reports = [];
        $twig    = new Twig('', false);
        $twig->setThemeTemplates([
            'common/home'   => "<main>{% include 'partial/hero-3d.twig' %}|{% include 'partial/about.twig' %}</main>",
            'partial/hero-3d' => '<section id="tema-hero">{{ text_about_title }}</section>',
            'partial/about' => "<section id=\"tema-about\">{{ constant('DB_PREFIX') }}</section>",
        ], static function (string $key, string $message) use (&$reports): void {
            $reports[$key] = $message;
        });

        $html = $twig->render('common/home', ['text_about_title' => 'Quem sou', 'text_about_kicker' => 'Sobre', 'about_paragraphs' => []]);

        self::assertStringContainsString('<section id="tema-hero">Quem sou</section>', $html);
        self::assertStringNotContainsString('tema-about', $html);
        self::assertStringNotContainsString('rms_', $html);
        self::assertStringContainsString('<section class="section" id="about">', $html, 'o trecho padrao entra no lugar');
        self::assertSame(['partial/about'], array_keys($reports));
        self::assertStringContainsString('constant', $reports['partial/about']);
        self::assertStringContainsString('line 1', $reports['partial/about']);

        // Segunda pagina na mesma requisicao: a fonte ruim ja saiu, sem novo aviso.
        $twig->render('common/home', ['about_paragraphs' => []]);
        self::assertCount(1, $reports);
    }

    public function testObjectsInTheContextAreNotCallableFromThemeSource(): void {
        $probe = new class {
            public string $secret = 'kzmark-prop';
            public function secret(): string { return 'kzmark-method'; }
            public function __toString(): string { return 'kzmark-string'; }
        };

        foreach (['{{ probe.secret }}', '{{ probe.secret() }}', '{{ probe }}', '{{ [probe]|join }}'] as $source) {
            $twig = new Twig('', false);
            $twig->setThemeTemplates(['error/not_found' => 'x' . $source]);

            $html = $twig->render('error/not_found', $this->notFoundData() + ['probe' => $probe]);

            self::assertStringNotContainsString(self::MARK, $html, $source);
        }
    }

    public function testTrustedHtmlFromTheSystemStillReachesThemeSource(): void {
        // O adaptador marca cms_page_content como HTML confiavel (Twig\Markup): tem de continuar saindo sem escape.
        $twig = new Twig('', false);
        $twig->setThemeTemplates(['error/not_found' => '<div>{{ cms_page_content }}</div><div>{{ unsafe }}</div>']);

        $html = $twig->render('error/not_found', $this->notFoundData() + ['cms_page_content' => '<p>ok</p>']);

        self::assertSame('<div><p>ok</p></div><div>&lt;b&gt;unsafe&lt;/b&gt;</div>', $html);
    }

    public function testTrustedHeaderRendersTheThemeBarThroughTheSandbox(): void {
        $data = $this->headerData();

        $twig = new Twig('', false);
        $twig->setThemeTemplates(['partial/site_header' => '<header id="tema-bar">{{ text_brand }}|{{ cms_route_content|default("sem-contexto") }}|{{ title|default("sem-titulo") }}</header>']);
        $html = $twig->render('common/header', $data);

        // A barra do tema aparece, recebe so as variaveis listadas (como o antigo `include ... only`)...
        self::assertStringContainsString('<header id="tema-bar">Marca|sem-contexto|sem-titulo</header>', $html);
        // ...e o shell confiavel continua imprimindo o HTML do CMS sem escape.
        self::assertStringContainsString('<p class="cms">rota</p>', $html);
        self::assertStringContainsString('<title>Titulo</title>', $html);
    }

    public function testHostileThemeBarFallsBackToTheDefaultBarAndTheHeaderSurvives(): void {
        $data     = $this->headerData();
        $expected = (new Twig('', false))->render('common/header', $data);

        $reports = [];
        $twig    = new Twig('', false);
        $twig->setThemeTemplates(['partial/site_header' => "<header id=\"tema-bar\">{{ ['" . self::MARK . "']|map('strtoupper')|join }}</header>"], static function (string $key, string $message) use (&$reports): void {
            $reports[] = $key;
        });

        self::assertSame($expected, $twig->render('common/header', $data));
        self::assertSame(['partial/site_header'], $reports);
    }

    public function testThemeCannotReplaceTheTrustedShell(): void {
        $data     = $this->headerData();
        $expected = (new Twig('', false))->render('common/header', $data);

        $twig = new Twig('', false);
        $twig->setThemeTemplates(['common/header' => 'tomado', 'common/footer' => '<footer id="tema-footer"></footer>']);

        self::assertSame($expected, $twig->render('common/header', $data));
        self::assertSame('<footer id="tema-footer"></footer>', $twig->render('common/footer', []));
    }

    public function testShellRenderedFirstDoesNotLendItsCompiledClassToTheSandbox(): void {
        // O shell confiavel (que usa |raw) e compilado ANTES; a fonte do tema o inclui depois.
        // Se o sandbox reaproveitasse a classe ja compilada, o |raw rodaria com dado do tema.
        $twig = new Twig('', false);
        $twig->render('common/header', $this->headerData());
        $twig->setThemeTemplates(['error/not_found' => "{% include 'common/header.twig' with {cms_route_content: unsafe} %}" . self::MARK]);

        $html = $twig->render('error/not_found', $this->notFoundData());

        self::assertStringNotContainsString('<b>unsafe</b>', $html);
        self::assertStringNotContainsString(self::MARK, $html);
    }

    public function testThemeWithoutSourcesKeepsThePlainRender(): void {
        $data  = $this->notFoundData();
        $plain = (new Twig('', false))->render('error/not_found', $data);

        $twig = new Twig('', false);
        $twig->setThemeTemplates([]);
        self::assertSame($plain, $twig->render('error/not_found', $data));

        // Chave fora da allowlist e valor que nao e texto nao viram fonte de tema.
        $twig->setThemeTemplates(['../../etc/passwd' => 'x', 'common/header' => 'x', 'error/not_found' => ['x'], 7 => 'x']);
        self::assertSame($plain, $twig->render('error/not_found', $data));

        // Fonte vazia continua valendo (o tema esvazia o trecho), como antes da ADR 0087.
        $twig->setThemeTemplates(['error/not_found' => '']);
        self::assertSame('', $twig->render('error/not_found', $data));

        // Publicar um tema sem fontes depois de um com fontes volta ao padrao.
        $twig->setThemeTemplates(['error/not_found' => 'tema']);
        self::assertSame('tema', $twig->render('error/not_found', $data));
        $twig->setThemeTemplates([]);
        self::assertSame($plain, $twig->render('error/not_found', $data));
    }

    public function testHtmlLayerStillRejectsScriptAndHandlers(): void {
        $data  = $this->notFoundData();
        $plain = (new Twig('', false))->render('error/not_found', $data);

        foreach (['<script>alert(1)</script>', '<a onclick="x()">a</a>', '<a href="javascript:x()">a</a>', '<iframe src="x"></iframe>', '{{ unsafe|raw }}', '{% autoescape false %}{{ unsafe }}{% endautoescape %}'] as $source) {
            $reports = [];
            $twig    = new Twig('', false);
            $twig->setThemeTemplates(['error/not_found' => $source], static function (string $key, string $message) use (&$reports): void {
                $reports[] = $message;
            });

            self::assertSame($plain, $twig->render('error/not_found', $data), $source);
            self::assertSame(['Source rejected by the theme allowlist.'], $reports, $source);
            self::assertIsArray(ThemeSandbox::check($source), $source);
        }
    }

    public function testCmsPagePreviewInTheAdminUsesTheSameSandbox(): void {
        $twig   = new Twig('', false);
        $page   = ['heading_title' => 'Pagina', 'description' => '<p>corpo</p>', 'cms_page_sections' => ['content'], 'unsafe' => '<b>unsafe</b>'];
        $header = $this->headerData();
        $plain  = $twig->renderCatalogPagePreview($page, $header, []);

        $hostile = $twig->renderCatalogPagePreview($page, $header, [], [
            'cms/page_info'       => "{{ constant('DB_PREFIX') }}" . self::MARK,
            'common/footer'       => "{{ ['" . self::MARK . "']|map('strtoupper')|join }}",
            'partial/site_header' => "{{ source('common/header.twig') }}" . self::MARK,
        ]);

        self::assertSame($plain, $hostile);
        self::assertStringNotContainsStringIgnoringCase(self::MARK, $hostile);
        self::assertStringNotContainsString('rms_', $hostile);

        $themed = $twig->renderCatalogPagePreview($page, $header, [], [
            'cms/page_info'       => '{{ header }}<article id="tema-page">{{ heading_title }}{{ description }}{{ unsafe }}</article>{{ footer }}',
            'common/footer'       => '<footer id="tema-footer"></footer>',
            'partial/site_header' => '<header id="tema-bar">{{ text_brand }}</header>',
        ]);

        self::assertStringContainsString('<header id="tema-bar">Marca</header>', $themed);
        self::assertStringContainsString('<article id="tema-page">Pagina<p>corpo</p>&lt;b&gt;unsafe&lt;/b&gt;</article>', $themed);
        self::assertStringContainsString('<footer id="tema-footer"></footer>', $themed);
    }

    public function testEditorPreviewCanvasAndPublishedSiteShareOnePolicy(): void {
        $twig = new Twig('', false);

        // Dentro da politica: os tres caminhos renderizam.
        $ok = "{{ 'abcdef'|slice(0, 3) }}{{ \"a\\nb\"|nl2br }}{% if heading_title is defined %}d{% endif %}";
        self::assertSame('abca<br />' . "\n" . 'bd', $twig->renderSandboxPreview('error/not_found', $ok));
        $canvas = $twig->renderThemePage('error/not_found', ['error/not_found' => $ok], $this->notFoundData());
        self::assertSame([], $canvas['errors']);
        $twig->setThemeTemplates(['error/not_found' => $ok]);
        self::assertSame('abca<br />' . "\n" . 'bd', $twig->render('error/not_found', $this->notFoundData()));

        // Fora da politica: os tres caminhos recusam.
        $bad = "{{ 'x'|replace({'x': 'y'}) }}";
        try {
            $twig->renderSandboxPreview('error/not_found', $bad);
            self::fail('a previa aceitou um filtro fora da politica');
        } catch (\Twig\Sandbox\SecurityError $e) {
            self::assertStringContainsString('replace', $e->getMessage());
        }
        $canvas = $twig->renderThemePage('error/not_found', ['error/not_found' => $bad], $this->notFoundData());
        self::assertStringContainsString('replace', $canvas['errors']['error/not_found'] ?? '');
        self::assertContains('error/not_found', $canvas['fallbacks']);
        self::assertIsArray(ThemeSandbox::check($bad));
    }

    public function testCheckReportsTheLineAndLeavesSyntaxErrorsToTheEditor(): void {
        self::assertNull(ThemeSandbox::check('<p>{{ title|default("x")|upper }}</p>{% for i in range(1, 2) %}{{ cycle(["a", "b"], i) }}{% endfor %}'));
        self::assertNull(ThemeSandbox::check(''));
        self::assertNull(ThemeSandbox::check('{% if x %}rascunho sem fechar'), 'erro de sintaxe nao e violacao: nao executa');

        $violation = ThemeSandbox::check("<p>um</p>\n<p>dois</p>\n{{ constant('X') }}");
        self::assertSame(['message' => 'Function "constant" is not allowed.', 'line' => 3], $violation);

        $violation = ThemeSandbox::check("ok\n<a onclick=\"x()\">a</a>");
        self::assertIsArray($violation);
        self::assertSame(2, $violation['line']);

        $violation = ThemeSandbox::check(str_repeat('a', ThemeSandbox::MAX_SOURCE + 1));
        self::assertSame(['message' => 'Source exceeds the allowed size.', 'line' => 0], $violation);
    }

    public function testBundledPresetsAndDefaultTemplatesFitThePolicy(): void {
        $checked = 0;

        foreach ((array)glob(DIR_REAMUR . 'docs/temas/*.reamurtheme.json') as $file) {
            $package   = json_decode((string)file_get_contents((string)$file), true);
            $templates = is_array($package) ? ($package['config']['templates'] ?? []) : [];
            self::assertNotSame([], $templates, basename((string)$file));

            foreach ($templates as $key => $source) {
                self::assertNull(ThemeSandbox::check((string)$source), basename((string)$file) . ' ' . $key);
                $checked++;
            }
        }

        foreach (ThemePackage::TEMPLATE_KEYS as $key) {
            $source = (string)file_get_contents(DIR_TEMPLATE . $key . '.twig');
            self::assertNotSame('', $source, $key);

            // O editor preenche cada <textarea> com o template padrao: se um deles
            // reprovasse, salvar um tema sem tocar nas fontes falharia.
            self::assertNull(ThemeSandbox::check($source), $key);
            $checked++;
        }

        self::assertGreaterThanOrEqual(150 + count(ThemePackage::TEMPLATE_KEYS), $checked);
    }

    public function testImportedPackageWithHostileSourceIsRejected(): void {
        $packages = new ThemePackage(new ConditionEngine(), new TokenRegistry());
        $json     = (string)file_get_contents(DIR_REAMUR . 'docs/temas/Reamur-Reference.reamurtheme.json');

        $clean = $packages->decode($json);
        self::assertTrue($clean['ok'], implode(',', $clean['errors']));

        foreach (["{{ constant('DB_PASSWORD') }}", "{{ ['id']|map('system')|join }}", "{{ x|# c\n raw }}"] as $source) {
            $package = json_decode($json, true);
            $package['config']['templates']['partial/about'] = $source;
            $package['checksum'] = $packages->checksum($package['config']);

            $result = $packages->decode($packages->encode($package));

            self::assertFalse($result['ok'], $source);
            self::assertSame(['template_source_invalid'], $result['errors'], $source);
            self::assertSame([], $result['config']);
        }
    }

    public function testEditorSaveRefusesTheSourceWithFontAndLineButTheCanvasDraftKeepsIt(): void {
        $registry = $this->themeRegistry();

        $theme = new AdminThemeController($registry);
        $build = new \ReflectionMethod($theme, 'buildDraftConfig');
        $post  = [
            'name' => 'Tema', 'slug' => 'tema', 'primary' => '#2563eb', 'surface' => '#ffffff',
            'templates' => ['partial/hero' => '<h1>{{ heading_title }}</h1>', 'partial/about' => "<p>um</p>\n<p>{{ ['id']|map('system')|join }}</p>"],
        ];

        // Salvar: reprovado, com a fonte e a linha.
        $saved = $build->invoke($theme, $post, true);
        self::assertSame('Source blocked. Nothing was saved. Source partial/about, line 2: Filter "map" is not allowed.', $saved['error']);
        self::assertSame([], $saved['config']);

        // Canvas e comparacao: a fonte segue no rascunho (o sandbox do canvas a troca
        // pelo padrao e mostra o erro no proprio trecho).
        $draft = $build->invoke($theme, $post);
        self::assertSame('', $draft['error']);
        self::assertSame($post['templates'], $draft['config']['templates']);

        // Dentro da politica: salvar passa.
        unset($post['templates']['partial/about']);
        $saved = $build->invoke($theme, $post, true);
        self::assertSame('', $saved['error']);
        self::assertSame($post['templates'], $saved['config']['templates']);

        // A camada de HTML continua valendo nos dois modos, agora com o detalhe.
        $post['templates']['partial/about'] = "ok\n<script>x</script>";
        foreach ([true, false] as $checkPolicy) {
            $result = $build->invoke($theme, $post, $checkPolicy);
            self::assertSame('Source blocked. Nothing was saved. Source partial/about, line 2: Blocked construct: <script', $result['error']);
        }
    }

    public function testRefusedSaveKeepsTheEditedSourcesInTheForm(): void {
        $registry = $this->themeRegistry();
        $request  = new Request();
        $response = new Response();
        $saved    = ['partial/about' => '<p>salvo</p>', 'partial/hero' => '<h1>salvo</h1>'];

        /** @var Session $session */
        $session = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $session->data = [];

        $model = new class($saved) {
            public int $saves = 0;
            /** @param array<string, string> $templates */
            public function __construct(private array $templates) {}
            /** @return array<string, mixed> */
            public function getTheme(int $id): array { return ['theme_id' => $id, 'name' => 'Tema', 'slug' => 'tema', 'draft_config' => json_encode(['templates' => $this->templates])]; }
            public function slugExists(string $slug, int $id): bool { return false; }
            public function save(mixed ...$args): bool { $this->saves++; return true; }
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
        $registry->set('theme_snapshot', new \Reamur\System\Library\Theme\ThemeSnapshot(
            $registry->get('theme_condition_engine'), $registry->get('theme_token_registry'), $registry->get('theme_package')
        ));
        $registry->set('user', new class {
            public function hasPermission(string $type, string $route): bool { return true; }
            public function getUserId(): int { return 1; }
        });

        $theme  = new AdminThemeController($registry);
        $edited = ['partial/about' => "<p>editado</p>\n{{ constant('DB_PASSWORD') }}", 'partial/hero' => '<h1>editado</h1>'];

        $request->server['REQUEST_METHOD'] = 'POST';
        $request->post = ['csrf_token' => Csrf::token($session), 'name' => 'Tema', 'slug' => 'tema', 'primary' => '#2563eb', 'surface' => '#ffffff', 'templates' => $edited];
        $theme->edit('1');

        self::assertSame(0, $model->saves);
        self::assertStringContainsString('Source partial/about, line 2: Function "constant" is not allowed.', (string)$load->data['error']);
        // Antes o formulario voltava com as fontes SALVAS e a edicao se perdia.
        self::assertSame($edited['partial/about'], $load->data['template_sources']['partial/about']);
        self::assertSame($edited['partial/hero'], $load->data['template_sources']['partial/hero']);

        $edited['partial/about'] = '<p>editado</p>';
        $request->post['templates'] = $edited;
        $load->data = [];
        $theme->edit('1');

        self::assertSame(1, $model->saves);
        self::assertSame([], $load->data, 'save aceito redireciona, nao renderiza o formulario');
        // KZ-17 (ADR 0110): salvar continua no editor do tema, com o aviso de rascunho salvo.
        self::assertContains('Location: https://example.test/admin/index.php?route=design/theme.edit|1&saved=1', $response->getHeaders());

        // GET nao reflete POST nenhum: mostra o que esta salvo.
        $request->server['REQUEST_METHOD'] = 'GET';
        $request->post = ['templates' => ['partial/about' => 'nao deve aparecer']];
        $theme->edit('1');
        self::assertSame($saved['partial/about'], $load->data['template_sources']['partial/about']);
    }

    public function testCatalogLogsTheFallbackOncePerHourPerSource(): void {
        $dir = sys_get_temp_dir() . '/reamur-s2-' . bin2hex(random_bytes(6)) . '/';
        mkdir($dir, 0700, true);

        $log = new class {
            /** @var list<string> */
            public array $lines = [];
            public function write(string $message): void { $this->lines[] = $message; }
        };

        try {
            $registry = new Registry();
            $registry->set('log', $log);
            $registry->set('cache', new FileCache(3600, $dir));

            $report = (new \ReflectionMethod(CatalogStartupTheme::class, 'themeSourceReporter'))->invoke(new CatalogStartupTheme($registry));

            $report('partial/about', "Filter \"map\" is not allowed.\n(line 3)");
            $report('partial/about', "Filter \"map\" is not allowed.\n(line 3)");
            $report('partial/hero', 'Function "constant" is not allowed. (line 1)');

            self::assertSame([
                'Theme sandbox: source "partial/about" left out, default template used. Filter "map" is not allowed. (line 3)',
                'Theme sandbox: source "partial/hero" left out, default template used. Function "constant" is not allowed. (line 1)',
            ], $log->lines);

            // Sem cache disponivel o aviso sai sempre: melhor repetir do que calar.
            $bare = new Registry();
            $bare->set('log', $log);
            $report = (new \ReflectionMethod(CatalogStartupTheme::class, 'themeSourceReporter'))->invoke(new CatalogStartupTheme($bare));
            $report('partial/about', 'x');
            $report('partial/about', 'x');
            self::assertCount(4, $log->lines);
        } finally {
            foreach ((array)glob($dir . '*') as $file) {
                @unlink((string)$file);
            }
            @rmdir($dir);
        }
    }

    /** Registry com os servicos de tema que o framework registra, sem banco. */
    private function themeRegistry(): Registry {
        $registry = new Registry();
        $language = new Language('en-us');
        $language->set('error_validation', 'Validation error');
        $language->set('error_template_source', 'Source blocked. Nothing was saved.');
        $language->set('error_template_source_detail', 'Source %s, line %d: %s');
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

    /** @return array<string, mixed> */
    private function notFoundData(): array {
        return [
            'header' => '', 'footer' => '', 'heading_title' => 'Not found', 'text_code' => '404', 'text_error' => 'Erro',
            'text_suggestion' => 'Volte', 'home' => '/', 'button_continue' => 'Inicio', 'cms_page_sections' => ['not_found_content'],
            'nf3d' => ['count' => 10, 'color' => '#000000', 'speed' => 0.1, 'drift' => 0.2],
            'unsafe' => '<b>unsafe</b>',
        ];
    }

    /** @return array<string, mixed> */
    private function headerData(): array {
        return [
            'title' => 'Titulo', 'body_class' => 'home', 'home' => '/', 'text_brand' => 'Marca', 'text_nav_home' => 'Inicio',
            'languages' => [], 'cms_route_key' => 'common/home', 'cms_route_content' => '<p class="cms">rota</p>',
        ];
    }
}
