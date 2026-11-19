<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use DateTimeImmutable;
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
use Reamur\System\Library\Payment\GatewayPaymentService;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayRegistry;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\Money;

final class GatewayPaymentServiceTest extends TestCase {
    private DateTimeImmutable $now;

    protected function setUp(): void {
        $this->now = new DateTimeImmutable('2026-11-17T12:00:00+00:00');
    }

    public function testSameCurrencyPaymentUsesExactSourceAmountAndPersists(): void {
        $adapter = new GatewayPaymentServiceTestAdapter();
        $ledger = new GatewayPaymentServiceTestLedger();
        $service = $this->service($adapter, $ledger);
        $request = $this->request('BRL');

        $record = $service->createPayment($this->account(), $request, new GatewayCredentials([]));

        self::assertSame('25.00', $record->chargeAmount->amount());
        self::assertSame('BRL', $record->chargeAmount->currency);
        self::assertSame(0, $adapter->quoteCalls);
        self::assertSame(1, $adapter->createCalls);
        self::assertTrue($ledger->persisted);
    }

    public function testCrossCurrencyUsesOnlyTheSelectedGatewayQuoteAndLocksIt(): void {
        $adapter = new GatewayPaymentServiceTestAdapter();
        $quote = new FxQuote(
            'stub_service', 'fx-900', new Money('25.00', 'BRL', 2), new Money('4.62', 'USD', 2),
            '0.1848', $this->now->modify('-1 minute'), $this->now->modify('+3 minutes')
        );
        $adapter->nextQuote = $quote;
        $ledger = new GatewayPaymentServiceTestLedger();
        $record = $this->service($adapter, $ledger)->createPayment(
            $this->account(), $this->request('USD'), new GatewayCredentials([])
        );

        self::assertSame(1, $adapter->quoteCalls);
        self::assertSame(1, $adapter->createCalls);
        self::assertSame($quote, $adapter->quotePassedToCreate);
        self::assertSame('4.62', $record->chargeAmount->amount());
        self::assertSame('fx-900', $record->fxQuote?->reference);
    }

    public function testExpiredGatewayQuoteFailsClosedBeforeCreatingPayment(): void {
        $adapter = new GatewayPaymentServiceTestAdapter();
        $adapter->nextQuote = new FxQuote(
            'stub_service', 'fx-expired', new Money('25.00', 'BRL', 2), new Money('4.62', 'USD', 2),
            '0.1848', $this->now->modify('-10 minutes'), $this->now->modify('-1 second')
        );
        $ledger = new GatewayPaymentServiceTestLedger();

        try {
            $this->service($adapter, $ledger)->createPayment(
                $this->account(), $this->request('USD'), new GatewayCredentials([])
            );
            self::fail('expired quote must be rejected');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('expired', $e->getMessage());
        }

        self::assertSame(0, $adapter->createCalls);
        self::assertFalse($ledger->persisted);
    }

    public function testUnsupportedCurrencyPairDoesNotCallQuoteOrCreate(): void {
        $adapter = new GatewayPaymentServiceTestAdapter();
        $ledger = new GatewayPaymentServiceTestLedger();
        $request = new GatewayPaymentRequest(
            'order', 101, new Money('25.00', 'BRL', 2), 'EUR', 'card', 'order-101-attempt-01', 'Order 101',
            'https://shop.example/return', 'https://shop.example/notify'
        );

        $this->expectException(\DomainException::class);
        try {
            $this->service($adapter, $ledger)->createPayment($this->account(), $request, new GatewayCredentials([]));
        } finally {
            self::assertSame(0, $adapter->quoteCalls);
            self::assertSame(0, $adapter->createCalls);
        }
    }

    public function testMissingGatewayQuoteDoesNotFallBackToLocalConversion(): void {
        $adapter = new GatewayPaymentServiceTestAdapter();
        $ledger = new GatewayPaymentServiceTestLedger();

        $this->expectException(\RuntimeException::class);
        try {
            $this->service($adapter, $ledger)->createPayment(
                $this->account(), $this->request('USD'), new GatewayCredentials([])
            );
        } finally {
            self::assertSame(1, $adapter->quoteCalls);
            self::assertSame(0, $adapter->createCalls);
            self::assertFalse($ledger->persisted);
        }
    }

