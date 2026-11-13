<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Common\Dashboard as DashboardController;
use Reamur\Admin\Controller\Setting\Setting as SettingController;
use Reamur\Admin\Controller\Startup\Setting as AdminStartupSetting;
use Reamur\Admin\Model\Setting\Webhook as AdminWebhookModel;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Security\SecretBox;
use Reamur\System\Library\Session;

/**
 * S9 (ADR 0094): a ligação da cifra ao resto do sistema — instalador, esquema
 * do banco, telas e textos. Onde dá, executa o código; onde não dá (arquivos
 * de instalação e modelos de tela), lê o arquivo que vai para o servidor.
 */
final class SecretWiringTest extends TestCase {
    private const LANGUAGES = ['en-us', 'pt-br', 'es-eu'];

    private static function repo(string $path): string {
        return dirname(__DIR__, 3) . '/' . $path;
    }

    private static function read(string $path): string {
        $file = self::repo($path);
        self::assertFileExists($file);

        return (string)file_get_contents($file);
    }

    // ── instalador ──────────────────────────────────────────────────────────

    public function testConfigTemplateCarriesOneActiveKeyLineAndNoActiveOldKey(): void {
        $tpl = self::read('dev/install/config.php.tpl');

        self::assertSame(1, preg_match_all("/^define\\('REAMUR_SECRET_KEY', '\\{\\{SECRET_KEY\\}\\}'\\);$/m", $tpl));
        self::assertSame(0, preg_match_all("/^\\s*define\\('REAMUR_SECRET_KEY_OLD'/m", $tpl), 'a chave anterior só aparece como exemplo comentado');
        self::assertSame(1, preg_match_all("/^\\/\\/\\s+define\\('REAMUR_SECRET_KEY_OLD'/m", $tpl));
    }

    public function testInstalledConfigIsValidPhpAndBothAppsReadTheSameKey(): void {
        $tpl = self::read('dev/install/config.php.tpl');
        $key = SecretBox::generateKey();
        $dir = sys_get_temp_dir() . '/rms-s9-' . bin2hex(random_bytes(5));
        mkdir($dir . '/catalog', 0777, true);
        mkdir($dir . '/admin', 0777, true);

        try {
            foreach (['catalog', 'admin'] as $app) {
                $out = strtr($tpl, ['{{SECRET_KEY}}' => $key, '{{APP_DIR}}' => $app]);
                $out = (string)preg_replace('/\{\{[A-Z_]+\}\}/', 'x', $out);
                file_put_contents($dir . '/' . $app . '/config.php', $out);

                // Analisa sem executar: erro de sintaxe vira ParseError.
                self::assertNotEmpty(token_get_all($out, TOKEN_PARSE));
                self::assertSame($key, SecretBox::readKeyFromConfig($dir . '/' . $app . '/config.php'));
            }

            $admin = new SecretBox($key, '', $dir . '/catalog/config.php');
            self::assertSame(SecretBox::PEER_SAME, $admin->peerState());
            self::assertTrue($admin->canSeal(), 'instalação nova já grava cifrado, sem passo manual');
        } finally {
            @unlink($dir . '/catalog/config.php');
            @unlink($dir . '/admin/config.php');
            @rmdir($dir . '/catalog');
            @rmdir($dir . '/admin');
            @rmdir($dir);
        }
    }

    public function testInstallerGeneratesOneKeyForBothConfigFiles(): void {
        $source = self::read('dev/install/controller/install/step_3.php');
        $start  = strpos($source, 'private function writeConfigs(');
        self::assertNotFalse($start);
        $body = substr($source, (int)$start);

        self::assertSame(1, substr_count($body, 'SecretBox::generateKey()'));
        self::assertSame(1, substr_count($body, "'{{SECRET_KEY}}'"));

        $generate = strpos($body, 'SecretBox::generateKey()');
        $write    = strpos($body, '$output = strtr($tpl');
        $loop     = strrpos(substr($body, 0, (int)$write), "foreach (['catalog', 'admin'] as \$app)");

        self::assertNotFalse($write);
        self::assertNotFalse($loop);
        self::assertLessThan($loop, $generate, 'a chave é gerada antes do laço: a mesma para catálogo e admin');
    }

