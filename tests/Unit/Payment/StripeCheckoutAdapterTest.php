<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayPaymentRecord;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\Money;
use Reamur\System\Library\Payment\Notification;
use Reamur\System\Library\Payment\StripeCheckoutAdapter;
use Reamur\System\Library\Payment\StripeCheckoutClient;

final class StripeCheckoutAdapterTest extends TestCase {
    private const SESSION_ID = 'cs_test_a1b2c3d4e5f6g7h8';
    private const PAYMENT_INTENT = 'pi_test_1a2b3c4d5e6f';
    private const IDEMPOTENCY = '71e3f12c-6b0c-5ac5-9b83-d1d43c90d0c9';
    private const SECRET_KEY = 'sk_test_platform_restricted_key_0001';
    private const WEBHOOK_SECRET = 'whsec_platform-webhook-secret-123456';

    private function account(string $environment = 'sandbox'): GatewayAccount {
        return new GatewayAccount(11, 'stripe', 'JOW platform', $environment, true, ['flow' => 'jow_internal']);
    }

    private function credentials(array $values = []): GatewayCredentials {
        return new GatewayCredentials($values + [
            'secret_key' => self::SECRET_KEY,
            'webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    private function request(
        string $currency = 'USD',
        string $idempotencyKey = self::IDEMPOTENCY,
        string $payerEmail = 'buyer@example.com'
    ): GatewayPaymentRequest {
        return new GatewayPaymentRequest(
            'jow_charge',
            42,
            new Money('125.50', $currency, 2),
            $currency,
            'checkout_session',
            $idempotencyKey,
            'JOW internal charge 42',
            'https://loja.example/catalog/index.php?route=common/home',
            'https://loja.example/catalog/index.php?route=checkout/gateway.notify&gateway_id=11',
            $payerEmail
        );
    }

    /** Fake client: all provider traffic is intercepted and stays local. */
    private function client(array $session = [], array $charges = [], array $sessions = []): StripeCheckoutClient {
        return new class($session, $charges, $sessions) extends StripeCheckoutClient {
            public array $createCalls = [];
            public array $getSessionCalls = [];
            public array $getChargeCalls = [];

            public function __construct(
                private array $createdSession,
                private array $charges,
                private array $sessions
            ) {
                parent::__construct(null, []);
            }

            public function createSession(array $form, string $secretKey, string $idempotencyKey): array {
                $this->createCalls[] = [$form, $secretKey, $idempotencyKey];
                return $this->createdSession;
            }

            public function getSession(string $sessionId, string $secretKey): array {
                $this->getSessionCalls[] = [$sessionId, $secretKey];
                return $this->sessions[$sessionId] ?? ['_http_status' => 404];
            }

            public function getCharge(string $chargeId, string $secretKey): array {
                $this->getChargeCalls[] = [$chargeId, $secretKey];
                return $this->charges[$chargeId] ?? ['_http_status' => 404];
            }
        };
    }

    private function createdSession(array $overrides = [], string $currency = 'usd'): array {
        return $overrides + [
            '_http_status' => 200,
            'id' => self::SESSION_ID,
            'object' => 'checkout.session',
            'client_reference_id' => self::IDEMPOTENCY,
            'payment_intent' => self::PAYMENT_INTENT,
            'url' => 'https://checkout.stripe.com/c/pay/' . self::SESSION_ID,
            'amount_total' => 12550,
            'currency' => $currency,
            'status' => 'open',
            'payment_status' => 'unpaid',
        ];
    }

    public function testCapabilitiesCoverCheckoutSessionInThreeCurrencies(): void {
        $capabilities = (new StripeCheckoutAdapter())->capabilities();

        self::assertTrue($capabilities->supports('checkout_session', 'BRL'));
        self::assertTrue($capabilities->supports('checkout_session', 'USD'));
        self::assertTrue($capabilities->supports('checkout_session', 'EUR'));
        self::assertFalse($capabilities->supports('payment_intent', 'USD'));
        self::assertFalse($capabilities->supports('checkout_session', 'JPY'));
        self::assertSame(2, $capabilities->fractionDigits('BRL'));
        self::assertSame(2, $capabilities->fractionDigits('USD'));
        self::assertSame(2, $capabilities->fractionDigits('EUR'));
    }

    public function testCreatesAnIdempotentHostedSessionOnThePlatformAccount(): void {
        $client = $this->client($this->createdSession());
        $adapter = new StripeCheckoutAdapter($client);

        $result = $adapter->createPayment($this->request(), $this->account(), $this->credentials(), null);

        self::assertSame(self::IDEMPOTENCY, $result->providerReference);
        self::assertSame(self::PAYMENT_INTENT, $result->providerPaymentId);
        self::assertSame(GatewayPaymentStatus::REQUIRES_ACTION, $result->status);
        self::assertSame(GatewayCustomerAction::REDIRECT, $result->customerAction->kind);
        self::assertSame('125.50', $result->chargeAmount->amount());
        self::assertCount(1, $client->createCalls);

        [$form, $secretKey, $idempotencyKey] = $client->createCalls[0];
        self::assertSame(self::SECRET_KEY, $secretKey);
        self::assertSame(self::IDEMPOTENCY, $idempotencyKey);
        self::assertSame('payment', $form['mode']);
        self::assertSame('usd', $form['line_items[0][price_data][currency]']);
        self::assertSame('12550', $form['line_items[0][price_data][unit_amount]']);
        self::assertSame('1', $form['line_items[0][quantity]']);
        self::assertSame(self::IDEMPOTENCY, $form['client_reference_id']);
        self::assertSame(self::IDEMPOTENCY, $form['metadata[jow_reference]']);
        self::assertSame('jow_charge', $form['metadata[jow_source_type]']);
        self::assertSame('42', $form['metadata[jow_source_id]']);
        self::assertSame('buyer@example.com', $form['customer_email']);
        self::assertArrayNotHasKey('application_fee_amount', $form, 'JOW charges use the Reamur platform account, not a Connect split');
        self::assertArrayNotHasKey('transfer_data', $form);
    }

    public function testOmitsCustomerEmailWhenNotProvided(): void {
        $client = $this->client($this->createdSession());

        (new StripeCheckoutAdapter($client))->createPayment(
            $this->request(payerEmail: ''),
            $this->account(),
            $this->credentials(),
            null
        );

        self::assertArrayNotHasKey('customer_email', $client->createCalls[0][0]);
    }

    public function testRejectsAResponseForAnotherReferenceOrAmount(): void {
        $bads = [
            'other reference' => $this->createdSession(['client_reference_id' => 'some-other-request']),
            'other amount' => $this->createdSession(['amount_total' => 12551]),
            'other currency' => $this->createdSession(['currency' => 'eur']),
        ];

        foreach ($bads as $label => $bad) {
            $rejected = null;

            try {
                (new StripeCheckoutAdapter($this->client($bad)))->createPayment($this->request(), $this->account(), $this->credentials(), null);
            } catch (\RuntimeException $e) {
                $rejected = $e;
            }

            self::assertNotNull($rejected, 'Stripe must reject a mismatched session: ' . $label);
        }
    }

    public function testMissingSecretKeyFailsClosedWithoutCallingTheProvider(): void {
        $client = $this->client($this->createdSession());

        try {
            (new StripeCheckoutAdapter($client))->createPayment(
                $this->request(),
                $this->account(),
                new GatewayCredentials(['webhook_secret' => self::WEBHOOK_SECRET]),
                null
            );
            self::fail('Expected missing secret key to fail closed');
        } catch (\RuntimeException $e) {
            self::assertStringNotContainsString(self::SECRET_KEY, $e->getMessage());
        }

        self::assertSame([], $client->createCalls);
    }

    public function testUnsupportedRequestsAreRejectedBeforeAnyProviderCall(): void {
        $client = $this->client($this->createdSession());
        $adapter = new StripeCheckoutAdapter($client);
        $account = $this->account();
        $credentials = $this->credentials();

        $this->expectException(\DomainException::class);
        try {
            $adapter->quote(new Money('1.00', 'BRL', 2), 'USD', $account, $credentials);
        } finally {
            self::assertSame([], $client->createCalls);
        }
    }

    public function testCompletedWebhookIsSignedFetchedAndReconciledFromTheApi(): void {
        $queried = $this->createdSession(['status' => 'complete', 'payment_status' => 'paid']);
        $client = $this->client([], [], [self::SESSION_ID => $queried]);
        $adapter = new StripeCheckoutAdapter($client);
        $notification = $this->sessionNotification(self::SESSION_ID, 'checkout.session.completed', self::WEBHOOK_SECRET);

        $event = $adapter->verifyNotification($notification, $this->account(), $this->credentials());

        self::assertSame(GatewayWebhookResult::VERIFIED, $event->kind);
        self::assertSame(self::IDEMPOTENCY, $event->providerReference);
        self::assertSame(self::PAYMENT_INTENT, $event->providerPaymentId);
        self::assertSame(GatewayPaymentStatus::PAID, $event->status);
        self::assertSame('125.50', $event->chargeAmount?->amount());
        self::assertSame([[self::SESSION_ID, self::SECRET_KEY]], $client->getSessionCalls);
    }

    public function testExpiredAndFailedAsyncSessionsMapToLedgerStatuses(): void {
        $expired = $this->createdSession(['status' => 'expired']);
        $client = $this->client([], [], [self::SESSION_ID => $expired]);
        $adapter = new StripeCheckoutAdapter($client);

        $expiredEvent = $adapter->verifyNotification(
            $this->sessionNotification(self::SESSION_ID, 'checkout.session.expired', self::WEBHOOK_SECRET),
            $this->account(),
            $this->credentials()
        );
        self::assertSame(GatewayPaymentStatus::EXPIRED, $expiredEvent->status);

        $failedEvent = $adapter->verifyNotification(
            $this->sessionNotification(self::SESSION_ID, 'checkout.session.async_payment_failed', self::WEBHOOK_SECRET),
            $this->account(),
            $this->credentials()
        );
        self::assertSame(GatewayPaymentStatus::FAILED, $failedEvent->status);
    }

    public function testPartialRefundComesFromTheChargesApiMetadata(): void {
        $charge = [
            '_http_status' => 200,
            'id' => 'ch_test_1a2b3c',
            'metadata' => ['jow_reference' => self::IDEMPOTENCY],
            'currency' => 'usd',
            'amount' => 12550,
            'amount_refunded' => 5050,
            'payment_intent' => self::PAYMENT_INTENT,
        ];
        $client = $this->client([], [$charge['id'] => $charge], []);
        $adapter = new StripeCheckoutAdapter($client);

        $event = $adapter->verifyNotification(
            $this->chargeNotification($charge['id'], self::WEBHOOK_SECRET),
            $this->account(),
            $this->credentials()
        );

        self::assertSame(GatewayWebhookResult::VERIFIED, $event->kind);
        self::assertSame(self::IDEMPOTENCY, $event->providerReference);
        self::assertSame(GatewayPaymentStatus::PARTIALLY_REFUNDED, $event->status);
        self::assertSame([[ $charge['id'], self::SECRET_KEY ]], $client->getChargeCalls);
    }

    public function testFullRefundMapsToRefunded(): void {
        $charge = [
            '_http_status' => 200,
            'id' => 'ch_test_9z8y7x',
            'metadata' => ['jow_reference' => self::IDEMPOTENCY],
            'currency' => 'usd',
            'amount' => 12550,
            'amount_refunded' => 12550,
            'payment_intent' => self::PAYMENT_INTENT,
        ];
        $client = $this->client([], [$charge['id'] => $charge], []);

        $event = (new StripeCheckoutAdapter($client))->verifyNotification(
            $this->chargeNotification($charge['id'], self::WEBHOOK_SECRET),
            $this->account(),
            $this->credentials()
        );

        self::assertSame(GatewayPaymentStatus::REFUNDED, $event->status);
    }

    public function testSignatureChecksTimestampToleranceAndRejectsTampering(): void {
        $body = '{"type":"checkout.session.completed","data":{"object":{"id":"' . self::SESSION_ID . '"}}}';
        $timestamp = (string)time();
        $rawSecret = 'platform-webhook-secret-123456';
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $rawSecret);

        self::assertTrue(StripeCheckoutAdapter::signatureValid(
            'whsec_' . $rawSecret,
            $body,
            't=' . $timestamp . ',v1=' . $signature,
            time()
        ));
        self::assertTrue(StripeCheckoutAdapter::signatureValid(
            $rawSecret,
            $body,
            't=' . $timestamp . ',v1=' . str_repeat('0', 64) . ',v1=' . $signature,
            time()
        ));
        self::assertFalse(StripeCheckoutAdapter::signatureValid(
            $rawSecret,
            $body,
            't=' . $timestamp . ',v1=' . $signature,
            time() + 600
        ), 'a stale timestamp must be rejected');
        self::assertFalse(StripeCheckoutAdapter::signatureValid(
            $rawSecret,
            $body . ' ',
            't=' . $timestamp . ',v1=' . $signature,
            time()
        ), 'the signature must cover the exact raw body');
        self::assertFalse(StripeCheckoutAdapter::signatureValid($rawSecret, $body, 't=' . $timestamp . ',v1=' . str_repeat('0', 64), time()));
        self::assertFalse(StripeCheckoutAdapter::signatureValid($rawSecret, $body, 't=' . $timestamp, time()));
    }

    public function testInvalidSignatureDoesNotQueryTheApi(): void {
        $client = $this->client([], [], [self::SESSION_ID => $this->createdSession()]);
        $body = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => self::SESSION_ID]]], JSON_THROW_ON_ERROR);
        $timestamp = (string)time();

        $event = (new StripeCheckoutAdapter($client))->verifyNotification(
            new Notification([], [], ['HTTP_STRIPE_SIGNATURE' => 't=' . $timestamp . ',v1=' . str_repeat('0', 64)], $body),
            $this->account(),
            $this->credentials()
        );

        self::assertSame(GatewayWebhookResult::IGNORED, $event->kind);
        self::assertSame('signature_rejected', $event->reasonCode);
        self::assertSame([], $client->getSessionCalls);
    }

    public function testUnhandledEventTypeIsIgnoredWithoutAnApiCall(): void {
        $client = $this->client([], [], []);
        $body = json_encode(['type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_1']]], JSON_THROW_ON_ERROR);
        $timestamp = (string)time();
        $secret = substr(self::WEBHOOK_SECRET, 6);
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        $event = (new StripeCheckoutAdapter($client))->verifyNotification(
            new Notification([], [], ['HTTP_STRIPE_SIGNATURE' => 't=' . $timestamp . ',v1=' . $signature], $body),
            $this->account(),
            $this->credentials()
        );

        self::assertSame(GatewayWebhookResult::IGNORED, $event->kind);
        self::assertSame('unhandled_event_type', $event->reasonCode);
        self::assertSame([], $client->getSessionCalls);
        self::assertSame([], $client->getChargeCalls);
    }

    public function testMissingCredentialsOrProviderOutageRequestsRetry(): void {
        $client = $this->client([], [], []);
        $adapter = new StripeCheckoutAdapter($client);
        $notification = $this->sessionNotification(self::SESSION_ID, 'checkout.session.completed', self::WEBHOOK_SECRET);

        $missing = $adapter->verifyNotification(
            $notification,
            $this->account(),
            new GatewayCredentials(['secret_key' => self::SECRET_KEY])
        );
        self::assertSame(GatewayWebhookResult::RETRY, $missing->kind);
        self::assertSame('credentials_missing', $missing->reasonCode);
        self::assertSame([], $client->getSessionCalls);

        $outage = (new StripeCheckoutAdapter($this->client([], [], [self::SESSION_ID => ['_http_status' => 503]])))->verifyNotification(
            $notification,
            $this->account(),
            $this->credentials()
        );
        self::assertSame(GatewayWebhookResult::RETRY, $outage->kind);
        self::assertSame('provider_unavailable', $outage->reasonCode);
    }

    /** Sessão paga como a API devolve depois de um pagamento aprovado. */
    private function paidSession(): array {
        return $this->createdSession([
            'status' => 'complete',
            'payment_status' => 'paid',
        ]);
    }

    /** Cobrança com estorno, indexada pelo id que a reconciliação vai pedir. */
    private function charge(string $id = self::PAYMENT_INTENT, int $amountRefunded = 0, int $amount = 12550): array {
        return [
            '_http_status' => 200,
            'id' => $id,
            'object' => 'charge',
            'metadata' => ['jow_reference' => self::IDEMPOTENCY],
            'currency' => 'usd',
            'amount' => $amount,
            'amount_refunded' => $amountRefunded,
            'payment_intent' => self::PAYMENT_INTENT,
        ];
    }

    private function record(string $status = GatewayPaymentStatus::PAID): GatewayPaymentRecord {
        return new GatewayPaymentRecord(
            41,
            11,
            'jow_charge',
            42,
            'stripe',
            'checkout_session',
            new Money('125.50', 'USD', 2),
            new Money('125.50', 'USD', 2),
            str_repeat('b', 48),
            self::IDEMPOTENCY,
            $status,
            self::IDEMPOTENCY,
            self::PAYMENT_INTENT,
            GatewayCustomerAction::redirect('https://checkout.stripe.com/c/pay/' . self::SESSION_ID),
            null,
            self::SESSION_ID
        );
    }

    /**
     * ADR 0124 — o buraco que a ADR 0123 deixou dito: a sessão de Checkout não
     * muda quando há estorno (`payment_status` continua 'paid'). Varrer a
     * tentativa paga sem olhar a cobrança consultaria para sempre um objeto que
     * responde "pago", e o estorno só chegaria pelo aviso assinado.
     */
    public function testReconciliationSeesARefundThatTheSessionCannotShow(): void {
        $client = $this->client([], [self::PAYMENT_INTENT => $this->charge(amountRefunded: 5050)], [
            self::SESSION_ID => $this->paidSession(),
        ]);

        $event = (new StripeCheckoutAdapter($client))->reconcile(
            $this->record(),
            $this->account(),
            $this->credentials()
        );

        self::assertSame(GatewayWebhookResult::VERIFIED, $event->kind);
        self::assertSame(GatewayPaymentStatus::PARTIALLY_REFUNDED, $event->status);
        self::assertSame(self::IDEMPOTENCY, $event->providerReference);
        self::assertSame([[self::SESSION_ID, self::SECRET_KEY]], $client->getSessionCalls);
        self::assertSame([[self::PAYMENT_INTENT, self::SECRET_KEY]], $client->getChargeCalls, 'a cobrança foi lida com o id que a sessão informou');
    }

    public function testReconciliationSeesAFullRefundAsRefunded(): void {
        $client = $this->client([], [self::PAYMENT_INTENT => $this->charge(amountRefunded: 12550)], [
            self::SESSION_ID => $this->paidSession(),
        ]);

        $event = (new StripeCheckoutAdapter($client))->reconcile($this->record(), $this->account(), $this->credentials());

        self::assertSame(GatewayWebhookResult::VERIFIED, $event->kind);
        self::assertSame(GatewayPaymentStatus::REFUNDED, $event->status);
    }

    /** Sem estorno, o veredicto é o da sessão — e não um "ignorado" a cada ciclo. */
    public function testReconciliationWithoutARefundKeepsTheSessionVerdict(): void {
        $client = $this->client([], [self::PAYMENT_INTENT => $this->charge(amountRefunded: 0)], [
            self::SESSION_ID => $this->paidSession(),
        ]);

        $event = (new StripeCheckoutAdapter($client))->reconcile($this->record(), $this->account(), $this->credentials());

        self::assertSame(GatewayWebhookResult::VERIFIED, $event->kind);
        self::assertSame(GatewayPaymentStatus::PAID, $event->status);
        self::assertSame([[self::PAYMENT_INTENT, self::SECRET_KEY]], $client->getChargeCalls, 'a cobrança chegou a ser consultada');
    }

    /** Sessão ainda aberta não gasta chamada de cobrança. */
    public function testAnUnpaidSessionDoesNotQueryTheCharge(): void {
        $client = $this->client([], [], [self::SESSION_ID => $this->createdSession()]);

        $event = (new StripeCheckoutAdapter($client))->reconcile(
            $this->record(GatewayPaymentStatus::PENDING),
            $this->account(),
            $this->credentials()
        );

        self::assertSame(GatewayPaymentStatus::REQUIRES_ACTION, $event->status);
        self::assertSame([], $client->getChargeCalls);
    }

    /** Cobrança de outro pagamento não vira veredicto sobre este. */
    public function testAChargeForAnotherPaymentIsRefused(): void {
        $client = $this->client([], [self::PAYMENT_INTENT => $this->charge('ch_de_outro_pagamento', 5050)], [
            self::SESSION_ID => $this->paidSession(),
        ]);

        $event = (new StripeCheckoutAdapter($client))->reconcile($this->record(), $this->account(), $this->credentials());

        self::assertSame(GatewayWebhookResult::IGNORED, $event->kind);
        self::assertSame('charge_not_reconciled', $event->reasonCode);
    }

    /** Provedor fora do ar na leitura da cobrança pede retry, não grava nada. */
    public function testAChargeOutageAsksForRetry(): void {
        $client = $this->client([], [self::PAYMENT_INTENT => ['_http_status' => 503]], [
            self::SESSION_ID => $this->paidSession(),
        ]);

        $event = (new StripeCheckoutAdapter($client))->reconcile($this->record(), $this->account(), $this->credentials());

        self::assertSame(GatewayWebhookResult::RETRY, $event->kind);
        self::assertSame('provider_unavailable', $event->reasonCode);
    }

    /** Sessão paga sem payment_intent consultável: nada de inventar id. */
    public function testAPaidSessionWithoutAPaymentIntentFallsBackToTheSessionState(): void {
        $client = $this->client([], [], [
            self::SESSION_ID => $this->createdSession(['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => null]),
        ]);

        $event = (new StripeCheckoutAdapter($client))->reconcile($this->record(), $this->account(), $this->credentials());

        self::assertSame(GatewayWebhookResult::VERIFIED, $event->kind);
        self::assertSame(GatewayPaymentStatus::PAID, $event->status);
        self::assertSame([], $client->getChargeCalls);
    }

    private function sessionNotification(string $sessionId, string $eventType, string $secret): Notification {
        $body = json_encode(
            ['id' => 'evt_test_1', 'type' => $eventType, 'data' => ['object' => ['id' => $sessionId]]],
            JSON_THROW_ON_ERROR
        );
        $timestamp = (string)time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, substr($secret, 6));

        return new Notification([], [], ['HTTP_STRIPE_SIGNATURE' => 't=' . $timestamp . ',v1=' . $signature], $body);
    }

    private function chargeNotification(string $chargeId, string $secret): Notification {
        $body = json_encode(
            ['id' => 'evt_test_2', 'type' => 'charge.refunded', 'data' => ['object' => ['id' => $chargeId]]],
            JSON_THROW_ON_ERROR
        );
        $timestamp = (string)time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, substr($secret, 6));

        return new Notification([], [], ['HTTP_STRIPE_SIGNATURE' => 't=' . $timestamp . ',v1=' . $signature], $body);
    }
}
