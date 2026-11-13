<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Reamur\Admin\Model\Setting\Marketplace as AdminMarketplaceModel;
use Reamur\Admin\Model\Setting\Secret as AdminSecretModel;
use Reamur\Admin\Model\Setting\Setting as AdminSettingModel;
use Reamur\Admin\Model\Setting\Webhook as AdminWebhookModel;
use Reamur\Catalog\Controller\Checkout\Payment as CatalogPaymentController;
use Reamur\Catalog\Controller\Startup\Setting as CatalogStartupSetting;
use Reamur\Catalog\Model\Checkout\Mp_payment as CatalogMpPaymentModel;
use Reamur\Catalog\Model\Setting\Webhook as CatalogWebhookModel;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Mail\Factory as MailFactory;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\SecretBox;
use Reamur\System\Library\Webhook\Dispatcher;

/**
 * S9 (ADR 0094): os pontos que gravam e leem segredos. Cada teste olha o SQL
 * que sairia para o banco (o segredo nao pode estar nele) e o valor que chega
 * ao ponto de uso (tem de ser o segredo verdadeiro, ou vazio + aviso).
 */
final class SecretStorageTest extends TestCase {
    private const ACCESS  = 'APP_USR-token-de-acesso-0123456789';
    private const REFRESH = 'TG-token-de-renovacao-0123456789';
    private const HOOK    = 'segredo-do-webhook-0123456789';

    /** Banco de mentira: guarda o SQL e responde pelo roteiro dado. */
    private function db(?\Closure $answer = null): object {
        return new class($answer) {
            /** @var list<string> */
            public array $sql = [];

            public function __construct(private ?\Closure $answer) {}

            public function escape(string $value): string {
                return addslashes($value);
            }

            public function getLastId(): int {
                return 7;
            }

            public function query(string $sql): \stdClass {
                $this->sql[] = $sql;
                $rows = $this->answer !== null ? (array)($this->answer)($sql) : [];
                $res  = new \stdClass();
                $res->rows     = array_values($rows);
                $res->row      = $rows !== [] ? reset($rows) : [];
                $res->num_rows = count($rows);

                return $res;
            }

            public function all(): string {
                return implode("\n", $this->sql);
            }
        };
    }

    private function cache(): object {
        return new class {
            /** @var array<string, mixed> */
            public array $store = [];
            /** @var list<string> */
            public array $deleted = [];
            public function get(string $key): mixed { return $this->store[$key] ?? null; }
            public function set(string $key, mixed $value, int $expire = 0): bool { $this->store[$key] = $value; return true; }
            public function delete(string $key): bool { $this->deleted[] = $key; unset($this->store[$key]); return true; }
        };
    }

    private function log(): object {
        return new class {
            /** @var list<string> */
            public array $lines = [];
            public function write(string $message): void { $this->lines[] = $message; }
        };
    }

    private function registry(object $db, ?SecretBox $box): Registry {
        $registry = new Registry();
        $registry->set('db', $db);
        $registry->set('cache', $this->cache());
        $registry->set('config', new Config());

        if ($box !== null) {
            $registry->set('secret_box', $box);
        }

        return $registry;
    }

    // ── tokens do Mercado Pago ──────────────────────────────────────────────

    public function testSellerTokensGoToTheDatabaseEncrypted(): void {
        $box = new SecretBox(SecretBox::generateKey());
        $db  = $this->db();

        $model = new AdminMarketplaceModel($this->registry($db, $box));
        $model->upsertSeller([
            'mp_user_id' => 902, 'nickname' => 'n', 'email' => 'e@example.test',
            'access_token' => self::ACCESS, 'refresh_token' => self::REFRESH,
            'scope' => 'read', 'token_expires_at' => '2027-01-01 00:00:00',
        ]);
        $model->rotateTokens(3, self::ACCESS . '-novo', self::REFRESH . '-novo', '2027-06-01 00:00:00');

        self::assertStringNotContainsString(self::ACCESS, $db->all());
        self::assertStringNotContainsString(self::REFRESH, $db->all());
        self::assertSame(4, substr_count($db->all(), "'" . SecretBox::PREFIX), 'dois tokens no INSERT, dois no UPDATE');

        preg_match_all("/`(access_token|refresh_token)`\\s*= '([^']+)'/", $db->sql[0], $m);
        self::assertSame(['access_token', 'refresh_token'], $m[1]);
        self::assertSame(self::ACCESS, $box->open(stripslashes($m[2][0]), SecretBox::CTX_MP_ACCESS));
        self::assertSame(self::REFRESH, $box->open(stripslashes($m[2][1]), SecretBox::CTX_MP_REFRESH));
        self::assertSame('', $box->open(stripslashes($m[2][0]), SecretBox::CTX_MP_REFRESH), 'cada token preso a sua coluna');
    }

