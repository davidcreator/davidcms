<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\Charge;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayEventApplier;
use Reamur\System\Library\Payment\GatewayAddressableLedger;
use Reamur\System\Library\Payment\GatewayPaymentRecord;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\Ledger;
use Reamur\System\Library\Payment\Money;
use Reamur\System\Library\Payment\OrderFlowLedger;
use Reamur\System\Library\Payment\OrderGatewayLedger;

/**
 * ADR 0119: o fluxo do pedido lê do ledger novo primeiro e escolhe **um** dono
 * por escrita. O que estes testes protegem:
 *
 *  - link emitido antes do deploy continua abrindo (leitura de compatibilidade);
 *  - pedido pago no ledger novo não aparece em aberto para o `Checkout`;
 *  - `record()` de uma tentativa de gateway nunca escreve em rms_mp_payment;
 *  - `record()` de um pagamento manual continua indo para o legado.
 */
final class OrderFlowLedgerTest extends TestCase {
    private function record(string $status = GatewayPaymentStatus::PENDING, string $paymentId = ''): GatewayPaymentRecord {
        return new GatewayPaymentRecord(
            501,
            7,
            'order',
            12,
            'stripe',
            'card',
            new Money('120.00', 'BRL', 2),
            new Money('120.00', 'BRL', 2),
            str_repeat('a', 48),
            'order-12-attempt-01',
            $status,
            'cs_test_501',
            $paymentId,
            GatewayCustomerAction::redirect('https://checkout.stripe.com/c/pay/cs_test_501'),
            null
        );
    }

    public function testReadsTheNewLedgerFirstAndKeepsLegacyLinksWorking(): void {
        $gateway = new OrderFlowTestGatewayLedger($this->record());
        $legacy = new OrderFlowTestLegacyLedger();
        $legacy->rows[12] = ['id' => 9, 'order_id' => 12, 'token' => 'old', 'status' => 'pending', 'url' => 'https://mp.example/old', 'payment_id' => ''];
        $ledgers = new OrderGatewayLedger($gateway, $legacy);

        $row = $ledgers->latestForOrder(12);

        self::assertSame('gateway', $row['source']);
        self::assertSame(501, $row['id']);
        self::assertSame('pending', $row['status']);
        self::assertSame('https://checkout.stripe.com/c/pay/cs_test_501', $row['url']);

        // Pedido sem tentativa no ledger novo: quem responde é o legado, e é isso
        // que mantém vivo o link que já está no e-mail do cliente.
        $gateway->nextRecord = null;
        $legacy->rows[99] = ['id' => 88, 'order_id' => 99, 'token' => 'old', 'status' => 'pending', 'url' => 'https://mp.example/old', 'payment_id' => ''];
        $legacyOnly = $ledgers->latestForOrder(99);
        self::assertSame('old', $legacyOnly['token']);
        self::assertSame(88, $legacyOnly['id']);
    }

    public function testLegacyLinkStillResolvesByTokenWhenTheNewLedgerHasNoRow(): void {
        $gateway = new OrderFlowTestGatewayLedger(null);
        $legacy = new OrderFlowTestLegacyLedger();
        $legacy->byTokenRows['deadbeef'] = [
            'id' => 9, 'order_id' => 12, 'token' => 'deadbeef',
            'status' => 'pending', 'url' => 'https://mp.example/old', 'payment_id' => '',
        ];

        $row = (new OrderGatewayLedger($gateway, $legacy))->byToken('deadbeef');

        self::assertSame(9, $row['id']);
        self::assertSame('https://mp.example/old', $row['url']);
    }

    public function testPaidAttemptReadsAsApprovedSoNoSecondLinkIsCreated(): void {
        $gateway = new OrderFlowTestGatewayLedger($this->record(GatewayPaymentStatus::PAID, 'pi_501'));
        $legacy = new OrderFlowTestLegacyLedger();
        $ledgers = new OrderGatewayLedger($gateway, $legacy);

        $row = $ledgers->latestForOrder(12);

        self::assertSame('approved', $row['status']);
        self::assertSame('pi_501', $row['payment_id']);
        self::assertSame(0, $legacy->latestCalls, 'o legado não deve ser consultado quando o ledger novo responde');
    }

    public function testClosedStatusesStopTakingTheCustomerToTheProvider(): void {
        self::assertSame('rejected', OrderGatewayLedger::legacyStatus(GatewayPaymentStatus::FAILED));
        self::assertSame('cancelled', OrderGatewayLedger::legacyStatus(GatewayPaymentStatus::EXPIRED));
        self::assertSame('cancelled', OrderGatewayLedger::legacyStatus(GatewayPaymentStatus::CANCELLED));
        self::assertSame('approved', OrderGatewayLedger::legacyStatus(GatewayPaymentStatus::REFUNDED));
        self::assertSame('pending', OrderGatewayLedger::legacyStatus(GatewayPaymentStatus::AUTHORIZED));
    }

    public function testGatewayRecordIsAppliedThroughTheSingleApplierAndNeverTouchesTheLegacyTable(): void {
        $gateway = new OrderFlowTestGatewayLedger($this->record(GatewayPaymentStatus::PENDING), '501');
        $legacy = new OrderFlowTestLegacyLedger();
        $ledger = new OrderFlowLedger(
            new OrderGatewayLedger($gateway, $legacy),
            $legacy,
            new GatewayEventApplier($gateway),
            $gateway
        );

        $ledger->record(501, 'pi_501', GatewayPaymentStatus::PAID);

        self::assertSame(0, $legacy->recordCalls, 'uma tentativa de gateway jamais escreve em rms_mp_payment');
        self::assertSame('pi_501', $gateway->applied['providerPaymentId'] ?? null);
        self::assertSame(GatewayPaymentStatus::PAID, $gateway->applied['status'] ?? null);
        self::assertSame('cs_test_501', $gateway->applied['providerReference'] ?? null);
    }

