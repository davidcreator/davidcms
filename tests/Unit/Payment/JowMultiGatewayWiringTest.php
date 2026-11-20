<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Payments\Jow as AdminJowController;
use Reamur\Admin\Model\Payments\Jow as AdminJowModel;
use Reamur\Catalog\Controller\Checkout\Gateway as CatalogGatewayController;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Security\SecretBox;
use Reamur\System\Library\Session;

/**
 * Wiring dos fluxos multi-gateway nos controllers reais (admin payments/jow e
 * webhook público checkout/gateway.notify), com banco falso e NENHUMA chamada
 * de rede: os caminhos de provedor falham/decidem sempre antes do transporte.
 */
final class JowMultiGatewayWiringTest extends TestCase {
    private const SITE_URL = 'https://loja.example/admin/';

    // ── banco falso roteado por tabela/intenção ─────────────────────────────

    /** @return object Banco de mentira com estado de gateways e cobranças. */
    private function db(array $options = []): object {
        return new class($options) {
            /** @var list<string> */
            public array $queries = [];
            /** @var list<string> */
            public array $chargeInserts = [];
            /** @var array<int, string> gateway_id => credencial cifrada entregue ao store */
            public array $credentialsByGateway = [];
            /** @var array<string, int> provider_code => gateway_id configurado */
            public array $platformIds;
            /** @var array<int, array{provider_code: string, status: int, environment: string}> */
            public array $accounts;
            public int $nextGatewayId = 7;
            public int $chargeLastId = 42;
            public string $chargeMethod = '';
            /** @var list<int> */
            public array $createdGatewayIds = [];

            public function __construct(private array $options) {
                $this->platformIds = $options['platformIds'] ?? [];
                $this->accounts = $options['accounts'] ?? [];
                $this->credentialsByGateway = $options['credentials'] ?? [];
            }

            public function escape(string $value): string {
                return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
            }

            public function getLastId(): int {
                return $this->chargeMethod !== '' ? $this->chargeLastId : $this->nextGatewayId;
            }

            public function query(string $sql): \stdClass|bool {
                $this->queries[] = $sql;

                if (str_starts_with($sql, 'SHOW TABLES')) {
                    return $this->result([['t' => 'x']]);
                }

                if (str_starts_with($sql, 'INSERT INTO `rms_jow_gateway`')) {
                    $id = $this->nextGatewayId;
                    $this->createdGatewayIds[] = $id;
                    $provider = '';
                    $environment = 'sandbox';

                    if (preg_match("/`provider_code` = '([a-z0-9_]+)'/", $sql, $m) === 1) {
                        $provider = $m[1];
                    }

                    if (preg_match("/`environment` = '([a-z]+)'/", $sql, $m) === 1) {
                        $environment = $m[1];
                    }

                    $this->accounts[$id] = ['provider_code' => $provider, 'status' => 0, 'environment' => $environment];

                    return true;
                }

                if (str_starts_with($sql, 'UPDATE `rms_jow_gateway`')) {
                    if (preg_match("/`credentials_encrypted_json` = '((?:\\\\'|[^'])*)'/", $sql, $m) === 1) {
                        $id = $this->createdGatewayIds ? end($this->createdGatewayIds) : (int)($this->options['firstAccountId'] ?? 7);
                        $this->credentialsByGateway[$id] = str_replace("\\'", "'", $m[1]);
                    }

                    return true;
                }

                if (str_starts_with($sql, 'INSERT INTO `rms_jow_charge`')) {
                    $this->chargeInserts[] = $sql;

                    if (preg_match("/VALUES \('(?:[^']*)',[\d.]+,'([a-z0-9_]+)'/", $sql, $m) === 1) {
                        $this->chargeMethod = $m[1];
                    }

                    return true;
                }

                if (str_starts_with($sql, 'SELECT')) {
                    if (str_contains($sql, 'public_config_json')) {
                        // DbGatewayAccountStore::find()
                        $id = $this->intParam($sql);
                        $account = $this->accounts[$id] ?? null;

                        return $account === null
                            ? $this->result([])
                            : $this->result([[
                                'gateway_id' => $id,
                                'provider_code' => $account['provider_code'],
                                'account_label' => 'JOW platform',
                                'environment' => $account['environment'],
                                'status' => $account['status'],
                                'public_config_json' => '{"flow":"jow_internal"}',
                            ]]);
                    }

                    if (str_contains($sql, 'credentials_encrypted_json')) {
                        // DbGatewayAccountStore::credentials()
                        $id = $this->intParam($sql);

                        return isset($this->credentialsByGateway[$id])
                            ? $this->result([[
                                'provider_code' => $this->accounts[$id]['provider_code'] ?? 'stripe',
                                'credentials_encrypted_json' => $this->credentialsByGateway[$id],
                            ]])
                            : $this->result([]);
                    }

                    if (str_contains($sql, '`provider_code` =') && str_contains($sql, 'rms_jow_gateway')) {
                        // Model::getPlatformGatewayId()
                        $provider = '';

                        if (preg_match("/`provider_code` = '([a-z0-9_]+)'/", $sql, $m) === 1) {
                            $provider = $m[1];
                        }

                        return isset($this->platformIds[$provider])
                            ? $this->result([['gateway_id' => $this->platformIds[$provider]]])
                            : $this->result([]);
                    }

                    if (str_contains($sql, 'rms_jow_charge') && str_contains($sql, 'WHERE')) {
                        // Model::getCharge()
                        return $this->chargeMethod === ''
                            ? $this->result([])
                            : $this->result([[
                                'charge_id' => $this->chargeLastId,
                                'description' => 'Cobrança wiring',
                                'amount' => '100.00',
                                'currency' => 'BRL',
                                'method' => $this->chargeMethod,
                                'status' => 'pending',
                                'payer_email' => '',
                                'pix_payload' => null,
                                'date_added' => '2026-11-19 00:00:00',
                            ]]);
                    }

                    return $this->result([]); // ledger, PIX, relatório
                }

                return true;
            }

            private function intParam(string $sql): int {
                return preg_match("/`gateway_id` = '(\d+)'/", $sql, $m) === 1 ? (int)$m[1] : 0;
            }

            /** @param list<array<string, mixed>> $rows */
            private function result(array $rows): \stdClass {
                $query = new \stdClass();
                $query->rows = $rows;
                $query->row = $rows[0] ?? [];
                $query->num_rows = count($rows);

                return $query;
            }
        };
    }

