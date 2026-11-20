<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\DbGatewayLedger;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentResult;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayReconcilableLedger;
use Reamur\System\Library\Payment\Money;

/**
 * P0-b: o ledger precisa (1) entregar a fila de tentativas abertas para a
 * reconciliação e (2) guardar o id consultável do provedor quando a coluna
 * aditiva existe — sem quebrar instalações que ainda não aplicaram o SQL.
 */
final class DbGatewayLedgerReconcileTest extends TestCase {
    private const IDEMPOTENCY = '51e3f12c-6b0c-5ac5-9b83-d1d43c90d0c1';

    private function request(): GatewayPaymentRequest {
        return new GatewayPaymentRequest(
            'jow_charge',
            42,
            new Money('125.50', 'BRL', 2),
            'BRL',
            'checkout_pro',
            self::IDEMPOTENCY,
            'Cobrança interna JOW 42',
            'https://loja.example/catalog/index.php?route=common/home',
            'https://loja.example/catalog/index.php?route=checkout/gateway.notify&gateway_id=7',
            ''
        );
    }

    private function providerResult(string $anchor): GatewayPaymentResult {
        return new GatewayPaymentResult(
            'ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9',
            GatewayCustomerAction::redirect('https://gateway.example/pay/41'),
            new Money('125.50', 'BRL', 2),
            GatewayPaymentStatus::PENDING,
            null,
            '',
            $anchor
        );
    }

    private function row(array $overrides = []): array {
        return $overrides + [
            'transaction_id' => 41,
            'gateway_id' => 7,
            'source_type' => 'jow_charge',
            'source_id' => 42,
            'provider_code' => 'mp',
            'method_code' => 'checkout_pro',
            'source_currency' => 'BRL',
            'source_fraction_digits' => 2,
            'source_amount' => '125.50',
            'charge_currency' => 'BRL',
            'charge_fraction_digits' => 2,
            'charge_amount' => '125.50',
            'fx_provider_code' => '',
            'fx_quote_reference' => null,
            'fx_rate' => null,
            'fx_quoted_at' => null,
            'fx_expires_at' => null,
            'provider_reference' => 'ORDTST01KS5AJ6HTK2HRQ3XJ3C2JCKP9',
            'provider_payment_id' => null,
            'provider_checkout_id' => null,
            'public_token' => str_repeat('d', 48),
            'idempotency_key' => self::IDEMPOTENCY,
            'status' => 'pending',
            'customer_action_json' => json_encode([
                'kind' => GatewayCustomerAction::REDIRECT,
                'url' => 'https://gateway.example/pay/41',
                'copy_paste' => '',
                'expires_at' => null,
            ], JSON_THROW_ON_ERROR),
        ];
    }

    public function testTheLedgerExposesTheReconciliationContract(): void {
        $this->assertInstanceOf(
            GatewayReconcilableLedger::class,
            new DbGatewayLedger(new ReconcilableLedgerTestDb(), 'rms_')
        );
    }

    public function testPendingQueueIsBoundedFilteredAndOrdered(): void {
        $db = new ReconcilableLedgerTestDb();
        $db->rows = [$this->row(), $this->row(['transaction_id' => 39, 'status' => 'requires_action'])];

        $records = (new DbGatewayLedger($db, 'rms_'))->pendingAttempts(
            7,
            '2026-11-20 11:45:00',
            '2026-11-13 12:00:00',
            25
        );

        $sql = $db->queries[count($db->queries) - 1];

        $this->assertCount(2, $records);
        $this->assertSame(41, $records[0]->transactionId);
        $this->assertSame(GatewayPaymentStatus::PENDING, $records[0]->status);
        $this->assertSame(GatewayPaymentStatus::REQUIRES_ACTION, $records[1]->status);
        $this->assertStringContainsString("`gateway_id` = '7'", $sql);
        $this->assertStringContainsString("`status` IN ('pending', 'requires_action', 'authorized')", $sql);
        $this->assertStringContainsString("`date_modified` <= '2026-11-20 11:45:00'", $sql);
        $this->assertStringContainsString("`date_added` >= '2026-11-13 12:00:00'", $sql);
        $this->assertStringContainsString('ORDER BY `date_modified` ASC, `transaction_id` ASC', $sql);
        $this->assertStringContainsString('LIMIT 25', $sql);
    }

    public function testEmptyQueueIsAnEmptyList(): void {
        $db = new ReconcilableLedgerTestDb();

        $this->assertSame([], (new DbGatewayLedger($db, 'rms_'))->pendingAttempts(7, '2026-11-20 11:45:00', '2026-11-13 12:00:00', 25));
    }

