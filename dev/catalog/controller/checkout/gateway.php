<?php declare(strict_types=1);
/** Public webhook endpoint for platform-owned JOW gateway accounts. */
namespace Reamur\Catalog\Controller\Checkout;

use Reamur\System\Library\Payment\DbGatewayAccountStore;
use Reamur\System\Library\Payment\DbGatewayLedger;
use Reamur\System\Library\Payment\GatewayCredentialVault;
use Reamur\System\Library\Payment\GatewayRegistry;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\GatewayWebhookService;
use Reamur\System\Library\Payment\InstalledGatewayAdapters;
use Reamur\System\Library\Payment\Notification;

class Gateway extends \Reamur\System\Engine\Controller {
    public function index(): void {
        (new \Reamur\System\Engine\Action('error/not_found'))->execute($this->registry);
    }

    /** Platform-owned gateway webhooks (Mercado Pago Orders, Stripe Checkout, PayPal Orders). */
    public function notify(): void {
        $this->response->addHeader('Content-Type: text/plain; charset=utf-8');

        $rawGatewayId = $this->request->get['gateway_id'] ?? null;
        $gatewayIdText = is_scalar($rawGatewayId) ? (string)$rawGatewayId : '';
        $gatewayId = ctype_digit($gatewayIdText) ? (int)$gatewayIdText : 0;

        if ($gatewayId <= 0) {
            $this->response->setOutput('ok');
            return;
        }

        try {
            $registry = InstalledGatewayAdapters::registry();
            $store = new DbGatewayAccountStore(
                $this->db,
                DB_PREFIX,
                $registry,
                new GatewayCredentialVault($this->secrets())
            );
            $account = $store->find($gatewayId);

            if ($account === null) {
                $this->response->setOutput('ok');
                return;
            }

            try {
                $registry->get($account->providerCode);
            } catch (\OutOfBoundsException) {
                // Unknown provider: nothing this deployment can reconcile.
                $this->response->setOutput('ok');
                return;
            }

            // A disabled account may still have payments in flight; it remains
            // able to reconcile signed events, but cannot create new attempts.
            $credentials = $store->credentials($gatewayId);
            $ledger = new DbGatewayLedger($this->db, DB_PREFIX);
            $rawBody = file_get_contents('php://input', false, null, 0, 65536);
            $notification = new Notification(
                (array)$this->request->get,
                (array)$this->request->post,
                (array)$this->request->server,
                is_string($rawBody) ? $rawBody : ''
            );
            $result = (new GatewayWebhookService($registry, $ledger))->process($account, $credentials, $notification);

            if ($result->kind === GatewayWebhookResult::RETRY) {
                $this->retryResponse();
                return;
            }

            if ($result->kind === GatewayWebhookResult::VERIFIED) {
                $record = $ledger->byProviderReference($gatewayId, $result->providerReference);

                if ($record !== null && $record->sourceType === 'jow_charge') {
                    $this->load->model('checkout/gateway_payment');
                    $this->model_checkout_gateway_payment->syncJowChargeStatus(
                        $record->sourceId,
                        $record->status,
                        $record->providerCode
                    );
                }
            }

            // Invalid signatures, unrelated notifications and unknown orders
            // get 200; transient provider/storage failures alone request retry.
            $this->response->setOutput('ok');
        } catch (\Throwable $e) {
            $this->safeLog('JOW platform gateway webhook failed: ' . $e->getMessage());
            $this->retryResponse();
        }
    }

    private function retryResponse(): void {
        $this->response->addHeader('HTTP/1.1 503 Service Unavailable');
        $this->response->addHeader('Retry-After: 60');
        $this->response->setOutput('retry');
    }

    private function safeLog(string $line): void {
        try {
            $this->log->write($line);
        } catch (\Throwable) {
            // Webhook replies must not depend on the logger.
        }
    }
}
