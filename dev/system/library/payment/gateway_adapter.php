<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * Provider boundary for JOW-Payments. Adapters must never trust a webhook body
 * alone; verified notifications must be authenticated and reconciled with the
 * provider before returning GatewayWebhookResult::verified().
 */
interface GatewayAdapter {
    public function code(): string;

    public function label(): string;

    public function capabilities(): GatewayCapabilities;

    /** Return null only when source and charge currencies are identical. */
    public function quote(Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?FxQuote;

    /** The quote and idempotency key must be honored exactly by the provider. */
    public function createPayment(
        GatewayPaymentRequest $request,
        GatewayAccount $account,
        GatewayCredentials $credentials,
        ?FxQuote $quote
    ): GatewayPaymentResult;

    /** Authenticate and reconcile with the provider before returning VERIFIED. */
    public function verifyNotification(
        Notification $notification,
        GatewayAccount $account,
        GatewayCredentials $credentials
    ): GatewayWebhookResult;
}
