<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Bloco compartilhado common/header (F2-4): <head> honesto (lang dinâmico,
 * título do document e styles do serviço document) + barra de marca com
 * switcher de idiomas (?language= — Adendo F2-3 do ADR 0001).
 * Chamado como sub-controller via $this->load->controller('common/header', $data)
 * e devolve HTML renderizado (padrão declarado em Loader::controller).
 */
namespace Reamur\Catalog\Controller\Common;

class Header extends \Reamur\System\Engine\Controller {

    /**
     * @param array<string, mixed> $data Dados passados pelo controller chamador
     * @return string HTML renderizado
     */
    public function index(array $data = []): string {
        $this->load->language('common/header');

        $data['cms_route_key'] = $this->routeContentKey((string)$this->config->get('_route', ''));
        $data['cms_route_content'] = $data['cms_route_key'] !== '' && $data['cms_route_key'] !== 'home'
            ? (string)$this->load->controller('cms/slot.render', $data['cms_route_key'])
            : '';

        $data['locale'] = (string)$this->config->get('language_directory', 'en-us');
        // F4-01 (ADR 0024): sem título/descrição explícita da página,
        // o fallback é o que o admin gravou em setting/setting (rms_setting).
        $title       = $this->document->getTitle();
        $description = $this->document->getDescription();

        $data['title']       = $title       !== '' ? $title       : (string)$this->config->get('config_meta_title', '');
        $data['description'] = $description !== '' ? $description : (string)$this->config->get('config_meta_description', '');
        $data['styles']      = $this->document->getStyles();
        $data['scripts']     = $this->document->getScripts();

        $data['home']           = (string)$this->config->get('site_url');
        $data['text_languages'] = $this->language->get('text_languages');
        $data['body_class']     = (string)($data['body_class'] ?? '');
        $data['theme_primary'] = (string)$this->config->get('catalog_theme_primary', '#6d28d9');
        $data['theme_surface'] = (string)$this->config->get('catalog_theme_surface', '#ffffff');

        // F4-07 (ADR 0031): canonical + Open Graph/Twitter + descoberta do sitemap.
        // Regra honesta: canonical/og:url SÓ quando a requisição chegou por caminho
        // limpo — com ?route= o canonical derivado ficaria errado e canonical
        // errado é pior que nenhum. og:title/description sempre (já com fallback).
        $origin    = $this->requestOrigin();
        $path      = (string)(parse_url((string)($this->request->server['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        $isClean   = $path !== '' && !str_ends_with(strtolower($path), 'index.php');
        $canonical = $isClean ? $origin . $path : '';

        $data['canonical']    = $canonical;
        $data['sitemap_href'] = $this->url->link('common/sitemap');
        $data['og'] = [
            'type'        => 'website',
            'site_name'   => (string)$this->config->get('config_name', ''),
            'title'       => $data['title'],
            'description' => $data['description'],
            'url'         => $canonical,
            'locale'      => str_replace('-', '_', (string)$data['locale']),
        ];

        // Tema base (F3-01, ADR 0019): folha própria do site publicado
        $this->document->addStyle(rtrim($data['home'], '/') . '/view/css/theme.css');
        $data['styles'] = $this->document->getStyles();

        // Marca + navegação da publicação (F2-17): idioma é a fonte de verdade
        $data['text_brand']        = $this->language->get('text_brand');
        $data['text_nav_home']     = $this->language->get('text_nav_home');
        $data['text_nav_about']    = $this->language->get('text_nav_about');
        $data['text_nav_blog']     = $this->language->get('text_nav_blog');
        $data['text_nav_portfolio']= $this->language->get('text_nav_portfolio');
        $data['text_nav_contact']  = $this->language->get('text_nav_contact');
        $data['text_nav_quote']    = $this->language->get('text_nav_quote');

        $base = rtrim($data['home'], '/');

        $data['nav_about']    = $base . '/#about';
        $data['nav_blog']     = $this->url->link('blog/blog');
        $data['nav_portfolio']= $this->url->link('catalog/project');
        $data['nav_contact']  = $this->url->link('common/contact');
        $data['nav_quote']    = $this->url->link('checkout/quote');

        // Switcher: diretórios que EXISTEM são as opções (fonte da verdade = filesystem)
        $args  = $this->request->get;
        $route = (string)($args['route'] ?? '');

        unset($args['route'], $args['language']);

        if ($route === '') {
            $route = (string)$this->config->get('action_default', 'common/home');
        }

        $data['languages'] = [];

        // F4-02 (ADR 0025): switcher mostra só idiomas ATIVOS (rms_language),
        // na ordem definida pelo painel (sort_order) — ainda cruzando com o
        // diretório real no filesystem.
        $this->load->model('localization/language');

        foreach ($this->model_localization_language->getEnabledCodes() as $code) {
            $langArgs        = $args;
            $langArgs['language'] = $code;

            $data['languages'][] = [
                'code'   => $code,
                'name'   => $this->language->get('text_lang_' . str_replace('-', '_', $code)),
                'active' => $code === $data['locale'],
                'href'   => $this->url->link($route, $langArgs),
            ];
        }

        return $this->load->view('common/header', $data);
    }

    /** Mapeia rotas públicas para as chaves de conteúdo fixas do CMS. */
    private function routeContentKey(string $route): string {
        $baseRoute = explode('|', trim($route), 2)[0];
        return match ($baseRoute) {
            'common/home' => 'home',
            'catalog/project' => 'portfolio',
            'catalog/project.info' => 'portfolio_detail',
            'blog/blog' => 'blog',
            'blog/blog.info' => 'blog_post',
            'tool/tool' => 'tools',
            'tool/tool.info' => 'tool_detail',
            'common/contact' => 'contact',
            'checkout/quote' => 'quote',
            'error/not_found' => 'not_found',
            default => '',
        };
    }

    /**
     * Origem scheme+host da publicação. Fonte = site_url gravado pelo wizard
     * (honra o deploy real, inclusive porta — localhost:8090 nas baterias);
     * NUNCA o header Host cru (preview/proxy pode mascarar).
     */
    private function requestOrigin(): string {
        $site   = (string)$this->config->get('site_url', '');
        $scheme = (string)(parse_url($site, PHP_URL_SCHEME) ?: 'http');
        $host   = (string)(parse_url($site, PHP_URL_HOST) ?: 'localhost');
        $port   = parse_url($site, PHP_URL_PORT);

        return $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
    }
}
