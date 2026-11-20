<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/** Persisted, provider-neutral payment attempt returned by GatewayLedger. */
final class GatewayPaymentRecord {
    public function __construct(
        public readonly int $transactionId,
        public readonly int $gatewayId,
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly string $providerCode,
        public readonly string $methodCode,
        public readonly Money $sourceAmount,
        public readonly Money $chargeAmount,
        public readonly string $publicToken,
        public readonly string $idempotencyKey,
        public readonly string $status,
        public readonly string $providerReference,
        public readonly string $providerPaymentId,
        public readonly GatewayCustomerAction $customerAction,
        public readonly ?FxQuote $fxQuote,
        /** Provider-side id queryable by reconciliation; '' when unknown. */
        public readonly string $providerCheckoutId = '',
    ) {
        if ($transactionId <= 0 || $gatewayId <= 0 || $sourceId <= 0
            || !in_array($sourceType, ['order', 'jow_charge'], true)
            || preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $providerCode) !== 1
            || preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $methodCode) !== 1
            || preg_match('/^[a-f0-9]{48}$/D', $publicToken) !== 1
            || preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $idempotencyKey) !== 1
            || !GatewayPaymentStatus::isKnown($status)
            || trim($providerReference) === '' || strlen($providerReference) > 128
            || strlen($providerPaymentId) > 128 || strlen($providerCheckoutId) > 128
            || preg_match('/[\r\n\0]/', $providerCheckoutId) === 1) {
            throw new \InvalidArgumentException('payment record: invalid persisted fields');
        }

        if (!$sourceAmount->isPositive() || !$chargeAmount->isPositive()) {
            throw new \InvalidArgumentException('payment record: amounts must be positive');
        }

        if ($fxQuote === null && !$sourceAmount->equals($chargeAmount)) {
            throw new \InvalidArgumentException('payment record: amount/currency changes require a locked FX quote');
        }

        if ($fxQuote !== null && (!$fxQuote->sourceAmount->equals($sourceAmount)
            || !$fxQuote->chargeAmount->equals($chargeAmount) || $fxQuote->providerCode !== $providerCode)) {
            throw new \InvalidArgumentException('payment record: FX quote does not match the payment amounts/provider');
        }
    }

    /** Keep bearer token, idempotency key and provider action out of debug/log dumps. */
    public function __debugInfo(): array {
        return [
            'transaction_id' => $this->transactionId,
            'gateway_id' => $this->gatewayId,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'provider_code' => $this->providerCode,
            'method_code' => $this->methodCode,
            'source_currency' => $this->sourceAmount->currency,
            'source_amount' => $this->sourceAmount->amount(),
            'charge_currency' => $this->chargeAmount->currency,
            'charge_amount' => $this->chargeAmount->amount(),
            'status' => $this->status,
            'has_public_token' => $this->publicToken !== '',
            'has_provider_action' => true,
            'has_provider_checkout_id' => $this->providerCheckoutId !== '',
        ];
    }

    public function matchesRequest(int $gatewayId, string $providerCode, GatewayPaymentRequest $request): bool {
        return $this->gatewayId === $gatewayId
            && $this->providerCode === $providerCode
            && $this->sourceType === $request->sourceType
            && $this->sourceId === $request->sourceId
            && $this->methodCode === $request->paymentMethod
            && $this->sourceAmount->equals($request->sourceAmount)
            && $this->chargeAmount->currency === $request->chargeCurrency
            && $this->idempotencyKey === $request->idempotencyKey;
    }
}
