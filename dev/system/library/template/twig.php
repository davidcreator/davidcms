<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Adaptador Twig — engine padrão oficial (Guia-Twig §1).
 * Resolve templates em DIR_TEMPLATE, preserva template_directory e cache.
 *
 * [AGENTE: system] TAREFA: integração opt-in de fontes versionadas de tema.
 * ARQUIVOS ALVO: library/template/twig.php; config/catalog.php; startup/theme.
 * CONSUMIDORES AFETADOS: Catalog (opt-in), Admin (preview isolado), Install (inalterado).
 * IMPACTO: núcleo compartilhado — plano confirmado pelo usuário.
 * PLANO: 1) preservar construtor/cache/loader base; 2) limitar overrides a keys fixas;
 * 3) fornecer prévia sandboxada sem IO de escrita; 4) documentar impacto.
 * STATUS: concluído localmente; SQL/migration ainda não executados.
 */
namespace Reamur\System\Library\Template;
/* Frente AG: includers manuais (PROBEs, harnesses) nao registram autoloader
   para o namespace Theme. Sem este guard a classe nova nao seria encontrada. */
if (!class_exists(\Reamur\System\Library\Theme\ThemePreviewState::class, false)) {
    require_once __DIR__ . '/../theme/theme_preview_state.php';
}
if (!class_exists(ThemeSandbox::class, false)) {
    require_once __DIR__ . '/theme_sandbox.php';
}

final class Twig {
    /** Fixed Catalog source keys editable through Themes; arbitrary paths, script tags and raw filters are rejected. */
    private const THEME_TEMPLATE_KEYS = [
        'common/home', 'common/footer', 'common/contact',
        'partial/site_header', 'partial/hero', 'partial/hero-3d', 'partial/about',
        'partial/methodology', 'partial/skills', 'partial/services', 'partial/testimonials',
        'partial/portfolio', 'partial/tools', 'partial/news', 'partial/clients', 'partial/contact',
        'cms/page_info', 'catalog/project_list', 'catalog/project_info',
        'blog/blog_list', 'blog/blog_info', 'tool/tool_list', 'tool/tool_info',
        'checkout/quote', 'error/not_found',
    ];
    private \Twig\Environment $twig;
    private bool $cacheEnabled;
    private string $directory;
    /** @var array<string, mixed> Globais seguros publicados pelo tema (F12-01). */
    private array $themeGlobals = [];
    /** S2 (ADR 0087): fontes do tema publicado, sempre em sandbox. null = tema sem fontes. */
    private ?ThemeSandbox $themeSandbox = null;

    public function __construct(string $directory = '', bool $cache = false) {
        $this->cacheEnabled = $cache;
        $this->directory = $directory;
        $paths = [DIR_TEMPLATE];
        if ($directory !== '' && is_dir(DIR_TEMPLATE . $directory)) {
            array_unshift($paths, DIR_TEMPLATE . rtrim($directory, '/') . '/');
        }
        $cacheDir = DIR_STORAGE . 'cache/template/';
        if ($cache && !is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
        $this->twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader($paths), [
            'autoescape'=>'html', 'cache'=>$cache ? $cacheDir : false, 'auto_reload'=>true
        ]);
        $this->addThemePartial($this->twig, fn (string $key, array $data): string => $this->renderThemeKey($key, $data));
        $this->applyThemeGlobals($this->twig);
    }

    /** @return list<string> Pastas dos templates do sistema, na ordem de busca. */
    private function templatePaths(): array {
        $paths = [DIR_TEMPLATE];
        if ($this->directory !== '' && is_dir(DIR_TEMPLATE . $this->directory)) array_unshift($paths, DIR_TEMPLATE . rtrim($this->directory, '/') . '/');
        return $paths;
    }

