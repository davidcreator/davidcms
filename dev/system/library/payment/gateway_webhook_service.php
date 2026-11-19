<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/** Provider-neutral webhook verification, reconciliation and ledger application. */
final class GatewayWebhookService {
    public function __construct(
        private readonly GatewayRegistry $registry,
        private readonly GatewayLedger $ledger,
    ) {}

    public function process(
        GatewayAccount $account,
        GatewayCredentials $credentials,
        Notification $notification
    ): GatewayWebhookResult {
        $adapter = $this->registry->get($account->providerCode);
        $event = $adapter->verifyNotification($notification, $account, $credentials);

        if ($event->kind !== GatewayWebhookResult::VERIFIED) {
            return $event;
        }

        $record = $this->ledger->byProviderReference($account->gatewayId, $event->providerReference);

        if ($record === null || $record->providerCode !== $adapter->code()) {
            return GatewayWebhookResult::ignored('payment_not_found');
        }

        if ($event->chargeAmount !== null && !$record->chargeAmount->equals($event->chargeAmount)) {
            return GatewayWebhookResult::ignored('amount_mismatch');
        }

        if ($event->providerPaymentId !== '' && $record->providerPaymentId !== ''
            && !hash_equals($record->providerPaymentId, $event->providerPaymentId)) {
            return GatewayWebhookResult::ignored('payment_id_mismatch');
        }

        if (!GatewayPaymentStatus::canTransition($record->status, $event->status)) {
            // Out-of-order notifications must not roll a settled payment back.
            return GatewayWebhookResult::ignored('unsafe_status_transition');
        }

        $this->ledger->recordWebhookOnce($account->gatewayId, $event);
        $this->ledger->applyVerifiedEvent($account->gatewayId, $event);

        return $event;
    }
}
