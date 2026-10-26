<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * common/home — home composta de partials (F3-01, ADR 0019).
 * Hero estático por enquanto; a cena 3D com Three.js entra na F3-02.
 * Conteúdo real vem do schema (seed upgrade_f3_01) + language (textos da
 * produção). Nenhuma regra inventa tabela não prevista no schema.
 */
namespace Reamur\Catalog\Controller\Common;

class Home extends \Reamur\System\Engine\Controller {

    /** Renderiza a one-page pública (200) */
    public function index(): void {
        $this->load->language('common/home');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->setDescription($this->language->get('text_tagline'));

        $base = rtrim((string)$this->config->get('site_url', ''), '/') . '/';

        // ── Textos estáticos (language × 3) ─────────────────────────────
        // F4-03 (ADR 0026): institucional do banco > lang file (degradação
        // silenciosa ADR 0023). Chave existente governa; ausência cai no idioma.
        $this->load->model('setting/setting');

        $siteInfo = $this->model_setting_setting->getSettings();
        $langCode = (string)($this->session->data['language'] ?? 'en-us');

        $addrKey = 'config_contact_address_' . $langCode;

        $siteAddress      = array_key_exists($addrKey, $siteInfo)
            ? (string)$siteInfo[$addrKey]
            : $this->language->get('text_contact_address');
        $siteEmail        = array_key_exists('config_contact_email', $siteInfo)
            ? (string)$siteInfo['config_contact_email']
            : $this->language->get('text_contact_email');
        $siteWhatsappText = array_key_exists('config_contact_whatsapp_text', $siteInfo)
            ? (string)$siteInfo['config_contact_whatsapp_text']
            : $this->language->get('text_contact_whatsapp');
        $siteWhatsDigits  = (string)($siteInfo['config_contact_whatsapp_href'] ?? '');
        $siteWhatsappHref = $siteWhatsDigits !== '' ? 'https://wa.me/' . $siteWhatsDigits : '';

        $threeJs = $this->three_scene_config->runtime($siteInfo);

        // Cena 2: o modelo do Hero é um asset da allowlist do runtime. Quando
        // não está publicada, a cena segue sendo apenas o campo de partículas.
        $heroModel = $this->three_asset_manifest->heroModelUrl();

        $data = [
            // Configuração allowlisted do system/library/three/scene_config.php.
            // Sem script arbitrário; o header só carrega WebGL quando habilitado.
            'has_3d'     => $threeJs['hero_enabled'],
            'three_runtime_base' => $base . $this->three_asset_manifest->publicBasePath(),
            'hero3d'     => $threeJs['hero'] + ['model' => $heroModel !== null ? $base . $heroModel : ''],

            'heading_title'     => $this->language->get('heading_title'),
            'text_tagline'      => $this->language->get('text_tagline'),
            'text_hero_lead'    => $this->language->get('text_hero_lead'),
            'text_hero_kicker'  => $this->language->get('text_hero_kicker'),
            'button_portfolio'  => $this->language->get('button_portfolio'),
            'button_contact'    => $this->language->get('button_contact'),
            'button_quote'      => $this->language->get('button_quote'),
            'button_admin'      => $this->language->get('button_admin'),

            'text_about_kicker' => $this->language->get('text_about_kicker'),
            'text_about_title'  => $this->language->get('text_about_title'),
            'text_about_lead'   => $this->language->get('text_about_lead'),
            'text_about_focus'  => $this->language->get('text_about_focus'),
            'text_about_focus_body' => $this->language->get('text_about_focus_body'),

            'text_method_kicker' => $this->language->get('text_method_kicker'),
            'text_method_title'  => $this->language->get('text_method_title'),
            'text_method_lead'   => $this->language->get('text_method_lead'),

            'text_identity_title' => $this->language->get('text_identity_title'),

            'text_skills_kicker' => $this->language->get('text_skills_kicker'),
            'text_skills_title'  => $this->language->get('text_skills_title'),
            'text_skills_lead'   => $this->language->get('text_skills_lead'),

            'text_services_kicker' => $this->language->get('text_services_kicker'),
            'text_services_title'  => $this->language->get('text_services_title'),
            'text_services_lead'   => $this->language->get('text_services_lead'),
            'text_services_cta'    => $this->language->get('text_services_cta'),

            'text_portfolio_kicker' => $this->language->get('text_portfolio_kicker'),
            'text_portfolio_title'  => $this->language->get('text_portfolio_title'),
            'text_portfolio_lead'   => $this->language->get('text_portfolio_lead'),
            'text_all'              => $this->language->get('text_all'),
            'text_filter'           => $this->language->get('text_filter'),
            'text_featured'         => $this->language->get('text_featured'),
            'text_no_results'       => $this->language->get('text_no_results'),

            'text_testimonials_kicker' => $this->language->get('text_testimonials_kicker'),
            'text_testimonials_title'  => $this->language->get('text_testimonials_title'),
            'text_clients_kicker'      => $this->language->get('text_clients_kicker'),
            'text_clients_title'       => $this->language->get('text_clients_title'),
            'text_tools_kicker'        => $this->language->get('text_tools_kicker'),
            'text_tools_title'         => $this->language->get('text_tools_title'),
            'text_tools_lead'          => $this->language->get('text_tools_lead'),
            'text_tool_open'           => $this->language->get('text_tool_open'),

            'text_contact_kicker'   => $this->language->get('text_contact_kicker'),
            'text_contact_title'    => $this->language->get('text_contact_title'),
            'text_contact_lead'     => $this->language->get('text_contact_lead'),
            'text_contact_address'  => $siteAddress,
            'text_contact_email'    => $siteEmail,
            'text_contact_whatsapp' => $siteWhatsappText,
            'contact_whatsapp_href' => $siteWhatsappHref,

            'text_news_title'     => $this->language->get('text_news_title'),
            'text_news_kicker'    => $this->language->get('text_news_kicker'),
            'text_news_read_more' => $this->language->get('text_news_read_more'),
            'text_news_source'    => $this->language->get('text_news_source'),

            'text_socials_title' => $this->language->get('text_socials_title'),
        ];

        // ── Links de destino ────────────────────────────────────────────
        $data['quote']     = $this->url->link('checkout/quote');
        $data['contact']   = $this->url->link('common/contact');
        $data['portfolio'] = $this->url->link('catalog/project');
        $data['admin']     = $base . 'admin/';

        // ── Conteúdo do schema (todas as seções dinâmicas) ──────────────
        $this->load->model('catalog/project');
        $this->load->model('catalog/category');
        $this->load->model('marketing/testimonial');
        $this->load->model('marketing/client_logo');
        $this->load->model('tool/tool');

        // Filtro ativo via ?category_id= (o router resolve o keyword para
        // catalog/project.info|N; a grade ouve o id direto da requisição)
        $active = (int)($this->request->get['category_id'] ?? 0);

        $data['active_category'] = $active;

        $data['categories'] = [];

        foreach ($this->model_catalog_category->getCategoriesWithProjects() as $cat) {
            $cat = (array)$cat;

            $data['categories'][] = [
                'category_id' => (int)$cat['category_id'],
                'name'        => (string)$cat['name'],
                'href'        => $this->url->link('catalog/project', ['category_id' => (int)$cat['category_id']]),
            ];
        }

        $data['projects'] = [];

        foreach ($this->model_catalog_project->getProjects($active ?: null) as $p) {
            $p = (array)$p;

            $data['projects'][] = [
                'name'     => (string)$p['name'],
                'summary'  => (string)$p['summary'],
                'client'   => (string)$p['client'],
                'featured' => (int)$p['featured'],
                'image'    => ((string)$p['image'] !== '') ? $base . (string)$p['image'] : '',
                'href'     => $this->projectHref($p),
                'external' => ((string)$p['url'] !== '') ? 1 : 0,
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
                'href'        => $this->url->link('tool/tool.info|' . (int)$tool['tool_id']), // página dedicada (F3-04)
            ];
        }

        // ── Latest News: blog INTERNO (F7-02, ADR 0043 — feed WP externo aposentado) ──
        $this->load->model('blog/blog');

        $data['news_items'] = [];

        foreach ($this->model_blog_blog->getPosts(3) as $news_post) {
            $news_post = (array)$news_post;

            $news_plain = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string)$news_post['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');

            $data['news_items'][] = [
                'title'   => (string)$news_post['title'],
                'url'     => $this->url->link('blog/blog.info|' . (int)$news_post['post_id']),
                'date'    => date('Y-m-d', (int)strtotime((string)$news_post['date_published'])),
                'excerpt' => mb_strlen($news_plain) > 160 ? mb_strimwidth($news_plain, 0, 160, '…', 'UTF-8') : $news_plain,
                'source'  => 'Blog',
            ];
        }

