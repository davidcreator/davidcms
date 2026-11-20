<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * Outcome of one reconciliation run. Counters only — no provider payload,
 * no credential, no customer data ever reaches the report.
 */
final class GatewayReconciliationReport {
    public const APPLIED = 'applied';
    public const UNCHANGED = 'unchanged';
    public const SKIPPED = 'skipped';
    public const RETRY = 'retry';
    public const FAILED = 'failed';

    /**
     * @param list<array{transaction_id: int, provider_code: string, status_before: string, status_after: string, outcome: string, reason: string}> $details
     */
    public function __construct(
        public readonly int $scanned = 0,
        public readonly int $applied = 0,
        public readonly int $unchanged = 0,
        public readonly int $skipped = 0,
        public readonly int $retry = 0,
        public readonly int $failed = 0,
        public readonly array $details = [],
    ) {}

    public static function forOne(
        int $transactionId,
        string $providerCode,
        string $statusBefore,
        string $statusAfter,
        string $outcome,
        string $reason
    ): self {
        $detail = [[
            'transaction_id' => $transactionId,
            'provider_code' => $providerCode,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'outcome' => $outcome,
            'reason' => self::safeReason($reason),
        ]];

        return new self(
            1,
            $outcome === self::APPLIED ? 1 : 0,
            $outcome === self::UNCHANGED ? 1 : 0,
            $outcome === self::SKIPPED ? 1 : 0,
            $outcome === self::RETRY ? 1 : 0,
            $outcome === self::FAILED ? 1 : 0,
            $detail
        );
    }

    public static function empty(): self {
        return new self();
    }

    public function merge(self $other): self {
        return new self(
            $this->scanned + $other->scanned,
            $this->applied + $other->applied,
            $this->unchanged + $other->unchanged,
            $this->skipped + $other->skipped,
            $this->retry + $other->retry,
            $this->failed + $other->failed,
            array_merge($this->details, $other->details)
        );
    }

    public function hasFailures(): bool {
        return $this->failed > 0;
    }

    /** @return array{scanned: int, applied: int, unchanged: int, skipped: int, retry: int, failed: int, details: list<array<string, mixed>>} */
    public function toArray(): array {
        return [
            'scanned' => $this->scanned,
            'applied' => $this->applied,
            'unchanged' => $this->unchanged,
            'skipped' => $this->skipped,
            'retry' => $this->retry,
            'failed' => $this->failed,
            'details' => $this->details,
        ];
    }

    /**
     * Reason codes travel to logs and to the token-protected cron response, so
     * they are reduced to a short keyword. Exception messages are truncated and
     * stripped of anything that looks like a URL query, token or e-mail.
     */
    public static function safeReason(string $reason): string {
        $reason = preg_replace('/\s+/', ' ', trim($reason)) ?? '';

        if ($reason === '') {
            return '';
        }

        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $reason) === 1) {
            return $reason;
        }

        $reason = (string)preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[email]', $reason);
        $reason = (string)preg_replace('/\b[A-Za-z0-9_\-]{24,}\b/', '[redacted]', $reason);

        return mb_substr($reason, 0, 120);
    }
}
