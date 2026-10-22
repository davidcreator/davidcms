<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * blog/import — Importador WordPress (F7-03, ADR 0044) do blog interno.
 * Duas ações no mesmo formulário (action=preview|run):
 *  · preview → lê o endpoint público e MOSTRA as decisões (importar/pular/
 *    sufixo), sem escrever nada no banco nem no disco;
 *  · run     → materializa: blog_post + description (language_id=1 =
 *    âncora do coalesce) + keyword seo_url + imagem local.
 * Gate: hasPermission('modify', 'blog/blog') — importar é operação destrutiva-
 * criativa do próprio módulo blog (sem grant separado, ADR 0044 §ACL).
 * Rotas: GET /blog/import (form) · POST action no mesmo endpoint.
 */
namespace Reamur\Admin\Controller\Blog;

class Import extends \Reamur\System\Engine\Controller {

    /** WP REST REST-público padrão (ADRs 0002/0044) */
    private const DEFAULT_BASE = 'https://davidcreator.com';

    public function index(): void {
        $this->load->language('blog/import');

        $this->document->setTitle($this->language->get('heading_title'));

        if (!$this->user->hasPermission('modify', 'blog/blog')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->model('blog/import');

        $data = $this->baseData();

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $action = (string)($this->request->post['action'] ?? '');

            if ($action === 'preview' || $action === 'run') {
                $this->handle($data, $action === 'run');
            } else {
                $data['error'] = $this->language->get('error_action');
            }
        }

        $this->chrome($data);

        $this->response->setOutput($this->load->view('blog/blog_import', $data));
    }

    /**
     * Executa preview (commit=false) ou run (commit=true) e monta o relatório.
     *
     * @param array<string, mixed> $data
     */
    private function handle(array &$data, bool $commit): void {
        $base_url = trim((string)($this->request->post['base_url'] ?? ''));
        $page     = (int)($this->request->post['page'] ?? 1);
        $per_page = (int)($this->request->post['per_page'] ?? 50);

        if ($base_url === '') {
            $data['error'] = $this->language->get('error_base_url');

            return;
        }

        $res = $this->model_blog_import->fetchPage($base_url, $page, $per_page);

        $data['form']['base_url']   = $base_url;
        $data['form']['page']       = max(1, $page);
        $data['form']['per_page']   = min(100, max(1, $per_page));
        $posted_public = trim((string)($this->request->post['public_url'] ?? ''));

        $data['form']['public_url'] = $posted_public !== ''
            ? $posted_public
            : $this->model_blog_import->defaultPublicUrl();

        if ($res['error'] !== '') {
            $data['error'] = sprintf($this->language->get('error_fetch'), $res['error']);

            return;
        }

        $data['rows'] = [];

        $tallies = ['imported' => 0, 'import_with_suffix' => 0, 'import' => 0, 'skip' => 0, 'error' => 0];

        foreach ((array)$res['posts'] as $post) {
            $post   = (array)$post;
            $result = $this->model_blog_import->importOne($post, $commit);

            $key = (string)$result['action'];

            if (!isset($tallies[$key])) {
                $tallies[$key] = 0;
            }

            $tallies[$key]++;

            $data['rows'][] = [
                'wp_id'     => (string)($post['wp_id'] ?? ''),
                'title'     => (string)($post['title'] ?? ''),
                'date'      => (string)($post['date'] ?? ''),
                'keyword'   => (string)$result['keyword'],
                'action'    => (string)$result['action'],
                'reason'    => (string)$result['reason'],
                'image'     => (string)$result['image'],
                'has_image' => (string)($post['image_url'] ?? '') !== '',
                'href'      => in_array((string)$result['action'], ['imported', 'import_with_suffix'], true)
                    && (int)$result['post_id'] > 0
                    ? $this->url->link('blog/blog.edit|' . (int)$result['post_id'])
                    : '',
            ];
        }

        $data['tallies']	 = $tallies;
        $data['log_lines'] = $this->model_blog_import->getLog();

        if ($commit) {
            // Mapa 301 por URL nova absoluta — base pública editável no form
            $public_url = trim((string)($this->request->post['public_url'] ?? ''));

            if ($public_url === '') {
                $public_url = $this->model_blog_import->defaultPublicUrl();
            }

            $bytes = $this->model_blog_import->writeRedirectMap($public_url);

            $data['redirect_map_bytes'] = $bytes;
            $data['summary'] = sprintf(
                $this->language->get('text_summary_run'),
                $tallies['imported'],
                $tallies['import_with_suffix'],
                $tallies['skip'],
                $tallies['error'],
                $bytes > 0 ? sprintf($this->language->get('text_map_written'), $bytes) : $this->language->get('text_map_failed')
            );
        } else {
            $data['summary'] = sprintf(
                $this->language->get('text_summary_preview'),
                $tallies['import'] + $tallies['import_with_suffix'],
                $tallies['skip'],
                $tallies['import_with_suffix']
            );
        }

        $data['ran'] = true;
        $data['was_commit'] = $commit;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseData(): array {
        foreach ([
            'heading_title', 'text_form_intro', 'text_preview', 'text_run',
            'entry_base_url', 'entry_page', 'entry_per_page', 'help_base_url',
            'help_page', 'help_per_page',
            'column_wp_id', 'column_title', 'column_date', 'column_keyword',
            'column_action_taken', 'column_image', 'column_status_label',
            'text_summary_header', 'error_base_url', 'error_action',
            'error_fetch', 'text_summary_preview', 'text_summary_run',
            'text_map_written', 'text_map_failed', 'entry_public_url', 'help_public_url',
            'text_actions', 'text_action_imported', 'text_action_import_with_suffix',
            'text_action_skip', 'text_action_error',
            'button_back_list', 'button_submit',
            'text_no_rows', 'text_log_header', 'text_imported_edit',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error']        = '';
        $data['ran']          = false;
        $data['was_commit']   = false;
        $data['rows']         = [];
        $data['tallies']      = [];
        $data['log_lines']    = [];
        $data['summary']      = '';
        $data['redirect_map_bytes'] = 0;

        $data['form'] = [
            'base_url'   => self::DEFAULT_BASE,
            'page'       => 1,
            'per_page'   => 50,
            'public_url' => $this->model_blog_import->defaultPublicUrl(),
        ];

        $data['back']  = $this->url->link('blog/blog');
        $data['action']= $this->url->link('blog/import');

        return $data;
    }

    /** Chrome compartilhado + estilo do módulo */
    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');
    }

    /** log do model já vem formatado; sem suco extra */
}