    public function testManualPaymentStillGoesToTheLegacyLedger(): void {
        $gateway = new OrderFlowTestGatewayLedger(null);
        $legacy = new OrderFlowTestLegacyLedger();
        $ledger = new OrderFlowLedger(new OrderGatewayLedger($gateway, $legacy), $legacy, null, $gateway);

        $ledger->record(9, '123456', 'approved');

        self::assertSame(1, $legacy->recordCalls);
        self::assertSame('123456', $legacy->lastRecorded['paymentId'] ?? null);
    }

    public function testCreatingAnAttemptThroughTheOldContractFailsLoudlyInsteadOfWritingTwoLedgers(): void {
        $gateway = new OrderFlowTestGatewayLedger($this->record());
        $legacy = new OrderFlowTestLegacyLedger();
        $ledger = new OrderFlowLedger(new OrderGatewayLedger($gateway, $legacy), $legacy, null, $gateway);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OrderPaymentLink');

        $ledger->add(12, 'token', 120.0, Charge::created('ref', 'https://provider.example/pay'));
    }

    public function testGatewayRecordWithoutSchemaFailsInsteadOfSilentlyDroppingThePayment(): void {
        $gateway = new OrderFlowTestGatewayLedger($this->record(GatewayPaymentStatus::PENDING), '501', false);
        $legacy = new OrderFlowTestLegacyLedger();
        $ledger = new OrderFlowLedger(new OrderGatewayLedger($gateway, $legacy), $legacy, null, $gateway);

        $this->expectException(\RuntimeException::class);

        $ledger->record(501, 'pi_501', GatewayPaymentStatus::PAID);
    }

    public function testOrderStatusChangeStaysInTheLegacyLedger(): void {
        $legacy = new OrderFlowTestLegacyLedger();
        $ledger = new OrderFlowLedger(new OrderGatewayLedger(new OrderFlowTestGatewayLedger(null), $legacy), $legacy, null);

        $ledger->moveOrder(12, 4, 'pedido #12 pago');

        self::assertSame([12, 4, 'pedido #12 pago'], $legacy->movedTo);
    }
}

/** Ledger novo de mentira: devolve a linha que o teste mandar. */
final class OrderFlowTestGatewayLedger implements GatewayAddressableLedger {
    public ?int $lastOrderId = null;
    /** @var array<string, mixed> */
    public array $applied = [];
    public ?GatewayPaymentRecord $nextRecord;
    private ?GatewayPaymentRecord $record;
    private string $paymentId;
    private bool $schema;

    public function __construct(?GatewayPaymentRecord $record, string $paymentId = '', bool $schema = true) {
        $this->record = $record;
        $this->nextRecord = $record;
        $this->paymentId = $paymentId;
        $this->schema = $schema;
    }

    public function latestForSource(string $sourceType, int $sourceId): ?GatewayPaymentRecord {
        $this->lastOrderId = $sourceId;

        return $this->nextRecord;
    }

    public function byPublicToken(string $token): ?GatewayPaymentRecord {
        return null;
    }

    public function byProviderReference(int $gatewayId, string $providerReference): ?GatewayPaymentRecord {
        return $this->record;
    }

    public function byIdempotencyKey(int $gatewayId, GatewayPaymentRequest $request): ?GatewayPaymentRecord {
        return null;
    }

    public function createAttempt(int $gatewayId, string $providerCode, GatewayPaymentRequest $request, \Reamur\System\Library\Payment\GatewayPaymentResult $result): GatewayPaymentRecord {
        throw new \LogicException('not used in this test');
    }

    public function recordWebhookOnce(int $gatewayId, GatewayWebhookResult $event): bool {
        return true;
    }

    public function applyVerifiedEvent(int $gatewayId, GatewayWebhookResult $event): void {
        if (!$this->schema) {
            throw new \RuntimeException('gateway schema missing');
        }

        $this->applied = [
            'providerReference' => $event->providerReference,
            'providerPaymentId' => $event->providerPaymentId,
            'status' => $event->status,
        ];
    }

    /** Usado pelo OrderFlowLedger para achar a linha pelo id do contrato antigo. */
    public function byTransactionId(int $transactionId): ?GatewayPaymentRecord {
        return $this->record;
    }
}

/** `DbLedger` legado de mentira: registra as chamadas em vez de falar com o banco. */
final class OrderFlowTestLegacyLedger implements Ledger {
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    /** @var array<string, array<string, mixed>> */
    public array $byTokenRows = [];
    public int $latestCalls = 0;
    public int $recordCalls = 0;
    /** @var array<string, mixed> */
    public array $lastRecorded = [];
    /** @var array<int, mixed> */
    public array $movedTo = [];

    public function latestForOrder(int $orderId): array {
        $this->latestCalls++;

        return $this->rows[$orderId] ?? [];
    }

    public function byToken(string $token): array {
        return $this->byTokenRows[$token] ?? [];
    }

    public function add(int $orderId, string $token, float $amount, Charge $charge): void {}

    public function record(int $id, string $paymentId, string $status): void {
        $this->recordCalls++;
        $this->lastRecorded = ['id' => $id, 'paymentId' => $paymentId, 'status' => $status];
    }

    public function moveOrder(int $orderId, int $orderStatusId, string $comment): void {
        $this->movedTo = [$orderId, $orderStatusId, $comment];
    }
}
