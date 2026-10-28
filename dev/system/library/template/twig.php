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
    }

    /**
     * PLAN F10-03: opt-in Catalog-only template source overrides. Additive API;
     * fixed allowlist, bounded source, no raw/script/event-handler syntax, and
     * filesystem fallback. Admin and Install do not invoke it; no filesystem writes.
     * @param array<string, string> $templates
     */
    public function setThemeTemplates(array $templates): void {
        $allow = self::THEME_TEMPLATE_KEYS;
        $safe = [];
        foreach ($templates as $key => $source) {
            if (!is_string($key) || !in_array($key, $allow, true) || !is_string($source) || strlen($source) > 131072) continue;
            if (preg_match('~<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=~i', $source)) continue;
            $safe[$key . '.twig'] = $source;
        }
        if ($safe === []) return;
        $paths = [DIR_TEMPLATE];
        if ($this->directory !== '' && is_dir(DIR_TEMPLATE . $this->directory)) array_unshift($paths, DIR_TEMPLATE . rtrim($this->directory, '/') . '/');
        $loader = new \Twig\Loader\ChainLoader([
            new \Twig\Loader\ArrayLoader($safe),
            new \Twig\Loader\FilesystemLoader($paths)
        ]);
        $cacheDir = DIR_STORAGE . 'cache/template/';
        if ($this->cacheEnabled && !is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
        $this->twig = new \Twig\Environment($loader, [
            'autoescape'=>'html', 'cache'=>$this->cacheEnabled ? $cacheDir : false, 'auto_reload'=>true
        ]);
    }

    /**
     * Render an authenticated CMS Page preview with Catalog's actual shell and
     * templates. Catalog roots are fixed at DIR_SYSTEM; only published theme
     * templates from the existing allowlist are considered, and cache is off.
     * @param array<string,mixed> $pageData
     * @param array<string,mixed> $headerData
     * @param array<string,mixed> $footerData
     * @param array<string,string> $templates
     */
    public function renderCatalogPagePreview(array $pageData, array $headerData, array $footerData, array $templates = []): string {
        $allow = self::THEME_TEMPLATE_KEYS;
        $safe = [];
        foreach ($templates as $key => $source) {
            if (!is_string($key) || !in_array($key, $allow, true) || !is_string($source) || strlen($source) > 131072) continue;
            if (preg_match('~<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=~i', $source)) continue;
            $safe[$key . '.twig'] = $source;
        }
        $loader = new \Twig\Loader\ChainLoader([
            new \Twig\Loader\ArrayLoader($safe),
            new \Twig\Loader\FilesystemLoader([DIR_SYSTEM . '../catalog/view/template/'])
        ]);
        $env = new \Twig\Environment($loader, ['autoescape'=>'html','cache'=>false,'auto_reload'=>true]);
        $pageData['header'] = new \Twig\Markup($env->render('common/header.twig', $headerData), 'UTF-8');
        $pageData['footer'] = new \Twig\Markup($env->render('common/footer.twig', $footerData), 'UTF-8');
        $pageData = $this->markTrustedHtml($pageData, ['description','cms_home_content','cms_page_content']);
        return $env->render('cms/page_info.twig', $pageData);
    }

    /** Render a saved source in a restrictive sandbox with fixed, nonpersistent sample data. */
    public function renderSandboxPreview(string $key, string $source): string {
        $allow = self::THEME_TEMPLATE_KEYS;
        if (!in_array($key, $allow, true) || strlen($source) > 131072 || preg_match('~<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=~i', $source)) {
            throw new \InvalidArgumentException('Template outside the preview allowlist.');
        }
        $policy = new \Twig\Sandbox\SecurityPolicy(
            ['if','for','set','block','extends','include'],
            ['escape','e','default','join','length','upper','lower','title','trim','date','json_encode'],
            [], [], ['range','cycle','parent']
        );
        $loader = new \Twig\Loader\ChainLoader([
            new \Twig\Loader\ArrayLoader([$key . '.twig'=>$source]),
            new \Twig\Loader\FilesystemLoader([DIR_SYSTEM . '../catalog/view/template/'])
        ]);
        $env = new \Twig\Environment($loader, ['autoescape'=>'html','cache'=>false,'auto_reload'=>true]);
        $env->addExtension(new \Twig\Extension\SandboxExtension($policy, true));
        return $env->render($key . '.twig', [
            'theme'=>['primary'=>'#2563eb','surface'=>'#ffffff'], 'title'=>'Theme preview',
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
        ]);
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
        return $this->twig->render($route . '.twig', $data);
    }
}
