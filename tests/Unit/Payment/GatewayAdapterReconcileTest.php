<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\MercadopagoMarketplace;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayPaymentRecord;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\MercadoPagoGatewayAdapter;
use Reamur\System\Library\Payment\Money;
use Reamur\System\Library\Payment\PaypalOrdersAdapter;
use Reamur\System\Library\Payment\PaypalOrdersClient;
use Reamur\System\Library\Payment\StripeCheckoutAdapter;
use Reamur\System\Library\Payment\StripeCheckoutClient;

/**
 * P0-b: contrato read-only de reconciliação dos três adapters.
 *
 * Prova que cada provider consulta o recurso certo, traduz o estado para o
 * vocabulário neutro e — principalmente — que a reconciliação NÃO cria, NÃO
 * captura e NÃO reembolsa nada. Nenhuma chamada de rede: os clients são falsos.
 */
final class GatewayAdapterReconcileTest extends TestCase {
    private const MP_ORDER_ID = 'ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9';
    private const STRIPE_SESSION_ID = 'cs_test_a1b2c3d4e5f6g7h8';
    private const PAYPAL_ORDER_ID = '5O190127TN364715T';
    private const IDEMPOTENCY = '51e3f12c-6b0c-5ac5-9b83-d1d43c90d0c1';

    /** Constante exposta para o cliente anônimo (classe anônima não herda a externa). */
    public static function reference(): string {
        return self::IDEMPOTENCY;
    }

    private function account(string $provider): GatewayAccount {
        return new GatewayAccount(7, $provider, 'JOW platform', 'sandbox', true, ['flow' => 'jow_internal']);
    }

    private function record(
        string $provider,
        string $reference = self::IDEMPOTENCY,
        string $anchor = '',
        string $status = GatewayPaymentStatus::PENDING,
        string $paymentId = ''
    ): GatewayPaymentRecord {
        return new GatewayPaymentRecord(
            41,
            7,
            'jow_charge',
            42,
            $provider,
            'checkout_pro',
            new Money('125.50', 'BRL', 2),
            new Money('125.50', 'BRL', 2),
            str_repeat('b', 48),
            self::IDEMPOTENCY,
            $status,
            $reference,
            $paymentId,
            GatewayCustomerAction::redirect('https://gateway.example/pay/41'),
            null,
            $anchor
        );
    }

    // ── Mercado Pago ────────────────────────────────────────────────────────

    private function mpApi(array $orders): MercadopagoMarketplace {
        return new class($orders) extends MercadopagoMarketplace {
            /** @var list<array{0: string, 1: string}> */
            public array $getCalls = [];

            public function __construct(private array $orders) {
                parent::__construct([]);
            }

            public function getOrder(string $orderId, string $accessToken): array {
                $this->getCalls[] = [$orderId, $accessToken];

                return $this->orders[$orderId] ?? ['_http_status' => 404];
            }
        };
    }

    private function mpOrder(string $status = 'processed', string $detail = 'accredited', string $amount = '125.50'): array {
        return [
            '_http_status' => 200,
            'id' => self::MP_ORDER_ID,
            'external_reference' => self::IDEMPOTENCY,
            'currency' => 'BRL',
            'total_amount' => $amount,
            'status' => $status,
            'status_detail' => $detail,
            'transactions' => ['payments' => [['id' => 'pay-778899']]],
        ];
    }

    public function testMercadoPagoReconcilesByTheOrderIdAlreadyStoredInTheLedger(): void {
        $api = $this->mpApi([self::MP_ORDER_ID => $this->mpOrder()]);
        $result = (new MercadoPagoGatewayAdapter($api))->reconcile(
            $this->record('mp', self::MP_ORDER_ID),
            $this->account('mp'),
            new GatewayCredentials(['access_token' => 'APP_USR_PLATFORM_TOKEN_1234567890'])
        );

        $this->assertSame(GatewayWebhookResult::VERIFIED, $result->kind);
        $this->assertSame(GatewayPaymentStatus::PAID, $result->status);
        $this->assertSame(self::MP_ORDER_ID, $result->providerReference);
        $this->assertSame('pay-778899', $result->providerPaymentId);
        $this->assertSame('125.50', $result->chargeAmount?->amount());
        $this->assertSame([[self::MP_ORDER_ID, 'APP_USR_PLATFORM_TOKEN_1234567890']], $api->getCalls);
    }