    public function testProviderChargeAmountMustMatchLockedQuoteExactly(): void {
        $adapter = new GatewayPaymentServiceTestAdapter();
        $adapter->nextQuote = new FxQuote(
            'stub_service', 'fx-exact', new Money('25.00', 'BRL', 2), new Money('4.62', 'USD', 2),
            '0.1848', $this->now->modify('-1 minute'), $this->now->modify('+3 minutes')
        );
        $adapter->wrongChargeAmount = new Money('4.63', 'USD', 2);
        $ledger = new GatewayPaymentServiceTestLedger();

        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->service($adapter, $ledger)->createPayment(
                $this->account(), $this->request('USD'), new GatewayCredentials([])
            );
        } finally {
            self::assertSame(1, $adapter->createCalls);
            self::assertFalse($ledger->persisted);
        }
    }

    public function testQuoteFromWrongProviderIsRejected(): void {
        $adapter = new GatewayPaymentServiceTestAdapter();
        $adapter->nextQuote = new FxQuote(
            'other_provider', 'fx-wrong', new Money('25.00', 'BRL', 2), new Money('4.62', 'USD', 2),
            '0.1848', $this->now->modify('-1 minute'), $this->now->modify('+3 minutes')
        );
        $ledger = new GatewayPaymentServiceTestLedger();

        $this->expectException(\RuntimeException::class);
        try {
            $this->service($adapter, $ledger)->createPayment(
                $this->account(), $this->request('USD'), new GatewayCredentials([])
            );
        } finally {
            self::assertSame(0, $adapter->createCalls);
            self::assertFalse($ledger->persisted);
        }
    }

    public function testPreviouslyRecordedIdempotencyKeyDoesNotCreateAnotherGatewayPayment(): void {
        $adapter = new GatewayPaymentServiceTestAdapter();
        $ledger = new GatewayPaymentServiceTestLedger();
        $request = $this->request('BRL');
        $ledger->existing = new GatewayPaymentRecord(
            99, 7, 'jow_charge', 42, 'stub_service', 'card', $request->sourceAmount, $request->sourceAmount,
            str_repeat('a', 48), $request->idempotencyKey, GatewayPaymentStatus::PENDING, 'existing-ref', '',
            GatewayCustomerAction::redirect('https://gateway.example/pay/existing'), null
        );

        $record = $this->service($adapter, $ledger)->createPayment($this->account(), $request, new GatewayCredentials([]));

        self::assertSame(99, $record->transactionId);
        self::assertSame(0, $adapter->quoteCalls);
        self::assertSame(0, $adapter->createCalls);
        self::assertFalse($ledger->persisted);
    }

    public function testLiveEnvironmentIsDisabledByDefault(): void {
        $adapter = new GatewayPaymentServiceTestAdapter();
        $ledger = new GatewayPaymentServiceTestLedger();
        $live = new GatewayAccount(7, 'stub_service', 'Live', 'live', true);

        $this->expectException(\RuntimeException::class);
        try {
            $this->service($adapter, $ledger)->createPayment($live, $this->request('BRL'), new GatewayCredentials([]));
        } finally {
            self::assertSame(0, $adapter->createCalls);
            self::assertFalse($ledger->persisted);
        }
    }

    public function testDisabledGatewayAccountIsRejectedBeforeProviderCalls(): void {
        $adapter = new GatewayPaymentServiceTestAdapter();
        $ledger = new GatewayPaymentServiceTestLedger();
        $disabled = new GatewayAccount(7, 'stub_service', 'Test', 'sandbox', false);

        $this->expectException(\RuntimeException::class);
        try {
            $this->service($adapter, $ledger)->createPayment($disabled, $this->request('BRL'), new GatewayCredentials([]));
        } finally {
            self::assertSame(0, $adapter->createCalls);
        }
    }

    private function service(GatewayPaymentServiceTestAdapter $adapter, GatewayPaymentServiceTestLedger $ledger): GatewayPaymentService {
        return new GatewayPaymentService(
            new GatewayRegistry([$adapter]),
            $ledger,
            fn(): DateTimeImmutable => $this->now
        );
    }

    private function account(): GatewayAccount {
        return new GatewayAccount(7, 'stub_service', 'Test account', 'sandbox', true);
    }

    private function request(string $chargeCurrency): GatewayPaymentRequest {
        return new GatewayPaymentRequest(
            'jow_charge', 42, new Money('25.00', 'BRL', 2), $chargeCurrency, 'card',
            'jow_charge-42-attempt-01', 'Cobrança 42',
            'https://shop.example/return', 'https://shop.example/notify'
        );
    }
}

