<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayAccountStore;
use Reamur\System\Library\Payment\GatewayAdapter;
use Reamur\System\Library\Payment\GatewayCapabilities;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayEventApplier;
use Reamur\System\Library\Payment\GatewayPaymentRecord;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentResult;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayReconcilableLedger;
use Reamur\System\Library\Payment\GatewayReconciliationReport;
use Reamur\System\Library\Payment\GatewayReconciliationService;
use Reamur\System\Library\Payment\GatewayRegistry;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\Money;
use Reamur\System\Library\Payment\Notification;
use Reamur\System\Library\Payment\ReconcilableGatewayAdapter;

/**
 * P0-b: reconciliação ativa. Nenhuma chamada de rede — os adapters são falsos e
 * o ledger é em memória. O que se prova aqui é o comportamento do orquestrador:
 * quem entra na fila, o que é aplicado, o que é recusado e o que nunca acontece.
 */
final class GatewayReconciliationServiceTest extends TestCase {
    private const REFERENCE = 'ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9';
    private const NOW = '2026-11-20 12:00:00';

    private function account(string $providerCode = 'mp'): GatewayAccount {
        return new GatewayAccount(7, $providerCode, 'JOW platform', 'sandbox', true, ['flow' => 'jow_internal']);
    }

    private function credentials(): GatewayCredentials {
        return new GatewayCredentials(['access_token' => 'APP_USR_PLATFORM_TOKEN_1234567890']);
    }

    private function record(
        int $transactionId = 41,
        string $status = GatewayPaymentStatus::PENDING,
        string $reference = self::REFERENCE,
        string $providerCode = 'mp',
        string $paymentId = '',
        string $anchor = ''
    ): GatewayPaymentRecord {
        return new GatewayPaymentRecord(
            $transactionId,
            7,
            'jow_charge',
            42,
            $providerCode,
            'checkout_pro',
            new Money('125.50', 'BRL', 2),
            new Money('125.50', 'BRL', 2),
            str_repeat('a', 48),
            'idempotency-key-0042',
            $status,
            $reference,
            $paymentId,
            GatewayCustomerAction::redirect('https://gateway.example/pay/41'),
            null,
            $anchor
        );
    }

    private function service(GatewayAdapter $adapter, ReconciliationTestLedger $ledger): GatewayReconciliationService {
        return new GatewayReconciliationService(
            new GatewayRegistry([$adapter]),
            $ledger,
            new GatewayEventApplier($ledger),
            static fn(): \DateTimeImmutable => new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'))
        );
    }

    private function verified(string $status, string $reference = self::REFERENCE, string $paymentId = '', string $amount = '125.50'): GatewayWebhookResult {
        return GatewayWebhookResult::verified(
            'evt-' . $status,
            $reference,
            $paymentId,
            $status,
            '',
            new Money($amount, 'BRL', 2)
        );
    }

