<?php declare(strict_types=1);
/**
 * JOW-payments — painel interno de pagamentos do ReamurCMS (KZ-35).
 * Rota: payments/jow (somente admin; ACL access/modify via user_group).
 * Seções: cobranças, chaves PIX, relatório de recebimentos e config da loja.
 * Contas próprias multi-gateway (Mercado Pago, Stripe, PayPal) isoladas do
 * checkout público Marketplace OAuth/split.
 */
namespace Reamur\Admin\Controller\Payments;

use Reamur\System\Library\Payment\ChargeStatusConcordance;
use Reamur\System\Library\Payment\DbGatewayAccountStore;
use Reamur\System\Library\Payment\DbGatewayLedger;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayCredentialVault;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayPaymentRecord;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\DbLedger;
use Reamur\System\Library\Payment\GatewayPaymentService;
use Reamur\System\Library\Payment\Link;
use Reamur\System\Library\Payment\OrderGatewayLedger;
use Reamur\System\Library\Payment\OrderPaymentLink;
use Reamur\System\Library\Payment\GatewayRegistry;
use Reamur\System\Library\Payment\InstalledGatewayAdapters;
use Reamur\System\Library\Payment\Money;

class Jow extends \Reamur\System\Engine\Controller {
    private const PLATFORM_ACCOUNT_LABEL = 'JOW platform';
    private const MANUAL_METHODS = ['pix', 'card', 'boleto'];


    /**
     * Encrypted credential fields per installed provider. A provider only
     * enables its account when every listed field is present and openable.
     *
     * @var array<string, array<string, int>>
     */
    private const PROVIDER_SECRET_FIELDS = [
        'mp' => ['access_token' => 8192, 'webhook_secret' => 255],
        'stripe' => ['secret_key' => 8192, 'webhook_secret' => 255],
        'paypal' => ['client_id' => 8192, 'client_secret' => 8192, 'webhook_id' => 255],
    ];

    /**
     * Language keys for each provider's credential fields.
     *
     * @var array<string, array<string, array{entry: string, help: string}>>
     */
    private const PROVIDER_FIELD_TEXTS = [
        'mp' => [
            'access_token' => ['entry' => 'entry_mp_access_token', 'help' => 'help_mp_access_token'],
            'webhook_secret' => ['entry' => 'entry_mp_gateway_webhook_secret', 'help' => 'help_mp_gateway_webhook_secret'],
        ],
        'stripe' => [
            'secret_key' => ['entry' => 'entry_stripe_secret_key', 'help' => 'help_stripe_secret_key'],
            'webhook_secret' => ['entry' => 'entry_stripe_webhook_secret', 'help' => 'help_stripe_webhook_secret'],
        ],
        'paypal' => [
            'client_id' => ['entry' => 'entry_paypal_client_id', 'help' => 'help_paypal_client_id'],
            'client_secret' => ['entry' => 'entry_paypal_client_secret', 'help' => 'help_paypal_client_secret'],
            'webhook_id' => ['entry' => 'entry_paypal_webhook_id', 'help' => 'help_paypal_webhook_id'],
        ],
    ];

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
        // ADR 0119: o link de pedido nasce no ledger novo, então a tela precisa
        // de um ponto de entrada próprio. Sem a migração, a seção não aparece.
        $data['recent_orders'] = $data['gateway_schema_ready'] ? $this->model_payments_jow->getRecentOrdersForGatewayLink(10) : [];
        $data['gateway_providers'] = [];
        $data['gateways'] = [];
        $data['live_payments_allowed'] = $this->livePaymentsAllowed();
        $data['gateway_error'] = false;

