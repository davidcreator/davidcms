<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

require_once __DIR__ . '/ExtensionTestCase.php';

use Reamur\System\Library\Extension\Manager;
use Reamur\System\Library\Extension\Manifest;
use Reamur\System\Library\Extension\PageOutput;

/**
 * EX-01 (ADR 0095): a extensao que acompanha a etapa ("Verificacao de site")
 * rodando pela pasta de verdade, e as ligacoes do mecanismo nos arquivos que
 * vao para o servidor (as que um refactor apagaria sem nenhum teste notar).
 */
final class ShippedAndWiringTest extends ExtensionTestCase {
    private static function repo(): string {
        return dirname(__DIR__, 3) . '/';
    }

    private static function read(string $path): string {
        $text = file_get_contents(self::repo() . $path);
        self::assertIsString($text, $path);

        return $text;
    }

    // ── a pasta que vai para o servidor ─────────────────────────────────────

    public function testEveryShippedExtensionIsValidAndCompatibleWithThisCms(): void {
        [$registry] = $this->registry('admin');

        preg_match("/define\\('REAMUR_VERSION',\\s*'([^']+)'\\)/", self::read('dev/catalog/index.php'), $m);
        self::assertNotEmpty($m[1] ?? '', 'versao do CMS em catalog/index.php');

        $manager = $this->manager($registry, self::repo() . 'dev/extension/', $m[1], '8.2.0');
        $found   = $manager->discover();

        self::assertArrayHasKey('site_verification', $found);

        foreach ($found as $code => $entry) {
            self::assertSame([], $entry['problems'], $code);
            self::assertInstanceOf(Manifest::class, $entry['manifest'], $code);
            self::assertSame([], $entry['manifest']->incompatibilities($m[1], '8.2.0'), $code);
            self::assertFileExists(self::repo() . 'dev/extension/' . $code . '/extension.php');
            self::assertFileExists(self::repo() . 'dev/extension/' . $code . '/index.html');

            foreach (['en-us', 'pt-br', 'es-eu'] as $language) {
                self::assertNotSame('', $entry['manifest']->name($language), $code . ' ' . $language);
            }
        }
    }

    public function testExtensionFolderIsClosedToTheBrowser(): void {
        $htaccess = self::read('dev/extension/.htaccess');

        self::assertMatchesRegularExpression('/^Require all denied$/m', $htaccess);
        self::assertDoesNotMatchRegularExpression('/^\s*(Allow|Require all granted|<Files)/mi', $htaccess);
        self::assertFileExists(self::repo() . 'dev/extension/index.html');
    }

    public function testNoShippedExtensionIsEnabledByTheInstaller(): void {
        // Extensao so liga pela tela: nem o instalador nem o esquema gravam "<codigo>.enabled".
        foreach (['dev/install/sql/schema-complete.sql', 'dev/install/model/install/install.php'] as $file) {
            self::assertDoesNotMatchRegularExpression("/'extension',\\s*'[a-z0-9_]+\\.enabled'/", self::read($file), $file);
        }
    }

    // ── "Verificacao de site" ───────────────────────────────────────────────

    /** @param array<string, mixed> $config */
    private function runSiteVerification(array $config, bool $enabled = true): array {
        [$registry, $db, $log] = $this->registry();
        $db->put('site_verification.enabled', $enabled ? '1' : '0');
        $db->put('site_verification.config', (string)json_encode($config));

        $before  = $registry->get('csp')->policy();
        $manager = $this->manager($registry, self::repo() . 'dev/extension/');
        $manager->boot('catalog');

        $page = new PageOutput('common/home', 'pt-br');
        $args = [$page];
        $registry->get('event')->trigger(Manager::EVENT_PAGE_OUTPUT, $args);

        return [$page, $manager, $log, $before === $registry->get('csp')->policy(), $registry];
    }

    public function testSiteVerificationWritesOnlyTheFilledTags(): void {
        [$page, $manager, $log, $samePolicy] = $this->runSiteVerification(['google' => 'abcDEF_123-xyz', 'pinterest' => '0123456789abcdef']);

        self::assertSame(['site_verification'], $manager->running());
        self::assertSame(
            '<meta name="google-site-verification" content="abcDEF_123-xyz">' . "\n"
            . '<meta name="p:domain_verify" content="0123456789abcdef">' . "\n",
            $page->render(PageOutput::HEAD, 'n')
        );
        self::assertSame('', $page->render(PageOutput::BODY_START, 'n'));
        self::assertSame([], $page->rejected());
        self::assertSame([], $log->lines);
        self::assertTrue($samePolicy, 'nao pede origem nenhuma');
    }

