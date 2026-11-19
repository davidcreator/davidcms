<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/** Provider response after it creates a hosted or copy-and-paste payment attempt. */
final class GatewayPaymentResult {
    public readonly string $providerReference;
    public readonly string $providerPaymentId;
    public readonly GatewayCustomerAction $customerAction;
    public readonly Money $chargeAmount;
    public readonly string $status;
    public readonly ?FxQuote $quote;

    public function __construct(
        string $providerReference,
        GatewayCustomerAction $customerAction,
        Money $chargeAmount,
        string $status,
        ?FxQuote $quote = null,
        string $providerPaymentId = '',
    ) {
        $providerReference = trim($providerReference);

        if ($providerReference === '' || strlen($providerReference) > 128 || strlen($providerPaymentId) > 128
            || preg_match('/[\r\n\0]/', $providerReference) === 1 || preg_match('/[\r\n\0]/', $providerPaymentId) === 1) {
            throw new \InvalidArgumentException('gateway payment result: provider reference is invalid');
        }

        if (!$chargeAmount->isPositive() || !GatewayPaymentStatus::isKnown($status)) {
            throw new \InvalidArgumentException('gateway payment result: amount or status is invalid');
        }

        if ($quote !== null && !$quote->chargeAmount->equals($chargeAmount)) {
            throw new \InvalidArgumentException('gateway payment result: charge amount must match the locked quote');
        }

        $this->providerReference = $providerReference;
        $this->providerPaymentId = trim($providerPaymentId);
        $this->customerAction = $customerAction;
        $this->chargeAmount = $chargeAmount;
        $this->status = $status;
        $this->quote = $quote;
    }

    /** Provider references and customer instructions stay out of debug/log dumps. */
    public function __debugInfo(): array {
        return [
            'status' => $this->status,
            'charge_currency' => $this->chargeAmount->currency,
            'charge_amount' => $this->chargeAmount->amount(),
            'has_fx_quote' => $this->quote !== null,
            'customer_action_kind' => $this->customerAction->kind,
        ];
    }
}
