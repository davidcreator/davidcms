<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

use Closure;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Active reconciliation for gateway attempts whose notification never arrived.
 *
 * Safety rules, in this order:
 *  - only read-only adapter calls (the interface forbids creating/capturing);
 *  - only attempts in an open state, older than $minAgeMinutes and no older
 *    than $lookbackDays, so a lost webhook is rechecked but a settled payment
 *    is never polled forever;
 *  - the provider answer is applied through GatewayEventApplier, i.e. the same
 *    amount/ID/monotonic-transition checks as the webhook path;
 *  - one failing attempt never aborts the run.
 *
 * Nothing here is provider-specific and nothing here creates money movement.
 */
final class GatewayReconciliationService {
    /** Hard ceilings: a single cron run can never become an unbounded poll. */
    private const MAX_LIMIT = 200;
    private const MAX_MIN_AGE_MINUTES = 10080; // 7 days
    private const MAX_LOOKBACK_DAYS = 90;

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $clock;

    public function __construct(
        private readonly GatewayRegistry $registry,
        private readonly GatewayReconcilableLedger $ledger,
        private readonly GatewayEventApplier $applier,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function reconcileAccount(
        GatewayAccount $account,
        GatewayCredentials $credentials,
        int $limit = 25,
        int $minAgeMinutes = 15,
        int $lookbackDays = 7
    ): GatewayReconciliationReport {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('gateway reconciliation: limit is out of range');
        }

        if ($minAgeMinutes < 1 || $minAgeMinutes > self::MAX_MIN_AGE_MINUTES) {
            throw new \InvalidArgumentException('gateway reconciliation: minimum age is out of range');
        }

        if ($lookbackDays < 1 || $lookbackDays > self::MAX_LOOKBACK_DAYS) {
            throw new \InvalidArgumentException('gateway reconciliation: lookback window is out of range');
        }

        if ($account->gatewayId <= 0) {
            throw new \InvalidArgumentException('gateway reconciliation: invalid gateway account');
        }

        try {
            $adapter = $this->registry->get($account->providerCode);
        } catch (\OutOfBoundsException) {
            return GatewayReconciliationReport::empty();
        }

        if (!$adapter instanceof ReconcilableGatewayAdapter) {
            // Nothing to do, but the operator should know the provider cannot
            // be reconciled rather than assume the queue is empty.
            return GatewayReconciliationReport::forOne(
                0,
                $account->providerCode,
                '',
                '',
                GatewayReconciliationReport::SKIPPED,
                'provider_not_reconcilable'
            );
        }

        $now = ($this->clock)();
        $modifiedBefore = $now->sub(new \DateInterval('PT' . $minAgeMinutes . 'M'));
        $createdAfter = $now->sub(new \DateInterval('P' . $lookbackDays . 'D'));

        $records = $this->ledger->pendingAttempts(
            $account->gatewayId,
            self::utc($modifiedBefore),
            self::utc($createdAfter),
            $limit
        );

        $report = GatewayReconciliationReport::empty();

        foreach ($records as $record) {
            $report = $report->merge($this->reconcileRecord($adapter, $account, $credentials, $record));
        }

        return $report;
    }

    /** Reconcile every account the store returns, one provider at a time. */
    public function reconcileAccountList(
        GatewayAccountStore $store,
        int $limit = 25,
        int $minAgeMinutes = 15,
        int $lookbackDays = 7
    ): GatewayReconciliationReport {
        $report = GatewayReconciliationReport::empty();

        foreach ($store->all() as $account) {
            try {
                $credentials = $store->credentials($account->gatewayId);
            } catch (\Throwable $e) {
                $report = $report->merge(GatewayReconciliationReport::forOne(
                    0,
                    $account->providerCode,
                    '',
                    '',
                    GatewayReconciliationReport::FAILED,
                    'credentials_unavailable: ' . $e->getMessage()
                ));
                continue;
            }

            $report = $report->merge(
                $this->reconcileAccount($account, $credentials, $limit, $minAgeMinutes, $lookbackDays)
            );
        }

        return $report;
    }

    private function reconcileRecord(
        ReconcilableGatewayAdapter $adapter,
        GatewayAccount $account,
        GatewayCredentials $credentials,
        GatewayPaymentRecord $record
    ): GatewayReconciliationReport {
        try {
            $event = $adapter->reconcile($record, $account, $credentials);
        } catch (\Throwable $e) {
            return GatewayReconciliationReport::forOne(
                $record->transactionId,
                $record->providerCode,
                $record->status,
                $record->status,
                GatewayReconciliationReport::FAILED,
                $e->getMessage()
            );
        }

        if ($event->kind === GatewayWebhookResult::RETRY) {
            return GatewayReconciliationReport::forOne(
                $record->transactionId,
                $record->providerCode,
                $record->status,
                $record->status,
                GatewayReconciliationReport::RETRY,
                $event->reasonCode
            );
        }

        if ($event->kind !== GatewayWebhookResult::VERIFIED) {
            return GatewayReconciliationReport::forOne(
                $record->transactionId,
                $record->providerCode,
                $record->status,
                $record->status,
                GatewayReconciliationReport::SKIPPED,
                $event->reasonCode
            );
        }

        // A provider answer must talk about the attempt we asked for.
        if (!hash_equals($record->providerReference, $event->providerReference)) {
            return GatewayReconciliationReport::forOne(
                $record->transactionId,
                $record->providerCode,
                $record->status,
                $record->status,
                GatewayReconciliationReport::SKIPPED,
                'reference_mismatch'
            );
        }

        try {
            $applied = $this->applier->apply($account->gatewayId, $account->providerCode, $event);
        } catch (\Throwable $e) {
            return GatewayReconciliationReport::forOne(
                $record->transactionId,
                $record->providerCode,
                $record->status,
                $record->status,
                GatewayReconciliationReport::FAILED,
                $e->getMessage()
            );
        }

        if ($applied->kind !== GatewayWebhookResult::VERIFIED) {
            return GatewayReconciliationReport::forOne(
                $record->transactionId,
                $record->providerCode,
                $record->status,
                $record->status,
                GatewayReconciliationReport::SKIPPED,
                $applied->reasonCode
            );
        }

        $changed = $record->status !== $event->status;

        return GatewayReconciliationReport::forOne(
            $record->transactionId,
            $record->providerCode,
            $record->status,
            $event->status,
            $changed ? GatewayReconciliationReport::APPLIED : GatewayReconciliationReport::UNCHANGED,
            $changed ? 'provider_state_applied' : 'provider_state_unchanged'
        );
    }

    private static function utc(DateTimeImmutable $value): string {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
