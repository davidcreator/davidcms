<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/** MySQL/MariaDB implementation for the additive JOW multi-gateway ledger. */
final class DbGatewayLedger implements GatewayReconcilableLedger, GatewayAddressableLedger {
    /** Upper bound for one reconciliation page; mirrors the service ceiling. */
    private const MAX_PENDING_LIMIT = 200;

    /** null = not probed yet; see supportsProviderCheckoutId(). */
    private ?bool $checkoutIdColumn = null;

    public function __construct(private readonly object $db, private readonly string $prefix) {
        if ($prefix !== '' && preg_match('/^[A-Za-z0-9_]+$/D', $prefix) !== 1) {
            throw new \InvalidArgumentException('gateway ledger: invalid table prefix');
        }
    }

    /** @return list<GatewayPaymentRecord> */
    public function pendingAttempts(
        int $gatewayId,
        string $modifiedBeforeUtc,
        string $createdAfterUtc,
        int $limit
    ): array {
        if ($gatewayId <= 0 || $limit < 1 || $limit > self::MAX_PENDING_LIMIT) {
            throw new \InvalidArgumentException('gateway ledger: invalid pending attempt page');
        }

        self::assertUtc($modifiedBeforeUtc);
        self::assertUtc($createdAfterUtc);

        $query = $this->query(
            "SELECT * FROM `" . $this->table('payment_transaction') . "`"
            . " WHERE `gateway_id` = '" . $gatewayId . "'"
            . " AND `status` IN ('pending', 'requires_action', 'authorized')"
            . " AND `date_modified` <= '" . $this->escape($modifiedBeforeUtc) . "'"
            . " AND `date_added` >= '" . $this->escape($createdAfterUtc) . "'"
            . " ORDER BY `date_modified` ASC, `transaction_id` ASC LIMIT " . $limit
        );
        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
        $records = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                // A malformed ledger row is an integrity problem: it must not be
                // skipped silently, otherwise a payment would look "settled".
                $records[] = $this->recordFromRow($row);
            }
        }

        return $records;
    }

    public function latestForSource(string $sourceType, int $sourceId): ?GatewayPaymentRecord {
        self::assertSource($sourceType, $sourceId);

        return $this->findOne(
            "SELECT * FROM `" . $this->table('payment_transaction') . "`"
            . " WHERE `source_type` = '" . $this->escape($sourceType) . "' AND `source_id` = '" . $sourceId . "'"
            . " ORDER BY `transaction_id` DESC LIMIT 1"
        );
    }

    public function byPublicToken(string $token): ?GatewayPaymentRecord {
        if (preg_match('/^[a-f0-9]{48}$/D', $token) !== 1) {
            return null;
        }

        return $this->findOne(
            "SELECT * FROM `" . $this->table('payment_transaction') . "`"
            . " WHERE `public_token` = '" . $this->escape($token) . "' LIMIT 1"
        );
    }

    /**
     * Tentativa pelo `transaction_id` (ADR 0119).
     *
     * Necessário porque o contrato antigo (`Ledger::record()`) endereça a
     * cobrança pelo `id` que ele mesmo devolveu, e no ledger novo esse id é o
     * `transaction_id` — não a referência do provedor.
     */
    public function byTransactionId(int $transactionId): ?GatewayPaymentRecord {
        if ($transactionId <= 0) {
            return null;
        }

        return $this->findOne(
            "SELECT * FROM `" . $this->table('payment_transaction') . "`"
                . " WHERE `transaction_id` = '" . $transactionId . "' LIMIT 1"
        );
    }

    public function byProviderReference(int $gatewayId, string $providerReference): ?GatewayPaymentRecord {
        if ($gatewayId <= 0 || trim($providerReference) === '' || strlen($providerReference) > 128
            || preg_match('/[\r\n\0]/', $providerReference) === 1) {
            return null;
        }

        return $this->findOne(
            "SELECT * FROM `" . $this->table('payment_transaction') . "`"
            . " WHERE `gateway_id` = '" . $gatewayId . "' AND `provider_reference` = '" . $this->escape($providerReference) . "' LIMIT 1"
        );
    }

    public function byIdempotencyKey(int $gatewayId, GatewayPaymentRequest $request): ?GatewayPaymentRecord {
        if ($gatewayId <= 0) {
            throw new \InvalidArgumentException('gateway ledger: invalid gateway ID');
        }

        return $this->findOne(
            "SELECT * FROM `" . $this->table('payment_transaction') . "`"
            . " WHERE `source_type` = '" . $this->escape($request->sourceType) . "'"
            . " AND `source_id` = '" . $request->sourceId . "'"
            . " AND `gateway_id` = '" . $gatewayId . "'"
            . " AND `idempotency_key` = '" . $this->escape($request->idempotencyKey) . "' LIMIT 1"
        );
    }

    public function createAttempt(int $gatewayId, string $providerCode, GatewayPaymentRequest $request, GatewayPaymentResult $result): GatewayPaymentRecord {
        if ($gatewayId <= 0 || preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $providerCode) !== 1) {
            throw new \InvalidArgumentException('gateway ledger: invalid gateway identity');
        }

        $this->assertResultMatchesRequest($providerCode, $request, $result);

        $existing = $this->byIdempotencyKey($gatewayId, $request);

        if ($existing !== null) {
            $this->assertSameIdempotentResult($existing, $gatewayId, $providerCode, $request, $result);
            return $existing;
        }

        $token = bin2hex(random_bytes(24));
        $quote = $result->quote;
        $actionJson = json_encode($result->customerAction->toSafeArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $checkoutUrl = $result->customerAction->url;
        $providerPaymentId = $result->providerPaymentId;
        // Additive column: present only after tools/upgrade_jow_payments_reconcile.sql.
        // Without it the attempt is still recorded, it just cannot be polled back.
        $checkoutId = $result->providerCheckoutId;
        $checkoutSql = ($checkoutId !== '' && $this->supportsProviderCheckoutId())
            ? " `provider_checkout_id` = '" . $this->escape($checkoutId) . "',"
            : '';
        $quoteRef = $quote?->reference;
        $quoteProvider = $quote?->providerCode ?? '';
        $quoteRate = $quote?->rate;
        $quotedAt = $quote ? $this->utc($quote->quotedAt) : null;
        $expiresAt = $quote ? $this->utc($quote->expiresAt) : null;

        $this->query(
            "INSERT IGNORE INTO `" . $this->table('payment_transaction') . "` SET"
            . " `source_type` = '" . $this->escape($request->sourceType) . "',"
            . " `source_id` = '" . $request->sourceId . "',"
            . " `gateway_id` = '" . $gatewayId . "',"
            . " `provider_code` = '" . $this->escape($providerCode) . "',"
            . " `method_code` = '" . $this->escape($request->paymentMethod) . "',"
            . " `source_currency` = '" . $this->escape($request->sourceAmount->currency) . "',"
            . " `source_fraction_digits` = '" . $request->sourceAmount->fractionDigits . "',"
            . " `source_amount` = '" . $request->sourceAmount->amount() . "',"
            . " `charge_currency` = '" . $this->escape($result->chargeAmount->currency) . "',"
            . " `charge_fraction_digits` = '" . $result->chargeAmount->fractionDigits . "',"
            . " `charge_amount` = '" . $result->chargeAmount->amount() . "',"
            . " `fx_provider_code` = '" . $this->escape($quoteProvider) . "',"
            . " `fx_quote_reference` = " . $this->nullableEscaped($quoteRef) . ","
            . " `fx_rate` = " . $this->nullableEscaped($quoteRate) . ","
            . " `fx_quoted_at` = " . $this->nullableEscaped($quotedAt) . ","
            . " `fx_expires_at` = " . $this->nullableEscaped($expiresAt) . ","
            . " `provider_reference` = '" . $this->escape($result->providerReference) . "',"
            . " `provider_payment_id` = " . $this->nullableEscaped($providerPaymentId) . ","
            . $checkoutSql
            . " `checkout_url` = " . $this->nullableEscaped($checkoutUrl) . ","
            . " `customer_action_json` = '" . $this->escape($actionJson) . "',"
            . " `public_token` = '" . $token . "',"
            . " `idempotency_key` = '" . $this->escape($request->idempotencyKey) . "',"
            . " `status` = '" . $this->escape($result->status) . "',"
            . " `date_added` = UTC_TIMESTAMP(), `date_modified` = UTC_TIMESTAMP()"
        );

        $affectedRows = method_exists($this->db, 'countAffected') ? (int)$this->db->countAffected() : null;
        $transactionId = method_exists($this->db, 'getLastId') ? (int)$this->db->getLastId() : 0;

        if ($transactionId > 0 && ($affectedRows === null || $affectedRows > 0)) {
            return new GatewayPaymentRecord(
                $transactionId,
                $gatewayId,
                $request->sourceType,
                $request->sourceId,
                $providerCode,
                $request->paymentMethod,
                $request->sourceAmount,
                $result->chargeAmount,
                $token,
                $request->idempotencyKey,
                $result->status,
                $result->providerReference,
                $result->providerPaymentId,
                $result->customerAction,
                $quote,
                // Only report the anchor when it was actually persisted.
                $checkoutSql !== '' ? $result->providerCheckoutId : ''
            );
        }

        // INSERT IGNORE can mean a concurrent request won the idempotency key.
        $existing = $this->byIdempotencyKey($gatewayId, $request);

        if ($existing !== null) {
            $this->assertSameIdempotentResult($existing, $gatewayId, $providerCode, $request, $result);
            return $existing;
        }

        throw new \RuntimeException('gateway ledger: payment attempt could not be persisted');
    }

    public function recordWebhookOnce(int $gatewayId, GatewayWebhookResult $event): bool {
        if ($gatewayId <= 0 || $event->kind !== GatewayWebhookResult::VERIFIED) {
            throw new \InvalidArgumentException('gateway ledger: only verified provider events can be recorded');
        }

        $this->query(
            "INSERT IGNORE INTO `" . $this->table('payment_gateway_event') . "` SET"
            . " `gateway_id` = '" . $gatewayId . "',"
            . " `provider_event_id` = '" . $this->escape($event->providerEventId) . "',"
            . " `event_type` = '" . $this->escape($event->status) . "',"
            . " `event_sha256` = '" . $this->escape($event->eventSha256) . "',"
            . " `result_code` = 'received', `status` = 'received', `received_at` = UTC_TIMESTAMP()"
        );

        if (!method_exists($this->db, 'countAffected')) {
            return method_exists($this->db, 'getLastId') && (int)$this->db->getLastId() > 0;
        }

        if ((int)$this->db->countAffected() > 0) {
            return true;
        }

        $query = $this->query(
            "SELECT `event_sha256`, `event_type` FROM `" . $this->table('payment_gateway_event') . "`"
            . " WHERE `gateway_id` = '" . $gatewayId . "'"
            . " AND `provider_event_id` = '" . $this->escape($event->providerEventId) . "' LIMIT 1"
        );
        $row = ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];

        if ($row === []) {
            throw new \RuntimeException('gateway ledger: event insert was not confirmed by the database');
        }

        if (!hash_equals((string)($row['event_sha256'] ?? ''), $event->eventSha256)
            || (string)($row['event_type'] ?? '') !== $event->status) {
            throw new \DomainException('gateway ledger: provider reused an event ID with different verified content');
        }

        return false;
    }

    public function applyVerifiedEvent(int $gatewayId, GatewayWebhookResult $event): void {
        if ($gatewayId <= 0 || $event->kind !== GatewayWebhookResult::VERIFIED) {
            throw new \InvalidArgumentException('gateway ledger: refusing an unverified provider event');
        }

        $record = $this->byProviderReference($gatewayId, $event->providerReference);

        if ($record === null) {
            throw new \OutOfBoundsException('gateway ledger: verified event references an unknown payment');
        }

        if ($event->chargeAmount !== null && !$record->chargeAmount->equals($event->chargeAmount)) {
            throw new \RuntimeException('gateway ledger: provider event amount does not match the recorded attempt');
        }

        if ($event->providerPaymentId !== '' && $record->providerPaymentId !== ''
            && $event->providerPaymentId !== $record->providerPaymentId) {
            throw new \RuntimeException('gateway ledger: provider payment ID does not match the recorded attempt');
        }

        if (!GatewayPaymentStatus::canTransition($record->status, $event->status)) {
            throw new \DomainException('gateway ledger: unsafe payment status transition');
        }

        $paymentId = $event->providerPaymentId !== '' ? $event->providerPaymentId : $record->providerPaymentId;
        $this->query(
            "UPDATE `" . $this->table('payment_transaction') . "` SET"
            . " `status` = '" . $this->escape($event->status) . "',"
            . " `provider_payment_id` = " . $this->nullableEscaped($paymentId) . ","
            . " `date_modified` = UTC_TIMESTAMP()"
            . " WHERE `transaction_id` = '" . $record->transactionId . "'"
            . " AND `gateway_id` = '" . $gatewayId . "'"
            . " AND `status` = '" . $this->escape($record->status) . "'"
        );

        // Resolve an optimistic-update race: accept an identical status, never
        // overwrite a different concurrent state with stale notification data.
        if (method_exists($this->db, 'countAffected') && (int)$this->db->countAffected() === 0) {
            $current = $this->byProviderReference($gatewayId, $event->providerReference);

            if ($current === null || $current->status !== $event->status
                || ($event->providerPaymentId !== '' && $current->providerPaymentId !== $event->providerPaymentId)) {
                throw new \RuntimeException('gateway ledger: payment status changed concurrently');
            }
        }

        $this->query(
            "UPDATE `" . $this->table('payment_gateway_event') . "` SET"
            . " `result_code` = 'applied', `status` = 'processed', `processed_at` = UTC_TIMESTAMP()"
            . " WHERE `gateway_id` = '" . $gatewayId . "'"
            . " AND `provider_event_id` = '" . $this->escape($event->providerEventId) . "'"
        );
    }

    private function assertResultMatchesRequest(string $providerCode, GatewayPaymentRequest $request, GatewayPaymentResult $result): void {
        if ($result->quote !== null && $result->quote->providerCode !== $providerCode) {
            throw new \InvalidArgumentException('gateway ledger: FX quote belongs to a different provider');
        }

        if ($request->sourceAmount->currency === $request->chargeCurrency) {
            if ($result->quote !== null || !$result->chargeAmount->equals($request->sourceAmount)) {
                throw new \InvalidArgumentException('gateway ledger: same-currency charge must exactly equal the source amount');
            }

            return;
        }

        $quote = $result->quote;

        if ($quote === null || !$quote->sourceAmount->equals($request->sourceAmount)
            || $quote->chargeAmount->currency !== $request->chargeCurrency
            || !$quote->chargeAmount->equals($result->chargeAmount)) {
            throw new \InvalidArgumentException('gateway ledger: converted charge requires a matching provider FX quote');
        }
    }

    private function assertSameIdempotentResult(
        GatewayPaymentRecord $existing,
        int $gatewayId,
        string $providerCode,
        GatewayPaymentRequest $request,
        GatewayPaymentResult $result
    ): void {
        if (!$existing->matchesRequest($gatewayId, $providerCode, $request)
            || $existing->providerReference !== $result->providerReference
            || !$existing->chargeAmount->equals($result->chargeAmount)
            || !$this->sameQuote($existing->fxQuote, $result->quote)) {
            throw new \LogicException('gateway ledger: idempotency key conflicts with a different provider result');
        }
    }

    private function sameQuote(?FxQuote $left, ?FxQuote $right): bool {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return $left->providerCode === $right->providerCode
            && $left->reference === $right->reference
            && $left->sourceAmount->equals($right->sourceAmount)
            && $left->chargeAmount->equals($right->chargeAmount)
            && $left->rate === $right->rate
            && $left->quotedAt == $right->quotedAt
            && $left->expiresAt == $right->expiresAt;
    }

    private function findOne(string $sql): ?GatewayPaymentRecord {
        $query = $this->query($sql);
        $row = ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];

        return $row === [] ? null : $this->recordFromRow($row);
    }

    /** @param array<string, mixed> $row */
    private function recordFromRow(array $row): GatewayPaymentRecord {
        $source = new Money(
            (string)($row['source_amount'] ?? ''),
            (string)($row['source_currency'] ?? ''),
            (int)($row['source_fraction_digits'] ?? -1)
        );
        $charge = new Money(
            (string)($row['charge_amount'] ?? ''),
            (string)($row['charge_currency'] ?? ''),
            (int)($row['charge_fraction_digits'] ?? -1)
        );

        try {
            $actionData = json_decode((string)($row['customer_action_json'] ?? ''), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new \RuntimeException('gateway ledger: stored customer action is invalid', 0, $e);
        }

        if (!is_array($actionData)) {
            throw new \RuntimeException('gateway ledger: stored customer action is invalid');
        }

        $action = GatewayCustomerAction::fromSafeArray($actionData);
        $quote = null;
        $quoteRef = (string)($row['fx_quote_reference'] ?? '');

        if ($quoteRef !== '') {
            $quotedAt = $this->fromUtc((string)($row['fx_quoted_at'] ?? ''));
            $expiresAt = $this->fromUtc((string)($row['fx_expires_at'] ?? ''));
            $quote = new FxQuote(
                (string)($row['fx_provider_code'] ?? ''),
                $quoteRef,
                $source,
                $charge,
                (string)($row['fx_rate'] ?? ''),
                $quotedAt,
                $expiresAt
            );
        }

        return new GatewayPaymentRecord(
            (int)($row['transaction_id'] ?? 0),
            (int)($row['gateway_id'] ?? 0),
            (string)($row['source_type'] ?? ''),
            (int)($row['source_id'] ?? 0),
            (string)($row['provider_code'] ?? ''),
            (string)($row['method_code'] ?? ''),
            $source,
            $charge,
            (string)($row['public_token'] ?? ''),
            (string)($row['idempotency_key'] ?? ''),
            (string)($row['status'] ?? ''),
            (string)($row['provider_reference'] ?? ''),
            (string)($row['provider_payment_id'] ?? ''),
            $action,
            $quote,
            (string)($row['provider_checkout_id'] ?? '')
        );
    }

    private function fromUtc(string $value): DateTimeImmutable {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        if (!$date instanceof DateTimeImmutable) {
            throw new \RuntimeException('gateway ledger: stored FX timestamp is invalid');
        }

        return $date;
    }

    private function utc(DateTimeImmutable $value): string {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function table(string $name): string {
        return $this->prefix . $name;
    }

    private function nullableEscaped(?string $value): string {
        return $value === null || $value === '' ? 'NULL' : "'" . $this->escape($value) . "'";
    }

    private static function assertSource(string $sourceType, int $sourceId): void {
        if (!in_array($sourceType, ['order', 'jow_charge'], true) || $sourceId <= 0) {
            throw new \InvalidArgumentException('gateway ledger: invalid source reference');
        }
    }

    /**
     * Additive-column detection, cached per ledger instance: older installs keep
     * working untouched, and a deploy that applies the migration is seen by the
     * next request instead of being masked by a process-wide cache.
     */
    private function supportsProviderCheckoutId(): bool {
        if ($this->checkoutIdColumn !== null) {
            return $this->checkoutIdColumn;
        }

        $query = $this->query(
            "SHOW COLUMNS FROM `" . $this->table('payment_transaction') . "` LIKE 'provider_checkout_id'"
        );
        $this->checkoutIdColumn = $query instanceof \stdClass && (int)($query->num_rows ?? 0) > 0;

        return $this->checkoutIdColumn;
    }

    private static function assertUtc(string $value): void {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        if (!$date instanceof \DateTimeImmutable || $date->format('Y-m-d H:i:s') !== $value) {
            throw new \InvalidArgumentException('gateway ledger: timestamps must be UTC Y-m-d H:i:s');
        }
    }

    private function query(string $sql): mixed {
        return $this->db->query($sql);
    }

    private function escape(string $value): string {
        return (string)$this->db->escape($value);
    }
}
