<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\DbGatewayLedger;
use Reamur\System\Library\Payment\FxQuote;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentResult;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\Money;

final class DbGatewayLedgerCoreTest extends TestCase {
    public function testCreateAttemptPersistsNeutralSourceFxAndSafeCustomerAction(): void {
        $db = new GatewayLedgerTestDb();
        $db->lastId = 81;
        $db->affected = 1;
        $ledger = new DbGatewayLedger($db, 'rms_');
        $now = new DateTimeImmutable('2026-11-17T12:00:00+00:00');
        $source = new Money('100.00', 'BRL', 2);
        $charge = new Money('18.40', 'USD', 2);
        $quote = new FxQuote('stub', 'fx-81', $source, $charge, '0.184', $now, $now->modify('+5 minutes'));
        $request = new GatewayPaymentRequest(
            'jow_charge', 42, $source, 'USD', 'card', 'jow_charge-42-attempt-01', 'Cobrança 42',
            'https://shop.example/return', 'https://shop.example/notify'
        );
        $result = new GatewayPaymentResult(
            'provider-ref-81', GatewayCustomerAction::redirect('https://gateway.example/pay/81'), $charge,
            GatewayPaymentStatus::PENDING, $quote, 'provider-payment-81'
        );

        $record = $ledger->createAttempt(7, 'stub', $request, $result);

        self::assertSame(81, $record->transactionId);
        self::assertSame('jow_charge', $record->sourceType);
        self::assertSame('BRL', $record->sourceAmount->currency);
        self::assertSame('USD', $record->chargeAmount->currency);
        self::assertSame('fx-81', $record->fxQuote?->reference);
        self::assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $record->publicToken);
        self::assertStringNotContainsString($record->publicToken, print_r($record, true));
        self::assertStringNotContainsString('https://gateway.example/pay/81', print_r($record, true));
        self::assertStringContainsString('`source_type`', end($db->queries));
        self::assertStringContainsString("`provider_code` = 'stub'", end($db->queries));
        self::assertStringContainsString("`source_currency` = 'BRL'", end($db->queries));
        self::assertStringContainsString("`charge_currency` = 'USD'", end($db->queries));
        self::assertStringContainsString("`fx_quote_reference` = 'fx-81'", end($db->queries));
        self::assertStringContainsString('`customer_action_json`', end($db->queries));
        self::assertStringContainsString('UTC_TIMESTAMP()', end($db->queries));
    }

    public function testSameCurrencyAttemptNeedsNoQuoteAndStoresProviderCode(): void {
        $db = new GatewayLedgerTestDb();
        $db->lastId = 82;
        $db->affected = 1;
        $ledger = new DbGatewayLedger($db, 'rms_');
        $source = new Money('25.00', 'BRL', 2);
        $request = new GatewayPaymentRequest(
            'order', 5, $source, 'BRL', 'pix', 'order-5-attempt-0001', 'Pedido 5',
            'https://shop.example/return', 'https://shop.example/notify'
        );
        $result = new GatewayPaymentResult(
            'provider-ref-82', GatewayCustomerAction::pixCopyPaste('00020101021226890014br.gov.bcb.pix'),
            $source, GatewayPaymentStatus::PENDING
        );

        $record = $ledger->createAttempt(8, 'stub', $request, $result);

        self::assertSame('stub', $record->providerCode);
        self::assertNull($record->fxQuote);
        self::assertSame('25.00', $record->chargeAmount->amount());
        self::assertStringContainsString("`fx_provider_code` = ''", end($db->queries));
    }

    public function testDuplicateWebhookIsReportedWithoutStoringPayload(): void {
        $db = new GatewayLedgerTestDb();
        $ledger = new DbGatewayLedger($db, 'rms_');
        $event = GatewayWebhookResult::verified('evt-1', 'ref-1', 'pay-1', GatewayPaymentStatus::PAID, str_repeat('a', 64));

        $db->affected = 1;
        self::assertTrue($ledger->recordWebhookOnce(3, $event));
        $insert = end($db->queries);
        self::assertStringContainsString("`event_sha256` = '" . str_repeat('a', 64) . "'", $insert);
        self::assertStringNotContainsString('body=', $insert);

        $db->affected = 0;
        $db->rows[] = ['event_sha256' => str_repeat('a', 64), 'event_type' => GatewayPaymentStatus::PAID];
        self::assertFalse($ledger->recordWebhookOnce(3, $event));
    }

    public function testUnverifiedWebhookCannotBeRecordedOrApplied(): void {
        $ledger = new DbGatewayLedger(new GatewayLedgerTestDb(), 'rms_');

        $this->expectException(\InvalidArgumentException::class);
        $ledger->recordWebhookOnce(3, GatewayWebhookResult::ignored('not_a_payment'));
    }

    public function testReusedEventIdWithDifferentDigestIsRejected(): void {
        $db = new GatewayLedgerTestDb();
        $db->affected = 0;
        $db->rows[] = ['event_sha256' => str_repeat('b', 64), 'event_type' => GatewayPaymentStatus::PAID];
        $ledger = new DbGatewayLedger($db, 'rms_');
        $event = GatewayWebhookResult::verified('evt-1', 'ref-1', 'pay-1', GatewayPaymentStatus::PAID, str_repeat('a', 64));

        $this->expectException(\DomainException::class);
        $ledger->recordWebhookOnce(3, $event);
    }

    public function testVerifiedEventAppliesOnlyAllowedTransitionAndMarksEventProcessed(): void {
        $db = new GatewayLedgerTestDb();
        $db->rows[] = $this->row(GatewayPaymentStatus::PENDING);
        $db->affected = 1;
        $ledger = new DbGatewayLedger($db, 'rms_');
        $event = GatewayWebhookResult::verified('evt-paid', 'ref-10', 'pay-10', GatewayPaymentStatus::PAID);

        $ledger->applyVerifiedEvent(7, $event);

        self::assertStringContainsString("`status` = 'paid'", $db->queries[1]);
        self::assertStringContainsString("`provider_payment_id` = 'pay-10'", $db->queries[1]);
        self::assertStringContainsString("`status` = 'processed'", $db->queries[2]);
    }

    public function testLateFailedEventCannotReverseAPaidTransaction(): void {
        $db = new GatewayLedgerTestDb();
        $db->rows[] = $this->row(GatewayPaymentStatus::PAID);
        $ledger = new DbGatewayLedger($db, 'rms_');
        $event = GatewayWebhookResult::verified('evt-late', 'ref-10', 'pay-10', GatewayPaymentStatus::FAILED);

        $this->expectException(\DomainException::class);
        $ledger->applyVerifiedEvent(7, $event);
    }

    public function testProviderEventAmountMustMatchThePersistedChargeExactly(): void {
        $db = new GatewayLedgerTestDb();
        $db->rows[] = $this->row(GatewayPaymentStatus::PENDING);
        $ledger = new DbGatewayLedger($db, 'rms_');
        $event = GatewayWebhookResult::verified(
            'evt-wrong-amount',
            'ref-10',
            'pay-10',
            GatewayPaymentStatus::PAID,
            '',
            new Money('25.01', 'BRL', 2)
        );

        $this->expectException(\RuntimeException::class);
        $ledger->applyVerifiedEvent(7, $event);
    }

    public function testLookupRehydratesExactFxQuoteAndStoredCustomerAction(): void {
        $db = new GatewayLedgerTestDb();
        $row = $this->row(GatewayPaymentStatus::PENDING);
        $row['source_amount'] = '100.00';
        $row['charge_currency'] = 'USD';
        $row['charge_amount'] = '18.40';
        $row['fx_provider_code'] = 'stub';
        $row['fx_quote_reference'] = 'fx-lookup';
        $row['fx_rate'] = '0.184000000000000000';
        $row['fx_quoted_at'] = '2026-11-17 12:00:00';
        $row['fx_expires_at'] = '2026-11-17 12:05:00';
        $db->rows[] = $row;
        $ledger = new DbGatewayLedger($db, 'rms_');

        $record = $ledger->byProviderReference(7, 'ref-10');

        self::assertNotNull($record);
        self::assertSame('100.00', $record->sourceAmount->amount());
        self::assertSame('18.40', $record->chargeAmount->amount());
        self::assertSame('0.184', $record->fxQuote?->rate);
        self::assertSame('fx-lookup', $record->fxQuote?->reference);
        self::assertSame('https://gateway.example/pay/10', $record->customerAction->url);
    }

    public function testLookupByPublicTokenRejectsMalformedInputBeforeQuery(): void {
        $db = new GatewayLedgerTestDb();
        $ledger = new DbGatewayLedger($db, 'rms_');

        self::assertNull($ledger->byPublicToken("bad' OR 1=1"));
        self::assertSame([], $db->queries);
    }

    public function testTablePrefixMustBeSafe(): void {
        $this->expectException(\InvalidArgumentException::class);
        new DbGatewayLedger(new GatewayLedgerTestDb(), 'rms_` WHERE 1=1 --');
    }

    /** @return array<string, mixed> */
    private function row(string $status): array {
        return [
            'transaction_id' => 10,
            'gateway_id' => 7,
            'source_type' => 'order',
            'source_id' => 101,
            'provider_code' => 'stub',
            'method_code' => 'card',
            'source_currency' => 'BRL',
            'source_fraction_digits' => 2,
            'source_amount' => '25.00',
            'charge_currency' => 'BRL',
            'charge_fraction_digits' => 2,
            'charge_amount' => '25.00',
            'fx_provider_code' => '',
            'fx_quote_reference' => null,
            'fx_rate' => null,
            'fx_quoted_at' => null,
            'fx_expires_at' => null,
            'provider_reference' => 'ref-10',
            'provider_payment_id' => 'pay-10',
            'public_token' => str_repeat('c', 48),
            'idempotency_key' => 'order-101-attempt-01',
            'status' => $status,
            'customer_action_json' => json_encode([
                'kind' => GatewayCustomerAction::REDIRECT,
                'url' => 'https://gateway.example/pay/10',
                'copy_paste' => '',
                'expires_at' => null,
            ], JSON_THROW_ON_ERROR),
        ];
    }
}

final class GatewayLedgerTestDb {
    /** @var list<string> */
    public array $queries = [];
    /** @var list<array<string, mixed>> */
    public array $rows = [];
    public int $lastId = 0;
    public int $affected = 0;

    public function query(string $sql): \stdClass|bool {
        $this->queries[] = $sql;

        if (str_starts_with($sql, 'SELECT')) {
            $row = array_shift($this->rows) ?? [];
            $query = new \stdClass();
            $query->row = $row;
            $query->rows = $row === [] ? [] : [$row];
            $query->num_rows = $row === [] ? 0 : 1;
            return $query;
        }

        return true;
    }

    public function escape(string $value): string {
        return str_replace(['\\', "'", "\0", "\n", "\r"], ['\\\\', "\\'", '\\0', '\\n', '\\r'], $value);
    }

    public function getLastId(): int {
        $id = $this->lastId;
        $this->lastId = 0;
        return $id;
    }

    public function countAffected(): int {
        return $this->affected;
    }
}
