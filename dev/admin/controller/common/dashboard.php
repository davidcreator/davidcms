<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * common/dashboard — página inicial do painel (ação padrão do admin).
 * F8-01 (ADR 0045): visual novo — cards de contadores reais por permissão,
 * ações rápidas (modify-gated), listas dos últimos posts/mensagens e o
 * painel técnico do usuário (perfil resumido, ACL, cache, versão).
 */
namespace Reamur\Admin\Controller\Common;

class Dashboard extends \Reamur\System\Engine\Controller {

    /** Renderiza o painel inicial (200) */
    public function index(): void {
        if (!$this->user->isLogged()) {
            $this->response->redirect($this->url->link('common/login'));

            return;
        }

        $this->load->language('common/dashboard');
        $this->load->model('common/dashboard');

        $this->document->setTitle($this->language->get('heading_title'));

        $data = $this->baseData();

        // ADR 0088 (S3): instalação nova nasce com REAMUR_ERROR_DISPLAY=true.
        // Enquanto estiver assim, o painel avisa; vazio = nada a mostrar.
        $data['warning_error_display'] = $this->config->get('error_display', false)
            ? $this->language->get('warning_error_display')
            : '';

        // ADR 0091 (S12): a pasta install/ não tem uso depois da instalação.
        // Com o site instalado ela responde 403, mas continua sendo código de
        // instalação ao alcance da web; o painel avisa enquanto ela existir.
        $data['warning_install_folder'] = \Reamur\System\Library\Security\InstallSeal::installerPresent(dirname(rtrim(DIR_APPLICATION, '/')))
            ? $this->language->get('warning_install_folder')
            : '';

        // ADR 0093 (S8): a chave de emergência da política de conteúdo não pode
        // ficar esquecida. O painel lê o config.php do admin; o do site público
        // é um arquivo separado, e o aviso lembra de conferir os dois.
        $cspMode = $this->csp->getMode();
        $data['warning_csp'] = $cspMode === \Reamur\System\Library\Security\Csp::MODE_ENFORCE
            ? ''
            : $this->language->get('warning_csp_' . $cspMode);

        // ADR 0089 (S5): o que o servidor enxerga da conexão e avisos de proxy.
        $data = array_merge($data, $this->connectionData());

        // ── Cards de visão-geral: ACL-driven + tableExists no model ──
        $data['cards'] = [];

        foreach ($this->model_common_dashboard->getStats() as $key => $stat) {
            if (!$this->user->hasPermission('access', $stat['route'])) {
                continue;
            }

            $data['cards'][] = [
                'icon'        => $stat['icon'],
                'num'         => $this->fmtNum((int)$stat['count']),
                'label'       => $this->language->get($stat['label']),
                'extra_line'  => $stat['extra_label'] !== ''
                    ? sprintf($this->language->get($stat['extra_label']), $this->fmtNum((int)$stat['extra']))
                    : '',
                'href'        => $this->url->link($stat['route']),
            ];
        }

        // ── Ações rápidas: somente com modify na rota alvo ──
        $data['quick_actions'] = [];

        foreach ([
            ['route' => 'blog/blog.add',          'icon' => 'bi bi-journal-plus',   'key' => 'action_new_post'],
            ['route' => 'cms/page',                'icon' => 'bi bi-file-earmark-plus','key' => 'action_new_page'],
            ['route' => 'catalog/project',         'icon' => 'bi bi-briefcase',     'key' => 'action_new_project'],
            ['route' => 'tool/tool',               'icon' => 'bi bi-tools',         'key' => 'action_new_tool'],
        ] as $qa) {
            $access_route = explode('.', $qa['route'])[0];

            if ($this->user->hasPermission('modify', $access_route)) {
                $data['quick_actions'][] = [
                    'icon' => $qa['icon'],
                    'text' => $this->language->get($qa['key']),
                    'href' => $this->url->link($qa['route']),
                ];
            }
        }

        // Importador WP (F7-03) — atalho contextual no painel
        if ($this->user->hasPermission('modify', 'blog/blog')) {
            $data['quick_actions'][] = [
                'icon' => 'bi bi-wordpress',
                'text' => $this->language->get('action_import_wp'),
                'href' => $this->url->link('blog/import'),
            ];
        }

        // ── Listas rápidas ──
        $data['latest_posts'] = [];

        if ($this->user->hasPermission('access', 'blog/blog')) {
            foreach ($this->model_common_dashboard->getLatestPosts(5) as $row) {
                $data['latest_posts'][] = [
                    'title' => (string)($row['title'] ?? ''),
                    'date'  => (string)($row['date_published'] ?? ''),
                    'href'  => $this->url->link('blog/blog.edit|' . (int)($row['post_id'] ?? 0)),
                ];
            }
        }

        $data['latest_messages'] = [];

        if ($this->user->hasPermission('access', 'communication/contact_message')) {
            foreach ($this->model_common_dashboard->getLatestMessages(5) as $row) {
                $data['latest_messages'][] = [
                    'name'    => (string)($row['name'] ?? ''),
                    'subject' => (string)($row['subject'] ?? '') !== '' ? (string)$row['subject'] : $this->language->get('text_no_subject'),
                    'date'    => (string)($row['date_added'] ?? ''),
                    'href'    => $this->url->link('communication/contact_message.edit|' . (int)($row['contact_message_id'] ?? 0)),
                ];
            }
        }

        $data['blog_list_href']    = $this->url->link('blog/blog');
        $data['message_list_href'] = $this->url->link('communication/contact_message');

        // Perfil/atributos (mantidos de F2-6)
        $data['username']      = $this->user->getUsername();
        $data['email']         = $this->user->getEmail();
        $data['user_group_id'] = $this->user->getUserGroupId();
        $data['profile']       = $this->url->link('user/profile');

        $data['access_routes'] = $this->user->countPermissions('access');
        $data['modify_routes'] = $this->user->countPermissions('modify');

        // Indicador de cache (F4-06, ADR 0030)
        $data['cache_active'] = strtolower((string)substr((string)strrchr(get_class($this->cache), '\\'), 1));
        $data['cache_expire'] = (int)$this->config->get('cache_expire', 3600);
        $cacheConfigured      = strtolower(trim((string)$this->config->get('cache_engine', 'file')));

        $data['text_cache_indicator'] = sprintf($this->language->get('text_cache_indicator'), $data['cache_active'], $data['cache_expire']);
        $data['text_cache_fallback']  = ($cacheConfigured === 'apc' ? 'apcu' : $cacheConfigured) !== $data['cache_active']
            ? sprintf($this->language->get('text_cache_fallback'), $cacheConfigured)
            : '';

        // Chrome compartilhado (F8-01)
        $this->document->addStyle((string)$this->config->get('site_url', '') . 'view/css/admin.css?v=' . Header::ASSET_V);

        $data['body_class'] = '';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);