    // ── harness do controller admin ─────────────────────────────────────────

    /** @param array<string, mixed> $post */
    private function admin(object $db, array $post, ?SecretBox $box = null): array {
        $harness = $this->adminHarness($db, $post, $box);
        (new AdminJowController($harness['registry']))->save();

        return [$harness['response'], $harness['session'], $db];
    }

    private function adminHarness(object $db, array $post, ?SecretBox $box = null): array {
        $session = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $session->data = [];

        $request = new Request();
        $request->server = ['REQUEST_METHOD' => 'POST'];
        $request->post = ['csrf_token' => Csrf::token($session)] + $post;
        $request->get = [];

        $response = new Response();
        $config = new Config();
        $config->set('site_url', self::SITE_URL);

        $registry = new Registry();
        $registry->set('db', $db);
        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('session', $session);
        $registry->set('config', $config);
        $registry->set('log', new class {
            /** @var list<string> */
            public array $lines = [];
            public function write(string $m): void { $this->lines[] = $m; }
        });
        $registry->set('url', new class {
            public function link(string $route): string { return 'https://loja.example/admin/?route=' . $route; }
        });
        $registry->set('user', new class($registry) {
            public function __construct(private Registry $registry) {}
            public function hasPermission(string $type, string $route): bool { return $type === 'modify' && $route === 'payments/jow'; }
            public function csrfValid(): bool {
                $session = $this->registry->get('session');
                $request = $this->registry->get('request');
                $token = ($request instanceof Request) ? (string)($request->post['csrf_token'] ?? '') : '';

                return Csrf::check($session, $token);
            }
        });
        $registry->set('language', new class {
            public function get(string $key): string { return $key . ' [%s]'; }
        });
        $registry->set('secret_box', $box ?? new SecretBox(SecretBox::generateKey()));
        $registry->set('load', new class($registry) {
            public function __construct(private Registry $registry) {}
            public function language(string $route): void {}
            public function model(string $route): void {
                if ($route === 'payments/jow') {
                    $this->registry->set('model_payments_jow', new AdminJowModel($this->registry));
                }
            }
        });

        return ['registry' => $registry, 'response' => $response, 'session' => $session];
    }

