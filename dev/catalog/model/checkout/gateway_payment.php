<?php declare(strict_types=1);
/** Catalog-side synchronization for verified JOW gateway events. */
namespace Reamur\Catalog\Model\Checkout;

use Reamur\System\Library\Payment\GatewayPaymentStatus;

class GatewayPayment extends \Reamur\System\Engine\Model {
    /**
     * The multi-gateway core migration is additive and applied by hand. Without
     * it the reconciliation route must refuse instead of querying missing tables.
     */
    public function gatewaySchemaReady(): bool {
        return $this->hasTable('jow_gateway')
            && $this->hasTable('payment_transaction')
            && $this->hasTable('payment_gateway_event');
    }

    /**
     * Keep the legacy JOW charge row/report readable without letting a provider
     * event alter a manual PIX/card/boleto charge.
     *
     * The provider code is part of the WHERE clause on purpose: a verified
     * Stripe/PayPal event may only touch a charge created for that same
     * provider. A manual charge keeps `method` = pix|card|boleto and can never
     * match, so it is never altered by a gateway notification.
     */
    public function syncJowChargeStatus(int $chargeId, string $providerStatus, string $providerCode): void
    {
        if ($chargeId <= 0 || preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $providerCode) !== 1) {
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
            "UPDATE `" . $this->t('jow_charge') . "` SET `status` = '" . $status . "'" . $paidAt
            . " WHERE `charge_id` = " . $chargeId
            . " AND `method` = '" . $this->db->escape($providerCode) . "'"
        );
    }
}
