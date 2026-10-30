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

    private const PAGE_PREVIEW_MAX_BYTES = 4194304;

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
            'text_empty_title', 'text_empty_hint', 'text_system_page',
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
                'system_key' => (string)($row['system_key'] ?? ''),
                'is_system_page' => !empty($row['system_key']),
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

            $submittedFingerprint = (string)($this->request->post['edit_fingerprint'] ?? '');
            $data['edit_fingerprint'] = $submittedFingerprint;
            $data['edit_core_fingerprint'] = (string)($this->request->post['edit_core_fingerprint'] ?? '');
            $currentFingerprint = $this->pageEditFingerprint($page_id);
            $fingerprintMatches = $submittedFingerprint !== '' && $currentFingerprint !== '' && hash_equals($currentFingerprint, $submittedFingerprint);
            $errors = !$this->csrfValid() ? [$this->language->get('error_csrf')] : (!$fingerprintMatches ? [$this->language->get('error_edit_conflict')] : array_merge($this->validatePage($page_id), $this->validateBlockSubmission($page_id)));
            if (!$fingerprintMatches && $this->csrfValid()) { $data['revision_conflict'] = true; }
            if ($errors === [] && !hash_equals($submittedFingerprint, $this->pageEditFingerprint($page_id))) { $data['revision_conflict'] = true; $errors = [$this->language->get('error_edit_conflict')]; }

            if ($errors === []) {
                try {
                    $this->model_cms_page->editPage($page_id, $this->postPayload($page_id), (string)($this->request->post['edit_core_fingerprint'] ?? ''));
                } catch (\RuntimeException $exception) {
                    if ($exception->getMessage() !== 'edit_conflict') throw $exception;
                    $data['revision_conflict'] = true; $errors = [$this->language->get('error_edit_conflict')];
                }
                if ($errors === []) {
                    $valuesSaved = $this->model_custom_field_value->savePageValues($page_id, (array)($this->request->post['custom_field'] ?? []));
                    $this->setFlash($this->language->get($valuesSaved ? 'text_success_edit' : 'error_custom_field_save'), $valuesSaved ? 'success' : 'error');
                    $this->response->redirect($this->url->link('cms/page.edit|' . $page_id));
                    return;
                }
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

        if ($this->model_cms_page->isSystemPage($page_id)) {
            $this->setFlash($this->language->get('error_system_page_delete'), 'error');
            $this->response->redirect($this->url->link('cms/page'));
            return;
        }

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

    /** POST-only list of saved revisions for the current Page/language. */
    public function revisions(string $page_id = '0'): void {
        $this->load->language('cms/page'); $this->load->model('cms/page');
        if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST' || !$this->csrfValid() || !$this->user->hasPermission('modify', 'cms/page')) { $this->pageMediaJson(['success'=>false,'error'=>'forbidden']); return; }
        $pageId=(int)$page_id; $languageId=(int)($this->request->post['language_id'] ?? 0); $knownLanguages=array_map(static fn($l)=>(int)((array)$l)['language_id'],$this->model_cms_page->getLanguages());
        if ($pageId < 1 || !$this->model_cms_page->getPage($pageId) || !in_array($languageId,$knownLanguages,true)) { $this->pageMediaJson(['success'=>false,'error'=>'invalid_target']); return; }
        $items=$this->model_cms_page->getPageRevisions($pageId,$languageId);
        $this->pageMediaJson(['success'=>true,'items'=>array_map(static fn($r)=>['revision_id'=>$r['revision_id'],'date_added'=>$r['date_added']],$items)]);
    }

    /** POST-only fetch of one revision; the client restores it in memory and must still Save. */
    public function revision(string $page_id = '0'): void {
        $this->load->language('cms/page'); $this->load->model('cms/page');
        if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST' || !$this->csrfValid() || !$this->user->hasPermission('modify', 'cms/page')) { $this->pageMediaJson(['success'=>false,'error'=>'forbidden']); return; }
        $pageId=(int)$page_id; $languageId=(int)($this->request->post['language_id'] ?? 0); $revisionId=(int)($this->request->post['revision_id'] ?? 0); $knownLanguages=array_map(static fn($l)=>(int)((array)$l)['language_id'],$this->model_cms_page->getLanguages());
        if ($pageId < 1 || !$this->model_cms_page->getPage($pageId) || !in_array($languageId,$knownLanguages,true) || $revisionId < 1) { $this->pageMediaJson(['success'=>false,'error'=>'invalid_target']); return; }
        foreach ($this->model_cms_page->getPageRevisions($pageId,$languageId) as $revision) if ((int)$revision['revision_id']===$revisionId) {
            $snapshot=$revision['snapshot']; $raw=(string)($snapshot['description']['description'] ?? ''); $page=$this->model_cms_page->getPage($pageId);
            $layout=(new \Reamur\System\Library\Cms\PageLayout())->decode($raw,(string)($page['system_key'] ?? ''));
            if ($layout !== null) { $snapshot['page_layout_sections']=$layout['sections']; $snapshot['block_document']=$layout['content']; }
            $this->pageMediaJson(['success'=>true,'snapshot'=>$snapshot]); return;
        }
        $this->pageMediaJson(['success'=>false,'error'=>'not_found']);
    }

    /** POST-only private preview of an unsaved CMS Page document and its allowlisted system-page layout. */
    public function preview(): void {
        $this->load->language('cms/page');
        $this->response->addHeader('Cache-Control: private, no-store, no-cache, max-age=0');
        $this->response->addHeader('Pragma: no-cache');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $this->response->addHeader('X-Frame-Options: DENY');
        $this->response->addHeader('Referrer-Policy: no-referrer');
        $assetBase = $this->pageCatalogAssetUrl();
        $assetOrigin = $this->pageCatalogAssetOrigin($assetBase);
        $originSource = $assetOrigin !== '' ? ' ' . $assetOrigin : '';
        $this->response->addHeader("Content-Security-Policy: default-src 'none'; img-src 'self' data:" . $originSource . "; style-src 'self' 'unsafe-inline'" . $originSource . "; script-src 'self'" . $originSource . "; font-src 'self' data:" . $originSource . "; media-src 'self'" . $originSource . "; frame-src https://www.youtube-nocookie.com https://player.vimeo.com; frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'none'");

        if (!$this->user->hasPermission('modify', 'cms/page')) {
            $this->renderPagePreviewError($this->language->get('error_page_preview_permission'));
            return;
        }
        if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->renderPagePreviewError($this->language->get('error_page_preview_method'));
            return;
        }
        if (!$this->csrfValid()) {
            $this->renderPagePreviewError($this->language->get('error_page_preview_csrf'));
            return;
        }

        $raw = $this->request->post['preview_document'] ?? null;
        if (!is_string($raw) || $raw === '' || strlen($raw) > self::PAGE_PREVIEW_MAX_BYTES) {
            $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
            return;
        }

        $validator = new \Reamur\System\Library\Blog\BlockDocument(true);
        $decoded = $validator->deserializeDocument($raw);
        if (empty($decoded['valid']) || !is_array($decoded['document']) || ($decoded['document']['format_version'] ?? null) !== 2) {
            $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
            return;
        }

        // Preview is deliberately stricter than the persisted page: never execute custom Three.js JavaScript.
        $document = $decoded['document'];
        $this->stripPagePreviewScripts($document['blocks']);
        try {
            $safeJson = $validator->serializeDocument($document);
        } catch (\Throwable $exception) {
            $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
            return;
        }

        $assetBase = $this->pageCatalogAssetUrl();
        // Resolve only the renderer's allowlisted local files. New Page media stays under cms/page;
        // the existing validator permits Blog paths solely for read-only legacy references.
        $catalogViewRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view';
        $renderer = new \Reamur\System\Library\Blog\BlockRenderer(
            $catalogViewRoot . '/download',
            $catalogViewRoot . '/audio',
            $catalogViewRoot . '/video',
            true
        );
        $rendered = $renderer->renderJson($safeJson, 2, $assetBase);
        if (empty($rendered['valid'])) {
            $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
            return;
        }

        $title = $this->previewText($this->request->post['preview_title'] ?? '', 255);
        $language = $this->request->post['preview_language'] ?? 'en-us';
        $language = is_string($language) && preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/iD', $language) ? strtolower($language) : 'en-us';

        $this->load->model('cms/page');
        $availableLanguages = $this->model_cms_page->getLanguages();
        $languageIds = [];
        foreach ($availableLanguages as $row) {
            $row = (array)$row;
            $code = strtolower((string)($row['code'] ?? ''));
            if ($code !== '') $languageIds[$code] = (int)($row['language_id'] ?? 0);
        }
        if (!isset($languageIds[$language])) $language = isset($languageIds['en-us']) ? 'en-us' : (string)(array_key_first($languageIds) ?? 'en-us');
        $languageId = max(1, (int)($languageIds[$language] ?? 1));
        $englishId = max(1, (int)($languageIds['en-us'] ?? 1));
        $this->load->model('localization/language');
        $publicLanguages = [];
        foreach ($this->model_localization_language->getLanguages(['status' => 1]) as $row) {
            $row = (array)$row;
            $code = strtolower((string)($row['code'] ?? ''));
            if ($code !== '' && is_dir(rtrim(DIR_SYSTEM, '/\\') . '/../catalog/language/' . $code)) $publicLanguages[] = $row;
        }

        $pageIdRaw = $this->request->post['preview_page_id'] ?? '0';
        $pageId = is_scalar($pageIdRaw) && ctype_digit((string)$pageIdRaw) ? (int)$pageIdRaw : 0;
        $previewPage = $pageId > 0 ? $this->model_cms_page->getPage($pageId) : [];
        if ($pageId > 0 && !$previewPage) {
            $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
            return;
        }
        $systemKey = (string)($previewPage['system_key'] ?? '');
        $catalogRoute = $this->previewCatalogRoute($systemKey);
        $previewSections = [];
        $layoutOptions = $this->cms_page_layout->options($systemKey);
        if ($catalogRoute !== '' && $layoutOptions !== []) {
            $sectionsRaw = $this->request->post['preview_layout_sections'] ?? null;
            if ($sectionsRaw !== null) {
                if (!is_string($sectionsRaw) || strlen($sectionsRaw) > 16384) {
                    $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
                    return;
                }
                $submittedSections = json_decode($sectionsRaw, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($submittedSections) ||
                    ($submittedSections !== [] && array_keys($submittedSections) !== range(0, count($submittedSections) - 1))) {
                    $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
                    return;
                }
                $normalized = $this->cms_page_layout->normalizeSections($systemKey, $submittedSections);
                if ($normalized === null) {
                    $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
                    return;
                }
                $previewSections = $normalized;
            } else {
                $savedDescriptions = $this->model_cms_page->getPageDescriptions($pageId);
                $savedDescription = (string)($savedDescriptions[$languageId]['description'] ?? '');
                $savedLayout = $this->cms_page_layout->decode($savedDescription, $systemKey);
                $previewSections = $savedLayout !== null
                    ? $savedLayout['sections']
                    : $this->cms_page_layout->defaults($systemKey, !empty($document['blocks']));
            }
        }
        $customRaw = $this->request->post['preview_custom_fields'] ?? '{}';
        if (!is_string($customRaw) || strlen($customRaw) > 262144) {
            $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
            return;
        }
        $customPosted = json_decode($customRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($customPosted)) {
            $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
            return;
        }
        $this->load->language('cms/page', $language);
        $this->load->language('common/header', $language);
        $this->load->language('common/footer', $language);
        $this->load->language('common/home', $language);
        $this->load->model('custom_field/value');
        $publicFields = $this->buildPreviewPublicCustomFields($customPosted, $pageId, $languageId, $englishId, array_values($languageIds));
        if ($publicFields === false) {
            $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
            return;
        }

        $texts = $this->language->all();
        if ($title === '') $title = $this->language->get('text_page_preview_untitled');
        $metaTitle = $this->previewText($this->request->post['preview_meta_title'] ?? '', 255);
        $metaDescription = $this->previewText($this->request->post['preview_meta_description'] ?? '', 255);

        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSettings();
        $this->load->model('design/theme');
        $publishedTheme = [];
        $themeRows = $this->model_design_theme->getThemes();
        usort($themeRows, static fn(array $left, array $right): int => (int)($right['theme_id'] ?? 0) <=> (int)($left['theme_id'] ?? 0));
        foreach ($themeRows as $themeRow) {
            $themeRow = (array)$themeRow;
            if ((int)($themeRow['status'] ?? 0) !== 1) continue;
            $rawTheme = (string)($themeRow['published_config'] ?? '');
            if ($rawTheme === '') $rawTheme = (string)($themeRow['config'] ?? '{}');
            $decodedTheme = json_decode($rawTheme, true);
            if (is_array($decodedTheme)) $publishedTheme = $decodedTheme;
            break;
        }
        $primary = (string)($publishedTheme['primary'] ?? '#6d28d9');
        $surface = (string)($publishedTheme['surface'] ?? '#ffffff');
        if (!preg_match('/^#[a-fA-F0-9]{6}$/D', $primary)) $primary = '#6d28d9';
        if (!preg_match('/^#[a-fA-F0-9]{6}$/D', $surface)) $surface = '#ffffff';
        $themeTemplates = is_array($publishedTheme['templates'] ?? null) ? $publishedTheme['templates'] : [];

        $assetBase = $this->pageCatalogAssetUrl();
        $languageLinks = [];
        foreach ($publicLanguages as $row) {
            $row = (array)$row;
            $code = strtolower((string)($row['code'] ?? ''));
            if ($code === '') continue;
            $langKey = 'text_lang_' . str_replace('-', '_', $code);
            $languageLinks[] = [
                'code' => $code,
                'name' => (string)($texts[$langKey] ?? $row['name'] ?? $code),
                'active' => $code === $language,
                'href' => '#preview-language',
            ];
        }
        $publicTitle = $metaTitle !== '' ? $metaTitle : $title;
        $publicDescription = $metaDescription !== '' ? $metaDescription : (string)($settings['config_meta_description'] ?? '');
        $styles = [$assetBase . 'view/css/blog-blocks.css' => 'stylesheet'];
        $scripts = [];
        $requiresThreeJs = !empty($rendered['requires_threejs']);
        $assetVersion = '20261026c';
        if ($requiresThreeJs) {
            $styles[$assetBase . 'view/css/threejs-thumb.css?v=' . $assetVersion] = 'stylesheet';
            $scriptUrl = $assetBase . $this->three_asset_manifest->publicBasePath() . 'threejs-thumb.js?v=' . $assetVersion;
            $scripts[$scriptUrl] = $scriptUrl;
        }
        $styles[$assetBase . 'view/css/common.css'] = 'stylesheet';
        $styles[$assetBase . 'view/css/theme.css'] = 'stylesheet';
        $headerData = [
            'locale' => $language, 'title' => $publicTitle, 'description' => $publicDescription,
            'canonical' => '', 'sitemap_href' => '#preview-sitemap',
            'og' => ['type'=>'website','site_name'=>(string)($settings['config_name'] ?? ''),'title'=>$publicTitle,'description'=>$publicDescription,'url'=>'','locale'=>str_replace('-', '_', $language)],
            'styles' => $styles, 'scripts' => $scripts, 'has_3d' => false, 'three_runtime_base' => '',
            'home' => '#preview-home', 'body_class' => 'rms-page', 'theme_primary' => $primary, 'theme_surface' => $surface,
            'text_languages' => (string)($texts['text_languages'] ?? ''), 'text_brand' => (string)($texts['text_brand'] ?? ''),
            'text_nav_home' => (string)($texts['text_nav_home'] ?? ''), 'text_nav_about' => (string)($texts['text_nav_about'] ?? ''),
            'text_nav_blog' => (string)($texts['text_nav_blog'] ?? ''), 'text_nav_portfolio' => (string)($texts['text_nav_portfolio'] ?? ''),
            'text_nav_contact' => (string)($texts['text_nav_contact'] ?? ''), 'text_nav_quote' => (string)($texts['text_nav_quote'] ?? ''),
            'nav_about' => '#preview-about', 'nav_blog' => '#preview-blog',
            'nav_portfolio' => '#preview-portfolio', 'nav_contact' => '#preview-contact',
            'nav_quote' => '#preview-quote', 'languages' => $languageLinks,
            'cms_route_key' => '', 'cms_route_content' => '', 'private_preview' => true,
        ];

        $legalPages = $this->model_cms_page->getPublicLegalPages($languageId);
        $legal = []; $seenLegal = [];
        foreach ($legalPages as $legalPage) {
            $legalPage = (array)$legalPage;
            $keyword = (string)($legalPage['keyword'] ?? '');
            $legalId = (int)($legalPage['page_id'] ?? 0);
            if ($legalId < 1 || isset($seenLegal[$keyword])) continue;
            $seenLegal[$keyword] = true;
            $legal[] = ['name'=>(string)($legalPage['title'] ?? ''),'href'=>'#preview-legal'];
        }
        $copyrightKey = 'config_copyright_' . $language;
        $copyright = array_key_exists($copyrightKey, $settings) ? (string)$settings[$copyrightKey] : (string)($texts['text_copyright'] ?? '');
        $socials = [];
        foreach (['facebook','instagram','pinterest','x','google_business','redbubble'] as $network) {
            $key = 'config_social_' . $network . '_href';
            $href = array_key_exists($key, $settings) ? (string)$settings[$key] : (string)($texts['text_social_' . $network . '_href'] ?? '');
            if ($href !== '') $socials[] = ['name'=>(string)($texts['text_social_' . $network] ?? $network),'href'=>'#preview-social'];
        }
        $footerData = [
            'text_powered' => sprintf((string)($texts['text_powered'] ?? ''), REAMUR_VERSION),
            'text_copyright' => sprintf($copyright, date('Y')),
            'text_legal' => (string)($texts['text_legal'] ?? ''), 'legal' => $legal, 'socials' => $socials,
        ];
        $notice = '<aside class="cms-page-preview-notice" role="note" style="padding:12px 16px;margin-bottom:20px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;color:#334155;font-size:.875rem">' . htmlspecialchars((string)$this->language->get('text_page_preview_notice'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</aside>';
        $pageData = [
            'heading_title' => $title, 'description' => $notice . (string)$rendered['html'], 'button_back_home' => null,
            'public_custom_field_groups' => $publicFields, 'text_custom_fields' => (string)$this->language->get('text_custom_fields'),
            'text_yes' => (string)$this->language->get('text_yes'), 'text_no' => (string)$this->language->get('text_no'),
            'home' => '#preview-home', 'heading_home' => (string)($texts['heading_home'] ?? ''),
        ];

        $routeData = $catalogRoute !== ''
            ? $this->buildCatalogRoutePreviewData($systemKey, $title, (string)$rendered['html'], $previewSections)
            : [];
        $pageData['preview_notice'] = (string)$this->language->get('text_page_preview_notice');
        try {
            $html = $this->template->renderCatalogPagePreview($pageData, $headerData, $footerData, $themeTemplates, $catalogRoute !== '' ? $catalogRoute : 'cms/page_info', $routeData);
        } catch (\Throwable $exception) {
            $this->renderPagePreviewError($this->language->get('error_page_preview_document'));
            return;
        }
        $this->response->setOutput($html);
    }

    /** Map only fixed public system pages to their allowlisted Catalog templates. */
    private function previewCatalogRoute(string $systemKey): string {
        return match ($systemKey) {
            'home' => 'common/home',
            'portfolio' => 'catalog/project_list',
            'portfolio_detail' => 'catalog/project_info',
            'blog' => 'blog/blog_list',
            'blog_post' => 'blog/blog_info',
            'tools' => 'tool/tool_list',
            'tool_detail' => 'tool/tool_info',
            'contact' => 'common/contact',
            'quote' => 'checkout/quote',
            'not_found' => 'error/not_found',
            default => '',
        };
    }

    /** Build clearly synthetic, non-persistent module data for private Catalog-template previews. */
    private function buildCatalogRoutePreviewData(string $systemKey, string $title, string $contentHtml, array $sections): array {
        $sample = (string)$this->language->get('text_page_preview_sample_content');
        $sampleItem = (string)$this->language->get('text_page_preview_sample_item');
        $sampleHtml = '<p>' . htmlspecialchars($sample, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        $project = ['name'=>$sampleItem, 'image'=>'', 'description'=>$sample, 'client'=>'', 'url'=>''];
        $tool = ['name'=>$sampleItem, 'image'=>'', 'vendor'=>$sampleItem, 'description'=>$sample, 'external'=>false, 'url'=>'', 'href'=>'#preview-item'];
        $shared = [
            'heading_title'=>$title, 'home'=>'#preview-home', 'contact'=>'#preview-contact', 'quote'=>'#preview-quote',
            'button_back_home'=>$sampleItem, 'button_back'=>$sampleItem,
            'button_contact'=>$sampleItem, 'button_quote'=>$sampleItem,
            'text_lead'=>$sample, 'text_no_results'=>$sample, 'cms_page_content'=>$contentHtml,
            'cms_page_sections'=>$sections, 'cms_home_content'=>$contentHtml, 'cms_home_sections'=>$sections,
            'text_filter'=>$sample, 'text_all'=>$sample, 'text_featured'=>$sample, 'text_open'=>$sample,
            'button_view'=>$sample, 'entry_client'=>$sample, 'entry_url'=>$sample, 'entry_tags'=>$sample,
            'entry_gallery'=>$sample, 'entry_vendor'=>$sample, 'entry_external_url'=>$sample,
            'text_open_hint'=>$sample, 'text_read_more'=>$sample, 'text_published_on'=>$sample,
            'text_content_review'=>$sample, 'text_code'=>'404', 'text_error'=>$sample, 'text_suggestion'=>$sample,
            'button_continue'=>$sample, 'text_flash'=>['text'=>'','type'=>'success'], 'error'=>'',
            'action'=>'#preview-form', 'csrf_token'=>'', 'value_name'=>'', 'value_email'=>'',
            'value_telephone'=>'', 'value_subject'=>'', 'value_message'=>'', 'value_firstname'=>'',
            'value_lastname'=>'', 'value_comment'=>'', 'entry_name'=>$sample, 'entry_email'=>$sample,
            'entry_telephone'=>$sample, 'entry_subject'=>$sample, 'entry_message'=>$sample,
            'entry_firstname'=>$sample, 'entry_lastname'=>$sample, 'entry_services'=>$sample,
            'entry_comment'=>$sample, 'text_services'=>$sample, 'text_briefing'=>$sample,
            'text_honeypot_label'=>$sample, 'button_send'=>$sample,
        ];
        return match ($systemKey) {
            'home' => $shared + [
                'hero3d'=>['enabled'=>false,'count'=>0,'color'=>'#6d28d9','speed'=>0,'model'=>''],
                'text_hero_kicker'=>$sampleItem,'text_tagline'=>$sample,'text_hero_lead'=>$sample,
                'button_portfolio'=>$sample,'socials'=>[],
                'text_about_kicker'=>$sample,'text_about_title'=>$sampleItem,'text_about_lead'=>$sample,
                'about_paragraphs'=>[$sample], 'text_about_focus'=>$sampleItem,'text_about_focus_body'=>$sample,
                'text_method_kicker'=>$sample,'text_method_title'=>$sampleItem,'text_method_lead'=>$sample,
                'method_steps'=>[['n'=>'1','title'=>$sampleItem,'text'=>$sample]], 'identity_reasons'=>[], 'text_identity_title'=>$sample,
                'text_skills_kicker'=>$sample,'text_skills_title'=>$sampleItem,'text_skills_lead'=>$sample,
                'skill_chips'=>[$sampleItem], 'skill_groups'=>[['title'=>$sampleItem,'items'=>[$sample]]],
                'text_services_kicker'=>$sample,'text_services_title'=>$sampleItem,'text_services_lead'=>$sample,
                'text_services_cta'=>$sample,'service_groups'=>[['title'=>$sampleItem,'items'=>[$sample]]],
                'testimonials'=>[['text'=>$sample,'author'=>$sampleItem,'company'=>'']],
                'text_testimonials_kicker'=>$sample,'text_testimonials_title'=>$sampleItem,
                'portfolio3d'=>['enabled'=>false,'frame_limit'=>0,'images'=>[]],
                'projects'=>[['name'=>$sampleItem,'href'=>'#preview-project','image'=>'','client'=>'','featured'=>false]],
                'categories'=>[], 'active_category'=>null, 'portfolio'=>'#preview-portfolio',
                'text_portfolio_kicker'=>$sample,'text_portfolio_title'=>$sampleItem,'text_portfolio_lead'=>$sample,
                'tools3d'=>['enabled'=>false,'items'=>[]], 'tools'=>[$tool], 'text_tools_kicker'=>$sample,
                'text_tools_title'=>$sampleItem,'text_tools_lead'=>$sample,'text_tool_open'=>$sample,
                'news_items'=>[['date'=>'','source'=>$sampleItem,'title'=>$sampleItem,'excerpt'=>$sample,'url'=>'#preview-news']],
                'text_news_kicker'=>$sample,'text_news_title'=>$sampleItem,'text_news_read_more'=>$sample,
                'text_news_source'=>$sample,'text_news_visit_blog'=>$sample,'news_blog_href'=>'#preview-blog',
                'client_logos'=>[['name'=>$sampleItem,'image'=>'','url'=>'']],
                'text_clients_kicker'=>$sample,'text_clients_title'=>$sampleItem,
                'text_contact_kicker'=>$sample,'text_contact_title'=>$sampleItem,'text_contact_lead'=>$sample,
                'text_contact_address'=>$sample,'text_contact_email'=>'preview@example.invalid','text_contact_whatsapp'=>'',
                'contact_whatsapp_href'=>'',
            ],
            'portfolio' => $shared + [
                'filter_all'=>['active'=>true,'href'=>'#preview-all'], 'categories'=>[],
                'projects'=>[['name'=>$sampleItem,'href'=>'#preview-project','image'=>'','summary'=>$sample,'client'=>'','featured'=>false]],
            ],
            'portfolio_detail' => $shared + [
                'back_portfolio'=>'#preview-portfolio','text_summary'=>$sample,'categories'=>[],
                'project'=>$project,'text_tag'=>'','images'=>[],
            ],
            'blog' => $shared + [
                'posts'=>[['title'=>$sampleItem,'image'=>'','date'=>'','excerpt'=>$sample,'href'=>'#preview-post']],
            ],
            'blog_post' => $shared + [
                'back_blog'=>'#preview-blog', 'post'=>['title'=>$sampleItem,'image'=>'','date'=>'','content_mode'=>'blocks','content_valid'=>true,'block_html'=>$sampleHtml,'description'=>''],
            ],
            'tools' => $shared + ['tools'=>[$tool]],
            'tool_detail' => $shared + ['back_tools'=>'#preview-tools','tool'=>$tool],
            'contact' => $shared,
            'quote' => $shared + ['services'=>[['key'=>'preview-sample','label'=>$sampleItem,'checked'=>false]]],
            'not_found' => $shared + ['nf3d'=>['count'=>0,'color'=>'#6d28d9','speed'=>0,'drift'=>0]],
            default => [],
        };
    }

    private function previewText(mixed $value, int $limit): string {
        if (!is_string($value) || preg_match('//u', $value) !== 1) return '';
        return mb_substr(trim($value), 0, max(0, $limit), 'UTF-8');
    }

    /**
     * Build exactly the public custom-field view from submitted unsaved values,
     * validated against current public definitions. No values are persisted.
     * @return array<int,array<string,mixed>>|false
     */
    private function buildPreviewPublicCustomFields(array $submitted, int $pageId, int $languageId, int $englishId, array $allowedLanguageIds): array|false {
        if (count($submitted) > 500) return false;
        $definitions = $this->model_custom_field_value->getPageFields($languageId);
        $saved = $pageId > 0 ? $this->model_custom_field_value->getPageValues($pageId) : [];
        $allowedTypes = ['text','textarea','number','boolean','url','select'];
        $groups = [];
        foreach ($definitions as $definition) {
            $definition = (array)$definition;
            if (($definition['visibility'] ?? '') !== 'public' || !in_array((string)($definition['type'] ?? ''), $allowedTypes, true)) continue;
            $fieldId = (int)($definition['field_id'] ?? 0);
            if ($fieldId < 1) continue;
            $localized = (int)($definition['localized'] ?? 0) === 1;
            $posted = $submitted[(string)$fieldId] ?? $submitted[$fieldId] ?? [];
            if (!is_array($posted) || count($posted) > 10) return false;
            foreach ($posted as $postedLang => $postedValue) {
                if (!ctype_digit((string)$postedLang) || !is_scalar($postedValue)) return false;
                $postedLangId = (int)$postedLang;
                if (($localized && !in_array($postedLangId, $allowedLanguageIds, true)) || (!$localized && $postedLangId !== 0)) return false;
                $postedValue = trim((string)$postedValue);
                if (mb_strlen($postedValue, 'UTF-8') > 5000) return false;
                if ($postedValue !== '' && $this->model_custom_field_value->validateValue($definition, $postedValue) !== '') return false;
            }
            $pick = static function (int $langId) use ($posted, $saved, $fieldId): string {
                if (array_key_exists((string)$langId, $posted)) return (string)$posted[(string)$langId];
                if (array_key_exists($langId, $posted)) return (string)$posted[$langId];
                return (string)($saved[$fieldId][$langId] ?? '');
            };
            $value = $pick($localized ? $languageId : 0);
            // Catalog's public reader falls back from an empty localized value to en-US.
            if ($localized && trim($value) === '' && $languageId !== $englishId) $value = $pick($englishId);
            $value = trim($value);
            if ($value === '') continue;
            if (mb_strlen($value, 'UTF-8') > 5000 || $this->model_custom_field_value->validateValue($definition, $value) !== '') return false;
            if ((string)$definition['type'] === 'select') {
                $options = json_decode((string)($definition['options'] ?? '[]'), true); $display = null;
                foreach (is_array($options) ? $options : [] as $option) {
                    $option = (array)$option;
                    if ((string)($option['value'] ?? '') === $value) { $display = (string)($option['label'] ?? ''); break; }
                }
                if ($display === null || $display === '') return false;
                $value = $display;
            } elseif ((string)$definition['type'] === 'boolean') {
                if (!in_array($value, ['0','1'], true)) return false;
                $value = $value === '1' ? $this->language->get('text_yes') : $this->language->get('text_no');
            }
            $groupId = (int)($definition['group_id'] ?? 0);
            if (!isset($groups[$groupId])) $groups[$groupId] = ['label'=>(string)($definition['group_label'] ?? ''),'fields'=>[]];
            $groups[$groupId]['fields'][] = ['label'=>(string)($definition['label'] ?? ''),'value'=>mb_substr($value,0,5000,'UTF-8'),'type'=>(string)$definition['type']];
        }
        return array_values($groups);
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private function stripPagePreviewScripts(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!is_array($node)) continue;
            if (($node['type'] ?? '') === 'core/threejs_thumb' && is_array($node['attributes'] ?? null)) {
                $node['attributes']['custom_js'] = '';
            }
            if (is_array($node['children'] ?? null)) $this->stripPagePreviewScripts($node['children']);
        }
        unset($node);
    }

    private function pageCatalogAssetOrigin(string $assetBase): string {
        $parts = parse_url($assetBase);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) return '';
        $origin = strtolower((string)$parts['scheme']) . '://' . $parts['host'];
        if (isset($parts['port'])) $origin .= ':' . (int)$parts['port'];
        return $origin;
    }

    private function renderPagePreviewError(string $message): void {
        $this->response->setOutput($this->load->view('cms/page_preview', [
            'heading_title' => '',
            'preview_language' => 'en-us',
            'text_page_preview_title' => $this->language->get('text_page_preview_title'),
            'text_page_preview_notice' => '',
            'description' => '',
            'preview_error' => $message,
            'common_css_url' => $this->pageCatalogAssetUrl() . 'view/css/common.css',
            'blocks_css_url' => $this->pageCatalogAssetUrl() . 'view/css/blog-blocks.css',
            'requires_threejs' => false,
            'threejs_css_url' => '',
            'threejs_script_url' => '',
        ]));
    }

    /** GET: listagem da biblioteca independente de mídia de CMS Pages. */
    public function mediaList(): void {
        $this->load->language('cms/page');
        if (!$this->user->hasPermission('modify', 'cms/page')) {
            $this->pageMediaJson(['success' => false, 'error' => $this->language->get('error_page_media_permission')]);
            return;
        }
        if (($this->request->server['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->pageMediaJson(['success' => false, 'error' => $this->language->get('error_page_media_method')]);
            return;
        }
        $search = $this->request->get['search'] ?? '';
        $page = $this->request->get['page'] ?? 1;
        $this->load->model('cms/page_media');
        $kind = $this->request->get['kind'] ?? 'image';
        $kind = is_string($kind) ? $kind : '';
        $result = $this->model_cms_page_media->listPageMedia($kind, is_string($search) ? trim($search) : '', is_scalar($page) ? max(1, (int)$page) : 1);
        if (empty($result['success'])) {
            $this->pageMediaJson(['success' => false, 'error' => $this->language->get('error_page_media_list')]);
            return;
        }
        $base = $this->pageCatalogAssetUrl();
        foreach ($result['files'] as &$file) $file['url'] = $base . $file['path'];
        unset($file);
        $this->pageMediaJson($result);
    }

    /** POST: upload allowlisted Page media with CSRF + ACL enforcement. */
    public function mediaUpload(): void {
        $this->load->language('cms/page');
        if (!$this->user->hasPermission('modify', 'cms/page')) {
            $this->pageMediaJson(['success' => false, 'error' => $this->language->get('error_page_media_permission')]);
            return;
        }
        if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->pageMediaJson(['success' => false, 'error' => $this->language->get('error_page_media_method')]);
            return;
        }
        if ($this->pageMediaRequestExceedsPostLimit()) {
            $this->pageMediaJson(['success' => false, 'error' => $this->language->get('error_page_media_size')]);
            return;
        }
        if (!$this->csrfValid()) {
            $this->pageMediaJson(['success' => false, 'error' => $this->language->get('error_page_media_csrf')]);
            return;
        }
        $this->load->model('cms/page_media');
        $kind = $this->request->post['kind'] ?? '';
        $kind = is_string($kind) ? $kind : '';
        $result = $this->model_cms_page_media->storePageMedia($kind, (array)($this->request->files['file'] ?? []));
        if (empty($result['success'])) {
            $key = match ($result['error'] ?? '') {
                'no_file' => 'error_page_media_no_file',
                'size' => 'error_page_media_size',
                'type' => 'error_page_media_type',
                'root' => 'error_page_media_root',
                default => 'error_page_media_upload',
            };
            $this->pageMediaJson(['success' => false, 'error' => $this->language->get($key)]);
            return;
        }
        $result['url'] = $this->pageCatalogAssetUrl() . $result['path'];
        $this->pageMediaJson($result);
    }

    private function pageMediaRequestExceedsPostLimit(): bool {
        $contentLength = (int)($this->request->server['CONTENT_LENGTH'] ?? 0);
        $setting = trim((string)ini_get('post_max_size'));
        if ($contentLength < 1 || $setting === '' || $setting === '0') return false;
        $unit = strtolower(substr($setting, -1));
        $limit = (int)$setting;
        if ($unit === 'g') $limit *= 1024 * 1024 * 1024;
        elseif ($unit === 'm') $limit *= 1024 * 1024;
        elseif ($unit === 'k') $limit *= 1024;
        return $limit > 0 && $contentLength > $limit;
    }

    /** @param array<string, mixed> $payload */
    private function pageMediaJson(array $payload): void {
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $this->response->setOutput($json === false ? '{"success":false}' : $json);
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $page Linha do banco ou [] (create)
     * @return array<string, mixed> Dados do formulário (GET) ou do POST
     */
    private function formData(array $page, bool $includeEditor = false): array {
        $this->load->language('cms/page');
        $data['can_modify'] = $this->user->hasPermission('modify', 'cms/page');

        foreach ([
            'heading_title', 'text_edit', 'text_add', 'text_general', 'text_data',
            'text_languages_note', 'text_system_page', 'text_system_page_help', 'entry_title', 'entry_description',
            'entry_meta_title', 'entry_meta_description', 'entry_keyword',
            'entry_status', 'entry_sort_order',
            'button_save', 'button_cancel', 'text_enabled', 'text_disabled',
            'text_meta_group', 'help_sort_order', 'help_description', 'text_page_preview_button', 'error_page_preview_unavailable', 'text_page_media_library', 'text_page_media_empty', 'text_page_media_empty_audio', 'text_page_media_empty_video', 'text_page_media_empty_download', 'text_page_media_tab_image', 'text_page_media_tab_audio', 'text_page_media_tab_video', 'text_page_media_tab_download', 'text_page_media_loading', 'text_page_media_search', 'text_page_media_upload_hint', 'text_page_media_upload_hint_audio', 'text_page_media_upload_hint_video', 'text_page_media_upload_hint_download', 'text_page_media_uploading', 'text_page_media_select', 'text_page_media_page', 'text_page_media_upload', 'text_page_media_error', 'error_page_media_permission', 'error_page_media_method', 'error_page_media_csrf', 'error_page_media_list', 'error_page_media_no_file', 'error_page_media_size', 'error_page_media_type', 'error_page_media_root', 'error_page_media_upload', 'text_page_context_page', 'text_page_context_language', 'text_page_context_untitled', 'text_page_context_save_hint', 'text_page_context_readonly_hint', 'text_content_summary_note', 'text_content_empty', 'text_content_save_first', 'text_content_block_count', 'text_content_invalid', 'text_page_content_editor', 'text_content_editor_help', 'button_edit_content', 'button_save_content', 'text_editor_home', 'text_editor_insert', 'text_editor_view', 'text_editor_clipboard', 'text_editor_text_format', 'text_editor_paragraph_alignment', 'text_editor_group_insert', 'text_editor_group_panels', 'text_editor_group_preview', 'text_editor_close_panel', 'text_editor_align_left', 'text_editor_align_center', 'text_editor_align_right', 'text_editor_import_file', 'text_editor_import_success', 'text_editor_preview_title', 'text_editor_preview_mode', 'text_editor_edit_mode', 'text_editor_import_hint', 'text_editor_import_unavailable', 'text_editor_import_error', 'text_editor_import_audio_library', 'text_editor_import_video_library', 'text_editor_preview_video_link', 'text_editor_preview_video_open', 'text_custom_fields', 'text_custom_fields_note', 'text_shared_value', 'text_select_option', 'text_yes', 'text_no', 'error_edit_conflict', 'button_reload_current', 'text_revision_compare', 'text_revision_version', 'text_revision_current', 'text_revision_body', 'text_revision_meta', 'text_revision_error', 'text_revision_diff', 'text_revision_unchanged', 'text_revision_diff_limit',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $this->document->addScript($this->config->get('site_url') . 'view/js/cms-page.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);
        $this->document->addScript($this->config->get('site_url') . 'view/js/bdre-concurrency.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

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
                'text_block_pattern_cover_hero', 'text_block_pattern_hint', 'text_block_saved_patterns', 'text_block_save_pattern', 'text_block_rename_pattern', 'text_block_delete_pattern', 'text_block_pattern_name_prompt', 'text_block_pattern_rename_prompt', 'text_block_pattern_delete_confirm', 'text_block_pattern_saved', 'text_block_pattern_renamed', 'text_block_pattern_deleted', 'text_block_pattern_error', 'text_block_pattern_incompatible', 'text_block_pattern_invalid', 'text_block_pattern_custom_js_rejected', 'text_block_draft_recovery_prompt', 'text_block_draft_recovery_hint', 'text_block_export_document', 'text_block_export_success', 'text_block_export_error', 'text_block_import_replace_confirm', 'text_block_history_button', 'text_block_history_title', 'text_block_history_empty', 'text_block_history_restore', 'text_block_history_restore_confirm', 'text_block_history_restored', 'text_block_find_replace', 'text_block_find_keyboard_hint', 'text_block_find_label', 'text_block_replace_label', 'text_block_find_next', 'text_block_find_previous', 'text_block_replace_one', 'text_block_replace_all', 'text_block_find_case_sensitive', 'text_block_find_whole_word', 'text_block_find_cross_run_hint', 'text_block_find_none', 'text_block_find_count', 'text_block_replace_done', 'text_block_replace_limit', 'text_block_find_empty', 'text_block_document_statistics', 'text_block_document_words', 'text_block_document_characters', 'text_block_inspector_tabs', 'text_block_custom_content', 'text_block_customize', 'text_block_custom_text_color', 'text_block_custom_background', 'text_block_custom_border_color', 'text_block_custom_border_style', 'text_block_custom_border_solid', 'text_block_custom_border_dashed', 'text_block_custom_border_dotted', 'text_block_custom_border_width', 'text_block_custom_radius', 'text_block_custom_padding', 'text_block_custom_margin', 'text_block_custom_font_size', 'text_block_custom_text_align', 'text_block_custom_inherit', 'text_block_custom_hint', 'text_block_custom_reset', 'text_block_gallery_preview', 'text_block_columns_add', 'text_block_columns_remove', 'text_block_columns_limit', 'text_block_column_default', 'text_block_ribbon_review', 'text_block_save_state_saved', 'text_block_save_state_unsaved', 'text_block_save_state_saving',
            ] as $key) {
                $data[$key] = $this->language->get($key);
            }

            $data['block_pattern_config'] = [
                'listUrl' => $this->url->link('cms/block_pattern.listPages'),
                'getUrl' => $this->url->link('cms/block_pattern.getPages'),
                'saveUrl' => $this->url->link('cms/block_pattern.savePages'),
                'renameUrl' => $this->url->link('cms/block_pattern.renamePages'),
                'deleteUrl' => $this->url->link('cms/block_pattern.deletePages'),
                'csrfToken' => \Reamur\System\Library\Security\Csrf::token($this->session),
                'draftKey' => 'reamur:bdre:draft:v1:u' . (int)$this->user->getUserId() . ':pages:' . (int)($page['page_id'] ?? 0),
                'canRead' => $this->user->hasPermission('access', 'cms/block_pattern') && $this->user->hasPermission('modify', 'cms/page'),
                'canModify' => $this->user->hasPermission('modify', 'cms/block_pattern') && $this->user->hasPermission('modify', 'cms/page'),
            ];

            $data['block_editor_strings'] = [
                'bold' => $data['text_inline_bold'], 'italic' => $data['text_inline_italic'],
                'underline' => $data['text_inline_underline'], 'strike' => $data['text_inline_strike'], 'code' => $data['text_inline_code'],
                'link' => $data['text_inline_link'], 'linkPlaceholder' => $data['text_inline_link_placeholder'],
                'applyLink' => $data['text_inline_apply_link'], 'removeLink' => $data['text_inline_remove_link'],
                'invalidLink' => $data['text_inline_invalid_link'], 'toolbar' => $data['text_inline_toolbar'],
                'textLabel' => $data['text_inline_text_label'], 'placeholder' => $data['text_inline_placeholder'],
                'editorLabel' => $data['text_inline_editor_label'],
                'ribbonHome' => $data['text_editor_home'], 'ribbonInsert' => $data['text_editor_insert'], 'ribbonView' => $data['text_editor_view'], 'ribbonReview' => $data['text_block_ribbon_review'], 'workspaceRailLabel' => $data['text_block_workspace_rail_label'] ?? 'Ferramentas do espaço de trabalho', 'workspaceToolText' => $data['text_block_workspace_tool_text'] ?? 'Texto', 'workspaceToolAppearance' => $data['text_block_workspace_tool_appearance'] ?? 'Aparência', 'workspaceToolPatterns' => $data['text_block_workspace_tool_patterns'] ?? 'Padrões',
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
            'savedPatternsCategory' => $data['text_block_saved_patterns'], 'saveAsPattern' => $data['text_block_save_pattern'],
            'renamePattern' => $data['text_block_rename_pattern'], 'deletePattern' => $data['text_block_delete_pattern'],
            'patternNamePrompt' => $data['text_block_pattern_name_prompt'], 'patternRenamePrompt' => $data['text_block_pattern_rename_prompt'],
            'patternDeleteConfirm' => $data['text_block_pattern_delete_confirm'], 'patternSaved' => $data['text_block_pattern_saved'],
            'patternRenamed' => $data['text_block_pattern_renamed'], 'patternDeleted' => $data['text_block_pattern_deleted'],
            'patternError' => $data['text_block_pattern_error'], 'patternIncompatible' => $data['text_block_pattern_incompatible'],
            'patternInvalid' => $data['text_block_pattern_invalid'], 'patternCustomJsRejected' => $data['text_block_pattern_custom_js_rejected'],
            'draftRecoveryPrompt' => $data['text_block_draft_recovery_prompt'],
            'draftRecoveryHint' => $data['text_block_draft_recovery_hint'],
            'exportDocument' => $data['text_block_export_document'], 'exportSuccess' => $data['text_block_export_success'],
            'exportError' => $data['text_block_export_error'], 'importReplaceConfirm' => $data['text_block_import_replace_confirm'],
            'historyButton' => $data['text_block_history_button'], 'historyTitle' => $data['text_block_history_title'],
            'historyEmpty' => $data['text_block_history_empty'], 'historyRestore' => $data['text_block_history_restore'],
            'historyRestoreConfirm' => $data['text_block_history_restore_confirm'], 'historyRestored' => $data['text_block_history_restored'],
            'findReplaceButton' => $data['text_block_find_replace'], 'findKeyboardHint' => $data['text_block_find_keyboard_hint'], 'findLabel' => $data['text_block_find_label'], 'replaceLabel' => $data['text_block_replace_label'],
            'findNext' => $data['text_block_find_next'], 'findPrevious' => $data['text_block_find_previous'], 'replaceOne' => $data['text_block_replace_one'], 'replaceAll' => $data['text_block_replace_all'],
            'findCaseSensitive' => $data['text_block_find_case_sensitive'], 'findWholeWord' => $data['text_block_find_whole_word'], 'findCrossRunHint' => $data['text_block_find_cross_run_hint'], 'findNoMatches' => $data['text_block_find_none'],
            'findMatchCount' => $data['text_block_find_count'], 'replaceDone' => $data['text_block_replace_done'], 'replaceLimit' => $data['text_block_replace_limit'], 'findEmpty' => $data['text_block_find_empty'],
            'documentStatistics' => $data['text_block_document_statistics'], 'documentWords' => $data['text_block_document_words'], 'documentCharacters' => $data['text_block_document_characters'],
            'inspectorTabs' => $data['text_block_inspector_tabs'], 'customContentTab' => $data['text_block_custom_content'], 'customizeTab' => $data['text_block_customize'], 'customTextColor' => $data['text_block_custom_text_color'], 'customBackground' => $data['text_block_custom_background'], 'customBorderColor' => $data['text_block_custom_border_color'], 'customBorderStyle' => $data['text_block_custom_border_style'], 'customBorderSolid' => $data['text_block_custom_border_solid'], 'customBorderDashed' => $data['text_block_custom_border_dashed'], 'customBorderDotted' => $data['text_block_custom_border_dotted'], 'customBorderWidth' => $data['text_block_custom_border_width'], 'customCornerRadius' => $data['text_block_custom_radius'], 'customPadding' => $data['text_block_custom_padding'], 'customMargin' => $data['text_block_custom_margin'], 'customFontSize' => $data['text_block_custom_font_size'], 'customTextAlign' => $data['text_block_custom_text_align'], 'customInherit' => $data['text_block_custom_inherit'], 'customAppearanceHint' => $data['text_block_custom_hint'], 'customReset' => $data['text_block_custom_reset'], 'galleryPreview' => $data['text_block_gallery_preview'], 'columnsAdd' => $data['text_block_columns_add'], 'columnsRemove' => $data['text_block_columns_remove'], 'columnsLimit' => $data['text_block_columns_limit'], 'columnDefault' => $data['text_block_column_default'],
            ];

                $site = $this->config->get('site_url');
                $version = \Reamur\Admin\Controller\Common\Header::ASSET_V . '-threejs-9';
                $catalogAssetBase = $this->pageCatalogAssetUrl();
                $this->document->addStyle($site . 'view/css/blog-block-editor.css?v=' . $version);
                $this->document->addStyle($catalogAssetBase . 'view/css/threejs-thumb.css?v=' . $version);
                $this->document->addScript($catalogAssetBase . $this->three_asset_manifest->publicBasePath() . 'threejs-thumb.js?v=' . $version);
                $this->document->addScript($site . 'view/js/blog-block-import.js?v=' . $version);
                $this->document->addScript($site . 'view/js/blog-block-editor.js?v=' . $version);
                $this->document->addScript($site . 'view/js/blog-block-editor-init.js?v=' . $version);
                if (!empty($data['can_modify'])) {
                    $this->document->addScript($site . 'view/js/cms-page-preview.js?v=' . $version);
                    $data['page_preview_url'] = $this->url->link('cms/page.preview');
                    $this->document->addScript($site . 'view/js/cms-page-media.js?v=' . $version);
                    $data['page_image_media_config'] = [
                        'listUrl' => $this->url->link('cms/page.mediaList'),
                        'uploadUrl' => $this->url->link('cms/page.mediaUpload'),
                        'csrfToken' => \Reamur\System\Library\Security\Csrf::token($this->session),
                        'strings' => [
                            'loading' => $data['text_page_media_loading'], 'empty' => $data['text_page_media_empty'],
                            'search' => $data['text_page_media_search'], 'uploading' => $data['text_page_media_uploading'],
                            'select' => $data['text_page_media_select'], 'page' => $data['text_page_media_page'],
                            'upload' => $data['text_page_media_upload'], 'error' => $data['text_page_media_error'],
                            'uploadHint' => ['image'=>$data['text_page_media_upload_hint'], 'audio'=>$data['text_page_media_upload_hint_audio'], 'video'=>$data['text_page_media_upload_hint_video'], 'download'=>$data['text_page_media_upload_hint_download']],
                            'emptyKind' => ['image'=>$data['text_page_media_empty'], 'audio'=>$data['text_page_media_empty_audio'], 'video'=>$data['text_page_media_empty_video'], 'download'=>$data['text_page_media_empty_download']],
                            'tabs' => ['image'=>$data['text_page_media_tab_image'], 'audio'=>$data['text_page_media_tab_audio'], 'video'=>$data['text_page_media_tab_video'], 'download'=>$data['text_page_media_tab_download']],
                        ],
                    ];
                }



        }

        $data['error']      = '';
        $data['text_flash'] = '';
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);

        $pid          = (int)($page['page_id'] ?? 0);
        $systemKey    = (string)($page['system_key'] ?? '');
        $pageLayout   = $this->cms_page_layout;
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
            $layoutSections = [];
            $layoutOptions = $pageLayout->options($systemKey);
            if ($layoutOptions !== []) {
                $submittedSections = $post['page_layout_sections'][$lid] ?? null;
                if ($submittedSections === null && isset($post['page_layout_sections_present'][$lid])) $submittedSections = [];
                $hasEditorial = trim($description) !== '';
                if (str_starts_with($description, 'REAMURCMS_BDRE_JSON_V2:')) {
                    $existingContent = (new \Reamur\System\Library\Blog\BlockDocument(true))->deserializeDocument(substr($description, strlen('REAMURCMS_BDRE_JSON_V2:')));
                    $hasEditorial = !empty($existingContent['valid']) && !empty($existingContent['document']['blocks']);
                }
                $layoutSections = is_array($submittedSections) ? $submittedSections : $pageLayout->defaults($systemKey, $hasEditorial);
                $savedLayout = $pageLayout->decode($description, $systemKey);
                if ($savedLayout !== null) {
                    $layoutSections = is_array($submittedSections) ? $submittedSections : $savedLayout['sections'];
                    $description = 'REAMURCMS_BDRE_JSON_V2:' . (new \Reamur\System\Library\Blog\BlockDocument(true))->serializeDocument($savedLayout['content']);
                    $fields['description'] = $description;
                }
            }
            $summary = '';
            $prefixForEditor = 'REAMURCMS_BDRE_JSON_V2:';
            $contentSavedValue = $description;
            $blockSummaryDocument = null;
            $blockPrefix = 'REAMURCMS_BDRE_JSON_V2:';
            $blockMode = 'legacy';
            $blockJson = '';
            $storedBlockValue = $description;
            $invalidLayoutEnvelope = $layoutOptions !== [] && str_starts_with($description, \Reamur\System\Library\Cms\PageLayout::PREFIX);
            if ($invalidLayoutEnvelope) {
                $blockMode = 'invalid';
                $summary = $data['text_content_invalid'];
            } elseif (str_starts_with($description, $blockPrefix)) {
                $blockJson = substr($description, strlen($blockPrefix));
                $decoded = (new \Reamur\System\Library\Blog\BlockDocument(true))->deserializeDocument($blockJson);
                if (!empty($decoded['valid']) && is_array($decoded['document']) && ($decoded['document']['format_version'] ?? null) === 2) {
                    $blockMode = 'blocks';
                    $blockSummaryDocument = $decoded['document'];
                    // Canonicalize in memory on read too: older PHP JSON encoding wrote empty attributes as [] instead of {}.
                    $blockJson = (new \Reamur\System\Library\Blog\BlockDocument(true))->serializeDocument($decoded['document']);
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
                $conversion = (new \Reamur\System\Library\Blog\BlockDocument(true))->convertLegacy($description);
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
            $fields['page_layout_sections'] = $layoutSections;
            $fields['page_layout_options'] = $layoutOptions;
            $fields['page_layout_system_key'] = $systemKey;

            $data['languages'][] = [
                'language_id' => $lid,
                'name'        => (string)$language['name'],
                'code'        => (string)$language['code'],
                'fields'      => $fields,
                'content_editor_url' => $pid ? $this->url->link('cms/page.content|' . $pid, 'language_id=' . $lid) : '',
            ];
        }

        $data['page_id']    = $pid;
        $data['edit_core_fingerprint'] = $pid > 0 ? $this->model_cms_page->getPageEditFingerprint($pid) : '';
        $data['edit_fingerprint'] = $pid > 0 ? $this->pageEditFingerprint($pid) : '';
        $data['revision_conflict'] = false;
        $data['page_revisions_url'] = $pid > 0 ? $this->url->link('cms/page.revisions|' . $pid) : '';
        $data['page_revision_url'] = $pid > 0 ? $this->url->link('cms/page.revision|' . $pid) : '';
        $data['system_key'] = $systemKey;
        $data['is_system_page'] = $data['system_key'] !== '';
        $layoutBySystemKey = [
            'home'=>'common/home', 'portfolio'=>'catalog/project_list', 'portfolio_detail'=>'catalog/project_info',
            'blog'=>'blog/blog_list', 'blog_post'=>'blog/blog_info', 'tools'=>'tool/tool_list',
            'tool_detail'=>'tool/tool_info', 'contact'=>'common/contact', 'quote'=>'checkout/quote', 'not_found'=>'error/not_found',
        ];
        $data['theme_template_key'] = $layoutBySystemKey[$data['system_key']] ?? 'cms/page_info';
        $data['theme_editor_url'] = '';
        if ($this->user->hasPermission('access', 'design/theme')) {
            $this->load->language('design/theme');
            $data['text_template_editor'] = $this->language->get('text_template_editor');
            $data['text_template_help'] = $this->language->get('text_template_help');
            $data['button_edit_theme'] = $this->language->get('button_edit');
            $this->load->model('design/theme');
            $activeThemeId = 0;
            foreach ($this->model_design_theme->getThemes() as $themeRow) {
                $themeRow = (array)$themeRow;
                if ((int)($themeRow['status'] ?? 0) === 1) { $activeThemeId = (int)($themeRow['theme_id'] ?? 0); break; }
            }
            $data['theme_editor_url'] = $activeThemeId > 0
                ? $this->url->link('design/theme.edit|' . $activeThemeId, 'focus_template=' . rawurlencode($data['theme_template_key']))
                : $this->url->link('design/theme');
        }
        $data['sort_order'] = (string)($post['sort_order'] ?? (string)($page['sort_order'] ?? '0'));
        $data['status']     = $isPost ? ((string)($post['status'] ?? '0') === '1' ? '1' : '0') : (string)(int)($page['status'] ?? 0);

        $data['cancel'] = $this->url->link('cms/page');

        return $data;
    }

    /** Add custom-field values to the CMS Page document fingerprint for stale-form detection. */
    private function pageEditFingerprint(int $pageId): string {
        $core = $this->model_cms_page->getPageEditFingerprint($pageId);
        if ($core === '' || !isset($this->model_custom_field_value)) return '';
        $custom = $this->model_custom_field_value->getPageValues($pageId);
        $sort = function (&$value) use (&$sort): void {
            if (!is_array($value)) return;
            foreach ($value as &$child) $sort($child); unset($child);
            if (array_keys($value) !== range(0, count($value) - 1)) ksort($value);
        };
        $sort($custom);
        $json = json_encode($custom, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? hash('sha256', $core . "\\0" . $json) : '';
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
                $decoded = (new \Reamur\System\Library\Blog\BlockDocument(true))->deserializeDocument(substr($description, strlen('REAMURCMS_BDRE_JSON_V2:')));
                if (!empty($decoded['valid']) && is_array($decoded['document']) && ($decoded['document']['format_version'] ?? null) === 2) {
                    $content = $decoded['document'];
                    $description = 'REAMURCMS_BDRE_JSON_V2:' . (new \Reamur\System\Library\Blog\BlockDocument(true))->serializeDocument($content);
                    if ($pageId > 0) {
                        $page = $this->model_cms_page->getPage($pageId);
                        $systemKey = (string)($page['system_key'] ?? '');
                        $postedSections = $post['page_layout_sections'][$lid] ?? (isset($post['page_layout_sections_present'][$lid]) ? [] : null);
                        $sections = $this->cms_page_layout->normalizeSections($systemKey, $postedSections);
                        if ($sections !== null) $description = $this->cms_page_layout->encode($systemKey, $content, $sections);
                    }
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
        $page = $pageId ? $this->model_cms_page->getPage($pageId) : [];
        $systemKey = (string)($page['system_key'] ?? '');
        $layoutOptions = $this->cms_page_layout->options($systemKey);
        $prefix = 'REAMURCMS_BDRE_JSON_V2:';
        $validator = new \Reamur\System\Library\Blog\BlockDocument(true);
        foreach ($descriptions as $languageId => $posted) {
            $mode = (string)($modes[$languageId] ?? '');
            $posted = (array)$posted;
            $description = (string)($posted['description'] ?? '');
            $hasMarker = str_starts_with($description, $prefix);
            if ($mode === 'invalid') {
                $original = (string)($storedDescriptions[(int)$languageId]['description'] ?? '');
                if (!$pageId || $original === '' || !hash_equals($original, $description)) {
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
                continue;
            }
            $postedSections = $post['page_layout_sections'][$languageId] ?? (isset($post['page_layout_sections_present'][$languageId]) ? [] : null);
            if ($layoutOptions !== [] && $this->cms_page_layout->normalizeSections($systemKey, $postedSections) === null) {
                $errors[] = 'A ordem das seções da página é inválida. Recarregue a página e tente novamente.';
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