    // ── admin: cobranças ─────────────────────────────────────────────────────

    public function testChargeWithUnknownProviderIsRejectedWithoutInserting(): void {
        $db = $this->db();

        [, $session, $db] = $this->admin($db, [
            'action' => 'charge_add',
            'provider' => 'unknown_gw',
            'description' => 'Cobrança',
            'amount' => '100.00',
        ]);

        self::assertSame([], $db->chargeInserts, 'provedor inválido não pode gravar cobrança');
        self::assertStringStartsWith('error_method', $session->data['error'] ?? '');
    }

    public function testStripeChargeIsPersistedThenFailsClosedOnDisabledAccount(): void {
        $db = $this->db([
            'platformIds' => ['stripe' => 7],
            'accounts' => [7 => ['provider_code' => 'stripe', 'status' => 0, 'environment' => 'sandbox']],
        ]);

        [$response, $session, $db] = $this->admin($db, [
            'action' => 'charge_add',
            'provider' => 'stripe',
            'description' => 'Cobrança wiring',
            'amount' => '100.00',
        ]);

        self::assertCount(1, $db->chargeInserts, 'a cobrança JOW é gravada antes do gateway');
        self::assertStringContainsString(",'stripe',", $db->chargeInserts[0]);
        self::assertStringStartsWith('error_gateway_link_failed', $session->data['error'] ?? '');
        self::assertStringContainsString('Location: https://loja.example/admin/?route=payments/jow', implode("\n", $response->getHeaders()));
    }

    public function testStripeChargeFailsClosedOnLockedCredentials(): void {
        $vault = new \Reamur\System\Library\Payment\GatewayCredentialVault(new SecretBox(SecretBox::generateKey()));
        $locked = $vault->sealAll(7, 'stripe', ['secret_key' => 'locked', 'webhook_secret' => 'locked']);

        $db = $this->db([
            'platformIds' => ['stripe' => 7],
            'accounts' => [7 => ['provider_code' => 'stripe', 'status' => 1, 'environment' => 'sandbox']],
            'credentials' => [7 => $locked],
        ]);

        [, $session, $db] = $this->admin($db, [
            'action' => 'charge_add',
            'provider' => 'stripe',
            'description' => 'Cobrança wiring',
            'amount' => '100.00',
        ]);

        self::assertCount(1, $db->chargeInserts);
        self::assertStringStartsWith('error_gateway_link_failed', $session->data['error'] ?? '');
        self::assertSame([], array_filter($db->queries, static fn ($q) => str_contains($q, 'api.stripe.com')));
    }

    public function testLegacyChargeMpLinkActionStillRoutesToMercadoPago(): void {
        $db = $this->db(['platformIds' => ['mp' => 0]]);

        [, $session, $db] = $this->admin($db, [
            'action' => 'charge_mp_link',
            'charge_id' => '42',
        ]);

        self::assertStringStartsWith('error_gateway_link_failed', $session->data['error'] ?? '');
        self::assertSame([], array_filter($db->queries, static fn ($q) => str_contains($q, 'mercadopago')));
    }

    // ── admin: contas por provedor ───────────────────────────────────────────

