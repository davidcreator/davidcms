<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * marketing/client_logo — CRUD de logos de clientes (F2-12, ADR 0012) —
 * quinto CRUD F2 e o PRIMEIRO single-table (sem descrições/multi-idioma,
 * sem SEO, sem datas). Segundo consumidor do componente MediaUpload (agora
 * extração validada em uso direto — ADR 0011).
 * Rotas: /marketing/client_logo · .add · .edit|N · .delete|N (POST) · .upload (POST→JSON)
 */
namespace Reamur\Admin\Controller\Marketing;

use Reamur\System\Library\MediaUpload;

class Client_logo extends \Reamur\System\Engine\Controller {

    private const UPLOAD_DIR = 'view/image/client_logo/';

    /** Lista logos (200) */
    public function index(): void {
        $this->load->language('marketing/client_logo');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('marketing/client_logo');

        $data['heading_title']       = $this->language->get('heading_title');
        $data['text_list']           = $this->language->get('text_list');
        $data['text_no_results']     = $this->language->get('text_no_results');
        $data['text_confirm_delete'] = $this->language->get('text_confirm_delete');
        $data['text_enabled']        = $this->language->get('text_enabled');
        $data['text_disabled']       = $this->language->get('text_disabled');
        $data['text_placeholder']    = $this->language->get('text_placeholder');
        $data['text_none']           = $this->language->get('text_none');
        $data['column_image']        = $this->language->get('column_image');
        $data['column_name']         = $this->language->get('column_name');
        $data['column_url']          = $this->language->get('column_url');
        $data['column_status']       = $this->language->get('column_status');
        $data['column_sort_order']   = $this->language->get('column_sort_order');
        $data['column_action']       = $this->language->get('column_action');
        $data['button_add']          = $this->language->get('button_add');
        $data['button_edit']         = $this->language->get('button_edit');
        $data['button_delete']       = $this->language->get('button_delete');
        $data['text_flash']          = $this->flash();

        $catalog = $this->catalogUrl();

        $data['client_logos'] = [];

        foreach ($this->model_marketing_client_logo->getClientLogos() as $logo) {
            $logo  = (array)$logo;
            $lid   = (int)$logo['client_logo_id'];
            $image = (string)$logo['image'];

            $data['client_logos'][] = [
                'client_logo_id' => $lid,
                'thumb'          => $image !== '' ? $catalog . $image : '',
                'name'           => (string)$logo['name'],
                'url'            => (string)$logo['url'],
                'status'         => (int)$logo['status'],
                'sort_order'     => (int)$logo['sort_order'],
                'edit'           => $this->url->link('marketing/client_logo.edit|' . $lid),
                'delete'         => $this->url->link('marketing/client_logo.delete|' . $lid),
            ];
        }

        $data['add'] = $this->url->link('marketing/client_logo.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('marketing/client_logo_list', $data));
    }

    /** GET: formulário de criação · POST: valida e persiste; sucesso → lista + flash */
    public function add(): void {
        if (!$this->user->hasPermission('modify', 'marketing/client_logo') && $this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('marketing/client_logo');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('marketing/client_logo');

        $data = $this->formData([]);

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validateClientLogo();

            if ($errors === []) {
                $client_logo_id = $this->model_marketing_client_logo->addClientLogo($this->postPayload());

                if ($client_logo_id) {
                    $this->setFlash($this->language->get('text_success_add'));

                    $this->response->redirect($this->url->link('marketing/client_logo'));

                    return;
                }
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('marketing/client_logo.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('marketing/client_logo_form', $data));
    }

    /** GET: formulário de edição · POST: valida e persiste (args do Action são strings) */
    public function edit(string $client_logo_id = '0'): void {
        $client_logo_id = (int)$client_logo_id;

        $this->load->language('marketing/client_logo');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('marketing/client_logo');

        $logo = $this->model_marketing_client_logo->getClientLogo($client_logo_id);

        if (!$logo) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $data          = $this->formData($logo);
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'marketing/client_logo')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $errors = $this->validateClientLogo();

            if ($errors === []) {
                $this->model_marketing_client_logo->editClientLogo($client_logo_id, $this->postPayload());

                $this->setFlash($this->language->get('text_success_edit'));

                $this->response->redirect($this->url->link('marketing/client_logo'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('marketing/client_logo.edit|' . $client_logo_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('marketing/client_logo_form', $data));
    }

    /** Exclui via POST (cascata trivial + logo físico; GET jamais muta) */
    public function delete(string $client_logo_id = '0'): void {
        $client_logo_id = (int)$client_logo_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'marketing/client_logo')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('marketing/client_logo');

        $this->load->model('marketing/client_logo');

        $logo = $this->model_marketing_client_logo->getClientLogo($client_logo_id);

        if ($logo) {
            $image = (string)$logo['image'];

            if ($image !== '') {
                $this->media()->delete($image);
            }

            $this->model_marketing_client_logo->deleteClientLogo($client_logo_id);

            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('marketing/client_logo'));
    }

    /** Upload do logo — POST multipart 'file' → JSON {success, path} (componente MediaUpload) */
    public function upload(): void {
        $this->load->language('marketing/client_logo');

        $ok   = false;
        $msg  = '';
        $path = '';

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $msg = $this->language->get('error_upload_method');
        } elseif (!$this->user->hasPermission('modify', 'marketing/client_logo')) {
            $msg = $this->language->get('error_permission');
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
     * @param array<string, mixed> $logo Linha do banco ou [] (create)
     * @return array<string, mixed> Dados do formulário (GET) ou do POST
     */
    private function formData(array $logo): array {
        $this->load->language('marketing/client_logo');

        foreach ([
            'heading_title', 'text_edit', 'text_add', 'text_data', 'text_image', 'text_upload_hint',
            'entry_name', 'entry_url', 'entry_image', 'entry_status', 'entry_sort_order',
            'button_save', 'button_cancel', 'button_upload', 'text_placeholder',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error']      = '';
        $data['text_flash'] = '';

        $lid    = (int)($logo['client_logo_id'] ?? 0);
        $isPost = $this->request->server['REQUEST_METHOD'] === 'POST';
        $post   = $this->request->post;

        $data['client_logo_id'] = $lid;
        $data['name']       = (string)($post['name'] ?? ($logo['name'] ?? ''));
        $data['url']        = (string)($post['url'] ?? ($logo['url'] ?? ''));
        $data['image']      = (string)($post['image'] ?? ($logo['image'] ?? ''));
        $data['sort_order'] = (string)($post['sort_order'] ?? (string)($logo['sort_order'] ?? '0'));
        $data['status']     = isset($post['status']) ? '1' : ($isPost ? '0' : (string)(int)($logo['status'] ?? 0));

        $data['upload']     = $this->url->link('marketing/client_logo.upload');
        $data['catalog']    = $this->catalogUrl();
        $data['cancel']     = $this->url->link('marketing/client_logo');
        $data['text_error_upload_js'] = $this->language->get('error_upload');

        return $data;
    }

    /**
     * Payload normalizado do POST (chaves SEMPRE presentes — lição F2).
     *
     * @return array{name: string, image: string, url: string, status: int, sort_order: int}
     */
    private function postPayload(): array {
        $post = $this->request->post;

        return [
            'name'       => mb_substr(trim((string)($post['name'] ?? '')), 0, 128),
            'image'      => $this->media()->guard((string)($post['image'] ?? ''), self::UPLOAD_DIR),
            'url'        => mb_substr(trim((string)($post['url'] ?? '')), 0, 255),
            'status'     => isset($post['status']) ? 1 : 0,
            'sort_order' => (int)($post['sort_order'] ?? 0),
        ];
    }

    /**
     * @return array<int, string> Mensagens de erro (vazio = válido)
     */
    private function validateClientLogo(): array {
        $errors = [];
        $post   = $this->request->post;

        $name = trim((string)($post['name'] ?? ''));

        if ($name === '' || mb_strlen($name) > 128) {
            $errors[] = $this->language->get('error_name');
        }

        // URL opcional; se presente, somente http(s)://
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
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
