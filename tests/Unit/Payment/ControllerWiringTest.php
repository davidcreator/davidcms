<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\Admin\Controller\Setting\Marketplace as AdminMarketplaceController;
use Reamur\Catalog\Controller\Checkout\Payment as CatalogPaymentController;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\Admin\Model\Setting\Marketplace as AdminMarketplaceModel;
use Reamur\Catalog\Model\Checkout\Mp_payment as CatalogSellersModel;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Security\SecretBox;
use Reamur\System\Library\Session;

/**
 * EX-03 (ADR 0097): os dois controllers so juntam as pecas. Aqui se confere
 * que o que o usuario ve (aviso na tela, redirecionamento, resposta ao
 * provedor) continua o de antes para cada resultado do Checkout.
 */
final class ControllerWiringTest extends TestCase {
    private const CHARGE = [
        'mp_payment_id' => '7', 'order_id' => '41', 'payment_id' => '0', 'public_token' => 'chave-do-link',
        'status' => 'pending', 'init_point' => 'https://mp.example/pagar/pref-1',
    ];

    /** Banco de mentira: guarda o SQL; responde so a consultas de cobranca. */
    private function db(array $charge = []): object {
        return new class($charge) {
            /** @var list<string> */
            public array $sql = [];

            public function __construct(private array $charge) {}

            public function escape(string $value): string {
                return addslashes($value);
            }

            public function getLastId(): int {
                return 7;
            }

            public function query(string $sql): \stdClass {
                $this->sql[] = $sql;
                $rows = (str_contains($sql, 'mp_payment') && $this->charge !== []) ? [$this->charge] : [];
                $res  = new \stdClass();
                $res->rows     = $rows;
                $res->row      = $rows[0] ?? [];
                $res->num_rows = count($rows);

                return $res;
            }
        };
    }

    private function log(): object {
        return new class {
            /** @var list<string> */
            public array $lines = [];
            public function write(string $m): void { $this->lines[] = $m; }
        };
    }

    // ── site publico ────────────────────────────────────────────────────────

    /** @return array{0: CatalogPaymentController, 1: Response, 2: object, 3: object} */
    private function catalog(array $charge, array $get = []): array {
        $db       = $this->db($charge);
        $log      = $this->log();
        $request  = new Request();
        $request->get    = $get;
        $request->post   = [];
        $request->server = [];
        $response = new Response();

        $registry = new Registry();
        $registry->set('db', $db);
        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('log', $log);
        $registry->set('config', new Config());
        $registry->set('url', new class { public function link(string $route): string { return 'https://loja.example/catalog/?route=' . $route; } });
        $registry->set('load', new class {
            public function language(string $route): void {}
            public function model(string $route): void {}
        });
        $registry->set('model_checkout_mp_payment', new class {
            public function getSellerByMpUserId(int $id): array { return []; }
            public function getActiveSellers(): array { return []; }
        });

        return [new CatalogPaymentController($registry), $response, $db, $log];
    }

