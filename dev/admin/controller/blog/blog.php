<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * blog/blog — CRUD do blog interno (F7-01, ADR 0042) no molde cms/page
 * (F2-10): method-grammar, ACL base-route, multi-idioma, seo_url global.
 * Diferenças do page: ordering cronológico (date_published), imagem destaque
 * como campo texto (URL/path; upload real segue com o importador F7-03),
 * e SEM sort_order (o editorial é a data).
 * Rotas: /blog/blog · .add · .edit|N · .delete|N (POST).
 */
namespace Reamur\Admin\Controller\Blog;

class Blog extends \Reamur\System\Engine\Controller {

    /** Lista posts (200) */
    public function index(): void {
        $this->load->language('blog/blog');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('blog/blog');

        $data['heading_title']        = $this->language->get('heading_title');
        $data['text_list']            = $this->language->get('text_list');
        $data['text_no_results']      = $this->language->get('text_no_results');
        $data['text_confirm_delete']  = $this->language->get('text_confirm_delete');
        $data['text_enabled']         = $this->language->get('text_enabled');
        $data['text_disabled']        = $this->language->get('text_disabled');
        $data['column_title']         = $this->language->get('column_title');
        $data['column_status']        = $this->language->get('column_status');
        $data['column_date_published'] = $this->language->get('column_date_published');
        $data['column_date_modified'] = $this->language->get('column_date_modified');
        $data['column_action']        = $this->language->get('column_action');
        $data['button_add']           = $this->language->get('button_add');
        $data['button_edit']          = $this->language->get('button_edit');
        $data['button_delete']        = $this->language->get('button_delete');
        $data['text_flash']           = $this->flash();

        // F7-03 (ADR 0044): acesso ao importador WP somente com modify no módulo
        $data['button_import_wp'] = $this->language->get('button_import_wp');
        $data['import_href']      = $this->user->hasPermission('modify', 'blog/blog')
            ? $this->url->link('blog/import')
            : '';

        $data['posts'] = [];

        foreach ($this->model_blog_blog->getPosts() as $post) {
            $post = (array)$post;
            $pid  = (int)$post['post_id'];

            $data['posts'][] = [
                'post_id'        => $pid,
                'title'          => (string)$post['title'],
                'status'         => (int)$post['status'],
                'date_published' => (string)$post['date_published'],
                'date_modified'  => (string)$post['date_modified'],
                'edit'           => $this->url->link('blog/blog.edit|' . $pid),
                'delete'         => $this->url->link('blog/blog.delete|' . $pid),
            ];
        }

        $data['add'] = $this->url->link('blog/blog.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('blog/blog_list', $data));
    }

    /** GET: formulário de criação · POST: valida e persiste; sucesso → lista + flash */
    public function add(): void {
        if (!$this->user->hasPermission('modify', 'blog/blog') && $this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('blog/blog');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('blog/blog');

        $data = $this->formData([]);

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validatePost(0);

            if ($errors === []) {
                $post_id = $this->model_blog_blog->addPost($this->postPayload());

                if ($post_id) {
                    $this->setFlash($this->language->get('text_success_add'));

                    $this->response->redirect($this->url->link('blog/blog'));

                    return;
                }
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('blog/blog.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('blog/blog_form', $data));
    }

    /** GET: formulário de edição · POST: valida e persiste (args do Action são strings) */
    public function edit(string $post_id = '0'): void {
        $post_id = (int)$post_id;

        $this->load->language('blog/blog');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('blog/blog');

        $post = $this->model_blog_blog->getPost($post_id);

        if (!$post) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $data          = $this->formData($post);
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'blog/blog')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $errors = $this->validatePost($post_id);

            if ($errors === []) {
                $this->model_blog_blog->editPost($post_id, $this->postPayload());

                $this->setFlash($this->language->get('text_success_edit'));

                $this->response->redirect($this->url->link('blog/blog'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('blog/blog.edit|' . $post_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('blog/blog_form', $data));
    }

    /** POST exclusivo (F2-6: delete só por POST, Action args string) */
    public function delete(string $post_id = '0'): void {
        $post_id = (int)$post_id;

        $this->load->language('blog/blog');

        $this->load->model('blog/blog');

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->user->hasPermission('modify', 'blog/blog')) {
            if ($this->model_blog_blog->getPost($post_id)) {
                $this->model_blog_blog->deletePost($post_id);

                $this->setFlash($this->language->get('text_success_delete'));
            }
        }

        $this->response->redirect($this->url->link('blog/blog'));
    }

    /**
     * Monta os dados do form (criação e edição compartilham).
     *
     * @param array<string, mixed> $post Linha main (vazio = criação)
     */
    private function formData(array $post): array {
        $this->load->language('blog/blog');

        foreach ([
            'heading_title', 'text_edit', 'text_add', 'text_general', 'text_data',
            'text_languages_note', 'entry_title', 'entry_description',
            'entry_meta_title', 'entry_meta_description', 'entry_keyword',
            'entry_image', 'entry_date_published', 'entry_status', 'help_image', 'help_date_published',
            'button_save', 'button_cancel',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error']      = '';
        $data['text_flash'] = '';

        $pid          = (int)($post['post_id'] ?? 0);
        $isPost       = $this->request->server['REQUEST_METHOD'] === 'POST';
        $posted       = $this->request->post;
        $descriptions = $pid ? $this->model_blog_blog->getPostDescriptions($pid) : [];
        $keywords     = $pid ? $this->model_blog_blog->getPostKeywords($pid) : [];

        $data['languages'] = [];

        foreach ($this->model_blog_blog->getLanguages() as $language) {
            $language = (array)$language;
            $lid      = (int)$language['language_id'];
            $saved    = (array)($descriptions[$lid] ?? []);

            $fields = [];

            foreach (['title', 'description', 'meta_title', 'meta_description'] as $f) {
                $fields[$f] = (string)($posted['blog_description'][$lid][$f] ?? ($saved[$f] ?? ''));
            }

            $fields['keyword'] = (string)($posted['blog_description'][$lid]['keyword'] ?? ($keywords[$lid] ?? ''));

            $data['languages'][] = [
                'language_id' => $lid,
                'name'        => (string)$language['name'],
                'code'        => (string)$language['code'],
                'fields'      => $fields,
            ];
        }

        $data['post_id']        = $pid;
        $data['image']          = (string)($posted['image'] ?? (string)($post['image'] ?? ''));
        $data['date_published'] = (string)($posted['date_published'] ?? (string)($post['date_published'] ?? date('Y-m-d H:i:s')));
        $data['status']         = isset($posted['status']) ? '1' : ($isPost ? '0' : (string)(int)($post['status'] ?? 0));

        $data['cancel'] = $this->url->link('blog/blog');

        return $data;
    }

    /**
     * Payload normalizado do POST (chaves SEMPRE presentes — lição F2).
     *
     * @return array{image: string, status: int, date_published: string, descriptions: array<int, array<string, string>>}
     */
    private function postPayload(): array {
        $posted = $this->request->post;

        $descriptions = [];

        foreach ((array)($posted['blog_description'] ?? []) as $lid => $d) {
            $d = (array)$d;

            $descriptions[(int)$lid] = [
                'title'            => mb_substr(trim((string)($d['title'] ?? '')), 0, 255),
                'description'      => trim((string)($d['description'] ?? '')),
                'meta_title'       => mb_substr(trim((string)($d['meta_title'] ?? '')), 0, 255),
                'meta_description' => mb_substr(trim((string)($d['meta_description'] ?? '')), 0, 255),
                'keyword'          => mb_substr(trim((string)($d['keyword'] ?? '')), 0, 64),
            ];
        }

        $date = trim((string)($posted['date_published'] ?? ''));

        if ($date !== '') {
            // datetime-local envia 'Y-m-d\TH:i' — normaliza para MySQL 'Y-m-d H:i:s'
            $date = str_replace('T', ' ', $date);

            if (strlen($date) === 16) {
                $date .= ':00';
            }
        } else {
            $date = date('Y-m-d H:i:s');
        }

        return [
            'image'          => mb_substr(trim((string)($posted['image'] ?? '')), 0, 255),
            'status'         => isset($posted['status']) ? 1 : 0,
            'date_published' => $date,
            'descriptions'   => $descriptions,
        ];
    }

    /**
     * @return array<int, string> Mensagens de erro (vazio = válido)
     */
    private function validatePost(int $post_id): array {
        $errors = [];
        $posted = $this->request->post;
        $desp   = (array)($posted['blog_description'] ?? []);

        $titleEn = trim((string)($desp[1]['title'] ?? ''));

        if ($titleEn === '' || mb_strlen($titleEn) > 255) {
            $errors[] = $this->language->get('error_title');
        }

        $date = trim((string)($posted['date_published'] ?? ''));

        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?)?$/', $date)) {
            $errors[] = $this->language->get('error_date_published');
        }

        // keyword SEO: formato + unicidade global por idioma (seo_url compartilhada)
        foreach ($desp as $lid => $d) {
            $keyword = trim((string)(((array)$d)['keyword'] ?? ''));

            if ($keyword === '') {
                continue;
            }

            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $keyword)) {
                $errors[] = sprintf($this->language->get('error_keyword_format'), (int)$lid);
            } elseif ($this->model_blog_blog->keywordTaken($keyword, (int)$lid, $post_id)) {
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
    }
}