    public function testWithoutKeySellerTokensAreStoredAsBefore(): void {
        $db    = $this->db();
        $model = new AdminMarketplaceModel($this->registry($db, null));
        $model->rotateTokens(3, self::ACCESS, self::REFRESH, '2027-06-01 00:00:00');

        self::assertStringContainsString("'" . self::ACCESS . "'", $db->all());
        self::assertStringNotContainsString(SecretBox::PREFIX, $db->all());
    }

    public function testSellerIsReadWithUsableTokensOrFlaggedAsLocked(): void {
        $key    = SecretBox::generateKey();
        $box    = new SecretBox($key);
        $stored = [
            'mp_seller_id' => 3, 'mp_user_id' => 902, 'nickname' => 'n', 'status' => 1,
            'access_token'  => $box->seal(self::ACCESS, SecretBox::CTX_MP_ACCESS),
            'refresh_token' => $box->seal(self::REFRESH, SecretBox::CTX_MP_REFRESH),
        ];
        $answer = static fn (string $sql): array => str_contains($sql, 'mp_seller') ? [$stored] : [];

        $seller = (new AdminMarketplaceModel($this->registry($this->db($answer), $box)))->getSeller(3);
        self::assertSame(self::ACCESS, $seller['access_token']);
        self::assertSame(self::REFRESH, $seller['refresh_token']);
        self::assertFalse($seller['token_locked']);

        $active = (new AdminMarketplaceModel($this->registry($this->db($answer), $box)))->getActiveSellers();
        self::assertSame(self::ACCESS, $active[0]['access_token']);

        // A grade nunca recebe token, nem cifrado.
        $list = (new AdminMarketplaceModel($this->registry($this->db($answer), $box)))->getSellers();
        self::assertArrayNotHasKey('access_token', $list[0]);
        self::assertArrayNotHasKey('refresh_token', $list[0]);
        self::assertFalse($list[0]['token_locked']);

        // Chave errada: vazio + aviso, nunca o texto cifrado no lugar do token.
        $wrong  = new SecretBox(SecretBox::generateKey());
        $locked = (new AdminMarketplaceModel($this->registry($this->db($answer), $wrong)))->getSeller(3);
        self::assertSame('', $locked['access_token']);
        self::assertSame('', $locked['refresh_token']);
        self::assertTrue($locked['token_locked']);
        self::assertTrue((new AdminMarketplaceModel($this->registry($this->db($answer), $wrong)))->getSellers()[0]['token_locked']);

        // Formato antigo (legivel) continua valendo.
        $legacy = static fn (string $sql): array => [['mp_seller_id' => 3, 'access_token' => self::ACCESS, 'refresh_token' => self::REFRESH]];
        $old    = (new AdminMarketplaceModel($this->registry($this->db($legacy), $box)))->getSeller(3);
        self::assertSame(self::ACCESS, $old['access_token']);
        self::assertFalse($old['token_locked']);

        self::assertSame([], (new AdminMarketplaceModel($this->registry($this->db(), $box)))->getSeller(99), 'inexistente continua vazio');
    }

    public function testCatalogReadsSellerTokenForUseOrFlagsIt(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $row    = ['mp_seller_id' => 3, 'mp_user_id' => 902, 'nickname' => 'n', 'access_token' => $box->seal(self::ACCESS, SecretBox::CTX_MP_ACCESS)];
        $answer = static fn (string $sql): array => [$row];

        $model = new CatalogMpPaymentModel($this->registry($this->db($answer), $box));
        self::assertSame(self::ACCESS, $model->getSellerById(3)['access_token']);
        self::assertSame(self::ACCESS, $model->getSellerByMpUserId(902)['access_token']);
        self::assertSame(self::ACCESS, $model->getActiveSellers()[0]['access_token']);
        self::assertFalse($model->getActiveSellers()[0]['token_locked']);

        $wrong = new CatalogMpPaymentModel($this->registry($this->db($answer), new SecretBox(SecretBox::generateKey())));
        self::assertSame('', $wrong->getSellerById(3)['access_token']);
        self::assertTrue($wrong->getSellerById(3)['token_locked']);
        self::assertTrue($wrong->getActiveSellers()[0]['token_locked']);

        $noKey = new CatalogMpPaymentModel($this->registry($this->db($answer), null));
        self::assertSame('', $noKey->getSellerByMpUserId(902)['access_token'], 'sem chave, valor cifrado nao vira token');
        self::assertTrue($noKey->getSellerByMpUserId(902)['token_locked']);

        self::assertSame([], (new CatalogMpPaymentModel($this->registry($this->db(), $box)))->getSellerById(99));
    }

