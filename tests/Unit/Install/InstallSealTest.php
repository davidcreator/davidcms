<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Install;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\Install\Controller\Install\Step_3;
use Reamur\Install\Model\Install\Install as InstallModel;
use Reamur\System\Library\Security\InstallSeal;

/**
 * S12 (ADR 0091): o instalador se recusa quando o site está instalado, e
 * "instalado" é o estado real do disco (trava OU config.php), não só a trava.
 */
final class InstallSealTest extends TestCase {
    private string $root = '';

    protected function setUp(): void {
        $this->root = sys_get_temp_dir() . '/rms-s12-' . bin2hex(random_bytes(5));

        foreach (['dev/install', 'dev/catalog', 'dev/admin', 'dev/system/storage', 'dev/system/library/security'] as $dir) {
            mkdir($this->root . '/' . $dir, 0777, true);
        }
    }

    protected function tearDown(): void {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($this->root);
    }

    private function storage(): string {
        return $this->root . '/dev/system/storage/';
    }

    private function dev(): string {
        return $this->root . '/dev';
    }

    private static function repo(string $path): string {
        return dirname(__DIR__, 3) . '/' . $path;
    }

    // ── a decisão ─────────────────────────────────────────────────────────

    public function testFreshTreeIsOpen(): void {
        self::assertSame('', InstallSeal::reason($this->storage(), $this->dev()));
        self::assertFalse(InstallSeal::isSealed($this->storage(), $this->dev()));
    }

    public function testLockAloneSeals(): void {
        file_put_contents($this->storage() . 'install.lock', "installed\n");

        self::assertSame('lock', InstallSeal::reason($this->storage(), $this->dev()));
    }

    /** @return array<string, array{0: string}> */
    public static function apps(): array {
        return ['catalog' => ['catalog'], 'admin' => ['admin']];
    }

    #[DataProvider('apps')]
    public function testOneConfigFileSealsWithoutAnyLock(string $app): void {
        file_put_contents($this->dev() . '/' . $app . '/config.php', "<?php\ndefine('DIR_APPLICATION', '/x/');\n");

        self::assertFileDoesNotExist($this->storage() . 'install.lock');
        self::assertSame('config', InstallSeal::reason($this->storage(), $this->dev()));
        self::assertTrue(InstallSeal::isSealed($this->storage(), $this->dev()));
    }

    public function testEmptyConfigFilesAreNotAnInstallation(): void {
        file_put_contents($this->dev() . '/catalog/config.php', '');
        file_put_contents($this->dev() . '/admin/config.php', '');

        self::assertSame('', InstallSeal::reason($this->storage(), $this->dev()));
    }

    public function testPathsAreAcceptedWithOrWithoutTrailingSlashAndWithBackslashes(): void {
        file_put_contents($this->dev() . '/admin/config.php', '<?php // x');

        self::assertTrue(InstallSeal::isSealed(rtrim($this->storage(), '/'), $this->dev() . '/'));
        self::assertSame(
            [$this->dev() . '/catalog/config.php', $this->dev() . '/admin/config.php'],
            InstallSeal::configFiles($this->dev() . '/')
        );
    }

    public function testSealWritesTheLockAndReportsFailure(): void {
        self::assertTrue(InstallSeal::seal($this->storage()));
        self::assertFileExists($this->storage() . 'install.lock');
        self::assertStringStartsWith('installed ', (string)file_get_contents($this->storage() . 'install.lock'));
        self::assertSame('lock', InstallSeal::reason($this->storage(), $this->dev()));

        InstallSeal::unseal($this->storage());
        self::assertFileDoesNotExist($this->storage() . 'install.lock');
        InstallSeal::unseal($this->storage()); // sem trava: não reclama

        // Pasta que não existe (ou sem permissão de escrita): a falha é devolvida, não engolida.
        self::assertFalse(InstallSeal::seal($this->root . '/nao-existe/'));
    }

    public function testInstallerPresence(): void {
        self::assertFalse(InstallSeal::installerPresent($this->dev()));

        file_put_contents($this->dev() . '/install/index.php', '<?php');

        self::assertTrue(InstallSeal::installerPresent($this->dev()));
        self::assertTrue(InstallSeal::installerPresent($this->dev() . '/'));
    }