        $this->response->setOutput($this->load->view('common/dashboard', $data));
    }

    /**
     * Textos base (i18n) — todos entram em $data explicitamente.
     *
     * @return array<string, mixed>
     */
    /**
     * ADR 0089 (S5): leitura da conexão para o painel "Conta e sistema" e os
     * avisos de proxy/CDN. É o jeito de descobrir o que a hospedagem entrega
     * ao PHP antes de declarar REAMUR_TRUSTED_PROXIES / _HOPS no config.php.
     *
     * @return array<string, mixed>
     */
    private function connectionData(): array {
        $connection = $this->request->connection();
        $lang       = fn (string $key): string => (string)$this->language->get($key);

        $rawHttps = $this->request->server['HTTPS'] ?? '';
        $direct   = is_string($rawHttps) && $rawHttps !== '' && strtolower($rawHttps) !== 'off';

        if (!$connection->https) {
            $https = $lang('text_conn_https_no');
        } elseif ($direct) {
            $https = $lang('text_conn_https_direct');
        } else {
            $https = $lang('text_conn_https_proxy');
        }

        if ($connection->mode === $connection::MODE_LIST) {
            $trust = sprintf($lang('text_conn_trust_list'), $connection->entries);
        } elseif ($connection->mode === $connection::MODE_HOPS) {
            $trust = sprintf($lang('text_conn_trust_hops'), $connection->hops);
        } else {
            $trust = $lang('text_conn_trust_none');
        }

        $secure = \Reamur\System\Library\Session::cookieSecure(
            defined('HTTP_SERVER') ? (string)HTTP_SERVER : '',
            $connection->https
        );

        $warnings = [];

        if ($connection->invalid !== []) {
            $warnings[] = sprintf($lang('warning_proxy_invalid'), implode(', ', $connection->invalid));
        }

        if ($connection->forwarded && $connection->mode === $connection::MODE_DIRECT) {
            $warnings[] = sprintf($lang('warning_proxy_unconfigured'), $connection->peer);
        } elseif ($connection->forwarded && $connection->mode === $connection::MODE_LIST && !$connection->honoured) {
            $warnings[] = sprintf($lang('warning_proxy_untrusted_peer'), $connection->peer);
        } elseif ($connection->mode === $connection::MODE_HOPS && !$connection->forwarded) {
            $warnings[] = sprintf($lang('warning_proxy_hops_direct'), $connection->hops);
        }

        return [
            'text_conn_heading' => $lang('text_conn_heading'),
            'text_conn_ip'      => $lang('text_conn_ip'),
            'text_conn_peer'    => $lang('text_conn_peer'),
            'text_conn_https'   => $lang('text_conn_https'),
            'text_conn_cookie'  => $lang('text_conn_cookie'),
            'text_conn_trust'   => $lang('text_conn_trust'),
            'text_conn_note'    => $lang('text_conn_note'),
            'conn_ip'           => $connection->ip !== '' ? $connection->ip : '—',
            'conn_peer'         => $connection->peer !== '' ? $connection->peer : '—',
            'conn_https'        => $https,
            'conn_cookie'       => $lang($secure ? 'text_conn_cookie_secure' : 'text_conn_cookie_plain'),
            'conn_trust'        => $trust,
            'warnings_proxy'    => $warnings,
        ];
    }

    private function baseData(): array {
        foreach ([
            'heading_title', 'text_welcome_overview',
            'text_latest_posts', 'text_latest_messages',
            'text_view_all', 'text_no_posts', 'text_no_messages',
            'text_profile', 'text_username', 'text_email', 'text_user_group',
            'text_manage_profile', 'text_panel_user', 'text_panel_tech',
            'text_acl', 'text_access_routes', 'text_modify_routes', 'text_acl_note',
            'text_stats_overview', 'text_quick_actions',
            'card_posts', 'card_pages', 'card_projects', 'card_orders',
            'card_messages', 'card_testimonials', 'card_tools', 'card_webhook_fails',
            'card_today', 'card_7d', 'card_24h', 'card_30d',
            'action_new_post', 'action_new_page', 'action_new_project', 'action_new_tool',
            'action_import_wp', 'text_no_subject',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['text_version'] = sprintf($this->language->get('text_version'), (string)REAMUR_VERSION);

        $data['logout']        = $this->url->link('common/logout');
        $data['button_logout'] = $this->language->get('button_logout');

        return $data;
    }

    /**
     * Formata números sem depender de ext-intl (nem todo host a tem).
     * pt-BR/es-EU: 1.234 — en-US: 1,234 (âncora = language_directory do painel).
     */
    private function fmtNum(int $n): string {
        $locale    = (string)$this->config->get('language_directory', 'en-us');
        $thousands = $locale === 'en-us' ? ',' : '.';

        return number_format($n, 0, $locale === 'en-us' ? '.' : ',', $thousands);
    }
}
