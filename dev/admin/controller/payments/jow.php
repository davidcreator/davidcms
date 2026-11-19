<?php declare(strict_types=1);
/**
 * JOW-payments — painel interno de pagamentos do ReamurCMS (KZ-35).
 * Rota: payments/jow (somente admin; ACL access/modify via user_group).
 * Seções: cobranças, chaves PIX, relatório de recebimentos e config da loja.
 */
namespace Reamur\Admin\Controller\Payments;

use Reamur\System\Library\Payment\DbGatewayAccountStore;
use Reamur\System\Library\Payment\DbGatewayLedger;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayCredentialVault;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayPaymentRecord;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentService;
use Reamur\System\Library\Payment\GatewayRegistry;
use Reamur\System\Library\Payment\MercadoPagoGatewayAdapter;
use Reamur\System\Library\Payment\Money;

class Jow extends \Reamur\System\Engine\Controller {
    private const PLATFORM_ACCOUNT_LABEL = 'JOW platform';

    public function index(): void {
        $this->load->language('payments/jow');
        $data = array_merge($this->load->language('payments/jow'), $data ?? []); // KZ-57
        $this->load->model('payments/jow');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [
            ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard')],
            ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('payments/jow')],
        ];
        $data['can_modify'] = $this->user->hasPermission('modify', 'payments/jow');
        $data['action'] = $this->url->link('payments/jow.save');
        $data['csrf_token'] = $this->session->data['csrf_token'] ?? '';

        $data['charges'] = $this->model_payments_jow->getCharges(50);
        $data['pix_keys'] = $this->model_payments_jow->getPixKeys();
        $data['report'] = $this->model_payments_jow->report();
        $data['store'] = [
            'jow_store_name' => (string)$this->config->get('jow_store_name'),
            'jow_city' => (string)$this->config->get('jow_city'),
            'jow_merchant_category' => (string)$this->config->get('jow_merchant_category'),
        ];
        $data['gateway_schema_ready'] = $this->model_payments_jow->gatewaySchemaReady();
        $data['gateway'] = [
            'configured' => false,
            'enabled' => false,
            'environment' => 'sandbox',
            'access_token_set' => false,
            'webhook_secret_set' => false,
            'credentials_locked' => false,
        ];
        $data['gateway_webhook_url'] = '';
        $data['live_payments_allowed'] = $this->livePaymentsAllowed();
        $data['gateway_error'] = false;

        if ($data['gateway_schema_ready']) {
            try {
                $registry = $this->gatewayRegistry();
                $store = $this->gatewayAccountStore($registry);
                $gatewayId = $this->model_payments_jow->getPlatformGatewayId();

                if ($gatewayId > 0) {
                    $account = $store->find($gatewayId);

                    if ($account !== null && $account->providerCode === 'mp') {
                        $data['gateway'] = [
                            'configured' => true,
                            'enabled' => $account->enabled,
                            'environment' => $account->environment,
                            'access_token_set' => false,
                            'webhook_secret_set' => false,
                            'credentials_locked' => false,
                        ];
                        $data['gateway_webhook_url'] = $this->catalogRouteUrl('checkout/gateway.notify&gateway_id=' . $gatewayId);

                        try {
                            $credentials = $store->credentials($gatewayId);
                            $data['gateway']['access_token_set'] = $credentials->has('access_token');
                            $data['gateway']['webhook_secret_set'] = $credentials->has('webhook_secret');
                        } catch (\Throwable) {
                            $data['gateway']['credentials_locked'] = true;
                        }
                    }
                }

                $ledger = new DbGatewayLedger($this->db, DB_PREFIX);
                foreach ($data['charges'] as &$charge) {
                    $charge['gateway_status'] = '';
                    $charge['payment_url'] = '';

                    if ((string)($charge['method'] ?? '') !== 'mp') {
                        continue;
                    }

                    $record = $ledger->latestForSource('jow_charge', (int)$charge['charge_id']);
                    if ($record !== null) {
                        $charge['gateway_status'] = $record->status;
                        $charge['payment_url'] = $record->customerAction->url;
                    }
                }
                unset($charge);
            } catch (\Throwable $e) {
                $data['gateway_error'] = true;
                $this->safeLog('JOW Mercado Pago account/ledger could not be loaded: ' . $e->getMessage());
            }
        }

