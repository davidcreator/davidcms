<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/category — CRUD de categorias do portfólio (F2-9, ADR 0009) — segundo
 * CRUD no molde F2-8: method-grammar, ACL base-route, multi-idioma, seo_url,
 * upload delegando ao componente compartilhado MediaUpload (componente extraído
 * na F2-11, regra dos três — ADR 0011).
 * Rotas: /catalog/category · .add · .edit|N · .delete|N (POST) · .upload (POST→JSON)
 */
namespace Reamur\Admin\Controller\Catalog;

use Reamur\System\Library\MediaUpload;

class Category extends \Reamur\System\Engine\Controller {

    private const UPLOAD_DIR  = 'view/image/category/';

    /** Lista categorias (200) */
    public function index(): void {
        $this->load->language('catalog/category');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/category');

        $data['heading_title']       = $this->language->get('heading_title');
        $data['text_list']           = $this->language->get('text_list');
        $data['text_no_results']     = $this->language->get('text_no_results');
        $data['text_confirm_delete'] = $this->language->get('text_confirm_delete');
        $data['text_enabled']        = $this->language->get('text_enabled');
        $data['text_disabled']       = $this->language->get('text_disabled');
        $data['text_none']           = $this->language->get('text_none');
        $data['text_placeholder']    = $this->language->get('text_placeholder');
        $data['column_image']        = $this->language->get('column_image');
        $data['column_name']         = $this->language->get('column_name');
        $data['column_parent']       = $this->language->get('column_parent');
        $data['column_status']       = $this->language->get('column_status');
        $data['column_sort_order']   = $this->language->get('column_sort_order');
        $data['column_date_modified'] = $this->language->get('column_date_modified');
        $data['column_action']       = $this->language->get('column_action');
        $data['button_add']          = $this->language->get('button_add');
        $data['button_edit']         = $this->language->get('button_edit');
        $data['button_delete']       = $this->language->get('button_delete');
        $data['text_flash']          = $this->flash();

        $catalog = $this->catalogUrl();

        $data['categories'] = [];

        foreach ($this->model_catalog_category->getCategories() as $category) {
            $category = (array)$category;
            $cid      = (int)$category['category_id'];
            $image    = (string)$category['image'];

            $data['categories'][] = [
                'category_id'   => $cid,
                'thumb'         => $image !== '' ? $catalog . $image : '',
                'name'          => (string)$category['name'],
                'parent_name'   => (string)($category['parent_name'] ?? ''),
                'status'        => (int)$category['status'],
                'sort_order'    => (int)$category['sort_order'],
                'date_modified' => (string)$category['date_modified'],
                'edit'          => $this->url->link('catalog/category.edit|' . $cid),
                'delete'        => $this->url->link('catalog/category.delete|' . $cid),
            ];
        }

        $data['add'] = $this->url->link('catalog/category.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('catalog/category_list', $data));
    }

    /** GET: formulário de criação · POST: valida e persiste; sucesso → lista + flash */
    public function add(): void {
        if (!$this->user->hasPermission('modify', 'catalog/category') && $this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('catalog/category');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/category');

        $data = $this->formData([]);

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validateCategory(0);

            if ($errors === []) {
                $category_id = $this->model_catalog_category->addCategory($this->postPayload());

                if ($category_id) {
                    $this->setFlash($this->language->get('text_success_add'));

                    $this->response->redirect($this->url->link('catalog/category'));

                    return;
                }
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('catalog/category.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('catalog/category_form', $data));
    }

    /** GET: formulário de edição · POST: valida e persiste (args do Action são strings) */
    public function edit(string $category_id = '0'): void {
        $category_id = (int)$category_id;

        $this->load->language('catalog/category');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/category');

        $category = $this->model_catalog_category->getCategory($category_id);

        if (!$category) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $data          = $this->formData($category);
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'catalog/category')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $errors = $this->validateCategory($category_id);

            if ($errors === []) {
                $this->model_catalog_category->editCategory($category_id, $this->postPayload());

                $this->setFlash($this->language->get('text_success_edit'));

                $this->response->redirect($this->url->link('catalog/category'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('catalog/category.edit|' . $category_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('catalog/category_form', $data));
    }

    /** Exclui via POST (cascata manual: filhos viram raiz; GET jamais muta) */
    public function delete(string $category_id = '0'): void {
        $category_id = (int)$category_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'catalog/category')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('catalog/category');

        $this->load->model('catalog/category');

        $category = $this->model_catalog_category->getCategory($category_id);

        if ($category) {
            $image = (string)$category['image'];

            if ($image !== '') {
                $this->media()->delete($image);
            }

            $this->model_catalog_category->deleteCategory($category_id);

            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('catalog/category'));
    }

    /** Upload da imagem da categoria — POST multipart 'file' → JSON {success, path} */
    public function upload(): void {
        $this->load->language('catalog/category');

        $ok   = false;
        $msg  = '';
        $path = '';

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $msg = $this->language->get('error_upload_method');
        } elseif (!$this->user->hasPermission('modify', 'catalog/category')) {
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
     * @param array<string, mixed> $category Linha do banco ou [] (create)
     * @return array<string, mixed> Dados do formulário (GET) ou do POST
     */
    private function formData(array $category): array {
        $this->load->language('catalog/category');

        foreach ([
            'heading_title', 'text_edit', 'text_add', 'text_general', 'text_data',
            'text_languages_note', 'text_image_cover', 'text_upload_hint',
            'entry_name', 'entry_description', 'entry_meta_title', 'entry_meta_description',
            'entry_keyword', 'entry_parent', 'entry_image', 'entry_status', 'entry_sort_order',
            'button_save', 'button_cancel', 'button_upload', 'text_placeholder', 'text_none',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error']      = '';
        $data['text_flash'] = '';

        $cid          = (int)($category['category_id'] ?? 0);
        $isPost       = $this->request->server['REQUEST_METHOD'] === 'POST';
        $post         = $this->request->post;
        $descriptions = $cid ? $this->model_catalog_category->getCategoryDescriptions($cid) : [];
        $keywords     = $cid ? $this->model_catalog_category->getCategoryKeywords($cid) : [];

        $data['languages'] = [];

        foreach ($this->model_catalog_category->getLanguages() as $language) {
            $language = (array)$language;
            $lid      = (int)$language['language_id'];
            $saved    = (array)($descriptions[$lid] ?? []);

            $fields = [];

            foreach (['name', 'description', 'meta_title', 'meta_description'] as $f) {
                $fields[$f] = (string)($post['category_description'][$lid][$f] ?? ($saved[$f] ?? ''));
            }

            $fields['keyword'] = (string)($post['category_description'][$lid]['keyword'] ?? ($keywords[$lid] ?? ''));

            $data['languages'][] = [
                'language_id' => $lid,
                'name'        => (string)$language['name'],
                'code'        => (string)$language['code'],
                'fields'      => $fields,
            ];
        }

        $data['category_id'] = $cid;
        $data['parent_id']   = (int)($post['parent_id'] ?? ($category['parent_id'] ?? 0));
        $data['image']       = (string)($post['image'] ?? ($category['image'] ?? ''));
        $data['sort_order']  = (string)($post['sort_order'] ?? (string)($category['sort_order'] ?? '0'));
        $data['status']      = isset($post['status']) ? '1' : ($isPost ? '0' : (string)(int)($category['status'] ?? 0));

        $data['parents']  = array_map(
            fn(array $row): array => ['category_id' => (int)$row['category_id'], 'name' => (string)$row['name']],
            $this->model_catalog_category->getParentOptions($cid)
        );
        $data['upload']     = $this->url->link('catalog/category.upload');
        $data['catalog']    = $this->catalogUrl();
        $data['cancel']     = $this->url->link('catalog/category');
        $data['text_error_upload_js'] = $this->language->get('error_upload');

        return $data;
    }

    /**
     * Payload normalizado do POST (chaves SEMPRE presentes — lição F2).
     *
     * @return array{parent_id: int, image: string, status: int, sort_order: int, descriptions: array<int, array<string, string>>}
     */
    private function postPayload(): array {
        $post = $this->request->post;

        $descriptions = [];

        foreach ((array)($post['category_description'] ?? []) as $lid => $d) {
            $d = (array)$d;

            $descriptions[(int)$lid] = [
                'name'             => mb_substr(trim((string)($d['name'] ?? '')), 0, 255),
                'description'      => trim((string)($d['description'] ?? '')),
                'meta_title'       => mb_substr(trim((string)($d['meta_title'] ?? '')), 0, 255),
                'meta_description' => mb_substr(trim((string)($d['meta_description'] ?? '')), 0, 255),
                'keyword'          => mb_substr(trim((string)($d['keyword'] ?? '')), 0, 64),
            ];
        }

        return [
            'parent_id'    => (int)($post['parent_id'] ?? 0),
            'image'        => $this->imageGuard((string)($post['image'] ?? '')),
            'status'       => isset($post['status']) ? 1 : 0,
            'sort_order'   => (int)($post['sort_order'] ?? 0),
            'descriptions' => $descriptions,
        ];
    }

    /**
     * @return array<int, string> Mensagens de erro (vazio = válido)
     */
    private function validateCategory(int $category_id): array {
        $errors = [];
        $post   = $this->request->post;
        $desp   = (array)($post['category_description'] ?? []);

        $nameEn = trim((string)($desp[1]['name'] ?? ''));

        if ($nameEn === '' || mb_strlen($nameEn) > 255) {
            $errors[] = $this->language->get('error_name');
        }

        // keyword SEO: formato + unicidade global por idioma (seo_url é compartilhada)
        foreach ($desp as $lid => $d) {
            $keyword = trim((string)(((array)$d)['keyword'] ?? ''));

            if ($keyword === '') {
                continue;
            }

            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $keyword)) {
                $errors[] = sprintf($this->language->get('error_keyword_format'), (int)$lid);
            } elseif ($this->model_catalog_category->keywordTaken($keyword, (int)$lid, $category_id)) {
                $errors[] = sprintf($this->language->get('error_keyword_taken'), $keyword);
            }
        }

        // Imagem: só dentro do diretório do módulo
        $cover = (string)($post['image'] ?? '');

        if ($cover !== '' && $this->imageGuard($cover) === '') {
            $errors[] = $this->language->get('error_image_path');
        }

        // Anti-ciclo: pai não pode ser a própria nem descendente (model já
        // exclui do dropdown — o POST pode vir adulterado)
        $parent_id = (int)($post['parent_id'] ?? 0);

        if ($parent_id > 0) {
            $valid = array_column($this->model_catalog_category->getParentOptions($category_id), 'category_id');

            if (!in_array($parent_id, array_map('intval', $valid), true)) {
                $errors[] = $this->language->get('error_parent_cycle');
            }
        }

        return $errors;
    }


    /** Componente compartilhado de upload (regra dos três — ADR 0011); root = catalog/ irmão do admin */
    private function media(): MediaUpload {
        return new MediaUpload(rtrim((string)dirname(DIR_APPLICATION), '/') . '/catalog/');
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

    /** Só vale path relativo dentro do diretório de uploads do módulo e arquivo existente (delega ao MediaUpload) */
    private function imageGuard(string $path): string {
        return $this->media()->guard($path, self::UPLOAD_DIR);
    }

    /** Resolve o path físico no catalog (irmão do admin); '' se não existir (delega ao MediaUpload) */
    private function imagePath(string $relative): string {
        return $this->media()->absolute($relative);
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