    // ── rms_setting ─────────────────────────────────────────────────────────

    public function testSettingsModelEncryptsOnlyTheSecretKeys(): void {
        $box = new SecretBox(SecretBox::generateKey());
        $db  = $this->db();

        (new AdminSettingModel($this->registry($db, $box)))->editSettings([
            'config_name'        => 'Loja de Teste',
            'mp_app_id'          => '1234567890',
            'mp_client_secret'   => 'segredo-do-aplicativo-0123456789',
            'mp_webhook_secret'  => 'assinatura-das-notificacoes-0123',
            'mail_smtp_password' => 'senha smtp com espaco ',
            'chave_fora_da_lista' => 'x',
        ]);

        $sql = $db->all();
        self::assertStringContainsString("'Loja de Teste'", $sql);
        self::assertStringContainsString("'1234567890'", $sql);
        self::assertStringNotContainsString('segredo-do-aplicativo-0123456789', $sql);
        self::assertStringNotContainsString('assinatura-das-notificacoes-0123', $sql);
        self::assertStringNotContainsString('senha smtp com espaco', $sql);
        self::assertStringNotContainsString('chave_fora_da_lista', $sql);
        self::assertStringContainsString("`key` = 'mp_webhook_secret'", $sql, 'segredo das notificacoes agora entra pela tela');

        preg_match("/SET `value` = '([^']+)',\\s*`serialized` = '0'\\s*WHERE `code` = 'config' AND `key` = 'mail_smtp_password'/", $sql, $m);
        self::assertSame('senha smtp com espaco ', $box->open(stripslashes($m[1] ?? ''), SecretBox::settingContext('mail_smtp_password')));
    }

    public function testSettingsModelKeepsAnAlreadyEncryptedValueUntouched(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $sealed = $box->seal('senha-smtp-0123456789', SecretBox::settingContext('mail_smtp_password'));
        $db     = $this->db();

        // A tela que "mantem a senha" devolve o que leu do banco.
        (new AdminSettingModel($this->registry($db, $box)))->editSettings(['mail_smtp_password' => $sealed]);

        self::assertStringContainsString("'" . addslashes($sealed) . "'", $db->all());
        self::assertSame(2, substr_count($db->all(), SecretBox::PREFIX), 'um UPDATE e um INSERT condicional, com o mesmo valor');
    }

    public function testSettingsModelWithoutKeyStoresAsBefore(): void {
        $db = $this->db();
        (new AdminSettingModel($this->registry($db, null)))->editSettings(['mp_client_secret' => 'segredo-do-aplicativo-0123456789']);

        self::assertStringContainsString("'segredo-do-aplicativo-0123456789'", $db->all());
    }

    public function testStartupOpensSmtpPasswordInMemoryOrFlagsIt(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $sealed = $box->seal('senha-smtp-0123456789', SecretBox::settingContext('mail_smtp_password'));
        $rows   = static fn (string $sql): array => [
            (object)['key' => 'mail_engine', 'value' => 'smtp', 'serialized' => 0],
            (object)['key' => 'mail_smtp_password', 'value' => $sealed, 'serialized' => 0],
        ];

        $run = function (?SecretBox $box) use ($rows): Config {
            $registry = $this->registry($this->db($rows), $box);
            $registry->set('model_setting_setting', new \Reamur\Catalog\Model\Setting\Setting($registry));
            $registry->set('load', new class { public function model(string $route): void {} });
            (new CatalogStartupSetting($registry))->index();

            return $registry->get('config');
        };

        $config = $run($box);
        self::assertSame('senha-smtp-0123456789', $config->get('mail_smtp_password'));
        self::assertFalse((bool)$config->get('mail_smtp_password_locked', false));

        $config = $run(new SecretBox(SecretBox::generateKey()));
        self::assertNotSame($sealed, $config->get('mail_smtp_password', ''), 'o texto cifrado nao vira senha');
        self::assertSame('', (string)$config->get('mail_smtp_password', ''));
        self::assertTrue($config->get('mail_smtp_password_locked'));
    }

