<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/** Result of provider-specific webhook authentication/reconciliation. */
final class GatewayWebhookResult {
    public const IGNORED = 'ignored';
    public const RETRY = 'retry';
    public const VERIFIED = 'verified';

    private function __construct(
        public readonly string $kind,
        public readonly string $reasonCode = '',
        public readonly string $providerEventId = '',
        public readonly string $providerReference = '',
        public readonly string $providerPaymentId = '',
        public readonly string $status = '',
        public readonly string $eventSha256 = '',
        public readonly ?Money $chargeAmount = null,
    ) {}

    public static function ignored(string $reasonCode): self {
        return new self(self::IGNORED, self::safeCode($reasonCode));
    }

    public static function retry(string $reasonCode): self {
        return new self(self::RETRY, self::safeCode($reasonCode));
    }

    /**
     * @param string $eventSha256 SHA-256 of the raw body when available; when
     *                             omitted a digest of normalized event fields is stored.
     */
    public static function verified(
        string $eventId,
        string $providerReference,
        string $providerPaymentId,
        string $status,
        string $eventSha256 = '',
        ?Money $chargeAmount = null
    ): self {
        if (trim($eventId) === '' || strlen($eventId) > 128 || preg_match('/[\r\n\0]/', $eventId) === 1
            || trim($providerReference) === '' || strlen($providerReference) > 128 || preg_match('/[\r\n\0]/', $providerReference) === 1
            || strlen($providerPaymentId) > 128 || preg_match('/[\r\n\0]/', $providerPaymentId) === 1
            || !GatewayPaymentStatus::isKnown($status)
            || ($chargeAmount !== null && !$chargeAmount->isPositive())) {
            throw new \InvalidArgumentException('gateway webhook result: verified event is incomplete');
        }

        if ($eventSha256 === '') {
            $eventSha256 = hash('sha256', implode("\0", [
                $eventId,
                $providerReference,
                $providerPaymentId,
                $status,
                $chargeAmount?->currency ?? '',
                $chargeAmount?->amount() ?? '',
            ]));
        } elseif (preg_match('/^[a-f0-9]{64}$/D', $eventSha256) !== 1) {
            throw new \InvalidArgumentException('gateway webhook result: event digest must be a lowercase SHA-256 hex string');
        }

        return new self(self::VERIFIED, '', trim($eventId), trim($providerReference), trim($providerPaymentId), $status, $eventSha256, $chargeAmount);
    }

    private static function safeCode(string $code): string {
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $code) !== 1) {
            throw new \InvalidArgumentException('gateway webhook result: invalid reason code');
        }

        return $code;
    }
}