        $data['error'] = $this->session->data['error'] ?? '';
        $data['success'] = $this->session->data['success'] ?? '';
        unset($this->session->data['error'], $this->session->data['success']);

        $this->document->addStyle($this->adminAssetUrl() . 'view/css/admin.css?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('payments/jow', $data));
    }

    public function save(): void {
        $this->load->language('payments/jow');

        if (!$this->user->hasPermission('modify', 'payments/jow')
            || $this->request->server['REQUEST_METHOD'] !== 'POST'
            || !$this->user->csrfValid()) {
            $this->response->redirect($this->url->link('payments/jow'));
            return;
        }

        $this->load->model('payments/jow');
        $action = $this->postScalar('action');

        switch ($action) {
            case 'charge_add':
                $amountText = str_replace(',', '.', trim($this->postScalar('amount', '0')));
                $provider = $this->postScalar('provider', 'manual');
                $description = trim($this->postScalar('description'));
                $payerEmail = trim($this->postScalar('payer_email'));
                $dueDate = trim($this->postScalar('due_date'));
                $method = $provider === 'mp' ? 'mp' : $this->postScalar('method', 'pix');

                if (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amountText) !== 1
                    || (float)$amountText <= 0.0 || !is_finite((float)$amountText)) {
                    $this->session->data['error'] = $this->language->get('error_amount');
                    break;
                }

                if ($description === '') {
                    $this->session->data['error'] = $this->language->get('error_description');
                    break;
                }

                if (!in_array($provider, ['manual', 'mp'], true)
                    || ($provider === 'manual' && !in_array($method, ['pix', 'card', 'boleto'], true))) {
                    $this->session->data['error'] = $this->language->get('error_method');
                    break;
                }

                if ($payerEmail !== '' && (strlen($payerEmail) > 96 || filter_var($payerEmail, FILTER_VALIDATE_EMAIL) === false)) {
                    $this->session->data['error'] = $this->language->get('error_payer_email');
                    break;
                }

                $id = $this->model_payments_jow->addCharge(
                    $description,
                    (float)$amountText,
                    $method,
                    $payerEmail,
                    $dueDate !== '' ? $dueDate : null
                );

                if ($provider === 'mp') {
                    try {
                        $this->createMercadoPagoLink($id);
                        $this->session->data['success'] = $this->language->get('text_mp_link_ready');
                    } catch (\Throwable $e) {
                        $this->safeLog('JOW Mercado Pago checkout creation failed: ' . $e->getMessage());
                        $this->session->data['error'] = $this->language->get('error_mp_link_failed');
                    }
                    break;
                }

                if ($method === 'pix') {
                    $key = $this->model_payments_jow->defaultPixKey();
                    if ($key !== '') {
                        $this->model_payments_jow->attachPix(
                            $id,
                            $this->model_payments_jow->buildPixPayload($key, (float)$amountText, $description)
                        );
                    }
                }

                $this->session->data['success'] = $this->language->get('text_charge_added');
                break;

            case 'charge_mp_link':
                try {
                    $this->createMercadoPagoLink((int)$this->postScalar('charge_id'));
                    $this->session->data['success'] = $this->language->get('text_mp_link_ready');
                } catch (\Throwable $e) {
                    $this->safeLog('JOW Mercado Pago checkout retry failed: ' . $e->getMessage());
                    $this->session->data['error'] = $this->language->get('error_mp_link_failed');
                }
                break;

            case 'charge_status':
                $this->model_payments_jow->setChargeStatus(
                    (int)$this->postScalar('charge_id'),
                    $this->postScalar('status', 'pending')
                );
                $this->session->data['success'] = $this->language->get('text_charge_updated');
                break;

            case 'mp_account_save':
                $this->saveMercadoPagoAccount();
                break;

            case 'pix_add':
                $ok = $this->model_payments_jow->addPixKey(
                    $this->postScalar('type'),
                    $this->postScalar('key'),
                    $this->postScalar('holder')
                );
                $this->session->data[$ok ? 'success' : 'error'] = $this->language->get($ok ? 'text_pix_saved' : 'error_pix_invalid');
                break;

            case 'pix_delete':
                $this->model_payments_jow->deletePixKey((int)$this->postScalar('key_id'));
                $this->session->data['success'] = $this->language->get('text_pix_deleted');
                break;

            case 'store_save':
                $this->model_payments_jow->saveStore((array)$this->request->post);
                $this->session->data['success'] = $this->language->get('text_store_saved');
                break;
        }

