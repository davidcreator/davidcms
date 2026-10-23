<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * F4-02 (ADR 0025) — gestão de idiomas do painel.
 * Rotas: /localization/language (lista + defaults por app) e
 * .edit|{language_id}. Sem add/delete: o idioma só existe se tiver diretório
 * no filesystem (fonte da verdade).
 */
namespace Reamur\Admin\Controller\Localization;

class Language extends \Reamur\System\Engine\Controller {

    /** Lista paginada; opções de defaults sempre completas e sem mutação GET. */
    public function index(): void {
        $this->load->language('localization/language');
        if (!$this->user->hasPermission('access', 'localization/language')) {
            $this->response->redirect($this->url->link('error/permission'));
            return;
        }
        $this->load->model('localization/language');
        $this->load->model('setting/setting');
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'localization/language')) {
                $this->response->redirect($this->url->link('error/permission'));
                return;
            }
            $error = $this->csrfValid() ? $this->validateDefaults() : $this->language->get('error_csrf');
            if ($error === '') {
                $this->model_setting_setting->editSettings([
                    'config_language_catalog' => (string)($this->request->post['config_language_catalog'] ?? ''),
                    'config_language_admin' => (string)($this->request->post['config_language_admin'] ?? ''),
                ]);
                $this->flash('success', $this->language->get('text_success_defaults'));
            } else { $this->flash('error', $error); }
            $this->response->redirect($this->url->link('localization/language'));
            return;
        }
        $data = $this->chrome();
        $data['text_flash'] = $this->flashReader();
        $rawSearch = $this->request->get['filter_search'] ?? '';
        $rawStatus = $this->request->get['filter_status'] ?? 'all';
        $search = is_string($rawSearch) ? mb_substr(trim($rawSearch), 0, 255) : '';
        $status = is_string($rawStatus) && in_array($rawStatus, ['all', '0', '1'], true) ? $rawStatus : 'all';
        $requested = $this->request->get['page'] ?? 1;
        $options = ['search' => $search, 'status' => $status];
        $limit = 20; $total = $this->model_localization_language->getTotalLanguages($options);
        $page = is_scalar($requested) ? max(1, (int)$requested) : 1;
        $page = min($page, max(1, (int)ceil($total / $limit)));
        $data['languages'] = [];
        foreach ($this->model_localization_language->getLanguages($options + ['start' => ($page - 1) * $limit, 'limit' => $limit]) as $row) {
            $data['languages'][] = $this->languageRow((array)$row);
        }
        $data['language_options'] = [];
        foreach ($this->model_localization_language->getLanguages() as $row) {
            $data['language_options'][] = $this->languageRow((array)$row);
        }
        $data['default_catalog'] = (string)$this->config->get('config_language_catalog', 'en-us');
        $data['default_admin'] = (string)$this->config->get('config_language_admin', 'en-us');
        $data['action'] = $this->url->link('localization/language');
        $data['filter_action'] = $this->url->link('localization/language');
        $data['filter_reset'] = $this->url->link('localization/language');
        $data['filter_search'] = $search; $data['filter_status'] = $status; $data['total_languages'] = $total;
        $data['showing'] = $total > 0 ? sprintf($this->language->get('text_showing'), ($page - 1) * $limit + 1, min($page * $limit, $total), $total) : '';
        require_once DIR_SYSTEM . 'library/pagination.php';
        $pagination = new \Pagination(); $pagination->total = $total; $pagination->page = $page; $pagination->limit = $limit;
        $pagination->css_class = 'pagination pagination-sm mb-0';
        $pagination->text_first = $this->language->get('button_first_page');
        $pagination->text_last = $this->language->get('button_last_page');
        $pagination->text_next = $this->language->get('button_next_page');
        $pagination->text_prev = $this->language->get('button_previous_page');
        $pagination->url = $this->url->link('localization/language', 'page={page}'
            . ($search !== '' ? '&filter_search=' . rawurlencode($search) : '')
            . ($status !== 'all' ? '&filter_status=' . $status : ''));
        $data['pagination'] = str_replace('&amp;amp;', '&amp;', $pagination->render());
        $this->response->setOutput($this->load->view('localization/language_list', $data));
    }

    /** Edita metadados + status de UM idioma (parâmetro roteado .edit|N) */
    public function edit(string $language_id = '0'): void {
        $language_id = (int)$language_id;

        $this->load->language('localization/language');
        $this->load->model('localization/language');
        $this->load->model('setting/setting');

        if (!$this->user->hasPermission('access', 'localization/language')) {
            $this->response->redirect($this->url->link('error/permission'));
            return;
        }

        $language = $this->model_localization_language->getLanguage($language_id);

        if (!$language) {
            $this->flash('error', $this->language->get('error_not_found'));
            $this->response->redirect($this->url->link('localization/language'));
            return;
        }

        $error = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'localization/language')) {
                $this->response->redirect($this->url->link('error/permission'));
                return;
            }

            $errors = $this->csrfValid() ? $this->validateEdit($language_id, $language) : ['error_csrf'];

            if (!$errors) {
                $post = $this->request->post;

                $this->model_localization_language->editLanguage($language_id, [
                    'name'       => (string)$post['name'],
                    'locale'     => (string)$post['locale'],
                    'sort_order' => (int)$post['sort_order'],
                    'status'     => !empty($post['status']) ? 1 : 0,
                ]);

                $this->flash('success', $this->language->get('text_success_edit'));
                $this->response->redirect($this->url->link('localization/language'));
                return;
            }

            $error = implode("\n", array_map(
                fn(string $k): string => $this->language->get($k),
                $errors
            ));
        }

        $post = $this->request->post;
        $data = $this->chrome();
        $data['error'] = $error;

        $data['text_flash'] = ['type' => '', 'text' => ''];

        $data['entry'] = array_replace($language, [
            'name'       => (string)($post['name'] ?? $language['name']),
            'locale'     => (string)($post['locale'] ?? $language['locale']),
            'sort_order' => (int)($post['sort_order'] ?? $language['sort_order']),
            'status'     => $this->request->server['REQUEST_METHOD'] === 'POST' ? (!empty($post['status']) ? 1 : 0) : (int)$language['status'],
        ]);
        $data['entry']['code']       = (string)$language['code'];
        $data['entry']['dir_exists'] = is_dir(DIR_APPLICATION . 'language/' . $data['entry']['code']);

        $data['is_default_catalog'] = $data['entry']['code'] === (string)$this->config->get('config_language_catalog', 'en-us');
        $data['is_default_admin']   = $data['entry']['code'] === (string)$this->config->get('config_language_admin', 'en-us');

        $data['entry_name_label']       = $this->language->get('entry_name');
        $data['entry_code_label']       = $this->language->get('entry_code');
        $data['entry_locale_label']     = $this->language->get('entry_locale');
        $data['entry_sort_order_label'] = $this->language->get('entry_sort_order');
        $data['entry_status_label']     = $this->language->get('entry_status');
        $data['text_default_locked']    = $this->language->get('text_default_locked');

        $data['action'] = $this->url->link('localization/language.edit|' . $language_id);
        $data['back']   = $this->url->link('localization/language');

        $this->response->setOutput($this->load->view('localization/language_form', $data));
    }

    /** Defaults proibidos para idioma status=0 */
    private function validateDefaults(): string {
        $post = $this->request->post;

        $catalog = (string)($post['config_language_catalog'] ?? '');
        $admin   = (string)($post['config_language_admin'] ?? '');

        $enabled = [];
        foreach ($this->model_localization_language->getLanguages() as $row) {
            if ((int)$row['status'] === 1) {
                $enabled[] = (string)$row['code'];
            }
        }

        if ($catalog === '' || $admin === '') {
            return $this->language->get('error_defaults_required');
        }

        if (!in_array($catalog, $enabled, true) || !in_array($admin, $enabled, true)) {
            return $this->language->get('error_default_disabled');
        }

        return '';
    }

    /**
     * @param array<string, mixed> $language Linha atual (pré-edição)
     * @return array<int, string> chaves de idioma de erro
     */
    private function validateEdit(int $language_id, array $language): array {
        $errors = [];
        $post   = $this->request->post;

        $name   = trim((string)($post['name'] ?? ''));
        $locale = trim((string)($post['locale'] ?? ''));
        $status = !empty($post['status']) ? 1 : 0;

        if ($name === '' || mb_strlen($name) > 32) {
            $errors[] = 'error_name';
        }

        if ($locale === '' || mb_strlen($locale) > 32) {
            $errors[] = 'error_locale';
        }

        // Não pode desativar o idioma que É o default vigente de qualquer app
        $code = (string)$language['code'];

        if ($status === 0) {
            $defaults = [
                (string)$this->config->get('config_language_catalog', 'en-us'),
                (string)$this->config->get('config_language_admin', 'en-us'),
            ];

            if (in_array($code, $defaults, true)) {
                $errors[] = 'error_disable_default';
            }
        }

        return $errors;
    }

    /** Projeção da grade/opções sem alterar dados, diretórios ou códigos. */
    private function languageRow(array $row): array {
        $code = (string)$row['code'];
        return ['language_id' => (int)$row['language_id'], 'name' => (string)$row['name'], 'code' => $code,
            'locale' => (string)$row['locale'], 'sort_order' => (int)$row['sort_order'], 'status' => (int)$row['status'],
            'dir_exists' => is_dir(DIR_APPLICATION . 'language/' . $code),
            'edit' => $this->url->link('localization/language.edit|' . (int)$row['language_id'])];
    }

    private function csrfValid(): bool {
        return \Reamur\System\Library\Security\Csrf::check($this->session, $this->request->post['csrf_token'] ?? '');
    }

    /** Chrome comum (header/footer + breadcrumbs), padrão dos CRUDs admin */
    private function chrome(): array {
        $data = [];
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);
        $data['error'] = '';
        $data['can_modify'] = $this->user->hasPermission('modify', 'localization/language');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        foreach (['button_filter', 'button_reset', 'button_close', 'text_all_status', 'text_search_placeholder',
            'text_empty_title', 'text_empty_hint', 'text_readonly_notice'] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $this->document->setTitle($data['heading_title']);

        $data['breadcrumbs'] = [
            ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard')],
            ['text' => $data['heading_title'],            'href' => $this->url->link('localization/language')],
        ];

        $data['column_name']       = $this->language->get('column_name');
        $data['column_code']       = $this->language->get('column_code');
        $data['column_locale']     = $this->language->get('column_locale');
        $data['column_sort_order'] = $this->language->get('column_sort_order');
        $data['column_status']     = $this->language->get('column_status');
        $data['column_action']     = $this->language->get('column_action');

        $data['text_list']           = $this->language->get('text_list');
        $data['text_no_results']     = $this->language->get('text_no_results');
        $data['text_enabled']        = $this->language->get('text_enabled');
        $data['text_disabled']       = $this->language->get('text_disabled');
        $data['text_dir_missing']    = $this->language->get('text_dir_missing');
        $data['text_default_catalog'] = $this->language->get('text_default_catalog');
        $data['text_default_admin']   = $this->language->get('text_default_admin');
        $data['text_defaults_note']  = $this->language->get('text_defaults_note');

        $data['button_save']   = $this->language->get('button_save');
        $data['button_edit']   = $this->language->get('button_edit');
        $data['button_cancel'] = $this->language->get('button_cancel');

        $data['header'] = $this->load->controller('common/header', $data);
        $data['footer'] = $this->load->controller('common/footer');

        return $data;
    }

    /** flash + reader no padrão da casa (same session bag) */
    private function flash(string $type, string $text): void {
        $this->session->data['flash'] = ['type' => $type, 'text' => $text];
    }

    /** @return array{type: string, text: string} */
    private function flashReader(): array {
        $flash = (array)($this->session->data['flash'] ?? []);
        unset($this->session->data['flash']);

        return [
            'type' => (string)($flash['type'] ?? ''),
            'text' => (string)($flash['text'] ?? ''),
        ];
    }
}
