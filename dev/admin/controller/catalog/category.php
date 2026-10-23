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

    /** Lista categorias com filtros e paginação, sem paginar opções de pai. */
    public function index(): void {
        $this->load->language('catalog/category');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('catalog/category');
        foreach ([
            'heading_title', 'text_list', 'text_no_results', 'text_confirm_delete',
            'text_enabled', 'text_disabled', 'text_none', 'text_placeholder',
            'column_image', 'column_name', 'column_parent', 'column_status',
            'column_sort_order', 'column_date_modified', 'column_action',
            'button_add', 'button_edit', 'button_delete', 'button_close',
            'button_filter', 'button_reset', 'text_all_status', 'text_search_placeholder',
            'text_empty_title', 'text_empty_hint',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }
        $data['text_flash'] = $this->flash();
        $data['can_modify'] = $this->user->hasPermission('modify', 'catalog/category');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $name = $this->request->get['filter_name'] ?? '';
        $status = $this->request->get['filter_status'] ?? 'all';
        $requestedPage = $this->request->get['page'] ?? 1;
        $filter_name = is_string($name) ? mb_substr(trim($name), 0, 255) : '';
        $filter_status = is_string($status) && in_array($status, ['all', '0', '1'], true) ? $status : 'all';
        $filters = ['name' => $filter_name, 'status' => $filter_status];
        $limit = 20;
        $total = $this->model_catalog_category->getTotalCategories($filters);
        $page = is_scalar($requestedPage) ? max(1, (int)$requestedPage) : 1;
        $page = min($page, max(1, (int)ceil($total / $limit)));
        $catalog = $this->catalogUrl();
        $data['categories'] = [];
        foreach ($this->model_catalog_category->getCategories($filters + ['start' => ($page - 1) * $limit, 'limit' => $limit]) as $row) {
            $row = (array)$row;
            $cid = (int)$row['category_id'];
            $image = (string)$row['image'];
            $data['categories'][] = [
                'category_id' => $cid,
                'thumb' => $image !== '' ? $catalog . $image : '',
                'name' => (string)$row['name'],
                'parent_name' => (string)($row['parent_name'] ?? ''),
                'status' => (int)$row['status'],
                'sort_order' => (int)$row['sort_order'],
                'date_modified' => (string)$row['date_modified'],
                'edit' => $this->url->link('catalog/category.edit|' . $cid),
                'delete' => $this->url->link('catalog/category.delete|' . $cid),
            ];
        }
        $data['add'] = $this->url->link('catalog/category.add');
        $data['total_categories'] = $total;
        $data['filter_name'] = $filter_name;
        $data['filter_status'] = $filter_status;
        $data['filter_action'] = $this->url->link('catalog/category');
        $data['filter_reset'] = $this->url->link('catalog/category');
        $data['showing'] = $total > 0
            ? sprintf($this->language->get('text_showing'), ($page - 1) * $limit + 1, min($page * $limit, $total), $total)
            : '';
        require_once DIR_SYSTEM . 'library/pagination.php';
        $pagination = new \Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->css_class = 'pagination pagination-sm mb-0';
        $pagination->text_first = $this->language->get('button_first_page');
        $pagination->text_last = $this->language->get('button_last_page');
        $pagination->text_next = $this->language->get('button_next_page');
        $pagination->text_prev = $this->language->get('button_previous_page');
        $pagination->url = $this->url->link('catalog/category', 'page={page}'
            . ($filter_name !== '' ? '&filter_name=' . rawurlencode($filter_name) : '')
            . ($filter_status !== 'all' ? '&filter_status=' . $filter_status : ''));
        // Correção local do escape duplo da library, como no CMS (F8-06).
        $data['pagination'] = str_replace('&amp;amp;', '&amp;', $pagination->render());
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
            $errors = $this->csrfValid() ? $this->validateCategory(0) : [$this->language->get('error_csrf')];

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

            $errors = $this->csrfValid() ? $this->validateCategory($category_id) : [$this->language->get('error_csrf')];

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
        if (!$this->csrfValid()) {
            $this->setFlash($this->language->get('error_csrf'), 'error');
            $this->response->redirect($this->url->link('catalog/category'));
            return;
        }

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
            'text_enabled', 'text_disabled', 'text_meta_group', 'help_sort_order', 'text_upload_wait',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $this->document->addScript($this->config->get('site_url') . 'view/js/catalog-category.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        $data['error']      = '';
        $data['text_flash'] = '';
        $data['can_modify'] = $this->user->hasPermission('modify', 'catalog/category');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);

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
        $preview = $this->imageGuard($data['image']);
        $data['image_preview'] = $preview !== '' ? $data['catalog'] . $preview : '';
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


    /** Token estável da sessão para add/edit/delete/upload. */
    private function csrfValid(): bool {
        return \Reamur\System\Library\Security\Csrf::check($this->session, $this->request->post['csrf_token'] ?? '');
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
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