    public function testSiteVerificationWithAllFourCodes(): void {
        [$page] = $this->runSiteVerification(['google' => 'g1234567', 'bing' => 'B1234567', 'pinterest' => 'p1234567', 'meta' => 'm1234567']);

        self::assertSame(
            '<meta name="google-site-verification" content="g1234567">' . "\n"
            . '<meta name="msvalidate.01" content="B1234567">' . "\n"
            . '<meta name="p:domain_verify" content="p1234567">' . "\n"
            . '<meta name="facebook-domain-verification" content="m1234567">' . "\n",
            $page->render(PageOutput::HEAD, 'n')
        );
    }

    public function testSiteVerificationEnabledWithoutCodesWritesNothing(): void {
        [$page, $manager, $log] = $this->runSiteVerification([]);

        self::assertSame(['site_verification'], $manager->running());
        self::assertSame('', $page->render(PageOutput::HEAD, 'n'));
        self::assertSame([], $log->lines);
    }

    public function testSiteVerificationDisabledWritesNothing(): void {
        [$page, $manager, , , $registry] = $this->runSiteVerification(['google' => 'abcDEF_123-xyz'], false);

        self::assertSame([], $manager->running());
        self::assertSame('', $page->render(PageOutput::HEAD, 'n'));
        self::assertFalse($registry->get('event')->has(Manager::EVENT_PAGE_OUTPUT));
    }

    public function testSiteVerificationIgnoresAValueTamperedInTheDatabase(): void {
        // Valor que nao passaria pelo formulario (colado direto no banco) nao chega a pagina.
        [$page] = $this->runSiteVerification(['google' => '"><script>alert(1)</script>', 'bing' => 'curto', 'meta' => ['x'], 'pinterest' => 'ok_12345']);

        self::assertSame('<meta name="p:domain_verify" content="ok_12345">' . "\n", $page->render(PageOutput::HEAD, 'n'));
    }

    public function testSiteVerificationFormRefusesAWholeTagPastedIn(): void {
        $manifest = Manifest::read(self::repo() . 'dev/extension/', 'site_verification');

        $result = $manifest->validate(['google' => '<meta name="google-site-verification" content="abc">', 'bing' => 'B1234567']);

        self::assertSame(['google' => 'pattern'], $result['errors']);
        self::assertSame(['catalog'], $manifest->apps());
        self::assertSame([], $manifest->csp());
        self::assertSame([], $manifest->secretKeys());
        self::assertSame([['trigger' => 'page/output', 'handler' => 'onPageOutput', 'priority' => 0]], $manifest->events());
    }

    // ── ligacoes ────────────────────────────────────────────────────────────

    public function testBothAppsStartExtensionsAsTheLastPreAction(): void {
        foreach (['catalog', 'admin'] as $app) {
            preg_match("/\\\$_\\['action_pre_action'\\]\\s*=\\s*\\[([^\\]]*)\\]/", self::read('dev/system/config/' . $app . '.php'), $m);
            preg_match_all("/'([a-z\\/_]+)'/", $m[1] ?? '', $routes);

            self::assertSame('startup/extension', end($routes[1]), $app . ': depois das configuracoes, do idioma e (no admin) do login e da permissao');
            self::assertFileExists(self::repo() . 'dev/' . $app . '/controller/startup/extension.php');
        }

        $admin = $routesAdmin = null;
        preg_match("/\\\$_\\['action_pre_action'\\]\\s*=\\s*\\[([^\\]]*)\\]/", self::read('dev/system/config/admin.php'), $m);
        self::assertLessThan(strpos($m[1], 'startup/extension'), strpos($m[1], 'startup/permission'));
        self::assertLessThan(strpos($m[1], 'startup/extension'), strpos($m[1], 'startup/login'));
    }

    public function testFrameworkCreatesTheServiceWithoutReadingTheFolder(): void {
        $framework = self::read('dev/system/framework.php');

        self::assertStringContainsString("\$registry->set('extensions', new \\Reamur\\System\\Library\\Extension\\Manager(", $framework);
        self::assertStringNotContainsString('->boot(', $framework, 'quem liga e a pre-acao, depois das configuracoes');
        self::assertStringNotContainsString('->discover(', $framework);
    }

    public function testMasterSwitchComesFromTheConfigFileConstant(): void {
        $default = self::read('dev/system/config/default.php');

        self::assertStringContainsString("\$_['extension_off']", $default);
        self::assertStringContainsString("defined('REAMUR_EXTENSIONS')", $default);
        self::assertStringContainsString('Manager::switchedOff(REAMUR_EXTENSIONS)', $default);
        self::assertStringContainsString('REAMUR_EXTENSIONS', self::read('dev/install/config.php.tpl'), 'a linha esta documentada no modelo de config.php');
    }