    public function testMailFactoryRefusesSmtpWhenThePasswordCannotBeOpened(): void {
        $config = new Config();
        $config->set('mail_engine', 'smtp');
        $config->set('mail_smtp_password_locked', true);
        $mail = ['to' => 'a@example.test', 'from' => 'loja@example.test', 'sender' => 'Loja', 'subject' => 's', 'text' => 't'];

        try {
            MailFactory::create($config, $mail);
            self::fail('deveria recusar');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('REAMUR_SECRET_KEY', $e->getMessage());
        }

        // Outro motor de envio nao depende da senha.
        $config->set('mail_engine', 'mail');
        self::assertInstanceOf(\Reamur\System\Library\Mail\Mail::class, MailFactory::create($config, $mail));
    }

    // ── webhooks ────────────────────────────────────────────────────────────

    public function testWebhookSecretIsEncryptedWhenTheColumnHoldsIt(): void {
        $box = new SecretBox(SecretBox::generateKey());
        $db  = $this->db(static fn (string $sql): array => str_starts_with($sql, 'SHOW COLUMNS') ? [['Field' => 'secret', 'Type' => 'varchar(512)']] : []);

        $model = new AdminWebhookModel($this->registry($db, $box));
        $model->addWebhook(['trigger' => 'order_add', 'url' => 'https://example.test/h', 'secret' => self::HOOK, 'description' => 'd', 'status' => 1]);
        $model->editWebhook(7, ['trigger' => 'order_add', 'url' => 'https://example.test/h', 'secret' => self::HOOK, 'description' => 'd', 'status' => 1]);

        self::assertStringNotContainsString(self::HOOK, $db->all());
        self::assertSame(2, substr_count($db->all(), "`secret`      = '" . SecretBox::PREFIX));
        self::assertStringNotContainsString('ALTER TABLE', $db->all());
    }

    public function testNarrowWebhookColumnIsWidenedBeforeTheFirstEncryptedWrite(): void {
        $box = new SecretBox(SecretBox::generateKey());
        $db  = $this->db(static fn (string $sql): array => str_starts_with($sql, 'SHOW COLUMNS') ? [['Field' => 'secret', 'Type' => 'varchar(64)']] : []);

        $model = new AdminWebhookModel($this->registry($db, $box));
        $model->addWebhook(['trigger' => 'order_add', 'url' => 'https://example.test/h', 'secret' => self::HOOK, 'description' => 'd', 'status' => 1]);

        $alter  = array_values(array_filter($db->sql, static fn (string $s): bool => str_contains($s, 'ALTER TABLE')));
        $insert = array_values(array_filter($db->sql, static fn (string $s): bool => str_contains($s, 'INSERT INTO')));

        self::assertCount(1, $alter);
        self::assertStringContainsString('MODIFY `secret` VARCHAR(512) NOT NULL', $alter[0]);
        self::assertLessThan(array_search($insert[0], $db->sql, true), array_search($alter[0], $db->sql, true), 'alarga antes de gravar');
        self::assertStringContainsString(SecretBox::PREFIX, $insert[0]);
        self::assertStringNotContainsString(self::HOOK, $insert[0]);
    }

    public function testWhenTheColumnCannotBeWidenedTheSecretIsStoredReadableNotTruncated(): void {
        $box = new SecretBox(SecretBox::generateKey());
        $db  = $this->db(static function (string $sql): array {
            if (str_contains($sql, 'ALTER TABLE')) {
                throw new \Exception('ALTER command denied to user');
            }

            return str_starts_with($sql, 'SHOW COLUMNS') ? [['Field' => 'secret', 'Type' => 'varchar(64)']] : [];
        });
        $registry = $this->registry($db, $box);
        $log      = new \Reamur\System\Library\Log('s9-teste-' . bin2hex(random_bytes(4)) . '.log', sys_get_temp_dir() . '/');
        $registry->set('log', $log);

        $model = new AdminWebhookModel($registry);
        self::assertFalse($model->secretColumnReady());

        $model->addWebhook(['trigger' => 'order_add', 'url' => 'https://example.test/h', 'secret' => self::HOOK, 'description' => 'd', 'status' => 1]);

        $insert = array_values(array_filter($db->sql, static fn (string $s): bool => str_contains($s, 'INSERT INTO')));
        self::assertStringContainsString("`secret`      = '" . self::HOOK . "'", $insert[0]);
        self::assertStringNotContainsString(SecretBox::PREFIX, $insert[0]);
        self::assertStringContainsString('upgrade_s9_webhook_secret.sql', (string)file_get_contents($log->getFilePath()));
        @unlink($log->getFilePath());
    }

