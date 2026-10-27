<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Bloco compartilhado common/header do ADMIN (F2-4 → F8-01, ADR 0045):
 * shell do painel (sidebar + topbar) com menu ACL-driven montado aqui,
 * logo oficial, toggle dark/light, switcher de idiomas e dropdown do usuário.
 *
 * Estrutura aberta em header.twig é fechada em footer.twig (mesma convenção
 * dos controllers de F2-4 — os dois twigs são um par indissociável).
 */
namespace Reamur\Admin\Controller\Common;

class Header extends \Reamur\System\Engine\Controller {

    /** Versão dos assets self-hosted (cache-bust por query string) */
    public const ASSET_V = '8.1.2';

    /**
     * @param array<string, mixed> $data Dados passados pelo controller chamador
     * @return string HTML renderizado
     */
    public function index(array $data = []): string {
        $this->load->language('common/header');

        $data['locale'] = (string)$this->config->get('language_directory', 'en-us');
        $data['title']  = $this->document->getTitle();
        $data['styles']  = $this->document->getStyles();
        $data['scripts'] = $this->document->getScripts();

        $data['home']           = (string)$this->config->get('site_url');
        $data['site_url']       = (string)$this->config->get('site_url');
        $data['asset_v']        = self::ASSET_V;
        $data['text_languages'] = $this->language->get('text_languages');
        $data['body_class']     = (string)($data['body_class'] ?? '');

        $data['logged']        = $this->user instanceof \Reamur\System\Library\User && $this->user->isLogged();
        $data['logout']        = $this->url->link('common/logout');
        $data['button_logout'] = $this->language->get('button_logout');

        // Rota corrente — drive do estado ativo do menu e do switcher de idiomas
        $args  = $this->request->get;
        $route = (string)($args['route'] ?? '');

        unset($args['route'], $args['language']);

        if ($route === '') {
            $route = (string)$this->config->get('action_default', 'common/dashboard');
        }

        // idempotente: switcher mantém a rota e os args atuais
        $data['languages'] = [];

        foreach ((array)glob(DIR_APPLICATION . 'language/*', GLOB_ONLYDIR) as $dir) {
            $code                 = basename((string)$dir);
            $langArgs             = $args;
            $langArgs['language'] = $code;

            $data['languages'][] = [
                'code'   => $code,
                'name'   => $this->language->get('text_lang_' . str_replace('-', '_', $code)),
                'active' => $code === $data['locale'],
                'href'   => $this->url->link($route, $langArgs),
            ];
        }

        // ── F8-01 (ADR 0045): menu lateral ACL-driven ──
        $data['menu'] = $data['logged'] ? $this->buildMenu((string)$route) : [];

        $data['text_menu']         = $this->language->get('text_menu');
        $data['text_toggle_menu']  = $this->language->get('text_toggle_menu');
        $data['text_toggle_theme'] = $this->language->get('text_toggle_theme');
        $data['text_user_menu']    = $this->language->get('text_user_menu');
        $data['text_profile']      = $this->language->get('text_profile');
        $data['text_appearance']   = $this->language->get('text_appearance');
        $data['appearance_href']   = $this->url->link('setting/appearance');

        $data['user_fullname'] = $data['logged'] ? ($this->user->getFullname() ?: $this->user->getUsername()) : '';
        $data['user_username'] = $data['logged'] ? $this->user->getUsername() : '';
        $data['user_initials'] = $this->initials($data['user_fullname']);
        $data['profile_href']  = $this->url->link('user/profile');
        $data['version_label'] = sprintf($this->language->get('text_version_label'), REAMUR_VERSION);

        // ── F8-V (ADR 0047): tema efetivo (override do usuário > padrão global > defaults) ──
        $this->load->model('setting/appearance');

        $prefs = ($data['logged'] && method_exists($this->user, 'getThemePrefs'))
            ? $this->user->getThemePrefs()
            : [];

        $theme = $this->model_setting_appearance->resolveTheme($prefs);

        // override só conta se o usuário de fato personalizou algo (flag explícita)
        if (empty($prefs['override'])) {
            $theme = $this->model_setting_appearance->resolveTheme([]);
        }

        $data['theme_mode']       = $theme['mode'];
        $data['theme_skin']       = $theme['skin'];
        $data['theme_sidebar']    = $theme['sidebar'];
        $data['theme_density']    = $theme['density'];
        $data['theme_font_scale'] = $theme['font_scale'];

        // acento efetivo (par light/dark; '' = o da skin) com contraste computado
        $data['theme_accent_light'] = $theme['accent'];
        $data['theme_accent_dark']  = $theme['accent_dark'] !== '' ? $theme['accent_dark'] : $theme['accent'];
        $data['theme_accent_light_contrast'] = $theme['accent'] !== ''
            ? $this->model_setting_appearance->bestContrastOn($theme['accent'])['contrast'] : '';
        $data['theme_accent_dark_contrast'] = $data['theme_accent_dark'] !== ''
            ? $this->model_setting_appearance->bestContrastOn($data['theme_accent_dark'])['contrast'] : '';

        // endpoint AJAX do toggle salva o AND csrf p/ chamadas no shell
        $data['csrf_token']      = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['appearance_mode_url'] = $this->url->link('setting/appearance.saveMode');

        return $this->load->view('common/header', $data);
    }

