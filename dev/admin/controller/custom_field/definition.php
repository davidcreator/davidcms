<?php
/**
 * F9-06 — interface administrativa para definições de campos personalizados.
 * Sem integração com CMS/page nem renderização pública (reservadas à F9-07+).
 */
namespace Reamur\Admin\Controller\Custom_field;

class Definition extends \Reamur\System\Engine\Controller {
    private const ROUTE = 'custom_field/definition';

    public function index(): void {
        $this->load->language(self::ROUTE);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model(self::ROUTE);
        $data = $this->labels([
            'heading_title','text_list','text_groups','text_fields','text_no_results','text_confirm_delete','text_enabled','text_disabled',
            'column_key','column_label','column_entity','column_type','column_group','column_visibility','column_sort_order','column_action',
            'button_add_group','button_add_field','button_edit','button_delete','button_filter','button_reset','button_close','text_all_groups',
            'text_empty_title','text_empty_hint','text_private','text_public','text_group_has_fields','text_field_has_values'
        ]);
        $data['can_modify'] = $this->user->hasPermission('modify', self::ROUTE);
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['text_flash'] = $this->flash();
        $entity = 'cms/page';
        $data['groups'] = [];
        foreach ($this->model_custom_field_definition->getGroups(['entity_type'=>$entity]) as $row) {
            $row=(array)$row; $id=(int)$row['group_id'];
            $data['groups'][]=['group_id'=>$id,'key'=>(string)$row['key'],'label'=>(string)$row['label'],'status'=>(int)$row['status'],'sort_order'=>(int)$row['sort_order'],'field_count'=>$this->model_custom_field_definition->countGroupFields($id),'edit'=>$this->url->link(self::ROUTE.'.edit_group|'.$id),'delete'=>$this->url->link(self::ROUTE.'.delete_group|'.$id)];
        }
        $data['fields'] = [];
        foreach ($this->model_custom_field_definition->getFields(['entity_type'=>$entity]) as $row) {
            $row=(array)$row; $id=(int)$row['field_id'];
            $data['fields'][]=['field_id'=>$id,'key'=>(string)$row['key'],'label'=>(string)$row['label'],'type'=>(string)$row['type'],'group'=>(string)$row['group_name'],'visibility'=>(string)$row['visibility'],'sort_order'=>(int)$row['sort_order'],'edit'=>$this->url->link(self::ROUTE.'.edit_field|'.$id),'delete'=>$this->url->link(self::ROUTE.'.delete_field|'.$id)];
        }
        $data['add_group']=$this->url->link(self::ROUTE.'.add_group');
        $data['add_field']=$this->url->link(self::ROUTE.'.add_field');
        $data['text_private']=$this->language->get('text_private');
        $data['text_public']=$this->language->get('text_public');
        $this->chrome($data);
        $this->response->setOutput($this->load->view('custom_field/definition_list',$data));
    }

    public function add_group(): void { $this->groupForm(0); }
    public function edit_group(string $id='0'): void { $this->groupForm((int)$id); }
    private function groupForm(int $id): void {
        $this->load->language(self::ROUTE); $this->load->model(self::ROUTE);
        if (!$this->canModifyPost()) return;
        $group=$id ? $this->model_custom_field_definition->getGroup($id) : [];
        if ($id && !$group) { $this->response->redirect($this->url->link('error/not_found')); return; }
        if ($this->isPost()) {
            $errors=$this->csrfValid()? $this->validateGroup($id):[$this->language->get('error_csrf')];
            if (!$errors) {
                $payload=$this->groupPayload();
                if ($id) $this->model_custom_field_definition->editGroup($id,$payload); else $this->model_custom_field_definition->addGroup($payload);
                $this->setFlash($this->language->get($id?'text_success_edit':'text_success_add'));
                $this->response->redirect($this->url->link(self::ROUTE)); return;
            }
        } else $errors=[];
        $data=$this->formBase($id?'text_edit_group':'text_add_group',$errors);
        $data['form_kind']='group'; $data['action']=$this->url->link(self::ROUTE.($id?'.edit_group|'.$id:'.add_group'));
        $data['key']=(string)($this->request->post['key']??($group['key']??''));
        $data['name']=(string)($this->request->post['name']??($group['name']??''));
        $data['status']=isset($this->request->post['status'])?1:($this->isPost()?0:(int)($group['status']??1));
        $data['sort_order']=(string)($this->request->post['sort_order']??($group['sort_order']??0));
        $data['descriptions']=$this->descriptionRows('group',$id);
        $data['cancel']=$this->url->link(self::ROUTE);
        $this->chrome($data); $this->response->setOutput($this->load->view('custom_field/definition_form',$data));
    }

