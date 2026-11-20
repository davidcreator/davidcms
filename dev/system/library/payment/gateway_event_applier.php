<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * Single place where an already authenticated provider event becomes ledger
 * state. The webhook path and the active reconciliation path both go through
 * here, so amount, provider payment ID and the monotonic transition policy can
 * never drift between them.
 */
final class GatewayEventApplier {
    public function __construct(private readonly GatewayLedger $ledger) {}

    public function apply(int $gatewayId, string $providerCode, GatewayWebhookResult $event): GatewayWebhookResult {
        if ($event->kind !== GatewayWebhookResult::VERIFIED) {
            throw new \InvalidArgumentException('gateway event applier: only verified events can be applied');
        }

        if ($gatewayId <= 0 || preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $providerCode) !== 1) {
            throw new \InvalidArgumentException('gateway event applier: invalid gateway identity');
        }

        $record = $this->ledger->byProviderReference($gatewayId, $event->providerReference);

        if ($record === null || $record->providerCode !== $providerCode) {
            return GatewayWebhookResult::ignored('payment_not_found');
        }

        if ($event->chargeAmount !== null && !$record->chargeAmount->equals($event->chargeAmount)) {
            return GatewayWebhookResult::ignored('amount_mismatch');
        }

        if ($event->providerPaymentId !== '' && $record->providerPaymentId !== ''
            && !hash_equals($record->providerPaymentId, $event->providerPaymentId)) {
            return GatewayWebhookResult::ignored('payment_id_mismatch');
        }

        if (!GatewayTerminalStatus::canTransition($record->status, $event->status)) {
            // Out-of-order notifications must not roll a settled payment back.
            // (ADR 0124: um estorno total já executado no provedor é aceito
            // mesmo quando o ledger não registrou o `paid` intermediário.)
            return GatewayWebhookResult::ignored('unsafe_status_transition');
        }

        $this->ledger->recordWebhookOnce($gatewayId, $event);
        $this->ledger->applyVerifiedEvent($gatewayId, $event);

        return $event;
    }
}
