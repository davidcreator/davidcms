<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * S8 (ADR 0093): a politica so protege enquanto o HTML que o servidor escreve
 * couber nela. Estes testes travam a fiacao: nenhum <script> embutido sem
 * nonce, nenhum atributo on*, nenhuma politica frouxa de volta no config, e o
 * nonce fora do alcance do codigo de tema.
 */
final class CspWiringTest extends TestCase {
    private static function root(): string {
        return dirname(__DIR__, 3) . '/';
    }

    private static function read(string $path): string {
        return (string)file_get_contents(self::root() . $path);
    }

    /** @return array<string, string> caminho relativo => conteudo */
    private static function templates(string $app): array {
        $base  = self::root() . 'dev/' . $app . '/view/template';
        $found = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'twig') {
                $path = str_replace('\\', '/', substr($file->getPathname(), strlen(self::root())));
                // Comentarios Twig podem citar <script> para explicar a regra.
                $found[$path] = (string)preg_replace('/\{#.*?#\}/s', '', (string)file_get_contents($file->getPathname()));
            }
        }

        ksort($found);

        return $found;
    }

    /** @return array<string, array{0: string}> */
    public static function apps(): array {
        return ['catalog' => ['catalog'], 'admin' => ['admin']];
    }

    #[DataProvider('apps')]
    public function testEveryInlineScriptCarriesTheNonce(string $app): void {
        $offenders = [];
        $inline    = 0;

        foreach (self::templates($app) as $path => $source) {
            preg_match_all('/<script\b[^>]*>/i', $source, $tags);

            foreach ($tags[0] as $tag) {
                if (preg_match('/\ssrc\s*=/i', $tag) === 1) {
                    continue; // arquivo do proprio site: coberto por 'self'
                }

                $inline++;

                if (!str_contains($tag, 'nonce="{{ csp_nonce }}"')) {
                    $offenders[] = $path . ': ' . $tag;
                }
            }
        }

        self::assertSame([], $offenders, 'Todo <script> embutido precisa de nonce="{{ csp_nonce }}" (ADR 0093)');
        self::assertGreaterThan(0, $inline, 'o inventario deixou de enxergar os scripts embutidos');
    }

    #[DataProvider('apps')]
    public function testNoTemplateWritesEventHandlerAttributesOrJavascriptLinks(string $app): void {
        $offenders = [];

        foreach (self::templates($app) as $path => $source) {
            preg_match_all('/<[a-z][^<>]*?\s(on[a-z]+)\s*=\s*["\']/i', $source, $handlers);

            foreach ($handlers[1] as $name) {
                $offenders[] = $path . ': ' . $name . '=';
            }

            if (preg_match('/(?:href|src|action)\s*=\s*["\']\s*javascript:/i', $source) === 1) {
                $offenders[] = $path . ': javascript:';
            }
        }

        self::assertSame([], $offenders, 'Atributos on* e links javascript: sao recusados pela politica; use data-rms-confirm ou um .js');
    }

    public function testNonceIsNotReachableFromThemeCode(): void {
        // O tema nao pode escrever <script> (ADR 0087). Se o nonce fosse uma
        // variavel global do Twig, um tema publicado poderia autorizar o proprio script.
        $twig = self::read('dev/system/library/template/twig.php');
        self::assertStringNotContainsString('csp', strtolower($twig));
        self::assertStringNotContainsString('csp', strtolower(self::read('dev/system/library/template/theme_sandbox.php')));
        self::assertStringNotContainsString('csp', strtolower(self::read('dev/system/engine/loader.php')));

        // Rotas que o tema pode redefinir nao recebem nem usam o nonce.
        self::assertSame(1, preg_match('/THEME_TEMPLATE_KEYS\s*=\s*\[(.*?)\];/s', $twig, $m));
        preg_match_all("/'([a-z0-9_\\/-]+)'/", $m[1], $keys);
        self::assertContains('partial/site_header', $keys[1]);
        self::assertNotContains('common/header', $keys[1], 'o cabecalho com o importmap nao pode virar fonte de tema');

        foreach ($keys[1] as $key) {
            $file = self::root() . 'dev/catalog/view/template/' . $key . '.twig';

            if (is_file($file)) {
                self::assertStringNotContainsString('csp_nonce', (string)file_get_contents($file), $key);
            }
        }

        // O cabecalho entrega ao trecho do tema so as variaveis nomeadas.
        $header = self::read('dev/catalog/view/template/common/header.twig');
        self::assertSame(1, preg_match("/theme_partial\\('partial\\/site_header',\\s*\\{(.*?)\\}\\)/s", $header, $call));
        self::assertStringNotContainsString('csp_nonce', $call[1]);
    }

    public function testControllersThatRenderInlineScriptsPassTheNonce(): void {
        $views = [];

        foreach (self::apps() as [$app]) {
            foreach (self::templates($app) as $path => $source) {
                if (str_contains($source, 'csp_nonce')) {
                    $views[$app][] = substr($path, strlen('dev/' . $app . '/view/template/'), -strlen('.twig'));
                }
            }
        }

        self::assertSame(['common/header'], $views['catalog']);
        self::assertCount(7, $views['admin']);

        foreach ($views as $app => $routes) {
            foreach ($routes as $route) {
                $calls = 0;

                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . 'dev/' . $app . '/controller', \FilesystemIterator::SKIP_DOTS)) as $file) {
                    $code = (string)file_get_contents($file->getPathname());
                    $n    = substr_count($code, "load->view('" . $route . "'");

                    if ($n > 0) {
                        $calls += $n;
                        self::assertSame(
                            $n,
                            substr_count($code, "\$data['csp_nonce'] = \$this->csp->nonce();"),
                            $file->getFilename() . ' renderiza ' . $route . ' sem entregar o nonce em todas as chamadas'
                        );
                    }
                }

                self::assertGreaterThan(0, $calls, $app . ':' . $route);
            }
        }
    }

    public function testFrameworkBuildsThePolicyAndConfigNoLongerShipsALooseOne(): void {
        $config = self::read('dev/system/config/default.php');
        $code   = '';

        foreach (token_get_all($config) as $token) {
            if (!is_array($token) || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= is_array($token) ? $token[1] : $token;
            }
        }

        self::assertStringNotContainsString('Content-Security-Policy', $code);
        self::assertStringNotContainsString('unsafe-eval', $code);
        self::assertStringNotContainsString('unsafe-inline', $code);
        self::assertStringNotContainsString('Strict-Transport-Security', $code);
        self::assertStringContainsString("\$_['csp_mode']", $code);
        self::assertStringContainsString("defined('REAMUR_CSP_MODE') ? REAMUR_CSP_MODE : 'enforce'", $code);
        self::assertStringContainsString('REAMUR_CSP_SOURCES', $code);
        self::assertStringContainsString('REAMUR_HSTS_MAX_AGE', $code);

        $framework = self::read('dev/system/framework.php');
        self::assertStringContainsString("\$registry->set('csp', \$csp);", $framework);
        self::assertStringContainsString('$response->setHeader($csp->header());', $framework);
        self::assertStringContainsString('Security\\Csp::hsts(', $framework);
        // HSTS depende so do endereco configurado, nunca de cabecalho do cliente.
        self::assertSame(1, preg_match('/Csp::hsts\(\s*defined\(\'HTTP_SERVER\'\) \? \(string\)HTTP_SERVER : \'\',\s*\$config->get\(\'hsts_max_age\'/', $framework));
    }

    public function testOnlyTheLegacyPostEditorLoosensTheScriptRule(): void {
        $callers = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . 'dev', \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen(self::root())));

            if ($file->getExtension() !== 'php' || str_contains($path, '/storage/') || $path === 'dev/system/library/security/csp.php') {
                continue;
            }

            if (str_contains((string)file_get_contents($file->getPathname()), 'allowInlineScripts(')) {
                $callers[] = $path;
            }
        }

        self::assertSame(['dev/admin/controller/blog/blog.php'], $callers);

        $blog = self::read('dev/admin/controller/blog/blog.php');
        self::assertSame(1, substr_count($blog, '$this->csp->allowInlineScripts();'));
        self::assertSame(1, preg_match('/if \(\$legacyEditor\) \{\s*\$this->csp->allowInlineScripts\(\);\s*\$this->response->setHeader\(\$this->csp->header\(\)\);/', $blog));

        // O editor antigo nao consulta mais o site do fabricante.
        self::assertStringContainsString('versionCheck: false', self::read('dev/admin/view/js/admin.js'));
    }

    public function testConfirmationsAreHandledByAdminJs(): void {
        $js = self::read('dev/admin/view/js/admin.js');
        self::assertStringContainsString("hasAttribute('data-rms-confirm')", $js);
        self::assertStringContainsString("closest('button[data-rms-confirm], input[data-rms-confirm]')", $js);

        $uses = 0;

        foreach (self::templates('admin') as $source) {
            $uses += preg_match_all('/\sdata-rms-confirm="/', $source);
        }

        // EX-01 (ADR 0095): +2 na tela de extensoes (ligar e remover dados).
        self::assertSame(7, $uses);
    }

    public function testControllersWithTheirOwnPolicyReplaceTheGlobalOne(): void {
        // addHeader() empilharia duas linhas; quem manda a propria politica usa setHeader().
        $offenders = [];
        $own       = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . 'dev', \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen(self::root())));

            if ($file->getExtension() !== 'php' || str_contains($path, '/storage/')) {
                continue;
            }

            $code = (string)file_get_contents($file->getPathname());
            $own += substr_count($code, 'setHeader("Content-Security-Policy: ');

            if (preg_match('/addHeader\(\s*["\']Content-Security-Policy/i', $code) === 1) {
                $offenders[] = $path;
            }
        }

        self::assertSame([], $offenders);
        self::assertSame(5, $own, 'previas do admin: paginas (1), posts (1) e miniaturas do tema (3)');
    }

    public function testDashboardWarnsWhileTheEmergencySwitchIsOn(): void {
        $controller = self::read('dev/admin/controller/common/dashboard.php');
        self::assertStringContainsString("\$this->language->get('warning_csp_' . \$cspMode)", $controller);
        self::assertStringContainsString('data-rms-warning="csp"', self::read('dev/admin/view/template/common/dashboard.twig'));

        foreach (['pt-br', 'en-us', 'es-eu'] as $language) {
            $_ = [];
            require self::root() . 'dev/admin/language/' . $language . '/common/dashboard.php';

            foreach (['report', 'off'] as $mode) {
                self::assertNotSame('', (string)($_['warning_csp_' . $mode] ?? ''), $language . ' ' . $mode);
                self::assertStringContainsString('REAMUR_CSP_MODE = ' . $mode, $_['warning_csp_' . $mode]);
            }
        }
    }

    public function testInstallTemplateDocumentsTheSwitches(): void {
        $tpl = self::read('dev/install/config.php.tpl');

        foreach (['REAMUR_CSP_MODE', 'REAMUR_CSP_SOURCES', 'REAMUR_HSTS_MAX_AGE'] as $name) {
            self::assertStringContainsString($name, $tpl);
            // So como exemplo comentado: instalacao nova nasce com a politica aplicada.
            self::assertSame(0, preg_match('/^\s*define\(\'' . $name . '\'/m', $tpl), $name);
        }
    }
}
