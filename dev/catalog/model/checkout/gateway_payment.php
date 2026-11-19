<?php declare(strict_types=1);
/** Catalog-side synchronization for verified JOW gateway events. */
namespace Reamur\Catalog\Model\Checkout;

use Reamur\System\Library\Payment\GatewayPaymentStatus;

class GatewayPayment extends \Reamur\System\Engine\Model {
    /**
     * Keep the legacy JOW charge row/report readable without letting a provider
     * event alter a manual PIX/card/boleto charge.
     */
    public function syncJowChargeStatus(int $chargeId, string $providerStatus): void {
        if ($chargeId <= 0) {
            return;
        }

        $status = match ($providerStatus) {
            GatewayPaymentStatus::PENDING,
            GatewayPaymentStatus::REQUIRES_ACTION,
            GatewayPaymentStatus::AUTHORIZED => 'pending',
            GatewayPaymentStatus::PAID => 'paid',
            GatewayPaymentStatus::PARTIALLY_REFUNDED => 'partial',
            GatewayPaymentStatus::FAILED,
            GatewayPaymentStatus::CANCELLED => 'canceled',
            GatewayPaymentStatus::EXPIRED => 'expired',
            GatewayPaymentStatus::REFUNDED => 'refunded',
            GatewayPaymentStatus::DISPUTED => 'disputed',
            default => '',
        };

        if ($status === '') {
            return;
        }

        $paidAt = in_array($status, ['paid', 'partial', 'refunded', 'disputed'], true)
            ? ', `paid_at` = COALESCE(`paid_at`, NOW())'
            : '';

        $this->db->query(
            "UPDATE `" . DB_PREFIX . "jow_charge` SET `status` = '" . $status . "'" . $paidAt
            . " WHERE `charge_id` = " . $chargeId . " AND `method` = 'mp'"
        );
    }
}