    public function testInvalidQueueQueriesAreRefusedBeforeTouchingTheDatabase(): void {
        $db = new ReconcilableLedgerTestDb();
        $ledger = new DbGatewayLedger($db, 'rms_');

        $cases = [
            [0, '2026-11-20 11:45:00', '2026-11-13 12:00:00', 25],
            [7, '2026-11-20 11:45:00', '2026-11-13 12:00:00', 0],
            [7, '2026-11-20 11:45:00', '2026-11-13 12:00:00', 201],
            [7, '2026-11-20', '2026-11-13 12:00:00', 25],
            [7, '2026-11-20 11:45:00', '2026-02-31 12:00:00', 25],
            [7, "2026-11-20 11:45:00' OR 1=1 --", '2026-11-13 12:00:00', 25],
        ];

        foreach ($cases as $case) {
            try {
                $ledger->pendingAttempts($case[0], $case[1], $case[2], $case[3]);
                $this->fail('invalid queue query must be refused: ' . json_encode($case));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame([], $db->queries);
    }

    public function testAnchorIsPersistedOnlyWhenTheAdditiveColumnExists(): void {
        $db = new ReconcilableLedgerTestDb();
        $db->hasCheckoutColumn = true;
        $db->lastId = 41;
        $db->affected = 1;

        $record = (new DbGatewayLedger($db, 'rms_'))->createAttempt(7, 'mp', $this->request(), $this->providerResult('ORD-ANCHOR-77'));
        $insert = $this->insertStatement($db);

        $this->assertStringContainsString('SHOW COLUMNS', implode("\n", $db->queries));
        $this->assertStringContainsString("`provider_checkout_id` = 'ORD-ANCHOR-77'", $insert);
        $this->assertSame('ORD-ANCHOR-77', $record->providerCheckoutId);
    }

    public function testWithoutTheColumnTheAttemptIsStillRecordedAndNoAnchorIsClaimed(): void {
        $db = new ReconcilableLedgerTestDb();
        $db->hasCheckoutColumn = false;
        $db->lastId = 41;
        $db->affected = 1;

        $record = (new DbGatewayLedger($db, 'rms_'))->createAttempt(7, 'mp', $this->request(), $this->providerResult('ORD-ANCHOR-77'));
        $insert = $this->insertStatement($db);

        $this->assertStringNotContainsString('provider_checkout_id', $insert);
        $this->assertSame('', $record->providerCheckoutId, 'sem coluna o ledger não pode prometer uma âncora que não guardou');
        $this->assertSame(41, $record->transactionId);
    }

    public function testNoAnchorMeansNoSchemaProbeAtAll(): void {
        $db = new ReconcilableLedgerTestDb();
        $db->lastId = 41;
        $db->affected = 1;

        (new DbGatewayLedger($db, 'rms_'))->createAttempt(7, 'mp', $this->request(), $this->providerResult(''));

        $this->assertStringNotContainsString('SHOW COLUMNS', implode("\n", $db->queries));
        $this->assertStringNotContainsString('provider_checkout_id', $this->insertStatement($db));
    }

    public function testStoredAnchorIsReadBackOnTheRecord(): void {
        $db = new ReconcilableLedgerTestDb();
        $db->rows = [$this->row(['provider_checkout_id' => 'ORD-ANCHOR-77'])];

        $records = (new DbGatewayLedger($db, 'rms_'))->pendingAttempts(7, '2026-11-20 11:45:00', '2026-11-13 12:00:00', 10);

        $this->assertSame('ORD-ANCHOR-77', $records[0]->providerCheckoutId);
    }

    public function testMissingAnchorColumnIsNotAnErrorForOlderRows(): void {
        $db = new ReconcilableLedgerTestDb();
        $row = $this->row();
        unset($row['provider_checkout_id']);
        $db->rows = [$row];

        $records = (new DbGatewayLedger($db, 'rms_'))->pendingAttempts(7, '2026-11-20 11:45:00', '2026-11-13 12:00:00', 10);

        $this->assertSame('', $records[0]->providerCheckoutId);
    }

    private function insertStatement(ReconcilableLedgerTestDb $db): string {
        foreach (array_reverse($db->queries) as $sql) {
            if (str_starts_with($sql, 'INSERT IGNORE')) {
                return $sql;
            }
        }

        $this->fail('no INSERT was issued');
    }
}

/** Banco de mentira com controle de schema (coluna aditiva presente ou não). */
final class ReconcilableLedgerTestDb {
    /** @var list<string> */
    public array $queries = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public bool $hasCheckoutColumn = false;
    public int $lastId = 0;
    public int $affected = 0;

    public function query(string $sql): \stdClass|bool {
        $this->queries[] = $sql;

        if (str_starts_with($sql, 'SHOW COLUMNS')) {
            $query = new \stdClass();
            $query->row = $this->hasCheckoutColumn ? ['Field' => 'provider_checkout_id'] : [];
            $query->rows = $query->row === [] ? [] : [$query->row];
            $query->num_rows = $this->hasCheckoutColumn ? 1 : 0;

            return $query;
        }

        if (str_starts_with($sql, 'SELECT')) {
            $query = new \stdClass();
            $query->row = $this->rows[0] ?? [];
            $query->rows = $this->rows;
            $query->num_rows = count($this->rows);

            return $query;
        }

        return true;
    }

    public function escape(string $value): string {
        return str_replace(["\\", "'", "\0", "\n", "\r"], ["\\\\", "\\'", '\\0', '\\n', '\\r'], $value);
    }

    public function getLastId(): int {
        return $this->lastId;
    }

    public function countAffected(): int {
        return $this->affected;
    }
}
