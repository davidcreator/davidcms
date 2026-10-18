<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * common/home — HOME PUBLICADA (F2-17, ADR 0017): one-page com seções reais
 * lidas do schema (portfólio, depoimentos, clientes, ferramentas) + seções
 * estáticas via language (sobre/serviços/skills/metodologia) + CTA de contato
 * e orçamento. Tema minimalista atual (common.css) — o design definitivo com
 * Three.js é Fase 3. Nada aqui inventa tabela: seções sem módulo ficam por
 * conta do idioma, nunca de DDL improvisada.
 */
namespace Reamur\Catalog\Controller\Common;

class Home extends \Reamur\System\Engine\Controller {

    /** Renderiza a one-page pública (200) */
    public function index(): void {
        $this->load->language('common/home');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->setDescription($this->language->get('text_tagline'));

        $base = $this->config->get('site_url', '');

        // Marca / hero
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_tagline']  = $this->language->get('text_tagline');
        $data['text_hero_lead']= $this->language->get('text_hero_lead');
        $data['button_portfolio'] = $this->language->get('button_portfolio');
        $data['button_contact']   = $this->language->get('button_contact');
        $data['button_quote']     = $this->language->get('button_quote');

        // Seções estáticas
        $data['text_about_title']  = $this->language->get('text_about_title');
        $data['text_about_lead']   = $this->language->get('text_about_lead');
        $data['text_about_body']   = $this->language->get('text_about_body');
        $data['text_services_title'] = $this->language->get('text_services_title');
        $data['text_services_body']  = $this->language->get('text_services_body');
        $data['text_skills_title'] = $this->language->get('text_skills_title');
        $data['text_skills_body']  = $this->language->get('text_skills_body');
        $data['text_method_title'] = $this->language->get('text_method_title');
        $data['text_method_body']  = $this->language->get('text_method_body');

        // Portfólio
        $data['text_portfolio_title'] = $this->language->get('text_portfolio_title');
        $data['text_portfolio_lead']  = $this->language->get('text_portfolio_lead');
        $data['text_view_all']        = $this->language->get('text_view_all');
        $data['text_featured']        = $this->language->get('text_featured');

        // Depoimentos / clientes / ferramentas
        $data['text_testimonials_title'] = $this->language->get('text_testimonials_title');
        $data['text_clients_title']      = $this->language->get('text_clients_title');
        $data['text_tools_title']        = $this->language->get('text_tools_title');
        $data['text_tools_lead']         = $this->language->get('text_tools_lead');

        // Contato (CTA para a página de formulário)
        $data['text_contact_title'] = $this->language->get('text_contact_title');
        $data['text_contact_lead']  = $this->language->get('text_contact_lead');
        $data['text_contact_address'] = $this->language->get('text_contact_address');
        $data['text_contact_email']   = $this->language->get('text_contact_email');
        $data['text_contact_whatsapp']= $this->language->get('text_contact_whatsapp');

        $data['quote']    = $this->url->link('checkout/quote');
        $data['contact']  = $this->url->link('common/contact');
        $data['portfolio']    = $this->url->link('catalog/project');
        $data['admin']        = $base . 'admin/';
        $data['button_admin'] = $this->language->get('button_admin');

        // Seções do schema (lazy — falha silenciosa não deve derrubar a home inteira? Não: se falhar, estoura — disciplina de erro honesta)
        $this->load->model('catalog/project');
        $this->load->model('catalog/category');
        $this->load->model('marketing/testimonial');
        $this->load->model('marketing/client_logo');
        $this->load->model('tool/tool');

        $data['categories'] = [];

        foreach ($this->model_catalog_category->getCategoriesWithProjects() as $cat) {
            $cat = (array)$cat;

            $data['categories'][] = [
                'name' => (string)$cat['name'],
                'href' => $this->url->link('catalog/project', ['category_id' => (int)$cat['category_id']]),
            ];
        }

        $data['projects'] = [];

        foreach ($this->model_catalog_project->getProjects(null) as $p) {
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

        $data['testimonials'] = [];

        foreach ($this->model_marketing_testimonial->getTestimonials() as $t) {
            $t = (array)$t;

            $data['testimonials'][] = [
                'author'  => (string)$t['author'],
                'company' => (string)$t['company'],
                'text'    => (string)$t['text'],
                'image'   => ((string)$t['image'] !== '') ? $base . (string)$t['image'] : '',
            ];
        }

        $data['client_logos'] = [];

        foreach ($this->model_marketing_client_logo->getClientLogos() as $c) {
            $c = (array)$c;

            $data['client_logos'][] = [
                'name'  => (string)$c['name'],
                'image' => ((string)$c['image'] !== '') ? $base . (string)$c['image'] : '',
                'url'   => (string)$c['url'],
            ];
        }

        $data['tools'] = [];

        foreach ($this->model_tool_tool->getTools() as $tool) {
            $tool = (array)$tool;

            $data['tools'][] = [
                'name'        => (string)$tool['name'],
                'vendor'      => (string)$tool['vendor'],
                'description' => (string)$tool['description'],
                'image'       => ((string)$tool['image'] !== '') ? $base . (string)$tool['image'] : '',
                'url'         => (string)$tool['url'],
            ];
        }

        // CMS do rodapé (cookies/políticas): NÃO seedado aqui — linka se houver keyword
        $this->document->addStyle($base . 'view/css/common.css');

        $data['body_class'] = 'rms-home';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);

        $this->response->setOutput($this->load->view('common/home', $data));
    }
}