    public function testLockedPageSaysNothingAboutTheServer(): void {
        $page = InstallSeal::lockedPage();

        self::assertStringContainsString('Installation locked', $page);
        self::assertStringContainsString('noindex', $page);
        self::assertDoesNotMatchRegularExpression('#/home/|/var/|[A-Z]:\\\\|PHP/\d|<form|<input|<a #i', $page);
    }

    // ── a porta de entrada: install/index.php decide antes de subir o núcleo ──

    /**
     * Roda o install/index.php REAL numa árvore de teste sem o núcleo. Se a
     * trava recusa, sai a página de recusa. Se a trava deixa passar, o script
     * segue até o require do núcleo, que não existe ali, e morre com erro:
     * é o sinal de que passou.
     *
     * @return array{0: string, 1: int}
     */
    private function runInstaller(): array {
        copy(self::repo('dev/install/index.php'), $this->dev() . '/install/index.php');
        copy(self::repo('dev/system/library/security/install_seal.php'), $this->dev() . '/system/library/security/install_seal.php');

        // Sem shell (funciona igual em Linux e Windows): comando em lista e cwd explícito.
        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=-1', 'index.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->dev() . '/install'
        );

        self::assertIsResource($process);

        $output = (string)stream_get_contents($pipes[1]) . (string)stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [$output, proc_close($process)];
    }

    /** @return array<string, array{0: list<string>, 1: bool}> */
    public static function gateCases(): array {
        return [
            'nada no disco'                   => [[], false],
            'só a trava'                      => [['system/storage/install.lock'], true],
            'só catalog/config.php'           => [['catalog/config.php'], true],
            'só admin/config.php'             => [['admin/config.php'], true],
            'os dois config.php, sem trava'   => [['catalog/config.php', 'admin/config.php'], true],
            'trava e config.php'              => [['system/storage/install.lock', 'catalog/config.php', 'admin/config.php'], true],
        ];
    }

    /** @param list<string> $files */
    #[DataProvider('gateCases')]
    public function testFrontControllerRefusesBeforeBootingTheCore(array $files, bool $sealed): void {
        foreach ($files as $file) {
            file_put_contents($this->dev() . '/' . $file, "<?php // instalado\n");
        }

        [$output, $code] = $this->runInstaller();

        if ($sealed) {
            self::assertSame(0, $code, $output);
            self::assertStringContainsString('<h1>Installation locked</h1>', $output);
            self::assertStringNotContainsString('startup.php', $output, 'recusou antes de chegar ao núcleo');
        } else {
            self::assertStringNotContainsString('Installation locked', $output);
            self::assertStringContainsString('startup.php', $output, 'passou pela trava e tentou subir o núcleo');
            self::assertNotSame(0, $code);
        }
    }

    public function testEmptyConfigFilesLetTheInstallerRun(): void {
        file_put_contents($this->dev() . '/catalog/config.php', '');
        file_put_contents($this->dev() . '/admin/config.php', '');

        [$output] = $this->runInstaller();

        self::assertStringNotContainsString('Installation locked', $output);
        self::assertStringContainsString('startup.php', $output);
    }

    public function testGateSitsBeforeTheCoreInTheFrontController(): void {
        $source = (string)file_get_contents(self::repo('dev/install/index.php'));

        $require = strpos($source, "/system/library/security/install_seal.php')");
        $gate    = strpos($source, 'InstallSeal::isSealed(DIR_STORAGE, dirname(__DIR__))');
        $core    = strpos($source, "require_once(DIR_SYSTEM . 'startup.php')");

        self::assertIsInt($require);
        self::assertIsInt($gate);
        self::assertIsInt($core);
        self::assertLessThan($gate, $require);
        self::assertLessThan($core, $gate);
        self::assertStringContainsString('http_response_code(403)', substr($source, $gate, $core - $gate));
        self::assertStringContainsString('exit();', substr($source, $gate, $core - $gate));
    }

    // ── o passo 3 trava junto com a instalação ────────────────────────────

    public function testStepThreeSealsFirstAndUndoesOnFailure(): void {
        $source = (string)file_get_contents(self::repo('dev/install/controller/install/step_3.php'));

        $start = strpos($source, 'private function install(array $db, string $prefix): array {');
        $end   = strpos($source, 'private function undoInstall(): void {');

        self::assertIsInt($start);
        self::assertIsInt($end);

        $install = substr($source, $start, $end - $start);

        $seal   = strpos($install, '$seal::seal(DIR_STORAGE)');
        $config = strpos($install, '$this->writeConfigs(');
        $schema = strpos($install, '->importSchema(');
        $admin  = strpos($install, '->createAdministrator(');

        self::assertIsInt($seal);
        self::assertIsInt($config);
        self::assertIsInt($schema);
        self::assertIsInt($admin);
        self::assertLessThan($config, $seal, 'trava antes do config.php');
        self::assertLessThan($schema, $config);
        self::assertLessThan($admin, $schema);

        // falha no config.php e falha no banco: as duas desfazem
        self::assertSame(2, substr_count($install, '$this->undoInstall();'));
        self::assertStringContainsString('catch (\Throwable $e)', $install);
        self::assertStringContainsString("'error_lock'", $install);

        $undo = substr($source, $end, 600);

        self::assertStringContainsString('$seal::configFiles(', $undo);
        self::assertStringContainsString('@unlink($file)', $undo);
        self::assertStringContainsString('$seal::unseal(DIR_STORAGE)', $undo);
    }

    public function testConclusionIsRenderedInTheSameResponseAndStepFourNoLongerWritesTheLock(): void {
        $step3 = (string)file_get_contents(self::repo('dev/install/controller/install/step_3.php'));
        $step4 = (string)file_get_contents(self::repo('dev/install/controller/install/step_4.php'));

        self::assertStringContainsString("\$this->load->controller('install/step_4');", $step3);
        self::assertStringNotContainsString("link('install/step_4')", $step3, 'redirect para o passo 4 bateria no 403 da trava');

        $codeOnly = implode('', array_map(
            static fn ($token): string => is_array($token) ? (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : $token[1]) : $token,
            token_get_all($step4)
        ));

        self::assertStringNotContainsString('file_put_contents', $codeOnly);
        self::assertStringNotContainsString('install.lock', $codeOnly);
        self::assertStringContainsString("empty(\$this->session->data['install_done'])", $codeOnly);
    }

    public function testConfigFoldersAreCheckedBeforeTheFirstWrite(): void {
        $source = (string)file_get_contents(self::repo('dev/install/controller/install/step_3.php'));
        $start  = strpos($source, 'private function writeConfigs(');

        self::assertIsInt($start);

        $body = substr($source, $start);

        // uma conferência das duas pastas antes do laço que grava, outra dentro dele
        self::assertSame(2, substr_count($body, 'is_writable($dir_dev . $app)'));
        self::assertLessThan(strpos($body, '$output = strtr($tpl, ['), strpos($body, 'is_writable($dir_dev . $app)'));
    }

    // ── "Testar conexão" ─────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: bool}> */
    public static function dbHosts(): array {
        return [
            'localhost'              => ['localhost', true],
            'IPv4'                   => ['127.0.0.1', true],
            'nome de hospedagem'     => ['mysql-01.hostinger.com', true],
            'nome com sublinhado'    => ['db_server', true],
            'nome:porta'             => ['db.example.org:3307', true],
            'IPv6 entre colchetes'   => ['[2001:db8::1]', true],
            'mysqli persistente'     => ['p:localhost', true],
            'socket'                 => ['localhost:/var/run/mysqld/mysqld.sock', true],
            'vazio'                  => ['', false],
            'opção extra no DSN'     => ['x;unix_socket=/tmp/s', false],
            'ponto e vírgula'        => ['localhost;', false],
            'sinal de igual'         => ['host=x', false],
            'espaço'                 => ['local host', false],
            'quebra de linha no fim' => ["localhost\n", false],
            'NUL'                    => ["localhost\0", false],
            'aspas'                  => ["local'host", false],
            'arroba'                 => ['user@host', false],
            'longo demais'           => [str_repeat('a', 256), false],
        ];
    }

    #[DataProvider('dbHosts')]
    public function testDatabaseHostAlphabet(string $host, bool $expected): void {
        self::assertSame($expected, Step_3::isDbHost($host));
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function dbNames(): array {
        return [
            'comum'             => ['u123456_site', true],
            'com hífen'         => ['meu-banco', true],
            'com acento'        => ['aplicação', true],
            'com espaço'        => ['meu banco', true],
            'vazio'             => ['', false],
            'ponto e vírgula'   => ['x;port=1', false],
            'quebra de linha'   => ["x\n", false],
            'NUL'               => ["x\0", false],
            '64 caracteres'     => [str_repeat('a', 64), true],
            '65 caracteres'     => [str_repeat('a', 65), false],
        ];
    }

    #[DataProvider('dbNames')]
    public function testDatabaseNameRule(string $name, bool $expected): void {
        self::assertSame($expected, Step_3::isDbName($name));
    }

    public function testConnectionTestIsValidatedAndTimeLimited(): void {
        $step3 = (string)file_get_contents(self::repo('dev/install/controller/install/step_3.php'));
        $model = (string)file_get_contents(self::repo('dev/install/model/install/install.php'));

        $start = strpos($step3, 'public function test(): void {');
        $end   = strpos($step3, 'private const TEST_TIMEOUT');

        self::assertIsInt($start);
        self::assertIsInt($end);

        $test = substr($step3, $start, $end - $start);

        self::assertLessThan(strpos($test, '->connect($db, self::TEST_TIMEOUT)'), strpos($test, 'self::isDbHost($db[\'db_hostname\'])'));
        self::assertStringContainsString('self::isDbName($db[\'db_database\'])', $test);
        self::assertStringContainsString('private const TEST_TIMEOUT = 5;', $step3);

        // validate() do formulário usa as mesmas regras
        self::assertSame(2, substr_count($step3, "self::isDbHost("));
        self::assertSame(2, substr_count($step3, "self::isDbName("));

        $connect = new \ReflectionMethod(InstallModel::class, 'connect');

        self::assertSame(2, $connect->getNumberOfParameters());
        self::assertSame(60, $connect->getParameters()[1]->getDefaultValue());
        self::assertStringContainsString("'mysqlnd.net_read_timeout'", $model);
        self::assertStringContainsString("'default_socket_timeout'", $model);
        self::assertStringContainsString('\PDO::ATTR_TIMEOUT => $timeout', $model);
    }

    public function testFailedConnectionRestoresTheIniValues(): void {
        if (!extension_loaded('pdo_mysql')) {
            self::markTestSkipped('pdo_mysql ausente');
        }

        $before = [ini_get('mysqlnd.net_read_timeout'), ini_get('default_socket_timeout')];
        $model  = new InstallModel(new \Reamur\System\Engine\Registry());

        // Porta 1 em loopback: recusa na hora, sem rede externa.
        $ok = $model->connect([
            'db_driver' => 'pdo', 'db_hostname' => '127.0.0.1', 'db_username' => 'x',
            'db_password' => 'x', 'db_database' => 'x', 'db_port' => '1',
        ], 2);

        self::assertFalse($ok);
        self::assertNotSame('', $model->getError());
        self::assertSame($before, [ini_get('mysqlnd.net_read_timeout'), ini_get('default_socket_timeout')]);
    }

    // ── textos e aviso do painel ──────────────────────────────────────────

    public function testTextsExistInEveryLanguage(): void {
        foreach (['pt-br', 'en-us', 'es-eu'] as $language) {
            $_ = [];
            require self::repo('dev/install/language/' . $language . '/install/step_3.php');

            foreach (['error_lock', 'error_import', 'error_db_hostname', 'error_db_database'] as $key) {
                self::assertArrayHasKey($key, $_, $language);
                self::assertNotSame('', trim((string)$_[$key]), $language . ' ' . $key);
            }

            $_ = [];
            require self::repo('dev/admin/language/' . $language . '/common/dashboard.php');

            self::assertArrayHasKey('warning_install_folder', $_, $language);
            self::assertStringContainsString('install', (string)$_['warning_install_folder']);
        }
    }

    public function testDashboardWarnsWhileTheInstallFolderExists(): void {
        $controller = (string)file_get_contents(self::repo('dev/admin/controller/common/dashboard.php'));
        $template   = (string)file_get_contents(self::repo('dev/admin/view/template/common/dashboard.twig'));

        self::assertStringContainsString("InstallSeal::installerPresent(dirname(rtrim(DIR_APPLICATION, '/')))", $controller);
        self::assertStringContainsString("\$this->language->get('warning_install_folder')", $controller);
        self::assertStringContainsString('{% if warning_install_folder %}', $template);
        self::assertStringContainsString('{{ warning_install_folder }}', $template);
        self::assertStringNotContainsString('warning_install_folder|raw', $template);
    }
}
