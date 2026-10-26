<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * cms/page catalog — active page with optional, explicitly public custom fields.
 */
namespace Reamur\Catalog\Controller\Cms;

class Page extends \Reamur\System\Engine\Controller {
    public function info(string $page_id = '0'): void {
        $page_id = (int)$page_id;
        $this->load->language('cms/page');
        $this->load->model('catalog/page');
        $page = $this->model_catalog_page->getPage($page_id);
        if (!$page) {
            (new \Reamur\System\Engine\Action('error/not_found'))->execute($this->registry);
            return;
        }

        $this->document->setTitle((string)($page['meta_title'] !== '' ? $page['meta_title'] : $page['title']));
        $this->document->setDescription((string)$page['meta_description']);
        $data['heading_title'] = (string)$page['title'];
        $storedDescription = (string)$page['description'];
        $blockPrefix = 'REAMURCMS_BDRE_JSON_V2:';
        if (str_starts_with($storedDescription, $blockPrefix)) {
            $json = substr($storedDescription, strlen($blockPrefix));
            $downloadRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/download/blog';
            $audioRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/audio/blog';
            $videoRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/video/blog';
            $assetBase = $this->config->get('site_url', '');
            if (str_ends_with($assetBase, 'admin/')) $assetBase = substr($assetBase, 0, -strlen('admin/'));
            $rendered = (new \Reamur\System\Library\Blog\BlockRenderer($downloadRoot, $audioRoot, $videoRoot))->renderJson($json, 2, $assetBase);
            $data['description'] = !empty($rendered['valid']) ? (string)$rendered['html'] : '';
            if (!empty($rendered['valid'])) $this->document->addStyle($this->config->get('site_url') . 'view/css/blog-blocks.css');
            if (!empty($rendered['requires_threejs'])) {
                $this->document->addStyle($this->config->get('site_url') . 'view/css/threejs-thumb.css?v=20261026c');
                $this->document->addScript($this->config->get('site_url') . 'view/js/threejs-thumb.js?v=20261026c');
            }
        } else {
            // Legacy HTML remains on its existing path; the marker is never sent to Twig.
            $data['description'] = $storedDescription;
        }
        $data['button_back_home'] = null;
        $data['heading_home'] = $this->language->get('heading_home');
        $data['home'] = $this->config->get('site_url', '');
        $data['text_custom_fields'] = $this->language->get('text_custom_fields');
        $data['text_yes'] = $this->language->get('text_yes');
        $data['text_no'] = $this->language->get('text_no');

        $this->load->model('custom_field/value');
        $groups=[];
        foreach($this->model_custom_field_value->getPagePublicFields($page_id) as $row){
            $row=(array)$row;$value=(string)($row['value_text']??'');$type=(string)($row['type']??'');
            if($value==='') continue;
            if($type==='boolean'){
                if(!in_array($value,['0','1'],true)) continue;
                $value=$value==='1'?$data['text_yes']:$data['text_no'];
            }elseif($type==='select'){
                $options=json_decode((string)($row['options']??'[]'),true);$display=null;
                foreach(is_array($options)?$options:[] as $option){$option=(array)$option;if((string)($option['value']??'')===$value){$display=(string)($option['label']??'');break;}}
                if($display===null || $display==='') continue;
                $value=$display;
            }elseif(!in_array($type,['text','textarea','number','url'],true)) continue;
            $value=mb_substr($value,0,5000);
            $groupId=(int)$row['group_id'];
            if(!isset($groups[$groupId])) $groups[$groupId]=['label'=>(string)$row['group_label'],'fields'=>[]];
            $groups[$groupId]['fields'][]=['label'=>(string)$row['label'],'value'=>$value,'type'=>$type];
        }
        $data['public_custom_field_groups']=array_values($groups);

        $this->document->addStyle($this->config->get('site_url') . 'view/css/common.css');
        $data['body_class']='rms-page';
        $data['header']=$this->load->controller('common/header',$data);
        $data['footer']=$this->load->controller('common/footer',$data);
        $this->response->setOutput($this->load->view('cms/page_info',$data));
    }
}
