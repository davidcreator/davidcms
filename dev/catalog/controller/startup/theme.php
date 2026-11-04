<?php
/**
 * Apply published Catalog theme overrides; isolated to the Catalog pre-action.
 *
 * F12-01: avalia condicoes declarativas do tema e publica:
 *  - tokens de cor e cena (compatibilidade com o legado F10-03);
 *  - configuracao Three.js allowlisted (catalog_theme_three);
 *  - globais Twig seguros (theme / theme_scene) visiveis em TODAS as telas.
 * F13-R: site efetivo alinhado ao ID estavel da decisao (fecha a O).
 * F13-P: idioma efetivo unificado (K+L) antes do contexto das condicoes.
 * F13-O: rollout pode ser limitado a sites allowlistados (frentes H+J).
 * Nenhuma regra escreve arquivo, executa codigo ou acessa dado sensivel.
 */
namespace Reamur\Catalog\Controller\Startup;
class Theme extends \Reamur\System\Engine\Controller {
    private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found'];

    public function index(): void {
        $this->load->model('common/theme');
        // F13-H: uma unica leitura traz publicado + rascunho do tema ativo.
        // A decisao e PURA: zero escrita, zero cron, zero cookie novo. O
        // publicado continua intacto no banco; 'Ativar' segue sendo explicito.
        $effective = method_exists($this->model_common_theme, 'getEffectiveTheme')
            ? $this->model_common_theme->getEffectiveTheme()
            : ['published'=>(array)$this->model_common_theme->getPublishedTheme(),'draft'=>[],'slug'=>''];
        $published = (array)($effective['published'] ?? []);
        $draft     = (array)($effective['draft'] ?? []);
        $selfSlug  = (string)($effective['slug'] ?? '');
        // F13-O: o site da DECISAO vem da politica PUBLICADA (estavel). Usar a
        // politica do config efetivo criaria ciclo: a escolha do config ja
        // depende da decisao. Nenhuma query nova: match() e puro.
        // F13-P: este bloco sobe para ANTES do contexto — o idioma do site
        // precisa estar disponivel na negociacao e nas condicoes (frente L).
        $publishedSites = $this->theme_sites->normalize($published['sites'] ?? null) ?? [];
        $siteHost = (string)($this->request->server['HTTP_HOST'] ?? '');
        $siteSeed = $this->theme_sites->match($publishedSites, $siteHost);
        $siteIdSeed = (string)($siteSeed['id'] ?? '');
        $siteIdSeed = in_array($siteIdSeed, $this->theme_sites->ids(), true) ? $siteIdSeed : 'primary';

        // F13-P: uma UNICA decisao de idioma, antes de montar o contexto.
        // A leitura de idiomas migra de startup/language para ca (mesmo 1
        // SELECT, nenhuma query nova no total). O contexto das condicoes
        // passa a ver o idioma EFETIVO, fechando a limitacao da frente L.
        $this->load->model('localization/language');
        $langSession = $this->registry->get('session');
        $sessData = ($langSession instanceof \Reamur\System\Library\Session) ? $langSession->data : [];
        $neg = \Reamur\System\Library\LanguageNegotiator::resolve([
            'enabled'      => $this->model_localization_language->getEnabledCodes(),
            'default'      => (string)$this->config->get('config_language_catalog',
                                (string)$this->config->get('language_directory', 'en-us')),
            'requested'    => (string)($this->request->get['language'] ?? ''),
            'stored'       => (string)($sessData['language'] ?? ''),
            'stored_site'  => (string)($sessData['language_site'] ?? ''),
            'site'         => $siteIdSeed,
            'site_language'=> (string)($siteSeed['language'] ?? ''),
        ]);
        if ($langSession instanceof \Reamur\System\Library\Session && $neg['store']) {
            $langSession->data['language'] = $neg['code'];
            $langSession->data['language_site'] = $neg['site'];
        }
        $this->config->set('catalog_theme_language_effective', $neg['code']);
        $this->config->set('catalog_theme_language_source', $neg['source']);
        $this->config->set('catalog_theme_language_site_changed', $neg['site_changed']);

        $context   = $this->context();
        // F13-P: substitui o default do configfile pelo idioma efetivo.
        $context['language'] = $neg['code'];
        $schedule  = $this->theme_schedule->normalize($draft['schedule'] ?? null);
        $decision  = $this->theme_schedule->decide(
            $schedule, $this->theme_schedule->now(),
            hash('sha256', (string)$this->session->getId()),
            (bool)($context['logged_in'] ?? false),
            $siteIdSeed
        );
        $source = $decision['effective'] === 'draft' ? 'draft' : 'published';
        $config = $source === 'draft' ? $draft : $published;

        // F13-J: politica de sites vem do config EFETIVO do tema ativo. O host
        // do request so e COMPARADO (ThemeSites::match), nunca publicado.
        $sitesCfg = $this->theme_sites->normalize($config['sites'] ?? null) ?? [];
        // $siteHost ja resolvido acima (F13-O); J continua usando o config
        // EFETIVO e o host apenas COMPARADO, nunca publicado.
        $site = $this->theme_sites->match($sitesCfg, $siteHost);

        // F13-R: fecha a limitacao declarada da frente O. O ID estavel da
        // politica PUBLICADA ($siteIdSeed, usado no decide() e na negociacao
        // de idioma da P) e a fonte de verdade para TODA a requisicao. Se o
        // rascunho alterou o host de um site, match() sobre o config efetivo
        // re-resolvia o MESMO host para OUTRO ID: um rollout liberado para
        // `shop` renderizava com tema, condicoes e idioma de `primary`.
        // byId() (frente Q) recupera os ATRIBUTOS daquele ID no config efetivo,
        // sem ciclo: nenhuma decisao depende do config que ela mesma escolheu.
        // A decisao da O (politica publicada) fica INTACTA; apenas propagamos
        // o mesmo ID aos consumidores. Sem divergencia NADA muda, inclusive
        // `reason='exact'` => compatibilidade byte a byte com A-Q.
        $effectiveId = (string)($site['id'] ?? '');
        if ($effectiveId !== '' && $effectiveId !== $siteIdSeed) {
            $aligned = $this->theme_sites->byId($sitesCfg, $siteIdSeed);
            if (!empty($aligned['active'])) $site = $aligned;
        }

        // F13-L: dimensao `site` entra no contexto allowlisted ANTES do
        // ThemeSnapshot. O ID vem SOMENTE de ThemeSites (allowlist da J);
        // HTTP_HOST continua sendo apenas COMPARADO, nunca publicado.
        // Sem sites configurados, match() devolve `primary` (identico a K).
        $siteId = (string)($site['id'] ?? '');
        $context['site'] = in_array($siteId, $this->theme_sites->ids(), true) ? $siteId : 'primary';

        // F13-C: uma unica fonte de verdade para o estado efetivo do tema.
        // O Admin (previa rascunho x publicado) resolve exatamente o mesmo
        // calculo com a mesma classe, no mesmo contexto allowlistado.
        // F13-E: o Catalog resolve a cadeia de child-themes sobre o mapa publicado.
        $themeMap = method_exists($this->model_common_theme, 'getThemeMap') ? $this->model_common_theme->getThemeMap($source) : [];
        // F13-J: o mapa acima ja traz TODOS os temas. Escolher o tema do site
        // nao custa nenhuma consulta nova; slug ausente cai no tema ativo.
        $root = $this->theme_sites->resolve($site, $themeMap, $selfSlug);
        $rootConfig = $root['config'] ?? $config;
        $rootSlug = (string)$root['slug'];
        $snapshot = $this->theme_snapshot->resolve($rootConfig, $context, $themeMap, $rootSlug, $decision,
            ['sites'=>$sitesCfg,'active'=>$site]);
        $primary  = $snapshot['primary'];
        $surface  = $snapshot['surface'];
        $scene    = $snapshot['scene'];
        $three    = $snapshot['three'];
        $tokens   = $snapshot['tokens'];

        if ($primary !== null) $this->config->set('catalog_theme_primary', $primary);
        if ($surface !== null) $this->config->set('catalog_theme_surface', $surface);
        $this->config->set('catalog_theme_scene', $scene);
        $this->config->set('catalog_theme_three', $three);
        $this->config->set('catalog_theme_tokens', $tokens);
        $this->config->set('catalog_theme_vars', $snapshot['vars']);
        $this->config->set('catalog_theme_flags', $snapshot['flags']);
        $this->config->set('catalog_theme_classes', $snapshot['classes']);
        $this->config->set('catalog_theme_matched', $snapshot['matched']);
        // F13-F: null = layout legado do CMS; array = override do tema.
        $this->config->set('catalog_theme_sections', $snapshot['sections']);
        // F13-G: componentes efetivos + variaveis CSS allowlistadas.
        $this->config->set('catalog_theme_components', $snapshot['components']);
        $this->config->set('catalog_theme_component_css', $snapshot['component_css']);
        // F13-H: estado efetivo + variaveis planas (nenhum HTML novo).
        $this->config->set('catalog_theme_schedule', $decision);
        $this->config->set('catalog_theme_effective', $decision['effective']);
        $this->config->set('catalog_theme_schedule_css', $snapshot['schedule_css']);
        // F13-I: variaveis --rms-media-* (data URI SVG deterministico).
        $this->config->set('catalog_theme_media_css', $snapshot['media_css']);
        // F13-J: site ativo (allowlistado), CSS de inteiros e idioma sugerido.
        $this->config->set('catalog_theme_site', $site);
        $this->config->set('catalog_theme_site_css', $snapshot['site_css']);
        $this->config->set('catalog_theme_site_language', $snapshot['site_language']);

        // Globais primeiro: setThemeTemplates() reconstrói o Environment e
        // reaplica estes mesmos valores (implementado no adaptador Twig).
        if (method_exists($this->template, 'setThemeGlobals')) $this->template->setThemeGlobals($snapshot['globals']);
        if (method_exists($this->template, 'setThemeTemplates')) $this->template->setThemeTemplates($snapshot['templates']);
    }

