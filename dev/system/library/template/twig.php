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
     * fixed allowlist, bounded source, fallback to filesystem loader. Admin and
     * Install do not invoke this method. No arbitrary paths or filesystem writes.
     * @param array<string, string> $templates
     */
    public function setThemeTemplates(array $templates): void {
        $allow = ['common/home','common/footer','partial/hero','partial/about','partial/portfolio','cms/page_info','catalog/project_list'];
        $safe = [];
        foreach ($templates as $key => $source) {
            if (!is_string($key) || !in_array($key, $allow, true) || !is_string($source) || strlen($source) > 131072) continue;
            if (preg_match('~<\s*script\b|javascript\s*:|\bon[a-z]+\s*=~i', $source)) continue;
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

    /** Render a saved source in a restrictive sandbox and scalar sample context. */
    public function renderSandboxPreview(string $key, string $source): string {
        $allow = ['common/home','common/footer','partial/hero','partial/about','partial/portfolio','cms/page_info','catalog/project_list'];
        if (!in_array($key, $allow, true) || strlen($source) > 131072 || preg_match('~<\s*script\b|javascript\s*:|\bon[a-z]+\s*=~i', $source)) {
            throw new \InvalidArgumentException('Template outside the preview allowlist.');
        }
        $policy = new \Twig\Sandbox\SecurityPolicy(
            ['if','for','set','block','extends','include','autoescape'],
            ['escape','e','raw','default','join','length','upper','lower','title','trim','date','json_encode'],
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
            'content'=>'Safe sample content', 'header'=>'', 'footer'=>'', 'cms_home_content'=>'Theme preview content'
        ]);
    }

    /** @param array<string, mixed> $data */
    public function render(string $route, array $data = []): string {
        return $this->twig->render($route . '.twig', $data);
    }
}
