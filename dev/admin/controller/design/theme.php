<?php
/** Admin theme manager (F10-02); access and modify are separately ACL-gated. */
namespace Reamur\Admin\Controller\Design;
class Theme extends \Reamur\System\Engine\Controller {
    // F13-E: allowlist unica em ThemePackage::TEMPLATE_KEYS (sem duplicacao).
    public function index(): void {
        if (!$this->user->hasPermission('access','design/theme')) { $this->response->redirect($this->url->link('error/permission')); return; }
        $this->load->language('design/theme'); $this->load->model('design/theme');
        $data=[]; foreach(['heading_title','text_intro','text_empty','column_name','column_slug','column_status','column_modified','button_edit','button_add','button_save','button_activate','text_active','text_inactive'] as $k) $data[$k]=$this->language->get($k);
        $data['themes']=$this->model_design_theme->getThemes(); $data['csrf_token']=\Reamur\System\Library\Security\Csrf::token($this->session); $data['can_modify']=$this->user->hasPermission('modify','design/theme');
        $data['add']=$this->url->link('design/theme.edit');
        foreach($data['themes'] as &$theme) { $theme['edit']=$this->url->link('design/theme.edit|' . (int)$theme['theme_id']); $theme['activate']=$this->url->link('design/theme.activate|' . (int)$theme['theme_id']); } unset($theme);
        $this->chrome($data); $this->response->setOutput($this->load->view('design/theme_list',$data));
    }
    public function edit(string $theme_id='0'): void {
        if (!$this->user->hasPermission('access','design/theme')) { $this->response->redirect($this->url->link('error/permission')); return; }
        $id=(int)$theme_id; $this->load->language('design/theme'); $this->load->model('design/theme');
        $theme=$id ? $this->model_design_theme->getTheme($id) : [];
        if ($id && !$theme) { $this->response->redirect($this->url->link('design/theme')); return; }
        $config=json_decode((string)($theme['draft_config']??$theme['config']??'{}'),true); if (!is_array($config)) $config=[];
        $data=['heading_title'=>$this->language->get('heading_title'),'text_intro'=>$this->language->get('text_editor_scope'),'text_name'=>$this->language->get('text_name'),'text_slug'=>$this->language->get('text_slug'),'text_colors'=>$this->language->get('text_colors'),'text_primary'=>$this->language->get('text_primary'),'text_surface'=>$this->language->get('text_surface'),'text_scene'=>$this->language->get('text_scene'),'text_no_scene'=>$this->language->get('text_no_scene'),'button_save'=>$this->language->get('button_save'),'button_back'=>$this->language->get('button_back'),'error'=>'','can_modify'=>$this->user->hasPermission('modify','design/theme'),'csrf_token'=>\Reamur\System\Library\Security\Csrf::token($this->session),'action'=>$this->url->link('design/theme.edit'.($id?'|'.$id:'')),'cancel'=>$this->url->link('design/theme'),'name'=>(string)($theme['name']??''),'slug'=>(string)($theme['slug']??''),'primary'=>(string)($config['primary']??'#2563eb'),'surface'=>(string)($config['surface']??'#ffffff'),'scene'=>(string)($config['scene']??''),'templates'=>(array)($config['templates']??[])];
        // F12-01: condicoes declarativas + Three.js por tema.
        $engine=$this->theme_condition_engine;
        $spec=$engine->spec();
        $conditions=(isset($config['conditions'])&&is_array($config['conditions']))?$config['conditions']:['version'=>1,'rules'=>[]];
        $data['conditions_json']=json_encode($conditions,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '{"version":1,"rules":[]}';
        $data['three']=$engine->normalizeThree(is_array($config['three']??null)?$config['three']:[]) ?? ['enabled'=>false,'scene'=>'','slot'=>'hero','count'=>400,'color'=>'#8a4fff','speed'=>0.05];
        $data['three_scenes']=$spec['scenes'];
        $data['three_slots']=$spec['slots'];
        // F13-E: pacote versionavel + child-theme.
        $parents=[];
        foreach($this->model_design_theme->getThemes() as $candidate) {
            $candidateSlug=(string)($candidate['slug']??'');
            if ($candidateSlug==='' || $candidateSlug===(string)($theme['slug']??'')) continue;
            $parents[]=['slug'=>$candidateSlug,'name'=>(string)($candidate['name']??$candidateSlug)];
        }
        $data['theme_parents']=$parents;
        $data['parent_theme']=(string)($config['parent']??'');
        $data['package_format']=\Reamur\System\Library\Theme\ThemePackage::FORMAT;
        $data['package_version']=\Reamur\System\Library\Theme\ThemePackage::VERSION;
        $data['export_url']=$this->url->link('design/theme.export'.($id?'|'.$id:''));
        $data['export_published_url']=$this->url->link('design/theme.export'.($id?'|'.$id:''),'source=published');
        $data['import_url']=$this->url->link('design/theme.import'.($id?'|'.$id:''));
        foreach(['text_package','text_package_help','text_package_export','text_package_export_published','text_package_import','text_package_import_help','text_package_file','text_package_paste','text_package_button','text_package_version','text_package_parent','text_package_parent_help','text_package_parent_none','text_import_ok','text_import_error','error_theme_parent'] as $k) $data[$k]=$this->language->get($k);
        $data['notice']='';
        $codes=['package_size_invalid','json_invalid','format_invalid','version_invalid','identity_invalid','config_invalid','checksum_invalid','config_key_invalid','color_invalid','scene_invalid','parent_invalid','templates_invalid','template_source_invalid','conditions_invalid','three_invalid','tokens_invalid','sections_invalid','components_invalid','schedule_invalid','media_invalid','sites_invalid','save_failed','forbidden','csrf'];
        $impErr=(string)($this->request->get['import_error']??'');
        if ((string)($this->request->get['import_ok']??'')==='1') $data['notice']=$this->language->get('text_import_ok');
        elseif ($impErr!=='') $data['error']=$this->language->get('text_import_error').' ('.(in_array($impErr,$codes,true)?$impErr:'unknown').')';
        $this->document->addScript($this->config->get('site_url') . 'view/js/theme-package.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        // F13-D: presets allowlistados + miniatura SVG deterministica.
        $presets=[];
        foreach($this->three_scene_preset->all() as $p) {
            $p['name']=$this->language->get($p['label']);
            $p['help_text']=$this->language->get($p['help']);
            $p['thumb']=$this->three_scene_preset->dataUri($p);
            $p['enabled_str']=$p['enabled']?'1':'0';
            $p['speed_str']=number_format($p['speed'],2,'.','');
            $presets[]=$p;
        }
        $data['scene_presets']=$presets;
        $data['scene_preset']=$this->three_scene_preset->matchingPreset($data['three']);
        $thumbBase=$this->url->link('design/theme.sceneThumb');
        $thumbQuery=http_build_query([
            'scene'=>(string)($data['three']['scene']??''),'slot'=>(string)($data['three']['slot']??'hero'),
            'count'=>(string)($data['three']['count']??400),'color'=>(string)($data['three']['color']??'#8a4fff'),
            'speed'=>(string)($data['three']['speed']??0.05),'enabled'=>!empty($data['three']['enabled'])?'1':'0',
        ],'','&',PHP_QUERY_RFC3986);
        $data['scene_thumb_base']=$thumbBase;
        $data['scene_thumb_live']=$thumbBase.(str_contains($thumbBase,'?')?'&':'?').$thumbQuery;
        foreach(['text_scene_editor','text_scene_editor_help','text_scene_thumb','text_scene_thumb_help','text_scene_preset','text_scene_preset_help','text_scene_preset_custom','text_scene_preset_active','text_scene_preset_apply','error_scene_preset'] as $k) $data[$k]=$this->language->get($k);

        $data['condition_fields']=$spec['fields'];
        $data['condition_query_keys']=$spec['query_keys'];
        $data['condition_operators']=$spec['operators'];
        $data['condition_max_rules']=$spec['max_rules'];
        foreach(['text_conditions','text_conditions_help','text_three','text_three_enabled','text_three_scene','text_three_count','text_three_color','text_three_speed','text_three_slot','text_three_help','text_condition_fields','text_condition_operators','text_condition_example','error_conditions','error_three'] as $k) $data[$k]=$this->language->get($k);
        // F13-B: design tokens allowlistados + contraste WCAG medido.
        $this->applyTokenFormData($data,(isset($config['tokens'])&&is_array($config['tokens']))?$config['tokens']:[]);
        foreach(['text_tokens','text_tokens_help','text_token_group_typography','text_token_group_shape','text_token_group_light','text_token_group_dark','text_token_contrast','error_tokens'] as $k) $data[$k]=$this->language->get($k);
        // F13-A: builder visual + simulador. O textarea JSON continua sendo a
        // fonte submetida (progressive enhancement); sem JS nada muda.
        $jsonFlags=JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES;
        $data['condition_spec_json']=json_encode($spec,$jsonFlags) ?: '{}';
        $data['simulate_url']=$this->url->link('design/theme.simulate');
        $data['builder_routes_json']=json_encode(['common/home','blog/blog_list','blog/blog_info','catalog/project_list','catalog/project_info','tool/tool_list','tool/tool_info','cms/page_info','common/contact','checkout/quote','error/not_found'],$jsonFlags) ?: '[]';
        $data['builder_languages_json']=json_encode(['en-us','pt-br','es-eu'],$jsonFlags) ?: '[]';
        $data['builder_devices_json']=json_encode(['desktop','tablet','mobile'],$jsonFlags) ?: '[]';
        $labelMap=[
            'tokens'=>'text_builder_tokens',
            'no_value'=>'text_builder_no_value',
            'add_condition'=>'text_builder_add_condition',
            'add_group'=>'text_builder_add_group',
            'remove'=>'text_builder_remove',
            'list_hint'=>'text_builder_list_hint',
            'group_all'=>'text_builder_group_all',
            'group_any'=>'text_builder_group_any',
            'group_not'=>'text_builder_group_not',
            'primary'=>'text_builder_primary',
            'surface'=>'text_builder_surface',
            'scene'=>'text_builder_scene',
            'no_scene'=>'text_builder_no_scene',
            'flags'=>'text_builder_flags',
            'classes'=>'text_builder_classes',
            'vars'=>'text_builder_vars',
            'add_var'=>'text_builder_add_var',
            'three'=>'text_builder_three',
            'rule_id'=>'text_builder_rule_id',
            'drag'=>'text_builder_drag',
            'up'=>'text_builder_up',
            'down'=>'text_builder_down',
            'when'=>'text_builder_when',
            'then'=>'text_builder_then',
            'valid'=>'text_builder_valid',
            'invalid'=>'text_builder_invalid',
            'matched'=>'text_builder_matched',
            'simulating'=>'text_builder_simulating',
            'offline'=>'text_builder_offline',
            'route'=>'text_builder_route',
            'language'=>'text_builder_language',
            'device'=>'text_builder_device',
            'variant'=>'text_builder_variant',
            'hour'=>'text_builder_hour',
            'logged'=>'text_builder_logged',
            'query'=>'text_builder_query',
            'json_invalid'=>'text_builder_json_invalid'
        ];
        $builderLabels=[];
        foreach($labelMap as $jsKey=>$langKey) { $builderLabels[$jsKey]=$this->language->get($langKey); $data[$langKey]=$builderLabels[$jsKey]; }
        $data['builder_labels_json']=json_encode($builderLabels,$jsonFlags) ?: '{}';
        $builderAssetV=\Reamur\Admin\Controller\Common\Header::ASSET_V . '-theme-builder-a';
        $this->document->addStyle($this->config->get('site_url') . 'view/css/theme-condition-builder.css?v=' . $builderAssetV);
        $this->document->addScript($this->config->get('site_url') . 'view/js/theme-condition-builder.js?v=' . $builderAssetV);
        // F13-D: editor de cena (presets + miniatura ao vivo).
        $this->document->addScript($this->config->get('site_url') . 'view/js/theme-scene-editor.js?v=' . $builderAssetV);
        // F13-F: section builder. Sem `sections` no config, o formulario
        // comeca com o layout padrao do CMS e avisa que o override nao existe.
        $sectionSelected=(array_key_exists('sections',$config)&&is_array($config['sections']))
            ?$config['sections']:$this->theme_section_registry->defaults();
        $data['section_rows']=$this->sectionRows($sectionSelected);
        $data['sections_order']=implode(',',array_column($data['section_rows'],'id'));
        $data['section_active_count']=count(array_filter($data['section_rows'],static fn(array $r):bool=>(bool)$r['enabled']));
        $data['sections_defined']=array_key_exists('sections',$config);
        foreach(['text_sections','text_sections_help','text_sections_active','text_section_up','text_section_down','text_section_partial','text_section_legacy','text_section_hint','error_sections','text_section_group_opening','text_section_group_content','text_section_group_proof','text_section_group_catalog','text_section_group_closing','text_section_kind_static','text_section_kind_dynamic','text_section_kind_editorial'] as $k) $data[$k]=$this->language->get($k);
        $this->document->addScript($this->config->get('site_url') . 'view/js/theme-section-builder.js?v=' . $builderAssetV);
        // F13-G: biblioteca de componentes (galeria, opcoes e snippets Twig).
        $componentRaw=(array_key_exists('components',$config)&&is_array($config['components']))?$config['components']:null;
        $data['component_rows']=$this->componentRows($componentRaw);
        $data['component_snippets_json']=json_encode($this->theme_component_registry->snippets(),$jsonFlags) ?: '{}';
        $data['component_thumb_base']=$this->url->link('design/theme.componentThumb');
        foreach(['text_components','text_components_help','text_component_target','text_component_variant','text_component_tone','text_component_insert','text_component_inserted','text_component_enabled','text_component_none','error_components','text_component_group_action','text_component_group_display','text_component_group_feedback'] as $k) $data[$k]=$this->language->get($k);
        $this->document->addScript($this->config->get('site_url') . 'view/js/theme-component-library.js?v=' . $builderAssetV);
        if ($this->request->server['REQUEST_METHOD']==='POST') {
            $post=$this->request->post;
            if (!$data['can_modify']) { $data['error']=$this->language->get('error_permission'); }
            elseif (!\Reamur\System\Library\Security\Csrf::check($this->session,(string)($post['csrf_token']??''))) { $data['error']=$this->language->get('error_csrf'); }
            else {
                // F13-C: a previa e o save usam EXATAMENTE o mesmo construtor
                // de config, com as mesmas validacoes (sem divergencia).
                $built=$this->buildDraftConfig($post);
                if ($built['error']!=='') $data['error']=$built['error'];
                else {
                    $saved=$this->model_design_theme->save($id,['name'=>$built['name'],'slug'=>$built['slug'],'config'=>$built['config']],(int)$this->user->getUserId());
                    if ($saved) { $this->response->redirect($this->url->link('design/theme')); return; }
                    $data['error']=$this->language->get('error_validation');
                }
            }
            foreach(['name','slug','primary','surface','scene'] as $k) $data[$k]=(string)($post[$k]??$data[$k]);
            $data['conditions_json']=(string)($post['conditions']??$data['conditions_json']);
            $this->applyTokenFormData($data,(array)($post['tokens']??[]));
            $data['three']=[
                // F13-D
                'enabled'=>(string)($post['three_enabled']??'0')==='1',
                'scene'=>(string)($post['three_scene']??''),
                'slot'=>(string)($post['three_slot']??'hero'),
                'count'=>(string)($post['three_count']??'400'),
                'color'=>(string)($post['three_color']??'#8a4fff'),
                'speed'=>(string)($post['three_speed']??'0.05'),
            ];
            $data['scene_preset']=$this->three_scene_preset->matchingPreset($data['three']);
            $data['parent_theme']=(string)($post['parent_theme']??$data['parent_theme']);
            // F13-F: reflete a ordem/habilitacao enviadas mesmo em caso de erro.
            $formOrder=$this->theme_section_registry->formOrder((string)($post['sections_order']??$data['sections_order']));
            $formEnabled=(array)($post['sections']??[]);
            $formSelected=[];
            foreach($formOrder as $sid) if (in_array($sid,$formEnabled,true)) $formSelected[]=$sid;
            $data['section_rows']=$this->sectionRows($formSelected);
            $data['sections_order']=implode(',',array_column($data['section_rows'],'id'));
            $data['section_active_count']=count($formSelected);
            $data['sections_defined']=true;
            // F13-G: reflete os componentes enviados mesmo em caso de erro.
            $data['component_rows']=$this->componentRows((array)($post['components']??[]));
        }
        // F13-H: agendamento/rollout declarativo do rascunho (zero escrita).
        $scheduleRaw=(isset($config['schedule'])&&is_array($config['schedule']))?$config['schedule']:null;
        $scheduleNorm=$this->theme_schedule->normalize($scheduleRaw) ?? $this->theme_schedule->normalize(null);
        $data['schedule']=$scheduleNorm;
        $data['schedule_scopes']=$this->theme_schedule->spec()['scopes'];
        $data['schedule_probe_now']=$this->theme_schedule->now();
        foreach(['text_schedule','text_schedule_help','text_schedule_enabled','text_schedule_from',
                 'text_schedule_until','text_schedule_percent','text_schedule_scope','text_schedule_scope_all',
                 'text_schedule_scope_logged_in','text_schedule_scope_anonymous','text_schedule_note',
                 'text_schedule_probe','text_schedule_probe_now','text_schedule_probe_visitor',
                 'text_schedule_probe_logged','text_schedule_probe_button','error_schedule'] as $k) $data[$k]=$this->language->get($k);
        $this->document->addScript($this->config->get('site_url') . 'view/js/theme-schedule.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V . '-theme-schedule-h');
        // F13-I: midia vetorial declarativa + modelos de pagina.
        $data['media_rows']=$this->mediaRows((isset($config['media'])&&is_array($config['media']))?$config['media']:[]);
        $data['media_snippets_json']=json_encode($this->theme_media->snippets(),$jsonFlags) ?: '{}';
        $data['media_thumb_base']=$this->url->link('design/theme.mediaThumb');
        $data['page_models']=$this->theme_media->models();
        foreach($data['page_models'] as &$pm){
            $pm['label']=$this->language->get($pm['label_key']);
            $pm['help']=$this->language->get($pm['help_key']);
        } unset($pm);
        $data['page_models_json']=json_encode($this->theme_media->modelPayload(),$jsonFlags) ?: '{}';
        foreach(['text_media','text_media_help','text_media_target','text_media_variant','text_media_tone',
                 'text_media_insert','text_media_inserted','text_media_enabled','text_media_none','error_media',
                 'text_models','text_models_help','text_models_apply','text_models_confirm','text_models_applied',
                 'text_models_sections','text_models_components','text_models_media','error_models'] as $k) $data[$k]=$this->language->get($k);
        $this->document->addScript($this->config->get('site_url') . 'view/js/theme-media.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V . '-theme-media-i');
        // F13-J: mapa declarativo de sites/hosts -> temas por slug.
        $sitesNorm=$this->theme_sites->normalize(is_array($config['sites']??null)?$config['sites']:[]) ?? [];
        $siteActive=$this->theme_sites->match($sitesNorm,(string)($this->request->server['HTTP_HOST']??''));
        $data['site_rows']=$this->theme_sites->view($sitesNorm,$siteActive);
        foreach($data['site_rows'] as &$sr){
            $sr['label']=$this->language->get($sr['label_key']);
            $sr['help']=$this->language->get($sr['help_key']);
        } unset($sr);
        $data['site_languages']=$this->theme_sites->languages();
        // F13-K: mostra no card o idioma que a frente K aplicara no Catalog.
        $data['site_active_id']=(string)($siteActive['id']??'');
        $data['site_active_language']=(string)($siteActive['language']??'');
        $data['site_theme_options']=[];
        foreach($this->model_design_theme->getThemes() as $candidate){
            $s=(string)($candidate['slug']??'');
            if ($s!=='') $data['site_theme_options'][]=['slug'=>$s,'name'=>(string)($candidate['name']??$s)];
        }
        $data['sites_json']=json_encode($sitesNorm,$jsonFlags) ?: '{}';
        // NUNCA refletir HTTP_HOST no HTML: a sonda comeca vazia e rodando no JS.
        $data['site_probe_host']='';
        foreach(['text_sites','text_sites_help','text_sites_host','text_sites_slug','text_sites_language',
                 'text_sites_enabled','text_sites_active','text_sites_default','text_sites_probe',
                 'text_sites_probe_help','text_sites_probe_button','text_sites_probe_unknown',
                 'text_sites_note','text_sites_empty','text_sites_fallback','error_sites'] as $k) $data[$k]=$this->language->get($k);
        $this->document->addScript($this->config->get('site_url') . 'view/js/theme-sites.js?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V . '-theme-sites-j');
        // F13-C: estado real de publicacao e dados do comparador.
        $publishedConfig=$id?$this->model_design_theme->getPublishedConfig($id):[];
        $draftNorm=json_encode(json_decode((string)($theme['draft_config']??$theme['config']??'{}'),true) ?: new \stdClass());
        $pubNorm=json_encode(json_decode((string)($theme['published_config']??'{}'),true) ?: new \stdClass());
        $data['theme_state']=((int)($theme['status']??0)===1 && $pubNorm!=='{}')?'published':'draft';
        $data['has_unpublished_changes']=($id>0 && $draftNorm!==$pubNorm);
        foreach(['text_compare','text_compare_help','text_compare_button','text_compare_note','text_compare_route','text_compare_language','text_compare_device','text_compare_variant','text_compare_template','text_state_published','text_state_draft','text_state_changed','text_state_clean'] as $k) $data[$k]=$this->language->get($k);
        $data['compare_url']=$this->url->link('design/theme.compare'.($id?'|'.$id:''));
        $data['preview_routes']=['common/home','blog/blog_list','blog/blog_info','catalog/project_list','catalog/project_info','tool/tool_list','tool/tool_info','cms/page_info','common/contact','checkout/quote','error/not_found'];
        $data['preview_languages']=['en-us','pt-br','es-eu'];
        $data['preview_devices']=['desktop','tablet','mobile'];
        $data['preview_variants']=['','a','b'];
        $focusPreview=(string)($this->request->get['focus_template']??'');
        $data['preview_template']=in_array($focusPreview,\Reamur\System\Library\Theme\ThemePackage::TEMPLATE_KEYS,true)?$focusPreview:'common/home';
        $data['preview_theme_id']=$id;
        $data['scenes']=[['id'=>'','name'=>$this->language->get('text_no_scene')],['id'=>'hero-particles','name'=>'Hero particles'],['id'=>'portfolio-3d','name'=>'Portfolio 3D'],['id'=>'tools-3d','name'=>'Tools 3D'],['id'=>'not-found','name'=>'Not found']];
        $data['revisions']=$id?$this->model_design_theme->getRevisions($id):[]; $data['template_keys'] = \Reamur\System\Library\Theme\ThemePackage::TEMPLATE_KEYS; $focusTemplate=$this->request->get['focus_template']??''; $data['focus_template']=is_string($focusTemplate)&&in_array($focusTemplate,\Reamur\System\Library\Theme\ThemePackage::TEMPLATE_KEYS,true)?$focusTemplate:''; $data['template_sources']=$data['templates']; foreach($data['template_keys'] as $templateKey) { if (!isset($data['template_sources'][$templateKey])) { $templateFile=DIR_SYSTEM.'../catalog/view/template/'.$templateKey.'.twig'; if (is_file($templateFile)) $data['template_sources'][$templateKey]=(string)file_get_contents($templateFile); } } $data['preview_links']=[]; foreach($data['template_keys'] as $key) $data['preview_links'][$key]=$this->url->link('design/theme.preview'.($id?'|'.$id:''),'template='.rawurlencode($key)); foreach($data['revisions'] as &$revision) $revision['restore']=$this->url->link('design/theme.restore|'.$id.'|'.(int)$revision['revision_id']); unset($revision);
        // F13-C: Loader::view() nao mescla o idioma; publica todas as chaves
        // carregadas (sem sobrescrever valores calculados) para nao deixar rotulos vazios.
        $data=array_replace($this->language->all(),$data);
        $this->chrome($data); $this->response->setOutput($this->load->view('design/theme_form',$data));
    }
    /**
     * F13-E: exporta o tema como pacote JSON versionado (rascunho por padrao,
     * publicado com ?source=published). GET de leitura, ACL access, no-store,
     * zero escrita. O checksum cobre exatamente o config exportado.
     */
    public function export(string $theme_id='0'): void {
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->addHeader('Cache-Control: no-store');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $fail=static function(): string { return (string)json_encode(['ok'=>false,'errors'=>['forbidden']]); };
        if (!$this->user->hasPermission('access','design/theme')) { $this->response->setOutput($fail()); return; }
        $this->load->model('design/theme');
        $id=(int)$theme_id; $theme=$id?$this->model_design_theme->getTheme($id):[];
        if (!$theme) { $this->response->setOutput($fail()); return; }
        $source=(string)($this->request->get['source']??'draft')==='published' ? 'published' : 'draft';
        $config=$source==='published'?$this->model_design_theme->getPublishedConfig($id):$this->model_design_theme->getDraftConfig($id);
        $package=$this->theme_package->export((string)($theme['name']??''),(string)($theme['slug']??''),$config,['source'=>$source]);
        $filename=preg_replace('/[^a-z0-9_-]/','',(string)($theme['slug']??'')) ?: 'theme';
        $this->response->addHeader('Content-Disposition: attachment; filename="'.$filename.'.reamurtheme.json"');
        $this->response->setOutput($this->theme_package->encode($package));
    }

    /**
     * F13-E: importa um pacote JSON como RASCUNHO do tema (nunca publica).
     * POST, ACL modify, CSRF, tamanho/checksum/allowlists fail-closed. Um
     * pacote invalido nao grava nada e volta com codigo de erro estavel.
     */
    public function import(string $theme_id='0'): void {
        $this->load->language('design/theme'); $this->load->model('design/theme');
        $id=(int)$theme_id;
        $back=$this->url->link('design/theme.edit'.($id?'|'.$id:''));
        $sep=str_contains($back,'?')?'&':'?';
        $method=(string)($this->request->server['REQUEST_METHOD']??'');
        if ($method!=='POST' || !$this->user->hasPermission('modify','design/theme')
            || !\Reamur\System\Library\Security\Csrf::check($this->session,(string)($this->request->post['csrf_token']??''))) {
            $this->response->redirect($back.$sep.'import_error=forbidden'); return;
        }
        $decoded=$this->theme_package->decode((string)($this->request->post['theme_package']??''));
        if (!$decoded['ok']) { $this->response->redirect($back.$sep.'import_error='.rawurlencode($decoded['errors'][0])); return; }
        $theme=$id?$this->model_design_theme->getTheme($id):[];
        $name=$id?(string)($theme['name']??$decoded['name']):$decoded['name'];
        $slug=$id?(string)($theme['slug']??$decoded['slug']):$decoded['slug'];
        $config=$decoded['config'];
        if (($config['parent']??'')===$slug) unset($config['parent']); // sem auto-heranca
        $saved=$this->model_design_theme->save($id,['name'=>$name,'slug'=>$slug,'config'=>$config],(int)$this->user->getUserId());
        $this->response->redirect($back.$sep.($saved?'import_ok=1':'import_error=save_failed'));
    }

    public function preview(string $theme_id='0'): void {
        if (!$this->user->hasPermission('access','design/theme')) { $this->response->redirect($this->url->link('error/permission')); return; }
        $this->load->language('design/theme'); $this->load->model('design/theme'); $id=(int)$theme_id; $theme=$this->model_design_theme->getTheme($id);
        $key=(string)($this->request->get['template']??''); $config=json_decode((string)($theme['draft_config']??$theme['config']??'{}'),true); $templates=is_array($config)?(array)($config['templates']??[]):[];
        if (!isset($templates[$key]) || !method_exists($this->template,'renderSandboxPreview')) { $this->response->setOutput('No saved source is available for this allowlisted preview.'); return; }
        try { $html=$this->template->renderSandboxPreview($key,(string)$templates[$key]); $this->response->setOutput('<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'"></head><body><main style="font:16px system-ui;padding:24px">'.$html.'</main></body></html>'); }
        catch (\Throwable $e) { $this->response->setOutput('<!doctype html><meta charset="utf-8"><p>'.htmlspecialchars($this->language->get('text_preview_error'),ENT_QUOTES,'UTF-8').'</p>'); }
    }
    public function restore(string $theme_id='0', string $revision_id='0'): void {
        if ($this->request->server['REQUEST_METHOD']!=='POST' || !$this->user->hasPermission('modify','design/theme')) { $this->response->redirect($this->url->link('error/permission')); return; }
        $this->load->model('design/theme');
        if (\Reamur\System\Library\Security\Csrf::check($this->session,$this->request->post['csrf_token']??'')) $this->model_design_theme->restoreRevision((int)$theme_id,(int)$revision_id,(int)$this->user->getUserId());
        $this->response->redirect($this->url->link('design/theme.edit|'.(int)$theme_id));
    }
    /**
     * F13-A: avalia um rascunho de condicoes SEM gravar nada. Usado pelo
     * builder para validacao ao vivo e pelo simulador de contexto.
     * ACL + CSRF + no-store; contexto reconstruido apenas com allowlists.
     */
    public function simulate(): void {
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->addHeader('Cache-Control: no-store');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $jsonFlags=JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE;
        $fail=static function(string $code) use ($jsonFlags): array {
            return ['ok'=>false,'errors'=>[['code'=>$code,'path'=>'request','rule'=>-1]],'matched'=>[],'ignored'=>[],'overrides'=>[]];
        };
        if (!$this->user->hasPermission('access','design/theme')) { $this->response->setOutput(json_encode($fail('forbidden'),$jsonFlags) ?: '{}'); return; }
        $post=$this->request->post;
        if (!\Reamur\System\Library\Security\Csrf::check($this->session,(string)($post['csrf_token']??''))) { $this->response->setOutput(json_encode($fail('csrf'),$jsonFlags) ?: '{}'); return; }
        $conditions=json_decode((string)($post['conditions']??''),true);
        $conditions=is_array($conditions)?$conditions:['version'=>1,'rules'=>[]];
        $engine=$this->theme_condition_engine;
        $errors=$engine->validate($conditions);
        $context=$this->simulateContext(isset($post['context'])&&is_array($post['context'])?$post['context']:[]);
        $result=$engine->evaluate($engine->normalize($conditions)['rules'],$context);
        $payload=[
            'ok'=>$errors===[],
            'errors'=>$errors,
            'matched'=>$result['matched'],
            'ignored'=>$result['ignored'],
            'overrides'=>[
                'primary'=>$result['primary'],'surface'=>$result['surface'],'scene'=>$result['scene'],
                'three'=>$result['three'],'vars'=>$result['vars'],'flags'=>$result['flags'],'classes'=>$result['classes'],
            ],
            'context'=>$context,
        ];
        $this->response->setOutput(json_encode($payload,$jsonFlags) ?: '{}');
    }

    /**
     * F13-C: constroi o config do RASCUNHO a partir do POST, sem gravar nada.
     * Usado por edit() (save) e compare() (previa): uma unica validacao.
     * @return array{name:string,slug:string,config:array,error:string}
     */
    private function buildDraftConfig(array $post): array {
        $bad=function(string $message): array { return ['name'=>'','slug'=>'','config'=>[],'error'=>$message]; };
        $name=trim((string)($post['name']??'')); $slug=strtolower(trim((string)($post['slug']??'')));
        if ($name==='' || !preg_match('/^[a-z0-9_-]{1,48}$/',$slug)) return $bad($this->language->get('error_validation'));
        $primary=(string)($post['primary']??''); $surface=(string)($post['surface']??'');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/',$primary) || !preg_match('/^#[0-9a-fA-F]{6}$/',$surface)) return $bad($this->language->get('error_validation'));
        $templates=(array)($post['templates']??[]); $safeTemplates=[]; $invalidTemplateSource=false;
        foreach($templates as $templateKey=>$source) {
            if (!in_array((string)$templateKey,\Reamur\System\Library\Theme\ThemePackage::TEMPLATE_KEYS,true) || !is_string($source) || strlen($source)>131072 || preg_match('~<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=~i',(string)$source)) { $invalidTemplateSource=true; continue; }
            $safeTemplates[(string)$templateKey]=$source;
        }
        if ($invalidTemplateSource) return $bad($this->language->get('error_template_source'));
        // F13-E: child-theme. Sem coluna nova: `parent` e so uma chave do JSON.
        // Formato allowlistado e proibido apontar para o proprio tema.
        $parent=strtolower(trim((string)($post['parent_theme']??'')));
        if ($parent!=='' && (preg_match('/^[a-z0-9_-]{1,48}$/D',$parent)!==1 || $parent===$slug)) return $bad($this->language->get('error_theme_parent'));
        $engine=$this->theme_condition_engine;
        $conditions=json_decode((string)($post['conditions']??''),true);
        $conditions=is_array($conditions)?$conditions:['version'=>1,'rules'=>[]];
        $conditionErrors=$engine->validate($conditions);
        // F13-D: um preset e apenas um ATALHO que preenche os mesmos campos
        // three_*; nenhuma chave nova entra no config do tema. Valor '' (ou
        // edicao manual) mantem os campos personalizados.
        $threeInput=[
            'enabled'=>(string)($post['three_enabled']??'0')==='1',
            'scene'=>(string)($post['three_scene']??''),
            'slot'=>(string)($post['three_slot']??'hero'),
            'count'=>(string)($post['three_count']??'400'),
            'color'=>(string)($post['three_color']??'#8a4fff'),
            'speed'=>(string)($post['three_speed']??'0.05'),
        ];
        $presetId=(string)($post['scene_preset']??'');
        if ($presetId!=='') {
            $presetFields=$this->three_scene_preset->apply($presetId);
            if ($presetFields===null) return $bad($this->language->get('error_scene_preset'));
            $threeInput=[
                'enabled'=>$presetFields['three_enabled']==='1',
                'scene'=>$presetFields['three_scene'],'slot'=>$presetFields['three_slot'],
                'count'=>$presetFields['three_count'],'color'=>$presetFields['three_color'],
                'speed'=>$presetFields['three_speed'],
            ];
        }
        $three=$engine->normalizeThree($threeInput);
        $tokensRaw=(array)($post['tokens']??[]);
        $tokenErrors=$this->theme_token_registry->validate($tokensRaw);
        $tokensNormalized=$this->theme_token_registry->normalize($tokensRaw);
        if ($conditionErrors) return $bad($this->language->get('error_conditions').' ('.$conditionErrors[0]['code'].')');
        if ($three===null) return $bad($this->language->get('error_three'));
        if ($tokenErrors || $tokensNormalized===null) return $bad($this->language->get('error_tokens').' ('.($tokenErrors[0]['code']??'contrast').')');
        // F13-F: composicao das secoes da home. Campos ausentes (pacotes da
        // frente E, clientes antigos) preservam o layout legado do CMS.
        $sectionOverride=null;
        if (array_key_exists('sections_order',$post)||array_key_exists('sections',$post)) {
            $order=$this->theme_section_registry->formOrder((string)($post['sections_order']??''));
            $enabled=(array)($post['sections']??[]);
            $selected=[];
            foreach($order as $sid) if (in_array($sid,$enabled,true)) $selected[]=$sid;
            $sectionOverride=$this->theme_section_registry->normalize($selected);
            if ($sectionOverride===null) return $bad($this->language->get('error_sections'));
        }
        // F13-G: componentes. Campos ausentes preservam pacotes/ temas A-F.
        $componentOverride=null;
        if (array_key_exists('components',$post)) {
            $rawComponents=(array)$post['components'];
            $clean=[];
            foreach($this->theme_component_registry->ids() as $cid) {
                if (!isset($rawComponents[$cid]) || !is_array($rawComponents[$cid])) continue;
                $clean[$cid]=[
                    'enabled'=>isset($rawComponents[$cid]['enabled'])&&(string)$rawComponents[$cid]['enabled']==='1',
                    'variant'=>(string)($rawComponents[$cid]['variant']??''),
                    'tone'=>(string)($rawComponents[$cid]['tone']??''),
                ];
            }
            $componentOverride=$this->theme_component_registry->normalize($clean);
            if ($componentOverride===null) return $bad($this->language->get('error_components'));
        }
        // F13-H: janela UTC + publico + percentual, tudo allowlistado.
        // Campos ausentes preservam pacotes/temas A-G (chave nao gravada).
        $scheduleOverride=null;
        if (array_key_exists('schedule',$post) && is_array($post['schedule'])) {
            $rawSchedule=(array)$post['schedule'];
            $scheduleOverride=$this->theme_schedule->normalize([
                'enabled'=>$rawSchedule['enabled']??false,
                'from'=>(string)($rawSchedule['from']??''),
                'until'=>(string)($rawSchedule['until']??''),
                'percent'=>(string)($rawSchedule['percent']??'0'),
                'scope'=>(string)($rawSchedule['scope']??'all'),
            ]);
            if ($scheduleOverride===null) return $bad($this->language->get('error_schedule'));
        }
        // F13-I: midias vetoriais. Campos ausentes preservam pacotes A-H.
        $mediaOverride=null;
        if (array_key_exists('media',$post)) {
            $rawMedia=(array)$post['media']; $cleanMedia=[];
            foreach($this->theme_media->ids() as $mid) {
                if (!isset($rawMedia[$mid]) || !is_array($rawMedia[$mid])) continue;
                $cleanMedia[$mid]=[
                    'enabled'=>isset($rawMedia[$mid]['enabled'])&&(string)$rawMedia[$mid]['enabled']==='1',
                    'variant'=>(string)($rawMedia[$mid]['variant']??''),
                    'tone'=>(string)($rawMedia[$mid]['tone']??''),
                ];
            }
            $mediaOverride=$this->theme_media->normalize($cleanMedia);
            if ($mediaOverride===null) return $bad($this->language->get('error_media'));
        }
        // F13-J: hosts/slugs/idiomas allowlistados. Campos ausentes preservam A-I.
        $sitesOverride=null;
        if (array_key_exists('sites',$post)) {
            $rawSites=(array)$post['sites']; $cleanSites=[];
            foreach($this->theme_sites->ids() as $sid) {
                if (!isset($rawSites[$sid]) || !is_array($rawSites[$sid])) continue;
                $cleanSites[$sid]=[
                    'host'=>(string)($rawSites[$sid]['host']??''),
                    'slug'=>(string)($rawSites[$sid]['slug']??''),
                    'language'=>(string)($rawSites[$sid]['language']??'pt-br'),
                    'enabled'=>isset($rawSites[$sid]['enabled'])&&(string)$rawSites[$sid]['enabled']==='1',
                ];
            }
            $sitesOverride=$this->theme_sites->normalize($cleanSites);
            if ($sitesOverride===null) return $bad($this->language->get('error_sites'));
        }
        $normalized=$engine->normalize($conditions);
        $out=['primary'=>$primary,'surface'=>$surface,
            'scene'=>in_array(($post['scene']??''),['','hero-particles','portfolio-3d','tools-3d','not-found'],true)?(string)($post['scene']??''):'',
            'templates'=>$safeTemplates,
            'conditions'=>['version'=>1,'rules'=>$normalized['rules']],
            'three'=>$three,'tokens'=>$tokensNormalized,'parent'=>$parent];
        if ($sectionOverride!==null) $out['sections']=$sectionOverride;
        if ($componentOverride!==null) $out['components']=$componentOverride;
        if ($scheduleOverride!==null) $out['schedule']=$scheduleOverride;
        if ($mediaOverride!==null) $out['media']=$mediaOverride;
        if ($sitesOverride!==null) $out['sites']=$sitesOverride;
        return ['name'=>$name,'slug'=>$slug,'error'=>'','config'=>$out];
    }

    /**
     * F13-C: PREVIA REAL rascunho x publicado. POST do formulario atual,
     * ACL access + CSRF + no-store, zero escrita, mesmo pipeline do Catalog.
     */
    public function compare(string $theme_id='0'): void {
        $this->response->addHeader('Content-Type: text/html; charset=utf-8');
        $this->response->addHeader('Cache-Control: no-store');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $this->response->addHeader('Referrer-Policy: no-referrer');
        $this->load->language('design/theme');
        $labels=$this->compareLabels();
        $method=(string)($this->request->server['REQUEST_METHOD']??'');
        $post=$this->request->post;
        if (!$this->user->hasPermission('access','design/theme') || $method!=='POST'
            || !\Reamur\System\Library\Security\Csrf::check($this->session,(string)($post['csrf_token']??''))) {
            $this->response->setOutput(self::compareErrorPage($labels['forbidden'])); return;
        }
        $this->load->model('design/theme');
        $id=(int)$theme_id;
        $published=$id?$this->model_design_theme->getPublishedConfig($id):[];
        $built=$this->buildDraftConfig($post);
        // Rascunho invalido: mostra o ultimo salvo e avisa; NAO grava nada.
        $draft=$built['error']===''?$built['config']:($id?$this->model_design_theme->getDraftConfig($id):[]);
        $context=$this->simulateContext([
            'route'=>(string)($post['preview_route']??'common/home'),
            'language'=>(string)($post['preview_language']??'en-us'),
            'device'=>(string)($post['preview_device']??'desktop'),
            'variant'=>(string)($post['preview_variant']??''),
            'hour'=>(int)($post['preview_hour']??9),
            'logged_in'=>(string)($post['preview_logged_in']??''),
            'query'=>isset($post['preview_query'])&&is_array($post['preview_query'])?$post['preview_query']:[],
        ]);
        // F13-H: sonda deterministica. Nenhum dado do visitante e exibido;
        // apenas o bucket (inteiro) aparece na fita. Zero escrita.
        $probeNow=(string)($post['schedule_probe_now']??$this->theme_schedule->now());
        if ($this->theme_schedule->parseTime($probeNow)===null) $probeNow=$this->theme_schedule->now();
        $probeVisitor=substr((string)($post['schedule_probe_visitor']??'visitor-a'),0,64);
        $probeLogged=in_array(strtolower((string)($post['schedule_probe_logged']??'')),['1','true','yes','on'],true);
        $scheduleDecision=$this->theme_schedule->decide(
            $this->theme_schedule->normalize($draft['schedule']??null),
            $probeNow, $probeVisitor, $probeLogged
        );
        $pubDecision=array_replace($scheduleDecision,['effective'=>'published','active'=>false,'reason'=>'disabled']);
        $key=(string)($post['preview_template']??'common/home');
        if (!in_array($key,\Reamur\System\Library\Theme\ThemePackage::TEMPLATE_KEYS,true)) $key='common/home';
        $snapshot=$this->theme_snapshot;
        // F13-E: heranca resolvida no mesmo ponto do Catalog (ThemeSnapshot).
        $mapPublished=$this->model_design_theme->getThemeMap('published');
        $mapDraft=$this->model_design_theme->getThemeMap('draft');
        // F13-J: a sonda de host e so COMPARADA (nunca exibida); cada painel
        // mostra a politica de sites do seu proprio config. Zero escrita.
        $sitesPub=$this->theme_sites->normalize($published['sites']??null) ?? [];
        $sitesDraft=$this->theme_sites->normalize($draft['sites']??null) ?? [];
        $probeHost=(string)($post['site_probe_host']??'');
        $sitePub=$this->theme_sites->match($sitesPub,$probeHost);
        $siteDraft=$this->theme_sites->match($sitesDraft,$probeHost);
        $pubState=$snapshot->resolve($published,$context,$mapPublished,(string)($published['slug']??''),$pubDecision,
            ['sites'=>$sitesPub,'active'=>$sitePub]);
        $draftState=$snapshot->resolve($draft,$context,$mapDraft,(string)($draft['slug']??''),$scheduleDecision,
            ['sites'=>$sitesDraft,'active'=>$siteDraft]);
        $pubSource=isset($published['templates'][$key])?$published['templates'][$key]:null;
        $draftSource=isset($draft['templates'][$key])?$draft['templates'][$key]:null;
        try {
            $html=(new \Reamur\System\Library\Theme\ThemeCompareView())->render([
                'lang'=>(string)$this->config->get('language_directory','en-us'),
                'labels'=>$labels,'context'=>$context,'template_key'=>$key,
                'error'=>$built['error'],
                'published'=>$pubState,'draft'=>$draftState,
                'published_html'=>$this->renderPreviewFragment($key,$pubSource),
                'draft_html'=>$this->renderPreviewFragment($key,$draftSource),
                'published_thumb'=>$this->three_scene_preset->dataUri($pubState['three']),
                'draft_thumb'=>$this->three_scene_preset->dataUri($draftState['three']),
                'diff'=>$snapshot->diff($published,$draft),
            ]);
        } catch (\Throwable $e) {
            $html=self::compareErrorPage($labels['render_error']);
        }
        $this->response->setOutput($html);
    }

    /** Fragmento do template allowlistado, via Twig Sandbox (ADR 0080). */
    private function renderPreviewFragment(string $key, $source): string {
        if (!is_string($source) || $source==='') {
            $file=DIR_SYSTEM.'../catalog/view/template/'.$key.'.twig';
            $source=is_file($file)?(string)file_get_contents($file):'';
        }
        if ($source==='') return '<p>'.htmlspecialchars($this->language->get('text_preview_no_source'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';
        if (!method_exists($this->template,'renderSandboxPreview')) return '<p>'.htmlspecialchars($this->language->get('text_preview_error'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';
        try { return (string)$this->template->renderSandboxPreview($key,$source); }
        catch (\Throwable $e) { return '<p>'.htmlspecialchars($this->language->get('text_preview_error'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>'; }
    }

    /**
     * F13-D: miniatura SVG das cenas nativas. GET de leitura idempotente,
     * ACL access, no-store, nosniff, CSP propria e zero escrita. So valores
     * allowlistados entram no SVG; nada do usuario e interpolado como texto.
     * Carregada via <img>, onde SVG nunca executa script. Sem CSRF: nao ha
     * mutacao de estado (o mesmo padrao dos assets publicos allowlistados).
     */
    public function sceneThumb(): void {
        $this->response->addHeader('Content-Type: image/svg+xml; charset=utf-8');
        $this->response->addHeader('Cache-Control: no-store');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $this->response->addHeader("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
        $this->response->addHeader('Content-Disposition: inline; filename="theme-scene.svg"');
        $registry=$this->three_scene_preset;
        if (!$this->user->hasPermission('access','design/theme')) {
            $this->response->setOutput($registry->svg(['enabled'=>false])); return;
        }
        $g=$this->request->get;
        $cfg=$registry->normalize([
            'scene'=>(string)($g['scene']??''),'slot'=>(string)($g['slot']??'hero'),
            'count'=>(string)($g['count']??400),'color'=>(string)($g['color']??'#8a4fff'),
            'speed'=>(string)($g['speed']??0.05),'enabled'=>(string)($g['enabled']??'0'),
        ]);
        $this->response->setOutput($registry->svg($cfg));
    }

    /**
     * F13-G: miniatura SVG de um componente. GET idempotente, ACL access,
     * no-store, nosniff, CSP propria e zero escrita. Id/variant/tone sao
     * normalizados pela allowlist do registry (valores invalidos caem no default).
     */
    public function componentThumb(): void {
        $this->response->addHeader('Content-Type: image/svg+xml; charset=utf-8');
        $this->response->addHeader('Cache-Control: no-store');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $this->response->addHeader("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
        $this->response->addHeader('Content-Disposition: inline; filename="theme-component.svg"');
        $registry=$this->theme_component_registry;
        if (!$this->user->hasPermission('access','design/theme')) {
            $this->response->setOutput($registry->svg('card')); return;
        }
        $g=$this->request->get;
        $this->response->setOutput($registry->svg(
            (string)($g['id']??'card'),(string)($g['variant']??''),(string)($g['tone']??'')
        ));
    }

    /**
     * F13-I: miniatura SVG de uma midia vetorial. GET idempotente, ACL access,
     * no-store, nosniff, CSP propria e zero escrita. Id/variant/tone sao
     * normalizados pela allowlist (valores invalidos caem no default).
     */
    public function mediaThumb(): void {
        $this->response->addHeader('Content-Type: image/svg+xml; charset=utf-8');
        $this->response->addHeader('Cache-Control: no-store');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $this->response->addHeader("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
        $this->response->addHeader('Content-Disposition: inline; filename="theme-media.svg"');
        $registry=$this->theme_media;
        if (!$this->user->hasPermission('access','design/theme')) {
            $this->response->setOutput($registry->svg('pattern-dots')); return;
        }
        $g=$this->request->get;
        $this->response->setOutput($registry->svg(
            (string)($g['id']??'pattern-dots'),(string)($g['variant']??''),(string)($g['tone']??'')
        ));
    }

    /** F13-I: linhas da galeria de midia com rotulos resolvidos. */
    private function mediaRows(?array $raw): array {
        $rows=$this->theme_media->view($raw);
        foreach($rows as &$row) {
            $row['label']=$this->language->get($row['label_key']);
            $row['help']=$this->language->get($row['help_key']);
            $row['group_label']=$this->language->get($row['group_key']);
        } unset($row);
        return $rows;
    }

    /** F13-G: linhas da galeria com rotulos resolvidos e miniatura inicial. */
    private function componentRows(?array $raw): array {
        $rows=$this->theme_component_registry->view($raw);
        foreach($rows as &$row) {
            $row['label']=$this->language->get($row['label_key']);
            $row['help']=$this->language->get($row['help_key']);
            $row['group_label']=$this->language->get($row['group_key']);
            $row['thumb']=$this->theme_component_registry->dataUri($row['id'],$row['variant'],$row['tone']);
        } unset($row);
        return $rows;
    }

    /** F13-F: linhas do builder ja com rotulos resolvidos (nenhum HTML aqui). */
    private function sectionRows(array $selected): array {
        $rows=$this->theme_section_registry->view($selected);
        foreach($rows as &$row) {
            $row['label']=$this->language->get($row['label_key']);
            $row['group_label']=$this->language->get($row['group_key']);
            $row['kind_label']=$this->language->get($row['kind_key']);
        } unset($row);
        return $rows;
    }

    /** @return array<string,string> */
    private function compareLabels(): array {
        $keys=['title','context','variant','invalid','nothing_saved','identical','changes','diff_help','diff_title','changed','added','removed','truncated','empty','published','draft','sample_title','sample_body','sample_button','primary','surface','matched','flags','tokens','forbidden','render_error','sections','section_legacy','section_empty','components','component_empty','schedule','schedule_inactive','schedule_active',
         'schedule_window','schedule_rollout','schedule_scope','schedule_bucket',
         'schedule_now','schedule_open','media','media_empty','media_ratio','media_variant',
         'site','site_empty','site_active','site_language'];
        $out=[]; foreach($keys as $k) $out[$k]=$this->language->get('compare_'.$k);
        return $out;
    }

    private static function compareErrorPage(string $message): string {
        return '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'"><title>Preview</title></head><body style="font:14px system-ui;padding:24px"><p>'.htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p></body></html>';
    }

    /** Contexto do simulador: somente campos/valores allowlisted, sem globais. */
    /** F13-B: monta os campos e os pares de contraste para o formulario. */
    private function applyTokenFormData(array &$data, array $raw): void {
        $registry=$this->theme_token_registry;
        $normalized=$registry->normalize($raw);
        $view=$normalized ?? array_replace($registry->defaults(),array_map(static function($v){ return is_scalar($v)?(string)$v:''; },$raw));
        $data['tokens']=$view;
        $data['token_fields']=$registry->formFields(fn(string $k): string => $this->language->get('text_token_'.$k),$view);
        $data['token_groups']=$registry->groups();
        $data['token_pairs']=$registry->contrastPairs($view);
    }

    private function simulateContext(array $raw): array {
        $spec=$this->theme_condition_engine->spec();
        $str=static function($v,int $max=120): string { return is_scalar($v)?substr((string)$v,0,$max):''; };
        $device=$str($raw['device']??'desktop',20);
        if (!in_array($device,['desktop','tablet','mobile'],true)) $device='desktop';
        $language=$str($raw['language']??'en-us',16);
        if (!in_array($language,['en-us','pt-br','es-eu'],true)) $language='en-us';
        $route=$str($raw['route']??'common/home',120);
        $variant=$str($raw['variant']??'',4);
        if (!in_array($variant,['','a','b'],true)) $variant='';
        $hour=filter_var($raw['hour']??9,FILTER_VALIDATE_INT);
        if ($hour===false||$hour<0||$hour>23) $hour=9;
        $date=$str($raw['date']??date('Y-m-d'),10);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$m)!==1 || !checkdate((int)$m[2],(int)$m[3],(int)$m[1])) $date=date('Y-m-d');
        $stamp=(int)strtotime($date.' 12:00:00');
        $query=[];
        $q=isset($raw['query'])&&is_array($raw['query'])?$raw['query']:[];
        foreach($spec['query_keys'] as $key) {
            if (array_key_exists($key,$q) && is_scalar($q[$key])) $query[$key]=substr((string)$q[$key],0,120);
        }
        $logged=in_array(strtolower($str($raw['logged_in']??'')),['1','true','yes','on'],true);
        return [
            'route'=>$route,'path'=>'/'.ltrim($route,'/'),'language'=>$language,
            'device'=>$device,'mobile'=>$device!=='desktop','logged_in'=>$logged,
            'user_group'=>$str($raw['user_group']??'',48),'hour'=>(int)$hour,
            'weekday'=>(int)date('N',$stamp),'date'=>$date,'month'=>(int)date('n',$stamp),
            'variant'=>$variant,'https'=>true,'ajax'=>false,'query'=>$query,
        ];
    }

    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);
        $data['header']=$this->load->controller('common/header',$data);
        $data['footer']=$this->load->controller('common/footer',$data);
    }
    public function activate(string $theme_id='0'): void {
        if ($this->request->server['REQUEST_METHOD']!=='POST' || !$this->user->hasPermission('modify','design/theme')) { $this->response->redirect($this->url->link('error/permission')); return; }
        $this->load->model('design/theme'); $token=(string)($this->request->post['csrf_token']??'');
        if (\Reamur\System\Library\Security\Csrf::check($this->session,$token)) $this->model_design_theme->activate((int)$theme_id,(int)$this->user->getUserId());
        $this->response->redirect($this->url->link('design/theme'));
    }
}