    public function testAdminNeverRunsExtensionsOnTheScreensThatTurnThemOff(): void {
        $quiet = \Reamur\Admin\Controller\Startup\Extension::QUIET_ROUTES;

        foreach (['extension/extension', 'common/login', 'common/logout'] as $route) {
            self::assertContains($route, $quiet);
        }
    }

    /** @return array<string, array{0: string, 1: bool, 2: bool}> rota, logado, deve ligar */
    public static function adminRoutes(): array {
        return [
            'painel'                         => ['common/dashboard', true, true],
            'rota vazia = painel'            => ['', true, true],
            'outra tela'                     => ['sale/order.edit|5', true, true],
            'tela de extensoes'              => ['extension/extension', true, false],
            'tela de extensoes, acao'        => ['extension/extension.disable|site_verification', true, false],
            'tela de extensoes, formulario'  => ['extension/extension.edit|x', true, false],
            'login'                          => ['common/login', true, false],
            'sair'                           => ['common/logout', true, false],
            'sem permissao'                  => ['error/permission', true, false],
            'visitante no painel'            => ['common/dashboard', false, false],
            'visitante em qualquer tela'     => ['sale/order', false, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminRoutes')]
    public function testAdminPreActionChoosesWhenExtensionsRun(string $route, bool $logged, bool $expected): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, ['apps' => ['admin']]), '');

        [$registry, $db] = $this->registry('admin');
        $db->put($code . '.enabled', '1');
        $registry->get('config')->set('_route', $route);
        $registry->get('config')->set('action_default', 'common/dashboard');
        $registry->set('user', new class($logged) {
            public function __construct(private bool $logged) {}

            public function isLogged(): bool {
                return $this->logged;
            }
        });

        $manager = $this->manager($registry, $folder);
        (new \Reamur\Admin\Controller\Startup\Extension($registry))->index();

        self::assertSame($expected ? [$code] : [], $manager->running());

        if (!$expected) {
            self::assertFalse(class_exists(self::classOf($code), false), 'o PHP da extensao nem foi carregado');
        }
    }

