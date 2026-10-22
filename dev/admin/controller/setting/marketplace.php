<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/marketplace (ADMIN) — fundação do Mercado Pago Marketplace
 * (F5-02, ADR 0034):
 *  · index     → tela única: configuração do app (app_id/client_secret/env)
 *                persistida em rms_setting (NUNCA no git) + grade de
 *                vendedores conectados via OAuth;
 *  · save      → POST: valida/persiste mp_* (client_secret vazio = manter);
 *  · authorize → inicia OAuth: gera state aleatório amarrado à sessão e
 *                redireciona à URL de autorização do MP;
 *  · callback  → retorno do MP (GET ?code&state): valida state com
 *                hash_equals, troca code→tokens, enriquece com /users/me e
 *                faz upsert por mp_user_id (reconectar ≠ duplicar);
 *  · refresh   → POST: rotaciona tokens do vendedor via refresh_token;
 *  · delete    → POST: desconecta LOCALMENTE (revogação é no painel do MP);
 *  · paylink   → POST|ORDER: gera preference c/ marketplace_fee (F5-03,
 *                ADR 0035) e devolve link público checkout/payment.pay|TOKEN.
 * CSRF admin: padrão dos CRUDs da casa — guarda = sessão + hasPermission
 * (decisão declarada no ADR 0034 §5; coerente com F2/F3/F4).
 */
namespace Reamur\Admin\Controller\Setting;

class Marketplace extends \Reamur\System\Engine\Controller {