    public function testMercadoPagoPrefersTheExplicitAnchorWhenPresent(): void {
        $api = $this->mpApi(['ORD-ANCHOR-0001' => ['_http_status' => 404]]);
        $result = (new MercadoPagoGatewayAdapter($api))->reconcile(
            $this->record('mp', self::MP_ORDER_ID, 'ORD-ANCHOR-0001'),
            $this->account('mp'),
            new GatewayCredentials(['access_token' => 'APP_USR_PLATFORM_TOKEN_1234567890'])
        );

        $this->assertSame('ORD-ANCHOR-0001', $api->getCalls[0][0]);
        $this->assertSame(GatewayWebhookResult::RETRY, $result->kind);
        $this->assertSame('provider_unavailable', $result->reasonCode);
    }

    public function testMercadoPagoWithoutTokenAsksForRetryInsteadOfGuessing(): void {
        $api = $this->mpApi([]);
        $result = (new MercadoPagoGatewayAdapter($api))->reconcile(
            $this->record('mp', self::MP_ORDER_ID),
            $this->account('mp'),
            new GatewayCredentials([])
        );

        $this->assertSame(GatewayWebhookResult::RETRY, $result->kind);
        $this->assertSame('credentials_missing', $result->reasonCode);
        $this->assertSame([], $api->getCalls);
    }

    public function testMercadoPagoRefusesAnAmountThatDoesNotMatchTheAttempt(): void {
        $api = $this->mpApi([self::MP_ORDER_ID => $this->mpOrder('processed', 'accredited', '1.00')]);
        $result = (new MercadoPagoGatewayAdapter($api))->reconcile(
            $this->record('mp', self::MP_ORDER_ID),
            $this->account('mp'),
            new GatewayCredentials(['access_token' => 'APP_USR_PLATFORM_TOKEN_1234567890'])
        );

        // O valor devolvido é autoritativo no evento; a recusa do valor fica no
        // applier, comparando com a tentativa persistida.
        $this->assertSame(GatewayWebhookResult::VERIFIED, $result->kind);
        $this->assertSame('1.00', $result->chargeAmount?->amount());
    }

    public function testMercadoPagoRefusesAnAnswerAboutAnotherProvider(): void {
        $result = (new MercadoPagoGatewayAdapter($this->mpApi([])))->reconcile(
            $this->record('stripe', self::MP_ORDER_ID),
            $this->account('mp'),
            new GatewayCredentials(['access_token' => 'APP_USR_PLATFORM_TOKEN_1234567890'])
        );

        $this->assertSame(GatewayWebhookResult::IGNORED, $result->kind);
        $this->assertSame('wrong_provider', $result->reasonCode);
    }

    // ── Stripe ──────────────────────────────────────────────────────────────

    private function stripeClient(array $sessions): StripeCheckoutClient {
        return new class($sessions) extends StripeCheckoutClient {
            /** @var list<string> */
            public array $sessionCalls = [];

            /** @var list<string> */
            public array $chargeCalls = [];

            private string $reference;

            public function __construct(private array $sessions) {
                parent::__construct(null, []);

                $this->reference = \Reamur\Tests\Unit\Payment\GatewayAdapterReconcileTest::reference();
            }

            public function getSession(string $sessionId, string $secretKey): array {
                $this->sessionCalls[] = $sessionId;

                return $this->sessions[$sessionId] ?? ['_http_status' => 404];
            }

            /**
             * ADR 0124: estes testes olham a SESSÃO. A cobrança é consultada
             * depois dela, então o dublê responde uma cobrança sem estorno —
             * herdando o client real, a chamada sairia para a rede.
             */
            public function getCharge(string $chargeId, string $secretKey): array {
                $this->chargeCalls[] = $chargeId;

                return [
                    '_http_status' => 200,
                    'id' => $chargeId,
                    'metadata' => ['jow_reference' => $this->reference],
                    'currency' => 'brl',
                    'amount' => 12550,
                    'amount_refunded' => 0,
                    'payment_intent' => $chargeId,
                ];
            }
        };
    }