    public function testEditingAWebhookWithoutANewSecretLeavesTheColumnAlone(): void {
        $db    = $this->db();
        $model = new AdminWebhookModel($this->registry($db, new SecretBox(SecretBox::generateKey())));
        $model->editWebhook(7, ['trigger' => 'order_add', 'url' => 'https://example.test/h', 'secret' => null, 'description' => 'nova', 'status' => 1]);

        self::assertCount(1, $db->sql);
        self::assertStringNotContainsString('`secret`', $db->sql[0]);
        self::assertStringContainsString("`description` = 'nova'", $db->sql[0]);
        self::assertStringContainsString("WHERE `webhook_id` = '7'", $db->sql[0]);
    }

    public function testHooksForAnEventCarryTheOpenedSecretOrTheLockedFlag(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $sealed = $box->seal(self::HOOK, SecretBox::CTX_WEBHOOK);
        $answer = static fn (string $sql): array => [
            ['webhook_id' => 1, 'url' => 'https://example.test/a', 'secret' => $sealed],
            ['webhook_id' => 2, 'url' => 'https://example.test/b', 'secret' => 'segredo-antigo-legivel-0123'],
        ];

        foreach ([AdminWebhookModel::class, CatalogWebhookModel::class] as $class) {
            $hooks = (new $class($this->registry($this->db($answer), $box)))->getHooksForEvent('order_add');
            self::assertSame(self::HOOK, $hooks[0]['secret'], $class);
            self::assertFalse($hooks[0]['secret_locked'], $class);
            self::assertSame('segredo-antigo-legivel-0123', $hooks[1]['secret'], $class);
            self::assertFalse($hooks[1]['secret_locked'], $class);

            $wrong = (new $class($this->registry($this->db($answer), new SecretBox(SecretBox::generateKey()))))->getHooksForEvent('order_add');
            self::assertSame('', $wrong[0]['secret'], $class);
            self::assertTrue($wrong[0]['secret_locked'], $class);
            self::assertFalse($wrong[1]['secret_locked'], $class);
        }
    }

    public function testDispatcherDoesNotSendWhenTheSecretCannotBeOpened(): void {
        $log     = $this->log();
        $results = (new Dispatcher($log))->dispatch('order_add', ['order_id' => 1], [
            ['webhook_id' => 9, 'url' => 'https://example.test/hook', 'secret' => '', 'secret_locked' => true],
        ]);

        self::assertCount(1, $results, 'uma linha no log de entregas, sem novas tentativas');
        self::assertSame(9, $results[0]['webhook_id']);
        self::assertSame(0, $results[0]['http_code']);
        self::assertSame(0.0, $results[0]['duration']);
        self::assertCount(1, $log->lines);
        self::assertStringContainsString('#9', $log->lines[0]);
        self::assertStringContainsString('REAMUR_SECRET_KEY', $log->lines[0]);
    }

    // ── inventario e conversao ──────────────────────────────────────────────

    /** @return \Closure(string): array<int, array<string, mixed>> */
    private function inventory(array $settings, array $sellers, array $webhooks): \Closure {
        return static function (string $sql) use ($settings, $sellers, $webhooks): array {
            if (str_starts_with($sql, 'SHOW TABLES')) {
                return [['t' => 1]];
            }

            if (str_starts_with(ltrim($sql), 'SELECT')) {
                return match (true) {
                    str_contains($sql, 'rms_setting')   => $settings,
                    str_contains($sql, 'rms_mp_seller') => $sellers,
                    str_contains($sql, 'rms_webhook')   => $webhooks,
                    default                             => [],
                };
            }

            return [];
        };
    }