        if ($data['gateway_schema_ready']) {
            try {
                $registry = $this->gatewayRegistry();
                $store = $this->gatewayAccountStore($registry);

                foreach ($registry->all() as $adapter) {
                    $code = $adapter->code();
                    $data['gateway_providers'][] = ['code' => $code, 'label' => $adapter->label()];
                    $data['gateways'][$code] = $this->gatewayState($store, $code) + ['label' => $adapter->label()];
                }

                $gatewayCodes = array_keys($data['gateways']);
                $ledger = new DbGatewayLedger($this->db, DB_PREFIX);
                foreach ($data['charges'] as &$charge) {
                    $charge['gateway_status'] = '';
                    $charge['payment_url'] = '';
                    $charge['is_gateway'] = in_array((string)($charge['method'] ?? ''), $gatewayCodes, true);

                    if (!$charge['is_gateway']) {
                        continue;
                    }

                    $record = $ledger->latestForSource('jow_charge', (int)$charge['charge_id']);
                    if ($record !== null) {
                        $charge['gateway_status'] = $record->status;
                        $charge['payment_url'] = $record->customerAction->url;
                    }

                    // ADR 0121 (R1): as duas fontes de situação divergem em
                    // silêncio — a do operador (`jow_charge.status`) e a do
                    // provedor (ledger). A tela mostrava só a do provedor para
                    // cobrança de gateway, então o que o operador digitava
                    // desaparecia. Aqui a divergência fica visível.
                    $charge['status_mismatch'] = ChargeStatusConcordance::diverges((string)($charge['status'] ?? ''), (string)($charge['gateway_status'] ?? ''));
                }
                unset($charge);
            } catch (\Throwable $e) {
                $data['gateway_error'] = true;
                $this->safeLog('JOW gateway account/ledger could not be loaded: ' . $e->getMessage());
            }
        } else {
            foreach ($this->gatewayRegistry()->all() as $adapter) {
                $data['gateway_providers'][] = ['code' => $adapter->code(), 'label' => $adapter->label()];
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
                $gatewayCodes = InstalledGatewayAdapters::codes();
                $isGateway = in_array($provider, $gatewayCodes, true);
                $method = $isGateway ? $provider : $this->postScalar('method', 'pix');

                if (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amountText) !== 1
                    || (float)$amountText <= 0.0 || !is_finite((float)$amountText)) {
                    $this->session->data['error'] = $this->language->get('error_amount');
                    break;
                }

                if ($description === '') {
                    $this->session->data['error'] = $this->language->get('error_description');
                    break;
                }

                if (!in_array($provider, array_merge(['manual'], $gatewayCodes), true)
                    || ($provider === 'manual' && !in_array($method, self::MANUAL_METHODS, true))) {
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

                if ($isGateway) {
                    try {
                        $this->createGatewayLink($id, $provider);
                        $this->session->data['success'] = $this->language->get('text_gateway_link_ready');
                    } catch (\Throwable $e) {
                        $this->safeLog('JOW gateway checkout creation failed: ' . $e->getMessage());
                        $this->session->data['error'] = $this->language->get('error_gateway_link_failed');
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

            case 'charge_gateway_link':
            case 'charge_mp_link': // compatibilidade com posts antigos da página
                $provider = $action === 'charge_mp_link'
                    ? 'mp'
                    : $this->postScalar('provider', '');

                try {
                    $this->createGatewayLink((int)$this->postScalar('charge_id'), $provider);
                    $this->session->data['success'] = $this->language->get('text_gateway_link_ready');
                } catch (\Throwable $e) {
                    $this->safeLog('JOW gateway checkout retry failed: ' . $e->getMessage());
                    $this->session->data['error'] = $this->language->get('error_gateway_link_failed');
                }
                break;

            case 'order_gateway_link':
                // ADR 0119: o link de um PEDIDO nasce no ledger novo. A ação é
                // separada de `charge_gateway_link` de propósito: pedido e
                // cobrança interna têm ledgers e telas diferentes, e misturar
                // os dois tornaria impossível saber qual registro foi criado.
                $orderId = (int)$this->postScalar('order_id');
                $provider = $this->postScalar('provider', '');

                try {
                    $link = $this->createOrderGatewayLink($orderId, $provider);
                    $this->session->data['success'] = match ($link->state) {
                        Link::CREATED => $this->language->get('text_gateway_link_ready'),
                        Link::REUSED  => $this->language->get('text_gateway_link_reused'),
                        Link::PAID    => $this->language->get('text_order_already_paid'),
                        Link::NO_TOTAL => $this->language->get('error_order_without_total'),
                        default        => $this->language->get('error_gateway_link_failed'),
                    };
                } catch (\Throwable $e) {
                    $this->safeLog('JOW order gateway link failed: ' . $e->getMessage());
                    $this->session->data['error'] = $this->language->get('error_gateway_link_failed');
                }
                break;

            case 'charge_status':
                $this->model_payments_jow->setChargeStatus(
                    (int)$this->postScalar('charge_id'),
                    $this->postScalar('status', 'pending')
                );
                $this->session->data['success'] = $this->language->get('text_charge_updated');
                break;

            case 'mp_account_save': // compatibilidade com posts antigos da página
            case 'gateway_account_save':
                $provider = $action === 'mp_account_save'
                    ? 'mp'
                    : $this->postScalar('gateway_provider', '');
                $this->saveGatewayAccount($provider);
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

    private function saveGatewayAccount(string $provider): void {
        if (!InstalledGatewayAdapters::isInstalled($provider)) {
            $this->session->data['error'] = $this->language->get('error_gateway_account_save');
            return;
        }

        if (!$this->model_payments_jow->gatewaySchemaReady()) {
            $this->session->data['error'] = $this->language->get('error_gateway_migration');
            return;
        }

        $environment = $this->postScalar('gateway_environment', 'sandbox');
        if (!in_array($environment, ['sandbox', 'live'], true)) {
            $this->session->data['error'] = $this->language->get('error_gateway_account_save');
            return;
        }

        $enabled = $this->postScalar('gateway_enabled') === '1';
        $secretUpdates = [];
        $clearFields = [];

        foreach (self::PROVIDER_SECRET_FIELDS[$provider] ?? [] as $field => $maxLength) {
            $value = trim($this->postScalar('secret_' . $field));
            $clear = $this->postScalar('secret_' . $field . '_clear') === '1';

            if ($value !== ''
                && (strlen($value) < 16 || strlen($value) > $maxLength
                    || preg_match('/[\x00-\x20\x7f]/', $value) === 1)) {
                $this->session->data['error'] = $this->language->get('error_gateway_credentials');
                return;
            }

            if ($value !== '') {
                $secretUpdates[$field] = $value;
            } elseif ($clear) {
                $clearFields[] = $field;
            }
        }

        try {
            $registry = $this->gatewayRegistry();
            $store = $this->gatewayAccountStore($registry);
            $gatewayId = $this->model_payments_jow->getPlatformGatewayId($provider);
            $publicConfig = ['flow' => 'jow_internal'];

            if ($gatewayId <= 0) {
                $account = $store->create($provider, self::PLATFORM_ACCOUNT_LABEL, $environment, false, $publicConfig, []);
                $gatewayId = $account->gatewayId;
            }

            // Save all changes with the account disabled first. It can only be
            // re-enabled after every required secret can be opened successfully.
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
                foreach (self::PROVIDER_SECRET_FIELDS[$provider] ?? [] as $field => $maxLength) {
                    if (!$credentials->has($field)) {
                        $this->session->data['error'] = $this->language->get('error_gateway_credentials_required');
                        return;
                    }
                }

                $store->update($gatewayId, self::PLATFORM_ACCOUNT_LABEL, $environment, true, $publicConfig);
            }

            $this->session->data['success'] = $this->language->get($enabled ? 'text_gateway_account_enabled' : 'text_gateway_account_saved');
        } catch (\Throwable $e) {
            $this->safeLog('JOW gateway account settings could not be saved: ' . $e->getMessage());
            $this->session->data['error'] = $this->language->get('error_gateway_account_save');
        }
    }

    /**
     * Link de pagamento de um PEDIDO no ledger novo (ADR 0119).
     *
     * Regras de dinheiro (conta habilitada, valor, tipo de tentativa) ficam no
     * `OrderPaymentLink`; aqui só se monta o endereço público de retorno e de
     * aviso e se confere o que a tela pode pedir.
     */
    private function createOrderGatewayLink(int $orderId, string $provider): Link {
        if ($orderId <= 0 || !InstalledGatewayAdapters::isInstalled($provider)) {
            throw new \OutOfBoundsException('JOW order gateway link: invalid order or provider');
        }

        if (!$this->model_payments_jow->orderGatewaySchemaReady()) {
            throw new \RuntimeException('JOW-Payments core migration is not installed');
        }

        $order = $this->model_payments_jow->getOrderForGatewayLink($orderId);

        if ($order === []) {
            throw new \OutOfBoundsException('JOW order gateway link: order was not found');
        }

        $registry = $this->gatewayRegistry();
        $legacy = new DbLedger($this->db, DB_PREFIX);

        $linker = new OrderPaymentLink(
            $registry,
            $this->gatewayAccountStore($registry),
            new OrderGatewayLedger(new DbGatewayLedger($this->db, DB_PREFIX), $legacy),
            $this->livePaymentsAllowed()
        );

        return $linker->link(
            $provider,
            $orderId,
            (float)($order['total'] ?? 0),
            (string)($order['title'] ?? ''),
            $this->catalogRouteUrl('checkout/gateway.notify&gateway_id=' . $this->model_payments_jow->getPlatformGatewayId($provider)),
            $this->catalogRouteUrl('checkout/payment.pay'),
            (string)($order['email'] ?? '')
        );
    }

    private function createGatewayLink(int $chargeId, string $provider): GatewayPaymentRecord {
        if (!InstalledGatewayAdapters::isInstalled($provider)) {
            throw new \OutOfBoundsException('JOW gateway provider is not installed');
        }

        if (!$this->model_payments_jow->gatewaySchemaReady()) {
            throw new \RuntimeException('JOW-Payments core migration is not installed');
        }

        $charge = $this->model_payments_jow->getCharge($chargeId);
        if ($charge === [] || (string)($charge['method'] ?? '') !== $provider) {
            throw new \OutOfBoundsException('JOW gateway charge was not found for this provider');
        }

        $chargeStatus = (string)($charge['status'] ?? '');
        if (!in_array($chargeStatus, ['pending', 'canceled', 'expired'], true)) {
            throw new \DomainException('JOW gateway checkout cannot be created for a settled charge');
        }

        $registry = $this->gatewayRegistry();
        $store = $this->gatewayAccountStore($registry);
        $gatewayId = $this->model_payments_jow->getPlatformGatewayId($provider);
        $account = $gatewayId > 0 ? $store->find($gatewayId) : null;

        if (!$account instanceof GatewayAccount || $account->providerCode !== $provider || !$account->enabled) {
            throw new \RuntimeException('JOW ' . $provider . ' platform account is not enabled');
        }

        $credentials = $store->credentials($gatewayId);
        foreach (self::PROVIDER_SECRET_FIELDS[$provider] ?? [] as $field => $maxLength) {
            if (!$credentials->has($field)) {
                throw new \RuntimeException('JOW ' . $provider . ' platform credentials are incomplete');
            }
        }

        $adapter = $registry->get($provider);
        $methods = array_keys($adapter->capabilities()->methods());
        $method = $methods[0] ?? '';

        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $method) !== 1) {
            throw new \LogicException('JOW ' . $provider . ' adapter exposes no payment method');
        }

        $ledger = new DbGatewayLedger($this->db, DB_PREFIX);
        $latest = $ledger->latestForSource('jow_charge', $chargeId);
        $idempotencyKey = $this->nextIdempotencyKey($provider, $chargeId, $latest);
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
            $method,
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

    private function nextIdempotencyKey(string $provider, int $chargeId, ?GatewayPaymentRecord $latest): string {
        if ($latest === null) {
            return self::orderIdempotencyUuid($provider, 'charge:' . $chargeId . ':initial');
        }

        if (!in_array($latest->status, ['failed', 'cancelled', 'expired'], true)) {
            if (!in_array($latest->status, ['pending', 'requires_action', 'authorized'], true)) {
                throw new \DomainException('JOW gateway charge is already settled or under dispute');
            }

            if (preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $latest->idempotencyKey) !== 1) {
                throw new \LogicException('JOW gateway attempt has an unexpected idempotency key');
            }

            return $latest->idempotencyKey;
        }

        // Derive a stable UUID v5 from the previous persisted transaction. If
        // the provider created a payment but DB persistence failed, a retry
        // sees the same prior transaction and reuses exactly the same key.
        return self::orderIdempotencyUuid($provider, 'charge:' . $chargeId . ':after:' . $latest->transactionId);
    }

    private static function orderIdempotencyUuid(string $provider, string $name): string {
        $namespace = hex2bin('6ba7b8109dad11d180b400c04fd430c8'); // RFC 4122 DNS namespace
        if ($namespace === false) {
            throw new \LogicException('JOW gateway could not create an idempotency UUID');
        }

        // Mercado Pago keys keep their released derivation; new providers get
        // their own namespace so existing in-flight attempts stay reconcilable.
        $salt = $provider === 'mp' ? 'reamurcms:jow:mercadopago:' : 'reamurcms:jow:gateway:' . $provider . ':';

        $hex = substr(sha1($namespace . $salt . $name), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x03) | 0x08);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    private function gatewayState(DbGatewayAccountStore $store, string $code): array {
        $state = [
            'configured' => false,
            'enabled' => false,
            'environment' => 'sandbox',
            'credentials_locked' => false,
            'webhook_url' => '',
            'fields' => [],
        ];

        $fieldTexts = self::PROVIDER_FIELD_TEXTS[$code] ?? [];

        foreach (self::PROVIDER_SECRET_FIELDS[$code] ?? [] as $field => $maxLength) {
            $texts = $fieldTexts[$field] ?? ['entry' => '', 'help' => ''];
            $state['fields'][] = [
                'name' => $field,
                'entry' => $texts['entry'] !== '' ? (string)$this->language->get($texts['entry']) : $field,
                'help' => $texts['help'] !== '' ? (string)$this->language->get($texts['help']) : '',
                'maxlength' => $maxLength,
                'set' => false,
            ];
        }

        $gatewayId = $this->model_payments_jow->getPlatformGatewayId($code);

        if ($gatewayId > 0) {
            $account = $store->find($gatewayId);

            if ($account !== null && $account->providerCode === $code) {
                $state['configured'] = true;
                $state['enabled'] = $account->enabled;
                $state['environment'] = $account->environment;
                $state['webhook_url'] = $this->catalogRouteUrl('checkout/gateway.notify&gateway_id=' . $gatewayId);

                try {
                    $credentials = $store->credentials($gatewayId);

                    foreach ($state['fields'] as &$field) {
                        $field['set'] = $credentials->has($field['name']);
                    }
                    unset($field);
                } catch (\Throwable) {
                    $state['credentials_locked'] = true;
                }
            }
        }

        return $state;
    }

    private function gatewayRegistry(): GatewayRegistry {
        return InstalledGatewayAdapters::registry();
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