        $data['news_blog_href'] = $this->url->link('blog/blog');

        // ── Conteúdo estático estruturado do language (partials) ────────
        $data['about_paragraphs'] = [
            $this->language->get('text_about_p1'),
            $this->language->get('text_about_p2'),
            $this->language->get('text_about_p3'),
        ];

        $data['skill_chips'] = [
            'Developer', 'Brand Design', 'Visual Identity',
            'Product Design', 'Illustrations', 'Brand Action',
        ];

        $data['skill_groups'] = [
            [
                'title' => $this->language->get('text_skill_tech'),
                'items' => array_values(array_filter([
                    'HTML', 'CSS', 'PHP', 'JavaScript', 'jQuery',
                ])),
            ],
            [
                'title' => $this->language->get('text_skill_platforms'),
                'items' => ['Wordpress', 'Prestashop', 'Joomla', 'Blogger', 'Winter'],
            ],
            [
                'title' => $this->language->get('text_skill_programs'),
                'items' => ['VS Code', 'Illustrator', 'Photoshop', 'CorelDRAW', 'Figma'],
            ],
            [
                'title' => $this->language->get('text_skill_scm'),
                'items' => ['GitHub', 'GitLab'],
            ],
        ];

        $data['method_steps'] = [];

        for ($i = 1; $i <= 6; $i++) {
            $data['method_steps'][] = [
                'n'     => $i,
                'title' => $this->language->get('text_method_' . $i . '_title'),
                'text'  => $this->language->get('text_method_' . $i . '_text'),
            ];
        }

