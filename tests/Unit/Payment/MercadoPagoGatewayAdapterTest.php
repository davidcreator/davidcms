<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\MercadopagoMarketplace;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\MercadoPagoGatewayAdapter;
use Reamur\System\Library\Payment\Money;
use Reamur\System\Library\Payment\Notification;

final class MercadoPagoGatewayAdapterTest extends TestCase {
    private const ORDER_ID = 'ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9';
    private const IDEMPOTENCY = '51e3f12c-6b0c-5ac5-9b83-d1d43c90d0c1';
    private const ACCESS_TOKEN = 'APP_USR_PLATFORM_TOKEN_1234567890';
    private const WEBHOOK_SECRET = 'platform-webhook-secret-1234567890';

    private function account(string $environment = 'sandbox'): GatewayAccount {
        return new GatewayAccount(7, 'mp', 'JOW platform', $environment, true, ['flow' => 'jow_internal']);
    }

    private function credentials(array $values = []): GatewayCredentials {
        return new GatewayCredentials($values + [
            'access_token' => self::ACCESS_TOKEN,
            'webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    private function request(
        string $idempotencyKey = self::IDEMPOTENCY,
        string $payerEmail = 'comprador@testuser.com'
    ): GatewayPaymentRequest {
        return new GatewayPaymentRequest(
            'jow_charge',
            42,
            new Money('125.50', 'BRL', 2),
            'BRL',
            'checkout_pro',
            $idempotencyKey,
            'Cobrança interna JOW 42',
            'https://loja.example/catalog/index.php?route=common/home',
            'https://loja.example/catalog/index.php?route=checkout/gateway.notify&gateway_id=7',
            $payerEmail
        );
    }

    /** Fake API client: all provider traffic is intercepted and stays local. */
    private function api(array $createdOrder = [], array $orders = []): MercadopagoMarketplace {
        return new class($createdOrder, $orders) extends MercadopagoMarketplace {
            public array $createCalls = [];
            public array $getCalls = [];

            public function __construct(private array $createdOrder, private array $orders) {
                parent::__construct([]);
            }

            public function createOrder(array $payload, string $accessToken, string $idempotencyKey): array {
                $this->createCalls[] = [$payload, $accessToken, $idempotencyKey];
                return $this->createdOrder;
            }

            public function getOrder(string $orderId, string $accessToken): array {
                $this->getCalls[] = [$orderId, $accessToken];
                return $this->orders[$orderId] ?? ['_http_status' => 404];
            }
        };
    }

    private function createdOrder(array $overrides = []): array {
        return $overrides + [
            '_http_status' => 201,
            'id' => self::ORDER_ID,
            'external_reference' => self::IDEMPOTENCY,
            'checkout_url' => 'https://www.mercadopago.com.br/checkout/v1/redirect?order_id=' . self::ORDER_ID,
            'currency' => 'BRL',
            'total_amount' => '125.50',
            'status' => 'created',
            'status_detail' => 'created',
        ];
    }

    public function testCapabilitiesAreExplicitlyLimitedToCheckoutProInBrl(): void {
        $capabilities = (new MercadoPagoGatewayAdapter())->capabilities();

        self::assertTrue($capabilities->supports('checkout_pro', 'BRL'));
        self::assertFalse($capabilities->supports('pix', 'BRL'));
        self::assertFalse($capabilities->supports('checkout_pro', 'USD'));
        self::assertSame(2, $capabilities->fractionDigits('BRL'));
    }

    public function testInvalidPayerEmailIsRejectedBeforeCreatingAnOrder(): void {
        $this->expectException(\InvalidArgumentException::class);
        new GatewayPaymentRequest(
            'jow_charge',
            42,
            new Money('125.50', 'BRL', 2),
            'BRL',
            'checkout_pro',
            self::IDEMPOTENCY,
            'Cobrança interna JOW 42',
            'https://loja.example/catalog/index.php?route=common/home',
            'https://loja.example/catalog/index.php?route=checkout/gateway.notify&gateway_id=7',
            'not-an-email'
        );
    }

    public function testSandboxRequiresAMercadoPagoTestUserEmail(): void {
        $api = $this->api($this->createdOrder());
        $adapter = new MercadoPagoGatewayAdapter($api);

        $this->expectException(\DomainException::class);
        try {
            $adapter->createPayment($this->request(self::IDEMPOTENCY, 'buyer@example.com'), $this->account(), $this->credentials(), null);
        } finally {
            self::assertSame([], $api->createCalls);
        }
    }

    public function testCreatesAnIdempotentPlatformOrderWithoutMarketplaceSplit(): void {
        $api = $this->api($this->createdOrder());
        $adapter = new MercadoPagoGatewayAdapter($api);

        $result = $adapter->createPayment($this->request(), $this->account(), $this->credentials(), null);

        self::assertSame(self::ORDER_ID, $result->providerReference);
        self::assertSame('', $result->providerPaymentId);
        self::assertSame(GatewayPaymentStatus::REQUIRES_ACTION, $result->status);
        self::assertSame(GatewayCustomerAction::REDIRECT, $result->customerAction->kind);
        self::assertSame('125.50', $result->chargeAmount->amount());
        self::assertCount(1, $api->createCalls);

        [$payload, $token, $idempotencyKey] = $api->createCalls[0];
        self::assertSame(self::ACCESS_TOKEN, $token);
        self::assertSame(self::IDEMPOTENCY, $idempotencyKey);
        self::assertSame('online', $payload['type']);
        self::assertSame('manual', $payload['processing_mode']);
        self::assertSame('automatic_async', $payload['capture_mode']);
        self::assertSame('125.50', $payload['total_amount']);
        self::assertSame(self::IDEMPOTENCY, $payload['external_reference']);
        self::assertSame('125.50', $payload['items'][0]['unit_price']);
        self::assertSame(1, $payload['items'][0]['quantity']);
        self::assertSame('comprador@testuser.com', $payload['payer']['email']);
        self::assertArrayNotHasKey('marketplace_fee', $payload, 'JOW charges use the Reamur platform account, not a seller split');
    }

    public function testUsesTheOrdersApiResponseAsTheCheckoutActionAndValidatesItsAmount(): void {
        $bad = $this->createdOrder(['total_amount' => '125.51']);
        $api = $this->api($bad);

        $this->expectException(\RuntimeException::class);
        (new MercadoPagoGatewayAdapter($api))->createPayment($this->request(), $this->account(), $this->credentials(), null);
    }

    public function testRejectsAResponseForAnotherIdempotencyReference(): void {
        $api = $this->api($this->createdOrder(['external_reference' => 'some-other-request']));

        $this->expectException(\RuntimeException::class);
        (new MercadoPagoGatewayAdapter($api))->createPayment($this->request(), $this->account(), $this->credentials(), null);
    }

    public function testMissingPlatformAccessTokenDoesNotCallTheProvider(): void {
        $api = $this->api($this->createdOrder());

        try {
            (new MercadoPagoGatewayAdapter($api))->createPayment(
                $this->request(),
                $this->account(),
                new GatewayCredentials(['webhook_secret' => self::WEBHOOK_SECRET]),
                null
            );
            self::fail('Expected missing account token to fail closed');
        } catch (\RuntimeException $e) {
            self::assertStringNotContainsString(self::ACCESS_TOKEN, $e->getMessage());
        }

        self::assertSame([], $api->createCalls);
    }

    public function testOrdersApiExternalReferenceLimitIsCheckedBeforeAnyProviderRequest(): void {
        $api = $this->api($this->createdOrder());
        $request = $this->request(str_repeat('a', 65));

        $this->expectException(\DomainException::class);
        try {
            (new MercadoPagoGatewayAdapter($api))->createPayment($request, $this->account(), $this->credentials(), null);
        } finally {
            self::assertSame([], $api->createCalls);
        }
    }

    public function testCurrencyMismatchAndFxAreRejected(): void {
        $adapter = new MercadoPagoGatewayAdapter($this->api($this->createdOrder()));
        $account = $this->account();
        $credentials = $this->credentials();

        $this->expectException(\DomainException::class);
        $adapter->quote(new Money('1.00', 'USD', 2), 'BRL', $account, $credentials);
    }

    public function testOrderWebhookIsSignedFetchedAndReconciledFromTheProvider(): void {
        $providerOrder = [
            '_http_status' => 200,
            'id' => self::ORDER_ID,
            'external_reference' => self::IDEMPOTENCY,
            'currency' => 'BRL',
            'total_amount' => '125.50',
            'total_paid_amount' => '125.50',
            'status' => 'processed',
            'status_detail' => 'accredited',
            'transactions' => ['payments' => [['id' => 'PAY01MERCADOPAGO123']]],
        ];
        $api = $this->api([], [self::ORDER_ID => $providerOrder]);
        $adapter = new MercadoPagoGatewayAdapter($api);
        $notification = $this->notification(self::ORDER_ID, 'order', self::WEBHOOK_SECRET);

        $event = $adapter->verifyNotification($notification, $this->account(), $this->credentials());

        self::assertSame(GatewayWebhookResult::VERIFIED, $event->kind);
        self::assertSame(self::ORDER_ID, $event->providerReference);
        self::assertSame('PAY01MERCADOPAGO123', $event->providerPaymentId);
        self::assertSame(GatewayPaymentStatus::PAID, $event->status);
        self::assertSame('125.50', $event->chargeAmount?->amount());
        self::assertSame([[self::ORDER_ID, self::ACCESS_TOKEN]], $api->getCalls);
    }

    public function testSignatureUsesLowercaseOrdersApiIdAndRejectsTampering(): void {
        $timestamp = '1784300000';
        $requestId = 'request-id-42';
        $manifest = 'id:' . strtolower(self::ORDER_ID) . ';request-id:' . $requestId . ';ts:' . $timestamp . ';';
        $signature = 'ts=' . $timestamp . ',v1=' . hash_hmac('sha256', $manifest, self::WEBHOOK_SECRET);

        self::assertTrue(MercadoPagoGatewayAdapter::signatureValid(self::WEBHOOK_SECRET, self::ORDER_ID, $signature, $requestId));
        self::assertFalse(MercadoPagoGatewayAdapter::signatureValid(self::WEBHOOK_SECRET, self::ORDER_ID, 'ts=' . $timestamp . ',v1=' . str_repeat('0', 64), $requestId));
        self::assertFalse(MercadoPagoGatewayAdapter::signatureValid(self::WEBHOOK_SECRET, self::ORDER_ID, $signature, "bad\r\nheader"));
    }

    public function testNonOrderOrMismatchedNoticeIsIgnoredWithoutAnApiCall(): void {
        $api = $this->api();
        $adapter = new MercadoPagoGatewayAdapter($api);

        $paymentNotice = new Notification(['type' => 'payment', 'data_id' => self::ORDER_ID]);
        $mismatch = new Notification(
            ['type' => 'order', 'data_id' => self::ORDER_ID],
            [],
            [],
            json_encode(['type' => 'order', 'data' => ['id' => 'ORDOTHER']]) ?: ''
        );

        self::assertSame(GatewayWebhookResult::IGNORED, $adapter->verifyNotification($paymentNotice, $this->account(), $this->credentials())->kind);
        self::assertSame('order_id_mismatch', $adapter->verifyNotification($mismatch, $this->account(), $this->credentials())->reasonCode);
        self::assertSame([], $api->getCalls);
    }

    public function testInvalidSignatureDoesNotQueryTheOrdersApi(): void {
        $api = $this->api();
        $notification = new Notification(
            ['type' => 'order', 'data_id' => self::ORDER_ID],
            [],
            ['HTTP_X_SIGNATURE' => 'ts=1784300000,v1=' . str_repeat('0', 64), 'HTTP_X_REQUEST_ID' => 'req-1'],
            '{"type":"order","data":{"id":"' . self::ORDER_ID . '"}}'
        );

        $event = (new MercadoPagoGatewayAdapter($api))->verifyNotification($notification, $this->account(), $this->credentials());

        self::assertSame(GatewayWebhookResult::IGNORED, $event->kind);
        self::assertSame('signature_rejected', $event->reasonCode);
        self::assertSame([], $api->getCalls);
    }

    public function testMissingWebhookCredentialsOrProviderOutageRequestsRetry(): void {
        $api = $this->api();
        $adapter = new MercadoPagoGatewayAdapter($api);
        $notification = $this->notification(self::ORDER_ID, 'order', self::WEBHOOK_SECRET);

        $missing = $adapter->verifyNotification(
            $notification,
            $this->account(),
            new GatewayCredentials(['access_token' => self::ACCESS_TOKEN])
        );
        self::assertSame(GatewayWebhookResult::RETRY, $missing->kind);
        self::assertSame('credentials_missing', $missing->reasonCode);
        self::assertSame([], $api->getCalls);

        $outageApi = $this->api([], [self::ORDER_ID => ['_http_status' => 503]]);
        $outage = (new MercadoPagoGatewayAdapter($outageApi))->verifyNotification(
            $notification,
            $this->account(),
            $this->credentials()
        );
        self::assertSame(GatewayWebhookResult::RETRY, $outage->kind);
        self::assertSame('provider_unavailable', $outage->reasonCode);
    }

    public function testOrderStatusesIncludePartialRefundAndChargeback(): void {
        $partial = [
            '_http_status' => 200, 'id' => self::ORDER_ID, 'external_reference' => self::IDEMPOTENCY,
            'currency' => 'BRL', 'total_amount' => '125.50', 'status' => 'processed',
            'status_detail' => 'partially_refunded',
        ];
        $disputed = array_replace($partial, ['status' => 'charged_back', 'status_detail' => 'in_process']);
        $api = $this->api([], [self::ORDER_ID => $partial]);
        $event = (new MercadoPagoGatewayAdapter($api))->verifyNotification(
            $this->notification(self::ORDER_ID, 'order', self::WEBHOOK_SECRET),
            $this->account(),
            $this->credentials()
        );
        self::assertSame(GatewayPaymentStatus::PARTIALLY_REFUNDED, $event->status);

        $disputedApi = $this->api([], [self::ORDER_ID => $disputed]);
        $disputedEvent = (new MercadoPagoGatewayAdapter($disputedApi))->verifyNotification(
            $this->notification(self::ORDER_ID, 'order', self::WEBHOOK_SECRET),
            $this->account(),
            $this->credentials()
        );
        self::assertSame(GatewayPaymentStatus::DISPUTED, $disputedEvent->status);
    }

    private function notification(string $orderId, string $type, string $secret): Notification {
        $timestamp = '1784300000';
        $requestId = 'request-id-42';
        $manifest = 'id:' . strtolower($orderId) . ';request-id:' . $requestId . ';ts:' . $timestamp . ';';
        $signature = 'ts=' . $timestamp . ',v1=' . hash_hmac('sha256', $manifest, $secret);

        return new Notification(
            ['type' => $type, 'data_id' => $orderId],
            [],
            ['HTTP_X_SIGNATURE' => $signature, 'HTTP_X_REQUEST_ID' => $requestId],
            json_encode(['type' => $type, 'data' => ['id' => $orderId]], JSON_THROW_ON_ERROR)
        );
    }
}
