<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/project (CATALOG) — portfólio público (F2-17):
 *  · index      → grade de projetos ATIVOS (+ filtro opcional ?category_id=N)
 *  · info|N     → página dedicada do projeto (galeria, meta por idioma);
 *                 inexistente/inativo → 404 honesto.
 * Links SAEM amigáveis via seo_url (rewrite do startup/router, ADR 0017).
 */
namespace Reamur\Catalog\Controller\Catalog;

class Project extends \Reamur\System\Engine\Controller {

    /** Grade pública (todos ou por categoria ativa) */
    public function index(): void {
        $this->load->language('catalog/project');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/project');
        $this->load->model('catalog/category');

        $category_id = (int)($this->request->get['category_id'] ?? 0);

        $data['heading_title']      = $this->language->get('heading_title');
        $data['text_lead']          = $this->language->get('text_lead');
        $data['text_no_results']    = $this->language->get('text_no_results');
        $data['text_all']           = $this->language->get('text_all');
        $data['text_filter']        = $this->language->get('text_filter');
        $data['button_view']        = $this->language->get('button_view');
        $data['button_back_home']   = $this->language->get('button_back_home');
        $data['text_featured']      = $this->language->get('text_featured');

        $base = $this->config->get('site_url', '');

        $data['categories'] = [];

        foreach ($this->model_catalog_category->getCategoriesWithProjects() as $cat) {
            $cat = (array)$cat;

            $data['categories'][] = [
                'name'   => (string)$cat['name'],
                'href'   => $this->url->link('catalog/project', ['category_id' => (int)$cat['category_id']]),
                'active' => ($category_id === (int)$cat['category_id']),
            ];
        }

        $data['filter_all'] = [
            'href'   => $this->url->link('catalog/project'),
            'active' => ($category_id === 0),
        ];

        $data['projects'] = [];

        foreach ($this->model_catalog_project->getProjects($category_id > 0 ? $category_id : null) as $p) {
            $p = (array)$p;

            $data['projects'][] = [
                'name'     => (string)$p['name'],
                'summary'  => (string)$p['summary'],
                'client'   => (string)$p['client'],
                'featured' => (int)$p['featured'],
                'image'    => ((string)$p['image'] !== '') ? $base . (string)$p['image'] : '',
                'href'     => $this->url->link('catalog/project.info|' . (int)$p['project_id']),
            ];
        }

        $data['home'] = $base;

        $data['cms_page_content'] = (string)$this->load->controller('cms/slot.render', 'portfolio');
        $data['cms_page_sections'] = (array)$this->load->controller('cms/slot.sections', 'portfolio');

        $this->chrome($data, 'rms-portfolio');

        $this->response->setOutput($this->load->view('catalog/project_list', $data));
    }

    /** Página dedicada (via ?route= ou keyword seo_url resolvida pelo router) */
    public function info(string $project_id = '0'): void {
        $project_id = (int)$project_id;

        $this->load->language('catalog/project');

        $this->load->model('catalog/project');

        $project = $this->model_catalog_project->getProject($project_id);

        if (!$project) {
            // 404 honesto: manda para a rota de erro da app (ela já marca o status)
            $action = new \Reamur\System\Engine\Action('error/not_found');

            $action->execute($this->registry);

            return;
        }

        $name = (string)($project['name'] !== '' ? $project['name'] : $this->language->get('heading_title'));

        $this->document->setTitle((string)($project['meta_title'] !== '' ? $project['meta_title'] : $name));
        $this->document->setDescription((string)$project['meta_description']);

        $base = $this->config->get('site_url', '');

        $data['heading_title'] = $name;
        $data['text_summary']  = (string)$project['summary'];
        $data['text_tag']      = (string)$project['tag'];
        $data['entry_client']  = $this->language->get('entry_client');
        $data['entry_url']     = $this->language->get('entry_url');
        $data['entry_tags']    = $this->language->get('entry_tags');
        $data['entry_gallery'] = $this->language->get('entry_gallery');
        $data['button_back']   = $this->language->get('button_back');

        $data['project'] = [
            'name'        => $name,
            'summary'     => (string)$project['summary'],
            'description' => nl2br((string)$project['description'], false),
            'client'      => (string)$project['client'],
            'url'         => (string)$project['url'],
            'image'       => ((string)$project['image'] !== '') ? $base . (string)$project['image'] : '',
        ];

        $data['categories'] = [];

        foreach ($this->model_catalog_project->getProjectCategories($project_id) as $cat) {
            $cat = (array)$cat;

            $data['categories'][] = [
                'name' => (string)$cat['name'],
                'href' => $this->url->link('catalog/project', ['category_id' => (int)$cat['category_id']]),
            ];
        }

        $data['images'] = [];

        foreach ($this->model_catalog_project->getProjectImages($project_id) as $img) {
            $img = (array)$img;

            $src = $base . (string)$img['image'];

            $data['images'][] = [
                'src' => $src,
                'alt' => (string)$img['alt'],
            ];
        }

        $data['back_portfolio'] = $this->url->link('catalog/project');

        $data['cms_page_content'] = (string)$this->load->controller('cms/slot.render', 'portfolio_detail');
        $data['cms_page_sections'] = (array)$this->load->controller('cms/slot.sections', 'portfolio_detail');

        $this->chrome($data, 'rms-project');

        $this->response->setOutput($this->load->view('catalog/project_info', $data));
    }

    /** Chrome compartilhado (mesmo padrão da home F2-4) */
    private function chrome(array &$data, string $body_class): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/common.css');

        $data['body_class'] = $body_class;
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
