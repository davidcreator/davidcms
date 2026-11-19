<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\Money;
use Reamur\System\Library\Payment\Notification;
use Reamur\System\Library\Payment\PaypalOrdersAdapter;
use Reamur\System\Library\Payment\PaypalOrdersClient;

final class PaypalOrdersAdapterTest extends TestCase {
    private const ORDER_ID = '8GB552690W583102V';
    private const CAPTURE_ID = '8XA123456T987012L';
    private const IDEMPOTENCY = '81e3f12c-6b0c-5ac5-9b83-d1d43c90d0c7';
    private const CLIENT_ID = 'AeXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX1';
    private const CLIENT_SECRET = 'EXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXQ';
    private const WEBHOOK_ID = 'WEBHOOK-ID-1A2B3C4D5E6F';

    private function account(string $environment = 'sandbox'): GatewayAccount {
        return new GatewayAccount(9, 'paypal', 'JOW platform', $environment, true, ['flow' => 'jow_internal']);
    }

    private function credentials(array $values = []): GatewayCredentials {
        return new GatewayCredentials($values + [
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
            'webhook_id' => self::WEBHOOK_ID,
        ]);
    }

    private function request(
        string $currency = 'USD',
        string $idempotencyKey = self::IDEMPOTENCY,
        string $payerEmail = 'sb-buyer@personal.example.com'
    ): GatewayPaymentRequest {
        return new GatewayPaymentRequest(
            'jow_charge',
            42,
            new Money('125.50', $currency, 2),
            $currency,
            'checkout_orders',
            $idempotencyKey,
            'JOW internal charge 42',
            'https://loja.example/catalog/index.php?route=common/home',
            'https://loja.example/catalog/index.php?route=checkout/gateway.notify&gateway_id=9',
            $payerEmail
        );
    }

    /** Fake client: all provider traffic is intercepted and stays local. */
    private function client(array $token = [], array $created = [], array $orders = [], array $captures = [], array $verifications = []): PaypalOrdersClient {
        return new class($token, $created, $orders, $captures, $verifications) extends PaypalOrdersClient {
            public array $tokenCalls = [];
            public array $createCalls = [];
            public array $getOrderCalls = [];
            public array $captureCalls = [];
            public array $verifyCalls = [];

            public function __construct(
                private array $token,
                private array $createdOrders,
                private array $orders,
                private array $captures,
                private array $verifications
            ) {
                parent::__construct(null, []);
            }

            public function oauthToken(string $baseUrl, string $clientId, string $clientSecret): array {
                $this->tokenCalls[] = [$baseUrl, $clientId, $clientSecret];
                return $this->token ?: ['_http_status' => 401];
            }

            public function createOrder(string $baseUrl, string $accessToken, array $payload, string $requestId): array {
                $this->createCalls[] = [$baseUrl, $accessToken, $payload, $requestId];
                return $this->createdOrders;
            }

            public function getOrder(string $baseUrl, string $accessToken, string $orderId): array {
                $this->getOrderCalls[] = [$baseUrl, $accessToken, $orderId];
                return $this->orders[$orderId] ?? ['_http_status' => 404];
            }

            public function captureOrder(string $baseUrl, string $accessToken, string $orderId, string $requestId): array {
                $this->captureCalls[] = [$baseUrl, $accessToken, $orderId, $requestId];
                return $this->captures[$orderId] ?? ['_http_status' => 400];
            }

            public function verifyWebhookSignature(string $baseUrl, string $accessToken, array $verification): array {
                $this->verifyCalls[] = [$baseUrl, $accessToken, $verification];
                return $this->verifications;
            }
        };
    }

    private function createdOrder(array $overrides = [], string $currency = 'USD'): array {
        return $overrides + [
            '_http_status' => 201,
            'id' => self::ORDER_ID,
            'status' => 'CREATED',
            'purchase_units' => [[
                'custom_id' => self::IDEMPOTENCY,
                'invoice_id' => self::IDEMPOTENCY,
                'amount' => ['currency_code' => $currency, 'value' => '125.50'],
            ]],
            'links' => [
                ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/' . self::ORDER_ID],
                ['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=' . self::ORDER_ID],
            ],
        ];
    }

