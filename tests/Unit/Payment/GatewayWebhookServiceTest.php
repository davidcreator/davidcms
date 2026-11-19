<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\FxQuote;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayAdapter;
use Reamur\System\Library\Payment\GatewayCapabilities;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayLedger;
use Reamur\System\Library\Payment\GatewayPaymentRecord;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentResult;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayRegistry;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\GatewayWebhookService;
use Reamur\System\Library\Payment\Money;
use Reamur\System\Library\Payment\Notification;

final class GatewayWebhookServiceTest extends TestCase {
    private function account(): GatewayAccount {
        return new GatewayAccount(11, 'webhook_test', 'Test account', 'sandbox', true);
    }

    private function record(string $status = GatewayPaymentStatus::REQUIRES_ACTION, string $providerPaymentId = ''): GatewayPaymentRecord {
        $amount = new Money('75.00', 'BRL', 2);
        return new GatewayPaymentRecord(
            20,
            11,
            'jow_charge',
            42,
            'webhook_test',
            'checkout_pro',
            $amount,
            $amount,
            str_repeat('a', 48),
            'jow-charge-42-attempt-01',
            $status,
            'order-ref-42',
            $providerPaymentId,
            GatewayCustomerAction::redirect('https://pay.example/checkout/42'),
            null
        );
    }

    private function service(WebhookServiceTestAdapter $adapter, WebhookServiceTestLedger $ledger): GatewayWebhookService {
        return new GatewayWebhookService(new GatewayRegistry([$adapter]), $ledger);
    }

    public function testVerifiedEventMatchesAmountAndIsRecordedThenApplied(): void {
        $event = GatewayWebhookResult::verified(
            'event-1',
            'order-ref-42',
            'provider-pay-42',
            GatewayPaymentStatus::PAID,
            '',
            new Money('75.00', 'BRL', 2)
        );
        $adapter = new WebhookServiceTestAdapter($event);
        $ledger = new WebhookServiceTestLedger($this->record());

        $result = $this->service($adapter, $ledger)->process($this->account(), new GatewayCredentials([]), new Notification());

        self::assertSame(GatewayWebhookResult::VERIFIED, $result->kind);
        self::assertSame(1, $ledger->recordCount);
        self::assertSame(1, $ledger->applyCount);
        self::assertSame(11, $ledger->gatewayId);
    }

    public function testAmountMismatchIsIgnoredWithoutWritingAnEventOrChangingStatus(): void {
        $event = GatewayWebhookResult::verified(
            'event-2',
            'order-ref-42',
            'provider-pay-42',
            GatewayPaymentStatus::PAID,
            '',
            new Money('75.01', 'BRL', 2)
        );
        $ledger = new WebhookServiceTestLedger($this->record());

        $result = $this->service(new WebhookServiceTestAdapter($event), $ledger)
            ->process($this->account(), new GatewayCredentials([]), new Notification());

        self::assertSame(GatewayWebhookResult::IGNORED, $result->kind);
        self::assertSame('amount_mismatch', $result->reasonCode);
        self::assertSame(0, $ledger->recordCount);
        self::assertSame(0, $ledger->applyCount);
    }

    public function testUnknownReferenceAndMismatchedProviderPaymentIdAreIgnored(): void {
        $unknown = GatewayWebhookResult::verified('event-3', 'other-order', '', GatewayPaymentStatus::PAID);
        $ledger = new WebhookServiceTestLedger($this->record());
        $result = $this->service(new WebhookServiceTestAdapter($unknown), $ledger)
            ->process($this->account(), new GatewayCredentials([]), new Notification());
        self::assertSame('payment_not_found', $result->reasonCode);
        self::assertSame(0, $ledger->applyCount);

        $wrongPayment = GatewayWebhookResult::verified('event-4', 'order-ref-42', 'payment-other', GatewayPaymentStatus::PAID);
        $ledger = new WebhookServiceTestLedger($this->record(providerPaymentId: 'payment-42'));
        $result = $this->service(new WebhookServiceTestAdapter($wrongPayment), $ledger)
            ->process($this->account(), new GatewayCredentials([]), new Notification());
        self::assertSame('payment_id_mismatch', $result->reasonCode);
        self::assertSame(0, $ledger->applyCount);
    }

