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
        $config = $this->model_common_theme->getPublishedTheme();
        if (!is_array($config)) $config = [];

        $engine  = $this->theme_condition_engine;
        $context = $this->context();

        // Regras invalidas sao descartadas por normalize(); o resto do site
        // continua com o comportamento legado mesmo se o JSON estiver ruim.
        $rules = [];
        if (isset($config['conditions']) && is_array($config['conditions'])) {
            $rules = $engine->normalize($config['conditions'])['rules'];
        }
        $r = $engine->evaluate($rules, $context);

        $primary = $r['primary'] ?? (is_string($config['primary'] ?? null) ? $config['primary'] : null);
        $surface = $r['surface'] ?? (is_string($config['surface'] ?? null) ? $config['surface'] : null);
        if ($primary !== null && preg_match('/^#[0-9a-fA-F]{6}$/D', $primary)) $this->config->set('catalog_theme_primary', $primary);
        if ($surface !== null && preg_match('/^#[0-9a-fA-F]{6}$/D', $surface)) $this->config->set('catalog_theme_surface', $surface);

        // Selecao de cena legada (F10-03) continua intacta; uma regra pode
        // sobrepo-la, mas jamais introduzir uma cena fora da allowlist.
        $scene = $r['scene'] ?? (is_string($config['scene'] ?? null) ? $config['scene'] : '');
        if (!in_array($scene, self::SCENES, true)) $scene = '';
        $this->config->set('catalog_theme_scene', $scene);

        // Three.js: base do tema + override da ultima regra casada.
        $base = isset($config['three']) && is_array($config['three']) ? $config['three'] : [];
        $three = $engine->normalizeThree($base) ?? [];
        if (is_array($r['three'])) $three = array_replace($three, $r['three']);
        if ($three === []) $three = ['enabled'=>false,'scene'=>'','slot'=>'hero','count'=>400,'color'=>'#8a4fff','speed'=>0.05];
        if (($three['scene'] ?? '') === '' && $scene !== '') $three['scene'] = $scene;
        $this->config->set('catalog_theme_three', $three);


        // F13-B: design tokens. Base allowlistada + patches das regras casadas.
        // Se um patch derrubar o contraste AA, ele e ignorado (fail-closed).
        $tokenRegistry = $this->theme_token_registry;
        $tokenBase = isset($config['tokens']) && is_array($config['tokens']) ? $config['tokens'] : [];
        $tokens = $tokenRegistry->normalize($tokenBase) ?? $tokenRegistry->defaults();
        if (is_array($r['tokens']) && $r['tokens'] !== []) {
            $merged = array_replace($tokens, $r['tokens']);
            $readable = true;
            foreach ($tokenRegistry->contrastPairs($merged) as $pair) {
                if (!$pair['ok']) { $readable = false; break; }
            }
            if ($readable) $tokens = $merged;
        }
        $this->config->set('catalog_theme_tokens', $tokens);

        // Resultado das condicoes para o header e para o Twig.
        $this->config->set('catalog_theme_vars', $r['vars']);
        $this->config->set('catalog_theme_flags', $r['flags']);
        $this->config->set('catalog_theme_classes', $r['classes']);
        $this->config->set('catalog_theme_matched', $r['matched']);

        // Globais primeiro: setThemeTemplates() reconstrói o Environment e
        // reaplica estes mesmos valores (implementado no adaptador Twig).
        if (method_exists($this->template, 'setThemeGlobals')) {
            $this->template->setThemeGlobals([
                'theme' => [
                    'vars' => $r['vars'], 'flags' => $r['flags'],
                    'classes' => $r['classes'], 'matched' => $r['matched'],
                    'tokens' => $tokens,
                ],
                'theme_scene' => $three,
            ]);
        }
        $templates = isset($config['templates']) && is_array($config['templates']) ? $config['templates'] : [];
        if (method_exists($this->template, 'setThemeTemplates')) $this->template->setThemeTemplates($templates);
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