    public function testInventoryCountsStatesWithoutExposingValues(): void {
        $oldKey = SecretBox::generateKey();
        $newKey = SecretBox::generateKey();
        $old    = new SecretBox($oldKey);
        $box    = new SecretBox($newKey, $oldKey);
        $alien  = new SecretBox(SecretBox::generateKey());

        $answer = $this->inventory(
            [
                ['setting_id' => 1, 'key' => 'mp_client_secret', 'value' => 'segredo-legivel-0123456789'],
                ['setting_id' => 2, 'key' => 'mail_smtp_password', 'value' => $box->seal('senha-0123456789', SecretBox::settingContext('mail_smtp_password'))],
            ],
            [['mp_seller_id' => 3, 'access_token' => $old->seal(self::ACCESS, SecretBox::CTX_MP_ACCESS), 'refresh_token' => '']],
            [['webhook_id' => 5, 'secret' => $alien->seal(self::HOOK, SecretBox::CTX_WEBHOOK)]],
        );

        $status = (new AdminSecretModel($this->registry($this->db($answer), $box)))->status();

        self::assertSame(['clear' => 1, 'sealed' => 0, 'old' => 0, 'locked' => 0], $status['groups']['mp_client_secret']);
        self::assertSame(['clear' => 0, 'sealed' => 1, 'old' => 0, 'locked' => 0], $status['groups']['mail_smtp_password']);
        self::assertSame(['clear' => 0, 'sealed' => 0, 'old' => 1, 'locked' => 0], $status['groups']['mp_seller']);
        self::assertSame(['clear' => 0, 'sealed' => 0, 'old' => 0, 'locked' => 1], $status['groups']['webhook']);
        self::assertSame([1, 1, 1, 1, 4], [$status['clear'], $status['sealed'], $status['old'], $status['locked'], $status['total']]);
        self::assertSame('pending', $status['code']);
        self::assertTrue($status['can_protect']);

        $flat = json_encode($status);
        self::assertStringNotContainsString('segredo-legivel', (string)$flat);
        self::assertStringNotContainsString(SecretBox::PREFIX, (string)$flat);
    }

    public function testStatusCodeFollowsKeyPeerAndContent(): void {
        $key   = SecretBox::generateKey();
        $box   = new SecretBox($key);
        $clear = $this->inventory([['setting_id' => 1, 'key' => 'mp_client_secret', 'value' => 'segredo-legivel-0123456789']], [], []);
        $done  = $this->inventory([['setting_id' => 1, 'key' => 'mp_client_secret', 'value' => $box->seal('segredo-0123456789', SecretBox::settingContext('mp_client_secret'))]], [], []);
        $none  = $this->inventory([], [], []);

        $code = fn (\Closure $rows, SecretBox $b): string => (string)(new AdminSecretModel($this->registry($this->db($rows), $b)))->status()['code'];

        $cfg = function (string $body): string {
            $file = (string)tempnam(sys_get_temp_dir(), 's9cfg');
            file_put_contents($file, "<?php\n" . $body . "\n");

            return $file;
        };
        $same      = $cfg("define('REAMUR_SECRET_KEY', '" . $key . "');");
        $different = $cfg("define('REAMUR_SECRET_KEY', '" . SecretBox::generateKey() . "');");
        $missing   = $cfg("define('DB_PREFIX', 'rms_');");

        self::assertSame('missing', $code($clear, new SecretBox('')));
        self::assertSame('invalid', $code($clear, new SecretBox('uma frase')));
        self::assertSame('pending', $code($clear, $box));
        self::assertSame('ok', $code($done, $box));
        self::assertSame('ok', $code($none, $box));
        self::assertSame('ok', $code($done, new SecretBox($key, '', $same)));
        self::assertSame('peer_different', $code($done, new SecretBox($key, '', $different)));
        self::assertSame('peer_missing', $code($done, new SecretBox($key, '', $missing)));
        self::assertSame('peer_unreadable', $code($done, new SecretBox($key, '', $missing . '.nao-existe')));
        self::assertSame('locked', $code($done, new SecretBox(SecretBox::generateKey())));

        $noKey = (new AdminSecretModel($this->registry($this->db($clear), new SecretBox(''))))->status();
        self::assertFalse($noKey['can_protect']);
        $peerOff = (new AdminSecretModel($this->registry($this->db($clear), new SecretBox($key, '', $missing))))->status();
        self::assertFalse($peerOff['can_protect']);

        foreach ([$same, $different, $missing] as $file) {
            @unlink($file);
        }
    }