    private function stripeSession(string $status = 'complete', string $paymentStatus = 'paid', int $amount = 12550): array {
        return [
            '_http_status' => 200,
            'id' => self::STRIPE_SESSION_ID,
            'object' => 'checkout.session',
            'client_reference_id' => self::IDEMPOTENCY,
            'payment_intent' => 'pi_test_1a2b3c4d5e6f',
            'amount_total' => $amount,
            'currency' => 'brl',
            'status' => $status,
            'payment_status' => $paymentStatus,
        ];
    }

    public function testStripeReconcilesByTheStoredSessionAnchor(): void {
        $client = $this->stripeClient([self::STRIPE_SESSION_ID => $this->stripeSession()]);
        $result = (new StripeCheckoutAdapter($client))->reconcile(
            $this->record('stripe', self::IDEMPOTENCY, self::STRIPE_SESSION_ID),
            $this->account('stripe'),
            new GatewayCredentials(['secret_key' => 'sk_test_platform_restricted_key_0001'])
        );

        $this->assertSame(GatewayWebhookResult::VERIFIED, $result->kind);
        $this->assertSame(GatewayPaymentStatus::PAID, $result->status);
        $this->assertSame(self::IDEMPOTENCY, $result->providerReference);
        $this->assertSame('pi_test_1a2b3c4d5e6f', $result->providerPaymentId);
        $this->assertSame([self::STRIPE_SESSION_ID], $client->sessionCalls);
    }

    public function testStripeWithoutTheSessionAnchorReportsItPlainly(): void {
        $client = $this->stripeClient([]);
        $result = (new StripeCheckoutAdapter($client))->reconcile(
            $this->record('stripe'),
            $this->account('stripe'),
            new GatewayCredentials(['secret_key' => 'sk_test_platform_restricted_key_0001'])
        );

        $this->assertSame(GatewayWebhookResult::IGNORED, $result->kind);
        $this->assertSame('missing_provider_anchor', $result->reasonCode);
        $this->assertSame([], $client->sessionCalls, 'sem âncora não existe chamada ao provedor');
    }

    public function testStripeOpenSessionStaysRequiresAction(): void {
        $client = $this->stripeClient([self::STRIPE_SESSION_ID => $this->stripeSession('open', 'unpaid')]);
        $result = (new StripeCheckoutAdapter($client))->reconcile(
            $this->record('stripe', self::IDEMPOTENCY, self::STRIPE_SESSION_ID),
            $this->account('stripe'),
            new GatewayCredentials(['secret_key' => 'sk_test_platform_restricted_key_0001'])
        );

        $this->assertSame(GatewayPaymentStatus::REQUIRES_ACTION, $result->status);
    }

    // ── PayPal ──────────────────────────────────────────────────────────────

    private function paypalClient(array $orders): PaypalOrdersClient {
        return new class($orders) extends PaypalOrdersClient {
            /** @var list<string> */
            public array $getCalls = [];
            /** @var list<string> */
            public array $captureCalls = [];

            public function __construct(private array $orders) {
                parent::__construct(null, []);
            }

            public function oauthToken(string $baseUrl, string $clientId, string $clientSecret): array {
                return ['_http_status' => 200, 'access_token' => 'A21TOKEN123'];
            }

            public function getOrder(string $baseUrl, string $accessToken, string $orderId): array {
                $this->getCalls[] = $orderId;

                return $this->orders[$orderId] ?? ['_http_status' => 404];
            }

            public function captureOrder(string $baseUrl, string $accessToken, string $orderId, string $requestId): array {
                $this->captureCalls[] = $orderId;

                return ['_http_status' => 201, 'id' => 'CAPTURE1', 'status' => 'COMPLETED'];
            }
        };
    }

    private function paypalOrder(string $orderStatus, string $captureStatus = 'COMPLETED'): array {
        $order = [
            '_http_status' => 200,
            'id' => self::PAYPAL_ORDER_ID,
            'status' => $orderStatus,
            'purchase_units' => [[
                'custom_id' => self::IDEMPOTENCY,
                'invoice_id' => self::IDEMPOTENCY,
                'amount' => ['currency_code' => 'BRL', 'value' => '125.50'],
            ]],
        ];

        if ($captureStatus !== '') {
            $order['purchase_units'][0]['payments'] = ['captures' => [[
                'id' => 'CAPTURE-7788',
                'status' => $captureStatus,
                'amount' => ['currency_code' => 'BRL', 'value' => '125.50'],
            ]]];
        }

        return $order;
    }