final class GatewayPaymentServiceTestAdapter implements GatewayAdapter {
    public int $quoteCalls = 0;
    public int $createCalls = 0;
    public ?FxQuote $nextQuote = null;
    public ?FxQuote $quotePassedToCreate = null;
    public ?Money $wrongChargeAmount = null;

    public function code(): string { return 'stub_service'; }
    public function label(): string { return 'Stub service'; }

    public function capabilities(): GatewayCapabilities {
        return new GatewayCapabilities(
            ['card' => ['BRL', 'USD']],
            ['BRL' => 2, 'USD' => 2],
            ['BRL' => ['USD']]
        );
    }

    public function quote(Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?FxQuote {
        $this->quoteCalls++;
        return $this->nextQuote;
    }

    public function createPayment(
        GatewayPaymentRequest $request,
        GatewayAccount $account,
        GatewayCredentials $credentials,
        ?FxQuote $quote
    ): GatewayPaymentResult {
        $this->createCalls++;
        $this->quotePassedToCreate = $quote;
        $amount = $this->wrongChargeAmount ?? ($quote?->chargeAmount ?? $request->sourceAmount);

        return new GatewayPaymentResult(
            'provider-ref-42',
            GatewayCustomerAction::redirect('https://gateway.example/pay/42'),
            $amount,
            GatewayPaymentStatus::PENDING,
            $quote,
            'provider-payment-42'
        );
    }

    public function verifyNotification(
        \Reamur\System\Library\Payment\Notification $notification,
        GatewayAccount $account,
        GatewayCredentials $credentials
    ): GatewayWebhookResult {
        return GatewayWebhookResult::ignored('not_a_payment');
    }
}

final class GatewayPaymentServiceTestLedger implements GatewayLedger {
    public bool $persisted = false;
    public ?GatewayPaymentRecord $existing = null;
    private int $nextId = 100;

    public function latestForSource(string $sourceType, int $sourceId): ?GatewayPaymentRecord { return null; }
    public function byPublicToken(string $token): ?GatewayPaymentRecord { return null; }
    public function byProviderReference(int $gatewayId, string $providerReference): ?GatewayPaymentRecord { return null; }

    public function byIdempotencyKey(int $gatewayId, GatewayPaymentRequest $request): ?GatewayPaymentRecord {
        return $this->existing;
    }

    public function createAttempt(int $gatewayId, string $providerCode, GatewayPaymentRequest $request, GatewayPaymentResult $result): GatewayPaymentRecord {
        $this->persisted = true;
        return new GatewayPaymentRecord(
            $this->nextId++,
            $gatewayId,
            $request->sourceType,
            $request->sourceId,
            $providerCode,
            $request->paymentMethod,
            $request->sourceAmount,
            $result->chargeAmount,
            str_repeat('b', 48),
            $request->idempotencyKey,
            $result->status,
            $result->providerReference,
            $result->providerPaymentId,
            $result->customerAction,
            $result->quote
        );
    }

    public function recordWebhookOnce(int $gatewayId, GatewayWebhookResult $event): bool { return true; }
    public function applyVerifiedEvent(int $gatewayId, GatewayWebhookResult $event): void {}
}