    /**
     * ADR 0123 (R1): o buraco que a ADR 0121 mediu. `pendingAttempts()` filtra
     * estado aberto, então a tentativa paga nunca era revisitada: um estorno
     * feito no painel do provedor só chegava ao CMS se o provedor reenviasse o
     * webhook.
     */
    public function testARefundMadeInTheProviderDashboardIsDiscoveredByTheSettledScan(): void {
        $ledger = new ReconciliationTestLedger();
        // A tentativa NAO esta na fila aberta: ela ja foi paga.
        $ledger->settled[] = $this->record(41, GatewayPaymentStatus::PAID);
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::REFUNDED));

        $report = $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame(1, $report->scanned, 'a varredura de pagas varreu a tentativa');
        $this->assertSame(1, $report->applied);
        $this->assertSame(GatewayPaymentStatus::REFUNDED, $report->details[0]['status_after']);
        $this->assertCount(1, $ledger->pendingCalls, 'a fila aberta foi consultada');
        $this->assertCount(1, $ledger->settledCalls, 'e a varredura de pagas foi consultada uma vez');
    }

    /**
     * O segundo estorno parcial leva de `partially_refunded` para `refunded`.
     * Varrer so `paid` deixaria esse caso invisivel a partir do primeiro.
     */
    public function testASecondPartialRefundIsStillSeenBecausePartiallyRefundedStaysInTheScan(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->settled[] = $this->record(41, GatewayPaymentStatus::PARTIALLY_REFUNDED);
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::REFUNDED));

        $report = $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame(1, $report->applied);
        $this->assertSame(GatewayPaymentStatus::PARTIALLY_REFUNDED, $report->details[0]['status_before']);
        $this->assertSame(GatewayPaymentStatus::REFUNDED, $report->details[0]['status_after']);
    }

    /**
     * D07: a janela das pagas e propria e maior. Com NOW 2026-11-20 12:00:00 e
     * o padrao de 30 dias, o corte enviado ao banco e 2026-10-21 12:00:00 —
     * independente da janela da fila aberta (7 dias).
     */
    public function testTheSettledScanUsesItsOwnThirtyDayWindow(): void {
        $ledger = new ReconciliationTestLedger();
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID));

        $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame('2026-10-21 12:00:00', $ledger->settledCalls[0][2]);
        $this->assertSame('2026-11-20 11:45:00', $ledger->pendingCalls[0][1], 'a fila aberta continua com min_age 15 min');
        $this->assertSame('2026-11-13 12:00:00', $ledger->pendingCalls[0][2], 'e com lookback de 7 dias');
    }

    /**
     * ADR 0124 — a ponta que faltava entre a varredura de pagas (ADR 0123) e o
     * Stripe: a sessão de Checkout não muda quando há estorno, então a varredura
     * sozinha não veria nada. Aqui a leitura da cobrança fecha o caminho e a
     * tentativa paga vira `partially_refunded` no ledger.
     */
    public function testAStripeRefundIsDiscoveredByTheSettledScan(): void {
        $sessionId = 'cs_test_a1b2c3d4e5f6g7h8';
        $paymentIntent = 'pi_test_1a2b3c4d5e6f';
        $reference = '71e3f12c-6b0c-5ac5-9b83-d1d43c90d0c9';

        $client = new class(
            ['_http_status' => 200, 'id' => $sessionId, 'client_reference_id' => $reference, 'currency' => 'usd',
             'amount_total' => 12550, 'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => $paymentIntent],
            ['_http_status' => 200, 'id' => $paymentIntent, 'metadata' => ['jow_reference' => $reference],
             'currency' => 'usd', 'amount' => 12550, 'amount_refunded' => 5050, 'payment_intent' => $paymentIntent]
        ) extends \Reamur\System\Library\Payment\StripeCheckoutClient {
            public function __construct(private array $session, private array $charge) {
                parent::__construct(null, []);
            }

            public function getSession(string $sessionId, string $secretKey): array {
                return $this->session;
            }

            public function getCharge(string $chargeId, string $secretKey): array {
                return $this->charge;
            }
        };

        $ledger = new ReconciliationTestLedger();
        $ledger->settled[] = new GatewayPaymentRecord(
            41, 11, 'jow_charge', 42, 'stripe', 'checkout_session',
            new Money('125.50', 'USD', 2), new Money('125.50', 'USD', 2),
            str_repeat('c', 48), $reference, GatewayPaymentStatus::PAID,
            $reference, $paymentIntent,
            GatewayCustomerAction::redirect('https://checkout.stripe.com/c/pay/' . $sessionId),
            null, $sessionId
        );

        $report = new GatewayReconciliationService(
            new GatewayRegistry([new \Reamur\System\Library\Payment\StripeCheckoutAdapter($client)]),
            $ledger,
            new GatewayEventApplier($ledger),
            static fn(): \DateTimeImmutable => new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'))
        )->reconcileAccount(
            new GatewayAccount(11, 'stripe', 'JOW platform', 'sandbox', true, ['flow' => 'jow_internal']),
            new GatewayCredentials(['secret_key' => 'sk_test_platform_restricted_key_0001'])
        );

        $this->assertSame(GatewayPaymentStatus::PAID, $report->details[0]['status_before']);
        $this->assertSame(GatewayPaymentStatus::PARTIALLY_REFUNDED, $report->details[0]['status_after']);
        $this->assertSame(1, $report->applied);
    }

    public function testASettledLookbackBeyondTheProviderWindowIsRefused(): void {
        $ledger = new ReconciliationTestLedger();
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID));

        $this->expectException(\InvalidArgumentException::class);

        $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials(), 25, 15, 7, 181);
    }

    public function testLostWebhookIsAppliedFromTheProviderState(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->records[] = $this->record();
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID, self::REFERENCE, 'pay-77'));

        $report = $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame(1, $report->scanned);
        $this->assertSame(1, $report->applied);
        $this->assertSame(0, $report->failed);
        $this->assertSame(GatewayPaymentStatus::PENDING, $report->details[0]['status_before']);
        $this->assertSame(GatewayPaymentStatus::PAID, $report->details[0]['status_after']);
        $this->assertSame('provider_state_applied', $report->details[0]['reason']);
        $this->assertSame(['evt-paid'], $ledger->appliedEventIds);
    }

    public function testProviderAnswerThatMatchesIsAppliedOnceOnly(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->records[] = $this->record(41, GatewayPaymentStatus::PAID);
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID));

        $report = $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame(0, $report->applied);
        $this->assertSame(1, $report->unchanged);
        $this->assertSame('provider_state_unchanged', $report->details[0]['reason']);
    }

    public function testSettledPaymentIsNeverRolledBackByAPollingAnswer(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->records[] = $this->record(41, GatewayPaymentStatus::PAID);
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PENDING));

        $report = $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame(1, $report->skipped);
        $this->assertSame('unsafe_status_transition', $report->details[0]['reason']);
        $this->assertSame(GatewayPaymentStatus::PAID, $report->details[0]['status_after']);
        $this->assertSame([], $ledger->appliedEventIds);
    }

    public function testProviderAmountMustMatchTheRecordedAttempt(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->records[] = $this->record();
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID, self::REFERENCE, '', '1.00'));

        $report = $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame('amount_mismatch', $report->details[0]['reason']);
        $this->assertSame([], $ledger->appliedEventIds);
    }

    public function testProviderPaymentIdCannotChange(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->records[] = $this->record(41, GatewayPaymentStatus::PENDING, self::REFERENCE, 'mp', 'pay-77');
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID, self::REFERENCE, 'pay-99'));

        $report = $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame('payment_id_mismatch', $report->details[0]['reason']);
        $this->assertSame([], $ledger->appliedEventIds);
    }

    public function testAnswerAboutAnotherAttemptIsRefused(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->records[] = $this->record();
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID, 'OTHER-REFERENCE-0000000000001'));

        $report = $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame(1, $report->skipped);
        $this->assertSame('reference_mismatch', $report->details[0]['reason']);
        $this->assertSame([], $ledger->appliedEventIds);
    }

    public function testIgnoredAndRetryAnswersAreCountedSeparately(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->records[] = $this->record(41);

        $ignored = $this->service(new ReconcilableTestAdapter(GatewayWebhookResult::ignored('missing_provider_anchor')), $ledger)
            ->reconcileAccount($this->account(), $this->credentials());
        $this->assertSame(1, $ignored->skipped);
        $this->assertSame('missing_provider_anchor', $ignored->details[0]['reason']);

        $retry = $this->service(new ReconcilableTestAdapter(GatewayWebhookResult::retry('provider_unavailable')), $ledger)
            ->reconcileAccount($this->account(), $this->credentials());
        $this->assertSame(1, $retry->retry);
        $this->assertSame(0, $retry->failed);
        $this->assertSame('provider_unavailable', $retry->details[0]['reason']);
    }

    public function testOneFailingAttemptNeverAbortsTheRun(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->records[] = $this->record(41, GatewayPaymentStatus::PENDING, 'REFERENCE-BROKEN-000000000001');
        $ledger->records[] = $this->record(42);
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID), new \RuntimeException('provider exploded'));

        $report = $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame(2, $report->scanned);
        $this->assertSame(2, $report->failed);
        $this->assertTrue($report->hasFailures());
        $this->assertSame('provider exploded', $report->details[0]['reason']);
    }

    public function testFailureOnApplyIsReportedAndNotSilentlyAccepted(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->records[] = $this->record();
        $ledger->failOnApply = true;

        $report = $this->service(new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID)), $ledger)
            ->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame(1, $report->failed);
        $this->assertSame('database is down', $report->details[0]['reason']);
        $this->assertSame('pending', $report->details[0]['status_after']);
    }

    public function testProviderWithoutReconciliationIsReportedNotIgnored(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->records[] = $this->record();

        $report = $this->service(new PlainTestAdapter(), $ledger)->reconcileAccount($this->account(), $this->credentials());

        $this->assertSame(1, $report->skipped);
        $this->assertSame('provider_not_reconcilable', $report->details[0]['reason']);
        $this->assertSame([], $ledger->pendingCalls, 'a provider that cannot be reconciled must not be queried');
    }

    public function testWindowAndPageAreComputedInUtc(): void {
        $ledger = new ReconciliationTestLedger();
        $adapter = new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID));

        $this->service($adapter, $ledger)->reconcileAccount($this->account(), $this->credentials(), 10, 30, 3);

        $this->assertSame([[7, '2026-11-20 11:30:00', '2026-11-17 12:00:00', 10]], $ledger->pendingCalls);
    }

    public function testLimitsAreEnforcedBeforeAnyQuery(): void {
        $ledger = new ReconciliationTestLedger();
        $service = $this->service(new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID)), $ledger);

        foreach ([[0, 15, 7], [201, 15, 7], [25, 0, 7], [25, 10081, 7], [25, 15, 0], [25, 15, 91]] as $case) {
            try {
                $service->reconcileAccount($this->account(), $this->credentials(), $case[0], $case[1], $case[2]);
                $this->fail('out-of-range parameters must be refused: ' . implode('/', $case));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame([], $ledger->pendingCalls);
    }

    public function testUnknownProviderInTheRegistryIsANoOp(): void {
        $ledger = new ReconciliationTestLedger();
        $service = new GatewayReconciliationService(
            new GatewayRegistry([]),
            $ledger,
            new GatewayEventApplier($ledger)
        );

        $report = $service->reconcileAccount($this->account('stripe'), $this->credentials());

        $this->assertSame(0, $report->scanned);
        $this->assertSame([], $ledger->pendingCalls);
    }

    public function testAccountListReconcilesEachAccountAndSurvivesBrokenCredentials(): void {
        $ledger = new ReconciliationTestLedger();
        $ledger->records[] = $this->record();
        $store = new ReconciliationTestStore(
            [new GatewayAccount(7, 'mp', 'JOW platform', 'sandbox', true), new GatewayAccount(8, 'mp', 'Broken', 'sandbox', true)],
            [7 => $this->credentials(), 8 => new \RuntimeException('credentials are locked')]
        );

        $report = $this->service(new ReconcilableTestAdapter($this->verified(GatewayPaymentStatus::PAID)), $ledger)
            ->reconcileAccountList($store, 5, 15, 7);

        $this->assertSame(2, $report->scanned);
        $this->assertSame(1, $report->applied);
        $this->assertSame(1, $report->failed);
        $this->assertStringContainsString('credentials_unavailable', $report->details[1]['reason']);
    }

    public function testReportExposesNoCredentialOrProviderPayload(): void {
        $report = GatewayReconciliationReport::forOne(
            41,
            'mp',
            'pending',
            'paid',
            GatewayReconciliationReport::APPLIED,
            'provider_state_applied'
        );
        $payload = $report->toArray();

        $this->assertSame(
            ['scanned', 'applied', 'unchanged', 'skipped', 'retry', 'failed', 'details'],
            array_keys($payload)
        );
        $this->assertSame(
            ['transaction_id', 'provider_code', 'status_before', 'status_after', 'outcome', 'reason'],
            array_keys($payload['details'][0])
        );
        $this->assertStringNotContainsString('APP_USR', (string)json_encode($payload));
    }

    public function testReasonsAreSanitizedBeforeLeavingTheService(): void {
        $this->assertSame('provider_unavailable', GatewayReconciliationReport::safeReason('provider_unavailable'));
        $this->assertStringNotContainsString(
            'comprador@testuser.com',
            GatewayReconciliationReport::safeReason('payer comprador@testuser.com failed')
        );
        $this->assertStringNotContainsString(
            'APP_USR_1234567890abcdef',
            GatewayReconciliationReport::safeReason('token APP_USR_1234567890abcdef rejected')
        );
        $this->assertLessThanOrEqual(120, strlen(GatewayReconciliationReport::safeReason(str_repeat('x ', 400))));
    }

    public function testReportsMergeAdditively(): void {
        $left = GatewayReconciliationReport::forOne(1, 'mp', 'pending', 'paid', GatewayReconciliationReport::APPLIED, 'a');
        $right = GatewayReconciliationReport::forOne(2, 'stripe', 'pending', 'failed', GatewayReconciliationReport::FAILED, 'b');

        $merged = $left->merge($right);

        $this->assertSame(2, $merged->scanned);
        $this->assertSame(1, $merged->applied);
        $this->assertSame(1, $merged->failed);
        $this->assertCount(2, $merged->details);
        $this->assertTrue($merged->hasFailures());
    }
}

