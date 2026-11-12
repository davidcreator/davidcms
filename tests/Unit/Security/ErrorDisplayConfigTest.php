<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Engine\Config;

/**
 * S3 (ADR 0088): o modo producao (REAMUR_ERROR_DISPLAY=false no config.php)
 * precisa desligar a exibicao de erros de verdade. Antes, catalog.php e
 * admin.php fixavam error_display=true por cima do default.php.
 */
final class ErrorDisplayConfigTest extends TestCase {
    private static function root(): string {
        return dirname(__DIR__, 3) . '/';
    }

    /** @return array<string, array{0: string}> */
    public static function applications(): array {
        return ['site' => ['catalog'], 'painel' => ['admin']];
    }

    #[DataProvider('applications')]
    public function testApplicationConfigDoesNotOverrideTheSwitch(string $application): void {
        $config = new Config();
        $config->load('default');
        $config->set('error_display', 'valor-do-default');
        $config->load($application);

        self::assertSame(
            'valor-do-default',
            $config->get('error_display'),
            "config/{$application}.php nao pode definir error_display: quem manda e o REAMUR_ERROR_DISPLAY do config.php"
        );
    }

    public function testDefaultFollowsTheInstallationConstant(): void {
        $config = new Config();
        $config->load('default');

        $expected = defined('REAMUR_ERROR_DISPLAY') ? (bool)constant('REAMUR_ERROR_DISPLAY') : false;

        self::assertSame($expected, $config->get('error_display'));
    }

    public function testDefaultIsOffWhenTheConstantIsMissing(): void {
        // Roda o default.php num processo limpo, com e sem a constante.
        $file = self::root() . 'dev/system/config/default.php';

        foreach (['' => 'false', "define('REAMUR_ERROR_DISPLAY', false);" => 'false', "define('REAMUR_ERROR_DISPLAY', true);" => 'true'] as $prelude => $expected) {
            $code = $prelude
                . "define('DIR_STORAGE', '/tmp/'); define('DIR_SYSTEM', '/tmp/'); define('DIR_APPLICATION', '/tmp/');"
                . "define('HTTP_SERVER', 'https://example.test/'); define('APPLICATION', 'Catalog'); define('REAMUR_VERSION', 't');"
                . '$_ = []; require ' . var_export($file, true) . '; echo var_export($_["error_display"], true);';

            $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -r ' . escapeshellarg($code) . ' 2>/dev/null');

            self::assertSame($expected, trim((string)$output), 'prelude: ' . ($prelude === '' ? '(constante ausente)' : $prelude));
        }
    }

    public function testFrameworkReadsTheSwitchWithASafeDefault(): void {
        $source = (string)file_get_contents(self::root() . 'dev/system/framework.php');

        self::assertStringNotContainsString("get('error_display', true)", $source, 'o padrao de leitura nao pode ser true');
        self::assertStringContainsString("get('error_display', false)", $source);
    }

    public function testNoLayerPrintsARawExceptionMessage(): void {
        // O ultimo recurso antigo do Router fazia exit('ReamurCMS error: ' . mensagem),
        // que mandava SQL e caminho de arquivo para o visitante.
        foreach (['dev/system/engine/router.php', 'dev/system/framework.php'] as $file) {
            $source = (string)file_get_contents(self::root() . $file);

            self::assertStringNotContainsString("exit('ReamurCMS error", $source, $file);
            self::assertDoesNotMatchRegularExpression('/\b(exit|die|echo|print)\b[^;]*->getMessage\(\)/', $source, $file);
        }
    }

    /** @return array<string, array{0: string}> */
    public static function adminLanguages(): array {
        return ['pt-br' => ['pt-br'], 'en-us' => ['en-us'], 'es-eu' => ['es-eu']];
    }

    #[DataProvider('adminLanguages')]
    public function testDashboardWarningExistsInEveryAdminLanguage(string $language): void {
        $_ = [];
        require self::root() . 'dev/admin/language/' . $language . '/common/dashboard.php';

        self::assertArrayHasKey('warning_error_display', $_);
        self::assertStringContainsString('REAMUR_ERROR_DISPLAY', $_['warning_error_display']);
        self::assertStringContainsString('false', $_['warning_error_display']);
        self::assertStringContainsString('config.php', $_['warning_error_display']);
    }

    public function testDashboardOnlyShowsTheWarningWhileDisplayIsOn(): void {
        $controller = (string)file_get_contents(self::root() . 'dev/admin/controller/common/dashboard.php');
        $template   = (string)file_get_contents(self::root() . 'dev/admin/view/template/common/dashboard.twig');

        self::assertMatchesRegularExpression(
            "/\\\$data\['warning_error_display'\]\s*=\s*\\\$this->config->get\('error_display', false\)\s*\?/",
            $controller
        );
        self::assertStringContainsString('{% if warning_error_display %}', $template);
        self::assertStringContainsString('{{ warning_error_display }}', $template, 'texto escapado pelo Twig, sem |raw');
        self::assertStringNotContainsString('warning_error_display|raw', $template);
    }

    /** @return array<string, array{0: string}> */
    public static function schemas(): array {
        return ['completo' => ['schema-complete.sql'], 'base' => ['schema.sql']];
    }

    #[DataProvider('schemas')]
    public function testInstallerTemporaryTablesDeclareTheCollation(string $file): void {
        // Sem COLLATE a tabela temporaria herda a collation padrao do servidor
        // (utf8mb4_uca1400_ai_ci no MariaDB 11.8) e o JOIN com rms_page falha
        // com o erro 1267: a instalacao parava no meio.
        $sql = (string)file_get_contents(self::root() . 'dev/install/sql/' . $file);

        self::assertGreaterThan(0, preg_match_all('/CREATE\s+TEMPORARY\s+TABLE.*?;/is', $sql, $matches));

        foreach ($matches[0] as $statement) {
            self::assertMatchesRegularExpression('/COLLATE\s*=?\s*utf8mb4_unicode_ci/i', $statement, substr($statement, 0, 60));
        }
    }
}
