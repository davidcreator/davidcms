<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * Ledger capability required by active reconciliation: list attempts that are
 * still open and old enough to be checked again.
 *
 * Kept separate from GatewayLedger so no existing implementation, fake or
 * caller breaks: only the reconciliation service and DbGatewayLedger know it.
 */
interface GatewayReconcilableLedger extends GatewayLedger {
    /**
     * Attempts in an open state (pending/requires_action/authorized) that were
     * last modified before $modifiedBeforeUtc and created after $createdAfterUtc.
     *
     * All timestamps are UTC strings in 'Y-m-d H:i:s'. Ordering is oldest
     * modification first so a bounded $limit walks the whole queue over time.
     *
     * @return list<GatewayPaymentRecord>
     */
    public function pendingAttempts(
        int $gatewayId,
        string $modifiedBeforeUtc,
        string $createdAfterUtc,
        int $limit
    ): array;

    /**
     * ADR 0123 (R1): tentativas que JÁ foram pagas e cuja última alteração
     * conhecida é anterior a $modifiedBeforeUtc.
     *
     * Existe porque `pendingAttempts()` filtra estado aberto: sem esta varredura,
     * um estorno feito no painel do provedor só chega ao CMS se o webhook for
     * reenviado. O que interessa aqui é descobrir que o mundo mudou depois do
     * pagamento — estorno total, parcial e disputa.
     *
     * `paid` e `partially_refunded` entram porque um segundo estorno parcial
     * leva de `partially_refunded` para `refunded`.
     *
     * Mesmo contrato de `pendingAttempts`: UTC 'Y-m-d H:i:s', mais antiga
     * primeiro, `$limit` limitado.
     *
     * @return list<GatewayPaymentRecord>
     */
    public function settledAttempts(
        int $gatewayId,
        string $modifiedBeforeUtc,
        string $createdAfterUtc,
        int $limit
    ): array;
}