/** Ledger em memória: registra o que foi aplicado e simula falhas de banco. */
final class ReconciliationTestLedger implements GatewayReconcilableLedger {
    /** @var list<GatewayPaymentRecord> */
    public array $records = [];

    /** @var list<array{0: int, 1: string, 2: string, 3: int}> */
    public array $pendingCalls = [];

    /** @var list<string> */
    public array $appliedEventIds = [];

    /** @var list<string> */
    public array $recordedEventIds = [];

    public bool $failOnApply = false;
    public bool $failOnRecord = false;

    /** @var list<GatewayPaymentRecord> ADR 0123 (R1): tentativas já pagas. */
    public array $settled = [];

    /** @var list<array{0: int, 1: string, 2: string, 3: int}> */
    public array $settledCalls = [];

    /** @return list<GatewayPaymentRecord> */
    public function pendingAttempts(int $gatewayId, string $modifiedBeforeUtc, string $createdAfterUtc, int $limit): array {
        $this->pendingCalls[] = [$gatewayId, $modifiedBeforeUtc, $createdAfterUtc, $limit];

        return array_slice($this->records, 0, $limit);
    }

    /** @return list<GatewayPaymentRecord> */
    public function settledAttempts(int $gatewayId, string $modifiedBeforeUtc, string $createdAfterUtc, int $limit): array {
        $this->settledCalls[] = [$gatewayId, $modifiedBeforeUtc, $createdAfterUtc, $limit];

        return array_slice($this->settled, 0, $limit);
    }