    public function testProtectEncryptsClearAndOldValuesAndLeavesTheRestAlone(): void {
        $oldKey = SecretBox::generateKey();
        $newKey = SecretBox::generateKey();
        $old    = new SecretBox($oldKey);
        $box    = new SecretBox($newKey, $oldKey);
        $alien  = new SecretBox(SecretBox::generateKey());

        $alreadyDone = $box->seal('senha-0123456789', SecretBox::settingContext('mail_smtp_password'));
        $lockedValue = $alien->seal('outro-0123456789abc', SecretBox::CTX_WEBHOOK);
        $oldValue    = $old->seal(self::REFRESH, SecretBox::CTX_MP_REFRESH);

        $db = $this->db($this->inventory(
            [
                ['setting_id' => 1, 'key' => 'mp_client_secret', 'value' => 'segredo-legivel-0123456789'],
                ['setting_id' => 2, 'key' => 'mail_smtp_password', 'value' => $alreadyDone],
            ],
            [['mp_seller_id' => 3, 'access_token' => self::ACCESS, 'refresh_token' => $oldValue]],
            [['webhook_id' => 5, 'secret' => self::HOOK], ['webhook_id' => 6, 'secret' => $lockedValue]],
        ));
        $registry = $this->registry($db, $box);

        $result  = (new AdminSecretModel($registry))->protect(true);
        $updates = array_values(array_filter($db->sql, static fn (string $s): bool => str_starts_with(ltrim($s), 'UPDATE')));

        self::assertSame(['converted' => 4, 'skipped_webhook' => 0], $result);
        self::assertCount(4, $updates);
        self::assertSame(['setting.config'], $registry->get('cache')->deleted, 'o cache de configuracoes e descartado');

        $byTable = [];
        foreach ($updates as $sql) {
            preg_match("/UPDATE `rms_(\\w+)`\\s+SET `(\\w+)` = '([^']+)'\\s+WHERE `(\\w+)` = '(\\d+)'\\s+AND `(\\w+)` = '([^']+)'/", $sql, $m);
            self::assertNotEmpty($m, $sql);
            self::assertSame($m[2], $m[6], 'so troca se o valor ainda for o que foi lido');
            $byTable[$m[1] . '.' . $m[2] . '#' . $m[5]] = [stripslashes($m[3]), stripslashes($m[7])];
        }

        $only = new SecretBox($newKey);
        self::assertSame('segredo-legivel-0123456789', $only->open($byTable['setting.value#1'][0], SecretBox::settingContext('mp_client_secret')));
        self::assertSame('segredo-legivel-0123456789', $byTable['setting.value#1'][1]);
        self::assertSame(self::ACCESS, $only->open($byTable['mp_seller.access_token#3'][0], SecretBox::CTX_MP_ACCESS));
        self::assertSame(self::REFRESH, $only->open($byTable['mp_seller.refresh_token#3'][0], SecretBox::CTX_MP_REFRESH));
        self::assertSame($oldValue, $byTable['mp_seller.refresh_token#3'][1]);
        self::assertSame(self::HOOK, $only->open($byTable['webhook.secret#5'][0], SecretBox::CTX_WEBHOOK));
        self::assertArrayNotHasKey('setting.value#2', $byTable, 'ja cifrado com a chave atual: intocado');
        self::assertArrayNotHasKey('webhook.secret#6', $byTable, 'nao abre: intocado, nao apagado');
    }

    public function testProtectSkipsWebhooksWhileTheColumnIsNarrow(): void {
        $box = new SecretBox(SecretBox::generateKey());
        $db  = $this->db($this->inventory(
            [['setting_id' => 1, 'key' => 'mp_client_secret', 'value' => 'segredo-legivel-0123456789']],
            [],
            [['webhook_id' => 5, 'secret' => self::HOOK]],
        ));

        $result = (new AdminSecretModel($this->registry($db, $box)))->protect(false);

        self::assertSame(['converted' => 1, 'skipped_webhook' => 1], $result);
        self::assertStringNotContainsString('UPDATE `rms_webhook`', $db->all());
    }

    public function testProtectDoesNothingWithoutAUsableKey(): void {
        $rows = $this->inventory([['setting_id' => 1, 'key' => 'mp_client_secret', 'value' => 'segredo-legivel-0123456789']], [], []);

        foreach ([new SecretBox(''), new SecretBox('frase'), new SecretBox(SecretBox::generateKey(), '', sys_get_temp_dir() . '/s9-nao-existe.php')] as $box) {
            $db = $this->db($rows);
            self::assertSame(['converted' => 0, 'skipped_webhook' => 0], (new AdminSecretModel($this->registry($db, $box)))->protect(true));
            self::assertSame([], $db->sql, 'sem chave utilizável o banco nem é consultado');
        }
    }

    // ── notificacao do Mercado Pago (catalogo) ──────────────────────────────