    public function add_field(): void { $this->fieldForm(0); }
    public function edit_field(string $id='0'): void { $this->fieldForm((int)$id); }
    private function fieldForm(int $id): void {
        $this->load->language(self::ROUTE); $this->load->model(self::ROUTE);
        if (!$this->canModifyPost()) return;
        $field=$id?$this->model_custom_field_definition->getField($id):[];
        if ($id && !$field) { $this->response->redirect($this->url->link('error/not_found')); return; }
        if ($this->isPost()) {
            $errors=$this->csrfValid()? $this->validateField($id,$field):[$this->language->get('error_csrf')];
            if (!$errors) {
                $payload=$this->fieldPayload($field);
                if ($id) $this->model_custom_field_definition->editField($id,$payload); else $this->model_custom_field_definition->addField($payload);
                $this->setFlash($this->language->get($id?'text_success_edit':'text_success_add'));
                $this->response->redirect($this->url->link(self::ROUTE)); return;
            }
        } else $errors=[];
        $data=$this->formBase($id?'text_edit_field':'text_add_field',$errors);
        $data['form_kind']='field'; $data['action']=$this->url->link(self::ROUTE.($id?'.edit_field|'.$id:'.add_field'));
        $data['groups']=$this->model_custom_field_definition->getGroups(['entity_type'=>'cms/page']);
        $data['types']=$this->model_custom_field_definition->getAllowedTypes();
        $data['visibility_options']=$this->model_custom_field_definition->getAllowedVisibility();
        $data['group_id']=(int)($this->request->post['group_id']??($field['group_id']??0));
        $data['key']=(string)($this->request->post['key']??($field['key']??''));
        $data['type']=(string)($field['type']??($this->request->post['type']??'text'));
        $data['localized']=(int)($field['localized']??($this->request->post['localized']??0)); $data['required']=isset($this->request->post['required'])?1:($this->isPost()?0:(int)($field['required']??0));
        $data['visibility']=(string)($this->request->post['visibility']??($field['visibility']??'admin_only'));
        $data['sort_order']=(string)($this->request->post['sort_order']??($field['sort_order']??0));
        $data['descriptions']=$this->descriptionRows('field',$id);
        $stored=json_decode((string)($field['options']??'[]'),true); $posted=$this->request->post['options']??null;
        $data['options']=is_array($posted)?$posted:(is_array($stored)?$stored:[]);
        $data['cancel']=$this->url->link(self::ROUTE);
        $this->chrome($data); $this->response->setOutput($this->load->view('custom_field/definition_form',$data));
    }

    public function delete_group(string $id='0'): void { $this->deleteDefinition((int)$id,'group'); }
    public function delete_field(string $id='0'): void { $this->deleteDefinition((int)$id,'field'); }
    private function deleteDefinition(int $id,string $kind): void {
        $this->load->language(self::ROUTE);
        if (!$this->isPost() || !$this->user->hasPermission('modify',self::ROUTE) || !$this->csrfValid()) { $this->response->redirect($this->url->link('error/permission')); return; }
        $this->load->model(self::ROUTE);
        $ok=$kind==='group'?$this->model_custom_field_definition->deleteGroup($id):$this->model_custom_field_definition->deleteField($id);
        if ($ok) $this->setFlash($this->language->get('text_success_delete'));
        else $this->setFlash($this->language->get($kind==='group'?'text_group_has_fields':'text_field_has_values'),'error');
        $this->response->redirect($this->url->link(self::ROUTE));
    }