    public function latestForSource(string $sourceType, int $sourceId): ?GatewayPaymentRecord {
        return null;
    }

    public function byPublicToken(string $token): ?GatewayPaymentRecord {
        return null;
    }

    public function byProviderReference(int $gatewayId, string $providerReference): ?GatewayPaymentRecord {
        // No banco real esta busca e por indice e NAO filtra estado nem janela:
        // por isso olha as duas listas, senao a varredura de pagas nao acha a
        // propria tentativa que acabou de listar.
        foreach (array_merge($this->records, $this->settled) as $record) {
            if ($record->gatewayId === $gatewayId && $record->providerReference === $providerReference) {
                return $record;
            }
        }

        return null;
    }

    public function byIdempotencyKey(int $gatewayId, GatewayPaymentRequest $request): ?GatewayPaymentRecord {
        return null;
    }

    public function createAttempt(int $gatewayId, string $providerCode, GatewayPaymentRequest $request, GatewayPaymentResult $result): GatewayPaymentRecord {
        throw new \LogicException('reconciliation never creates attempts');
    }

    public function recordWebhookOnce(int $gatewayId, GatewayWebhookResult $event): bool {
        if ($this->failOnRecord) {
            throw new \RuntimeException('event table is down');
        }

        $this->recordedEventIds[] = $event->providerEventId;

        return true;
    }

