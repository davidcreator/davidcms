<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * S5 (ADR 0089): a leitura do IP e do HTTPS tem UM ponto so
 * (Request::ip() / Request::isHttps()). Estes testes travam a fiacao:
 * ninguem volta a ler REMOTE_ADDR direto, e a confianca so vem do config.php.
 */
final class ProxyWiringTest extends TestCase {
    private static function root(): string {
        return dirname(__DIR__, 3) . '/';
    }

    private static function read(string $path): string {
        return (string)file_get_contents(self::root() . $path);
    }

    public function testNoApplicationCodeReadsTheConnectionAddressDirectly(): void {
        $allowed = [
            'dev/system/library/security/client_address.php', // o unico leitor
        ];

        $offenders = [];
        $iterator  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . 'dev', \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen(self::root())));

            if ($file->getExtension() !== 'php' || str_contains($path, '/storage/') || in_array($path, $allowed, true)) {
                continue;
            }

            // So codigo: comentarios podem citar o nome para explicar a regra.
            foreach (token_get_all((string)file_get_contents($file->getPathname())) as $token) {
                if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING
                    && in_array(trim($token[1], '\'"'), ['REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP'], true)) {
                    $offenders[] = $path . ':' . $token[2];
                }
            }
        }

        self::assertSame([], $offenders, 'use $this->request->ip() no lugar de REMOTE_ADDR');
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function callers(): array {
        return [
            'login do admin'       => ['dev/admin/controller/common/login.php', 1],
            'contato'              => ['dev/catalog/controller/common/contact.php', 2],
            'orcamento'            => ['dev/catalog/controller/checkout/quote.php', 2],
            'pedido pelo admin'    => ['dev/admin/controller/sale/order.php', 1],
        ];
    }

    #[DataProvider('callers')]
    public function testCallersAskTheRequestForTheVisitorIp(string $file, int $times): void {
        self::assertSame($times, substr_count(self::read($file), '$this->request->ip()'));
    }

    public function testThemeConditionAndSessionUseTheSameReading(): void {
        self::assertStringContainsString("'https'      => \$this->request->isHttps(),", self::read('dev/catalog/controller/startup/theme.php'));

        $framework = self::read('dev/system/framework.php');

        self::assertStringContainsString("\$request->setProxyTrust(\$config->get('proxy_trusted', ''), (int)\$config->get('proxy_hops', 0));", $framework);
        self::assertStringContainsString('Session::cookieSecure(', $framework);
        self::assertLessThan(
            strpos($framework, 'Session::cookieSecure('),
            strpos($framework, '$request->setProxyTrust('),
            'a confianca precisa estar declarada antes de a sessao abrir'
        );
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function sessionOptions(): array {
        return [
            'opcao secure ligada, conexao sem HTTPS'    => ["['secure' => true]", '', 'secure=1'],
            'opcao secure desligada, conexao com HTTPS' => ["['secure' => false]", "\$_SERVER['HTTPS'] = 'on';", 'secure=0'],
            'sem a opcao, conexao com HTTPS (legado)'   => ['[]', "\$_SERVER['HTTPS'] = 'on';", 'secure=1'],
            'sem a opcao, conexao sem HTTPS (legado)'   => ['[]', '', 'secure=0'],
        ];
    }

    #[DataProvider('sessionOptions')]
    public function testSessionHonoursTheSecureOption(string $options, string $prelude, string $expected): void {
        $dir  = sys_get_temp_dir() . '/rms-s5-' . bin2hex(random_bytes(4));
        $code = $prelude
            . 'require ' . var_export(self::root() . 'dev/system/library/session.php', true) . ';'
            . 'require ' . var_export(self::root() . 'dev/system/library/session/file.php', true) . ';'
            . 'mkdir(' . var_export($dir, true) . ');'
            . '$s = new \\Reamur\\System\\Library\\Session(new \\Reamur\\System\\Library\\Session\\File(' . var_export($dir . '/', true) . '), ' . $options . ');'
            . '$p = session_get_cookie_params();'
            . 'fwrite(STDERR, "secure=" . (int)$p["secure"] . " httponly=" . (int)$p["httponly"] . " samesite=" . $p["samesite"]);';

        $output = (string)shell_exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -r ' . escapeshellarg($code) . ' 2>&1 >/dev/null');

        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);

        self::assertSame($expected . ' httponly=1 samesite=Lax', trim($output));
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function constants(): array {
        return [
            'nada definido'        => ['', "''", '0'],
            'lista em texto'       => ["define('REAMUR_TRUSTED_PROXIES', '203.0.113.0/24, 10.0.0.1');", "'203.0.113.0/24, 10.0.0.1'", '0'],
            'lista em array'       => ["define('REAMUR_TRUSTED_PROXIES', ['203.0.113.0/24']);", "array (\n  0 => '203.0.113.0/24',\n)", '0'],
            'lista de tipo errado' => ["define('REAMUR_TRUSTED_PROXIES', true);", "''", '0'],
            'saltos'               => ["define('REAMUR_TRUSTED_PROXY_HOPS', 2);", "''", '2'],
            'saltos em texto'      => ["define('REAMUR_TRUSTED_PROXY_HOPS', '1');", "''", '1'],
            'saltos negativos'     => ["define('REAMUR_TRUSTED_PROXY_HOPS', -3);", "''", '0'],
            'saltos de tipo errado' => ["define('REAMUR_TRUSTED_PROXY_HOPS', 'muitos');", "''", '0'],
        ];
    }

    #[DataProvider('constants')]
    public function testDefaultConfigReadsTheInstallationConstants(string $prelude, string $trusted, string $hops): void {
        $file = self::root() . 'dev/system/config/default.php';
        $code = $prelude
            . "define('DIR_STORAGE', '/tmp/'); define('DIR_SYSTEM', '/tmp/'); define('DIR_APPLICATION', '/tmp/');"
            . "define('HTTP_SERVER', 'https://example.test/'); define('APPLICATION', 'Catalog'); define('REAMUR_VERSION', 't');"
            . '$_ = []; require ' . var_export($file, true) . ';'
            . 'echo var_export($_["proxy_trusted"], true), "|", var_export($_["proxy_hops"], true);';

        $output = (string)shell_exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -r ' . escapeshellarg($code) . ' 2>/dev/null');

        self::assertSame($trusted . '|' . $hops, trim($output));
    }

    #[DataProvider('applications')]
    public function testApplicationConfigsDoNotOverrideTheTrust(string $application): void {
        $source = self::read('dev/system/config/' . $application . '.php');

        self::assertStringNotContainsString('proxy_trusted', $source);
        self::assertStringNotContainsString('proxy_hops', $source);
    }

    /** @return array<string, array{0: string}> */
    public static function applications(): array {
        return ['site' => ['catalog'], 'painel' => ['admin'], 'instalador' => ['install']];
    }

    public function testInstallerTemplateExplainsButDoesNotTrustAnyone(): void {
        $template = self::read('dev/install/config.php.tpl');

        self::assertStringContainsString('REAMUR_TRUSTED_PROXIES', $template);
        self::assertStringContainsString('REAMUR_TRUSTED_PROXY_HOPS', $template);

        // Instalacao nova nao confia em proxy nenhum: os exemplos ficam comentados.
        foreach (preg_split('/\R/', $template) ?: [] as $line) {
            if (str_contains($line, 'REAMUR_TRUSTED_PROX')) {
                self::assertStringStartsWith('//', ltrim($line), $line);
            }
        }
    }

    public function testInstallerBuildsItsLinksWithTheForwardedScheme(): void {
        $index = self::read('dev/install/index.php');
        $step3 = self::read('dev/install/controller/install/step_3.php');

        self::assertStringContainsString('HTTP_X_FORWARDED_PROTO', $index);
        self::assertStringContainsString('ClientAddress::looksHttps($server)', $step3);
    }

    public function testDashboardTextsExistInEveryAdminLanguage(): void {
        $keys = [];

        foreach (['pt-br', 'en-us', 'es-eu'] as $language) {
            $_ = [];
            require self::root() . 'dev/admin/language/' . $language . '/common/dashboard.php';

            $mine = array_filter($_, static fn (string $key): bool => str_starts_with($key, 'text_conn_') || str_starts_with($key, 'warning_proxy_'), ARRAY_FILTER_USE_KEY);

            self::assertCount(19, $mine, $language);

            // mesmos marcadores de sprintf em todos os idiomas
            $keys[$language] = array_map(static function (string $text): array {
                preg_match_all('/%[sd]/', $text, $m);

                return $m[0];
            }, $mine);
        }

        self::assertSame($keys['pt-br'], $keys['en-us']);
        self::assertSame($keys['pt-br'], $keys['es-eu']);
    }

    public function testDashboardShowsTheConnectionEscaped(): void {
        $template = self::read('dev/admin/view/template/common/dashboard.twig');

        foreach (['conn_ip', 'conn_peer', 'conn_https', 'conn_cookie', 'conn_trust', 'warning'] as $name) {
            self::assertStringContainsString('{{ ' . $name . ' }}', $template);
            self::assertStringNotContainsString($name . '|raw', $template);
        }

        self::assertStringContainsString('{% for warning in warnings_proxy %}', $template);
    }
}
