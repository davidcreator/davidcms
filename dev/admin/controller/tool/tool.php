<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * tool/tool — CRUD de ferramentas conectadas (F2-13, ADR 0013) — sexto CRUD
 * F2. Multi-idioma (nome en-US obrigatório, descrição curta), vendor + URL
 * (validação http(s):// herdada do F2-12), ícone via componente MediaUpload
 * (4º consumidor), SEM SEO/keyword (item de listagem, não página própria).
 * Rotas: /tool/tool · .add · .edit|N · .delete|N (POST) · .upload (POST→JSON)
 */
namespace Reamur\Admin\Controller\Tool;

use Reamur\System\Library\MediaUpload;

class Tool extends \Reamur\System\Engine\Controller {

    private const UPLOAD_DIR = 'view/image/tool/';

    /** Lista ferramentas com nome/fornecedor/status e recorte de vinte. */
    public function index(): void {
        $this->load->language('tool/tool');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('tool/tool');
        foreach ([
            'heading_title', 'text_list', 'text_no_results', 'text_confirm_delete',
            'text_enabled', 'text_disabled', 'text_placeholder', 'text_none',
            'column_image', 'column_name', 'column_vendor', 'column_url', 'column_status',
            'column_sort_order', 'column_date_added', 'column_action',
            'button_add', 'button_edit', 'button_delete', 'button_close', 'button_filter', 'button_reset',
            'text_all_status', 'text_search_placeholder', 'text_vendor_placeholder', 'text_empty_title', 'text_empty_hint',
        ] as $key) { $data[$key] = $this->language->get($key); }
        $data['text_flash'] = $this->flash();
        $data['can_modify'] = $this->user->hasPermission('modify', 'tool/tool');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $name = $this->request->get['filter_name'] ?? '';
        $vendor = $this->request->get['filter_vendor'] ?? '';
        $status = $this->request->get['filter_status'] ?? 'all';
        $requestedPage = $this->request->get['page'] ?? 1;
        $filter_name = is_string($name) ? mb_substr(trim($name), 0, 128) : '';
        $filter_vendor = is_string($vendor) ? mb_substr(trim($vendor), 0, 64) : '';
        $filter_status = is_string($status) && in_array($status, ['all', '0', '1'], true) ? $status : 'all';
        $filters = ['name' => $filter_name, 'vendor' => $filter_vendor, 'status' => $filter_status];
        $limit = 20;
        $total = $this->model_tool_tool->getTotalTools($filters);
        $page = is_scalar($requestedPage) ? max(1, (int)$requestedPage) : 1;
        $page = min($page, max(1, (int)ceil($total / $limit)));
        $catalog = $this->catalogUrl();
        $data['tools'] = [];
        foreach ($this->model_tool_tool->getTools($filters + ['start' => ($page - 1) * $limit, 'limit' => $limit]) as $row) {
            $row = (array)$row;
            $tid = (int)$row['tool_id'];
            $image = (string)$row['image'];
            $data['tools'][] = [
                'tool_id' => $tid, 'thumb' => $image !== '' ? $catalog . $image : '',
                'name' => (string)$row['name'], 'vendor' => (string)$row['vendor'], 'url' => (string)$row['url'],
                'status' => (int)$row['status'], 'sort_order' => (int)$row['sort_order'],
                'date_added' => (string)$row['date_added'],
                'edit' => $this->url->link('tool/tool.edit|' . $tid),
                'delete' => $this->url->link('tool/tool.delete|' . $tid),
            ];
        }
        $data['add'] = $this->url->link('tool/tool.add');
        $data['total_tools'] = $total;
        $data['filter_name'] = $filter_name;
        $data['filter_vendor'] = $filter_vendor;
        $data['filter_status'] = $filter_status;
        $data['filter_action'] = $this->url->link('tool/tool');
        $data['filter_reset'] = $this->url->link('tool/tool');
        $data['showing'] = $total > 0
            ? sprintf($this->language->get('text_showing'), ($page - 1) * $limit + 1, min($page * $limit, $total), $total) : '';
        require_once DIR_SYSTEM . 'library/pagination.php';
        $pagination = new \Pagination();
        $pagination->total = $total; $pagination->page = $page; $pagination->limit = $limit;
        $pagination->css_class = 'pagination pagination-sm mb-0';
        $pagination->text_first = $this->language->get('button_first_page');
        $pagination->text_last = $this->language->get('button_last_page');
        $pagination->text_next = $this->language->get('button_next_page');
        $pagination->text_prev = $this->language->get('button_previous_page');
        $pagination->url = $this->url->link('tool/tool', 'page={page}'
            . ($filter_name !== '' ? '&filter_name=' . rawurlencode($filter_name) : '')
            . ($filter_vendor !== '' ? '&filter_vendor=' . rawurlencode($filter_vendor) : '')
            . ($filter_status !== 'all' ? '&filter_status=' . $filter_status : ''));
        // Normalização local do escape duplo da library, sem alterar o núcleo.
        $data['pagination'] = str_replace('&amp;amp;', '&amp;', $pagination->render());
        $this->chrome($data);
        $this->response->setOutput($this->load->view('tool/tool_list', $data));
    }

    /** GET: formulário de criação · POST: valida e persiste; sucesso → lista + flash */
    public function add(): void {
        if (!$this->user->hasPermission('modify', 'tool/tool') && $this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('tool/tool');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('tool/tool');

        $data = $this->formData([]);

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $errors = $this->csrfValid() ? $this->validateTool() : [$this->language->get('error_csrf')];

            if ($errors === []) {
                $tool_id = $this->model_tool_tool->addTool($this->postPayload());

                if ($tool_id) {
                    $this->setFlash($this->language->get('text_success_add'));

                    $this->response->redirect($this->url->link('tool/tool'));

                    return;
                }
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('tool/tool.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('tool/tool_form', $data));
    }

    /** GET: formulário de edição · POST: valida e persiste (args do Action são strings) */
    public function edit(string $tool_id = '0'): void {
        $tool_id = (int)$tool_id;

        $this->load->language('tool/tool');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('tool/tool');

        $tool = $this->model_tool_tool->getTool($tool_id);

        if (!$tool) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $data          = $this->formData($tool);
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'tool/tool')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $errors = $this->csrfValid() ? $this->validateTool() : [$this->language->get('error_csrf')];

            if ($errors === []) {
                $this->model_tool_tool->editTool($tool_id, $this->postPayload());

                $this->setFlash($this->language->get('text_success_edit'));

                $this->response->redirect($this->url->link('tool/tool'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('tool/tool.edit|' . $tool_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('tool/tool_form', $data));
    }

    /** Exclui via POST (cascata manual: descrições + ícone físico; GET jamais muta) */
    public function delete(string $tool_id = '0'): void {
        $tool_id = (int)$tool_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'tool/tool')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('tool/tool');
        if (!$this->csrfValid()) {
            $this->setFlash($this->language->get('error_csrf'), 'error');
            $this->response->redirect($this->url->link('tool/tool'));
            return;
        }

        $this->load->model('tool/tool');

        $tool = $this->model_tool_tool->getTool($tool_id);

        if ($tool) {
            $image = (string)$tool['image'];

            if ($image !== '') {
                $this->media()->delete($image);
            }

            $this->model_tool_tool->deleteTool($tool_id);

            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('tool/tool'));
    }

    /** Upload do ícone — POST multipart 'file' → JSON {success, path} (componente MediaUpload) */
    public function upload(): void {
        $this->load->language('tool/tool');

        $ok   = false;
        $msg  = '';
        $path = '';

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $msg = $this->language->get('error_upload_method');
        } elseif (!$this->user->hasPermission('modify', 'tool/tool')) {
            $msg = $this->language->get('error_permission');
        } elseif (!$this->csrfValid()) {
            $msg = $this->language->get('error_csrf');
        } else {
            $result = $this->media()->store((array)($this->request->files['file'] ?? []), self::UPLOAD_DIR);

            if ($result['ok']) {
                $ok   = true;
                $path = $result['path'];
            } else {
                $msg = $this->uploadErrorMessage($result['error']);
            }
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');

        $this->response->setOutput((string)json_encode([
            'success' => $ok,
            'path'    => $ok ? $path : '',
            'error'   => $ok ? '' : $msg,
        ]));
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $tool Linha do banco ou [] (create)
     * @return array<string, mixed> Dados do formulário (GET) ou do POST
     */
    private function formData(array $tool): array {
        $this->load->language('tool/tool');

        foreach ([
            'heading_title', 'text_edit', 'text_add', 'text_general', 'text_data',
            'text_languages_note', 'text_image', 'text_upload_hint',
            'entry_name', 'entry_description', 'entry_vendor', 'entry_url', 'entry_image',
            'entry_status', 'entry_sort_order',
            'button_save', 'button_cancel', 'button_upload', 'text_placeholder', 'text_none',
            'text_enabled', 'text_disabled', 'text_upload_wait',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $this->document->addScript($this->config->get('site_url') . 'view/js/connected-tools.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        $data['error']      = '';
        $data['text_flash'] = '';
        $data['can_modify'] = $this->user->hasPermission('modify', 'tool/tool');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);

        $tid          = (int)($tool['tool_id'] ?? 0);
        $isPost       = $this->request->server['REQUEST_METHOD'] === 'POST';
        $post         = $this->request->post;
        $descriptions = $tid ? $this->model_tool_tool->getToolDescriptions($tid) : [];

        $data['languages'] = [];

        foreach ($this->model_tool_tool->getLanguages() as $language) {
            $language = (array)$language;
            $lid      = (int)$language['language_id'];
            $saved    = (array)($descriptions[$lid] ?? []);

            $fields = [];

            foreach (['name', 'description'] as $f) {
                $fields[$f] = (string)($post['tool_description'][$lid][$f] ?? ($saved[$f] ?? ''));
            }

            $data['languages'][] = [
                'language_id' => $lid,
                'name'        => (string)$language['name'],
                'code'        => (string)$language['code'],
                'fields'      => $fields,
            ];
        }

        $data['tool_id']    = $tid;
        $data['vendor']     = (string)($post['vendor'] ?? ($tool['vendor'] ?? ''));
        $data['url']        = (string)($post['url'] ?? ($tool['url'] ?? ''));
        $data['image']      = (string)($post['image'] ?? ($tool['image'] ?? ''));
        $data['sort_order'] = (string)($post['sort_order'] ?? (string)($tool['sort_order'] ?? '0'));
        $data['status']     = isset($post['status']) ? '1' : ($isPost ? '0' : (string)(int)($tool['status'] ?? 0));

        $data['upload']     = $this->url->link('tool/tool.upload');
        $data['catalog']    = $this->catalogUrl();
        $relative = $this->media()->guard($data['image'], self::UPLOAD_DIR);
        $data['image_preview'] = $relative !== '' ? $data['catalog'] . $relative : '';
        $data['cancel']     = $this->url->link('tool/tool');
        $data['text_error_upload_js'] = $this->language->get('error_upload');

        return $data;
    }

    /**
     * Payload normalizado do POST (chaves SEMPRE presentes — lição F2).
     *
     * @return array{image: string, vendor: string, url: string, status: int, sort_order: int, descriptions: array<int, array<string, string>>}
     */
    private function postPayload(): array {
        $post = $this->request->post;

        $descriptions = [];

        foreach ((array)($post['tool_description'] ?? []) as $lid => $d) {
            $d = (array)$d;

            $descriptions[(int)$lid] = [
                'name'        => mb_substr(trim((string)($d['name'] ?? '')), 0, 128),
                'description' => mb_substr(trim((string)($d['description'] ?? '')), 0, 255),
            ];
        }

        return [
            'image'        => $this->media()->guard((string)($post['image'] ?? ''), self::UPLOAD_DIR),
            'vendor'       => mb_substr(trim((string)($post['vendor'] ?? '')), 0, 64),
            'url'          => mb_substr(trim((string)($post['url'] ?? '')), 0, 255),
            'status'       => isset($post['status']) ? 1 : 0,
            'sort_order'   => (int)($post['sort_order'] ?? 0),
            'descriptions' => $descriptions,
        ];
    }

    /**
     * @return array<int, string> Mensagens de erro (vazio = válido)
     */
    private function validateTool(): array {
        $errors = [];
        $post   = $this->request->post;
        $desp   = (array)($post['tool_description'] ?? []);

        $nameEn = trim((string)($desp[1]['name'] ?? ''));

        if ($nameEn === '' || mb_strlen($nameEn) > 128) {
            $errors[] = $this->language->get('error_name');
        }

        // URL opcional; se presente, somente http(s):// (mesmo padrão do F2-12)
        $url = trim((string)($post['url'] ?? ''));

        if ($url !== '' && !preg_match('~^https?://~i', $url)) {
            $errors[] = $this->language->get('error_url');
        }

        // Imagem: só dentro do diretório do módulo (componente MediaUpload)
        $image = (string)($post['image'] ?? '');

        if ($image !== '' && $this->media()->guard($image, self::UPLOAD_DIR) === '') {
            $errors[] = $this->language->get('error_image_path');
        }

        return $errors;
    }

    /** Token estável da sessão para add/edit/delete/upload. */
    private function csrfValid(): bool {
        return \Reamur\System\Library\Security\Csrf::check($this->session, $this->request->post['csrf_token'] ?? '');
    }

    /** Código simbólico do MediaUpload → mensagem do módulo (idioma vigente) */
    private function uploadErrorMessage(string $code): string {
        return match ($code) {
            'size'  => sprintf($this->language->get('error_upload_size'), '2 MB'),
            'type'  => $this->language->get('error_upload_type'),
            'move'  => $this->language->get('error_upload_move'),
            default => $this->language->get('error_upload'),
        };
    }

    /** Componente compartilhado (regra dos três — ADR 0011); root = catalog/ irmão do admin */
    private function media(): MediaUpload {
        return new MediaUpload(rtrim((string)dirname(DIR_APPLICATION), '/') . '/catalog/');
    }

    /** Base pública do CATALOG derivada do site_url do admin (mesma regra do F2-8) */
    private function catalogUrl(): string {
        $base = (string)$this->config->get('site_url', '');

        if (str_ends_with($base, 'admin/')) {
            return substr($base, 0, -strlen('admin/')) . 'catalog/';
        }

        return $base;
    }

    /** Leia-uma-vez (consume) do flash da sessão */
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
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