    public function applyVerifiedEvent(int $gatewayId, GatewayWebhookResult $event): void {
        if ($this->failOnApply) {
            throw new \RuntimeException('database is down');
        }

        $this->appliedEventIds[] = $event->providerEventId;

        foreach ($this->records as $index => $record) {
            if ($record->providerReference !== $event->providerReference) {
                continue;
            }

            $this->records[$index] = new GatewayPaymentRecord(
                $record->transactionId,
                $record->gatewayId,
                $record->sourceType,
                $record->sourceId,
                $record->providerCode,
                $record->methodCode,
                $record->sourceAmount,
                $record->chargeAmount,
                $record->publicToken,
                $record->idempotencyKey,
                $event->status,
                $record->providerReference,
                $event->providerPaymentId !== '' ? $event->providerPaymentId : $record->providerPaymentId,
                $record->customerAction,
                $record->fxQuote,
                $record->providerCheckoutId
            );
        }
    }
}

/** Adapter que sabe reconciliar: devolve a resposta programada. */
final class ReconcilableTestAdapter implements GatewayAdapter, ReconcilableGatewayAdapter {
    public function __construct(
        private readonly GatewayWebhookResult $answer,
        private readonly ?\Throwable $throws = null
    ) {}

    public function code(): string {
        return 'mp';
    }

