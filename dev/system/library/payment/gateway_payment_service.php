<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

use Closure;
use DateTimeImmutable;

/**
 * Shared order/JOW payment flow: validates capabilities, obtains the selected
 * gateway's FX quote, creates an idempotent provider payment, then persists it.
 */
final class GatewayPaymentService {
    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;

    public function __construct(
        private readonly GatewayRegistry $registry,
        private readonly GatewayLedger $ledger,
        ?Closure $clock = null,
        private readonly bool $allowLive = false,
    ) {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now');
    }

    public function createPayment(GatewayAccount $account, GatewayPaymentRequest $request, GatewayCredentials $credentials): GatewayPaymentRecord {
        if (!$account->enabled) {
            throw new \RuntimeException('gateway payment: selected gateway account is disabled');
        }

        if ($account->environment === 'live' && !$this->allowLive) {
            throw new \RuntimeException('gateway payment: live environment is disabled by the core safety gate');
        }

        $adapter = $this->registry->get($account->providerCode);
        $capabilities = $adapter->capabilities();

        $existing = $this->ledger->byIdempotencyKey($account->gatewayId, $request);

        if ($existing !== null) {
            if (!$existing->matchesRequest($account->gatewayId, $account->providerCode, $request)) {
                throw new \LogicException('gateway payment: idempotency key was already used for a different request');
            }

            return $existing;
        }

        if (!$capabilities->supports($request->paymentMethod, $request->chargeCurrency)) {
            throw new \DomainException('gateway payment: payment method/currency pair is not supported');
        }

        $sourceFractionDigits = $capabilities->fractionDigits($request->sourceAmount->currency);
        $chargeFractionDigits = $capabilities->fractionDigits($request->chargeCurrency);

        if ($request->sourceAmount->fractionDigits !== $sourceFractionDigits) {
            throw new \DomainException('gateway payment: source currency precision does not match gateway metadata');
        }

        $quote = null;
        $expectedChargeAmount = $request->sourceAmount;

        if ($request->sourceAmount->currency !== $request->chargeCurrency) {
            if (!$capabilities->supportsFxQuote($request->sourceAmount->currency, $request->chargeCurrency)) {
                throw new \DomainException('gateway payment: selected gateway does not quote this currency pair');
            }

            $quote = $adapter->quote($request->sourceAmount, $request->chargeCurrency, $account, $credentials);

            if ($quote === null) {
                throw new \RuntimeException('gateway payment: gateway did not return its required FX quote');
            }

            $this->assertQuote($quote, $adapter->code(), $request, $chargeFractionDigits);
            $expectedChargeAmount = $quote->chargeAmount;
        } elseif ($chargeFractionDigits !== $request->sourceAmount->fractionDigits) {
            throw new \DomainException('gateway payment: source and charge precision differ for the same currency');
        }

        // Avoid issuing a provider request using a quote that expired while the
        // request was being prepared. There is intentionally no local FX fallback.
        if ($quote !== null && !$quote->isUsableAt(($this->clock)())) {
            throw new \RuntimeException('gateway payment: gateway FX quote expired before payment creation');
        }

        $result = $adapter->createPayment($request, $account, $credentials, $quote);

        if (in_array($result->status, [GatewayPaymentStatus::PENDING, GatewayPaymentStatus::REQUIRES_ACTION], true)
            && $result->customerAction->expiresAt !== null
            && $result->customerAction->expiresAt <= ($this->clock)()) {
            throw new \RuntimeException('gateway payment: provider returned an already-expired customer action');
        }

        if (!$result->chargeAmount->equals($expectedChargeAmount)) {
            throw new \RuntimeException('gateway payment: provider charge amount differs from the exact expected amount');
        }

        if ($quote === null && $result->quote !== null) {
            throw new \RuntimeException('gateway payment: unexpected FX quote for a same-currency payment');
        }

        if ($quote !== null && ($result->quote === null || !$this->sameQuote($quote, $result->quote))) {
            throw new \RuntimeException('gateway payment: provider result did not retain the locked FX quote');
        }

        return $this->ledger->createAttempt($account->gatewayId, $adapter->code(), $request, $result);
    }

    private function assertQuote(FxQuote $quote, string $providerCode, GatewayPaymentRequest $request, int $chargeFractionDigits): void {
        if ($quote->providerCode !== $providerCode || !$quote->sourceAmount->equals($request->sourceAmount)
            || $quote->chargeAmount->currency !== $request->chargeCurrency
            || $quote->chargeAmount->fractionDigits !== $chargeFractionDigits) {
            throw new \RuntimeException('gateway payment: FX quote does not match the selected gateway/request');
        }

        if (!$quote->isUsableAt(($this->clock)())) {
            throw new \RuntimeException('gateway payment: gateway returned an expired or future-dated FX quote');
        }
    }

    private function sameQuote(FxQuote $left, FxQuote $right): bool {
        return $left->providerCode === $right->providerCode
            && $left->reference === $right->reference
            && $left->sourceAmount->equals($right->sourceAmount)
            && $left->chargeAmount->equals($right->chargeAmount)
            && $left->rate === $right->rate
            && $left->quotedAt == $right->quotedAt
            && $left->expiresAt == $right->expiresAt;
    }
}