    public function testOutOfOrderEventCannotMovePaidPaymentBackToActionRequired(): void {
        $event = GatewayWebhookResult::verified('event-old', 'order-ref-42', '', GatewayPaymentStatus::REQUIRES_ACTION);
        $ledger = new WebhookServiceTestLedger($this->record(GatewayPaymentStatus::PAID));

        $result = $this->service(new WebhookServiceTestAdapter($event), $ledger)
            ->process($this->account(), new GatewayCredentials([]), new Notification());

        self::assertSame(GatewayWebhookResult::IGNORED, $result->kind);
        self::assertSame('unsafe_status_transition', $result->reasonCode);
        self::assertSame(0, $ledger->recordCount);
        self::assertSame(0, $ledger->applyCount);
    }

    public function testPartialRefundIsAValidForwardTransition(): void {
        self::assertTrue(GatewayPaymentStatus::canTransition(GatewayPaymentStatus::PAID, GatewayPaymentStatus::PARTIALLY_REFUNDED));
        self::assertTrue(GatewayPaymentStatus::canTransition(GatewayPaymentStatus::PARTIALLY_REFUNDED, GatewayPaymentStatus::REFUNDED));
        self::assertFalse(GatewayPaymentStatus::canTransition(GatewayPaymentStatus::REFUNDED, GatewayPaymentStatus::PAID));
    }
}

final class WebhookServiceTestAdapter implements GatewayAdapter {
    public function __construct(private GatewayWebhookResult $event) {}

    public function code(): string { return 'webhook_test'; }
    public function label(): string { return 'Webhook test'; }
    public function capabilities(): GatewayCapabilities { return new GatewayCapabilities(['checkout_pro' => ['BRL']], ['BRL' => 2]); }
    public function quote(Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?FxQuote { return null; }
    public function createPayment(GatewayPaymentRequest $request, GatewayAccount $account, GatewayCredentials $credentials, ?FxQuote $quote): GatewayPaymentResult {
        throw new \LogicException('not used in webhook tests');
    }
    public function verifyNotification(Notification $notification, GatewayAccount $account, GatewayCredentials $credentials): GatewayWebhookResult { return $this->event; }
}

final class WebhookServiceTestLedger implements GatewayLedger {
    public int $recordCount = 0;
    public int $applyCount = 0;
    public int $gatewayId = 0;

    public function __construct(private ?GatewayPaymentRecord $record) {}
    public function latestForSource(string $sourceType, int $sourceId): ?GatewayPaymentRecord { return $this->record; }
    public function byPublicToken(string $token): ?GatewayPaymentRecord { return null; }
    public function byProviderReference(int $gatewayId, string $providerReference): ?GatewayPaymentRecord {
        return $this->record !== null && $this->record->providerReference === $providerReference ? $this->record : null;
    }
    public function byIdempotencyKey(int $gatewayId, GatewayPaymentRequest $request): ?GatewayPaymentRecord { return null; }
    public function createAttempt(int $gatewayId, string $providerCode, GatewayPaymentRequest $request, GatewayPaymentResult $result): GatewayPaymentRecord {
        throw new \LogicException('not used in webhook tests');
    }
    public function recordWebhookOnce(int $gatewayId, GatewayWebhookResult $event): bool {
        $this->gatewayId = $gatewayId;
        $this->recordCount++;
        return true;
    }
    public function applyVerifiedEvent(int $gatewayId, GatewayWebhookResult $event): void {
        $this->gatewayId = $gatewayId;
        $this->applyCount++;
    }
}