        $data['identity_reasons'] = [];

        foreach (['unity', 'credibility', 'attraction'] as $reason) {
            $data['identity_reasons'][] = [
                'title' => $this->language->get('text_identity_' . $reason . '_title'),
                'text'  => $this->language->get('text_identity_' . $reason . '_text'),
            ];
        }

        $data['service_groups'] = [
            [
                'title' => $this->language->get('text_service_gd_title'),
                'items' => explode('|', $this->language->get('text_service_gd_items')),
            ],
            [
                'title' => $this->language->get('text_service_wd_title'),
                'items' => explode('|', $this->language->get('text_service_wd_items')),
            ],
            [
                'title' => $this->language->get('text_service_seo_title'),
                'items' => explode('|', $this->language->get('text_service_seo_items')),
            ],
        ];

        $data['socials'] = [];

        foreach (['facebook', 'instagram', 'pinterest', 'x', 'google_business', 'redbubble'] as $net) {
            $key  = 'config_social_' . $net . '_href';
            $href = array_key_exists($key, $siteInfo)
                ? (string)$siteInfo[$key]
                : (string)$this->language->get('text_social_' . $net . '_href');

            if ($href !== '') {
                $data['socials'][] = [
                    'name' => $this->language->get('text_social_' . $net),
                    'href' => $href,
                ];
            }
        }

        // Chrome compartilhado (header + rodapé) — template agora é o tema
        // da home composta (F3-01), o que permite os partials do guia.
        $data['header'] = $this->load->controller('common/header', $data);
        $data['footer'] = $this->load->controller('common/footer', $data);

        $this->response->setOutput($this->load->view('common/home', $data));
    }

    /**
     * href do card de projeto: keyword limpa quando existir (router reescreve),
     * senão o link externo real (target=_blank no partial).
     *
     * @param array<string, mixed> $p
     */
    private function projectHref(array $p): string {
        // Card externo quando o projeto tem URL real cadastrada (Hiperion Gamer
        // abre o site dele — mesma regra da produção); senão a página interna.
        if ((string)($p['url'] ?? '') !== '') {
            return (string)$p['url'];
        }

        return $this->url->link('catalog/project.info|' . (int)$p['project_id']);
    }
}