    /** Tela única (config + vendedores) */
    public function index(): void {
        $this->load->language('setting/marketplace');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('setting/marketplace');

        $settings = $this->model_setting_setting->getSettings();

        $mp = $this->mpFromSettings($settings);

        $sellerFilter = [
            'search' => mb_substr($this->queryValue('filter_seller'), 0, 128),
            'status' => $this->queryValue('filter_seller_status') === '' ? 'all' : $this->queryValue('filter_seller_status'),
        ];
        if (!in_array($sellerFilter['status'], ['all', '0', '1'], true)) { $sellerFilter['status'] = 'all'; }
        $sellerLimit = 10;
        $sellerPage = max(1, (int)$this->queryValue('seller_page'));
        $sellerTotal = $this->model_setting_marketplace->getTotalSellers($sellerFilter);
        $sellerPage = min($sellerPage, max(1, (int)ceil($sellerTotal / $sellerLimit)));
        $sellerFilter['start'] = ($sellerPage - 1) * $sellerLimit;
        $sellerFilter['limit'] = $sellerLimit;

        $paymentFilter = [
            'order_id' => max(0, (int)$this->queryValue('filter_order_id')),
            'customer' => mb_substr($this->queryValue('filter_customer'), 0, 96),
            'status' => ($this->queryValue('filter_payment_status') ?: 'all'),
        ];
        if (!in_array($paymentFilter['status'], ['all', 'pending', 'approved', 'rejected', 'cancelled', 'refunded', 'in_process'], true)) { $paymentFilter['status'] = 'all'; }
        $paymentLimit = 20;
        $paymentPage = max(1, (int)$this->queryValue('payment_page'));
        $paymentTotal = $this->model_setting_marketplace->getTotalPayments($paymentFilter);
        $paymentPage = min($paymentPage, max(1, (int)ceil($paymentTotal / $paymentLimit)));
        $paymentFilter['start'] = ($paymentPage - 1) * $paymentLimit;
        $paymentFilter['limit'] = $paymentLimit;

        require_once DIR_SYSTEM . 'library/pagination.php';
        $sellerPagination = new \Pagination();
        $sellerPagination->total = $sellerTotal;
        $sellerPagination->page = $sellerPage;
        $sellerPagination->limit = $sellerLimit;
        $sellerQuery = 'seller_page={page}';
        if ($sellerFilter['search'] !== '') { $sellerQuery .= '&filter_seller=' . rawurlencode($sellerFilter['search']); }
        if ($sellerFilter['status'] !== 'all') { $sellerQuery .= '&filter_seller_status=' . $sellerFilter['status']; }
        $sellerPagination->url = $this->url->link('setting/marketplace', $sellerQuery);
        $sellerPagination->css_class = 'pagination pagination-sm mb-0';
        $sellerPagination->text_first = $this->language->get('text_page_first');
        $sellerPagination->text_last = $this->language->get('text_page_last');
        $sellerPagination->text_next = $this->language->get('text_page_next');
        $sellerPagination->text_prev = $this->language->get('text_page_prev');

        $paymentPagination = new \Pagination();
        $paymentPagination->total = $paymentTotal;
        $paymentPagination->page = $paymentPage;
        $paymentPagination->limit = $paymentLimit;
        $paymentQuery = 'payment_page={page}';
        if ($paymentFilter['order_id'] > 0) { $paymentQuery .= '&filter_order_id=' . $paymentFilter['order_id']; }
        if ($paymentFilter['customer'] !== '') { $paymentQuery .= '&filter_customer=' . rawurlencode($paymentFilter['customer']); }
        if ($paymentFilter['status'] !== 'all') { $paymentQuery .= '&filter_payment_status=' . rawurlencode($paymentFilter['status']); }
        $paymentPagination->url = $this->url->link('setting/marketplace', $paymentQuery);
        $paymentPagination->css_class = 'pagination pagination-sm mb-0';
        $paymentPagination->text_first = $this->language->get('text_page_first');
        $paymentPagination->text_last = $this->language->get('text_page_last');
        $paymentPagination->text_next = $this->language->get('text_page_next');
        $paymentPagination->text_prev = $this->language->get('text_page_prev');

        $rawPayments = $this->model_setting_marketplace->getPayments($paymentFilter);
        $payments = array_map(function (array $payment): array {
            $payment['pay_url'] = $this->publicPayUrl((string)$payment['public_token']);
            return $payment;
        }, $rawPayments);

        $data = [
            'heading_title'        => $this->language->get('heading_title'),
            'text_config'          => $this->language->get('text_config'),
            'text_sellers'         => $this->language->get('text_sellers'),
            'text_filter'          => $this->language->get('text_filter'),
            'text_close'           => $this->language->get('text_close'),
            'text_showing'         => $this->language->get('text_showing'),
            'text_all_status'      => $this->language->get('text_all_status'),
            'text_active'          => $this->language->get('text_active'),
            'text_inactive'        => $this->language->get('text_inactive'),
            'text_payment_pending' => $this->language->get('text_payment_pending'),
            'text_payment_approved' => $this->language->get('text_payment_approved'),
            'text_payment_rejected' => $this->language->get('text_payment_rejected'),
            'text_payment_cancelled' => $this->language->get('text_payment_cancelled'),
            'text_payment_refunded' => $this->language->get('text_payment_refunded'),
            'text_payment_in_process' => $this->language->get('text_payment_in_process'),
            'entry_filter_seller'  => $this->language->get('entry_filter_seller'),
            'entry_filter_seller_status' => $this->language->get('entry_filter_seller_status'),
            'entry_filter_order_id' => $this->language->get('entry_filter_order_id'),
            'entry_filter_customer' => $this->language->get('entry_filter_customer'),
            'entry_filter_payment_status' => $this->language->get('entry_filter_payment_status'),
            'button_filter'        => $this->language->get('button_filter'),
            'button_reset'         => $this->language->get('button_reset'),
            'text_no_sellers'      => $this->language->get('text_no_sellers'),
            'text_not_configured'  => $this->language->get('text_not_configured'),
            'text_env'             => $this->language->get('text_env'),
            'text_env_test'        => $this->language->get('text_env_test'),
            'text_env_prod'        => $this->language->get('text_env_prod'),
            'text_secret_hint'     => $this->language->get('text_secret_hint'),
            'text_redirect_hint'   => $this->language->get('text_redirect_hint'),
            'text_disconnect_note' => $this->language->get('text_disconnect_note'),
            'text_confirm_disconnect' => $this->language->get('text_confirm_disconnect'),
            'can_modify'           => $this->user->hasPermission('modify', 'setting/marketplace'),
            'text_flash'           => $this->flash(),
            'csrf_token'           => \Reamur\System\Library\Security\Csrf::token($this->session),
            'entry_app_id'         => $this->language->get('entry_app_id'),
            'entry_client_secret'  => $this->language->get('entry_client_secret'),
            'entry_redirect_uri'   => $this->language->get('entry_redirect_uri'),
            'column_mp_user'       => $this->language->get('column_mp_user'),
            'column_nickname'      => $this->language->get('column_nickname'),
            'column_email'         => $this->language->get('column_email'),
            'column_expires'       => $this->language->get('column_expires'),
            'column_scope'         => $this->language->get('column_scope'),
            'column_status'        => $this->language->get('column_status'),
            'column_action'        => $this->language->get('column_action'),
            'button_save'          => $this->language->get('button_save'),
            'button_connect'       => $this->language->get('button_connect'),
            'button_refresh'       => $this->language->get('button_refresh'),
            'button_disconnect'    => $this->language->get('button_disconnect'),
            'mp_app_id'            => (string)($settings['mp_app_id'] ?? ''),
            'mp_has_secret'        => trim((string)($settings['mp_client_secret'] ?? '')) !== '',
            'mp_env'               => (string)($settings['mp_env'] ?? 'test'),
            'mp_redirect_uri'      => (string)($settings['mp_redirect_uri'] ?? ''),
            'mp_configured'        => $mp->isConfigured(),
            'text_paylinks'        => $this->language->get('text_paylinks'),
            'text_no_payments'     => $this->language->get('text_no_payments'),
            'text_fee_current'     => sprintf($this->language->get('text_fee_current'), (string)($settings['mp_marketplace_fee_percent'] ?? '10')),
            'column_order'         => $this->language->get('column_order'),
            'column_customer'      => $this->language->get('column_customer'),
            'column_amount'        => $this->language->get('column_amount'),
            'column_fee'           => $this->language->get('column_fee'),
            'column_seller'        => $this->language->get('column_seller'),
            'column_payment_status'=> $this->language->get('column_payment_status'),
            'column_pay_link'      => $this->language->get('column_pay_link'),
            'button_copy'          => $this->language->get('button_copy'),
            'payments'             => $payments,
            'filter_action' => $this->url->link('setting/marketplace'),
            'seller_filter_search' => $sellerFilter['search'],
            'seller_filter_status' => $sellerFilter['status'],
            'payment_filter_order_id' => $paymentFilter['order_id'],
            'payment_filter_customer' => $paymentFilter['customer'],
            'payment_filter_status' => $paymentFilter['status'],
            'seller_total' => $sellerTotal,
            'seller_showing' => $sellerTotal > 0 ? sprintf($this->language->get('text_showing'), (($sellerPage - 1) * $sellerLimit) + 1, min($sellerPage * $sellerLimit, $sellerTotal), $sellerTotal) : '',
            'seller_pagination' => $sellerPagination->render(),
            'payment_total' => $paymentTotal,
            'payment_showing' => $paymentTotal > 0 ? sprintf($this->language->get('text_showing'), (($paymentPage - 1) * $paymentLimit) + 1, min($paymentPage * $paymentLimit, $paymentTotal), $paymentTotal) : '',
            'payment_pagination' => $paymentPagination->render(),
            'action_save'          => $this->url->link('setting/marketplace.save'),
            'action_connect'       => $this->url->link('setting/marketplace.authorize'),
            'action_refresh'       => $this->url->link('setting/marketplace.refresh'),
            'action_delete'        => $this->url->link('setting/marketplace.delete'),
            'sellers'              => $this->model_setting_marketplace->getSellers($sellerFilter),
        ];

        $this->chrome($data);

        $this->response->setOutput($this->load->view('setting/marketplace', $data));
    }

