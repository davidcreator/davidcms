<?php
/**
 * Apply published Catalog theme overrides; isolated to the Catalog pre-action.
 *
 * F12-01: avalia condicoes declarativas do tema e publica:
 *  - tokens de cor e cena (compatibilidade com o legado F10-03);
 *  - configuracao Three.js allowlisted (catalog_theme_three);
 *  - globais Twig seguros (theme / theme_scene) visiveis em TODAS as telas.
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
        $context   = $this->context();
        $schedule  = $this->theme_schedule->normalize($draft['schedule'] ?? null);
        $decision  = $this->theme_schedule->decide(
            $schedule, $this->theme_schedule->now(),
            hash('sha256', (string)$this->session->getId()),
            (bool)($context['logged_in'] ?? false)
        );
        $source = $decision['effective'] === 'draft' ? 'draft' : 'published';
        $config = $source === 'draft' ? $draft : $published;

        // F13-C: uma unica fonte de verdade para o estado efetivo do tema.
        // O Admin (previa rascunho x publicado) resolve exatamente o mesmo
        // calculo com a mesma classe, no mesmo contexto allowlistado.
        // F13-E: o Catalog resolve a cadeia de child-themes sobre o mapa publicado.
        $themeMap = method_exists($this->model_common_theme, 'getThemeMap') ? $this->model_common_theme->getThemeMap($source) : [];
        $snapshot = $this->theme_snapshot->resolve($config, $context, $themeMap, $selfSlug, $decision);
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
