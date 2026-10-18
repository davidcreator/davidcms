<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * marketing/testimonial — CRUD de depoimentos (F2-11, ADR 0011) — quarto CRUD
 * no molde F2-8/F2-9: method-grammar, ACL base-route, multi-idioma. PRIMEIRO
 * consumidor do componente compartilhado MediaUpload (regra dos três); sem
 * SEO/keyword (conteúdo da home, não página própria — Dicionário-de-Dados).
 * Rotas: /marketing/testimonial · .add · .edit|N · .delete|N (POST) · .upload (POST→JSON)
 */
namespace Reamur\Admin\Controller\Marketing;

use Reamur\System\Library\MediaUpload;

class Testimonial extends \Reamur\System\Engine\Controller {

    private const UPLOAD_DIR = 'view/image/testimonial/';

    /** Lista depoimentos (200) */
    public function index(): void {
        $this->load->language('marketing/testimonial');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('marketing/testimonial');

        $data['heading_title']       = $this->language->get('heading_title');
        $data['text_list']           = $this->language->get('text_list');
        $data['text_no_results']     = $this->language->get('text_no_results');
        $data['text_confirm_delete'] = $this->language->get('text_confirm_delete');
        $data['text_enabled']        = $this->language->get('text_enabled');
        $data['text_disabled']       = $this->language->get('text_disabled');
        $data['text_placeholder']    = $this->language->get('text_placeholder');
        $data['column_image']        = $this->language->get('column_image');
        $data['column_author']       = $this->language->get('column_author');
        $data['column_company']      = $this->language->get('column_company');
        $data['column_status']       = $this->language->get('column_status');
        $data['column_sort_order']   = $this->language->get('column_sort_order');
        $data['column_date_added']   = $this->language->get('column_date_added');
        $data['column_action']       = $this->language->get('column_action');
        $data['button_add']          = $this->language->get('button_add');
        $data['button_edit']         = $this->language->get('button_edit');
        $data['button_delete']       = $this->language->get('button_delete');
        $data['text_flash']          = $this->flash();

        $catalog = $this->catalogUrl();

        $data['testimonials'] = [];

        foreach ($this->model_marketing_testimonial->getTestimonials() as $testimonial) {
            $testimonial = (array)$testimonial;
            $tid         = (int)$testimonial['testimonial_id'];
            $image       = (string)$testimonial['image'];

            $data['testimonials'][] = [
                'testimonial_id' => $tid,
                'thumb'          => $image !== '' ? $catalog . $image : '',
                'author'         => (string)$testimonial['author'],
                'company'        => (string)$testimonial['company'],
                'status'         => (int)$testimonial['status'],
                'sort_order'     => (int)$testimonial['sort_order'],
                'date_added'     => (string)$testimonial['date_added'],
                'edit'           => $this->url->link('marketing/testimonial.edit|' . $tid),
                'delete'         => $this->url->link('marketing/testimonial.delete|' . $tid),
            ];
        }

        $data['add'] = $this->url->link('marketing/testimonial.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('marketing/testimonial_list', $data));
    }

    /** GET: formulário de criação · POST: valida e persiste; sucesso → lista + flash */
    public function add(): void {
        if (!$this->user->hasPermission('modify', 'marketing/testimonial') && $this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('marketing/testimonial');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('marketing/testimonial');

        $data = $this->formData([]);

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validateTestimonial();

            if ($errors === []) {
                $testimonial_id = $this->model_marketing_testimonial->addTestimonial($this->postPayload());

                if ($testimonial_id) {
                    $this->setFlash($this->language->get('text_success_add'));

                    $this->response->redirect($this->url->link('marketing/testimonial'));

                    return;
                }
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('marketing/testimonial.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('marketing/testimonial_form', $data));
    }

    /** GET: formulário de edição · POST: valida e persiste (args do Action são strings) */
    public function edit(string $testimonial_id = '0'): void {
        $testimonial_id = (int)$testimonial_id;

        $this->load->language('marketing/testimonial');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('marketing/testimonial');

        $testimonial = $this->model_marketing_testimonial->getTestimonial($testimonial_id);

        if (!$testimonial) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $data          = $this->formData($testimonial);
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'marketing/testimonial')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $errors = $this->validateTestimonial();

            if ($errors === []) {
                $this->model_marketing_testimonial->editTestimonial($testimonial_id, $this->postPayload());

                $this->setFlash($this->language->get('text_success_edit'));

                $this->response->redirect($this->url->link('marketing/testimonial'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('marketing/testimonial.edit|' . $testimonial_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('marketing/testimonial_form', $data));
    }

    /** Exclui via POST (cascata manual + imagem física; GET jamais muta) */
    public function delete(string $testimonial_id = '0'): void {
        $testimonial_id = (int)$testimonial_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'marketing/testimonial')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('marketing/testimonial');

        $this->load->model('marketing/testimonial');

        $testimonial = $this->model_marketing_testimonial->getTestimonial($testimonial_id);

        if ($testimonial) {
            $image = (string)$testimonial['image'];

            if ($image !== '') {
                $this->media()->delete($image);
            }

            $this->model_marketing_testimonial->deleteTestimonial($testimonial_id);

            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('marketing/testimonial'));
    }

    /** Upload da foto do autor — POST multipart 'file' → JSON {success, path} (componente MediaUpload) */
    public function upload(): void {
        $this->load->language('marketing/testimonial');

        $ok   = false;
        $msg  = '';
        $path = '';

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $msg = $this->language->get('error_upload_method');
        } elseif (!$this->user->hasPermission('modify', 'marketing/testimonial')) {
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
     * @param array<string, mixed> $testimonial Linha do banco ou [] (create)
     * @return array<string, mixed> Dados do formulário (GET) ou do POST
     */
    private function formData(array $testimonial): array {
        $this->load->language('marketing/testimonial');

        foreach ([
            'heading_title', 'text_edit', 'text_add', 'text_general', 'text_data',
            'text_languages_note', 'text_image', 'text_upload_hint',
            'entry_author', 'entry_company', 'entry_text', 'entry_image',
            'entry_status', 'entry_sort_order',
            'button_save', 'button_cancel', 'button_upload', 'text_placeholder',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error']      = '';
        $data['text_flash'] = '';

        $tid          = (int)($testimonial['testimonial_id'] ?? 0);
        $isPost       = $this->request->server['REQUEST_METHOD'] === 'POST';
        $post         = $this->request->post;
        $descriptions = $tid ? $this->model_marketing_testimonial->getTestimonialDescriptions($tid) : [];

        $data['languages'] = [];

        foreach ($this->model_marketing_testimonial->getLanguages() as $language) {
            $language = (array)$language;
            $lid      = (int)$language['language_id'];
            $saved    = (array)($descriptions[$lid] ?? []);

            $fields = [];

            foreach (['author', 'company', 'text'] as $f) {
                $fields[$f] = (string)($post['testimonial_description'][$lid][$f] ?? ($saved[$f] ?? ''));
            }

            $data['languages'][] = [
                'language_id' => $lid,
                'name'        => (string)$language['name'],
                'code'        => (string)$language['code'],
                'fields'      => $fields,
            ];
        }

        $data['testimonial_id'] = $tid;
        $data['image']          = (string)($post['image'] ?? ($testimonial['image'] ?? ''));
        $data['sort_order']     = (string)($post['sort_order'] ?? (string)($testimonial['sort_order'] ?? '0'));
        $data['status']         = isset($post['status']) ? '1' : ($isPost ? '0' : (string)(int)($testimonial['status'] ?? 0));

        $data['upload']     = $this->url->link('marketing/testimonial.upload');
        $data['catalog']    = $this->catalogUrl();
        $data['cancel']     = $this->url->link('marketing/testimonial');
        $data['text_error_upload_js'] = $this->language->get('error_upload');

        return $data;
    }

    /**
     * Payload normalizado do POST (chaves SEMPRE presentes — lição F2).
     *
     * @return array{image: string, status: int, sort_order: int, descriptions: array<int, array<string, string>>}
     */
    private function postPayload(): array {
        $post = $this->request->post;

        $descriptions = [];

        foreach ((array)($post['testimonial_description'] ?? []) as $lid => $d) {
            $d = (array)$d;

            $descriptions[(int)$lid] = [
                'author'  => mb_substr(trim((string)($d['author'] ?? '')), 0, 128),
                'company' => mb_substr(trim((string)($d['company'] ?? '')), 0, 128),
                'text'    => trim((string)($d['text'] ?? '')),
            ];
        }

        return [
            'image'        => $this->media()->guard((string)($post['image'] ?? ''), self::UPLOAD_DIR),
            'status'       => isset($post['status']) ? 1 : 0,
            'sort_order'   => (int)($post['sort_order'] ?? 0),
            'descriptions' => $descriptions,
        ];
    }

    /**
     * @return array<int, string> Mensagens de erro (vazio = válido)
     */
    private function validateTestimonial(): array {
        $errors = [];
        $post   = $this->request->post;
        $desp   = (array)($post['testimonial_description'] ?? []);

        $authorEn = trim((string)($desp[1]['author'] ?? ''));

        if ($authorEn === '' || mb_strlen($authorEn) > 128) {
            $errors[] = $this->language->get('error_author');
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