        $this->response->redirect($this->url->link('payments/jow'));
    }

    private function saveMercadoPagoAccount(): void {
        if (!$this->model_payments_jow->gatewaySchemaReady()) {
            $this->session->data['error'] = $this->language->get('error_gateway_migration');
            return;
        }

        $environment = $this->postScalar('mp_environment', 'sandbox');
        if (!in_array($environment, ['sandbox', 'live'], true)) {
            $this->session->data['error'] = $this->language->get('error_mp_account_save');
            return;
        }

        $accessToken = trim($this->postScalar('mp_access_token'));
        $webhookSecret = trim($this->postScalar('mp_gateway_webhook_secret'));
        $clearAccessToken = $this->postScalar('mp_access_token_clear') === '1';
        $clearWebhookSecret = $this->postScalar('mp_gateway_webhook_secret_clear') === '1';
        $enabled = $this->postScalar('mp_enabled') === '1';

        if (($accessToken !== '' && (strlen($accessToken) < 16 || strlen($accessToken) > 8192 || preg_match('/[\x00-\x20\x7f]/', $accessToken) === 1))
            || ($webhookSecret !== '' && (strlen($webhookSecret) < 16 || strlen($webhookSecret) > 255 || preg_match('/[\x00-\x20\x7f]/', $webhookSecret) === 1))) {
            $this->session->data['error'] = $this->language->get('error_mp_credentials');
            return;
        }

        $secretUpdates = [];
        $clearFields = [];
        if ($accessToken !== '') {
            $secretUpdates['access_token'] = $accessToken;
        } elseif ($clearAccessToken) {
            $clearFields[] = 'access_token';
        }
        if ($webhookSecret !== '') {
            $secretUpdates['webhook_secret'] = $webhookSecret;
        } elseif ($clearWebhookSecret) {
            $clearFields[] = 'webhook_secret';
        }

        try {
            $registry = $this->gatewayRegistry();
            $store = $this->gatewayAccountStore($registry);
            $gatewayId = $this->model_payments_jow->getPlatformGatewayId();
            $publicConfig = ['flow' => 'jow_internal'];

            if ($gatewayId <= 0) {
                $account = $store->create('mp', self::PLATFORM_ACCOUNT_LABEL, $environment, false, $publicConfig, []);
                $gatewayId = $account->gatewayId;
            }

            // Save all changes with the account disabled first. It can only be
            // re-enabled after both required secrets can be opened successfully.
            $store->update(
                $gatewayId,
                self::PLATFORM_ACCOUNT_LABEL,
                $environment,
                false,
                $publicConfig,
                $secretUpdates,
                $clearFields
            );

            if ($enabled) {
                $credentials = $store->credentials($gatewayId);
                if (!$credentials->has('access_token') || !$credentials->has('webhook_secret')) {
                    $this->session->data['error'] = $this->language->get('error_mp_credentials_required');
                    return;
                }

                $store->update($gatewayId, self::PLATFORM_ACCOUNT_LABEL, $environment, true, $publicConfig);
            }

            $this->session->data['success'] = $this->language->get($enabled ? 'text_mp_account_enabled' : 'text_mp_account_saved');
        } catch (\Throwable $e) {
            $this->safeLog('JOW Mercado Pago account settings could not be saved: ' . $e->getMessage());
            $this->session->data['error'] = $this->language->get('error_mp_account_save');
        }
    }

    private function createMercadoPagoLink(int $chargeId): GatewayPaymentRecord {
        if (!$this->model_payments_jow->gatewaySchemaReady()) {
            throw new \RuntimeException('JOW-Payments core migration is not installed');
        }

        $charge = $this->model_payments_jow->getCharge($chargeId);
        if ($charge === [] || (string)($charge['method'] ?? '') !== 'mp') {
            throw new \OutOfBoundsException('JOW Mercado Pago charge was not found');
        }

        $chargeStatus = (string)($charge['status'] ?? '');
        if (!in_array($chargeStatus, ['pending', 'canceled', 'expired'], true)) {
            throw new \DomainException('JOW Mercado Pago checkout cannot be created for a settled charge');
        }

        $registry = $this->gatewayRegistry();
        $store = $this->gatewayAccountStore($registry);
        $gatewayId = $this->model_payments_jow->getPlatformGatewayId();
        $account = $gatewayId > 0 ? $store->find($gatewayId) : null;

        if (!$account instanceof GatewayAccount || $account->providerCode !== 'mp' || !$account->enabled) {
            throw new \RuntimeException('JOW Mercado Pago platform account is not enabled');
        }

        $credentials = $store->credentials($gatewayId);
        if (!$credentials->has('access_token') || !$credentials->has('webhook_secret')) {
            throw new \RuntimeException('JOW Mercado Pago platform credentials are incomplete');
        }

        $ledger = new DbGatewayLedger($this->db, DB_PREFIX);
        $latest = $ledger->latestForSource('jow_charge', $chargeId);
        $idempotencyKey = $this->nextIdempotencyKey($chargeId, $latest);
        $amount = new Money((string)$charge['amount'], 'BRL', 2);
        $title = trim((string)($charge['description'] ?? ''));
        if ($title === '') {
            $title = 'JOW charge #' . $chargeId;
        }

        $request = new GatewayPaymentRequest(
            'jow_charge',
            $chargeId,
            $amount,
            'BRL',
            'checkout_pro',
            $idempotencyKey,
            mb_substr($title, 0, 255),
            $this->catalogRouteUrl('common/home'),
            $this->catalogRouteUrl('checkout/gateway.notify&gateway_id=' . $gatewayId),
            (string)($charge['payer_email'] ?? '')
        );

        $service = new GatewayPaymentService(
            $registry,
            $ledger,
            null,
            $this->livePaymentsAllowed()
        );

        return $service->createPayment($account, $request, $credentials);
    }

    private function nextIdempotencyKey(int $chargeId, ?GatewayPaymentRecord $latest): string {
        if ($latest === null) {
            return self::orderIdempotencyUuid('charge:' . $chargeId . ':initial');
        }

        if (!in_array($latest->status, ['failed', 'cancelled', 'expired'], true)) {
            if (!in_array($latest->status, ['pending', 'requires_action', 'authorized'], true)) {
                throw new \DomainException('JOW Mercado Pago charge is already settled or under dispute');
            }

            if (preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $latest->idempotencyKey) !== 1) {
                throw new \LogicException('JOW Mercado Pago attempt has an unexpected idempotency key');
            }

            return $latest->idempotencyKey;
        }

        // Derive a stable UUID v5 from the previous persisted transaction. If
        // the provider created an order but DB persistence failed, a retry sees
        // the same prior transaction and reuses exactly the same key.
        return self::orderIdempotencyUuid('charge:' . $chargeId . ':after:' . $latest->transactionId);
    }

    private static function orderIdempotencyUuid(string $name): string {
        $namespace = hex2bin('6ba7b8109dad11d180b400c04fd430c8'); // RFC 4122 DNS namespace
        if ($namespace === false) {
            throw new \LogicException('JOW Mercado Pago could not create an idempotency UUID');
        }

        $hex = substr(sha1($namespace . 'reamurcms:jow:mercadopago:' . $name), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x03) | 0x08);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    private function gatewayRegistry(): GatewayRegistry {
        return new GatewayRegistry([new MercadoPagoGatewayAdapter()]);
    }

    private function gatewayAccountStore(GatewayRegistry $registry): DbGatewayAccountStore {
        return new DbGatewayAccountStore(
            $this->db,
            DB_PREFIX,
            $registry,
            new GatewayCredentialVault($this->secrets())
        );
    }

    private function catalogRouteUrl(string $route): string {
        $base = rtrim((string)$this->config->get('site_url', ''), '/');

        if (str_ends_with($base, '/admin')) {
            $base = substr($base, 0, -6);
        }

        return $base . '/catalog/index.php?route=' . $route;
    }

    private function livePaymentsAllowed(): bool {
        if (!defined('REAMUR_PAYMENTS_ALLOW_LIVE')) {
            return false;
        }

        $value = constant('REAMUR_PAYMENTS_ALLOW_LIVE');

        return $value === true || $value === 1 || $value === '1';
    }

    private function safeLog(string $line): void {
        try {
            $this->log->write($line);
        } catch (\Throwable) {
            // Logging failure must not reveal provider credentials or interrupt the UI.
        }
    }
}
