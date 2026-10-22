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

    /** Sugestões de trigger dos eventos implementados no F5-01 + triggers-futuros
     *  (o disparo real vive em Library\Webhook\Dispatcher — ADR 0033; a ponte
     *  legada 'system/webhook' da Fase 0 foi removida no F6-01 — ADR 0037). */
    private const TRIGGER_SUGGESTIONS = ['order_add', 'order_edit', 'order_history_edit', 'contact_add', 'product_add', 'product_edit', 'product_delete', 'product_*', 'order_*'];

    /** Grade de endpoints com filtros e paginação server-side. */
    public function index(): void {
        $this->load->language('setting/webhook');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/webhook');

        $filter = [
            'trigger' => mb_substr($this->queryValue('filter_trigger'), 0, 64),
            'url' => mb_substr($this->queryValue('filter_url'), 0, 255),
            'status' => $this->queryValue('filter_status') === '' ? 'all' : $this->queryValue('filter_status'),
        ];
        if (!in_array($filter['status'], ['all', '0', '1'], true)) { $filter['status'] = 'all'; }
        $page = max(1, (int)$this->queryValue('page'));
        $limit = 20;
        $total = $this->model_setting_webhook->getTotalWebhooks($filter);
        $page = min($page, max(1, (int)ceil($total / $limit)));
        $filter['start'] = ($page - 1) * $limit;
        $filter['limit'] = $limit;

        foreach ([
            'heading_title', 'text_list', 'text_close', 'text_no_results', 'text_confirm_delete',
            'text_enabled', 'text_disabled', 'text_logs_all', 'column_trigger',
            'column_url', 'column_secret', 'column_description', 'column_status',
            'column_deliveries', 'column_date_added', 'column_action', 'button_add',
            'button_edit', 'button_delete', 'button_logs', 'text_filter',
            'entry_filter_trigger', 'entry_filter_url', 'text_all_status',
            'button_filter', 'button_reset', 'text_showing', 'text_secret_unset',
            'text_secret_badge_set', 'text_secret_badge_unset',
        ] as $key) { $data[$key] = $this->language->get($key); }
        $data['text_flash'] = $this->flash();
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['can_modify'] = $this->user->hasPermission('modify', 'setting/webhook');
        $data['filter_trigger'] = $filter['trigger'];
        $data['filter_url'] = $filter['url'];
        $data['filter_status'] = $filter['status'];
        $data['filter_action'] = $this->url->link('setting/webhook');
        $data['webhooks'] = [];
        foreach ($this->model_setting_webhook->getWebhooks($filter) as $wh) {
            $wh = (array)$wh;
            $wid = (int)$wh['webhook_id'];
            $data['webhooks'][] = [
                'webhook_id' => $wid,
                'trigger' => (string)$wh['trigger'],
                'url' => (string)$wh['url'],
                'secret_is_set' => (int)($wh['secret_is_set'] ?? 0) === 1,
                'description' => (string)$wh['description'],
                'status' => (int)$wh['status'],
                'log_count' => (int)$wh['log_count'],
                'date_added' => (string)$wh['date_added'],
                'logs' => $this->url->link('setting/webhook.logs|' . $wid),
                'edit' => $this->url->link('setting/webhook.edit|' . $wid),
                'delete' => $this->url->link('setting/webhook.delete|' . $wid),
            ];
        }
        $data['add'] = $this->url->link('setting/webhook.add');
        $data['logs_all'] = $this->url->link('setting/webhook.logs|0');
        $data['total'] = $total;
        $data['showing'] = $total > 0 ? sprintf($data['text_showing'], (($page - 1) * $limit) + 1, min($page * $limit, $total), $total) : '';
        require_once DIR_SYSTEM . 'library/pagination.php';
        $pagination = new \Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $query = 'page={page}';
        foreach (['trigger' => 'filter_trigger', 'url' => 'filter_url', 'status' => 'filter_status'] as $key => $param) {
            if ($filter[$key] !== '' && !($key === 'status' && $filter[$key] === 'all')) {
                $query .= '&' . $param . '=' . rawurlencode((string)$filter[$key]);
            }
        }
        $pagination->url = $this->url->link('setting/webhook', $query);
        $pagination->css_class = 'pagination pagination-sm mb-0';
        $pagination->text_first = $this->language->get('text_page_first');
        $pagination->text_last = $this->language->get('text_page_last');
        $pagination->text_next = $this->language->get('text_page_next');
        $pagination->text_prev = $this->language->get('text_page_prev');
        $data['pagination'] = $pagination->render();

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
            $errors = $this->csrfValid() ? $this->validateWebhook() : [$this->language->get('error_csrf')];

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

            $csrfOk = $this->csrfValid();
            $allowEmptySecret = false;
            if ($csrfOk) {
                $secretInput = (string)($this->request->post['secret'] ?? '');
                $clearSecret = (string)($this->request->post['secret_clear'] ?? '') === '1';
                if ($secretInput === '' && $clearSecret) {
                    // Limpar o segredo HMAC desativa o endpoint para impedir envios sem assinatura.
                    $this->request->post['secret'] = '';
                    $this->request->post['status'] = '0';
                    $allowEmptySecret = true;
                } elseif ($secretInput === '') {
                    $this->request->post['secret'] = (string)($webhook['secret'] ?? '');
                    $allowEmptySecret = (string)($webhook['secret'] ?? '') === '';
                }
            }
            $errors = $csrfOk ? $this->validateWebhook($webhook_id, $allowEmptySecret) : [$this->language->get('error_csrf')];

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
        if (!$this->csrfValid()) {
            $this->setFlash($this->language->get('error_csrf'), 'error');
            $this->response->redirect($this->url->link('setting/webhook'));
            return;
        }

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

        $logFilter = [
            'delivery' => mb_substr($this->queryValue('filter_delivery'), 0, 64),
            'trigger' => mb_substr($this->queryValue('filter_trigger'), 0, 64),
            'http_code' => max(0, (int)$this->queryValue('filter_http_code')),
        ];
        $data['heading_title']    = $this->language->get('heading_title');
        $data['text_logs_title']  = $webhook
            ? sprintf($this->language->get('text_logs_of'), (string)$webhook['trigger'], '#' . $webhook_id)
            : $this->language->get('text_logs_all');
        $data['text_logs_hint']   = $this->language->get('text_logs_hint');
        $data['text_close']       = $this->language->get('text_close');
        $data['text_no_results']  = $this->language->get('text_no_logs');
        $data['text_filter']      = $this->language->get('text_filter');
        $data['entry_delivery']   = $this->language->get('entry_delivery');
        $data['entry_trigger']    = $this->language->get('entry_trigger');
        $data['entry_http_code']  = $this->language->get('entry_http_code');
        $data['button_filter']    = $this->language->get('button_filter');
        $data['button_reset']     = $this->language->get('button_reset');
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
        $data['text_showing']     = $this->language->get('text_showing');
        $data['filter_delivery']  = $logFilter['delivery'];
        $data['filter_trigger']   = $logFilter['trigger'];
        $data['filter_http_code'] = $logFilter['http_code'];
        $data['filter_action']    = $this->url->link('setting/webhook.logs|' . $webhook_id);
        $data['filter_reset']     = $this->url->link('setting/webhook.logs|' . $webhook_id);
        $data['total'] = $this->model_setting_webhook->getTotalLogs($filter, $logFilter);
        $page = max(1, (int)$this->queryValue('page'));
        $limit = 25;
        $page = min($page, max(1, (int)ceil($data['total'] / $limit)));
        $start = ($page - 1) * $limit;
        $data['showing'] = $data['total'] > 0 ? sprintf($data['text_showing'], $start + 1, min($start + $limit, $data['total']), $data['total']) : '';

        $data['logs'] = [];
        foreach ($this->model_setting_webhook->getLogs($filter, $start, $limit, $logFilter) as $log) {
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
        require_once DIR_SYSTEM . 'library/pagination.php';
        $pagination = new \Pagination();
        $pagination->total = $data['total'];
        $pagination->page = $page;
        $pagination->limit = $limit;
        $logQuery = 'page={page}';
        foreach (['delivery' => 'filter_delivery', 'trigger' => 'filter_trigger', 'http_code' => 'filter_http_code'] as $key => $param) {
            if ((string)$logFilter[$key] !== '' && !($key === 'http_code' && $logFilter[$key] === 0)) {
                $logQuery .= '&' . $param . '=' . rawurlencode((string)$logFilter[$key]);
            }
        }
        $pagination->url = $this->url->link('setting/webhook.logs|' . $webhook_id, $logQuery);
        $pagination->css_class = 'pagination pagination-sm mb-0';
        $pagination->text_first = $this->language->get('text_page_first');
        $pagination->text_last = $this->language->get('text_page_last');
        $pagination->text_next = $this->language->get('text_page_next');
        $pagination->text_prev = $this->language->get('text_page_prev');
        $data['pagination'] = $pagination->render();

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
            'text_secret_set'    => $this->language->get('text_secret_set'),
            'text_secret_unset'  => $this->language->get('text_secret_unset'),
            'text_secret_clear_help' => $this->language->get('text_secret_clear_help'),
            'entry_secret_clear' => $this->language->get('entry_secret_clear'),
            'csrf_token'         => \Reamur\System\Library\Security\Csrf::token($this->session),
            'text_status_hint'   => $this->language->get('text_status_hint'),
            'entry_trigger'      => $this->language->get('entry_trigger'),
            'entry_url'          => $this->language->get('entry_url'),
            'entry_secret'       => $this->language->get('entry_secret'),
            'entry_description'  => $this->language->get('entry_description'),
            'entry_status'       => $this->language->get('entry_status'),
            'button_save'        => $this->language->get('button_save'),
            'button_cancel'      => $this->language->get('button_cancel'),
            'button_generate'    => $this->language->get('button_generate'),
            'webhook_id'         => (int)($webhook['webhook_id'] ?? 0),
            'can_modify'         => $this->user->hasPermission('modify', 'setting/webhook'),
            'secret_required'    => empty($webhook['webhook_id']),
            'trigger_suggestions' => self::TRIGGER_SUGGESTIONS,
            'value_trigger'      => (string)($post['trigger'] ?? ($webhook['trigger'] ?? '')),
            'value_url'          => (string)($post['url'] ?? ($webhook['url'] ?? '')),
            // Segredos jamais são devolvidos no HTML nem após erro de validação.
            'value_secret'       => '',
            'secret_is_set'      => (string)($webhook['secret'] ?? '') !== '',
            'value_secret_clear' => (string)($post['secret_clear'] ?? '') === '1' && (string)($post['secret'] ?? '') === '',
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
    private function validateWebhook(int $except_webhook_id = 0, bool $allow_empty_secret = false): array {
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

        $emptySecretAllowed = $secret === '' && $allow_empty_secret && $except_webhook_id > 0 && empty($post['status']);
        if (!($emptySecretAllowed || (mb_strlen($secret) >= 16 && mb_strlen($secret) <= 64))) {
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

    /** Chrome compartilhado + estilo do módulo */
    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