    private function completedOrder(string $orderId, string $captureStatus = 'COMPLETED', string $customId = self::IDEMPOTENCY): array {
        return [
            '_http_status' => 200,
            'id' => $orderId,
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'custom_id' => $customId,
                'invoice_id' => $customId,
                'amount' => ['currency_code' => 'USD', 'value' => '125.50'],
                'payments' => ['captures' => [[
                    'id' => self::CAPTURE_ID,
                    'status' => $captureStatus,
                    'amount' => ['currency_code' => 'USD', 'value' => '125.50'],
                ]]],
            ]],
        ];
    }

    public function testCapabilitiesCoverCheckoutOrdersInThreeCurrencies(): void {
        $capabilities = (new PaypalOrdersAdapter())->capabilities();

        self::assertTrue($capabilities->supports('checkout_orders', 'BRL'));
        self::assertTrue($capabilities->supports('checkout_orders', 'USD'));
        self::assertTrue($capabilities->supports('checkout_orders', 'EUR'));
        self::assertFalse($capabilities->supports('checkout_session', 'USD'));
        self::assertSame(2, $capabilities->fractionDigits('BRL'));
        self::assertSame(2, $capabilities->fractionDigits('EUR'));
    }

    public function testBaseUrlFollowsTheAccountEnvironment(): void {
        self::assertSame(PaypalOrdersClient::SANDBOX_API_URL, PaypalOrdersClient::baseUrlFor('sandbox'));
        self::assertSame(PaypalOrdersClient::LIVE_API_URL, PaypalOrdersClient::baseUrlFor('live'));
    }

    public function testCreatesAnIdempotentPaypalOrderOnThePlatformAccount(): void {
        $client = $this->client(['_http_status' => 200, 'access_token' => 'A21TOKEN123'], $this->createdOrder());
        $adapter = new PaypalOrdersAdapter($client);

        $result = $adapter->createPayment($this->request(), $this->account(), $this->credentials(), null);

        self::assertSame(self::IDEMPOTENCY, $result->providerReference);
        self::assertSame('', $result->providerPaymentId);
        self::assertSame(GatewayPaymentStatus::REQUIRES_ACTION, $result->status);
        self::assertSame(GatewayCustomerAction::REDIRECT, $result->customerAction->kind);
        self::assertSame('125.50', $result->chargeAmount->amount());
        self::assertCount(1, $client->tokenCalls);
        self::assertSame([PaypalOrdersClient::SANDBOX_API_URL, self::CLIENT_ID, self::CLIENT_SECRET], $client->tokenCalls[0]);
        self::assertCount(1, $client->createCalls);

        [$baseUrl, $accessToken, $payload, $requestId] = $client->createCalls[0];
        self::assertSame(PaypalOrdersClient::SANDBOX_API_URL, $baseUrl);
        self::assertSame('A21TOKEN123', $accessToken);
        self::assertSame(self::IDEMPOTENCY, $requestId);
        self::assertSame('CAPTURE', $payload['intent']);
        self::assertSame(self::IDEMPOTENCY, $payload['purchase_units'][0]['custom_id']);
        self::assertSame(self::IDEMPOTENCY, $payload['purchase_units'][0]['invoice_id']);
        self::assertSame('125.50', $payload['purchase_units'][0]['amount']['value']);
        self::assertSame('USD', $payload['purchase_units'][0]['amount']['currency_code']);
        self::assertSame('PAY_NOW', $payload['payment_source']['paypal']['experience_context']['user_action']);
        self::assertSame('sb-buyer@personal.example.com', $payload['payer']['email_address']);
        self::assertArrayNotHasKey('payee', $payload['purchase_units'][0], 'JOW charges use the Reamur platform account, not a seller payee');
    }

    public function testRejectsAResponseForAnotherReferenceOrAmount(): void {
        $bads = [
            'other custom_id' => $this->createdOrder(['purchase_units' => [['custom_id' => 'other', 'invoice_id' => 'other', 'amount' => ['currency_code' => 'USD', 'value' => '125.50']]]]),
            'other amount' => $this->createdOrder(['purchase_units' => [['custom_id' => self::IDEMPOTENCY, 'invoice_id' => self::IDEMPOTENCY, 'amount' => ['currency_code' => 'USD', 'value' => '125.51']]]]),
            'other currency' => $this->createdOrder([], 'EUR'),
        ];

        foreach ($bads as $label => $bad) {
            $rejected = null;

            try {
                (new PaypalOrdersAdapter($this->client(['_http_status' => 200, 'access_token' => 'T1'], $bad)))
                    ->createPayment($this->request(), $this->account(), $this->credentials(), null);
            } catch (\RuntimeException $e) {
                $rejected = $e;
            }

            self::assertNotNull($rejected, 'PayPal must reject a mismatched order: ' . $label);
        }
    }

    public function testMissingClientCredentialsFailsClosedWithoutCallingTheProvider(): void {
        $client = $this->client(['_http_status' => 200, 'access_token' => 'T1'], $this->createdOrder());

        try {
            (new PaypalOrdersAdapter($client))->createPayment(
                $this->request(),
                $this->account(),
                new GatewayCredentials(['webhook_id' => self::WEBHOOK_ID]),
                null
            );
            self::fail('Expected missing client credentials to fail closed');
        } catch (\RuntimeException $e) {
            self::assertStringNotContainsString(self::CLIENT_SECRET, $e->getMessage());
        }

        self::assertSame([], $client->tokenCalls);
        self::assertSame([], $client->createCalls);
    }

    public function testCrossCurrencyQuoteIsRejected(): void {
        $client = $this->client();

        $this->expectException(\DomainException::class);
        (new PaypalOrdersAdapter($client))->quote(new Money('1.00', 'BRL', 2), 'USD', $this->account(), $this->credentials());
    }

    public function testApprovedOrderIsCapturedAndReconciledFromTheOrdersApi(): void {
        $orderId = self::ORDER_ID;
        $client = $this->client(
            ['_http_status' => 200, 'access_token' => 'A21TOKEN123'],
            [],
            [$orderId => $this->completedOrder($orderId)],
            [$orderId => ['_http_status' => 201, 'id' => self::CAPTURE_ID, 'status' => 'COMPLETED']],
            ['_http_status' => 200, 'verification_status' => 'SUCCESS']
        );
        $adapter = new PaypalOrdersAdapter($client);

        $event = $adapter->verifyNotification(
            $this->orderNotification($orderId, self::IDEMPOTENCY, 'CHECKOUT.ORDER.APPROVED'),
            $this->account(),
            $this->credentials()
        );

        self::assertSame(GatewayWebhookResult::VERIFIED, $event->kind);
        self::assertSame(self::IDEMPOTENCY, $event->providerReference);
        self::assertSame(self::CAPTURE_ID, $event->providerPaymentId);
        self::assertSame(GatewayPaymentStatus::PAID, $event->status);
        self::assertSame('125.50', $event->chargeAmount?->amount());
        self::assertCount(1, $client->verifyCalls);
        self::assertCount(1, $client->captureCalls);
        self::assertSame([PaypalOrdersClient::SANDBOX_API_URL, 'A21TOKEN123', $orderId, self::IDEMPOTENCY . ':capture'], $client->captureCalls[0]);
        self::assertSame([PaypalOrdersClient::SANDBOX_API_URL, 'A21TOKEN123', $orderId], $client->getOrderCalls[0]);
    }

    public function testCaptureEventsReconcileThroughTheParentOrder(): void {
        $orderId = self::ORDER_ID;
        $partial = $this->completedOrder($orderId, 'PARTIALLY_REFUNDED');
        $client = $this->client(
            ['_http_status' => 200, 'access_token' => 'A21TOKEN123'],
            [],
            [$orderId => $partial],
            [],
            ['_http_status' => 200, 'verification_status' => 'SUCCESS']
        );

        $body = json_encode([
            'id' => 'WH-REFUND-1',
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => self::CAPTURE_ID,
                'amount' => ['currency_code' => 'USD', 'value' => '50.00'],
                'supplementary_data' => ['related_ids' => ['order_id' => $orderId]],
            ],
        ], JSON_THROW_ON_ERROR);

        $event = (new PaypalOrdersAdapter($client))->verifyNotification(
            $this->signedNotification($body),
            $this->account(),
            $this->credentials()
        );

        self::assertSame(GatewayWebhookResult::VERIFIED, $event->kind);
        self::assertSame(GatewayPaymentStatus::PARTIALLY_REFUNDED, $event->status);
        self::assertSame(self::CAPTURE_ID, $event->providerPaymentId);
    }

    public function testRejectedSignatureIsIgnoredWithoutCapturing(): void {
        $orderId = self::ORDER_ID;
        $client = $this->client(
            ['_http_status' => 200, 'access_token' => 'A21TOKEN123'],
            [],
            [$orderId => $this->completedOrder($orderId)],
            [],
            ['_http_status' => 200, 'verification_status' => 'FAILURE']
        );

        $event = (new PaypalOrdersAdapter($client))->verifyNotification(
            $this->orderNotification($orderId, self::IDEMPOTENCY, 'CHECKOUT.ORDER.APPROVED'),
            $this->account(),
            $this->credentials()
        );

        self::assertSame(GatewayWebhookResult::IGNORED, $event->kind);
        self::assertSame('signature_rejected', $event->reasonCode);
        self::assertSame([], $client->captureCalls);
        self::assertSame([], $client->getOrderCalls);
    }

    public function testInconclusiveVerificationOrOutageRequestsRetry(): void {
        $orderId = self::ORDER_ID;
        $adapter = new PaypalOrdersAdapter($this->client(
            ['_http_status' => 200, 'access_token' => 'A21TOKEN123'],
            [],
            [$orderId => $this->completedOrder($orderId)],
            [],
            ['_http_status' => 503]
        ));

        $inconclusive = $adapter->verifyNotification(
            $this->orderNotification($orderId, self::IDEMPOTENCY, 'CHECKOUT.ORDER.APPROVED'),
            $this->account(),
            $this->credentials()
        );
        self::assertSame(GatewayWebhookResult::RETRY, $inconclusive->kind);
        self::assertSame('verification_inconclusive', $inconclusive->reasonCode);

        $outage = (new PaypalOrdersAdapter($this->client(
            ['_http_status' => 200, 'access_token' => 'A21TOKEN123'],
            [],
            [$orderId => ['_http_status' => 500]],
            [],
            ['_http_status' => 200, 'verification_status' => 'SUCCESS']
        )))->verifyNotification(
            $this->orderNotification($orderId, self::IDEMPOTENCY, 'CHECKOUT.ORDER.COMPLETED'),
            $this->account(),
            $this->credentials()
        );
        self::assertSame(GatewayWebhookResult::RETRY, $outage->kind);
        self::assertSame('provider_unavailable', $outage->reasonCode);
    }

    public function testMissingWebhookIdRequestsRetryWithoutApiCalls(): void {
        $client = $this->client(['_http_status' => 200, 'access_token' => 'A21TOKEN123']);

        $event = (new PaypalOrdersAdapter($client))->verifyNotification(
            $this->orderNotification(self::ORDER_ID, self::IDEMPOTENCY, 'CHECKOUT.ORDER.APPROVED'),
            $this->account(),
            new GatewayCredentials(['client_id' => self::CLIENT_ID, 'client_secret' => self::CLIENT_SECRET])
        );

        self::assertSame(GatewayWebhookResult::RETRY, $event->kind);
        self::assertSame('credentials_missing', $event->reasonCode);
        self::assertSame([], $client->verifyCalls);
        self::assertSame([], $client->captureCalls);
    }

    public function testUnhandledEventTypeIsIgnored(): void {
        $client = $this->client(['_http_status' => 200, 'access_token' => 'A21TOKEN123'], [], [], [], ['_http_status' => 200, 'verification_status' => 'SUCCESS']);

        $body = json_encode(['id' => 'WH-CUSTOM-1', 'event_type' => 'CUSTOMER.DISPUTE.CREATED', 'resource' => ['dispute_id' => 'PP-D-1']], JSON_THROW_ON_ERROR);

        $event = (new PaypalOrdersAdapter($client))->verifyNotification(
            $this->signedNotification($body),
            $this->account(),
            $this->credentials()
        );

        self::assertSame(GatewayWebhookResult::IGNORED, $event->kind);
        self::assertSame('unhandled_event_type', $event->reasonCode);
    }

    /** A signed, well-formed approved-order webhook. */
    private function orderNotification(string $orderId, string $customId, string $eventType): Notification {
        $body = json_encode([
            'id' => 'WH-ORDER-1',
            'event_type' => $eventType,
            'resource' => [
                'id' => $orderId,
                'status' => 'APPROVED',
                'purchase_units' => [['custom_id' => $customId]],
            ],
        ], JSON_THROW_ON_ERROR);

        return $this->signedNotification($body);
    }

    private function signedNotification(string $body): Notification {
        return new Notification([], [], [
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'a1b2c3d4-e5f6-7890-abcd-ef0123456789',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => '2026-11-19T17:00:00Z',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => str_repeat('a', 64),
            'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.com/v1/notifications/certs/CERT-1',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
        ], $body);
    }
}