    public function label(): string {
        return 'Mercado Pago';
    }

    public function capabilities(): GatewayCapabilities {
        return new GatewayCapabilities(['checkout_pro' => ['BRL']], ['BRL' => 2]);
    }

    public function quote(Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?\Reamur\System\Library\Payment\FxQuote {
        return null;
    }

    public function createPayment(GatewayPaymentRequest $request, GatewayAccount $account, GatewayCredentials $credentials, ?\Reamur\System\Library\Payment\FxQuote $quote): GatewayPaymentResult {
        throw new \LogicException('reconciliation never creates payments');
    }

    public function verifyNotification(Notification $notification, GatewayAccount $account, GatewayCredentials $credentials): GatewayWebhookResult {
        return GatewayWebhookResult::ignored('not_used_in_this_test');
    }

    public function reconcile(GatewayPaymentRecord $record, GatewayAccount $account, GatewayCredentials $credentials): GatewayWebhookResult {
        if ($this->throws !== null) {
            throw $this->throws;
        }

        return $this->answer;
    }
}

/** Adapter sem suporte a reconciliação (só o contrato obrigatório). */
final class PlainTestAdapter implements GatewayAdapter {
    public function code(): string {
        return 'mp';
    }

    public function label(): string {
        return 'Mercado Pago';
    }

    public function capabilities(): GatewayCapabilities {
        return new GatewayCapabilities(['checkout_pro' => ['BRL']], ['BRL' => 2]);
    }

    public function quote(Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?\Reamur\System\Library\Payment\FxQuote {
        return null;
    }

    public function createPayment(GatewayPaymentRequest $request, GatewayAccount $account, GatewayCredentials $credentials, ?\Reamur\System\Library\Payment\FxQuote $quote): GatewayPaymentResult {
        throw new \LogicException('not used');
    }

    public function verifyNotification(Notification $notification, GatewayAccount $account, GatewayCredentials $credentials): GatewayWebhookResult {
        return GatewayWebhookResult::ignored('not_used');
    }
}

/** Store mínimo para exercitar a varredura por conta. */
final class ReconciliationTestStore implements GatewayAccountStore {
    /**
     * @param list<GatewayAccount> $accounts
     * @param array<int, GatewayCredentials|\Throwable> $credentials
     */
    public function __construct(private readonly array $accounts, private readonly array $credentials) {}

    public function find(int $gatewayId): ?GatewayAccount {
        foreach ($this->accounts as $account) {
            if ($account->gatewayId === $gatewayId) {
                return $account;
            }
        }

        return null;
    }

    /** @return list<GatewayAccount> */
    public function all(): array {
        return $this->accounts;
    }

    public function credentials(int $gatewayId): GatewayCredentials {
        $value = $this->credentials[$gatewayId] ?? new \RuntimeException('credentials missing');

        if ($value instanceof \Throwable) {
            throw $value;
        }

        return $value;
    }

    public function create(string $providerCode, string $accountLabel, string $environment, bool $enabled, array $publicConfig, array $secretFields): GatewayAccount {
        throw new \LogicException('not used');
    }

    public function update(int $gatewayId, string $accountLabel, string $environment, bool $enabled, array $publicConfig, array $secretUpdates = [], array $clearFields = []): GatewayAccount {
        throw new \LogicException('not used');
    }
}
