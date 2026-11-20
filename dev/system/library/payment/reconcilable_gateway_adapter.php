<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * Optional adapter capability: query the provider for the authoritative state
 * of an attempt that was already created, without any webhook at all.
 *
 * Reconciliation is a READ-ONLY safety net for lost deliveries: it never
 * creates a charge, never captures and never refunds. A verified result is
 * applied through the same event applier used by the webhook path, so amount,
 * provider payment ID and monotonic transitions are enforced identically.
 *
 * Adapters that do not implement this interface are simply not reconcilable;
 * the reconciliation report says so and keeps going.
 */
interface ReconcilableGatewayAdapter {
    /**
     * @param GatewayPaymentRecord $record       Persisted attempt being checked.
     * @param GatewayAccount       $account      Provider account that owns it.
     * @param GatewayCredentials   $credentials  Decrypted credentials for that account.
     *
     * @return GatewayWebhookResult VERIFIED when the provider answered with a
     *                              known state; RETRY for transient failures;
     *                              IGNORED when the attempt cannot be queried
     *                              or the provider answer does not match it.
     */
    public function reconcile(
        GatewayPaymentRecord $record,
        GatewayAccount $account,
        GatewayCredentials $credentials
    ): GatewayWebhookResult;
}
