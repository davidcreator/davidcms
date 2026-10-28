<?php
/** Admin theme manager (F10-02); access and modify are separately ACL-gated. */
namespace Reamur\Admin\Controller\Design;
class Theme extends \Reamur\System\Engine\Controller {
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
        if ($this->request->server['REQUEST_METHOD']==='POST') {
            $post=$this->request->post;
            if (!$data['can_modify']) { $data['error']=$this->language->get('error_permission'); }
            elseif (!\Reamur\System\Library\Security\Csrf::check($this->session,(string)($post['csrf_token']??''))) { $data['error']=$this->language->get('error_csrf'); }
            else {
                $name=trim((string)($post['name']??'')); $slug=strtolower(trim((string)($post['slug']??'')));
                if ($name==='' || !preg_match('/^[a-z0-9_-]{1,48}$/',$slug)) $data['error']=$this->language->get('error_validation');
                else {
                    $primary=(string)($post['primary']??''); $surface=(string)($post['surface']??'');
                    if (!preg_match('/^#[0-9a-fA-F]{6}$/',$primary) || !preg_match('/^#[0-9a-fA-F]{6}$/',$surface)) $data['error']=$this->language->get('error_validation');
                    else { $templates=(array)($post['templates']??[]); $allowedTemplates=['common/home','common/footer','partial/hero','partial/about','partial/portfolio','cms/page_info','catalog/project_list']; $safeTemplates=[]; foreach($templates as $templateKey=>$source) { if (in_array((string)$templateKey,$allowedTemplates,true) && is_string($source) && strlen($source)<=131072 && !preg_match('~<\\s*script\\b|javascript\\s*:|\\bon[a-z]+\\s*=~i',$source)) $safeTemplates[(string)$templateKey]=$source; } $saved=$this->model_design_theme->save($id,['name'=>$name,'slug'=>$slug,'config'=>['primary'=>$primary,'surface'=>$surface,'scene'=>in_array(($post['scene']??''),['','hero-particles','portfolio-3d','tools-3d','not-found'],true)?(string)$post['scene']: '', 'templates'=>$safeTemplates]],(int)$this->user->getUserId()); if ($saved) { $this->response->redirect($this->url->link('design/theme')); return; } $data['error']=$this->language->get('error_validation'); }
                }
            }
            foreach(['name','slug','primary','surface','scene'] as $k) $data[$k]=(string)($post[$k]??$data[$k]);
        }
        $data['scenes']=[['id'=>'','name'=>$this->language->get('text_no_scene')],['id'=>'hero-particles','name'=>'Hero particles'],['id'=>'portfolio-3d','name'=>'Portfolio 3D'],['id'=>'tools-3d','name'=>'Tools 3D'],['id'=>'not-found','name'=>'Not found']];
        $data['revisions']=$id?$this->model_design_theme->getRevisions($id):[]; $data['template_keys']=['common/home','common/footer','partial/hero','partial/about','partial/portfolio','cms/page_info','catalog/project_list']; $data['template_sources']=$data['templates']; foreach($data['template_keys'] as $templateKey) { if (!isset($data['template_sources'][$templateKey])) { $templateFile=DIR_SYSTEM.'../catalog/view/template/'.$templateKey.'.twig'; if (is_file($templateFile)) $data['template_sources'][$templateKey]=(string)file_get_contents($templateFile); } } $data['preview_links']=[]; foreach($data['template_keys'] as $key) $data['preview_links'][$key]=$this->url->link('design/theme.preview'.($id?'|'.$id:''),'template='.rawurlencode($key)); foreach($data['revisions'] as &$revision) $revision['restore']=$this->url->link('design/theme.restore|'.$id.'|'.(int)$revision['revision_id']); unset($revision);
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