    /**
     * S2 (ADR 0087): `{{ theme_partial('partial/site_header', {...}) }}` e o
     * UNICO caminho de um template confiavel (arquivo do sistema, ex.
     * common/header.twig, que usa |raw) ate um trecho que o tema pode redefinir.
     * O trecho e renderizado por $render: em sandbox se o tema o redefine. Um
     * `{% include %}` no lugar leria so o arquivo padrao, porque o Environment
     * confiavel nao enxerga fonte de tema.
     * O trecho recebe SO as variaveis passadas (e os globais do tema), como
     * `{% include ... with {...} only %}`: o contexto do template confiavel,
     * que carrega HTML ja marcado como seguro, nao vaza para a fonte de tema.
     * @param \Closure(string, array<string, mixed>): string $render
     */
    private function addThemePartial(\Twig\Environment $env, \Closure $render): void {
        $keys = self::THEME_TEMPLATE_KEYS;
        $env->addFunction(new \Twig\TwigFunction(ThemeSandbox::TRUSTED_PARTIAL, static function (string $key, array $variables = []) use ($render, $keys): \Twig\Markup {
            if (!in_array($key, $keys, true)) throw new \InvalidArgumentException('Template outside the theme allowlist.');
            return new \Twig\Markup($render($key, $variables), 'UTF-8');
        }, ['is_safe' => ['html']]));
    }

    /** @param array<string, mixed> $data */
    private function renderThemeKey(string $key, array $data): string {
        if ($this->themeSandbox !== null) return $this->themeSandbox->render($key, $data, $this->twig);
        return $this->twig->render($key . '.twig', $data);
    }