    public function testCatalogPreActionStartsTheEnabledOnes(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code), '');

        [$registry, $db] = $this->registry();
        $db->put($code . '.enabled', '1');

        $manager = $this->manager($registry, $folder);
        (new \Reamur\Catalog\Controller\Startup\Extension($registry))->index();

        self::assertSame([$code], $manager->running());
    }

    public function testPreActionsWithoutTheServiceDoNothing(): void {
        [$registry] = $this->registry();

        (new \Reamur\Catalog\Controller\Startup\Extension($registry))->index();
        (new \Reamur\Admin\Controller\Startup\Extension($registry))->index();

        $this->addToAssertionCount(1);
    }

    public function testPublicHeaderIsTheOnlyPlaceThatPrintsExtensionOutput(): void {
        $controller = self::read('dev/catalog/controller/common/header.php');
        $twig       = self::read('dev/catalog/view/template/common/header.twig');

        self::assertStringContainsString("\$data['extension_head']       = '';", $controller, 'sem extensao, a variavel existe e esta vazia');
        self::assertStringContainsString('Manager::EVENT_PAGE_OUTPUT', $controller);
        self::assertStringContainsString("\$page->render(\$page::HEAD, (string)\$data['csp_nonce'])", $controller);
        self::assertStringContainsString('extension output refused, ', $controller);

        self::assertSame(1, substr_count($twig, '{{ extension_head|raw }}'));
        self::assertSame(1, substr_count($twig, '{{ extension_body_start|raw }}'));
        self::assertLessThan(strpos($twig, '</head>'), strpos($twig, '{{ extension_head|raw }}'));
        self::assertGreaterThan(strpos($twig, '<body'), strpos($twig, '{{ extension_body_start|raw }}'));

        // Nenhum outro template (do nucleo ou de tema) recebe saida de extensao.
        $others = [];
        $files  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::repo() . 'dev/catalog/view', \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() === 'twig' && str_contains((string)file_get_contents($file->getPathname()), 'extension_')) {
                $others[] = substr($file->getPathname(), strlen(self::repo()));
            }
        }

        self::assertSame(['dev/catalog/view/template/common/header.twig'], $others);
    }

    public function testAdminNeverPrintsExtensionOutput(): void {
        // As previas do admin (pagina, blog) montam o cabecalho publico por conta
        // propria: nao disparam page/output nem preenchem as variaveis de extensao.
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::repo() . 'dev/admin', \FilesystemIterator::SKIP_DOTS));
        $hits  = [];

        foreach ($files as $file) {
            if (!in_array($file->getExtension(), ['php', 'twig'], true)) {
                continue;
            }

            $text = (string)file_get_contents($file->getPathname());

            if (str_contains($text, 'extension_head') || str_contains($text, 'extension_body_start') || str_contains($text, 'EVENT_PAGE_OUTPUT')) {
                $hits[] = substr($file->getPathname(), strlen(self::repo()));
            }
        }

        self::assertSame([], $hits);

        $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader([
            't' => "A\n{% if extension_head|default('') %}{{ extension_head|raw }}{% endif %}\nB",
        ]), ['autoescape' => 'html', 'strict_variables' => true]);

        self::assertSame("A\nB", $twig->render('t', ['private_preview' => true]), 'sem a variavel, nem uma linha em branco');
        self::assertSame("A\n<meta name=\"a\" content=\"b\">\nB", $twig->render('t', ['extension_head' => "<meta name=\"a\" content=\"b\">\n"]));
        self::assertStringContainsString("{% if extension_head|default('') %}{{ extension_head|raw }}{% endif %}", self::read('dev/catalog/view/template/common/header.twig'));
        self::assertStringContainsString("{% if extension_body_start|default('') %}{{ extension_body_start|raw }}{% endif %}", self::read('dev/catalog/view/template/common/header.twig'));
    }

    public function testOrderEventsReachExtensionsAfterTheWebhooks(): void {
        $controller = self::read('dev/system/engine/controller.php');

        $webhooks   = strpos($controller, '$this->deliverWebhooks($event, $resolve, $load, $log);');
        $extensions = strpos($controller, '$events->trigger($event, $args);');

        self::assertNotFalse($webhooks);
        self::assertNotFalse($extensions);
        self::assertLessThan($extensions, $webhooks);
        self::assertStringContainsString('$events->has($event)', $controller, 'sem ouvinte, os dados nem sao montados');
    }

    // ── permissao da tela ───────────────────────────────────────────────────

    public function testNewInstallAndUpgradeGrantTheScreenToAdministrators(): void {
        self::assertMatchesRegularExpression("/\\\$seedRoutes\\s*=\\s*\\[[^\\]]*'extension\\/extension'/s", self::read('dev/install/model/install/install.php'));

        $upgrade  = self::read('dev/install/sql/upgrade_ex01_extension_grant.sql');
        $rollback = self::read('dev/install/sql/rollback_ex01_extension_grant.sql');
        $schema   = self::read('dev/install/sql/schema-complete.sql');

        $statements = static fn (string $sql): string => trim((string)preg_replace('/\s+/', ' ', (string)preg_replace('/^--.*$/m', '', $sql)));

        self::assertStringContainsString($statements($upgrade), $statements($schema), 'schema-complete.sql traz os mesmos comandos do upgrade');

        foreach (['access', 'modify'] as $kind) {
            self::assertStringContainsString("JSON_ARRAY_APPEND(`permission`, '$." . $kind . "', 'extension/extension')", $upgrade);
            self::assertStringContainsString("JSON_SEARCH(JSON_EXTRACT(`permission`, '$." . $kind . "'), 'one', 'extension/extension') IS NULL", $upgrade, 'idempotente');
            self::assertStringContainsString("'$." . $kind . "'", $rollback);
        }

        self::assertDoesNotMatchRegularExpression('/\b(CREATE|ALTER|DROP)\s+TABLE\b/i', $upgrade, 'so ACL: sem mudanca de esquema');
        self::assertDoesNotMatchRegularExpression('/\b(CREATE|ALTER|DROP)\s+TABLE\b/i', $rollback);
        self::assertDoesNotMatchRegularExpression('/^\s*DELETE\b/mi', $rollback, 'desfazer a permissao nao apaga o estado das extensoes');
    }

    public function testAdminMenuHasTheEntryInTheThreeLanguages(): void {
        self::assertStringContainsString("'extension/extension'", self::read('dev/admin/controller/common/header.php'));

        foreach (['en-us', 'pt-br', 'es-eu'] as $language) {
            self::assertStringContainsString("\$_['text_menu_extensions']", self::read('dev/admin/language/' . $language . '/common/header.php'), $language);
            self::assertStringContainsString("\$_['text_secret_group_extension']", self::read('dev/admin/language/' . $language . '/setting/setting.php'), $language);
        }
    }

    public function testScreenTemplatesHaveNoInlineScriptOrStyle(): void {
        foreach (['extension_list', 'extension_form'] as $template) {
            $twig = self::read('dev/admin/view/template/extension/' . $template . '.twig');

            self::assertStringNotContainsString('<script', $twig, $template);
            self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $twig, $template . ': sem atributo de evento');
            self::assertStringNotContainsString('style=', $twig, $template);
            self::assertSame(2, substr_count($twig, '|raw'), $template . ': so header e footer');
        }
    }

    public function testProductionBuildCarriesTheExtensionFolder(): void {
        self::assertStringContainsString('extension/', self::read('tools/build_prod.sh'));
    }
}
