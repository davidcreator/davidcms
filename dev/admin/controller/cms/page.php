<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * cms/page — CRUD de páginas de conteúdo/information (F2-10, ADR 0010) —
 * terceiro CRUD no molde F2-8/0008: method-grammar, ACL base-route,
 * multi-idioma, seo_url global. SEM upload: page não tem imagem por design
 * (Dicionario-de-Dados) — a extração do componente de upload (regra dos
 * três) segue pendente do terceiro módulo com arquivo.
 * Rotas: /cms/page · .add · .edit|N · .delete|N (POST).
 */
namespace Reamur\Admin\Controller\Cms;

class Page extends \Reamur\System\Engine\Controller {

    /** Lista páginas (200) */
    public function index(): void {
        $this->load->language('cms/page');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('cms/page');

        $data['heading_title']       = $this->language->get('heading_title');
        $data['text_list']           = $this->language->get('text_list');
        $data['text_no_results']     = $this->language->get('text_no_results');
        $data['text_confirm_delete'] = $this->language->get('text_confirm_delete');
        $data['text_enabled']        = $this->language->get('text_enabled');
        $data['text_disabled']       = $this->language->get('text_disabled');
        $data['column_title']        = $this->language->get('column_title');
        $data['column_status']       = $this->language->get('column_status');
        $data['column_sort_order']   = $this->language->get('column_sort_order');
        $data['column_date_modified'] = $this->language->get('column_date_modified');
        $data['column_action']       = $this->language->get('column_action');
        $data['button_add']          = $this->language->get('button_add');
        $data['button_edit']         = $this->language->get('button_edit');
        $data['button_delete']       = $this->language->get('button_delete');
        $data['text_flash']          = $this->flash();

        $data['pages'] = [];

        foreach ($this->model_cms_page->getPages() as $page) {
            $page = (array)$page;
            $pid  = (int)$page['page_id'];

            $data['pages'][] = [
                'page_id'       => $pid,
                'title'         => (string)$page['title'],
                'status'        => (int)$page['status'],
                'sort_order'    => (int)$page['sort_order'],
                'date_modified' => (string)$page['date_modified'],
                'edit'          => $this->url->link('cms/page.edit|' . $pid),
                'delete'        => $this->url->link('cms/page.delete|' . $pid),
            ];
        }

        $data['add'] = $this->url->link('cms/page.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('cms/page_list', $data));
    }

    /** GET: formulário de criação · POST: valida e persiste; sucesso → lista + flash */
    public function add(): void {
        if (!$this->user->hasPermission('modify', 'cms/page') && $this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('cms/page');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('cms/page');

        $data = $this->formData([]);

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validatePage(0);

            if ($errors === []) {
                $page_id = $this->model_cms_page->addPage($this->postPayload());

                if ($page_id) {
                    $this->setFlash($this->language->get('text_success_add'));

                    $this->response->redirect($this->url->link('cms/page'));

                    return;
                }
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('cms/page.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('cms/page_form', $data));
    }

    /** GET: formulário de edição · POST: valida e persiste (args do Action são strings) */
    public function edit(string $page_id = '0'): void {
        $page_id = (int)$page_id;

        $this->load->language('cms/page');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('cms/page');

        $page = $this->model_cms_page->getPage($page_id);

        if (!$page) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $data          = $this->formData($page);
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'cms/page')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $errors = $this->validatePage($page_id);

            if ($errors === []) {
                $this->model_cms_page->editPage($page_id, $this->postPayload());

                $this->setFlash($this->language->get('text_success_edit'));

                $this->response->redirect($this->url->link('cms/page'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('cms/page.edit|' . $page_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('cms/page_form', $data));
    }

    /** Exclui via POST (GET jamais muta) */
    public function delete(string $page_id = '0'): void {
        $page_id = (int)$page_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'cms/page')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('cms/page');

        $this->load->model('cms/page');

        if ($this->model_cms_page->getPage($page_id)) {
            $this->model_cms_page->deletePage($page_id);

            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('cms/page'));
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $page Linha do banco ou [] (create)
     * @return array<string, mixed> Dados do formulário (GET) ou do POST
     */
    private function formData(array $page): array {
        $this->load->language('cms/page');

        foreach ([
            'heading_title', 'text_edit', 'text_add', 'text_general', 'text_data',
            'text_languages_note', 'entry_title', 'entry_description',
            'entry_meta_title', 'entry_meta_description', 'entry_keyword',
            'entry_status', 'entry_sort_order',
            'button_save', 'button_cancel',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error']      = '';
        $data['text_flash'] = '';

        $pid          = (int)($page['page_id'] ?? 0);
        $isPost       = $this->request->server['REQUEST_METHOD'] === 'POST';
        $post         = $this->request->post;
        $descriptions = $pid ? $this->model_cms_page->getPageDescriptions($pid) : [];
        $keywords     = $pid ? $this->model_cms_page->getPageKeywords($pid) : [];

        $data['languages'] = [];

        foreach ($this->model_cms_page->getLanguages() as $language) {
            $language = (array)$language;
            $lid      = (int)$language['language_id'];
            $saved    = (array)($descriptions[$lid] ?? []);

            $fields = [];

            foreach (['title', 'description', 'meta_title', 'meta_description'] as $f) {
                $fields[$f] = (string)($post['page_description'][$lid][$f] ?? ($saved[$f] ?? ''));
            }

            $fields['keyword'] = (string)($post['page_description'][$lid]['keyword'] ?? ($keywords[$lid] ?? ''));

            $data['languages'][] = [
                'language_id' => $lid,
                'name'        => (string)$language['name'],
                'code'        => (string)$language['code'],
                'fields'      => $fields,
            ];
        }

        $data['page_id']    = $pid;
        $data['sort_order'] = (string)($post['sort_order'] ?? (string)($page['sort_order'] ?? '0'));
        $data['status']     = isset($post['status']) ? '1' : ($isPost ? '0' : (string)(int)($page['status'] ?? 0));

        $data['cancel'] = $this->url->link('cms/page');

        return $data;
    }

    /**
     * Payload normalizado do POST (chaves SEMPRE presentes — lição F2).
     *
     * @return array{status: int, sort_order: int, descriptions: array<int, array<string, string>>}
     */
    private function postPayload(): array {
        $post = $this->request->post;

        $descriptions = [];

        foreach ((array)($post['page_description'] ?? []) as $lid => $d) {
            $d = (array)$d;

            $descriptions[(int)$lid] = [
                'title'            => mb_substr(trim((string)($d['title'] ?? '')), 0, 255),
                'description'      => trim((string)($d['description'] ?? '')),
                'meta_title'       => mb_substr(trim((string)($d['meta_title'] ?? '')), 0, 255),
                'meta_description' => mb_substr(trim((string)($d['meta_description'] ?? '')), 0, 255),
                'keyword'          => mb_substr(trim((string)($d['keyword'] ?? '')), 0, 64),
            ];
        }

        return [
            'status'       => isset($post['status']) ? 1 : 0,
            'sort_order'   => (int)($post['sort_order'] ?? 0),
            'descriptions' => $descriptions,
        ];
    }

    /**
     * @return array<int, string> Mensagens de erro (vazio = válido)
     */
    private function validatePage(int $page_id): array {
        $errors = [];
        $post   = $this->request->post;
        $desp   = (array)($post['page_description'] ?? []);

        $titleEn = trim((string)($desp[1]['title'] ?? ''));

        if ($titleEn === '' || mb_strlen($titleEn) > 255) {
            $errors[] = $this->language->get('error_title');
        }

        // keyword SEO: formato + unicidade global por idioma (seo_url compartilhada)
        foreach ($desp as $lid => $d) {
            $keyword = trim((string)(((array)$d)['keyword'] ?? ''));

            if ($keyword === '') {
                continue;
            }

            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $keyword)) {
                $errors[] = sprintf($this->language->get('error_keyword_format'), (int)$lid);
            } elseif ($this->model_cms_page->keywordTaken($keyword, (int)$lid, $page_id)) {
                $errors[] = sprintf($this->language->get('error_keyword_taken'), $keyword);
            }
        }

        return $errors;
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