    public function testCustomerLinkRedirectsToTheProviderAddress(): void {
        [$controller, $response, $db] = $this->catalog(self::CHARGE);

        $controller->pay('chave-do-link');

        self::assertContains('Location: https://mp.example/pagar/pref-1', $response->getHeaders());
        self::assertCount(1, $db->sql);
        self::assertStringContainsString("`public_token` = 'chave-do-link'", $db->sql[0]);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function deadLinks(): iterable {
        yield 'chave desconhecida' => [[], 'qualquer'];
        yield 'chave vazia'        => [self::CHARGE, ''];
        yield 'chave longa demais' => [self::CHARGE, str_repeat('a', 49)];
        yield 'ja pago'            => [['status' => 'approved'] + self::CHARGE, 'chave-do-link'];
        yield 'recusado'           => [['status' => 'rejected'] + self::CHARGE, 'chave-do-link'];
        yield 'cancelado'          => [['status' => 'cancelled'] + self::CHARGE, 'chave-do-link'];
        yield 'endereco http'      => [['init_point' => 'http://mp.example/pagar'] + self::CHARGE, 'chave-do-link'];
    }

    #[DataProvider('deadLinks')]
    public function testLinkThatIsNotValidGoesToNotFound(array $charge, string $token): void {
        [$controller, $response] = $this->catalog($charge);

        $controller->pay($token);

        self::assertContains('Location: https://loja.example/catalog/?route=error/not_found', $response->getHeaders());
    }

    public function testNoticeThatIsNotAPaymentIsAnsweredWithoutTouchingTheDatabase(): void {
        [$controller, $response, $db, $log] = $this->catalog(self::CHARGE, ['topic' => 'merchant_order', 'id' => '1']);

        $controller->notify();

        self::assertSame('ok:ignored', $response->getOutput());
        self::assertContains('Content-Type: text/plain; charset=utf-8', $response->getHeaders());
        self::assertNotContains('HTTP/1.1 503 Service Unavailable', $response->getHeaders());
        self::assertSame([], $db->sql);
        self::assertSame([], $log->lines);
    }

    public function testNoticeOfAPaymentNobodyHasKeepsTheAnswerAndTheLogLineOfBefore(): void {
        [$controller, $response, , $log] = $this->catalog(self::CHARGE, ['type' => 'payment', 'data_id' => '777001']);

        $controller->notify();

        self::assertSame('ok:not-ours', $response->getOutput());
        self::assertSame(['mp notify: payment 777001 não confirmado em nenhum seller ativo'], $log->lines);
    }

    public function testOldSignatureFunctionStillAnswers(): void {
        $sig = hash_hmac('sha256', 'id:5;request-id:r;ts:1;', 's');

        self::assertTrue(CatalogPaymentController::verifyWebhookSignature('s', 5, 'ts=1,v1=' . $sig, 'r'));
        self::assertFalse(CatalogPaymentController::verifyWebhookSignature('s', 6, 'ts=1,v1=' . $sig, 'r'));
    }

    // ── painel ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed>       $order
     * @param array<string, string>      $charge
     * @param list<array<string, mixed>> $sellers
     * @return array{0: Response, 1: Session, 2: object}
     */
    private function paylink(array $order, array $charge, array $sellers, string $method = 'POST', bool $can = true, bool $token = true, array $settings = []): array {
        $session = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $session->data = [];

        $request = new Request();
        $request->server = ['REQUEST_METHOD' => $method];
        $request->post   = ['csrf_token' => $token ? Csrf::token($session) : 'errado'];
        $request->get    = [];

        $db       = $this->db($charge);
        $response = new Response();
        $config   = new Config();
        $config->set('site_url', 'https://loja.example/admin/');

        $registry = new Registry();
        $registry->set('db', $db);
        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('session', $session);
        $registry->set('config', $config);
        $registry->set('log', $this->log());
        $registry->set('load', new class {
            public function language(string $route): void {}
            public function model(string $route): void {}
        });
        $registry->set('language', new class { public function get(string $key): string { return $key . ' [%s]'; } });
        $registry->set('url', new class { public function link(string $route): string { return 'https://loja.example/admin/?route=' . $route; } });
        $registry->set('user', new class($can) {
            public function __construct(private bool $can) {}
            public function hasPermission(string $type, string $route): bool { return $this->can && $type === 'modify' && $route === 'setting/marketplace'; }
        });
        $registry->set('model_sale_order', new class($order) {
            public function __construct(private array $order) {}
            public function getOrder(int $id): array { return ((int)($this->order['order_id'] ?? 0) === $id) ? $this->order : []; }
        });
        $registry->set('model_setting_setting', new class($settings) {
            public function __construct(private array $settings) {}
            public function getSettings(): array { return $this->settings; }
        });
        $registry->set('model_setting_marketplace', new class($sellers) {
            public function __construct(private array $sellers) {}
            public function getActiveSellers(): array { return $this->sellers; }
            public function getSellerByMpUserId(int $id): array { return []; }
        });

        (new AdminMarketplaceController($registry))->paylink((string)($order['order_id'] ?? 41));

        return [$response, $session, $db];
    }

    private const ORDER  = ['order_id' => 41, 'total' => '150.0000'];
    private const SELLER = ['mp_seller_id' => 1, 'mp_user_id' => 902901, 'access_token' => 'TOKEN-A', 'token_locked' => false];
    private const BACK   = 'Location: https://loja.example/admin/?route=setting/marketplace';

    public function testPaidOrderShowsTheAlreadyPaidWarning(): void {
        [$response, $session, $db] = $this->paylink(self::ORDER, ['status' => 'approved'] + self::CHARGE, [self::SELLER]);

        self::assertSame(['type' => 'warning', 'text' => 'error_already_paid [41]'], $session->data['flash']);
        self::assertContains(self::BACK, $response->getHeaders());
        self::assertStringNotContainsString('INSERT', implode("\n", $db->sql));
    }

    public function testPendingLinkIsShownAgainWithThePublicAddressOfTheSite(): void {
        [$response, $session, $db] = $this->paylink(self::ORDER, self::CHARGE, []);

        self::assertSame(
            ['type' => 'info', 'text' => 'text_paylink_reused [https://loja.example/catalog/index.php?route=checkout/payment.pay|chave-do-link]'],
            $session->data['flash']
        );
        self::assertContains(self::BACK, $response->getHeaders());
        self::assertStringNotContainsString('INSERT', implode("\n", $db->sql));
    }

    public function testWithoutSellerTheWarningIsTheOneOfBefore(): void {
        [, $session] = $this->paylink(self::ORDER, [], []);

        self::assertSame(['type' => 'warning', 'text' => 'error_no_seller [%s]'], $session->data['flash']);
    }

    public function testSellerWhoseTokenDoesNotOpenShowsTheKeyError(): void {
        [, $session, $db] = $this->paylink(self::ORDER, [], [['access_token' => '', 'token_locked' => true] + self::SELLER]);

        self::assertSame(['type' => 'danger', 'text' => 'error_token_locked [%s]'], $session->data['flash']);
        self::assertStringNotContainsString('INSERT', implode("\n", $db->sql));
    }

    public function testOrderWithoutValueShowsTheZeroTotalWarning(): void {
        [, $session] = $this->paylink(['order_id' => 41, 'total' => '0.0000'], [], [self::SELLER]);

        self::assertSame(['type' => 'warning', 'text' => 'error_no_total [41]'], $session->data['flash']);
    }

    public function testRefusalFromMercadoPagoShowsTheErrorAndStoresNothing(): void {
        if (!function_exists('curl_init')) {
            self::markTestSkipped('sem a extensao curl');
        }

        // Porta fechada nesta maquina: a chamada falha na hora e nada sai para a internet.
        [$response, $session, $db] = $this->paylink(self::ORDER, [], [self::SELLER], 'POST', true, true, ['mp_api_url' => 'http://127.0.0.1:1']);

        self::assertSame('danger', $session->data['flash']['type']);
        self::assertStringStartsWith('error_preference [Mercado Pago OAuth request failed:', $session->data['flash']['text']);
        self::assertContains(self::BACK, $response->getHeaders());
        self::assertStringNotContainsString('INSERT', implode("\n", $db->sql));
    }

    public function testOrderThatDoesNotExistGoesToNotFound(): void {
        [$response, $session, $db] = $this->paylink([], [], [self::SELLER]);

        self::assertContains('Location: https://loja.example/admin/?route=error/not_found', $response->getHeaders());
        self::assertArrayNotHasKey('flash', $session->data);
        self::assertSame([], $db->sql);
    }

    public function testLinkIsOnlyGeneratedByPostWithPermissionAndFormToken(): void {
        [$response, , $db] = $this->paylink(self::ORDER, [], [self::SELLER], 'GET');
        self::assertContains('Location: https://loja.example/admin/?route=error/permission', $response->getHeaders());
        self::assertSame([], $db->sql);

        [$response, , $db] = $this->paylink(self::ORDER, [], [self::SELLER], 'POST', false);
        self::assertContains('Location: https://loja.example/admin/?route=error/permission', $response->getHeaders());
        self::assertSame([], $db->sql);

        [$response, $session, $db] = $this->paylink(self::ORDER, [], [self::SELLER], 'POST', true, false);
        self::assertContains(self::BACK, $response->getHeaders());
        self::assertSame(['type' => 'danger', 'text' => 'error_csrf [%s]'], $session->data['flash']);
        self::assertSame([], $db->sql);
    }

    // ── vendedores: a mesma resposta nas duas aplicacoes ────────────────────

    public function testBothApplicationsListActiveSellersInTheSameOrderWithTheTokenOpened(): void {
        $box  = new SecretBox(SecretBox::generateKey());
        $rows = [
            ['mp_seller_id' => 1, 'mp_user_id' => 902901, 'nickname' => 'A', 'access_token' => $box->seal('TOKEN-A-0123456789', SecretBox::CTX_MP_ACCESS)],
            ['mp_seller_id' => 2, 'mp_user_id' => 902902, 'nickname' => 'B', 'access_token' => (new SecretBox(SecretBox::generateKey()))->seal('TOKEN-B-0123456789', SecretBox::CTX_MP_ACCESS)],
        ];

        foreach ([AdminMarketplaceModel::class, CatalogSellersModel::class] as $class) {
            $db = new class($rows) {
                /** @var list<string> */
                public array $sql = [];
                public function __construct(private array $rows) {}
                public function escape(string $value): string { return addslashes($value); }
                public function query(string $sql): \stdClass {
                    $this->sql[] = (string)preg_replace('/\s+/', ' ', $sql);
                    $res = new \stdClass();
                    $res->rows = $this->rows;
                    $res->row  = $this->rows[0];
                    $res->num_rows = count($this->rows);

                    return $res;
                }
            };
            $registry = new Registry();
            $registry->set('db', $db);
            $registry->set('secret_box', $box);
            $model = new $class($registry);

            $sellers = $model->getActiveSellers();

            self::assertSame([0, 1], array_keys($sellers), $class);
            self::assertSame('TOKEN-A-0123456789', $sellers[0]['access_token'], $class);
            self::assertFalse($sellers[0]['token_locked'], $class);
            self::assertSame('', $sellers[1]['access_token'], 'token que nao abre vem vazio, nunca cifrado');
            self::assertTrue($sellers[1]['token_locked'], $class);
            self::assertStringContainsString('FROM `rms_mp_seller` WHERE `status` = 1 ORDER BY `mp_seller_id` ASC', $db->sql[0], $class);
            self::assertStringNotContainsString('LIMIT', $db->sql[0], $class);

            $one = $model->getSellerByMpUserId(902901);
            self::assertSame('TOKEN-A-0123456789', $one['access_token'], $class);
            self::assertStringContainsString("WHERE `mp_user_id` = '902901' AND `status` = 1", $db->sql[1], $class);
        }
    }

    public function testNoSellerIsAnEmptyAnswerInBothApplications(): void {
        foreach ([AdminMarketplaceModel::class, CatalogSellersModel::class] as $class) {
            $registry = new Registry();
            $registry->set('db', $this->db());
            $model = new $class($registry);

            self::assertSame([], $model->getActiveSellers(), $class);
            self::assertSame([], $model->getSellerByMpUserId(1), $class);
        }
    }
}