    /**
     * F12-01: publica APENAS escalares/listas planas como globais Twig, para
     * que TODAS as telas (header, footer, home, qualquer rota) enxerguem as
     * condicoes do tema. Objetos, closures, recursos, HTML e arrays aninhados
     * sao descartados; Twig\Markup nao e aceito (sem |raw, sem HTML confiavel).
     * @param array<string, mixed> $globals
     */
    public function setThemeGlobals(array $globals): void {
        $safe = [];
        foreach ($globals as $name => $value) {
            if (!is_string($name) || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name) !== 1) continue;
            if (is_string($value)) {
                if (strlen($value) <= 256 && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) $safe[$name] = $value;
                continue;
            }
            if (is_bool($value) || is_int($value) || (is_float($value) && is_finite($value))) { $safe[$name] = $value; continue; }
            if (is_array($value)) {
                $flat = [];
                foreach ($value as $k => $v) {
                    if (count($flat) >= 64) break;
                    if (is_scalar($v) && (!is_string($v) || strlen($v) <= 256)) $flat[$k] = $v;
                }
                $safe[$name] = $flat;
            }
        }
        $this->themeGlobals = $safe;
        $this->applyThemeGlobals($this->twig);
        if ($this->themeSandbox !== null) $this->themeSandbox->setGlobals($safe);
    }

    private function applyThemeGlobals(\Twig\Environment $env): void {
        foreach ($this->themeGlobals as $name => $value) $env->addGlobal($name, $value);
    }

    /**
     * PLAN F10-03: opt-in Catalog-only template source overrides. Additive API;
     * fixed allowlist, bounded source, and filesystem fallback. Admin and
     * Install do not invoke it.
     *
     * S2 (ADR 0087): as fontes NAO entram mais no Environment confiavel. Antes
     * este metodo trocava `$this->twig` por um Environment comum com as fontes
     * do tema dentro, protegido so por uma regex: `constant()`, `map('system')`
     * e `source()` funcionavam no site publicado. Agora elas vivem num
     * Environment dedicado e sempre em sandbox (ThemeSandbox), com a mesma
     * politica da previa e do canvas. Fonte que viola a politica sai, o
     * template padrao entra no lugar e $report recebe (chave, motivo).
     * @param array<string, string> $templates
     * @param \Closure(string, string): void|null $report
     */
    public function setThemeTemplates(array $templates, ?\Closure $report = null): void {
        $cacheDir = DIR_STORAGE . 'cache/template/';
        if ($this->cacheEnabled && !is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
        $sandbox = new ThemeSandbox(
            $templates, self::THEME_TEMPLATE_KEYS, $this->templatePaths(),
            $this->cacheEnabled ? $cacheDir : false, $this->themeGlobals, $report
        );
        $this->themeSandbox = $sandbox->isEmpty() ? null : $sandbox;
    }

    /**
     * Render an authenticated CMS Page preview with Catalog's actual shell and
     * templates. Catalog roots are fixed at DIR_SYSTEM; only published theme
     * templates from the existing allowlist are considered, and cache is off.
     * @param array<string,mixed> $pageData
     * @param array<string,mixed> $headerData
     * @param array<string,mixed> $footerData
     * @param array<string,string> $templates
     * @param array<string,mixed> $routeData Fixed, synthetic data for one allowlisted system-page template.
     */
    public function renderCatalogPagePreview(array $pageData, array $headerData, array $footerData, array $templates = [], string $routeKey = 'cms/page_info', array $routeData = []): string {
        $routes = [
            'cms/page_info', 'common/home', 'catalog/project_list', 'catalog/project_info',
            'tool/tool_list', 'tool/tool_info', 'blog/blog_list', 'blog/blog_info',
            'common/contact', 'checkout/quote', 'error/not_found',
        ];
        if (!in_array($routeKey, $routes, true)) throw new \InvalidArgumentException('Catalog preview route is not allowed.');
        // S2 (ADR 0087): mesma separacao do site publicado. $env so le arquivos do
        // sistema; as fontes do tema ficam em $themed, sempre em sandbox. Antes as
        // duas coisas dividiam um Environment comum, e esta previa executava no
        // admin qualquer Twig que a regex deixasse passar.
        $paths = [DIR_SYSTEM . '../catalog/view/template/'];
        $env = new \Twig\Environment(new \Twig\Loader\FilesystemLoader($paths), ['autoescape'=>'html','cache'=>false,'auto_reload'=>true]);
        $themed = new ThemeSandbox($templates, self::THEME_TEMPLATE_KEYS, $paths, false, $this->themeGlobals);
        $this->addThemePartial($env, static fn (string $key, array $data): string => $themed->render($key, $data, $env));
        $this->applyThemeGlobals($env);
        $headerHtml = $env->render('common/header.twig', $headerData);
        $footerHtml = $themed->render('common/footer', $footerData, $env);
        $viewData = array_replace($pageData, $routeData);
        if ($routeKey !== 'cms/page_info') {
            $notice = trim((string)($pageData['preview_notice'] ?? ''));
            if ($notice !== '') {
                $headerHtml .= '<aside class="cms-page-preview-notice" role="note" style="padding:12px 16px;margin:12px auto;max-width:1200px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;color:#334155;font-size:.875rem">' . htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</aside>';
            }
        }
        $viewData['header'] = new \Twig\Markup($headerHtml, 'UTF-8');
        $viewData['footer'] = new \Twig\Markup($footerHtml, 'UTF-8');
        $viewData = $this->markTrustedHtml($viewData, ['description','cms_home_content','cms_page_content']);
        if (isset($viewData['post']) && is_array($viewData['post']) && is_string($viewData['post']['block_html'] ?? null)) {
            $viewData['post']['block_html'] = new \Twig\Markup($viewData['post']['block_html'], 'UTF-8');
        }
        return $themed->render($routeKey, $viewData, $env);
    }

    /** Frente AF — normaliza a cena do KAZTheme-Editor para o contexto da
     *  previa. FAIL-CLOSED: so escalares allowlistados, sem HTML, sem texto
     *  longo, sem caminho, sem executavel. Nao depende de servico nem de IO.
     *  @param array<string,mixed> $raw
     *  @return array{enabled:bool,css:string,layers:array<int,array<string,scalar>>,canvas:array{w:int,h:int}} */
    public static function previewSceneView(array $raw): array {
        $empty = ['enabled'=>false,'css'=>'','layers'=>[],'canvas'=>['w'=>960,'h'=>540]];
        if (($raw['enabled'] ?? false) !== true) return $empty;
        $w = (int)($raw['canvas']['w'] ?? 0); $h = (int)($raw['canvas']['h'] ?? 0);
        if ($w < 320 || $w > 3840 || $h < 240 || $h > 2160) return $empty;
        $src = is_array($raw['layers'] ?? null) ? $raw['layers'] : [];
        $kinds = ['rect','text','button','card','section'];
        $roles = ['none','primary','surface','section','component'];
        $pct = static function ($v): float { return round(max(0.0,min(100.0,(float)$v)),3); };
        $out = []; $n = 0;
        foreach ($src as $l) {
            if (!is_array($l) || ++$n > 48) break;
            $kind = in_array((string)($l['kind'] ?? ''), $kinds, true) ? (string)$l['kind'] : 'rect';
            $role = in_array((string)($l['role'] ?? ''), $roles, true) ? (string)$l['role'] : 'none';
            $fill = preg_match('/^#[0-9a-fA-F]{6}$/D', (string)($l['fill'] ?? '')) === 1
                  ? (string)$l['fill'] : '#ffffff';
            $text = (string)preg_replace('/[\x00-\x1F\x7F]/',' ',(string)($l['text'] ?? ''));
            $text = function_exists('mb_substr') ? mb_substr($text,0,80) : substr($text,0,80);
            $out[] = [
                'n'=>$n,'kind'=>$kind,'role'=>$role,
                'left'=>$pct($l['left'] ?? 0),'top'=>$pct($l['top'] ?? 0),
                'width'=>$pct($l['width'] ?? 0),'height'=>$pct($l['height'] ?? 0),
                'fill'=>$fill,'radius'=>max(0,min(64,(int)($l['radius'] ?? 0))),
                'font'=>max(8,min(72,(int)($l['font'] ?? 16))),'text'=>$text,
            ];
        }
        if (!$out) return $empty;
        return ['enabled'=>true,'css'=>'','layers'=>$out,'canvas'=>['w'=>$w,'h'=>$h]];
    }

    /** Render a saved source in a restrictive sandbox with fixed, nonpersistent sample data.
     *  Frente AF: $sceneView e o scene_view ja compilado por ThemeSceneCss.
     *  Frente AG: $themeState e o estado EFETIVO de ThemeSnapshot::resolve();
     *  seus globais allowlistados substituem os valores de exemplo. Ambos os
     *  argumentos novos sao OPCIONAIS: chamadores legados continuam validos. */
    public function renderSandboxPreview(string $key, string $source, array $sceneView = [], array $themeState = []): string {
        $allow = self::THEME_TEMPLATE_KEYS;
        if (!in_array($key, $allow, true) || ThemeSandbox::blocked($source)) {
            throw new \InvalidArgumentException('Template outside the preview allowlist.');
        }
        // ADR 0087: politica unica (ThemeSandbox), a mesma do canvas e do site.
        $loader = new \Twig\Loader\ChainLoader([
            new \Twig\Loader\ArrayLoader([$key . '.twig'=>$source]),
            new \Twig\Loader\FilesystemLoader([DIR_SYSTEM . '../catalog/view/template/'])
        ]);
        $env = ThemeSandbox::environment($loader);
        $this->applyThemeGlobals($env);
        $context = [
            'theme'=>['primary'=>'#2563eb','surface'=>'#ffffff','vars'=>[],'flags'=>[],'classes'=>[],'matched'=>[]],
            'theme_scene'=>['enabled'=>false,'scene'=>'','slot'=>'hero','count'=>400,'color'=>'#8a4fff','speed'=>0.05],
            /* Frente AF: sem isto o home.twig incluia theme-scene-block.twig,
               mas theme_scene_view.enabled caia em default(false) e a cena
               desenhada no editor NAO aparecia na previa. */
            'theme_scene_view'=>self::previewSceneView($sceneView),
            'title'=>'Theme preview',
            'heading_title'=>'Theme preview', 'content'=>'Safe sample content', 'description'=>'Safe sample content',
            'header'=>'', 'footer'=>'', 'cms_home_content'=>'', 'home'=>'#home', 'button_back_home'=>'Home',
            'text_brand'=>'Theme preview', 'text_languages'=>'Languages', 'text_nav_home'=>'Home',
            'text_nav_about'=>'About', 'text_nav_blog'=>'Blog', 'text_nav_portfolio'=>'Portfolio',
            'text_nav_contact'=>'Contact', 'text_nav_quote'=>'Request a quote', 'nav_about'=>'#about',
            'nav_blog'=>'#blog', 'nav_portfolio'=>'#portfolio', 'nav_contact'=>'#contact', 'nav_quote'=>'#quote',
            'languages'=>[['code'=>'en-us','name'=>'English','active'=>true,'href'=>'#language']],
            'text_lead'=>'Safe sample content', 'text_all'=>'All', 'text_filter'=>'Filter',
            'filter_all'=>['active'=>true,'href'=>'#all'], 'posts'=>[], 'projects'=>[], 'tools'=>[],
            'categories'=>[], 'images'=>[], 'services'=>[], 'testimonials'=>[], 'client_logos'=>[], 'news_items'=>[],
            'project'=>['name'=>'Sample project','image'=>'','description'=>'','client'=>'','url'=>''],
            'tool'=>['vendor'=>'','external'=>false,'url'=>''],
            'post'=>['title'=>'Sample post','image'=>'','date'=>'','content_mode'=>'blocks','content_valid'=>true,'block_html'=>'','description'=>''],
            'text_flash'=>['text'=>'','type'=>'success'], 'error'=>'', 'action'=>'#form',
            'nf3d'=>['count'=>120,'color'=>'#8a4fff','speed'=>0.05,'drift'=>0.4],
            'text_code'=>'404', 'text_error'=>'Not found', 'text_suggestion'=>'Return to the site home.',
            'button_continue'=>'Home', 'button_send'=>'Send',
        ];
        /* Frente AG: sem isto a previa usava theme.primary FIXO (#2563eb) e
           tokens/classes/componentes do rascunho nunca apareciam. Somente
           chaves `theme`/`theme_*` podem entrar; a cena sanitizada da AF
           prevalece sobre qualquer global de mesmo nome. */
        if ($themeState !== []) {
            $extra = \Reamur\System\Library\Theme\ThemePreviewState::globals($themeState);
            unset($extra['header'], $extra['footer']);
            $context = array_replace($context, $extra);
            $context['theme_scene_view'] = self::previewSceneView($sceneView);
        }
        return $env->render($key . '.twig', $context);
    }

    /**
     * KZ-01 (ADR 0083): renderiza a PAGINA INTEIRA de uma rota para o canvas do
     * KAZEditor-Theme: cabecalho do tema + rota + rodape, tudo em Twig Sandbox.
     *
     * Diferencas em relacao a renderSandboxPreview():
     *  - TODAS as fontes do tema entram no loader (um include de partial resolve
     *    para a versao do tema, como no Catalog publicado), nao so a fonte pedida;
     *  - o contexto vem de fora (ThemeCanvasSample), com listas preenchidas;
     *  - cada fonte falha sozinha: a fonte do tema que nao compila da lugar ao
     *    template padrao e o erro volta com a linha; a pagina nao some.
     * Mantem: allowlist de chaves, limite de tamanho, padroes proibidos, Twig
     * Sandbox, cache desligado e nenhum Twig\Markup aceito no contexto.
     *
     * @param array<string, mixed> $templates Fontes do tema (chave allowlistada => Twig).
     * @param array<string, mixed> $context   Contexto sintetico da rota.
     * @param array<string, mixed> $sceneView scene_view compilado (ThemeSceneCss).
     * @param array<string, mixed> $themeState Estado efetivo (ThemeSnapshot::resolve()).
     * @param bool $annotate KZ-02 (ADR 0084): cerca a saida de cada fonte allowlistada com
     *        comentarios `<!--kz:b:CHAVE-->`…`<!--kz:e:CHAVE-->` para o editor saber de qual
     *        fonte veio cada trecho da pagina. So constantes da allowlist entram no marcador.
     *        KZ-04 (ADR 0086): tambem poe `data-kz-el="CHAVE:N"` em cada tag de abertura
     *        escrita nas fontes e devolve `elements` (ThemeSourceMap::describe() por chave).
     * @return array{header:string, main:string, footer:string, errors:array<string,string>, fallbacks:list<string>, sources:array<string,string>, defaults:array<string,string>, elements:array<string,array<string,mixed>>}
     */
    public function renderThemePage(string $route, array $templates, array $context, array $sceneView = [], array $themeState = [], bool $annotate = false): array {
        if (!in_array($route, self::THEME_TEMPLATE_KEYS, true) || str_starts_with($route, 'partial/') || $route === 'common/footer') {
            throw new \InvalidArgumentException('Route outside the canvas allowlist.');
        }
        $safe = [];
        $errors = [];
        $fallbacks = [];
        foreach ($templates as $key => $source) {
            if (!is_string($key) || !in_array($key, self::THEME_TEMPLATE_KEYS, true) || !is_string($source) || $source === '') continue;
            if (ThemeSandbox::blocked($source)) {
                $errors[$key] = 'Source rejected by the theme allowlist.';
                $fallbacks[] = $key;
                continue;
            }
            // KZ-04: no canvas a fonte e lida com quebras `\n`, como o <textarea> do
            // editor a entrega; as posicoes do mapa de elementos dependem disso.
            $safe[$key . '.twig'] = $annotate ? \Reamur\System\Library\Theme\ThemeSourceMap::normalize($source) : $source;
        }
        // ADR 0087: politica unica (ThemeSandbox), a mesma da previa e do site
        // publicado. `slice` e `nl2br` estao nela porque os templates PADRAO do
        // Catalog usam.
        $catalog = new \Twig\Loader\FilesystemLoader([DIR_SYSTEM . '../catalog/view/template/']);
        // KZ-02: com $annotate, os templates PADRAO das chaves allowlistadas sao
        // lidos por caminho fixo (constante + extensao) para receber o marcador.
        $defaults = [];
        if ($annotate) {
            foreach (self::THEME_TEMPLATE_KEYS as $key) {
                $file = DIR_SYSTEM . '../catalog/view/template/' . $key . '.twig';
                $raw = is_file($file) ? file_get_contents($file) : false;
                if (is_string($raw) && $raw !== '' && strlen($raw) <= 131072) $defaults[$key] = \Reamur\System\Library\Theme\ThemeSourceMap::normalize($raw);
            }
        }
        // KZ-04 (ADR 0086): alem dos marcadores de trecho, cada tag de abertura
        // escrita na fonte recebe `data-kz-el="CHAVE:N"`. A anotacao so vale se
        // os tokens Twig da fonte anotada forem identicos aos da original; se
        // nao forem (ou a fonte nao lexar), a fonte segue sem anotacao.
        $lexer = null;
        $twigTokens = static function (string $code) use (&$lexer): ?array {
            try {
                $lexer ??= new \Twig\Environment(new \Twig\Loader\ArrayLoader([]), ['cache' => false]);
                $stream = $lexer->tokenize(new \Twig\Source($code, 'kz'));
                $out = [];
                while (!$stream->isEOF()) {
                    $token = $stream->next();
                    $out[] = $token->getType() === \Twig\Token::TEXT_TYPE ? 'text' : $token->getType() . ':' . (string)$token->getValue();
                }
                return $out;
            } catch (\Throwable $e) {
                return null;
            }
        };
        /** @var array<string,array{0:string,1:?array<string,mixed>}> $annotated fonte anotada + mapa, por chave e conteudo */
        $annotated = [];
        $mark = static function (string $key, string $source) use (&$annotated, $twigTokens): string {
            // Template com heranca nao aceita conteudo fora de blocos: fica sem marcador.
            if (preg_match('/\{%-?\s*extends\b/', $source) === 1) return $source;
            $id = $key . ':' . md5($source);
            if (!isset($annotated[$id])) {
                $map = '\Reamur\System\Library\Theme\ThemeSourceMap';
                $elements = $map::scan($source);
                $with = $map::annotate($key, $source, $elements);
                $entry = [$source, null];
                if ($with !== $source) {
                    $plain = $twigTokens($source);
                    if ($plain !== null && $plain === $twigTokens($with)) $entry = [$with, $map::describe($source, $elements)];
                }
                $annotated[$id] = $entry;
            }
            return '<!--kz:b:' . $key . '-->' . $annotated[$id][0] . '<!--kz:e:' . $key . '-->';
        };
        $build = function (array $sources) use ($catalog, $annotate, $defaults, $mark): \Twig\Environment {
            if ($annotate) {
                $marked = [];
                foreach ($defaults as $key => $raw) $marked[$key . '.twig'] = $mark($key, $raw);
                foreach ($sources as $name => $source) $marked[$name] = $mark(substr($name, 0, -5), $source);
                $sources = $marked;
            }
            $env = ThemeSandbox::environment(new \Twig\Loader\ChainLoader([new \Twig\Loader\ArrayLoader($sources), $catalog]));
            $this->applyThemeGlobals($env);
            return $env;
        };
        // Um aviso do PHP durante o render (ex.: array impresso como texto) derruba
        // a pagina no Catalog, onde o framework converte avisos em excecao. Aqui a
        // mesma conversao vale so durante este metodo: o canvas mostra o erro que
        // o site publicado teria, em vez de vazar o aviso para a saida.
        set_error_handler(static function (int $code, string $message, string $file = '', int $line = 0): bool {
            if (!(error_reporting() & $code)) return false;
            throw new \ErrorException($message, 0, $code, $file, $line);
        });
        try {
            // Cada fonte do tema e compilada sozinha antes de montar a pagina. A que
            // falhar (sintaxe ou politica do sandbox) sai do loader: o template
            // padrao ocupa o lugar dela e o erro e devolvido com a linha. Assim um
            // partial quebrado nao apaga a pagina inteira no canvas.
            $env = $build($safe);
            $broken = [];
            foreach (array_keys($safe) as $name) {
                $key = substr($name, 0, -5);
                try {
                    $env->load($name);
                } catch (\Twig\Error\Error $e) {
                    $line = $e->getTemplateLine();
                    $errors[$key] = substr($e->getRawMessage(), 0, 300) . ($line > 0 ? ' (line ' . $line . ')' : '');
                    $broken[] = $key;
                } catch (\Throwable $e) {
                    $errors[$key] = 'Template could not be compiled.';
                    $broken[] = $key;
                }
            }
            if ($broken !== []) {
                foreach ($broken as $key) {
                    unset($safe[$key . '.twig']);
                    $fallbacks[] = $key;
                }
                $env = $build($safe);
            }

            // Somente dados simples: nenhum objeto (nem Twig\Markup) chega ao sandbox.
            $clean = static function (mixed $value, int $depth = 0) use (&$clean): mixed {
                if (is_array($value)) {
                    if ($depth > 6) return [];
                    $out = [];
                    foreach ($value as $k => $v) $out[$k] = $clean($v, $depth + 1);
                    return $out;
                }
                return (is_scalar($value) || $value === null) ? $value : null;
            };
            $context = (array)$clean($context);
            $context['theme'] = ['primary' => '#2563eb', 'surface' => '#ffffff', 'vars' => [], 'flags' => [], 'classes' => [], 'matched' => []];
            $context['theme_scene'] = ['enabled' => false, 'scene' => '', 'slot' => 'hero', 'count' => 400, 'color' => '#8a4fff', 'speed' => 0.05];
            if ($themeState !== []) {
                $extra = \Reamur\System\Library\Theme\ThemePreviewState::globals($themeState);
                unset($extra['header'], $extra['footer']);
                $context = array_replace($context, $extra);
            }
            $context['theme_scene_view'] = self::previewSceneView($sceneView);
            $context['header'] = '';
            $context['footer'] = '';

            // Render das tres partes. Violacoes da politica do sandbox so aparecem
            // aqui: se a culpada for uma fonte do TEMA, ela sai do loader, o padrao
            // entra no lugar e a pagina e montada de novo (no maximo uma vez por chave).
            $slots = ['header' => 'partial/site_header', 'main' => $route, 'footer' => 'common/footer'];
            $attempt = 0;
            do {
                $retry = false;
                $runtime = [];
                $parts = ['header' => '', 'main' => '', 'footer' => ''];
                foreach ($slots as $slot => $key) {
                    try {
                        $parts[$slot] = $env->render($key . '.twig', $context);
                    } catch (\Twig\Error\Error $e) {
                        $source = $e->getSourceContext();
                        $where = $source ? (string)preg_replace('/\.twig$/', '', $source->getName()) : $key;
                        $line = $e->getTemplateLine();
                        $message = substr($e->getRawMessage(), 0, 300) . ($line > 0 ? ' (line ' . $line . ')' : '');
                        if (isset($safe[$where . '.twig'])) {
                            $errors[$where] = $message;
                            $fallbacks[] = $where;
                            unset($safe[$where . '.twig']);
                            $env = $build($safe);
                            $retry = true;
                            break;
                        }
                        $runtime[$where] = $message;
                    } catch (\Throwable $e) {
                        $runtime[$key] = 'Template could not be rendered.';
                    }
                }
            } while ($retry && ++$attempt <= count(self::THEME_TEMPLATE_KEYS));
        } finally {
            restore_error_handler();
        }
        $errors += $runtime;
        $header = $parts['header'];
        $main   = $parts['main'];
        $footer = $parts['footer'];
        // Temas fecham </body></html> no rodape; o documento da previa e do chamador.
        $footer = (string)preg_replace('~</\s*(body|html)\s*>~i', '', $footer);

        $sources = [];
        foreach (self::THEME_TEMPLATE_KEYS as $key) $sources[$key] = isset($safe[$key . '.twig']) ? 'theme' : 'default';
        // Fonte padrao so das chaves que realmente aparecem na pagina e que o tema
        // nao redefine: e o ponto de partida de "personalizar este trecho".
        $used = [];
        // KZ-04: mapa de elementos das fontes que aparecem nesta pagina (do tema ou padrao).
        $elements = [];
        if ($annotate) {
            $all = $header . $main . $footer;
            foreach (self::THEME_TEMPLATE_KEYS as $key) {
                if (!str_contains($all, '<!--kz:b:' . $key . '-->')) continue;
                $own = ($sources[$key] ?? '') === 'theme';
                if (!$own && isset($defaults[$key])) $used[$key] = $defaults[$key];
                $source = $own ? ($safe[$key . '.twig'] ?? null) : ($defaults[$key] ?? null);
                $entry = is_string($source) ? ($annotated[$key . ':' . md5($source)] ?? null) : null;
                if ($entry !== null && $entry[1] !== null) $elements[$key] = $entry[1];
            }
        }
        return ['header' => $header, 'main' => $main, 'footer' => $footer, 'errors' => $errors, 'fallbacks' => $fallbacks, 'sources' => $sources, 'defaults' => $used, 'elements' => $elements];
    }

    /** Mark only HTML fragments produced by trusted renderers/sanitizers; raw filter stays unavailable to theme source.
     * @param array<string,mixed> $data @param list<string> $keys @return array<string,mixed>
     */
    private function markTrustedHtml(array $data, array $keys): array {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_string($data[$key])) $data[$key] = new \Twig\Markup($data[$key], 'UTF-8');
        }
        if (isset($data['header']) && is_string($data['header'])) $data['header'] = new \Twig\Markup($data['header'], 'UTF-8');
        if (isset($data['footer']) && is_string($data['footer'])) $data['footer'] = new \Twig\Markup($data['footer'], 'UTF-8');
        return $data;
    }

    /** @param array<string, mixed> $data */
    public function render(string $route, array $data = []): string {
        $data = $this->markTrustedHtml($data, ['cms_home_content','cms_page_content']);
        if ($route === 'cms/page_info' && isset($data['description']) && is_string($data['description'])) {
            $data['description'] = new \Twig\Markup($data['description'], 'UTF-8');
        }
        if ($route === 'blog/blog_info' && isset($data['post']) && is_array($data['post'])) {
            foreach (['block_html','description'] as $key) {
                if (isset($data['post'][$key]) && is_string($data['post'][$key])) $data['post'][$key] = new \Twig\Markup($data['post'][$key], 'UTF-8');
            }
        }
        // S2 (ADR 0087): rota que o tema pode redefinir (ou que inclui trecho que
        // ele redefine) sai pelo sandbox quando ha fontes de tema publicadas.
        if ($this->themeSandbox !== null && in_array($route, self::THEME_TEMPLATE_KEYS, true)) {
            return $this->themeSandbox->render($route, $data, $this->twig);
        }
        return $this->twig->render($route . '.twig', $data);
    }
}
