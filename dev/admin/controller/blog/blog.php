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
 * Diferenças do page: ordering cronológico (date_published), campo de capa
 * preservado como texto/URL com seletor de mídia Blog-only (F8-05), e SEM
 * sort_order (o editorial é a data). O importador F7-03 permanece independente.
 * Rotas: /blog/blog · .add · .edit|N · .delete|N · .mediaList · .mediaUpload · .mediaFolder · .mediaDelete.
 */
namespace Reamur\Admin\Controller\Blog;

use Reamur\System\Library\MediaUpload;

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
        $data['csrf_token']           = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['can_modify']           = $this->user->hasPermission('modify', 'blog/blog');

        // F7-03 (ADR 0044): acesso ao importador WP somente com modify no módulo
        $data['button_import_wp'] = $this->language->get('button_import_wp');
        $data['import_href']      = $this->user->hasPermission('modify', 'blog/blog')
            ? $this->url->link('blog/import')
            : '';

        // F8-02: rótulos da barra de filtros, badges e estados
        foreach ([
            'text_total_posts', 'text_showing', 'text_search_placeholder',
            'text_all_status', 'text_published', 'text_draft', 'text_view_site',
            'text_empty_state', 'text_empty_state_cta',
            'button_filter', 'button_reset',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        // F8-02 (ADR 0046): filtros server-side (busca por título, status)
        // e paginação 20/página preservando os filtros nos links.
        $filter_title  = trim((string)($this->request->get['filter_title'] ?? ''));
        $filter_status = (string)($this->request->get['filter_status'] ?? 'all');

        if (!in_array($filter_status, ['all', '0', '1'], true)) {
            $filter_status = 'all';
        }

        $page    = max(1, (int)($this->request->get['page'] ?? 1));
        $limit   = 20;
        $filters = ['title' => $filter_title, 'status' => $filter_status];

        $total = $this->model_blog_blog->getTotalPosts($filters);

        // Página além do fim (ex.: após deleções) → recua para a última válida
        if ($total > 0 && ($page - 1) * $limit >= $total) {
            $page = max(1, (int)ceil($total / $limit));
        }

        $rows = $this->model_blog_blog->getPosts($filters + [
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
        ]);

        // Imagens locais pertencem ao app Catalog; URLs externas HTTP(S) continuam válidas.
        $catalogBase = $this->catalogAssetUrl();
        $data['posts'] = [];

        foreach ($rows as $post) {
            $post = (array)$post;
            $pid  = (int)$post['post_id'];
            $img  = trim((string)($post['image'] ?? ''));

            $data['posts'][] = [
                'post_id'        => $pid,
                'title'          => (string)$post['title'],
                'status'         => (int)$post['status'],
                'date_published' => (string)$post['date_published'],
                'date_modified'  => (string)$post['date_modified'],
                'thumb'          => $this->catalogImageUrl($img),
                'view_site'      => $catalogBase . 'index.php?route=blog/blog.info|' . $pid,
                'edit'           => $this->url->link('blog/blog.edit|' . $pid),
                'delete'         => $this->url->link('blog/blog.delete|' . $pid),
            ];
        }

        $data['add'] = $this->url->link('blog/blog.add');

        $data['total_posts']     = $total;
        $data['filter_title']    = $filter_title;
        $data['filter_status']   = $filter_status;

        $data['filter_action'] = $this->url->link('blog/blog');
        $data['filter_reset']  = $this->url->link('blog/blog');

        // mudança de filtro sempre volta para a página 1 (JS-admin: submit puro)
        require_once DIR_SYSTEM . 'library/pagination.php';

        $pagination           = new \Pagination();
        $pagination->total    = $total;
        $pagination->page     = $page;
        $pagination->limit    = $limit;
        $pagination->url      = $this->url->link('blog/blog', 'page={page}' . ($filter_title !== '' ? '&filter_title=' . rawurlencode($filter_title) : '') . ($filter_status !== 'all' ? '&filter_status=' . $filter_status : ''));
        $pagination->css_class = 'pagination pagination-sm mb-0';

        $data['pagination'] = $pagination->render();
        $data['showing']    = $total > 0
            ? sprintf(
                $this->language->get('text_showing'),
                (($page - 1) * $limit) + 1,
                min($page * $limit, $total),
                $total
              )
            : '';

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
            $errors = $this->csrfValid()
                ? $this->validatePost(0)
                : [$this->language->get('error_csrf')];
            $blockSave = ['storage_available' => false, 'documents' => [], 'snapshots' => [], 'errors' => []];
            if ($errors === []) {
                $blockSave = $this->prepareBlockSubmission(0);
                $errors = array_merge($errors, $blockSave['errors']);
            }

            if ($errors === []) {
                $payload = $this->postPayload();
                foreach ($blockSave['snapshots'] as $lid => $html) $payload['descriptions'][$lid]['description'] = $html;
                $post_id = $blockSave['storage_available']
                    ? $this->model_blog_blog->savePostWithBlocks(0, $payload, $blockSave['documents'])
                    : $this->model_blog_blog->addPost($payload);

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

            $submittedFingerprint = (string)($this->request->post['edit_fingerprint'] ?? '');
            $data['edit_fingerprint'] = $submittedFingerprint;
            $currentFingerprint = $this->model_blog_blog->getPostEditFingerprint($post_id);
            $fingerprintMatches = $submittedFingerprint !== '' && $currentFingerprint !== '' && hash_equals($currentFingerprint, $submittedFingerprint);
            $errors = !$this->csrfValid() ? [$this->language->get('error_csrf')] : (!$fingerprintMatches ? [$this->language->get('error_edit_conflict')] : $this->validatePost($post_id));
            if (!$fingerprintMatches && $this->csrfValid()) { $data['revision_conflict'] = true; $data['edit_fingerprint'] = $submittedFingerprint; }
            $blockSave = ['storage_available' => false, 'documents' => [], 'snapshots' => [], 'errors' => []];
            if ($errors === []) {
                $blockSave = $this->prepareBlockSubmission($post_id);
                $errors = array_merge($errors, $blockSave['errors']);
            }
            if ($errors === [] && !hash_equals($submittedFingerprint, $this->model_blog_blog->getPostEditFingerprint($post_id))) { $data['revision_conflict'] = true; $errors = [$this->language->get('error_edit_conflict')]; }

            if ($errors === []) {
                $payload = $this->postPayload();
                foreach ($blockSave['snapshots'] as $lid => $html) $payload['descriptions'][$lid]['description'] = $html;
                try {
                    if ($blockSave['storage_available']) $this->model_blog_blog->savePostWithBlocks($post_id, $payload, $blockSave['documents'], $submittedFingerprint);
                    else $this->model_blog_blog->editPost($post_id, $payload, true, $submittedFingerprint);
                } catch (\RuntimeException $exception) {
                    if ($exception->getMessage() !== 'edit_conflict') throw $exception;
                    $data['revision_conflict'] = true; $errors = [$this->language->get('error_edit_conflict')];
                }
                if ($errors === []) {
                    $this->setFlash($this->language->get('text_success_edit'));
                    $this->response->redirect($this->url->link('blog/blog'));
                    return;
                }
                $data['error'] = implode("\n", $errors);
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('blog/blog.edit|' . $post_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('blog/blog_form', $data));
    }

    /** POST-only list of saved revisions for the current Blog post/language. */
    public function revisions(string $post_id = '0'): void {
        $this->load->language('blog/blog'); $this->load->model('blog/blog');
        if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST' || !$this->csrfValid() || !$this->user->hasPermission('modify', 'blog/blog')) { $this->mediaJson(['success'=>false,'error'=>'forbidden']); return; }
        $postId=(int)$post_id; $languageId=(int)($this->request->post['language_id'] ?? 0); $knownLanguages=array_map(static fn($l)=>(int)((array)$l)['language_id'],$this->model_blog_blog->getLanguages());
        if ($postId < 1 || !$this->model_blog_blog->getPost($postId) || !in_array($languageId,$knownLanguages,true)) { $this->mediaJson(['success'=>false,'error'=>'invalid_target']); return; }
        $items=$this->model_blog_blog->getPostRevisions($postId,$languageId);
        $this->mediaJson(['success'=>true,'items'=>array_map(static fn($r)=>['revision_id'=>$r['revision_id'],'date_added'=>$r['date_added']],$items)]);
    }

    /** POST-only fetch; restored content stays in memory until explicit Save. */
    public function revision(string $post_id = '0'): void {
        $this->load->language('blog/blog'); $this->load->model('blog/blog');
        if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST' || !$this->csrfValid() || !$this->user->hasPermission('modify', 'blog/blog')) { $this->mediaJson(['success'=>false,'error'=>'forbidden']); return; }
        $postId=(int)$post_id; $languageId=(int)($this->request->post['language_id'] ?? 0); $revisionId=(int)($this->request->post['revision_id'] ?? 0); $knownLanguages=array_map(static fn($l)=>(int)((array)$l)['language_id'],$this->model_blog_blog->getLanguages());
        if ($postId < 1 || !$this->model_blog_blog->getPost($postId) || !in_array($languageId,$knownLanguages,true) || $revisionId < 1) { $this->mediaJson(['success'=>false,'error'=>'invalid_target']); return; }
        foreach ($this->model_blog_blog->getPostRevisions($postId,$languageId) as $revision) if ((int)$revision['revision_id']===$revisionId) { $this->mediaJson(['success'=>true,'snapshot'=>$revision['snapshot']]); return; }
        $this->mediaJson(['success'=>false,'error'=>'not_found']);
    }

    /** Preview the unsaved Blog BDRE document in the Catalog post template. */
    public function preview(): void {
        $this->load->language('blog/blog');
        $this->blogPreviewHeaders();
        if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->blogPreviewError($this->language->get('error_media_method'));
            return;
        }
        if (!$this->user->hasPermission('modify', 'blog/blog')) {
            $this->blogPreviewError($this->language->get('error_permission'));
            return;
        }
        if (!$this->csrfValid()) {
            $this->blogPreviewError($this->language->get('error_csrf'));
            return;
        }

        $this->load->model('blog/blog');
        $this->load->model('blog/media');
        if (!$this->model_blog_blog->blogBlockContentTableExists()) {
            $this->blogPreviewError($this->language->get('error_block_schema_unavailable'));
            return;
        }
        $postId = filter_var($this->request->post['preview_post_id'] ?? '0', FILTER_VALIDATE_INT);
        $languageId = filter_var($this->request->post['preview_language_id'] ?? '0', FILTER_VALIDATE_INT);
        if ($postId === false || $postId < 0 || $languageId === false || $languageId < 1 ||
            ($postId > 0 && !$this->model_blog_blog->getPost((int)$postId))) {
            $this->blogPreviewError($this->language->get('error_block_document_invalid'));
            return;
        }
        $languages = [];
        foreach ($this->model_blog_blog->getLanguages() as $row) {
            $row = (array)$row;
            $languages[(int)($row['language_id'] ?? 0)] = $row;
        }
        if (!isset($languages[(int)$languageId])) {
            $this->blogPreviewError($this->language->get('error_block_document_invalid'));
            return;
        }
        $languageCode = (string)($languages[(int)$languageId]['code'] ?? 'en-us');
        if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/i', $languageCode)) $languageCode = 'en-us';
        foreach (['blog/blog','common/header','common/footer','common/home'] as $route) $this->load->language($route, $languageCode);
        $texts = $this->language->all();

        $json = $this->request->post['preview_document'] ?? null;
        if (($this->request->post['preview_editor_ready'] ?? '') !== '1' || !is_string($json) || strlen($json) > 4194304) {
            $this->blogPreviewError($this->language->get('error_block_document_invalid'));
            return;
        }
        $validator = new \Reamur\System\Library\Blog\BlockDocument();
        $decoded = $validator->deserializeDocument($json);
        if (empty($decoded['valid']) || !$this->blockMediaAvailable((array)$decoded['document']['blocks'])) {
            $this->blogPreviewError($this->language->get('error_block_document_invalid'));
            return;
        }
        $document = (array)$decoded['document'];
        $document['blocks'] = $this->stripBlogPreviewScripts((array)$document['blocks']);
        $canonical = $validator->serializeDocument($document);
        $downloadRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/download/blog';
        $audioRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/audio/blog';
        $videoRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/video/blog';
        $base = $this->catalogUrl();
        $assetBase = $this->catalogAssetUrl();
        $renderer = new \Reamur\System\Library\Blog\BlockRenderer($downloadRoot, $audioRoot, $videoRoot);
        $rendered = $renderer->renderJson($canonical, (int)$document['format_version'], $assetBase);
        if (empty($rendered['valid'])) {
            $this->blogPreviewError($this->language->get('error_block_document_invalid'));
            return;
        }
        $cmsSlot = $this->blogPreviewCmsSlot((int)$languageId, $assetBase, $downloadRoot, $audioRoot, $videoRoot);

        $title = mb_substr($this->postScalar('preview_title'), 0, 255);
        if ($title === '') $title = $this->language->get('text_catalog_preview_untitled');
        $metaTitle = mb_substr($this->postScalar('preview_meta_title'), 0, 255);
        $metaDescription = mb_substr($this->postScalar('preview_meta_description'), 0, 255);
        $image = $this->catalogImageUrl($this->postScalar('preview_image'));
        $timestamp = strtotime($this->postScalar('preview_date_published'));
        $date = $timestamp !== false ? date('Y-m-d', $timestamp) : '';

        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSettings();
        $this->load->model('design/theme');
        $publishedTheme = [];
        $themeRows = $this->model_design_theme->getThemes();
        usort($themeRows, static fn(array $a, array $b): int => (int)($b['theme_id'] ?? 0) <=> (int)($a['theme_id'] ?? 0));
        foreach ($themeRows as $row) {
            $row = (array)$row;
            if ((int)($row['status'] ?? 0) !== 1) continue;
            $config = (string)($row['published_config'] ?? '');
            if ($config === '') $config = (string)($row['config'] ?? '{}');
            $parsed = json_decode($config, true);
            if (is_array($parsed)) $publishedTheme = $parsed;
            break;
        }
        $primary = (string)($publishedTheme['primary'] ?? '#6d28d9');
        $surface = (string)($publishedTheme['surface'] ?? '#ffffff');
        if (!preg_match('/^#[a-fA-F0-9]{6}$/D', $primary)) $primary = '#6d28d9';
        if (!preg_match('/^#[a-fA-F0-9]{6}$/D', $surface)) $surface = '#ffffff';
        $themeTemplates = is_array($publishedTheme['templates'] ?? null) ? $publishedTheme['templates'] : [];
        $requiresThreeJs = !empty($rendered['requires_threejs']) || !empty($cmsSlot['requires_threejs']);
        $styles = [
            $assetBase . 'view/css/blog-blocks.css'=>'stylesheet',
            $assetBase . 'view/css/common.css'=>'stylesheet',
            $assetBase . 'view/css/theme.css'=>'stylesheet',
        ];
        $scripts = [];
        if ($requiresThreeJs) {
            $styles[$assetBase . 'view/css/threejs-thumb.css?v=20261026c']='stylesheet';
            $src = $assetBase . $this->three_asset_manifest->publicBasePath() . 'threejs-thumb.js?v=20261026c';
            $scripts[$src] = $src;
        }
        $languageLinks = [];
        foreach ($languages as $row) {
            $code = strtolower((string)($row['code'] ?? ''));
            if ($code === '') continue;
            $key = 'text_lang_' . str_replace('-', '_', $code);
            $languageLinks[] = ['code'=>$code,'name'=>(string)($texts[$key] ?? $row['name'] ?? $code),'active'=>$code === $languageCode,'href'=>'#preview-language'];
        }
        $publicTitle = $metaTitle !== '' ? $metaTitle : $title;
        $publicDescription = $metaDescription !== '' ? $metaDescription : (string)($settings['config_meta_description'] ?? '');
        $text = static fn(string $key, string $fallback = ''): string => (string)($texts[$key] ?? $fallback);
        $header = [
            'locale'=>$languageCode, 'title'=>$publicTitle, 'description'=>$publicDescription, 'canonical'=>'',
            'sitemap_href'=>'#preview-sitemap',
            'og'=>['type'=>'article','site_name'=>(string)($settings['config_name'] ?? ''),'title'=>$publicTitle,'description'=>$publicDescription,'url'=>'','locale'=>str_replace('-', '_', $languageCode)],
            'styles'=>$styles,'scripts'=>$scripts,'has_3d'=>false,'three_runtime_base'=>'',
            'home'=>'#preview-home','body_class'=>'rms-blog-post','theme_primary'=>$primary,'theme_surface'=>$surface,'private_preview'=>true,
            'text_brand'=>(string)($texts['text_brand'] ?? $settings['config_name'] ?? ''),
            'text_nav_home'=>$text('text_nav_home','Home'),'text_nav_about'=>$text('text_nav_about','About'),
            'text_nav_blog'=>$text('text_nav_blog','Blog'),'text_nav_portfolio'=>$text('text_nav_portfolio','Portfolio'),
            'text_nav_contact'=>$text('text_nav_contact','Contact'),'text_nav_quote'=>$text('text_nav_quote','Request a quote'),
            'nav_about'=>'#preview-about','nav_blog'=>'#preview-blog','nav_portfolio'=>'#preview-portfolio','nav_contact'=>'#preview-contact','nav_quote'=>'#preview-quote',
            'text_languages'=>$text('text_languages','Languages'),'languages'=>$languageLinks,
        ];
        $copyrightKey = 'config_copyright_' . $languageCode;
        $copyright = array_key_exists($copyrightKey, $settings) ? (string)$settings[$copyrightKey] : (string)($texts['text_copyright'] ?? '');
        $socials = [];
        foreach (['facebook','instagram','pinterest','x','google_business','redbubble'] as $network) {
            if (($settings['config_social_' . $network . '_href'] ?? $texts['text_social_' . $network . '_href'] ?? '') !== '') {
                $socials[] = ['name'=>(string)($texts['text_social_' . $network] ?? $network),'href'=>'#preview-social'];
            }
        }
        $legal = [];
        try {
            $this->load->model('cms/page');
            foreach ($this->model_cms_page->getPublicLegalPages((int)$languageId) as $legalPage) {
                $legalPage = (array)$legalPage;
                if ((int)($legalPage['page_id'] ?? 0) > 0) $legal[] = ['name'=>(string)($legalPage['title'] ?? ''),'href'=>'#preview-legal'];
            }
        } catch (\Throwable $exception) { $legal = []; }
        $footer = [
            'text_powered'=>sprintf($text('text_powered',''), defined('REAMUR_VERSION') ? REAMUR_VERSION : ''),
            'text_copyright'=>sprintf($copyright, date('Y')),'text_legal'=>$text('text_legal',''),'legal'=>$legal,'socials'=>$socials,
        ];
        $pageData = ['heading_title'=>$title,'preview_notice'=>$this->language->get('text_catalog_preview_notice')];
        $routeData = [
            'heading_title'=>$title,'back_blog'=>'#preview-blog','button_back'=>$this->language->get('button_back'),
            'text_published_on'=>$this->language->get('text_published_on'),'text_content_review'=>$this->language->get('text_content_review'),
            'cms_page_sections'=>$cmsSlot['sections'],'cms_page_content'=>$cmsSlot['html'],
            'post'=>['post_id'=>(int)$postId,'title'=>$title,'description'=>'','content_mode'=>'blocks','content_valid'=>true,
                'block_html'=>(string)$rendered['html'],'image'=>$image,'date'=>$date],
        ];
        try {
            $html = $this->template->renderCatalogPagePreview($pageData, $header, $footer, $themeTemplates, 'blog/blog_info', $routeData);
        } catch (\Throwable $exception) {
            $this->blogPreviewError($this->language->get('error_block_document_invalid'));
            return;
        }
        $this->response->setOutput($html);
    }

    /** Reads the active Blog-post system-page layout only to reproduce the public Catalog shell. */
    private function blogPreviewCmsSlot(int $languageId, string $assetBase, string $downloadRoot, string $audioRoot, string $videoRoot): array {
        $defaults = $this->cms_page_layout->defaults('blog_post', false);
        $result = ['sections'=>$defaults,'html'=>'','requires_threejs'=>false];
        try {
            $this->load->model('cms/page');
            $pageId = 0;
            foreach ($this->model_cms_page->getPages() as $row) {
                $row = (array)$row;
                if ((string)($row['system_key'] ?? '') === 'blog_post' && (int)($row['status'] ?? 0) === 1) {
                    $pageId = (int)($row['page_id'] ?? 0); break;
                }
            }
            if ($pageId < 1) return $result;
            $descriptions = $this->model_cms_page->getPageDescriptions($pageId);
            $description = trim((string)($descriptions[$languageId]['description'] ?? ''));
            if ($description === '') $description = trim((string)($descriptions[1]['description'] ?? ''));
            if ($description === '') return $result;
            $layout = $this->cms_page_layout->decode($description, 'blog_post');
            if ($layout !== null) {
                $result['sections'] = $layout['sections'];
                $validator = new \Reamur\System\Library\Blog\BlockDocument(true);
                $decoded = $validator->deserializeDocument($validator->serializeDocument((array)$layout['content']));
                if (empty($decoded['valid'])) return $result;
                $doc = (array)$decoded['document'];
                $doc['blocks'] = $this->stripBlogPreviewScripts((array)$doc['blocks']);
                $html = (new \Reamur\System\Library\Blog\BlockRenderer($downloadRoot,$audioRoot,$videoRoot))
                    ->renderJson($validator->serializeDocument($doc), 2, $assetBase);
                if (!empty($html['valid'])) {
                    $result['html'] = (string)$html['html'];
                    $result['requires_threejs'] = !empty($html['requires_threejs']);
                }
                return $result;
            }
            if (str_starts_with($description, \Reamur\System\Library\Cms\PageLayout::PREFIX)) return $result;
            $prefix = 'REAMURCMS_BDRE_JSON_V2:';
            if (str_starts_with($description, $prefix)) {
                $validator = new \Reamur\System\Library\Blog\BlockDocument(true);
                $decoded = $validator->deserializeDocument(substr($description, strlen($prefix)));
                if (empty($decoded['valid'])) return $result;
                $doc = (array)$decoded['document'];
                $doc['blocks'] = $this->stripBlogPreviewScripts((array)$doc['blocks']);
                $html = (new \Reamur\System\Library\Blog\BlockRenderer($downloadRoot,$audioRoot,$videoRoot))
                    ->renderJson($validator->serializeDocument($doc), (int)$doc['format_version'], $assetBase);
                if (!empty($html['valid'])) {
                    $result['sections'] = $this->cms_page_layout->defaults('blog_post', !empty($doc['blocks']));
                    $result['html'] = (string)$html['html'];
                    $result['requires_threejs'] = !empty($html['requires_threejs']);
                }
                return $result;
            }
            // The active legacy HTML slot is already public; this is read-only reuse, not Blog storage.
            $result['sections'] = $this->cms_page_layout->defaults('blog_post', true);
            $result['html'] = $description;
        } catch (\Throwable $exception) {
            return $result;
        }
        return $result;
    }

    /** Private previews use the approved configurable scene but never execute arbitrary scene JavaScript. */
    private function stripBlogPreviewScripts(array $nodes): array {
        foreach ($nodes as &$node) {
            if (!is_array($node)) continue;
            if (($node['type'] ?? '') === 'core/threejs_thumb' && is_array($node['attributes'] ?? null)) $node['attributes']['custom_js'] = '';
            if (is_array($node['children'] ?? null)) $node['children'] = $this->stripBlogPreviewScripts($node['children']);
        }
        unset($node);
        return $nodes;
    }

    private function blogPreviewHeaders(): void {
        $origin = $this->blogPreviewAssetOrigin($this->catalogAssetUrl());
        $this->response->addHeader('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        $this->response->addHeader('Pragma: no-cache');
        $this->response->addHeader('X-Robots-Tag: noindex, nofollow, noarchive');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $this->response->addHeader('X-Frame-Options: DENY');
        $this->response->addHeader('Referrer-Policy: no-referrer');
        $this->response->addHeader("Content-Security-Policy: default-src 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'; form-action 'none'; img-src 'self' data: https: {$origin}; style-src 'self' 'unsafe-inline' {$origin}; script-src 'self' {$origin}; font-src 'self' data: {$origin}; media-src 'self' data: {$origin}; connect-src 'self' {$origin}; frame-src https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com");
    }

    private function blogPreviewAssetOrigin(string $assetBase): string {
        $parts = parse_url($assetBase);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http','https'], true) || empty($parts['host'])) return "'self'";
        return strtolower((string)$parts['scheme']) . '://' . strtolower((string)$parts['host']) . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
    }

    private function blogPreviewError(string $message): void {
        $assetBase = $this->catalogAssetUrl();
        $language = $this->postScalar('preview_language') ?: 'en-us';
        if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/i', $language)) $language = 'en-us';
        $this->response->setOutput($this->load->view('blog/blog_preview_error', [
            'preview_language'=>$language,'heading_title'=>$this->language->get('heading_title'),
            'text_catalog_preview_button'=>$this->language->get('text_catalog_preview_button'),
            'text_catalog_preview_notice'=>$this->language->get('text_catalog_preview_notice'),
            'common_css_url'=>$assetBase . 'view/css/common.css','blocks_css_url'=>$assetBase . 'view/css/blog-blocks.css',
            'preview_error'=>$message,
        ]));
    }

    /** POST exclusivo: ACL + CSRF antes de excluir. */
    public function delete(string $post_id = '0'): void {
        $post_id = (int)$post_id;

        $this->load->language('blog/blog');

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'blog/blog')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        if (!$this->csrfValid()) {
            $this->setFlash($this->language->get('error_csrf'), 'error');
            $this->response->redirect($this->url->link('blog/blog'));

            return;
        }

        $this->load->model('blog/blog');

        if ($this->model_blog_blog->getPost($post_id)) {
            $this->model_blog_blog->deletePost($post_id);
            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('blog/blog'));
    }

    /** Lista imagens e pastas para o seletor (GET; leitura protegida por ACL). */
    public function mediaList(): void {
        $this->load->language('blog/blog');

        if ($this->request->server['REQUEST_METHOD'] !== 'GET') {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_media_method')]);
            return;
        }

        if (!$this->user->hasPermission('modify', 'blog/blog')) {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_permission')]);
            return;
        }

        $this->load->model('blog/media');

        $result = $this->model_blog_media->getMediaPage(
            $this->queryScalar('folder'),
            $this->queryScalar('search'),
            max(1, (int)$this->queryScalar('page'))
        );

        if (!$result['ok']) {
            $this->mediaJson(['success' => false, 'error' => $this->mediaError((string)$result['error'])]);
            return;
        }

        $catalog = $this->catalogAssetUrl();
        $files = [];

        foreach ($result['files'] as $file) {
            $file['url'] = $catalog . ltrim((string)$file['path'], '/');
            $files[] = $file;
        }

        $this->mediaJson([
            'success' => true,
            'folder' => (string)$result['folder'],
            'folders' => $result['folders'],
            'files' => $files,
            'total' => (int)$result['total'],
            'page' => (int)$result['page'],
            'pages' => (int)$result['pages'],
        ]);
    }

    /** Upload validado pelo componente MediaUpload para a pasta Blog escolhida. */
    public function mediaUpload(): void {
        $this->load->language('blog/blog');

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_media_method')]);
            return;
        }

        if (!$this->user->hasPermission('modify', 'blog/blog')) {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_permission')]);
            return;
        }

        if (!$this->csrfValid()) {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_csrf')]);
            return;
        }

        $this->load->model('blog/media');
        $target = $this->model_blog_media->getUploadTarget($this->postScalar('folder'));

        if ($target === '') {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_media_folder_not_found')]);
            return;
        }

        $result = $this->mediaUploader()->store((array)($this->request->files['file'] ?? []), $target);

        if (!$result['ok']) {
            $key = match ((string)$result['error']) {
                'size' => 'error_media_upload_size',
                'type' => 'error_media_upload_type',
                'move' => 'error_media_upload_move',
                'no_file' => 'error_media_upload_no_file',
                default => 'error_media_upload',
            };

            $message = $key === 'error_media_upload_size'
                ? sprintf($this->language->get($key), '2 MB')
                : $this->language->get($key);

            $this->mediaJson(['success' => false, 'error' => $message]);
            return;
        }

        $this->mediaJson([
            'success' => true,
            'path' => (string)$result['path'],
            'url' => $this->catalogAssetUrl() . ltrim((string)$result['path'], '/'),
            'message' => $this->language->get('text_media_upload_success'),
        ]);
    }

    /** Lista arquivos de áudio locais validados; upload tem rota POST separada e não há exclusão. */
    public function audioList(): void {
        $this->load->language('blog/blog');
        if ($this->request->server['REQUEST_METHOD'] !== 'GET') { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_media_method')]); return; }
        if (!$this->user->hasPermission('modify', 'blog/blog')) { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_permission')]); return; }
        $this->load->model('blog/media');
        $result = $this->model_blog_media->getAudioPage($this->queryScalar('search'), max(1, (int)$this->queryScalar('page')));
        if (!$result['ok']) { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_audio_generic')]); return; }
        $this->mediaJson(['success' => true, 'files' => $result['files'], 'total' => $result['total'], 'page' => $result['page'], 'pages' => $result['pages']]);
    }

    /** Upload de áudio local com allowlist estrita, ACL do Blog e CSRF; sem exclusão. */
    public function audioUpload(): void {
        $this->uploadBlogMedia('audio');
    }

    /** Lista vídeos locais validados da raiz dedicada do Blog. */
    public function videoList(): void {
        $this->load->language('blog/blog');
        if ($this->request->server['REQUEST_METHOD'] !== 'GET') { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_media_method')]); return; }
        if (!$this->user->hasPermission('modify', 'blog/blog')) { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_permission')]); return; }
        $this->load->model('blog/media');
        $result = $this->model_blog_media->getVideoPage($this->queryScalar('search'), max(1, (int)$this->queryScalar('page')));
        if (!$result['ok']) { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_video_generic')]); return; }
        $this->mediaJson(['success' => true, 'files' => $result['files'], 'total' => $result['total'], 'page' => $result['page'], 'pages' => $result['pages']]);
    }

    /** Upload de vídeo local com allowlist estrita, ACL do Blog e CSRF; sem exclusão. */
    public function videoUpload(): void {
        $this->uploadBlogMedia('video');
    }

    private function uploadBlogMedia(string $kind): void {
        $this->load->language('blog/blog');
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_media_method')]); return; }
        if (!$this->user->hasPermission('modify', 'blog/blog')) { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_permission')]); return; }
        if ($this->requestExceedsPostMaxSize()) {
            $limit = $kind === 'audio' ? '20 MiB' : '100 MiB';
            $this->mediaJson(['success' => false, 'error' => sprintf($this->language->get('error_audio_video_upload_size'), $limit)]); return;
        }
        if (!$this->csrfValid()) { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_csrf')]); return; }
        $this->load->model('blog/media');
        $result = $this->model_blog_media->uploadLocalBlogMedia((array)($this->request->files['file'] ?? []), $kind);
        if (!$result['ok']) {
            $key = match ((string)$result['error']) {
                'size' => 'error_audio_video_upload_size',
                'type' => $kind === 'audio' ? 'error_audio_upload_type' : 'error_video_upload_type',
                'no_file' => 'error_media_upload_no_file',
                'root' => 'error_audio_video_root',
                default => 'error_audio_video_upload',
            };
            $limit = $kind === 'audio' ? '20 MiB' : '100 MiB';
            $message = $key === 'error_audio_video_upload_size' ? sprintf($this->language->get($key), $limit) : $this->language->get($key);
            $this->mediaJson(['success' => false, 'error' => $message]); return;
        }
        $this->mediaJson(['success' => true, 'path' => (string)$result['path'], 'name' => (string)$result['name'], 'size' => (int)$result['size']]);
    }

    private function requestExceedsPostMaxSize(): bool {
        $raw = trim((string)ini_get('post_max_size'));
        if ($raw === '' || $raw === '0') return false;
        $unit = strtolower(substr($raw, -1)); $number = (float)$raw;
        $multiplier = match ($unit) { 'k' => 1024, 'm' => 1048576, 'g' => 1073741824, default => 1 };
        $limit = (int)($number * $multiplier);
        $length = (int)($this->request->server['CONTENT_LENGTH'] ?? 0);
        return $limit > 0 && $length > $limit;
    }

    /** Lista documentos da raiz dedicada de downloads; acesso protegido por ACL. */
    public function downloadList(): void {
        $this->load->language('blog/blog');
        if ($this->request->server['REQUEST_METHOD'] !== 'GET') { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_media_method')]); return; }
        if (!$this->user->hasPermission('modify', 'blog/blog')) { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_permission')]); return; }
        $this->load->model('blog/media');
        $result = $this->model_blog_media->getDownloadPage($this->queryScalar('search'), max(1, (int)$this->queryScalar('page')));
        if (!$result['ok']) { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_download_generic')]); return; }
        foreach ($result['files'] as &$file) $file['url'] = $this->catalogAssetUrl() . ltrim((string)$file['path'], '/');
        unset($file);
        $this->mediaJson(['success' => true, 'files' => $result['files'], 'total' => $result['total'], 'page' => $result['page'], 'pages' => $result['pages']]);
    }

    /** Upload de documento via allowlist dedicada, ACL modify e CSRF; não há exclusão. */
    public function downloadUpload(): void {
        $this->load->language('blog/blog');
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_media_method')]); return; }
        if (!$this->user->hasPermission('modify', 'blog/blog')) { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_permission')]); return; }
        if (!$this->csrfValid()) { $this->mediaJson(['success' => false, 'error' => $this->language->get('error_csrf')]); return; }
        $this->load->model('blog/media');
        $result = $this->model_blog_media->uploadDownload((array)($this->request->files['file'] ?? []));
        if (!$result['ok']) {
            $key = match ((string)$result['error']) { 'no_file' => 'error_download_upload_no_file', 'size' => 'error_download_upload_size', 'type' => 'error_download_upload_type', 'move' => 'error_download_upload_move', default => 'error_download_generic' };
            $message = $key === 'error_download_upload_size' ? sprintf($this->language->get($key), '2 MiB') : $this->language->get($key);
            $this->mediaJson(['success' => false, 'error' => $message]); return;
        }
        $this->mediaJson(['success' => true, 'path' => (string)$result['path'], 'name' => (string)$result['name'], 'url' => $this->catalogAssetUrl() . ltrim((string)$result['path'], '/')]);
    }

    /** Cria uma subpasta no filesystem da biblioteca, com ACL + CSRF. */
    public function mediaFolder(): void {
        $this->load->language('blog/blog');

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_media_method')]);
            return;
        }

        if (!$this->user->hasPermission('modify', 'blog/blog')) {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_permission')]);
            return;
        }

        if (!$this->csrfValid()) {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_csrf')]);
            return;
        }

        $this->load->model('blog/media');
        $result = $this->model_blog_media->createFolder($this->postScalar('folder'), $this->postScalar('name'));

        if ($result !== 'ok') {
            $this->mediaJson(['success' => false, 'error' => $this->mediaError($result)]);
            return;
        }

        $this->mediaJson(['success' => true, 'message' => $this->language->get('text_media_folder_created')]);
    }

    /** Exclui somente imagens não referenciadas ou pastas vazias (ACL + CSRF). */
    public function mediaDelete(): void {
        $this->load->language('blog/blog');

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_media_method')]);
            return;
        }

        if (!$this->user->hasPermission('modify', 'blog/blog')) {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_permission')]);
            return;
        }

        if (!$this->csrfValid()) {
            $this->mediaJson(['success' => false, 'error' => $this->language->get('error_csrf')]);
            return;
        }

        $this->load->model('blog/media');
        $result = $this->model_blog_media->deleteMedia($this->postScalar('type'), $this->postScalar('path'));

        if ($result !== 'ok') {
            $this->mediaJson(['success' => false, 'error' => $this->mediaError($result)]);
            return;
        }

        $message = $this->postScalar('type') === 'folder'
            ? $this->language->get('text_media_folder_deleted')
            : $this->language->get('text_media_file_deleted');

        $this->mediaJson(['success' => true, 'message' => $message]);
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
            'button_save', 'button_cancel', 'text_catalog_preview_button', 'text_catalog_preview_error',
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
            'text_audio_library', 'text_audio_empty', 'text_audio_hint', 'button_audio_select', 'error_audio_generic',
            'error_download_upload_no_file', 'error_download_upload_size', 'error_download_upload_type', 'error_download_upload_move', 'error_download_generic',
            'entry_media_search', 'entry_media_folder_name', 'entry_media_external_url',
            'button_media_library', 'button_media_upload', 'button_media_create_folder',
            'button_media_use_url', 'button_media_select', 'button_media_delete',
            'button_media_back', 'button_media_previous', 'button_media_next', 'button_media_close', 'error_edit_conflict', 'button_reload_current', 'text_revision_compare', 'text_revision_version', 'text_revision_current', 'text_revision_body', 'text_revision_meta', 'text_revision_error', 'text_revision_diff', 'text_revision_unchanged', 'text_revision_diff_limit',
            'text_catalog_preview_notice', 'text_published_on', 'button_back', 'text_content_review',
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
            'text_block_pattern_cover_hero', 'text_block_pattern_hint', 'text_block_saved_patterns', 'text_block_save_pattern', 'text_block_rename_pattern', 'text_block_delete_pattern', 'text_block_pattern_name_prompt', 'text_block_pattern_rename_prompt', 'text_block_pattern_delete_confirm', 'text_block_pattern_saved', 'text_block_pattern_renamed', 'text_block_pattern_deleted', 'text_block_pattern_error', 'text_block_pattern_incompatible', 'text_block_pattern_invalid', 'text_block_pattern_custom_js_rejected', 'text_block_draft_recovery_prompt', 'text_block_draft_recovery_hint', 'text_block_export_document', 'text_block_export_success', 'text_block_export_error', 'text_block_import_replace_confirm', 'text_block_history_button', 'text_block_history_title', 'text_block_history_empty', 'text_block_history_restore', 'text_block_history_restore_confirm', 'text_block_history_restored', 'text_block_find_replace', 'text_block_find_label', 'text_block_replace_label', 'text_block_find_next', 'text_block_find_previous', 'text_block_replace_one', 'text_block_replace_all', 'text_block_find_case_sensitive', 'text_block_find_cross_run_hint', 'text_block_find_none', 'text_block_find_count', 'text_block_replace_done', 'text_block_replace_limit', 'text_block_find_empty', 'text_block_save_state_saved', 'text_block_save_state_unsaved', 'text_block_save_state_saving',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['block_pattern_config'] = [
            'listUrl' => $this->url->link('cms/block_pattern.listBlog'),
            'getUrl' => $this->url->link('cms/block_pattern.getBlog'),
            'saveUrl' => $this->url->link('cms/block_pattern.saveBlog'),
            'renameUrl' => $this->url->link('cms/block_pattern.renameBlog'),
            'deleteUrl' => $this->url->link('cms/block_pattern.deleteBlog'),
            'csrfToken' => \Reamur\System\Library\Security\Csrf::token($this->session),
            'draftKey' => 'reamur:bdre:draft:v1:u' . (int)$this->user->getUserId() . ':blog:' . (int)($post['post_id'] ?? 0),
            'canRead' => $this->user->hasPermission('access', 'cms/block_pattern') && $this->user->hasPermission('modify', 'blog/blog'),
            'canModify' => $this->user->hasPermission('modify', 'cms/block_pattern') && $this->user->hasPermission('modify', 'blog/blog'),
        ];

        $data['block_editor_strings'] = [
            'bold' => $data['text_inline_bold'], 'italic' => $data['text_inline_italic'],
            'underline' => $data['text_inline_underline'], 'strike' => $data['text_inline_strike'], 'code' => $data['text_inline_code'],
            'link' => $data['text_inline_link'], 'linkPlaceholder' => $data['text_inline_link_placeholder'],
            'applyLink' => $data['text_inline_apply_link'], 'removeLink' => $data['text_inline_remove_link'],
            'invalidLink' => $data['text_inline_invalid_link'], 'toolbar' => $data['text_inline_toolbar'],
            'textLabel' => $data['text_inline_text_label'], 'placeholder' => $data['text_inline_placeholder'],
            'editorLabel' => $data['text_inline_editor_label'],
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
            'audio' => ['source' => $data['text_block_audio_source'], 'choose' => $data['text_block_audio_choose'], 'catalogBase' => $this->catalogAssetUrl()],
            'videoFile' => ['source' => $data['text_block_video_file_source'], 'choose' => $data['text_block_video_file_choose'], 'catalogBase' => $this->catalogAssetUrl()],
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
            'findReplaceButton' => $data['text_block_find_replace'], 'findLabel' => $data['text_block_find_label'], 'replaceLabel' => $data['text_block_replace_label'],
            'findNext' => $data['text_block_find_next'], 'findPrevious' => $data['text_block_find_previous'], 'replaceOne' => $data['text_block_replace_one'], 'replaceAll' => $data['text_block_replace_all'],
            'findCaseSensitive' => $data['text_block_find_case_sensitive'], 'findCrossRunHint' => $data['text_block_find_cross_run_hint'], 'findNoMatches' => $data['text_block_find_none'],
            'findMatchCount' => $data['text_block_find_count'], 'replaceDone' => $data['text_block_replace_done'], 'replaceLimit' => $data['text_block_replace_limit'], 'findEmpty' => $data['text_block_find_empty'],
        ];

        // CKEditor 4 (assets locais, ADR 0046): init fica no admin.js (textarea[data-rte])
        $this->document->addScript($this->config->get('site_url') . 'view/js/ckeditor/ckeditor.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);
        $this->document->addScript($this->config->get('site_url') . 'view/js/blog-media.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);
        $this->document->addScript($this->config->get('site_url') . 'view/js/bdre-concurrency.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        $data['seo_base'] = $this->catalogUrl();
        $data['catalog_base'] = $this->catalogAssetUrl();
        $data['can_modify'] = $this->user->hasPermission('modify', 'blog/blog');
        $data['block_editor_available'] = $data['can_modify'] && $this->model_blog_blog->blogBlockContentTableExists();
        $data['preview_url'] = $data['block_editor_available'] ? $this->url->link('blog/blog.preview') : '';
        if ($data['block_editor_available']) {
            $assetVersion = \Reamur\Admin\Controller\Common\Header::ASSET_V . '-threejs-9';
            $assetBase = rtrim((string)$this->config->get('site_url'), '/') . '/view/';
            $catalogAssetBase = $this->catalogAssetUrl();
            $catalogViewBase = $catalogAssetBase . 'view/';
            $this->document->addStyle($assetBase . 'css/blog-block-editor.css?v=' . $assetVersion);
            $this->document->addStyle($catalogViewBase . 'css/threejs-thumb.css?v=' . $assetVersion);
            $this->document->addScript($catalogAssetBase . $this->three_asset_manifest->publicBasePath() . 'threejs-thumb.js?v=' . $assetVersion);
            $this->document->addScript($assetBase . 'js/blog-block-editor.js?v=' . $assetVersion);
            $this->document->addScript($assetBase . 'js/blog-block-editor-init.js?v=' . $assetVersion);
            $this->document->addScript($assetBase . 'js/blog-file-media.js?v=' . $assetVersion);
            $this->document->addScript($assetBase . 'js/blog-audio-media.js?v=' . $assetVersion);
            $this->document->addScript($assetBase . 'js/blog-catalog-preview.js?v=' . $assetVersion);
        }
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['media_config'] = [
            'listUrl' => $this->url->link('blog/blog.mediaList'),
            'downloadListUrl' => $this->url->link('blog/blog.downloadList'),
            'audioListUrl' => $this->url->link('blog/blog.audioList'),
            'audioUploadUrl' => $this->url->link('blog/blog.audioUpload'),
            'videoListUrl' => $this->url->link('blog/blog.videoList'),
            'videoUploadUrl' => $this->url->link('blog/blog.videoUpload'),
            'downloadUploadUrl' => $this->url->link('blog/blog.downloadUpload'),
            'uploadUrl' => $this->url->link('blog/blog.mediaUpload'),
            'folderUrl' => $this->url->link('blog/blog.mediaFolder'),
            'deleteUrl' => $this->url->link('blog/blog.mediaDelete'),
            'csrfToken' => $data['csrf_token'],
            'catalogBase' => $this->catalogAssetUrl(),
            'strings' => [
                'loading' => $this->language->get('text_media_loading'),
                'empty' => $this->language->get('text_media_empty'),
                'noMatches' => $this->language->get('text_media_no_matches'),
                'page' => $this->language->get('text_media_page'),
                'confirmFileDelete' => $this->language->get('text_media_confirm_delete_file'),
                'confirmFolderDelete' => $this->language->get('text_media_confirm_delete_folder'),
                'genericError' => $this->language->get('error_media_generic'),
                'invalidUrl' => $this->language->get('error_media_url'),
                'chooseFile' => $this->language->get('error_media_upload_no_file'),
                'root' => $this->language->get('text_media_library'),
                'select' => $this->language->get('button_media_select'),
                'delete' => $this->language->get('button_media_delete'),
                'inUse' => $this->language->get('text_media_in_use'),
                'uploadSuccess' => $this->language->get('text_media_upload_success'),
                'downloadEmpty' => $this->language->get('text_download_empty'),
                'downloadUploadHint' => $this->language->get('text_download_hint'),
                'downloadChooseFile' => $this->language->get('error_download_upload_no_file'),
                'downloadUploadSuccess' => $this->language->get('text_download_upload'),
                'downloadLimit' => $this->language->get('text_download_limit'),
                'downloadUploadError' => $this->language->get('error_download_generic'),
                'selectDownload' => $this->language->get('button_download_select'),
                'audioEmpty' => $this->language->get('text_audio_empty'),
                'audioError' => $this->language->get('error_audio_generic'),
                'selectAudio' => $this->language->get('button_audio_select'),
                'audioUploadHint' => $this->language->get('text_audio_upload_hint'),
                'audioDragHint' => $this->language->get('text_audio_drag_hint'),
                'videoLibrary' => $this->language->get('text_video_library'),
                'videoEmpty' => $this->language->get('text_video_empty'),
                'videoHint' => $this->language->get('text_video_hint'),
                'videoUploadHint' => $this->language->get('text_video_upload_hint'),
                'videoDragHint' => $this->language->get('text_video_drag_hint'),
                'selectVideo' => $this->language->get('button_video_select'),
                'audioUpload' => $this->language->get('button_audio_upload'),
                'videoUpload' => $this->language->get('button_video_upload'),
                'videoError' => $this->language->get('error_video_generic'),
                'audioUploadError' => $this->language->get('error_audio_video_upload'),
                'videoUploadError' => $this->language->get('error_audio_video_upload'),
            ],
        ];

        $data['error']      = '';
        $data['text_flash'] = '';

        $pid          = (int)($post['post_id'] ?? 0);
        $isPost       = $this->request->server['REQUEST_METHOD'] === 'POST';
        $posted       = $this->request->post;
        $descriptions = $pid ? $this->model_blog_blog->getPostDescriptions($pid) : [];
        $keywords     = $pid ? $this->model_blog_blog->getPostKeywords($pid) : [];

        $data['languages'] = [];
        $blockRows = ($data['block_editor_available'] && $pid > 0) ? $this->model_blog_blog->getPostBlockContents($pid) : [];
        $blockValidator = $data['block_editor_available'] ? new \Reamur\System\Library\Blog\BlockDocument() : null;
        $postedBlockModes = (array)($posted['blog_block_mode'] ?? []);
        $postedBlockJson = (array)($posted['blog_blocks'] ?? []);

        foreach ($this->model_blog_blog->getLanguages() as $language) {
            $language = (array)$language;
            $lid      = (int)$language['language_id'];
            $saved    = (array)($descriptions[$lid] ?? []);

            $fields = [];

            foreach (['title', 'description', 'meta_title', 'meta_description'] as $f) {
                $fields[$f] = (string)($posted['blog_description'][$lid][$f] ?? ($saved[$f] ?? ''));
            }

            $fields['keyword'] = (string)($posted['blog_description'][$lid]['keyword'] ?? ($keywords[$lid] ?? ''));
            $blockMode = 'legacy';
            $blockDocumentJson = '';
            $blockStatus = '';
            if ($data['block_editor_available'] && $blockValidator instanceof \Reamur\System\Library\Blog\BlockDocument) {
                $row = (array)($blockRows[$lid] ?? []);
                $postedMode = (string)($postedBlockModes[$lid] ?? '');
                $postedJson = $postedBlockJson[$lid] ?? null;
                if ($row !== []) {
                    $stored = $blockValidator->deserializeDocument((string)($row['content_json'] ?? ''));
                    $storedVersion = (int)($row['format_version'] ?? 0);
                    if ($stored['valid'] && $storedVersion === (int)$stored['document']['format_version']) {
                        $chosen = $stored['document'];
                        if ($isPost && $postedMode === 'blocks' && is_string($postedJson)) {
                            $submitted = $blockValidator->deserializeDocument($postedJson);
                            if ($submitted['valid']) $chosen = $submitted['document'];
                        }
                        $blockMode = 'blocks';
                        $blockDocumentJson = $blockValidator->serializeDocument($chosen);
                        $blockStatus = $this->language->get('text_block_saved');
                    } else {
                        $blockMode = 'blocked';
                        $blockStatus = $this->language->get('text_block_invalid');
                        $fields['description'] = (string)($saved['description'] ?? '');
                    }
                } elseif ($pid < 1) {
                    $emptyDocument = ['format_version' => 1, 'blocks' => []];
                    $chosen = $emptyDocument;
                    if ($isPost && $postedMode === 'blocks' && is_string($postedJson)) {
                        $submitted = $blockValidator->deserializeDocument($postedJson);
                        if ($submitted['valid']) $chosen = $submitted['document'];
                    }
                    $blockMode = 'blocks';
                    $blockDocumentJson = $blockValidator->serializeDocument($chosen);
                    $blockStatus = $this->language->get('text_block_conversion_note');
                } else {
                    $sourceHtml = (string)($saved['description'] ?? '');
                    $conversion = $blockValidator->convertLegacy($sourceHtml);
                    if ($conversion['status'] === 'converted' && (!$isPost || $postedMode === 'blocks')) {
                        $chosen = $conversion['document'];
                        if ($isPost && $postedMode === 'blocks' && is_string($postedJson)) {
                            $submitted = $blockValidator->deserializeDocument($postedJson);
                            if ($submitted['valid']) $chosen = $submitted['document'];
                        }
                        $blockMode = 'blocks';
                        $blockDocumentJson = $blockValidator->serializeDocument($chosen);
                        $blockStatus = $this->language->get('text_block_conversion_note');
                    } else {
                        $blockMode = 'legacy';
                        if ($conversion['status'] !== 'converted') $blockStatus = $this->language->get('text_block_review_required');
                    }
                }
            }

            $data['languages'][] = [
                'language_id' => $lid,
                'name'        => (string)$language['name'],
                'code'        => (string)$language['code'],
                'fields'      => $fields,
                'block_mode' => $blockMode,
                'block_document_json' => $blockDocumentJson,
                'block_status' => $blockStatus,
            ];
        }

        $data['post_id']        = $pid;
        $data['edit_fingerprint'] = $pid > 0 ? $this->model_blog_blog->getPostEditFingerprint($pid) : '';
        $data['revision_conflict'] = false;
        $data['post_revisions_url'] = $pid > 0 ? $this->url->link('blog/blog.revisions|' . $pid) : '';
        $data['post_revision_url'] = $pid > 0 ? $this->url->link('blog/blog.revision|' . $pid) : '';
        $data['image']          = (string)($posted['image'] ?? (string)($post['image'] ?? ''));
        $data['image_preview']  = $this->catalogImageUrl($data['image']);
        $data['date_published'] = (string)($posted['date_published'] ?? (string)($post['date_published'] ?? date('Y-m-d H:i:s')));
        $data['status']         = isset($posted['status']) ? '1' : ($isPost ? '0' : (string)(int)($post['status'] ?? 0));

        $data['cancel'] = $this->url->link('blog/blog');

        return $data;
    }

    /**
     * Validate block JSON in POST; preserve per-language legacy descriptions unless a valid explicit block save is submitted.
     * @return array{storage_available:bool,documents:array,snapshots:array,errors:array}
     */
    private function prepareBlockSubmission(int $post_id): array {
        $result = ['storage_available' => false, 'documents' => [], 'snapshots' => [], 'errors' => []];
        $storage = $this->model_blog_blog->blogBlockContentTableExists();
        $modes = (array)($this->request->post['blog_block_mode'] ?? []);
        $posted = (array)($this->request->post['blog_blocks'] ?? []);
        $readyFlags = (array)($this->request->post['blog_editor_ready'] ?? []);
        if (!$storage) {
            if (in_array('blocks', $modes, true) || $posted !== []) $result['errors'][] = $this->language->get('error_block_schema_unavailable');
            return $result;
        }
        $result['storage_available'] = true;
        $languages = [];
        foreach ($this->model_blog_blog->getLanguages() as $language) $languages[(int)((array)$language)['language_id']] = true;
        foreach (array_unique(array_merge(array_keys($modes), array_keys($posted))) as $language_id) {
            if (!isset($languages[(int)$language_id])) $result['errors'][] = $this->language->get('error_block_mode_invalid');
        }
        if ($result['errors']) return $result;

        $rows = $post_id > 0 ? $this->model_blog_blog->getPostBlockContents($post_id) : [];
        $savedDescriptions = $post_id > 0 ? $this->model_blog_blog->getPostDescriptions($post_id) : [];
        $validator = new \Reamur\System\Library\Blog\BlockDocument();
        $this->load->model('blog/media');
        $downloadRoot = rtrim(dirname(DIR_APPLICATION), '/\\\\') . '/catalog/view/download/blog';
        $audioRoot = rtrim(dirname(DIR_APPLICATION), '/\\\\') . '/catalog/view/audio/blog';
        $renderer = new \Reamur\System\Library\Blog\BlockRenderer($downloadRoot, $audioRoot);

        foreach (array_keys($languages) as $language_id) {
            $language_id = (int)$language_id;
            $mode = (string)($modes[$language_id] ?? 'legacy');
            $row = (array)($rows[$language_id] ?? []);
            $sourceHtml = (string)($savedDescriptions[$language_id]['description'] ?? '');

            if ($row !== []) {
                $stored = $validator->deserializeDocument((string)($row['content_json'] ?? ''));
                $storedVersion = (int)($row['format_version'] ?? 0);
                $storedValid = $stored['valid'] && $storedVersion === (int)$stored['document']['format_version'];
                if (!$storedValid) {
                    if ($mode !== 'blocked') $result['errors'][] = $this->language->get('error_block_mode_invalid');
                    else $result['snapshots'][$language_id] = $sourceHtml;
                    continue;
                }
                if ($mode !== 'blocks') {
                    $result['errors'][] = $this->language->get('error_block_mode_invalid');
                    continue;
                }
            } elseif ($mode === 'blocked') {
                $result['errors'][] = $this->language->get('error_block_mode_invalid');
                continue;
            } elseif ($mode !== 'blocks' && $mode !== 'legacy') {
                $result['errors'][] = $this->language->get('error_block_mode_invalid');
                continue;
            }

            if ($mode === 'legacy') continue;
            if (($readyFlags[$language_id] ?? '') !== '1') {
                $result['errors'][] = $this->language->get('error_block_editor_unavailable');
                continue;
            }
            if ($row === [] && $post_id > 0 && $sourceHtml !== '') {
                $conversion = $validator->convertLegacy($sourceHtml);
                if ($conversion['status'] !== 'converted') {
                    $result['errors'][] = $this->language->get('error_block_legacy_review');
                    continue;
                }
            }

            $json = $posted[$language_id] ?? null;
            if (!is_string($json)) {
                $result['errors'][] = $this->language->get('error_block_document_invalid');
                continue;
            }
            $decoded = $validator->deserializeDocument($json);
            if (!$decoded['valid'] || !$this->blockMediaAvailable((array)$decoded['document']['blocks'])) {
                $result['errors'][] = $this->language->get('error_block_document_invalid');
                continue;
            }
            $canonical = $validator->serializeDocument($decoded['document']);
            $rendered = $renderer->renderJson($canonical, (int)$decoded['document']['format_version'], $this->catalogUrl());
            if (!$rendered['valid']) {
                $result['errors'][] = $this->language->get('error_block_document_invalid');
                continue;
            }
            $result['documents'][$language_id] = $canonical;
            $result['snapshots'][$language_id] = (string)$rendered['html'];
        }

        $result['errors'] = array_values(array_unique($result['errors']));
        return $result;
    }

    /** Confirma recursivamente que referências core/file/core/audio apontam para assets permitidos existentes. */
    private function blockMediaAvailable(array $nodes): bool {
        foreach ($nodes as $node) {
            if (!is_array($node)) return false;
            if (($node['type'] ?? '') === 'core/file' && !$this->model_blog_media->downloadReferenceExists((string)($node['attributes']['src'] ?? ''))) return false;
            if (($node['type'] ?? '') === 'core/audio' && !$this->model_blog_media->audioReferenceExists((string)($node['attributes']['src'] ?? ''))) return false;
            if (isset($node['children']) && is_array($node['children']) && !$this->blockMediaAvailable($node['children'])) return false;
        }
        return true;
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

    /** Valida token em sessão em todos os POSTs mutáveis do Blog. */
    private function csrfValid(): bool {
        return \Reamur\System\Library\Security\Csrf::check(
            $this->session,
            $this->request->post['csrf_token'] ?? ''
        );
    }

    private function mediaUploader(): MediaUpload {
        return new MediaUpload(rtrim((string)dirname(DIR_APPLICATION), '/\\') . '/catalog/');
    }

    /** @param array<string, mixed> $payload */
    private function mediaJson(array $payload): void {
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput((string)json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        ));
    }

    private function mediaError(string $code): string {
        $key = match ($code) {
            'invalid_folder', 'invalid_path', 'invalid_type' => 'error_media_invalid_path',
            'folder_not_found' => 'error_media_folder_not_found',
            'not_found' => 'error_media_not_found',
            'folder_unreadable' => 'error_media_folder_unreadable',
            'invalid_name' => 'error_media_folder_name',
            'root_unavailable' => 'error_media_root',
            'create_failed' => 'error_media_folder_create',
            'exists' => 'error_media_folder_exists',
            'permission' => 'error_permission',
            'in_use' => 'error_media_file_in_use',
            'not_empty' => 'error_media_folder_not_empty',
            'delete_failed' => 'error_media_delete',
            default => 'error_media_generic',
        };

        return $this->language->get($key);
    }

    private function postScalar(string $key): string {
        $value = $this->request->post[$key] ?? '';

        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function queryScalar(string $key): string {
        $value = $this->request->get[$key] ?? '';

        return is_scalar($value) ? trim((string)$value) : '';
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

    /** Chrome compartilhado + estilo do módulo (F8-01, ADR 0045: par header/footer) */
    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        $data['header'] = $this->load->controller('common/header', $data);
        $data['footer'] = $this->load->controller('common/footer', $data);
    }

    /** Base pública canônica do site (admin /admin/ → raiz), usada no preview SEO. */
    private function catalogUrl(): string {
        $siteUrl = rtrim((string)$this->config->get('site_url', ''), '/') . '/';

        return str_ireplace('/admin/', '/', $siteUrl);
    }

    /** URL de imagem para preview/lista; paths legados com catálogo na raiz são normalizados. */
    private function catalogImageUrl(string $image): string {
        $image = trim($image);

        if ($image === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }

        $path = ltrim(str_replace(chr(92), '/', $image), '/');

        foreach (['dev/catalog/', 'catalog/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        return $this->catalogAssetUrl() . $path;
    }

    /** Base física pública do app catalog, usada em src de imagens do filesystem. */
    private function catalogAssetUrl(): string {
        $base = (string)$this->config->get('site_url', '');

        if (str_ends_with($base, 'admin/')) {
            return substr($base, 0, -strlen('admin/')) . 'catalog/';
        }

        if (str_ends_with($base, 'catalog/')) {
            return $base;
        }

        return rtrim($base, '/') . '/catalog/';
    }
}