    public function testGatewayAccountSaveCreatesStripeAccountAndEnablesWithAllSecrets(): void {
        $db = $this->db(['platformIds' => []]);

        [, $session, $db] = $this->admin($db, [
            'action' => 'gateway_account_save',
            'gateway_provider' => 'stripe',
            'gateway_environment' => 'sandbox',
            'gateway_enabled' => '1',
            'secret_secret_key' => 'sk_test_0123456789abcdef',
            'secret_webhook_secret' => 'whsec_0123456789abcdef',
        ]);

        $allSql = implode("\n", $db->queries);

        self::assertStringStartsWith('text_gateway_account_enabled', $session->data['success'] ?? '');
        self::assertStringNotContainsString('sk_test_0123456789abcdef', $allSql, 'segredo nunca vai para o SQL em plaintext');
        self::assertStringNotContainsString('whsec_0123456789abcdef', $allSql);
        self::assertSame([7], $db->createdGatewayIds);
        self::assertStringContainsString('rmsenc:v1:', $db->credentialsByGateway[7] ?? '');
    }

    public function testPaypalAccountCannotBeEnabledWithoutAllSecrets(): void {
        $db = $this->db(['platformIds' => []]);

        [, $session, $db] = $this->admin($db, [
            'action' => 'gateway_account_save',
            'gateway_provider' => 'paypal',
            'gateway_environment' => 'sandbox',
            'gateway_enabled' => '1',
            'secret_client_id' => 'Ae0123456789abcdefCLIENTID',
        ]);

        self::assertStringStartsWith('error_gateway_credentials_required', $session->data['error'] ?? '');

        $updates = array_values(array_filter($db->queries, static fn ($q) => str_starts_with($q, 'UPDATE `rms_jow_gateway`')));
        self::assertNotEmpty($updates);
        self::assertStringContainsString("`status` = '0'", end($updates), 'a conta permanece desativada');
    }

    public function testLegacyMpActionStillCreatesTheMercadoPagoAccount(): void {
        $db = $this->db(['platformIds' => []]);

        [, $session, $db] = $this->admin($db, [
            'action' => 'mp_account_save',
            'gateway_environment' => 'sandbox',
            'gateway_enabled' => '0',
            'secret_access_token' => 'APP_USR_0123456789abcdef',
            'secret_webhook_secret' => 'mp-secret-0123456789',
        ]);

        self::assertStringStartsWith('text_gateway_account_saved', $session->data['success'] ?? '');
        self::assertSame([7], $db->createdGatewayIds);
        $inserts = array_values(array_filter($db->queries, static fn ($q) => str_starts_with($q, 'INSERT INTO `rms_jow_gateway`')));
        self::assertStringContainsString("'mp'", $inserts[0] ?? '');
    }

    public function testUnknownGatewayProviderIsRejected(): void {
        $db = $this->db();

        [, $session, $db] = $this->admin($db, [
            'action' => 'gateway_account_save',
            'gateway_provider' => 'arbitrary_class',
            'gateway_enabled' => '1',
        ]);

        self::assertStringStartsWith('error_gateway_account_save', $session->data['error'] ?? '');
        self::assertSame([], $db->createdGatewayIds);
    }

    // ── webhook público ──────────────────────────────────────────────────────

    /** @param array<string, mixed> $get */
    private function catalog(object $db, array $get, array $server = [], string $body = '', ?SecretBox $box = null): array {
        $request = new Request();
        $request->get = $get;
        $request->post = [];
        $request->server = $server;

        $response = new Response();
        $registry = new Registry();
        $registry->set('db', $db);
        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('log', new class {
            /** @var list<string> */
            public array $lines = [];
            public function write(string $m): void { $this->lines[] = $m; }
        });
        $registry->set('secret_box', $box ?? new SecretBox(SecretBox::generateKey()));
        $registry->set('load', new class {
            public function model(string $route): void {}
        });

        (new CatalogGatewayController($registry))->notify();

        return [$response, $db];
    }

    public function testNotifyWithoutGatewayIdAnswersOkWithoutTouchingTheDatabase(): void {
        $db = $this->db();

        [$response, $db] = $this->catalog($db, []);

        self::assertSame('ok', $response->getOutput());
        self::assertSame([], $db->queries);
    }