    /** POST: valida e persiste a configuração do app (sem credencial no git) */
    public function save(): void {
        $this->load->language('setting/marketplace');

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'setting/marketplace')) {
            $this->response->redirect($this->url->link('error/permission'));
            return;
        }
        if (!$this->csrfValid()) {
            $this->session->data['flash'] = ['type' => 'danger', 'text' => $this->language->get('error_csrf')];
            $this->response->redirect($this->url->link('setting/marketplace'));
            return;
        }

        $this->load->model('setting/setting');

        $settings = $this->model_setting_setting->getSettings();

        $app_id        = trim((string)($this->request->post['mp_app_id'] ?? ''));
        $client_secret = trim((string)($this->request->post['mp_client_secret'] ?? ''));
        $env           = (string)($this->request->post['mp_env'] ?? 'test');
        $redirect_uri  = trim((string)($this->request->post['mp_redirect_uri'] ?? ''));

        $errors = [];

        if ($app_id !== '' && !preg_match('/^\d{6,}$/', $app_id)) {
            $errors[] = $this->language->get('error_app_id');
        }

        if (!in_array($env, ['test', 'prod'], true)) {
            $env = 'test';
        }

        if ($redirect_uri !== '' && !preg_match('#^https://#i', $redirect_uri)) {
            $errors[] = $this->language->get('error_redirect_uri');
        }

        if ($client_secret !== '' && strlen($client_secret) < 16) {
            $errors[] = $this->language->get('error_client_secret');
        }

        if ($errors) {
            $this->session->data['flash'] = ['type' => 'danger', 'text' => implode("\n", $errors)];
        } else {
            $save = [
                'mp_app_id'       => $app_id,
                'mp_env'          => $env,
                'mp_redirect_uri' => $redirect_uri,
            ];

            // Segredo vazio = MANTER o gravado (campo nunca volta preenchido ao GET)
            if ($client_secret !== '') {
                $save['mp_client_secret'] = $client_secret;
            }

            $this->model_setting_setting->editSettings($save);

            $this->session->data['flash'] = ['type' => 'success', 'text' => $this->language->get('text_success')];
        }

        $this->response->redirect($this->url->link('setting/marketplace'));
    }

    /** Inicia o OAuth do vendedor (state aleatório preso à sessão admin) */
    public function authorize(): void {
        $this->load->language('setting/marketplace');

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'setting/marketplace')) {
            $this->response->redirect($this->url->link('error/permission'));
            return;
        }
        if (!$this->csrfValid()) {
            $this->session->data['flash'] = ['type' => 'danger', 'text' => $this->language->get('error_csrf')];
            $this->response->redirect($this->url->link('setting/marketplace'));
            return;
        }

        $this->load->model('setting/setting');

        $mp = $this->mpFromSettings($this->model_setting_setting->getSettings());

        if (!$mp->isConfigured()) {
            $this->session->data['flash'] = ['type' => 'warning', 'text' => $this->language->get('error_not_configured')];

            $this->response->redirect($this->url->link('setting/marketplace'));

            return;
        }

        $state = bin2hex(random_bytes(16));

        $this->session->data['mp_oauth_state']    = $state;
        $this->session->data['mp_oauth_state_at'] = time();

        $this->response->redirect($mp->authorizationUrl($state));
    }

    /** Retorno do MP: valida state, troca code→tokens, upsert do vendedor */
    public function callback(): void {
        $this->load->language('setting/marketplace');

        if (!$this->user->hasPermission('access', 'setting/marketplace')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->model('setting/setting');
        $this->load->model('setting/marketplace');

        $expected = (string)($this->session->data['mp_oauth_state'] ?? '');
        $received = (string)($this->request->get['state'] ?? '');
        $code     = (string)($this->request->get['code'] ?? '');

        unset($this->session->data['mp_oauth_state'], $this->session->data['mp_oauth_state_at']);

        if ($expected === '' || !hash_equals($expected, $received)) {
            $this->session->data['flash'] = ['type' => 'danger', 'text' => $this->language->get('error_state')];

            $this->response->redirect($this->url->link('setting/marketplace'));

            return;
        }

        $mp = $this->mpFromSettings($this->model_setting_setting->getSettings());

        $result = $mp->exchangeAuthorizationCode($code);

        if (!empty($result['error']) || empty($result['access_token']) || empty($result['user_id'])) {
            $this->session->data['flash'] = [
                'type' => 'danger',
                'text' => sprintf($this->language->get('error_exchange'), $mp->extractErrorMessage($result)),
            ];

            $this->response->redirect($this->url->link('setting/marketplace'));

            return;
        }

        $expires_in = (int)($result['expires_in'] ?? 0);
        $info       = $mp->getUserInfo((string)$result['access_token']);

        $this->model_setting_marketplace->upsertSeller([
            'mp_user_id'       => (int)$result['user_id'],
            'nickname'         => $info['nickname'],
            'email'            => $info['email'],
            'access_token'     => (string)$result['access_token'],
            'refresh_token'    => (string)($result['refresh_token'] ?? ''),
            'scope'            => (string)($result['scope'] ?? ''),
            'token_expires_at' => date('Y-m-d H:i:s', time() + max(60, $expires_in)),
        ]);

        $this->session->data['flash'] = [
            'type' => 'success',
            'text' => sprintf($this->language->get('text_connected'), (int)$result['user_id']),
        ];

        $this->response->redirect($this->url->link('setting/marketplace'));
    }

    /** POST|id: rotaciona tokens do vendedor via refresh_token */
    public function refresh(string $mp_seller_id = '0'): void {
        $this->load->language('setting/marketplace');

        $mp_seller_id = (int)$mp_seller_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'setting/marketplace')) {
            $this->response->redirect($this->url->link('error/permission'));
            return;
        }
        if (!$this->csrfValid()) {
            $this->session->data['flash'] = ['type' => 'danger', 'text' => $this->language->get('error_csrf')];
            $this->response->redirect($this->url->link('setting/marketplace'));
            return;
        }

        $this->load->model('setting/setting');
        $this->load->model('setting/marketplace');

        $seller = $this->model_setting_marketplace->getSeller($mp_seller_id);

        if (!$seller) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $mp = $this->mpFromSettings($this->model_setting_setting->getSettings());

        $result = $mp->refreshSellerToken((string)$seller['refresh_token']);

        if (!empty($result['error']) || empty($result['access_token'])) {
            $this->session->data['flash'] = [
                'type' => 'danger',
                'text' => sprintf($this->language->get('error_refresh'), $mp->extractErrorMessage($result)),
            ];
        } else {
            $expires_in = (int)($result['expires_in'] ?? 0);

            $this->model_setting_marketplace->rotateTokens(
                $mp_seller_id,
                (string)$result['access_token'],
                (string)($result['refresh_token'] ?? $seller['refresh_token']),
                date('Y-m-d H:i:s', time() + max(60, $expires_in))
            );

            $this->session->data['flash'] = ['type' => 'success', 'text' => $this->language->get('text_refreshed')];
        }

        $this->response->redirect($this->url->link('setting/marketplace'));
    }

    /** POST|id: desconecta LOCALMENTE (revogação = painel do vendedor no MP) */
    public function delete(string $mp_seller_id = '0'): void {
        $this->load->language('setting/marketplace');

        $mp_seller_id = (int)$mp_seller_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'setting/marketplace')) {
            $this->response->redirect($this->url->link('error/permission'));
            return;
        }
        if (!$this->csrfValid()) {
            $this->session->data['flash'] = ['type' => 'danger', 'text' => $this->language->get('error_csrf')];
            $this->response->redirect($this->url->link('setting/marketplace'));
            return;
        }

        $this->load->model('setting/marketplace');

        $this->model_setting_marketplace->deleteSeller($mp_seller_id);

        $this->session->data['flash'] = ['type' => 'success', 'text' => $this->language->get('text_deleted')];

        $this->response->redirect($this->url->link('setting/marketplace'));
    }

    /**
     * POST|ORDER_ID — gera o link de pagamento (preference no MP com split).
     * Re-uso: pedido já com tentativa pending devolve o MESMO link (não cria
     * preference duplicada no MP); pedido já approved/rejected não regenera.
     */
    public function paylink(string $order_id = '0'): void {
        $this->load->language('setting/marketplace');

        $order_id = (int)$order_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'setting/marketplace')) {
            $this->response->redirect($this->url->link('error/permission'));
            return;
        }
        if (!$this->csrfValid()) {
            $this->session->data['flash'] = ['type' => 'danger', 'text' => $this->language->get('error_csrf')];
            $this->response->redirect($this->url->link('setting/marketplace'));
            return;
        }

        $this->load->model('sale/order');
        $this->load->model('setting/setting');
        $this->load->model('setting/marketplace');

        $order = $this->model_sale_order->getOrder($order_id);

        if (!$order) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $settings = $this->model_setting_setting->getSettings();

        $existing = $this->model_setting_marketplace->getPaymentByOrder($order_id);

        if ($existing && (string)$existing['status'] === 'approved') {
            $this->session->data['flash'] = ['type' => 'warning', 'text' => sprintf($this->language->get('error_already_paid'), $order_id)];

            $this->response->redirect($this->url->link('setting/marketplace'));

            return;
        }

        if ($existing && (string)$existing['status'] === 'pending' && (string)($existing['init_point'] ?? '') !== '') {
            $this->session->data['flash'] = [
                'type' => 'info',
                'text' => sprintf($this->language->get('text_paylink_reused'), $this->publicPayUrl((string)$existing['public_token'])),
            ];

            $this->response->redirect($this->url->link('setting/marketplace'));

            return;
        }

        $seller = $this->model_setting_marketplace->getActiveSeller();

        if (!$seller) {
            $this->session->data['flash'] = ['type' => 'warning', 'text' => $this->language->get('error_no_seller')];

            $this->response->redirect($this->url->link('setting/marketplace'));

            return;
        }

        $amount = round((float)$order['total'], 2);

        if ($amount <= 0) {
            $this->session->data['flash'] = ['type' => 'warning', 'text' => sprintf($this->language->get('error_no_total'), $order_id)];

            $this->response->redirect($this->url->link('setting/marketplace'));

            return;
        }

        $percent = (float)str_replace(',', '.', (string)($settings['mp_marketplace_fee_percent'] ?? '10'));
        $percent = max(0.0, min(100.0, $percent));
        $fee     = round($amount * $percent / 100, 2);

        $mp = $this->mpFromSettings($settings);

        $preference = $mp->createPreference([
            'items' => [[
                'title'       => sprintf($this->language->get('text_item_title'), $order_id),
                'quantity'    => 1,
                'currency_id' => 'BRL',
                'unit_price'  => $amount,
            ]],
            'marketplace_fee'    => $fee,
            'external_reference' => (string)$order_id,
            'notification_url'   => $this->catalogUrl('checkout/payment.notify'),
        ], (string)$seller['access_token']);

        $preference_id = (string)($preference['id'] ?? '');
        $init_point    = (string)($preference['init_point'] ?? $preference['sandbox_init_point'] ?? '');

        if ((int)($preference['_http_status'] ?? 0) < 200 || (int)($preference['_http_status'] ?? 0) >= 300 || $preference_id === '' || $init_point === '') {
            $this->session->data['flash'] = [
                'type' => 'danger',
                'text' => sprintf($this->language->get('error_preference'), $mp->extractErrorMessage($preference)),
            ];

            $this->response->redirect($this->url->link('setting/marketplace'));

            return;
        }

        $this->model_setting_marketplace->addPayment([
            'order_id'        => $order_id,
            'mp_seller_id'    => (int)$seller['mp_seller_id'],
            'preference_id'   => $preference_id,
            'public_token'    => bin2hex(random_bytes(24)),
            'amount'          => $amount,
            'marketplace_fee' => $fee,
            'init_point'      => $init_point,
        ]);

        $public = $this->publicPayUrl(
            (string)$this->model_setting_marketplace->getPaymentByOrder($order_id)['public_token']
        );

        $this->session->data['flash'] = ['type' => 'success', 'text' => sprintf($this->language->get('text_paylink_created'), $public)];

        $this->response->redirect($this->url->link('setting/marketplace'));
    }

    /** Link público do cliente (catálogo — sem sessão) */
    private function publicPayUrl(string $token): string {
        return $this->catalogUrl('checkout/payment.pay|' . $token);
    }

    /**
     * Base pública do CATÁLOGO a partir do admin: site_url da admin termina em
     * /admin/ (HTTP_SERVER do wizard); o catálogo mora em /catalog/ (mesma
     * convenção do Contrato-Bootstrap). Nunca usar site_url "cru" aqui:
     * apontaria o cliente para a aplicação admin (bug da revisão F5-03).
     */
    private function catalogUrl(string $route): string {
        $base = rtrim((string)$this->config->get('site_url'), '/');

        if (str_ends_with($base, '/admin')) {
            $base = substr($base, 0, -6);
        }

        return $base . '/catalog/index.php?route=' . $route;
    }

    // ── internos ──────────────────────────────────────────────────────────

    /** Monta a library OAuth a partir de rms_setting (credenciais fora do git) */
    private function mpFromSettings(array $settings): \Reamur\System\Library\MercadopagoMarketplace {
        return new \Reamur\System\Library\MercadopagoMarketplace([
            'app_id'        => (string)($settings['mp_app_id'] ?? ''),
            'client_secret' => (string)($settings['mp_client_secret'] ?? ''),
            'redirect_uri'  => (string)($settings['mp_redirect_uri'] ?? ''),
            // Costura de ambiente p/ baterias e2e (ADR 0034 §4): existir só se
            // alguém gravar as chaves-opcionais — fora das telas e do seed.
            'token_url'     => (string)($settings['mp_token_url'] ?? ''),
            'users_me_url'  => (string)($settings['mp_users_me_url'] ?? ''),
            // F5-03 (ADR 0035 §3): base /checkout/preferences + /v1/payments
            'api_url'       => (string)($settings['mp_api_url'] ?? ''),
        ]);
    }

    private function flash(): array {
        $flash = $this->session->data['flash'] ?? ['type' => '', 'text' => ''];

        unset($this->session->data['flash']);

        return ['type' => (string)($flash['type'] ?? ''), 'text' => (string)($flash['text'] ?? '')];
    }

    /** Lê valores escalares da querystring sem converter arrays para string. */
    private function queryValue(string $key): string {
        $value = $this->request->get[$key] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function csrfValid(): bool {
        return \Reamur\System\Library\Security\Csrf::check(
            $this->session,
            (string)($this->request->post['csrf_token'] ?? '')
        );
    }

    /** Chrome compartilhado (mesmo padrão dos demais módulos admin) */
    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
