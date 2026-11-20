<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * Ledger capability required by the order flow (ADR 0119): find an attempt by
 * its own `transaction_id`.
 *
 * Kept separate from GatewayLedger, exactly like GatewayReconcilableLedger, so
 * no existing implementation, fake or caller breaks. Only DbGatewayLedger and
 * OrderFlowLedger know it.
 */
interface GatewayAddressableLedger extends GatewayLedger {
    public function byTransactionId(int $transactionId): ?GatewayPaymentRecord;
}