    public function testNotifyForAnUnknownAccountAnswersOk(): void {
        $db = $this->db();

        [$response, $db] = $this->catalog($db, ['gateway_id' => '99']);

        self::assertSame('ok', $response->getOutput());
        self::assertSame([], array_filter($db->queries, static fn ($q) => str_contains($q, 'rms_payment_gateway_event')));
    }

    public function testNotifyForAnUninstalledProviderAnswersOk(): void {
        $db = $this->db([
            'accounts' => [9 => ['provider_code' => 'removed_provider', 'status' => 1, 'environment' => 'sandbox']],
        ]);

        [$response, $db] = $this->catalog($db, ['gateway_id' => '9']);

        self::assertSame('ok', $response->getOutput());
        self::assertSame([], array_filter($db->queries, static fn ($q) => str_contains($q, 'rms_payment_gateway_event')));
    }

    public function testUnsignedStripeBodyIsIgnoredWithoutAnyProviderCall(): void {
        $box = new SecretBox(SecretBox::generateKey());
        $db = $this->db([
            'platformIds' => ['stripe' => 11],
            'accounts' => [11 => ['provider_code' => 'stripe', 'status' => 1, 'environment' => 'sandbox']],
            'credentials' => [11 => (new \Reamur\System\Library\Payment\GatewayCredentialVault($box))
                ->sealAll(11, 'stripe', ['secret_key' => 'sk_test_0123456789abcdef', 'webhook_secret' => 'whsec_0123456789abcdef'])],
        ]);

        [$response, $db] = $this->catalog(
            $db,
            ['gateway_id' => '11'],
            [],
            (string)json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_x']]]),
            $box
        );

        self::assertSame('ok', $response->getOutput());
        self::assertNotContains('HTTP/1.1 503 Service Unavailable', $response->getHeaders());
        self::assertSame([], array_filter($db->queries, static fn ($q) => str_contains($q, 'api.stripe.com')));
        self::assertSame([], array_filter($db->queries, static fn ($q) => str_contains($q, 'rms_payment_gateway_event')));
    }

    public function testMissingWebhookSecretRequestsRetry(): void {
        $box = new SecretBox(SecretBox::generateKey());
        $db = $this->db([
            'platformIds' => ['stripe' => 12],
            'accounts' => [12 => ['provider_code' => 'stripe', 'status' => 1, 'environment' => 'sandbox']],
            'credentials' => [12 => (new \Reamur\System\Library\Payment\GatewayCredentialVault($box))
                ->sealAll(12, 'stripe', ['secret_key' => 'sk_test_0123456789abcdef'])],
        ]);

        [$response, $db] = $this->catalog(
            $db,
            ['gateway_id' => '12'],
            [],
            (string)json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_x']]]),
            $box
        );

        self::assertSame('retry', $response->getOutput());
        self::assertContains('HTTP/1.1 503 Service Unavailable', $response->getHeaders());
        self::assertContains('Retry-After: 60', $response->getHeaders());
    }

    /**
     * ADR 0119: a tela do JOW ganhou a seção de link de PEDIDO. Ela depende da
     * ação `order_gateway_link` existir no controller e das três chaves novas
     * estarem nos três idiomas — chave faltando vira literal na tela do
     * operador, e ação faltando vira POST sem efeito (o pior dos dois).
     */
    public function testOrderGatewayLinkSectionIsWiredInViewLanguagesAndAction(): void {
        $controller = (string)file_get_contents(__DIR__ . '/../../../dev/admin/controller/payments/jow.php');
        self::assertStringContainsString("case 'order_gateway_link':", $controller);
        self::assertStringContainsString('createOrderGatewayLink(', $controller);
        self::assertStringContainsString('OrderPaymentLink(', $controller);

        $view = (string)file_get_contents(__DIR__ . '/../../../dev/admin/view/template/payments/jow.twig');
        self::assertStringContainsString('value="order_gateway_link"', $view);
        self::assertStringContainsString('{{ text_order_gateway_link }}', $view);

        foreach (['en-us', 'es-eu', 'pt-br'] as $language) {
            $file = __DIR__ . '/../../../dev/admin/language/' . $language . '/payments/jow.php';
            $strings = (string)file_get_contents($file);

            foreach (['text_order_gateway_link', 'help_order_gateway_link', 'text_no_orders',
                      'text_gateway_link_reused', 'text_order_already_paid', 'error_order_without_total'] as $key) {
                self::assertStringContainsString('$_[' . "'" . $key . "'" . ']', $strings, $language . ' missing ' . $key);
            }
        }
    }