    // ── esquema do banco ────────────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function schemaFiles(): array {
        return [
            'schema.sql'          => ['dev/install/sql/schema.sql'],
            'schema-complete.sql' => ['dev/install/sql/schema-complete.sql'],
        ];
    }

    #[DataProvider('schemaFiles')]
    public function testFreshSchemaCreatesTheWideWebhookSecretColumn(string $file): void {
        $sql = self::read($file);
        self::assertSame(1, preg_match('/CREATE TABLE(?: IF NOT EXISTS)? `rms_webhook` \((.*?)\n\)/s', $sql, $m));
        self::assertSame(1, preg_match('/`secret`\s+VARCHAR\((\d+)\) NOT NULL/', $m[1], $col));
        self::assertSame(AdminWebhookModel::SECRET_COLUMN_SIZE, (int)$col[1]);
    }

    public function testUpgradeAndRollbackScriptsChangeOnlyTheColumnSize(): void {
        $strip = static fn (string $sql): string => trim((string)preg_replace('/^--.*$/m', '', $sql));

        $up   = $strip(self::read('dev/install/sql/upgrade_s9_webhook_secret.sql'));
        $down = $strip(self::read('dev/install/sql/rollback_s9_webhook_secret.sql'));

        self::assertSame(1, preg_match('/^ALTER TABLE `rms_webhook`\s+MODIFY `secret` VARCHAR\((\d+)\) NOT NULL COMMENT \'[^\']*\';$/', $up, $m));
        self::assertSame(AdminWebhookModel::SECRET_COLUMN_SIZE, (int)$m[1]);
        self::assertSame(1, preg_match('/^ALTER TABLE `rms_webhook`\s+MODIFY `secret` VARCHAR\(64\) NOT NULL COMMENT \'[^\']*\';$/', $down));

        foreach ([$up, $down] as $sql) {
            self::assertSame(1, substr_count($sql, ';'), 'um comando só');
            self::assertDoesNotMatchRegularExpression('/\b(UPDATE|DELETE|DROP|TRUNCATE|INSERT)\b/i', $sql);
        }
    }

    public function testLongestSecretTheFormAcceptsFitsTheColumnOnceEncrypted(): void {
        $box = new SecretBox(SecretBox::generateKey());

        // A tela aceita até 64 caracteres; em UTF-8 isso pode chegar a 256 bytes.
        foreach ([str_repeat('x', 64), str_repeat('ç', 64), str_repeat('😀', 64)] as $secret) {
            $sealed = $box->seal($secret, SecretBox::CTX_WEBHOOK);

            self::assertTrue(SecretBox::isSealed($sealed));
            self::assertLessThanOrEqual(AdminWebhookModel::SECRET_COLUMN_SIZE, strlen($sealed));
            self::assertGreaterThan(64, strlen($sealed), 'e não caberia na coluna antiga');
        }
    }

    // ── partida ─────────────────────────────────────────────────────────────

    public function testFrameworkTakesTheKeyFromTheConstantAndKeepsItOutOfConfig(): void {
        $source = self::read('dev/system/framework.php');

        self::assertSame(1, substr_count($source, "\$registry->set('secret_box'"));
        self::assertStringContainsString("defined('REAMUR_SECRET_KEY') ? REAMUR_SECRET_KEY : ''", $source);
        self::assertStringContainsString("defined('REAMUR_SECRET_KEY_OLD') ? REAMUR_SECRET_KEY_OLD : ''", $source);
        self::assertStringContainsString("/catalog/config.php'", $source);
        self::assertDoesNotMatchRegularExpression('/\$config->set\([^)]*REAMUR_SECRET_KEY/', $source);

        // Antes do banco: nenhum model roda sem a cifra já registrada.
        self::assertLessThan(strpos($source, "\$registry->set('db'"), strpos($source, "\$registry->set('secret_box'"));
    }

    public function testAdminStartupOpensTheSmtpPasswordInMemoryOrFlagsIt(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $sealed = $box->seal('senha-smtp-0123456789', SecretBox::settingContext('mail_smtp_password'));

        $run = static function (?SecretBox $box, string $stored): Config {
            $registry = new Registry();
            $registry->set('config', new Config());
            $registry->set('load', new class { public function model(string $route): void {} });
            $registry->set('model_setting_setting', new class($stored) {
                public function __construct(private string $stored) {}
                /** @return array<string, string> */
                public function getSettings(): array { return ['mail_engine' => 'smtp', 'mail_smtp_password' => $this->stored]; }
            });

            if ($box !== null) {
                $registry->set('secret_box', $box);
            }

            (new AdminStartupSetting($registry))->index();

            return $registry->get('config');
        };

        self::assertSame('senha-smtp-0123456789', $run($box, $sealed)->get('mail_smtp_password'));
        self::assertSame('senha-legivel-antiga', $run($box, 'senha-legivel-antiga')->get('mail_smtp_password'));
        self::assertSame('senha-legivel-antiga', $run(null, 'senha-legivel-antiga')->get('mail_smtp_password'));

        foreach ([new SecretBox(SecretBox::generateKey()), null] as $other) {
            $config = $run($other, $sealed);
            self::assertSame('', (string)$config->get('mail_smtp_password', ''));
            self::assertTrue($config->get('mail_smtp_password_locked'));
        }
    }

    // ── telas: nenhum segredo volta para o navegador ────────────────────────

    public function testSecretFieldsAreWriteOnlyInTheTemplates(): void {
        $webhook = self::read('dev/admin/view/template/setting/webhook_form.twig');
        self::assertSame(1, preg_match('/<input[^>]*name="secret"[^>]*>/', $webhook, $m));
        self::assertStringContainsString('type="password"', $m[0]);
        self::assertStringContainsString('value=""', $m[0]);
        self::assertStringContainsString('data-rms-secret-locked="webhook"', $webhook);

        $mp = self::read('dev/admin/view/template/setting/marketplace.twig');

        foreach (['mp_client_secret', 'mp_webhook_secret'] as $name) {
            self::assertSame(1, preg_match('/<input[^>]*name="' . $name . '"[^>]*>/', $mp, $m), $name);
            self::assertStringContainsString('type="password"', $m[0], $name);
            self::assertDoesNotMatchRegularExpression('/value="[^"]*\{\{/', $m[0], $name . ' não devolve o valor gravado');
        }

        self::assertStringContainsString('data-rms-secret-locked="mp_webhook_secret"', $mp);
        self::assertStringContainsString('data-rms-token-locked', $mp);
        self::assertDoesNotMatchRegularExpression('/\{\{\s*s\.(access|refresh)_token/', $mp);

        $setting = self::read('dev/admin/view/template/setting/setting.twig');
        self::assertSame(1, preg_match('/<input[^>]*name="mail_smtp_password"[^>]*>/', $setting, $m));
        self::assertDoesNotMatchRegularExpression('/value="[^"]*\{\{/', $m[0]);
        self::assertSame(1, preg_match('/<form[^>]*action="\{\{ protect_secrets \}\}"[^>]*>.*?<\/form>/s', $setting, $form));
        self::assertStringContainsString('method="post"', $form[0]);
        self::assertStringContainsString('name="csrf_token"', $form[0]);

        $before = substr($setting, 0, (int)strpos($setting, $form[0]));
        self::assertStringEndsWith('{% if can_modify and secret_can_protect %}', rtrim($before));
    }

    public function testEveryStatusAndGroupHasItsTextInTheThreeLanguages(): void {
        $codes  = ['ok', 'pending', 'locked', 'missing', 'invalid', 'unavailable', 'peer_missing', 'peer_different', 'peer_unreadable'];
        $groups = ['mp_seller', 'webhook', 'mp_client_secret', 'mp_webhook_secret', 'mail_smtp_password'];
        $sets   = [];

        foreach (self::LANGUAGES as $language) {
            $load = static function (string $file): array {
                $_ = [];
                require $file;

                return $_;
            };

            $setting   = $load(self::repo('dev/admin/language/' . $language . '/setting/setting.php'));
            $dashboard = $load(self::repo('dev/admin/language/' . $language . '/common/dashboard.php'));
            $market    = $load(self::repo('dev/admin/language/' . $language . '/setting/marketplace.php'));
            $webhook   = $load(self::repo('dev/admin/language/' . $language . '/setting/webhook.php'));

            foreach ($codes as $code) {
                self::assertNotEmpty($setting['text_secret_status_' . $code] ?? '', $language . ' status ' . $code);
            }

            foreach ($groups as $group) {
                self::assertNotEmpty($setting['text_secret_group_' . $group] ?? '', $language . ' grupo ' . $group);
            }

            foreach (['fieldset_secrets', 'help_secrets', 'text_secret_locked_count', 'text_secret_key_line', 'text_secret_key_note', 'text_secret_none', 'button_protect_secrets', 'text_secrets_protected', 'text_secrets_nothing', 'text_secrets_webhook_column', 'error_secrets_not_ready', 'column_secret_item', 'column_secret_sealed', 'column_secret_clear', 'column_secret_old', 'column_secret_locked'] as $key) {
                self::assertNotEmpty($setting[$key] ?? '', $language . ' ' . $key);
            }

            foreach (['missing', 'unavailable', 'peer', 'invalid', 'pending', 'locked'] as $warning) {
                self::assertNotEmpty($dashboard['warning_secret_' . $warning] ?? '', $language . ' aviso ' . $warning);
            }

            self::assertNotEmpty($webhook['text_secret_locked'] ?? '', $language);
            self::assertNotEmpty($webhook['error_secret_locked'] ?? '', $language);
            self::assertStringContainsString('%d', $setting['text_secrets_protected']);
            self::assertStringContainsString('%d', $setting['text_secret_locked_count']);
            self::assertStringContainsString('upgrade_s9_webhook_secret.sql', $setting['text_secrets_webhook_column']);

            $pick = static fn (array $all): array => array_values(array_filter(array_keys($all), static fn (string $k): bool => str_contains($k, 'secret') || str_contains($k, 'locked')));
            $sets[$language] = [$pick($setting), $pick($dashboard), $pick($market), $pick($webhook)];
        }

        self::assertSame($sets['en-us'], $sets['pt-br'], 'mesmas chaves em inglês e português');
        self::assertSame($sets['en-us'], $sets['es-eu'], 'mesmas chaves em inglês e espanhol');
    }

    // ── ação "Proteger segredos" ────────────────────────────────────────────

    /**
     * @return array{0: SettingController, 1: Response, 2: Session, 3: object}
     */
    private function settingController(string $method, bool $canModify, ?SecretBox $box, bool $withToken, ?\Throwable $fail = null): array {
        $session = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $session->data = [];

        $request = new Request();
        $request->server = ['REQUEST_METHOD' => $method];
        $request->post   = $withToken ? ['csrf_token' => Csrf::token($session)] : ['csrf_token' => 'errado'];

        $model = new class($fail) {
            public int $calls = 0;
            public ?bool $columnReady = null;
            public function __construct(private ?\Throwable $fail) {}
            /** @return array{converted: int, skipped_webhook: int} */
            public function protect(bool $webhookColumnReady): array {
                $this->calls++;
                $this->columnReady = $webhookColumnReady;

                if ($this->fail !== null) {
                    throw $this->fail;
                }

                return ['converted' => 3, 'skipped_webhook' => $webhookColumnReady ? 0 : 2];
            }
        };

        $registry = new Registry();
        $response = new Response();
        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('session', $session);
        $registry->set('config', new Config());
        $registry->set('load', new class {
            public function language(string $route): void {}
            public function model(string $route): void {}
        });
        $registry->set('language', new class { public function get(string $key): string { return $key === 'text_secrets_protected' ? 'ok %d' : ($key === 'text_secrets_webhook_column' ? 'fora %d' : $key); } });
        $registry->set('url', new class { public function link(string $route): string { return 'https://example.test/admin/?route=' . $route; } });
        $registry->set('user', new class($canModify) {
            public function __construct(private bool $can) {}
            public function hasPermission(string $type, string $route): bool { return $this->can && $type === 'modify' && $route === 'setting/setting'; }
        });
        $registry->set('log', new class {
            /** @var list<string> */
            public array $lines = [];
            public function write(string $m): void { $this->lines[] = $m; }
        });
        $registry->set('model_setting_secret', $model);
        $registry->set('model_setting_webhook', new class {
            public bool $ready = true;
            public function secretColumnReady(): bool { return $this->ready; }
        });

        if ($box !== null) {
            $registry->set('secret_box', $box);
        }

        return [new SettingController($registry), $response, $session, $model];
    }

    public function testProtectSecretsRunsOnlyForAPostWithPermissionTokenAndKey(): void {
        $key = new SecretBox(SecretBox::generateKey());

        // GET: nada acontece.
        [$controller, $response, $session, $model] = $this->settingController('GET', true, $key, true);
        $controller->protectSecrets();
        self::assertSame(0, $model->calls);
        self::assertArrayNotHasKey('flash', $session->data);
        self::assertContains('Location: https://example.test/admin/?route=setting/setting', $response->getHeaders());

        // Sem permissão de modificar.
        [$controller, $response, , $model] = $this->settingController('POST', false, $key, true);
        $controller->protectSecrets();
        self::assertSame(0, $model->calls);
        self::assertContains('Location: https://example.test/admin/?route=error/permission', $response->getHeaders());

        // Token do formulário errado.
        [$controller, , $session, $model] = $this->settingController('POST', true, $key, false);
        $controller->protectSecrets();
        self::assertSame(0, $model->calls);
        self::assertSame(['type' => 'error', 'text' => 'error_csrf'], $session->data['flash']);

        // Sem chave utilizável.
        foreach ([null, new SecretBox('frase'), new SecretBox(SecretBox::generateKey(), '', sys_get_temp_dir() . '/s9-nao-existe.php')] as $box) {
            [$controller, , $session, $model] = $this->settingController('POST', true, $box, true);
            $controller->protectSecrets();
            self::assertSame(0, $model->calls);
            self::assertSame(['type' => 'error', 'text' => 'error_secrets_not_ready'], $session->data['flash']);
        }

        // Tudo certo.
        [$controller, $response, $session, $model] = $this->settingController('POST', true, $key, true);
        $controller->protectSecrets();
        self::assertSame(1, $model->calls);
        self::assertTrue($model->columnReady);
        self::assertSame(['type' => 'success', 'text' => 'ok 3'], $session->data['flash']);
        self::assertContains('Location: https://example.test/admin/?route=setting/setting', $response->getHeaders());
    }

    public function testProtectSecretsReportsSkippedWebhooksAndHidesDatabaseErrors(): void {
        $key = new SecretBox(SecretBox::generateKey());

        [$controller, , $session, $model] = $this->settingController('POST', true, $key, true);
        $controller->model_setting_webhook->ready = false;
        $controller->protectSecrets();
        self::assertFalse($model->columnReady);
        self::assertSame(['type' => 'warning', 'text' => 'ok 3 fora 2'], $session->data['flash']);

        // Erro do banco pode trazer o SQL com o valor: nem a tela nem o log o repetem.
        $boom = new \Exception("UPDATE rms_setting SET value = 'segredo-legivel-0123456789' falhou");
        [$controller, , $session] = $this->settingController('POST', true, $key, true, $boom);
        $controller->protectSecrets();
        self::assertSame(['type' => 'error', 'text' => 'error_secrets_not_ready'], $session->data['flash']);
        self::assertSame(['setting/setting.protectSecrets falhou: Exception'], $controller->log->lines);
    }

    // ── aviso no painel ─────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $status
     * @return list<string>
     */
    private function dashboardWarnings(array $status): array {
        $registry = new Registry();
        $registry->set('load', new class { public function model(string $route): void {} });
        $registry->set('language', new class { public function get(string $key): string { return $key; } });
        $registry->set('model_setting_secret', new class($status) {
            /** @param array<string, mixed> $status */
            public function __construct(private array $status) {}
            /** @return array<string, mixed> */
            public function status(): array {
                if ($this->status === []) {
                    throw new \RuntimeException('sem tabela');
                }

                return $this->status;
            }
        });

        $method = new \ReflectionMethod(DashboardController::class, 'secretWarnings');

        return $method->invoke(new DashboardController($registry));
    }

    public function testDashboardWarnsOnlyWhenThereIsSomethingToDo(): void {
        $s = static fn (string $code, int $clear = 0, int $locked = 0): array => ['code' => $code, 'clear' => $clear, 'locked' => $locked];

        self::assertSame([], $this->dashboardWarnings($s('ok')));
        self::assertSame([], $this->dashboardWarnings($s('missing')), 'sem chave e sem segredo gravado: silêncio');
        self::assertSame(['warning_secret_missing'], $this->dashboardWarnings($s('missing', 2)));
        self::assertSame([], $this->dashboardWarnings($s('unavailable')));
        self::assertSame(['warning_secret_unavailable'], $this->dashboardWarnings($s('unavailable', 1)));
        self::assertSame(['warning_secret_invalid'], $this->dashboardWarnings($s('invalid')));
        self::assertSame(['warning_secret_pending'], $this->dashboardWarnings($s('pending', 1)));

        foreach (['peer_missing', 'peer_different', 'peer_unreadable'] as $code) {
            self::assertSame(['warning_secret_peer'], $this->dashboardWarnings($s($code)));
        }

        self::assertSame(['warning_secret_locked'], $this->dashboardWarnings($s('locked', 0, 3)));
        self::assertSame(['warning_secret_pending', 'warning_secret_locked'], $this->dashboardWarnings($s('pending', 1, 1)));
        self::assertSame(['warning_secret_missing', 'warning_secret_locked'], $this->dashboardWarnings($s('missing', 1, 2)));
        self::assertSame([], $this->dashboardWarnings([]), 'inventário indisponível não derruba o painel');
    }
}
