<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/** Neutral transaction ledger contract for order checkout and JOW internal charges. */
interface GatewayLedger {
    public function latestForSource(string $sourceType, int $sourceId): ?GatewayPaymentRecord;

    public function byPublicToken(string $token): ?GatewayPaymentRecord;

    public function byProviderReference(int $gatewayId, string $providerReference): ?GatewayPaymentRecord;

    public function byIdempotencyKey(int $gatewayId, GatewayPaymentRequest $request): ?GatewayPaymentRecord;

    /** Persist a completed provider attempt; implementations must enforce idempotency. */
    public function createAttempt(int $gatewayId, string $providerCode, GatewayPaymentRequest $request, GatewayPaymentResult $result): GatewayPaymentRecord;

    /**
     * Record a verified provider event once. A repeated ID with a different
     * digest must throw; callers should still call applyVerifiedEvent() for an
     * identical duplicate so interrupted processing can be recovered safely.
     */
    public function recordWebhookOnce(int $gatewayId, GatewayWebhookResult $event): bool;

    /** Apply only an already verified event to the matching transaction. */
    public function applyVerifiedEvent(int $gatewayId, GatewayWebhookResult $event): void;
}