    private function validateGroup(int $id): array {
        $p=$this->request->post; $key=trim((string)($p['key']??'')); $name=trim((string)($p['name']??'')); $errors=[];
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/',$key)) $errors[]=$this->language->get('error_key');
        if ($name==='') $errors[]=$this->language->get('error_name');
        if ($this->model_custom_field_definition->groupExists('cms/page',$key,$id)) $errors[]=$this->language->get('error_duplicate_key');
        if (empty($p['group_description'][1]['name'])) $errors[]=$this->language->get('error_en_label');
        return $errors;
    }
    private function validateField(int $id,array $field): array {
        $p=$this->request->post; $key=trim((string)($p['key']??($field['key']??''))); $type=(string)($field['type']??($p['type']??'')); $errors=[];
        if (!$id && !preg_match('/^[a-z][a-z0-9_]{1,63}$/',$key)) $errors[]=$this->language->get('error_key');
        if (!$id && !in_array($type,$this->model_custom_field_definition->getAllowedTypes(),true)) $errors[]=$this->language->get('error_type');
        if ($this->model_custom_field_definition->fieldExists('cms/page',$key,$id)) $errors[]=$this->language->get('error_duplicate_key');
        $groups=$this->model_custom_field_definition->getGroups(['entity_type'=>'cms/page']); $allowed=array_map(static fn($g)=>(int)((array)$g)['group_id'],$groups);
        if (!in_array((int)($p['group_id']??0),$allowed,true)) $errors[]=$this->language->get('error_group');
        if (!in_array((string)($p['visibility']??'admin_only'),$this->model_custom_field_definition->getAllowedVisibility(),true)) $errors[]=$this->language->get('error_visibility');
        if (empty($p['field_description'][1]['label'])) $errors[]=$this->language->get('error_en_label');
        return $errors;
    }
    private function groupPayload(): array {
        $p=$this->request->post; $d=[]; foreach ((array)($p['group_description']??[]) as $lid=>$row) $d[(int)$lid]=['name'=>mb_substr(trim((string)($row['name']??'')),0,128)];
        return ['entity_type'=>'cms/page','key'=>mb_substr(trim((string)($p['key']??'')),0,64),'name'=>mb_substr(trim((string)($p['name']??'')),0,128),'status'=>isset($p['status'])?1:0,'sort_order'=>(int)($p['sort_order']??0),'descriptions'=>$d];
    }
    private function fieldPayload(array $field): array {
        $p=$this->request->post; $d=[]; foreach ((array)($p['field_description']??[]) as $lid=>$row) $d[(int)$lid]=['label'=>mb_substr(trim((string)($row['label']??'')),0,128),'help'=>mb_substr(trim((string)($row['help']??'')),0,255)];
        $opts=[];foreach ((array)($p['options']['value']??[]) as $i=>$v) $opts[]=['value'=>(string)$v,'label'=>(string)($p['options']['label'][$i]??'')];
        return ['entity_type'=>'cms/page','group_id'=>(int)($p['group_id']??0),'key'=>(string)($field['key']??trim((string)($p['key']??''))),'type'=>(string)($field['type']??($p['type']??'')),'localized'=>(int)($field['localized']??($p['localized']??0)),'required'=>isset($p['required'])?1:0,'visibility'=>(string)($p['visibility']??'admin_only'),'options'=>$opts,'sort_order'=>(int)($p['sort_order']??0),'descriptions'=>$d];
    }
    private function descriptionRows(string $kind,int $id): array {
        $langs=$this->model_custom_field_definition->getLanguages();
        $saved=$id?($kind==='group'?$this->model_custom_field_definition->getGroupDescriptions($id):$this->model_custom_field_definition->getFieldDescriptions($id)):[];
        $post=$this->request->post[$kind==='group'?'group_description':'field_description']??[]; $out=[];
        foreach($langs as $lang){$lang=(array)$lang;$lid=(int)$lang['language_id'];$old=(array)($saved[$lid]??[]);$new=(array)($post[$lid]??[]);$out[]=['language_id'=>$lid,'name'=>(string)$lang['name'],'code'=>(string)$lang['code'],'label'=>(string)($new['label']??$old['label']??''),'help'=>(string)($new['help']??$old['help']??''),'group_name'=>(string)($new['name']??$old['name']??'')];}
        return $out;
    }
    private function formBase(string $title,array $errors): array {
        $data=$this->labels(['heading_title','text_required','text_optional','text_private','text_public','entry_key','entry_name','entry_label','entry_help','entry_entity','entry_type','entry_group','entry_visibility','entry_required','entry_localized','entry_status','entry_sort_order','entry_options','button_save','button_cancel','text_enabled','text_disabled','text_languages_note','text_options_note','text_key_hint','text_translations','text_yes','text_no']);
        $data['title']=$this->language->get($title);$data['error']=implode("\n",$errors);$data['csrf_token']=\Reamur\System\Library\Security\Csrf::token($this->session);$data['can_modify']=$this->user->hasPermission('modify',self::ROUTE);return $data;
    }
    private function labels(array $keys): array { $d=[];foreach($keys as $k)$d[$k]=$this->language->get($k);return $d; }
    private function canModifyPost(): bool { if($this->isPost()&&!$this->user->hasPermission('modify',self::ROUTE)){$this->response->redirect($this->url->link('error/permission'));return false;}return true; }
    private function isPost(): bool { return ($this->request->server['REQUEST_METHOD']??'GET')==='POST'; }
    private function csrfValid(): bool { return \Reamur\System\Library\Security\Csrf::check($this->session,$this->request->post['csrf_token']??''); }
    private function flash(): array { $s=$this->session;$d=['type'=>'','text'=>''];if($s instanceof \Reamur\System\Library\Session&&!empty($s->data['flash'])){$d=(array)$s->data['flash'];unset($s->data['flash']);}return $d; }
    private function setFlash(string $text,string $type='success'): void { $s=$this->session;if($s instanceof \Reamur\System\Library\Session)$s->data['flash']=['type'=>$type,'text'=>$text]; }
    private function chrome(array &$data): void { $data['header']=$this->load->controller('common/header',$data);$data['footer']=$this->load->controller('common/footer',$data); }
}
