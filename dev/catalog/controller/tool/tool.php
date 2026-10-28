<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * tool/tool (CATALOG) — páginas públicas das ferramentas conectadas (F3-04):
 *  · index    → grade como a seção da home, agora com link p/ página própria;
 *  · info|N   → página dedicada (SEO por idioma, keyword amigável /fluxa …);
 *               inexistente/inativa → 404 honesto (error/not_found).
 * A URL externa (t.url) só é exibida quando preenchida — enquanto vazia, a
 * página interna é a referência pública (produção usa /tools/open?id=N).
 */
namespace Reamur\Catalog\Controller\Tool;

class Tool extends \Reamur\System\Engine\Controller {

    /** Grade pública de ferramentas conectadas ativas */
    public function index(): void {
        $this->load->language('tool/tool');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->setDescription($this->language->get('text_lead'));

        $this->load->model('tool/tool');

        $base = rtrim((string)$this->config->get('site_url', ''), '/');

        $data['heading_title']  = $this->language->get('heading_title');
        $data['text_lead']      = $this->language->get('text_lead');
        $data['text_no_results']= $this->language->get('text_no_results');
        $data['button_view']    = $this->language->get('button_view');
        $data['button_back_home']= $this->language->get('button_back_home');
        $data['text_open']      = $this->language->get('text_open');

        $data['tools'] = [];

        foreach ($this->model_tool_tool->getTools() as $tool) {
            $tool = (array)$tool;

            $data['tools'][] = $this->hydrate($tool, $base);
        }

        $data['home'] = $base . '/';

        $data['cms_page_content'] = (string)$this->load->controller('cms/slot.render', 'tools');
        $data['cms_page_sections'] = (array)$this->load->controller('cms/slot.sections', 'tools');

        $this->chrome($data, 'rms-tools');

        $this->response->setOutput($this->load->view('tool/tool_list', $data));
    }

    /** Página dedicada de uma ferramenta (keyword seo_url resolvida pelo router) */
    public function info(string $tool_id = '0'): void {
        $tool_id = (int)$tool_id;

        $this->load->language('tool/tool');

        $this->load->model('tool/tool');

        $tool = $this->model_tool_tool->getTool($tool_id);

        if (!$tool) {
            $action = new \Reamur\System\Engine\Action('error/not_found');

            $action->execute($this->registry);

            return;
        }

        $base = rtrim((string)$this->config->get('site_url', ''), '/');

        $name = (string)$tool['name'];

        $this->document->setTitle($name . ' — ' . (string)$tool['vendor']);
        $this->document->setDescription((string)$tool['description']);

        $data['heading_title']   = $name;
        $data['text_lead']       = (string)$tool['description'];
        $data['entry_vendor']    = $this->language->get('entry_vendor');
        $data['entry_external_url'] = $this->language->get('entry_external_url');
        $data['text_open_hint']  = $this->language->get('text_open_hint');
        $data['text_open']       = $this->language->get('text_open');
        $data['button_back']     = $this->language->get('button_back');
        $data['button_contact']  = $this->language->get('button_contact');

        $data['tool'] = $this->hydrate($tool, $base);

        $data['back_tools'] = $this->url->link('tool/tool');
        $data['contact']    = $this->url->link('common/contact');

        $data['cms_page_content'] = (string)$this->load->controller('cms/slot.render', 'tool_detail');
        $data['cms_page_sections'] = (array)$this->load->controller('cms/slot.sections', 'tool_detail');

        $this->chrome($data, 'rms-tool');

        $this->response->setOutput($this->load->view('tool/tool_info', $data));
    }

    /**
     * Hidrata 1 linha do model com links e imagem absoluta.
     * href → página dedicada via seo_url (keyword /fluxa etc.);
     * external/url só aparecem quando a URL real foi preenchida no admin.
     *
     * @param array<string, mixed> $tool
     * @return array<string, mixed>
     */
    private function hydrate(array $tool, string $base): array {
        return [
            'tool_id'     => (int)$tool['tool_id'],
            'name'        => (string)$tool['name'],
            'vendor'      => (string)$tool['vendor'],
            'description' => (string)$tool['description'],
            'image'       => ((string)$tool['image'] !== '') ? $base . '/' . (string)$tool['image'] : '',
            'url'         => (string)$tool['url'],
            'external'    => ((string)$tool['url'] !== ''),
            'href'        => $this->url->link('tool/tool.info|' . (int)$tool['tool_id']),
        ];
    }

    /** Chrome compartilhado (mesmo padrão F2-4/F3-01) */
    private function chrome(array &$data, string $body_class): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/common.css');

        $data['body_class'] = $body_class;
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