    /**
     * KZ-64 (8ª parte): o item de menu é o nome do produto e não se traduz.
     * Já esteve "Payments (JOW)", "Pagamentos (JOW)" e "Pagos (JOW)" — três
     * grafias para a mesma tela, dependendo do idioma do admin.
     */
    public function testTheMenuEntryIsTheProductNameInEveryLanguage(): void {
        foreach (['en-us', 'es-eu', 'pt-br'] as $language) {
            $file = __DIR__ . '/../../../dev/admin/language/' . $language . '/common/header.php';
            $strings = (string)file_get_contents($file);

            self::assertStringContainsString("text_menu_payments'", $strings, $language);
            self::assertStringContainsString("= 'JOW Payments';", $strings, $language . ' tem de usar o nome do produto');
        }

        $header = (string)file_get_contents(__DIR__ . '/../../../dev/admin/controller/common/header.php');
        self::assertStringContainsString("'payments/jow'", $header);
        self::assertStringContainsString("'text_menu_payments'", $header);
    }

    /**
     * ADR 0120 (opção A): a conta configurada recebe o valor integral.
     * O texto NÃO pode voltar a falar de token de vendedor no fluxo novo —
     * quem habilita uma conta precisa saber que o dinheiro não é dividido.
     */
    public function testTheGatewayAccountHelpSaysTheAccountReceivesTheFullAmount(): void {
        foreach (['en-us', 'es-eu', 'pt-br'] as $language) {
            $file = __DIR__ . '/../../../dev/admin/language/' . $language . '/payments/jow.php';
            $strings = (string)file_get_contents($file);

            self::assertMatchesRegularExpression(
                "/help_gateway_account_isolation'\]\s*=\s*'[^']*(valor integral|full amount|importe íntegro)[^']*';/",
                $strings,
                $language . ' precisa dizer que a conta recebe o valor integral'
            );
        }
    }

    /**
     * ADR 0121 (R1): para cobrança de gateway a tela mostrava só a situação do
     * provedor, então o que o operador digitava desaparecia em silêncio.
     */
    public function testTheChargeRowShowsTheOperatorStatusWhenItDiverges(): void {
        $twig = (string)file_get_contents(__DIR__ . '/../../../dev/admin/view/template/payments/jow.twig');

        self::assertStringContainsString('c.status_mismatch', $twig);
        self::assertStringContainsString('text_status_mismatch', $twig);

        foreach (['en-us', 'es-eu', 'pt-br'] as $language) {
            $strings = (string)file_get_contents(
                __DIR__ . '/../../../dev/admin/language/' . $language . '/payments/jow.php'
            );
            self::assertStringContainsString('text_status_mismatch', $strings, $language);
            self::assertStringContainsString('help_charge_status_no_refund', $strings, $language);
        }
    }

    /**
     * ADR 0121 (R1): o select de situação não pode deixar o operador acreditar
     * que gravar `refunded` estorna dinheiro. O aviso tem de estar na tela.
     */
    public function testTheStatusSelectSaysItDoesNotRefundMoney(): void {
        $twig = (string)file_get_contents(__DIR__ . '/../../../dev/admin/view/template/payments/jow.twig');

        // A confirmação usa o mecanismo do projeto (`data-rms-confirm`), nunca
        // `onclick` — atributo de evento é recusado pela política de CSP.
        self::assertStringContainsString('help_charge_status_no_refund', $twig);
        self::assertStringContainsString('data-rms-confirm="{{ help_charge_status_no_refund', $twig);
        self::assertStringNotContainsString('onclick=', $twig);
    }
}