    public function testPaypalReconcilesByTheStoredOrderAnchor(): void {
        $client = $this->paypalClient([self::PAYPAL_ORDER_ID => $this->paypalOrder('COMPLETED')]);
        $result = (new PaypalOrdersAdapter($client))->reconcile(
            $this->record('paypal', self::IDEMPOTENCY, self::PAYPAL_ORDER_ID),
            $this->account('paypal'),
            new GatewayCredentials(['client_id' => 'AY_CLIENT_ID_0001', 'client_secret' => 'EP_CLIENT_SECRET_0001'])
        );

        $this->assertSame(GatewayWebhookResult::VERIFIED, $result->kind);
        $this->assertSame(GatewayPaymentStatus::PAID, $result->status);
        $this->assertSame(self::IDEMPOTENCY, $result->providerReference);
        $this->assertSame('CAPTURE-7788', $result->providerPaymentId);
        $this->assertSame([self::PAYPAL_ORDER_ID], $client->getCalls);
    }

    public function testPaypalApprovedOrderIsNeverCapturedByReconciliation(): void {
        $client = $this->paypalClient([self::PAYPAL_ORDER_ID => $this->paypalOrder('APPROVED', '')]);
        $result = (new PaypalOrdersAdapter($client))->reconcile(
            $this->record('paypal', self::IDEMPOTENCY, self::PAYPAL_ORDER_ID),
            $this->account('paypal'),
            new GatewayCredentials(['client_id' => 'AY_CLIENT_ID_0001', 'client_secret' => 'EP_CLIENT_SECRET_0001'])
        );

        // Ordem aprovada e não capturada continua `authorized`: mover dinheiro
        // não é papel da reconciliação.
        $this->assertSame(GatewayPaymentStatus::AUTHORIZED, $result->status);
        $this->assertSame([], $client->captureCalls, 'reconciliação nunca captura');
    }

    public function testPaypalRefundStateComesFromTheQueriedCapture(): void {
        $client = $this->paypalClient([self::PAYPAL_ORDER_ID => $this->paypalOrder('COMPLETED', 'PARTIALLY_REFUNDED')]);
        $result = (new PaypalOrdersAdapter($client))->reconcile(
            $this->record('paypal', self::IDEMPOTENCY, self::PAYPAL_ORDER_ID),
            $this->account('paypal'),
            new GatewayCredentials(['client_id' => 'AY_CLIENT_ID_0001', 'client_secret' => 'EP_CLIENT_SECRET_0001'])
        );

        $this->assertSame(GatewayPaymentStatus::PARTIALLY_REFUNDED, $result->status);
    }

    public function testPaypalWithoutTheOrderAnchorReportsItPlainly(): void {
        $client = $this->paypalClient([]);
        $result = (new PaypalOrdersAdapter($client))->reconcile(
            $this->record('paypal'),
            $this->account('paypal'),
            new GatewayCredentials(['client_id' => 'AY_CLIENT_ID_0001', 'client_secret' => 'EP_CLIENT_SECRET_0001'])
        );

        $this->assertSame(GatewayWebhookResult::IGNORED, $result->kind);
        $this->assertSame('missing_provider_anchor', $result->reasonCode);
        $this->assertSame([], $client->getCalls);
    }

    public function testEveryAdapterExposesItsReconciliationCapability(): void {
        $this->assertInstanceOf(\Reamur\System\Library\Payment\ReconcilableGatewayAdapter::class, new MercadoPagoGatewayAdapter());
        $this->assertInstanceOf(\Reamur\System\Library\Payment\ReconcilableGatewayAdapter::class, new StripeCheckoutAdapter());
        $this->assertInstanceOf(\Reamur\System\Library\Payment\ReconcilableGatewayAdapter::class, new PaypalOrdersAdapter());
    }
}