    /**
     * Monta o menu lateral a partir das rotas REAIS (controllers existentes):
     * cada item só aparece com hasPermission('access', rota) — grupo some
     * quando todos os itens são negados. Sem placeholders de módulos
     * inexistentes (ADR 0045 §menu).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildMenu(string $current_route): array {
        $groups = [
            [
                'title' => $this->language->get('text_group_main'),
                'items' => [
                    ['route' => 'common/dashboard', 'icon' => 'bi bi-speedometer2', 'key' => 'text_menu_dashboard'],
                ],
            ],
            [
                'title' => $this->language->get('text_group_pages'),
                'items' => [
                    ['route' => 'cms/page', 'icon' => 'bi bi-file-earmark-text', 'key' => 'text_menu_pages'],
                ],
            ],
            [
                'title' => $this->language->get('text_group_blog'),
                'items' => [
                    ['route' => 'blog/blog', 'icon' => 'bi bi-journal-text', 'key' => 'text_menu_posts'],
                ],
            ],
            [
                'title' => $this->language->get('text_group_content'),
                'items' => [
                    ['route' => 'custom_field/definition', 'icon' => 'bi bi-ui-checks-grid', 'key' => 'text_menu_custom_fields'],
                    ['route' => 'tool/tool', 'icon' => 'bi bi-tools', 'key' => 'text_menu_tools'],
                ],
            ],
            [
                'title' => $this->language->get('text_group_catalog'),
                'items' => [
                    ['route' => 'catalog/project',    'icon' => 'bi bi-briefcase',         'key' => 'text_menu_projects'],
                    ['route' => 'catalog/category',   'icon' => 'bi bi-folder2',           'key' => 'text_menu_categories'],
                ],
            ],
            [
                'title' => $this->language->get('text_group_sales'),
                'items' => [
                    ['route' => 'sale/order',         'icon' => 'bi bi-cart3',             'key' => 'text_menu_orders'],
                ],
            ],
            [
                'title' => $this->language->get('text_group_marketing'),
                'items' => [
                    ['route' => 'marketing/testimonial',  'icon' => 'bi bi-chat-quote',    'key' => 'text_menu_testimonials'],
                    ['route' => 'marketing/client_logo',  'icon' => 'bi bi-patch-check',   'key' => 'text_menu_client_logos'],
                ],
            ],
            [
                'title' => $this->language->get('text_group_communication'),
                'items' => [
                    ['route' => 'communication/contact_message', 'icon' => 'bi bi-chat-dots', 'key' => 'text_menu_messages'],
                ],
            ],
            [
                'title' => $this->language->get('text_group_extensions'),
                'items' => [
                    ['route' => 'setting/marketplace', 'icon' => 'bi bi-shop',            'key' => 'text_menu_marketplace'],
                ],
            ],
            [
                'title' => $this->language->get('text_group_system'),
                'items' => [
                    ['route' => 'setting/setting',      'icon' => 'bi bi-gear',           'key' => 'text_menu_settings'],
                    ['route' => 'setting/appearance',   'icon' => 'bi bi-palette2',       'key' => 'text_menu_appearance'],
                    ['route' => 'setting/site_info',    'icon' => 'bi bi-info-square',    'key' => 'text_menu_site_info'],
                    ['route' => 'setting/webhook',      'icon' => 'bi bi-diagram-3',      'key' => 'text_menu_webhooks'],
                    ['route' => 'localization/language','icon' => 'bi bi-translate',      'key' => 'text_menu_languages'],
                    ['route' => 'user/user',            'icon' => 'bi bi-people',         'key' => 'text_menu_users'],
                    ['route' => 'user/user_group',      'icon' => 'bi bi-person-lock',    'key' => 'text_menu_user_groups'],
                ],
            ],
        ];

        $menu = [];

        foreach ($groups as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                // common/dashboard é a home universal do painel — sempre visível
                // ao usuário logado (o middleware já a libera sem grant explícito)
                if ($item['route'] !== 'common/dashboard'
                    && !$this->user->hasPermission('access', $item['route'])) {
                    continue;
                }

                $items[] = [
                    'route'  => $item['route'],
                    'icon'   => $item['icon'],
                    'text'   => $this->language->get($item['key']),
                    'href'   => $this->url->link($item['route']),
                    'active' => $this->matchRoute($current_route, $item['route']),
                ];
            }

            $menu[] = ['title' => $group['title'], 'items' => $items];
        }

        return $menu;
    }

    /**
     * Ativo quando a rota corrente é a própria rota do item, uma sub-ação
     * mapeada EL (blog/blog.edit|3) ou uma rota irmã do módulo
     * (blog/import ativa o item blog/blog).
     */
    private function matchRoute(string $current, string $item): bool {
        if ($current === $item) {
            return true;
        }

        // sub-ações mapeadas EL: blog/blog.edit|3 | catalog/project.save
        if (str_starts_with($current, $item . '.') || str_starts_with($current, $item . '|')) {
            return true;
        }

        // irmãs do mesmo segmento de módulo (blog/import pertence a blog/blog)
        $itemSegments    = explode('/', $item);
        $currentSegments = explode('/', preg_replace('/[.|].*/', '', $current) ?? $current);

        return count($itemSegments) === 2
            && count($currentSegments) === 2
            && $itemSegments[0] === $currentSegments[0]
            && $itemSegments[1] !== $currentSegments[1]
            && $this->siblingBelongs($itemSegments[0], $currentSegments[1], $itemSegments[1]);
    }

    /**
     * Rotas irmãs aceitas como pertencentes ao item principal do módulo.
     * Lista explícita e curta — qualquer rota nova de módulo passa por aqui.
     */
    private function siblingBelongs(string $section, string $current_controller, string $item_controller): bool {
        $map = [
            'blog' => ['blog' => ['import']],
        ];

        return isset($map[$section][$item_controller])
            && in_array($current_controller, $map[$section][$item_controller], true);
    }

    /** Iniciais do usuário p/ avatar (máx 2 letras, unicode-safe) */
    private function initials(string $name): string {
        $name = trim($name);

        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = mb_strtoupper((string)mb_substr((string)($parts[0] ?? ''), 0, 1));
        $last  = count($parts) > 1 ? mb_strtoupper((string)mb_substr((string)end($parts), 0, 1)) : '';

        return $first . $last;
    }
}
