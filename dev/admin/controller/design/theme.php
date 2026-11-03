<?php
/** Admin theme manager (F10-02); access and modify are separately ACL-gated. */
namespace Reamur\Admin\Controller\Design;
class Theme extends \Reamur\System\Engine\Controller {
    /** Catalog templates the theme code editor may change; scripts stay in fixed runtime slots. */
    private const TEMPLATE_KEYS = [
        'common/home', 'common/footer', 'common/contact',
        'partial/site_header', 'partial/hero', 'partial/hero-3d', 'partial/about',
        'partial/methodology', 'partial/skills', 'partial/services', 'partial/testimonials',
        'partial/portfolio', 'partial/tools', 'partial/news', 'partial/clients', 'partial/contact',
        'cms/page_info', 'catalog/project_list', 'catalog/project_info',
        'blog/blog_list', 'blog/blog_info', 'tool/tool_list', 'tool/tool_info',
        'checkout/quote', 'error/not_found',
    ];
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
        }
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
        $data['preview_template']=in_array($focusPreview,self::TEMPLATE_KEYS,true)?$focusPreview:'common/home';
        $data['preview_theme_id']=$id;
        $data['scenes']=[['id'=>'','name'=>$this->language->get('text_no_scene')],['id'=>'hero-particles','name'=>'Hero particles'],['id'=>'portfolio-3d','name'=>'Portfolio 3D'],['id'=>'tools-3d','name'=>'Tools 3D'],['id'=>'not-found','name'=>'Not found']];
        $data['revisions']=$id?$this->model_design_theme->getRevisions($id):[]; $data['template_keys'] = self::TEMPLATE_KEYS; $focusTemplate=$this->request->get['focus_template']??''; $data['focus_template']=is_string($focusTemplate)&&in_array($focusTemplate,self::TEMPLATE_KEYS,true)?$focusTemplate:''; $data['template_sources']=$data['templates']; foreach($data['template_keys'] as $templateKey) { if (!isset($data['template_sources'][$templateKey])) { $templateFile=DIR_SYSTEM.'../catalog/view/template/'.$templateKey.'.twig'; if (is_file($templateFile)) $data['template_sources'][$templateKey]=(string)file_get_contents($templateFile); } } $data['preview_links']=[]; foreach($data['template_keys'] as $key) $data['preview_links'][$key]=$this->url->link('design/theme.preview'.($id?'|'.$id:''),'template='.rawurlencode($key)); foreach($data['revisions'] as &$revision) $revision['restore']=$this->url->link('design/theme.restore|'.$id.'|'.(int)$revision['revision_id']); unset($revision);
        // F13-C: Loader::view() nao mescla o idioma; publica todas as chaves
        // carregadas (sem sobrescrever valores calculados) para nao deixar rotulos vazios.
        $data=array_replace($this->language->all(),$data);
        $this->chrome($data); $this->response->setOutput($this->load->view('design/theme_form',$data));
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
            if (!in_array((string)$templateKey,self::TEMPLATE_KEYS,true) || !is_string($source) || strlen($source)>131072 || preg_match('~<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=~i',(string)$source)) { $invalidTemplateSource=true; continue; }
            $safeTemplates[(string)$templateKey]=$source;
        }
        if ($invalidTemplateSource) return $bad($this->language->get('error_template_source'));
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
        $normalized=$engine->normalize($conditions);
        return [
            'name'=>$name,'slug'=>$slug,'error'=>'',
            'config'=>[
                'primary'=>$primary,'surface'=>$surface,
                'scene'=>in_array(($post['scene']??''),['','hero-particles','portfolio-3d','tools-3d','not-found'],true)?(string)$post['scene']:'',
                'templates'=>$safeTemplates,
                'conditions'=>['version'=>1,'rules'=>$normalized['rules']],
                'three'=>$three,
                'tokens'=>$tokensNormalized,
            ],
        ];
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
        $key=(string)($post['preview_template']??'common/home');
        if (!in_array($key,self::TEMPLATE_KEYS,true)) $key='common/home';
        $snapshot=$this->theme_snapshot;
        $pubState=$snapshot->resolve($published,$context);
        $draftState=$snapshot->resolve($draft,$context);
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

    /** @return array<string,string> */
    private function compareLabels(): array {
        $keys=['title','context','variant','invalid','nothing_saved','identical','changes','diff_help','diff_title','changed','added','removed','truncated','empty','published','draft','sample_title','sample_body','sample_button','primary','surface','matched','flags','tokens','forbidden','render_error'];
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
