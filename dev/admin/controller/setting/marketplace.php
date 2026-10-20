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
 *  · delete    → POST: desconecta LOCALMENTE (revogação é no painel do MP).
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

        $data = [
            'heading_title'        => $this->language->get('heading_title'),
            'text_config'          => $this->language->get('text_config'),
            'text_sellers'         => $this->language->get('text_sellers'),
            'text_no_sellers'      => $this->language->get('text_no_sellers'),
            'text_not_configured'  => $this->language->get('text_not_configured'),
            'text_env'             => $this->language->get('text_env'),
            'text_env_test'        => $this->language->get('text_env_test'),
            'text_env_prod'        => $this->language->get('text_env_prod'),
            'text_secret_hint'     => $this->language->get('text_secret_hint'),
            'text_redirect_hint'   => $this->language->get('text_redirect_hint'),
            'text_disconnect_note' => $this->language->get('text_disconnect_note'),
            'text_flash'           => $this->flash(),
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
            'action_save'          => $this->url->link('setting/marketplace.save'),
            'action_connect'       => $this->url->link('setting/marketplace.authorize'),
            'action_refresh'       => $this->url->link('setting/marketplace.refresh'),
            'action_delete'        => $this->url->link('setting/marketplace.delete'),
            'sellers'              => $this->model_setting_marketplace->getSellers(),
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

        if (!$this->user->hasPermission('modify', 'setting/marketplace')) {
            $this->response->redirect($this->url->link('error/permission'));

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

        $this->load->model('setting/marketplace');

        $this->model_setting_marketplace->deleteSeller($mp_seller_id);

        $this->session->data['flash'] = ['type' => 'success', 'text' => $this->language->get('text_deleted')];

        $this->response->redirect($this->url->link('setting/marketplace'));
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
        ]);
    }

    private function flash(): array {
        $flash = $this->session->data['flash'] ?? ['type' => '', 'text' => ''];

        unset($this->session->data['flash']);

        return ['type' => (string)($flash['type'] ?? ''), 'text' => (string)($flash['text'] ?? '')];
    }

    /** Chrome compartilhado (mesmo padrão dos demais módulos admin) */
    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