    /** Contexto allowlisted e sem dados sensiveis (ver ConditionEngine::FIELDS). */
    private function context(): array {
        $server = $this->request->server;
        $ua = (string)($server['HTTP_USER_AGENT'] ?? '');
        $mobile = preg_match('/(android|iphone|ipod|iemobile|mobile)/i', $ua) === 1;
        $tablet = preg_match('/(ipad|tablet|kindle|silk|playbook)/i', $ua) === 1;
        $query = [];
        foreach ($this->request->get as $k => $v) {
            if (is_string($k) && is_scalar($v)) $query[$k] = substr((string)$v, 0, 120);
        }
        $variant = isset($query['variant']) && in_array((string)$query['variant'], ['a','b'], true) ? (string)$query['variant'] : '';
        return [
            'route'      => (string)$this->config->get('_route', ''),
            'path'       => (string)(parse_url((string)($server['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''),
            'language'   => (string)$this->config->get('language_directory', 'en-us'),
            'device'     => $tablet ? 'tablet' : ($mobile ? 'mobile' : 'desktop'),
            'mobile'     => $mobile || $tablet,
            'logged_in'  => !empty($this->session->data['user_id']) || !empty($this->session->data['customer_id']),
            'user_group' => (string)($this->session->data['user_group'] ?? ''),
            'hour'       => (int)date('G'),
            'weekday'    => (int)date('N'),
            'date'       => date('Y-m-d'),
            'month'      => (int)date('n'),
            'variant'    => $variant,
            'https'      => !empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off',
            'ajax'       => strtolower((string)($server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest',
            'query'      => $query,
        ];
    }
}
