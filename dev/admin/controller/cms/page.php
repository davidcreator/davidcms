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

    /** Lista páginas com filtros e paginação server-side (F8-06). */
    public function index(): void {
        $this->load->language('cms/page');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('cms/page');

        foreach ([
            'heading_title', 'text_list', 'text_no_results', 'text_confirm_delete',
            'text_enabled', 'text_disabled', 'column_title', 'column_status',
            'column_sort_order', 'column_date_modified', 'column_action',
            'button_add', 'button_edit', 'button_delete', 'button_filter', 'button_reset', 'button_close',
            'text_all_status', 'text_search_placeholder', 'text_showing',
            'text_empty_title', 'text_empty_hint',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['text_flash'] = $this->flash();
        $data['can_modify'] = $this->user->hasPermission('modify', 'cms/page');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);

        $title = $this->request->get['filter_title'] ?? '';
        $status = $this->request->get['filter_status'] ?? 'all';
        $requestedPage = $this->request->get['page'] ?? 1;
        $filter_title = is_string($title) ? mb_substr(trim($title), 0, 255) : '';
        $filter_status = is_string($status) && in_array($status, ['all', '0', '1'], true) ? $status : 'all';
        $filters = ['title' => $filter_title, 'status' => $filter_status];
        $limit = 20;
        $total = $this->model_cms_page->getTotalPages($filters);
        $page = is_scalar($requestedPage) ? max(1, (int)$requestedPage) : 1;
        $page = min($page, max(1, (int)ceil($total / $limit)));

        $data['pages'] = [];
        foreach ($this->model_cms_page->getPages($filters + ['start' => ($page - 1) * $limit, 'limit' => $limit]) as $row) {
            $row = (array)$row;
            $pid = (int)$row['page_id'];
            $data['pages'][] = [
                'page_id' => $pid,
                'title' => (string)$row['title'],
                'status' => (int)$row['status'],
                'sort_order' => (int)$row['sort_order'],
                'date_modified' => (string)$row['date_modified'],
                'edit' => $this->url->link('cms/page.edit|' . $pid),
                'delete' => $this->url->link('cms/page.delete|' . $pid),
            ];
        }

        $data['add'] = $this->url->link('cms/page.add');
        $data['total_pages'] = $total;
        $data['filter_title'] = $filter_title;
        $data['filter_status'] = $filter_status;
        $data['filter_action'] = $this->url->link('cms/page');
        $data['filter_reset'] = $this->url->link('cms/page');
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
        $pagination->url = $this->url->link('cms/page', 'page={page}'
            . ($filter_title !== '' ? '&filter_title=' . rawurlencode($filter_title) : '')
            . ($filter_status !== 'all' ? '&filter_status=' . $filter_status : ''));
        // Pagination escapa o href duas vezes. Normalização local; não alterar o núcleo.
        $data['pagination'] = str_replace('&amp;amp;', '&amp;', $pagination->render());

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

        $data = $this->formData([], true);
        $data['text_flash'] = $this->flash();

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $errors = $this->csrfValid() ? array_merge($this->validatePage(0), $this->validateBlockSubmission(0)) : [$this->language->get('error_csrf')];

            if ($errors === []) {
                $page_id = $this->model_cms_page->addPage($this->postPayload());

                if ($page_id) {
                    $valuesSaved = $this->model_custom_field_value->savePageValues($page_id, (array)($this->request->post['custom_field'] ?? []));
                    $this->setFlash($this->language->get($valuesSaved ? 'text_success_add' : 'error_custom_field_save'), $valuesSaved ? 'success' : 'error');

                    $this->response->redirect($this->url->link('cms/page.edit|' . $page_id));

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

        $data          = $this->formData($page, true);
        $data['text_flash'] = $this->flash();
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'cms/page')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $errors = $this->csrfValid() ? array_merge($this->validatePage($page_id), $this->validateBlockSubmission($page_id)) : [$this->language->get('error_csrf')];

            if ($errors === []) {
                $this->model_cms_page->editPage($page_id, $this->postPayload($page_id));
                $valuesSaved = $this->model_custom_field_value->savePageValues($page_id, (array)($this->request->post['custom_field'] ?? []));

                $this->setFlash($this->language->get($valuesSaved ? 'text_success_edit' : 'error_custom_field_save'), $valuesSaved ? 'success' : 'error');

                $this->response->redirect($this->url->link('cms/page.edit|' . $page_id));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('cms/page.edit|' . $page_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('cms/page_form', $data));
    }

    /** Compatibilidade com links antigos: a edição agora acontece na tela geral unificada. */
    public function content(string $page_id = '0'): void {
        $page_id = (int)$page_id;
        $this->response->redirect($this->url->link('cms/page.edit|' . $page_id));
    }

    /** Exclui via POST (GET jamais muta) */
    public function delete(string $page_id = '0'): void {
        $page_id = (int)$page_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'cms/page')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('cms/page');
        if (!$this->csrfValid()) {
            $this->setFlash($this->language->get('error_csrf'), 'error');
            $this->response->redirect($this->url->link('cms/page'));
            return;
        }

        $this->load->model('cms/page');
        $this->load->model('custom_field/value');

        if ($this->model_cms_page->getPage($page_id)) {
            if (!$this->model_custom_field_value->deletePageValues($page_id)) {
                $this->setFlash($this->language->get('error_custom_field_delete'), 'error');
                $this->response->redirect($this->url->link('cms/page'));
                return;
            }
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
    private function formData(array $page, bool $includeEditor = false): array {
        $this->load->language('cms/page');

        foreach ([
            'heading_title', 'text_edit', 'text_add', 'text_general', 'text_data',
            'text_languages_note', 'entry_title', 'entry_description',
            'entry_meta_title', 'entry_meta_description', 'entry_keyword',
            'entry_status', 'entry_sort_order',
            'button_save', 'button_cancel', 'text_enabled', 'text_disabled',
            'text_meta_group', 'help_sort_order', 'help_description', 'text_content_summary_note', 'text_content_empty', 'text_content_save_first', 'text_content_block_count', 'text_content_invalid', 'text_page_content_editor', 'text_content_editor_help', 'button_edit_content', 'button_save_content', 'text_editor_home', 'text_editor_insert', 'text_editor_view', 'text_editor_clipboard', 'text_editor_text_format', 'text_editor_paragraph_alignment', 'text_editor_group_insert', 'text_editor_group_panels', 'text_editor_group_preview', 'text_editor_close_panel', 'text_editor_align_left', 'text_editor_align_center', 'text_editor_align_right', 'text_editor_import_file', 'text_editor_import_success', 'text_editor_preview_title', 'text_editor_preview_mode', 'text_editor_edit_mode', 'text_editor_import_hint', 'text_editor_import_unavailable', 'text_editor_import_error', 'text_editor_import_audio_library', 'text_editor_import_video_library', 'text_editor_preview_video_link', 'text_editor_preview_video_open', 'text_custom_fields', 'text_custom_fields_note', 'text_shared_value', 'text_select_option', 'text_yes', 'text_no',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $this->document->addScript($this->config->get('site_url') . 'view/js/cms-page.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        if ($includeEditor) {
            $this->load->language('blog/blog');
            foreach ([
                'heading_title', 'text_edit', 'text_add', 'text_general', 'text_data',
                'text_languages_note', 'entry_title', 'entry_description',
                'entry_meta_title', 'entry_meta_description', 'entry_keyword',
                'entry_image', 'entry_date_published', 'entry_status', 'help_image', 'help_date_published',
                'button_save', 'button_cancel',
                // F8-02: publish sidebar + tabs + contadores + preview de SEO/capa
                'text_publish_card', 'text_published', 'text_draft', 'text_content_card',
                'text_meta_group', 'text_seo_url_preview', 'text_cover_preview',
                'text_status_help', 'text_chars',
                // F8-05: biblioteca de mídia restrita às imagens do Blog
                'text_media_library', 'text_media_browse', 'text_media_upload', 'text_media_create_folder',
                'text_media_external_url', 'text_media_loading', 'text_media_empty', 'text_media_no_matches',
                'text_media_page', 'text_media_confirm_delete_file', 'text_media_confirm_delete_folder', 'text_media_in_use',
                'text_media_upload_success', 'text_media_folder_created', 'text_media_file_deleted',
                'text_media_folder_deleted', 'text_media_upload_hint', 'text_media_no_folders',
                'text_download_library', 'text_download_empty', 'text_download_upload', 'text_download_hint', 'text_download_limit', 'button_download_select', 'button_download_upload',
                'text_audio_library', 'text_audio_empty', 'text_audio_hint', 'text_audio_drag_hint', 'text_audio_upload_hint', 'button_audio_select', 'button_audio_upload', 'error_audio_generic', 'error_audio_upload_type', 'text_video_library', 'text_video_empty', 'text_video_hint', 'text_video_upload_hint', 'text_video_drag_hint', 'button_video_select', 'button_video_upload', 'error_video_generic', 'error_video_upload_type', 'error_audio_video_upload', 'error_audio_video_root', 'error_media_upload_no_file',
                'error_download_upload_no_file', 'error_download_upload_size', 'error_download_upload_type', 'error_download_upload_move', 'error_download_generic',
                'entry_media_search', 'entry_media_folder_name', 'entry_media_external_url',
                'button_media_library', 'button_media_upload', 'button_media_create_folder',
                'button_media_use_url', 'button_media_select', 'button_media_delete',
                'button_media_back', 'button_media_previous', 'button_media_next', 'button_media_close',
                'text_block_editor_intro', 'text_block_conversion_note', 'text_block_review_required', 'text_block_invalid', 'text_block_saved',
                'error_block_schema_unavailable', 'error_block_document_invalid', 'error_block_legacy_review', 'error_block_mode_invalid', 'error_block_editor_unavailable',
                'text_inline_bold', 'text_inline_italic', 'text_inline_underline', 'text_inline_strike', 'text_inline_code', 'text_inline_link',
                'text_inline_link_placeholder', 'text_inline_apply_link', 'text_inline_remove_link', 'text_inline_invalid_link',
                'text_inline_toolbar', 'text_inline_text_label', 'text_inline_placeholder', 'text_inline_editor_label',
                'text_block_add', 'text_block_search', 'text_block_no_results', 'text_block_category_content', 'text_block_category_media', 'text_block_category_layout',
                'text_block_outline', 'text_block_outline_empty', 'text_block_outline_label', 'text_block_initial_invalid', 'text_block_document_too_large', 'text_block_move_up', 'text_block_move_down', 'text_block_duplicate', 'text_block_transform',
                'text_block_remove', 'text_block_add_after', 'text_block_drag', 'text_block_type_paragraph', 'text_block_type_heading', 'text_block_type_list',
                'text_block_type_image', 'text_block_type_file', 'text_block_file_source', 'text_block_file_choose', 'text_block_type_audio', 'text_block_audio_source', 'text_block_audio_choose', 'text_block_type_video_file', 'text_block_video_file_source', 'text_block_video_file_choose', 'text_block_type_threejs_thumb', 'text_block_threejs_label', 'text_block_threejs_scene_config', 'text_block_threejs_config_hint', 'text_block_threejs_preview_hint', 'text_block_threejs_objects', 'text_block_threejs_lights', 'text_block_threejs_addShape', 'text_block_threejs_addLight', 'text_block_threejs_remove', 'text_block_threejs_color', 'text_block_threejs_animation', 'text_block_threejs_intensity', 'text_block_threejs_invalidScene', 'text_block_threejs_scriptLabel', 'text_block_threejs_scriptHint', 'text_block_threejs_editInline', 'text_block_threejs_closeInline', 'text_block_threejs_deleteScript', 'text_block_threejs_scriptDeleted', 'text_block_threejs_scriptPlaceholder', 'text_block_threejs_addScript', 'text_block_type_callout', 'text_block_type_details', 'text_block_type_spacer', 'text_block_type_code', 'text_block_code_label', 'text_block_code_default', 'text_block_code_too_large', 'text_block_spacer_height', 'text_block_spacer_16', 'text_block_spacer_32', 'text_block_spacer_48', 'text_block_spacer_64', 'text_block_spacer_96', 'text_block_details_summary_label', 'text_block_details_summary_default', 'text_block_details_default', 'text_block_callout_default', 'text_block_callout_tone', 'text_block_callout_tone_info', 'text_block_callout_tone_success', 'text_block_callout_tone_warning', 'text_block_callout_tone_danger', 'text_block_type_quote', 'text_block_type_video', 'text_block_type_button', 'text_block_type_separator',
                'text_block_type_group', 'text_block_type_columns', 'text_block_type_list_item', 'text_block_type_column', 'text_block_transform_hint', 'text_block_insert_empty',
                'text_block_status_inserted', 'text_block_status_moved', 'text_block_status_duplicated', 'text_block_status_transformed',
                'text_block_status_removed', 'text_block_status_undo', 'text_block_status_redo',
                'text_block_undo', 'text_block_redo', 'text_block_indent', 'text_block_outdent', 'text_block_status_indented', 'text_block_status_outdented', 'text_block_hierarchy_move_unavailable', 'text_block_copy', 'text_block_paste', 'text_block_status_copied', 'text_block_status_pasted', 'text_block_paste_unavailable', 'text_block_navigation_path', 'text_block_navigation_row', 'text_block_navigation_cell', 'text_block_status_focus_changed', 'text_block_outline_keyboard_hint', 'text_block_status_outline_changed', 'text_block_keyboard_shortcuts_title', 'text_block_keyboard_shortcuts', 'text_block_preview_desktop', 'text_block_preview_tablet', 'text_block_preview_mobile',
                'text_block_editor_tools', 'text_block_canvas', 'text_block_canvas_empty', 'text_block_inspector', 'text_block_inspector_empty', 'text_block_column_count',
                'text_block_type_gallery', 'text_block_type_cover', 'text_block_type_table', 'text_block_table_header', 'text_block_table_add_row', 'text_block_table_remove_row', 'text_block_table_add_column', 'text_block_table_remove_column', 'text_block_table_toggle_header', 'text_block_table_cell_label', 'text_block_caption', 'text_block_alignment', 'text_block_image_size', 'text_block_mobile_size',
                'text_block_gallery_columns', 'text_block_media_source', 'text_block_image_alt', 'text_block_image_title',
                'text_block_align_left', 'text_block_align_center', 'text_block_align_right', 'text_block_align_content', 'text_block_align_wide', 'text_block_align_full',
                'text_block_size_small', 'text_block_size_medium', 'text_block_size_large', 'text_block_size_full',
                'text_block_mobile_full', 'text_block_mobile_large', 'text_block_mobile_medium',
                'text_block_video_preview', 'text_block_video_preview_hint', 'text_block_media_placeholder',
                'text_block_pattern_category', 'text_block_pattern_two_columns', 'text_block_pattern_quote_cta', 'text_block_pattern_section_intro',
                'text_block_pattern_cover_hero', 'text_block_pattern_hint', 'text_block_save_state_saved', 'text_block_save_state_unsaved', 'text_block_save_state_saving',
            ] as $key) {
                $data[$key] = $this->language->get($key);
            }

            $data['block_editor_strings'] = [
                'bold' => $data['text_inline_bold'], 'italic' => $data['text_inline_italic'],
                'underline' => $data['text_inline_underline'], 'strike' => $data['text_inline_strike'], 'code' => $data['text_inline_code'],
                'link' => $data['text_inline_link'], 'linkPlaceholder' => $data['text_inline_link_placeholder'],
                'applyLink' => $data['text_inline_apply_link'], 'removeLink' => $data['text_inline_remove_link'],
                'invalidLink' => $data['text_inline_invalid_link'], 'toolbar' => $data['text_inline_toolbar'],
                'textLabel' => $data['text_inline_text_label'], 'placeholder' => $data['text_inline_placeholder'],
                'editorLabel' => $data['text_inline_editor_label'],
                'ribbonHome' => $data['text_editor_home'], 'ribbonInsert' => $data['text_editor_insert'], 'ribbonView' => $data['text_editor_view'],
                'groupClipboard' => $data['text_editor_clipboard'], 'groupTextFormat' => $data['text_editor_text_format'], 'groupParagraphAlign' => $data['text_editor_paragraph_alignment'],
                'groupInsert' => $data['text_editor_group_insert'], 'groupPanels' => $data['text_editor_group_panels'], 'groupPreview' => $data['text_editor_group_preview'],
                'closePanel' => $data['text_editor_close_panel'], 'importFile' => $data['text_editor_import_file'], 'importSuccess' => $data['text_editor_import_success'], 'previewTitle' => $data['text_editor_preview_title'], 'previewMode' => $data['text_editor_preview_mode'], 'editMode' => $data['text_editor_edit_mode'], 'importHint' => $data['text_editor_import_hint'], 'importUnavailable' => $data['text_editor_import_unavailable'], 'importError' => $data['text_editor_import_error'], 'importAudioLibrary' => $data['text_editor_import_audio_library'], 'importVideoLibrary' => $data['text_editor_import_video_library'], 'previewVideoLink' => $data['text_editor_preview_video_link'], 'previewVideoOpen' => $data['text_editor_preview_video_open'], 'alignLeft' => $data['text_editor_align_left'], 'alignCenter' => $data['text_editor_align_center'], 'alignRight' => $data['text_editor_align_right'],
                'saveStateSaved' => $data['text_block_save_state_saved'], 'saveStateUnsaved' => $data['text_block_save_state_unsaved'], 'saveStateSaving' => $data['text_block_save_state_saving'],
                'addBlock' => $data['text_block_add'], 'searchBlocks' => $data['text_block_search'], 'noBlocks' => $data['text_block_no_results'],
                'categoryContent' => $data['text_block_category_content'], 'categoryMedia' => $data['text_block_category_media'], 'categoryLayout' => $data['text_block_category_layout'],
                'outline' => $data['text_block_outline'], 'outlineEmpty' => $data['text_block_outline_empty'], 'outlineLabel' => $data['text_block_outline_label'], 'initialInvalid' => $data['text_block_initial_invalid'], 'documentTooLarge' => $data['text_block_document_too_large'], 
                'moveUp' => $data['text_block_move_up'], 'moveDown' => $data['text_block_move_down'], 'duplicate' => $data['text_block_duplicate'],
                'transform' => $data['text_block_transform'], 'remove' => $data['text_block_remove'], 'addAfter' => $data['text_block_add_after'],
                'dragHint' => $data['text_block_drag'], 'transformHint' => $data['text_block_transform_hint'], 'insertEmpty' => $data['text_block_insert_empty'],
                'statusInserted' => $data['text_block_status_inserted'], 'statusMoved' => $data['text_block_status_moved'],
                'statusDuplicated' => $data['text_block_status_duplicated'], 'statusTransformed' => $data['text_block_status_transformed'],
                'statusRemoved' => $data['text_block_status_removed'], 'statusUndo' => $data['text_block_status_undo'], 'statusRedo' => $data['text_block_status_redo'],
                'undo' => $data['text_block_undo'], 'redo' => $data['text_block_redo'],
                'copyBlock' => $data['text_block_copy'], 'pasteBlock' => $data['text_block_paste'],
                'indentBlock' => $data['text_block_indent'], 'outdentBlock' => $data['text_block_outdent'],
                'statusIndented' => $data['text_block_status_indented'], 'statusOutdented' => $data['text_block_status_outdented'],
                'hierarchyMoveUnavailable' => $data['text_block_hierarchy_move_unavailable'],
                'statusCopied' => $data['text_block_status_copied'], 'statusPasted' => $data['text_block_status_pasted'],
                'pasteUnavailable' => $data['text_block_paste_unavailable'],
                'navigationPath' => $data['text_block_navigation_path'], 'navigationRow' => $data['text_block_navigation_row'], 'navigationCell' => $data['text_block_navigation_cell'],
                'statusFocusChanged' => $data['text_block_status_focus_changed'],
                'outlineKeyboardHint' => $data['text_block_outline_keyboard_hint'], 'statusOutlineChanged' => $data['text_block_status_outline_changed'],
                'keyboardShortcutsTitle' => $data['text_block_keyboard_shortcuts_title'], 'keyboardShortcuts' => $data['text_block_keyboard_shortcuts'],
                'previewDesktop' => $data['text_block_preview_desktop'], 'previewTablet' => $data['text_block_preview_tablet'], 'previewMobile' => $data['text_block_preview_mobile'],
                'editorTools' => $data['text_block_editor_tools'], 'canvas' => $data['text_block_canvas'], 'canvasEmpty' => $data['text_block_canvas_empty'],
                'inspector' => $data['text_block_inspector'], 'inspectorEmpty' => $data['text_block_inspector_empty'], 'columnCount' => $data['text_block_column_count'],
                'typeLabels' => [
                    'core/paragraph' => $data['text_block_type_paragraph'], 'core/heading' => $data['text_block_type_heading'],
                    'core/list' => $data['text_block_type_list'], 'core/image' => $data['text_block_type_image'],
                    'core/quote' => $data['text_block_type_quote'], 'core/callout' => $data['text_block_type_callout'], 'core/details' => $data['text_block_type_details'], 'core/spacer' => $data['text_block_type_spacer'], 'core/code' => $data['text_block_type_code'], 'core/video_embed' => $data['text_block_type_video'],
                    'core/button' => $data['text_block_type_button'], 'core/separator' => $data['text_block_type_separator'],
                    'core/group' => $data['text_block_type_group'], 'core/columns' => $data['text_block_type_columns'],
                    'core/list-item' => $data['text_block_type_list_item'], 'core/column' => $data['text_block_type_column'],
                    'core/gallery' => $data['text_block_type_gallery'], 'core/cover' => $data['text_block_type_cover'], 'core/table' => $data['text_block_type_table'], 'core/file' => $data['text_block_type_file'], 'core/audio' => $data['text_block_type_audio'], 'core/video_file' => $data['text_block_type_video_file'], 'core/threejs_thumb' => $data['text_block_type_threejs_thumb'],
                ],
                'preformatted' => ['label' => $data['text_block_code_label'], 'defaultText' => $data['text_block_code_default'], 'tooLarge' => $data['text_block_code_too_large']],
                'spacer' => ['heightLabel' => $data['text_block_spacer_height'], 'heights' => ['16' => $data['text_block_spacer_16'], '32' => $data['text_block_spacer_32'], '48' => $data['text_block_spacer_48'], '64' => $data['text_block_spacer_64'], '96' => $data['text_block_spacer_96']]],
                'details' => ['summaryLabel' => $data['text_block_details_summary_label'], 'summaryDefault' => $data['text_block_details_summary_default'], 'defaultText' => $data['text_block_details_default']],
                'callout' => ['defaultText' => $data['text_block_callout_default'], 'tone' => $data['text_block_callout_tone'],  'tones' => ['info' => $data['text_block_callout_tone_info'], 'success' => $data['text_block_callout_tone_success'], 'warning' => $data['text_block_callout_tone_warning'], 'danger' => $data['text_block_callout_tone_danger']]],
                'file' => ['source' => $data['text_block_file_source'], 'choose' => $data['text_block_file_choose']],
                'audio' => ['source' => $data['text_block_audio_source'], 'choose' => $data['text_block_audio_choose'], 'catalogBase' => $this->pageCatalogAssetUrl()],
                'videoFile' => ['source' => $data['text_block_video_file_source'], 'choose' => $data['text_block_video_file_choose'], 'catalogBase' => $this->pageCatalogAssetUrl()],
                'threejs' => ['label' => $data['text_block_threejs_label'], 'sceneConfig' => $data['text_block_threejs_scene_config'], 'configHint' => $data['text_block_threejs_config_hint'], 'previewHint' => $data['text_block_threejs_preview_hint'], 'objects' => $data['text_block_threejs_objects'], 'lights' => $data['text_block_threejs_lights'], 'addShape' => $data['text_block_threejs_addShape'], 'addLight' => $data['text_block_threejs_addLight'], 'remove' => $data['text_block_threejs_remove'], 'color' => $data['text_block_threejs_color'], 'animation' => $data['text_block_threejs_animation'], 'intensity' => $data['text_block_threejs_intensity'], 'invalidScene' => $data['text_block_threejs_invalidScene'], 'scriptLabel' => $data['text_block_threejs_scriptLabel'], 'scriptHint' => $data['text_block_threejs_scriptHint'], 'editInline' => $data['text_block_threejs_editInline'], 'closeInline' => $data['text_block_threejs_closeInline'], 'deleteScript' => $data['text_block_threejs_deleteScript'], 'scriptDeleted' => $data['text_block_threejs_scriptDeleted'], 'scriptPlaceholder' => $data['text_block_threejs_scriptPlaceholder'], 'addScript' => $data['text_block_threejs_addScript']],
                'table' => [
                    'header' => $data['text_block_table_header'], 'addRow' => $data['text_block_table_add_row'],
                    'removeRow' => $data['text_block_table_remove_row'], 'addColumn' => $data['text_block_table_add_column'],
                    'removeColumn' => $data['text_block_table_remove_column'], 'toggleHeader' => $data['text_block_table_toggle_header'],
                    'cellLabel' => $data['text_block_table_cell_label'],
                ],
                'media' => [
                    'caption' => $data['text_block_caption'], 'alignment' => $data['text_block_alignment'],
                    'imageSize' => $data['text_block_image_size'], 'mobileSize' => $data['text_block_mobile_size'],
                    'galleryColumns' => $data['text_block_gallery_columns'], 'source' => $data['text_block_media_source'],
                    'alt' => $data['text_block_image_alt'], 'title' => $data['text_block_image_title'],
                    'videoPreview' => $data['text_block_video_preview'], 'videoPreviewHint' => $data['text_block_video_preview_hint'],
                    'placeholder' => $data['text_block_media_placeholder'],
                    'alignments' => ['left'=>$data['text_block_align_left'],'center'=>$data['text_block_align_center'],'right'=>$data['text_block_align_right'],'content'=>$data['text_block_align_content'],'wide'=>$data['text_block_align_wide'],'full'=>$data['text_block_align_full']],
                    'sizes' => ['small'=>$data['text_block_size_small'],'medium'=>$data['text_block_size_medium'],'large'=>$data['text_block_size_large'],'full'=>$data['text_block_size_full']],
                    'mobileSizes' => ['full'=>$data['text_block_mobile_full'],'large'=>$data['text_block_mobile_large'],'medium'=>$data['text_block_mobile_medium']],
                ],
                'patternsCategory' => $data['text_block_pattern_category'],
                'patternHint' => $data['text_block_pattern_hint'],
                'patternLabels' => ['two_columns'=>$data['text_block_pattern_two_columns'],'quote_cta'=>$data['text_block_pattern_quote_cta'],'section_intro'=>$data['text_block_pattern_section_intro'],'cover_hero'=>$data['text_block_pattern_cover_hero']],
            ];

                $site = $this->config->get('site_url');
                $version = \Reamur\Admin\Controller\Common\Header::ASSET_V . '-threejs-5';
                $catalogAssetBase = $this->pageCatalogAssetUrl();
                $this->document->addStyle($site . 'view/css/blog-block-editor.css?v=' . $version);
                $this->document->addStyle($catalogAssetBase . 'view/css/threejs-thumb.css?v=' . $version);
                $this->document->addScript($catalogAssetBase . $this->three_asset_manifest->publicBasePath() . 'threejs-thumb.js?v=' . $version);
                $this->document->addScript($site . 'view/js/blog-block-import.js?v=' . $version);
                $this->document->addScript($site . 'view/js/blog-block-editor.js?v=' . $version);
                $this->document->addScript($site . 'view/js/blog-block-editor-init.js?v=' . $version);
                $this->document->addScript($site . 'view/js/blog-audio-media.js?v=' . $version);

                // Existing catalog endpoint enforces modify on blog/blog; expose only when that exact ACL is granted.
                $data['can_use_audio_catalog'] = $this->user->hasPermission('modify', 'blog/blog');
                $data['media_config'] = [
                    'audioListUrl' => $this->url->link('blog/blog.audioList'),
                    'audioUploadUrl' => $this->url->link('blog/blog.audioUpload'),
                    'videoListUrl' => $this->url->link('blog/blog.videoList'),
                    'videoUploadUrl' => $this->url->link('blog/blog.videoUpload'),
                    'csrfToken' => \Reamur\System\Library\Security\Csrf::token($this->session),
                    'strings' => [
                        'audioError' => $data['error_audio_generic'], 'chooseFile' => $data['error_media_upload_no_file'], 'audioUploadError' => $data['error_audio_video_upload'],
                        'loading' => $data['text_media_loading'],
                        'audioEmpty' => $data['text_audio_empty'],
                        'audioDragHint' => $data['text_audio_drag_hint'],
                        'selectAudio' => $data['button_audio_select'],
                        'audioUploadHint' => $data['text_audio_upload_hint'],
                        'videoLibrary' => $data['text_video_library'], 'videoEmpty' => $data['text_video_empty'],
                        'videoHint' => $data['text_video_hint'], 'videoUploadHint' => $data['text_video_upload_hint'],
                        'videoDragHint' => $data['text_video_drag_hint'], 'selectVideo' => $data['button_video_select'],
                        'audioUpload' => $data['button_audio_upload'], 'videoUpload' => $data['button_video_upload'],
                        'audioUploadError' => $data['error_audio_video_upload'], 'videoUploadError' => $data['error_audio_video_upload'],
                        'videoError' => $data['error_video_generic'],
                    ],
                ];

        }

        $data['error']      = '';
        $data['text_flash'] = '';
        $data['can_modify'] = $this->user->hasPermission('modify', 'cms/page');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);

        $pid          = (int)($page['page_id'] ?? 0);
        $isPost       = $this->request->server['REQUEST_METHOD'] === 'POST';
        $post         = $this->request->post;
        $descriptions = $pid ? $this->model_cms_page->getPageDescriptions($pid) : [];
        $keywords     = $pid ? $this->model_cms_page->getPageKeywords($pid) : [];
        $this->load->model('custom_field/value');
        $customDefinitions = $this->model_custom_field_value->getPageFields();
        $customLanguages = $this->model_custom_field_value->getActiveLanguages();
        $customSaved = $pid ? $this->model_custom_field_value->getPageValues($pid) : [];
        $customPosted = (array)($post['custom_field'] ?? []);
        $customGroups = [];
        foreach ($customDefinitions as $definition) {
            $definition = (array)$definition;
            $fid = (int)$definition['field_id'];
            $localized = (int)$definition['localized'] === 1;
            $targets = $localized ? $customLanguages : [['language_id'=>0,'name'=>'Shared','code'=>'shared']];
            $values = [];
            foreach ($targets as $target) {
                $target = (array)$target; $lid=(int)$target['language_id'];
                $values[]=['language_id'=>$lid,'language_name'=>(string)$target['name'],'value'=>(string)($customPosted[$fid][$lid] ?? ($customSaved[$fid][$lid] ?? ''))];
            }
            $opts = json_decode((string)($definition['options'] ?? '[]'), true);
            $definition['options_list'] = is_array($opts) ? $opts : [];
            $definition['values'] = $values; $definition['localized'] = $localized;
            $definition['required'] = (int)$definition['required'] === 1;
            $definition['id'] = $fid;
            $groupKey = (int)$definition['group_id'];
            if (!isset($customGroups[$groupKey])) $customGroups[$groupKey]=['label'=>(string)$definition['group_label'],'fields'=>[]];
            $customGroups[$groupKey]['fields'][]=$definition;
        }
        $data['custom_field_groups'] = array_values($customGroups);
        $data['text_custom_fields'] = $this->language->get('text_custom_fields');
        $data['text_custom_fields_note'] = $this->language->get('text_custom_fields_note');
        $data['text_shared_value'] = $this->language->get('text_shared_value');
        $data['text_select_option'] = $this->language->get('text_select_option');
        $data['text_yes'] = $this->language->get('text_yes'); $data['text_no'] = $this->language->get('text_no');

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
            $description = $fields['description'];
            $summary = '';
            $contentSavedValue = $description;
            $blockSummaryDocument = null;
            $blockPrefix = 'REAMURCMS_BDRE_JSON_V2:';
            $blockMode = 'legacy';
            $blockJson = '';
            $storedBlockValue = $description;
            if (str_starts_with($description, $blockPrefix)) {
                $blockJson = substr($description, strlen($blockPrefix));
                $decoded = (new \Reamur\System\Library\Blog\BlockDocument())->deserializeDocument($blockJson);
                if (!empty($decoded['valid']) && is_array($decoded['document']) && ($decoded['document']['format_version'] ?? null) === 2) {
                    $blockMode = 'blocks';
                    $blockSummaryDocument = $decoded['document'];
                    // Canonicalize in memory on read too: older PHP JSON encoding wrote empty attributes as [] instead of {}.
                    $blockJson = (new \Reamur\System\Library\Blog\BlockDocument())->serializeDocument($decoded['document']);
                } else {
                    $blockMode = 'invalid';
                    $summary = $data['text_content_invalid'];
                }
            } elseif ($description === '') {
                // Empty new descriptions can always open the editor without DOM conversion.
                $blockMode = 'blocks';
                $blockJson = '{"format_version":2,"blocks":[]}';
                $storedBlockValue = $blockPrefix . $blockJson;
            } else {
                $conversion = (new \Reamur\System\Library\Blog\BlockDocument())->convertLegacy($description);
                if (($conversion['status'] ?? '') === 'converted' && is_array($conversion['document'] ?? null)) {
                    $blockMode = 'blocks';
                    $blockSummaryDocument = $conversion['document'];
                    $blockJson = json_encode($conversion['document'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $storedBlockValue = $blockPrefix . (is_string($blockJson) ? $blockJson : '');
                }
            }
            if ($blockSummaryDocument !== null) {
                $summary = $this->summarizeBlockDocument($blockSummaryDocument, $data['text_content_block_count']);
            } elseif ($blockMode === 'legacy') {
                $summary = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $summary = preg_replace('/\\s+/u', ' ', trim($summary)) ?? trim($summary);
            }
            $fields['content_summary'] = mb_substr($summary, 0, 180);
            $fields['content_saved_value'] = $contentSavedValue;
            $fields['content_form_mode'] = $blockMode === 'blocks' && str_starts_with($contentSavedValue, $blockPrefix) ? 'blocks' : ($blockMode === 'invalid' ? 'invalid' : 'preserve');
            $fields['block_mode'] = $blockMode;
            $fields['block_document'] = $blockJson;
            $fields['block_stored_value'] = $storedBlockValue;

            $data['languages'][] = [
                'language_id' => $lid,
                'name'        => (string)$language['name'],
                'code'        => (string)$language['code'],
                'fields'      => $fields,
                'content_editor_url' => $pid ? $this->url->link('cms/page.content|' . $pid, 'language_id=' . $lid) : '',
            ];
        }

        $data['page_id']    = $pid;
        $data['sort_order'] = (string)($post['sort_order'] ?? (string)($page['sort_order'] ?? '0'));
        $data['status']     = $isPost ? ((string)($post['status'] ?? '0') === '1' ? '1' : '0') : (string)(int)($page['status'] ?? 0);

        $data['cancel'] = $this->url->link('cms/page');

        return $data;
    }

    /**
     * Payload normalizado do POST (chaves SEMPRE presentes — lição F2).
     *
     * @return array{status: int, sort_order: int, descriptions: array<int, array<string, string>>}
     */
    private function postPayload(int $pageId = 0): array {
        $post = $this->request->post;

        $descriptions = [];
        $savedDescriptions = $pageId ? $this->model_cms_page->getPageDescriptions($pageId) : [];

        foreach ((array)($post['page_description'] ?? []) as $lid => $d) {
            $d = (array)$d;

            $description = array_key_exists('description', $d)
                ? (string)$d['description']
                : (string)($savedDescriptions[(int)$lid]['description'] ?? '');
            if (($post['page_block_mode'][$lid] ?? '') === 'blocks' && str_starts_with($description, 'REAMURCMS_BDRE_JSON_V2:')) {
                $decoded = (new \Reamur\System\Library\Blog\BlockDocument())->deserializeDocument(substr($description, strlen('REAMURCMS_BDRE_JSON_V2:')));
                if (!empty($decoded['valid']) && is_array($decoded['document']) && ($decoded['document']['format_version'] ?? null) === 2) {
                    $description = 'REAMURCMS_BDRE_JSON_V2:' . (new \Reamur\System\Library\Blog\BlockDocument())->serializeDocument($decoded['document']);
                }
            }
            $descriptions[(int)$lid] = [
                'title'            => mb_substr(trim((string)($d['title'] ?? '')), 0, 255),
                'description'      => in_array(($post['page_block_mode'][$lid] ?? ''), ['blocks', 'invalid', 'preserve'], true) ? $description : trim($description),
                'meta_title'       => mb_substr(trim((string)($d['meta_title'] ?? '')), 0, 255),
                'meta_description' => mb_substr(trim((string)($d['meta_description'] ?? '')), 0, 255),
                'keyword'          => mb_substr(trim((string)($d['keyword'] ?? '')), 0, 64),
            ];
        }

        return [
            'status'       => (string)($post['status'] ?? '0') === '1' ? 1 : 0,
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
        $customFields = $this->model_custom_field_value->getPageFields();
        $postedFields = (array)($post['custom_field'] ?? []);
        $activeLanguages = array_map(static fn($l)=>(int)((array)$l)['language_id'], $this->model_custom_field_value->getActiveLanguages());
        foreach ($customFields as $field) {
            $field=(array)$field; $fid=(int)$field['field_id']; $targets=(int)$field['localized']===1?$activeLanguages:[0];
            foreach($targets as $lid) { $value=$postedFields[$fid][$lid] ?? ''; $problem=$this->model_custom_field_value->validateValue($field,$value); if($problem!=='') { $errors[]=$this->language->get('error_custom_field_'.$problem); } }
        }

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

    /** Return a plain-text excerpt from a validated v2 block tree for the general form. */
    private function summarizeBlockDocument(array $document, string $blockCountLabel): string {
        $parts = []; $count = 0;
        $walk = function (array $nodes) use (&$walk, &$parts, &$count): void {
            foreach ($nodes as $node) {
                if (!is_array($node)) continue;
                $count++;
                $content = $node['content'] ?? '';
                if (is_string($content)) { if (trim($content) !== '') $parts[] = $content; }
                elseif (is_array($content)) foreach ($content as $run) if (is_array($run) && is_string($run['text'] ?? null)) $parts[] = $run['text'];
                if (is_array($node['children'] ?? null)) $walk($node['children']);
            }
        };
        $walk((array)($document['blocks'] ?? []));
        $text = preg_replace('/\\s+/u', ' ', trim(implode(' ', $parts))) ?? trim(implode(' ', $parts));
        return $text !== '' ? $text : ($count > 0 ? sprintf($blockCountLabel, $count) : '');
    }

    /** Validate every editor payload; submission is rejected if JS did not initialize or JSON is invalid. */
    private function validateBlockSubmission(int $pageId): array {
        $errors = [];
        $post = (array)$this->request->post;
        $modes = (array)($post['page_block_mode'] ?? []);
        $descriptions = (array)($post['page_description'] ?? []);
        $storedDescriptions = $pageId ? $this->model_cms_page->getPageDescriptions($pageId) : [];
        $prefix = 'REAMURCMS_BDRE_JSON_V2:';
        $validator = new \Reamur\System\Library\Blog\BlockDocument();
        foreach ($descriptions as $languageId => $posted) {
            $mode = (string)($modes[$languageId] ?? '');
            $posted = (array)$posted;
            $description = (string)($posted['description'] ?? '');
            $hasMarker = str_starts_with($description, $prefix);
            if ($mode === 'invalid') {
                $original = (string)($storedDescriptions[(int)$languageId]['description'] ?? '');
                if (!$pageId || !$hasMarker || !hash_equals($original, $description)) {
                    $errors[] = 'O conteúdo marcado inválido só pode ser preservado sem alteração; restaure uma versão válida antes de editá-lo.';
                }
                continue;
            }
            if ($mode !== 'blocks' && !$hasMarker) continue;
            $ready = (string)($post['page_editor_ready'][$languageId] ?? '');
            if ($mode !== 'blocks' || $ready !== '1' || !$hasMarker) {
                $errors[] = 'O editor de blocos não foi inicializado corretamente. Recarregue a página antes de salvar.';
                continue;
            }
            $result = $validator->deserializeDocument(substr($description, strlen($prefix)));
            if (empty($result['valid']) || !is_array($result['document']) || ($result['document']['format_version'] ?? null) !== 2) {
                $errors[] = 'O documento do editor é inválido ou excede o limite permitido. O conteúdo não foi salvo.';
            }
        }
        return $errors;
    }

    /** Public site root used for safe media URLs in page block rendering. */
    private function pageCatalogAssetUrl(): string {
        $base = (string)$this->config->get('site_url', '');
        if (str_ends_with($base, 'admin/')) return substr($base, 0, -strlen('admin/')) . 'catalog/';
        if (str_ends_with($base, 'catalog/')) return $base;
        return rtrim($base, '/') . '/catalog/';
    }

    /** Token estável da sessão em todas as mutações de cms/page. */
    private function csrfValid(): bool {
        return \Reamur\System\Library\Security\Csrf::check($this->session, $this->request->post['csrf_token'] ?? '');
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
