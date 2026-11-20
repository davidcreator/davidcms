<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/** Provider-neutral webhook verification, reconciliation and ledger application. */
final class GatewayWebhookService {
    private readonly GatewayEventApplier $applier;

    public function __construct(
        private readonly GatewayRegistry $registry,
        private readonly GatewayLedger $ledger,
        ?GatewayEventApplier $applier = null,
    ) {
        $this->applier = $applier ?? new GatewayEventApplier($ledger);
    }

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

        // The post-verification rules live in GatewayEventApplier so the active
        // reconciliation path cannot drift from the webhook path.
        return $this->applier->apply($account->gatewayId, $adapter->code(), $event);
    }
}