    /**
     * @param list<array<string, mixed>> $sellers
     * @return array{0: string, 1: list<string>, 2: list<string>} corpo, cabecalhos, log
     */
    private function notify(?SecretBox $box, string $storedSignatureSecret, array $sellers, string $signatureHeader = '', string $requestId = ''): array {
        $db = $this->db(static fn (string $sql): array => str_contains($sql, 'setting')
            ? [['key' => 'mp_webhook_secret', 'value' => $storedSignatureSecret]]
            : []);

        $registry = $this->registry($db, $box);
        $log      = $this->log();
        $request  = new Request();
        $request->get    = ['type' => 'payment', 'data_id' => '777001'];
        $request->post   = [];
        $request->server = ['HTTP_X_SIGNATURE' => $signatureHeader, 'HTTP_X_REQUEST_ID' => $requestId];
        $response = new Response();

        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('log', $log);
        $registry->set('load', new class {
            public function language(string $route): void {}
            public function model(string $route): void {}
        });
        $registry->set('model_checkout_mp_payment', new class($sellers) {
            /** @param list<array<string, mixed>> $sellers */
            public function __construct(private array $sellers) {}
            /** @return array<string, mixed> */
            public function getSellerByMpUserId(int $id): array { return []; }
            /** @return list<array<string, mixed>> */
            public function getActiveSellers(): array { return $this->sellers; }
        });

        (new CatalogPaymentController($registry))->notify();

        return [$response->getOutput(), $response->getHeaders(), $log->lines];
    }

    public function testNotifyWithASignatureSecretThatCannotBeOpenedAsksForRetryInsteadOfSkippingTheCheck(): void {
        $sealed = (new SecretBox(SecretBox::generateKey()))->seal('assinatura-0123456789abcdef', SecretBox::settingContext('mp_webhook_secret'));

        foreach ([new SecretBox(SecretBox::generateKey()), null] as $box) {
            [$body, $headers, $log] = $this->notify($box, $sealed, []);

            self::assertSame('retry:secret-locked', $body);
            self::assertContains('HTTP/1.1 503 Service Unavailable', $headers);
            self::assertContains('Retry-After: 3600', $headers);
            self::assertCount(1, $log);
            self::assertStringContainsString('catalog/config.php', $log[0]);
        }
    }

    public function testNotifyChecksTheSignatureWithTheOpenedSecret(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $secret = 'assinatura-0123456789abcdef';
        $sealed = $box->seal($secret, SecretBox::settingContext('mp_webhook_secret'));
        $ts     = '1700000000';
        $good   = hash_hmac('sha256', 'id:777001;request-id:req-1;ts:' . $ts . ';', $secret);

        // Assinatura feita com o segredo verdadeiro: aceita (segue adiante; sem vendedor = not-ours).
        [$body, $headers] = $this->notify($box, $sealed, [], 'ts=' . $ts . ',v1=' . $good, 'req-1');
        self::assertSame('ok:not-ours', $body);
        self::assertNotContains('HTTP/1.1 503 Service Unavailable', $headers);

        // Assinatura feita com o TEXTO CIFRADO como se fosse o segredo: recusada.
        $withCipher = hash_hmac('sha256', 'id:777001;request-id:req-1;ts:' . $ts . ';', $sealed);
        self::assertSame('ok:signature-rejected', $this->notify($box, $sealed, [], 'ts=' . $ts . ',v1=' . $withCipher, 'req-1')[0]);

        // Sem cabecalho de assinatura, com segredo configurado: recusada.
        self::assertSame('ok:signature-rejected', $this->notify($box, $sealed, [])[0]);

        // Segredo antigo, legivel no banco: continua valendo.
        self::assertSame('ok:not-ours', $this->notify($box, $secret, [], 'ts=' . $ts . ',v1=' . $good, 'req-1')[0]);
    }

    public function testNotifyWithLockedSellerTokenAsksForRetryAndCallsNoOne(): void {
        // token vazio + token_locked: e o que o model do catalogo devolve quando a chave nao abre.
        [$body, $headers, $log] = $this->notify(new SecretBox(SecretBox::generateKey()), '', [
            ['mp_seller_id' => 3, 'mp_user_id' => 902, 'nickname' => 'n', 'access_token' => '', 'token_locked' => true],
        ]);

        self::assertSame('retry:token-locked', $body);
        self::assertContains('HTTP/1.1 503 Service Unavailable', $headers);
        self::assertCount(1, $log);
        self::assertStringContainsString('token de vendedor', $log[0]);
    }

    public function testNotifyWithoutSecretsKeepsTheOldAnswer(): void {
        [$body, $headers] = $this->notify(null, '', []);

        self::assertSame('ok:not-ours', $body);
        self::assertNotContains('HTTP/1.1 503 Service Unavailable', $headers);
    }
}
