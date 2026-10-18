<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/webhook — CRUD de endpoints de webhook + log de entregas (F2-16,
 * ADR 0016). ÚLTIMO módulo do backlog do schema — fecha a Fase 2 de CRUDs.
 * Validações na régua do Dicionário §10: trigger em sintaxe wildcard
 * (`order_add`, `order_*`, `*_add`), URL **somente https://** (dado sensível
 * em trânsito), secret HMAC 16–64 chars, UNIQUE(trigger,url) checada antes
 * do SQL, status coercido a 0/1 (POST adulterado não injeta valor). Delete
 * em cascata manual (endpoint + entregas). Secret mascarado na grade.
 * Rotas: /setting/webhook · .add · .edit|N · .delete|N (POST)
 * · .logs|N (0 = todas as entregas) · .log|N (payload read-only).
 */
namespace Reamur\Admin\Controller\Setting;

class Webhook extends \Reamur\System\Engine\Controller {

    /** Sugestões de trigger (engine in-memory + ponte system/webhook, Fase 5) */
    private const TRIGGER_SUGGESTIONS = ['order_add', 'order_edit', 'order_history_edit', 'contact_add', 'product_add', 'product_edit', 'product_delete', 'product_*', 'order_*'];

    /** Grade de endpoints */
    public function index(): void {
        $this->load->language('setting/webhook');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/webhook');

        $data['heading_title']       = $this->language->get('heading_title');
        $data['text_list']           = $this->language->get('text_list');
        $data['text_no_results']     = $this->language->get('text_no_results');
        $data['text_confirm_delete'] = $this->language->get('text_confirm_delete');
        $data['text_enabled']        = $this->language->get('text_enabled');
        $data['text_disabled']       = $this->language->get('text_disabled');
        $data['text_logs_all']       = $this->language->get('text_logs_all');
        $data['column_trigger']      = $this->language->get('column_trigger');
        $data['column_url']          = $this->language->get('column_url');
        $data['column_secret']       = $this->language->get('column_secret');
        $data['column_description']  = $this->language->get('column_description');
        $data['column_status']       = $this->language->get('column_status');
        $data['column_deliveries']   = $this->language->get('column_deliveries');
        $data['column_date_added']   = $this->language->get('column_date_added');
        $data['column_action']       = $this->language->get('column_action');
        $data['button_add']          = $this->language->get('button_add');
        $data['button_edit']         = $this->language->get('button_edit');
        $data['button_delete']       = $this->language->get('button_delete');
        $data['button_logs']         = $this->language->get('button_logs');
        $data['text_flash']          = $this->flash();

        $data['webhooks'] = [];

        foreach ($this->model_setting_webhook->getWebhooks() as $wh) {
            $wh   = (array)$wh;
            $wid  = (int)$wh['webhook_id'];

            $data['webhooks'][] = [
                'webhook_id'  => $wid,
                'trigger'     => (string)$wh['trigger'],
                'url'         => (string)$wh['url'],
                'secret_mask' => $this->maskSecret((string)$wh['secret']),
                'description' => (string)$wh['description'],
                'status'      => (int)$wh['status'],
                'log_count'   => (int)$wh['log_count'],
                'date_added'  => (string)$wh['date_added'],
                'logs'        => $this->url->link('setting/webhook.logs|' . $wid),
                'edit'        => $this->url->link('setting/webhook.edit|' . $wid),
                'delete'      => $this->url->link('setting/webhook.delete|' . $wid),
            ];
        }

        $data['add']      = $this->url->link('setting/webhook.add');
        $data['logs_all'] = $this->url->link('setting/webhook.logs|0');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('setting/webhook_list', $data));
    }

    /** GET: formulário de criação · POST: valida e persiste; sucesso → lista + flash */
    public function add(): void {
        if ($this->request->server['REQUEST_METHOD'] === 'POST' && !$this->user->hasPermission('modify', 'setting/webhook')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('setting/webhook');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/webhook');

        $data          = $this->formData([]);
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validateWebhook();

            if ($errors === []) {
                $webhook_id = $this->model_setting_webhook->addWebhook($this->postPayload());

                if ($webhook_id) {
                    $this->setFlash($this->language->get('text_success_add'));

                    $this->response->redirect($this->url->link('setting/webhook'));

                    return;
                }
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('setting/webhook.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('setting/webhook_form', $data));
    }

    /** GET: formulário de edição · POST: valida e persiste (args do Action são strings) */
    public function edit(string $webhook_id = '0'): void {
        $webhook_id = (int)$webhook_id;

        $this->load->language('setting/webhook');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/webhook');

        $webhook = $this->model_setting_webhook->getWebhook($webhook_id);

        if (!$webhook) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $data          = $this->formData($webhook);
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'setting/webhook')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $errors = $this->validateWebhook($webhook_id);

            if ($errors === []) {
                $this->model_setting_webhook->editWebhook($webhook_id, $this->postPayload());

                $this->setFlash($this->language->get('text_success_edit'));

                $this->response->redirect($this->url->link('setting/webhook'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('setting/webhook.edit|' . $webhook_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('setting/webhook_form', $data));
    }

    /** Exclui via POST (cascata manual: entregas + endpoint; GET jamais muta) */
    public function delete(string $webhook_id = '0'): void {
        $webhook_id = (int)$webhook_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'setting/webhook')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('setting/webhook');

        $this->load->model('setting/webhook');

        if ($this->model_setting_webhook->getWebhook($webhook_id)) {
            $this->model_setting_webhook->deleteWebhook($webhook_id);

            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('setting/webhook'));
    }

    /** Entregas recentes (id 0 = todas; filtro por endpoint via |N) */
    public function logs(string $webhook_id = '0'): void {
        $webhook_id = (int)$webhook_id;

        $this->load->language('setting/webhook');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/webhook');

        $filter = null;
        $webhook = null;

        if ($webhook_id > 0) {
            $webhook = $this->model_setting_webhook->getWebhook($webhook_id);

            if (!$webhook) {
                $this->response->redirect($this->url->link('error/not_found'));

                return;
            }

            $filter = $webhook_id;
        }

        $data['heading_title']    = $this->language->get('heading_title');
        $data['text_logs_title']  = $webhook
            ? sprintf($this->language->get('text_logs_of'), (string)$webhook['trigger'], '#' . $webhook_id)
            : $this->language->get('text_logs_all');
        $data['text_logs_hint']   = $this->language->get('text_logs_hint');
        $data['text_no_results']  = $this->language->get('text_no_logs');
        $data['column_delivery']  = $this->language->get('column_delivery');
        $data['column_trigger']   = $this->language->get('column_trigger');
        $data['column_http_code'] = $this->language->get('column_http_code');
        $data['column_duration']  = $this->language->get('column_duration');
        $data['column_attempt']   = $this->language->get('column_attempt');
        $data['column_date_added']= $this->language->get('column_date_added');
        $data['column_action']    = $this->language->get('column_action');
        $data['button_back']      = $this->language->get('button_back');
        $data['button_view']      = $this->language->get('button_view');
        $data['text_flash']       = $this->flash();

        $data['logs'] = [];

        foreach ($this->model_setting_webhook->getLogs($filter) as $log) {
            $log = (array)$log;
            $lid = (int)$log['webhook_log_id'];

            $data['logs'][] = [
                'webhook_log_id' => $lid,
                'delivery'       => (string)$log['delivery'],
                'trigger'        => (string)($log['trigger'] ?? ''),
                'http_code'      => (int)$log['http_code'],
                'duration'       => number_format((float)$log['duration'], 3),
                'attempt'        => (int)$log['attempt'],
                'date_added'     => (string)$log['date_added'],
                'ok'             => ((int)$log['http_code'] >= 200 && (int)$log['http_code'] < 300),
                'view'           => $this->url->link('setting/webhook.log|' . $lid),
            ];
        }

        $data['back'] = $this->url->link('setting/webhook');
        $data['logs_base'] = $this->url->link('setting/webhook.logs|0');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('setting/webhook_logs', $data));
    }

    /** Entrega individual: payload read-only (JSON pretty quando possível) */
    public function log(string $webhook_log_id = '0'): void {
        $webhook_log_id = (int)$webhook_log_id;

        $this->load->language('setting/webhook');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/webhook');

        $log = $this->model_setting_webhook->getLog($webhook_log_id);

        if (!$log) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $data['heading_title']     = $this->language->get('heading_title');
        $data['text_log_detail']   = $this->language->get('text_log_detail');
        $data['entry_delivery']    = $this->language->get('entry_delivery');
        $data['entry_trigger']     = $this->language->get('entry_trigger');
        $data['entry_url']         = $this->language->get('entry_url');
        $data['entry_http_code']   = $this->language->get('entry_http_code');
        $data['entry_duration']    = $this->language->get('entry_duration');
        $data['entry_attempt']     = $this->language->get('entry_attempt');
        $data['entry_date_added']  = $this->language->get('entry_date_added');
        $data['entry_payload']     = $this->language->get('entry_payload');
        $data['button_back']       = $this->language->get('button_back_logs');
        $data['text_flash']        = $this->flash();

        $payload = (string)$log['payload'];
        $decoded = json_decode($payload, true);

        $data['log'] = [
            'webhook_log_id' => $webhook_log_id,
            'webhook_id'     => (int)$log['webhook_id'],
            'delivery'       => (string)$log['delivery'],
            'trigger'        => (string)($log['trigger'] ?? ''),
            'url'            => (string)($log['url'] ?? ''),
            'http_code'      => (int)$log['http_code'],
            'ok'             => ((int)$log['http_code'] >= 200 && (int)$log['http_code'] < 300),
            'duration'       => number_format((float)$log['duration'], 3),
            'attempt'        => (int)$log['attempt'],
            'date_added'     => (string)$log['date_added'],
            'payload'        => is_array($decoded)
                ? (string)json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : $payload,
        ];

        $data['back'] = $this->url->link('setting/webhook.logs|' . (int)$log['webhook_id']);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('setting/webhook_log', $data));
    }

    /**
     * Campos do form (repovo com POST em erro).
     *
     * @param array<string, mixed> $webhook
     * @return array<string, mixed>
     */
    private function formData(array $webhook): array {
        $post = $this->request->post;

        return [
            'heading_title'      => $this->language->get('heading_title'),
            'text_form'          => $this->language->get('text_form'),
            'text_trigger_hint'  => $this->language->get('text_trigger_hint'),
            'text_url_hint'      => $this->language->get('text_url_hint'),
            'text_secret_hint'   => $this->language->get('text_secret_hint'),
            'text_status_hint'   => $this->language->get('text_status_hint'),
            'entry_trigger'      => $this->language->get('entry_trigger'),
            'entry_url'          => $this->language->get('entry_url'),
            'entry_secret'       => $this->language->get('entry_secret'),
            'entry_description'  => $this->language->get('entry_description'),
            'entry_status'       => $this->language->get('entry_status'),
            'button_save'        => $this->language->get('button_save'),
            'button_cancel'      => $this->language->get('button_cancel'),
            'button_generate'    => $this->language->get('button_generate'),
            'trigger_suggestions' => self::TRIGGER_SUGGESTIONS,
            'value_trigger'      => (string)($post['trigger'] ?? ($webhook['trigger'] ?? '')),
            'value_url'          => (string)($post['url'] ?? ($webhook['url'] ?? '')),
            'value_secret'       => (string)($post['secret'] ?? ($webhook['secret'] ?? '')),
            'value_description'  => (string)($post['description'] ?? ($webhook['description'] ?? '')),
            'value_status'       => $post ? (int)($post['status'] ?? 0) : (int)($webhook['status'] ?? 1),
            'cancel'             => $this->url->link('setting/webhook'),
        ];
    }

    /**
     * Régua do Dicionário §10: trigger wildcard válido · URL https:// ·
     * secret 16–64 · description 1–255 · UNIQUE(trigger,url).
     *
     * @return array<int, string>
     */
    private function validateWebhook(int $except_webhook_id = 0): array {
        $post   = $this->request->post;
        $errors = [];

        $trigger = strtolower(trim((string)($post['trigger'] ?? '')));
        $url     = trim((string)($post['url'] ?? ''));
        $secret  = trim((string)($post['secret'] ?? ''));
        $descr   = trim((string)($post['description'] ?? ''));

        if ($trigger === '' || mb_strlen($trigger) > 64 || !preg_match('/^\*{0,1}[a-z0-9_]{1,63}(\.\*)?$/u', $trigger)) {
            $errors[] = $this->language->get('error_trigger');
        }

        if ($url === '' || mb_strlen($url) > 255 || !str_starts_with($url, 'https://')) {
            $errors[] = $this->language->get('error_url_https');
        }

        if (mb_strlen($secret) < 16 || mb_strlen($secret) > 64) {
            $errors[] = $this->language->get('error_secret');
        }

        if ($descr === '' || mb_strlen($descr) > 255) {
            $errors[] = $this->language->get('error_description');
        }

        if ($errors === [] && $this->model_setting_webhook->existsTriggerUrl($trigger, $url, $except_webhook_id)) {
            $errors[] = $this->language->get('error_duplicate');
        }

        return $errors;
    }

    /**
     * @return array{trigger: string, url: string, secret: string, description: string, status: int}
     */
    private function postPayload(): array {
        $post = $this->request->post;

        return [
            'trigger'     => strtolower(trim((string)($post['trigger'] ?? ''))),
            'url'         => trim((string)($post['url'] ?? '')),
            'secret'      => trim((string)($post['secret'] ?? '')),
            'description' => trim((string)($post['description'] ?? '')),
            // coerção dura: qualquer valor adulterado vira 0/1
            'status'      => !empty($post['status']) && (int)$post['status'] === 1 ? 1 : 0,
        ];
    }

    /** 'abcd1234xyz' → '••••••••3xyz' */
    private function maskSecret(string $secret): string {
        return ($secret === '') ? '' : str_repeat('•', 8) . substr($secret, -4);
    }

    /** Leia-uma-vez (consume) do flash da sessão — mesmo helper dos demais CRUDs */
    private function flash(): array {
        $session = $this->session;

        $data = ['type' => '', 'text' => ''];

        if ($session instanceof \Reamur\System\Library\Session && !empty($session->data['flash'])) {
            $data         = (array)$session->data['flash'];
            $data['type'] = (string)($data['type'] ?? 'success');
            $data['text'] = (string)($data['text'] ?? '');

            unset($session->data['flash']);
        }

        return $data;
    }

    private function setFlash(string $text, string $type = 'success'): void {
        $session = $this->session;

        if ($session instanceof \Reamur\System\Library\Session) {
            $session->data['flash'] = ['type' => $type, 'text' => $text];
        }
    }

    /** Chrome compartilhado + estilo do módulo */
    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
