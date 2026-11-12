<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @license   https://opensource.org/licenses/GPL-3.0
 *
 * KZ-01 (ADR 0083) — dados de amostra do canvas do KAZEditor-Theme.
 *
 * O canvas mostra a PAGINA INTEIRA do rascunho (cabecalho + rota + rodape),
 * e nao um template isolado. Para isso cada rota precisa de um contexto
 * representativo: listas preenchidas, textos e imagens. Esta classe e a
 * fonte unica desse contexto.
 *
 * Regras:
 *  - PURA: nenhum banco, sessao, request, rede, cookie ou escrita. A unica
 *    leitura e de arquivos de idioma do Catalog, por caminho FIXO e
 *    allowlistado (codigo de idioma validado + nome de arquivo constante).
 *  - Nenhum dado real do site entra na previa: tudo aqui e sintetico.
 *  - Imagens sao data URI SVG deterministicos (a previa roda com CSP
 *    `img-src data:`).
 *  - As rotas aceitas saem de ThemePackage::TEMPLATE_KEYS: nao existe uma
 *    segunda allowlist de templates.
 */
namespace Reamur\System\Library\Theme;

final class ThemeCanvasSample {
    /** Fontes que nao sao paginas: entram como parte de uma pagina. */
    private const NOT_A_PAGE = ['common/footer'];

    /** Arquivos de idioma do Catalog lidos por rota (alem dos comuns). */
    private const LANGUAGE_FILES = [
        'common/home'          => ['common/home'],
        'common/contact'       => ['common/contact'],
        'cms/page_info'        => ['cms/page'],
        'catalog/project_list' => ['catalog/project'],
        'catalog/project_info' => ['catalog/project'],
        'blog/blog_list'       => ['blog/blog'],
        'blog/blog_info'       => ['blog/blog'],
        'tool/tool_list'       => ['tool/tool'],
        'tool/tool_info'       => ['tool/tool'],
        'checkout/quote'       => ['checkout/quote'],
        'error/not_found'      => ['error/not_found'],
    ];
    private const LANGUAGE_COMMON = ['common/header', 'common/footer'];

    /** Rota => chave de layout do contrato Cms\PageLayout (secoes da pagina). */
    private const LAYOUT_KEYS = [
        'common/home'          => 'home',
        'common/contact'       => 'contact',
        'catalog/project_list' => 'portfolio',
        'catalog/project_info' => 'portfolio_detail',
        'blog/blog_list'       => 'blog',
        'blog/blog_info'       => 'blog_post',
        'tool/tool_list'       => 'tools',
        'tool/tool_info'       => 'tool_detail',
        'checkout/quote'       => 'quote',
        'error/not_found'      => 'not_found',
    ];

    /** Larguras dos quadros do canvas (px). Altura = viewport do aparelho. */
    private const DEVICES = [
        'desktop' => ['w' => 1440, 'h' => 900],
        'tablet'  => ['w' => 768,  'h' => 1024],
        'mobile'  => ['w' => 390,  'h' => 844],
    ];

    /** @return list<string> Rotas que o canvas sabe montar como pagina. */
    public static function routes(): array {
        $out = [];
        foreach (ThemePackage::TEMPLATE_KEYS as $key) {
            if (str_starts_with($key, 'partial/') || in_array($key, self::NOT_A_PAGE, true)) continue;
            $out[] = $key;
        }
        return $out;
    }

    public static function isRoute(string $route): bool {
        return in_array($route, self::routes(), true);
    }

    /** @return array<string, array{w:int,h:int}> */
    public static function devices(): array {
        return self::DEVICES;
    }

    /**
     * Textos do Catalog para a rota, no idioma pedido (fallback en-us).
     * Somente strings escalares curtas sao devolvidas.
     * @return array<string, string>
     */
    public static function strings(string $language, string $route): array {
        if (!self::isRoute($route) || !defined('DIR_SYSTEM')) return [];
        $base = DIR_SYSTEM . '../catalog/language/';
        $codes = ['en-us'];
        if ($language !== 'en-us' && preg_match('/^[a-z]{2}-[a-z]{2}$/D', $language) === 1 && is_dir($base . $language)) {
            $codes[] = $language;
        }
        $files = array_merge(self::LANGUAGE_COMMON, self::LANGUAGE_FILES[$route] ?? []);
        $out = [];
        foreach ($codes as $code) {
            foreach ($files as $file) {
                foreach (self::readLanguageFile($base . $code . '/' . $file . '.php') as $key => $value) {
                    $out[$key] = $value;
                }
            }
        }
        return $out;
    }

    /**
     * Contexto sintetico completo para renderizar a pagina da rota.
     * @param array<string, string> $strings Textos de idioma (ver strings()).
     * @return array<string, mixed>
     */
    public static function context(string $route, array $strings = []): array {
        $list = static function (int $count, callable $make): array {
            $out = [];
            for ($i = 1; $i <= $count; $i++) $out[] = $make($i);
            return $out;
        };
        $lorem = 'Sample text so the layout can be judged with realistic length and rhythm.';
        $project = static fn(int $i): array => [
            'project_id' => $i, 'name' => 'Sample project ' . $i, 'title' => 'Sample project ' . $i,
            'client' => 'Client ' . $i, 'date' => '2026-01-0' . min($i, 9), 'summary' => $lorem, 'excerpt' => $lorem,
            'description' => $lorem, 'href' => '#project-' . $i, 'url' => '#project-' . $i,
            'image' => self::image(640, 400, 'Project ' . $i, $i), 'external' => false, 'featured' => $i === 1,
        ];
        $post = static fn(int $i): array => [
            'post_id' => $i, 'title' => 'Sample post ' . $i, 'name' => 'Sample post ' . $i, 'date' => '2026-02-0' . min($i, 9),
            'excerpt' => $lorem, 'summary' => $lorem, 'href' => '#post-' . $i, 'url' => '#post-' . $i,
            'image' => self::image(640, 360, 'Post ' . $i, $i + 3), 'source' => 'Blog',
        ];
        $tool = static fn(int $i): array => [
            'tool_id' => $i, 'name' => 'Sample tool ' . $i, 'vendor' => 'Vendor ' . $i, 'description' => $lorem,
            'href' => '#tool-' . $i, 'url' => '#tool-' . $i, 'external' => false,
            'image' => self::image(320, 200, 'Tool ' . $i, $i + 6),
        ];
        $category = static fn(int $i): array => [
            'category_id' => $i, 'name' => 'Category ' . $i, 'href' => '#category-' . $i, 'url' => '#category-' . $i,
            'active' => false, 'image' => self::image(320, 200, 'Category ' . $i, $i),
        ];

        $context = [
            // Navegacao e marca (partial/site_header recebe estas chaves).
            'home' => '#home', 'text_brand' => 'Sample brand', 'text_languages' => 'Languages',
            'text_nav_home' => 'Home', 'text_nav_about' => 'About', 'text_nav_blog' => 'Blog',
            'text_nav_portfolio' => 'Portfolio', 'text_nav_contact' => 'Contact', 'text_nav_quote' => 'Request a quote',
            'nav_about' => '#about', 'nav_blog' => '#blog', 'nav_portfolio' => '#portfolio',
            'nav_contact' => '#contact', 'nav_quote' => '#quote',
            'languages' => [
                ['code' => 'en-us', 'name' => 'English', 'active' => true, 'href' => '#language-en'],
                ['code' => 'pt-br', 'name' => 'Português', 'active' => false, 'href' => '#language-pt'],
            ],
            // Pagina.
            'title' => 'Theme preview', 'heading_title' => 'Sample heading', 'description' => $lorem,
            'content' => $lorem, 'text_lead' => $lorem, 'text_tagline' => 'Sample tagline',
            'text_all' => 'All', 'text_filter' => 'Filter', 'filter_all' => ['active' => true, 'href' => '#all'],
            'action' => '#form', 'error' => '', 'text_flash' => ['text' => '', 'type' => 'success'],
            'button_send' => 'Send', 'button_continue' => 'Home', 'button_back_home' => 'Home',
            'quote' => '#quote', 'contact' => '#contact', 'portfolio' => '#portfolio', 'news_blog_href' => '#blog',
            'active_category' => 0,
            // Home: a ordem real vem do tema (sections) quando definida.
            'cms_home_sections' => (new SectionRegistry())->defaults(), 'cms_home_content' => '',
            'cms_page_sections' => isset(self::LAYOUT_KEYS[$route])
                ? (new \Reamur\System\Library\Cms\PageLayout())->defaults(self::LAYOUT_KEYS[$route], false)
                : [],
            'cms_page_content' => '',
            'about_paragraphs' => [$lorem, $lorem],
            'skill_chips' => ['Design', 'Code', 'Brand', 'Motion'],
            'skill_groups' => $list(3, static fn(int $i): array => ['title' => 'Skill group ' . $i, 'items' => ['Skill A', 'Skill B', 'Skill C']]),
            'method_steps' => $list(4, static fn(int $i): array => ['n' => $i, 'title' => 'Step ' . $i, 'text' => $lorem]),
            'identity_reasons' => $list(3, static fn(int $i): array => ['title' => 'Reason ' . $i, 'text' => $lorem]),
            'service_groups' => $list(3, static fn(int $i): array => ['title' => 'Service ' . $i, 'text' => $lorem, 'items' => ['Item A', 'Item B', 'Item C']]),
            'services' => $list(3, static fn(int $i): array => ['name' => 'Service ' . $i, 'title' => 'Service ' . $i, 'description' => $lorem, 'href' => '#service-' . $i]),
            'testimonials' => $list(3, static fn(int $i): array => [
                'author' => 'Person ' . $i, 'name' => 'Person ' . $i, 'company' => 'Company ' . $i,
                'text' => $lorem, 'description' => $lorem, 'image' => self::image(96, 96, 'P' . $i, $i),
            ]),
            'categories' => $list(3, $category),
            'projects' => $list(6, $project),
            'tools' => $list(4, $tool),
            'news_items' => $list(3, $post),
            'posts' => $list(4, $post),
            'client_logos' => $list(5, static fn(int $i): array => [
                'name' => 'Client ' . $i, 'image' => self::image(200, 80, 'Client ' . $i, $i + 2), 'url' => '#client-' . $i, 'href' => '#client-' . $i,
            ]),
            'images' => $list(3, static fn(int $i): array => ['src' => self::image(960, 540, 'Image ' . $i, $i), 'alt' => 'Sample image ' . $i]),
            'public_custom_field_groups' => [['label' => 'Details', 'fields' => [['label' => 'Year', 'value' => '2026'], ['label' => 'Role', 'value' => 'Design']]]],
            'project' => $project(1),
            'tool' => $tool(1),
            'post' => $post(1) + ['content_mode' => 'html', 'content_valid' => true, 'block_html' => '', 'description' => $lorem],
            'legal' => [['name' => 'Privacy', 'href' => '#privacy'], ['name' => 'Terms', 'href' => '#terms']],
            'socials' => [['name' => 'Instagram', 'href' => '#instagram'], ['name' => 'GitHub', 'href' => '#github']],
            // Cenas Three.js nao executam na previa (sem script): so o fallback.
            'portfolio3d' => ['enabled' => false, 'frame_limit' => 0, 'images' => []],
            'tools3d' => ['enabled' => false, 'items' => []],
            'nf3d' => ['count' => 120, 'color' => '#8a4fff', 'speed' => 0.05, 'drift' => 0.4],
            'text_code' => '404', 'text_error' => 'Not found', 'text_suggestion' => 'Return to the site home.',
            'text_powered' => 'ReamurCMS', 'text_copyright' => '© Sample brand', 'text_legal' => 'Legal',
        ];
        // Textos reais do Catalog vencem os rotulos genericos.
        foreach ($strings as $key => $value) {
            if (is_string($key) && is_string($value)) $context[$key] = $value;
        }
        // header/footer NUNCA vem daqui: o adaptador monta as partes da pagina.
        unset($context['header'], $context['footer']);
        $context['canvas_route'] = self::isRoute($route) ? $route : 'common/home';
        return $context;
    }

    /** Imagem de amostra: SVG deterministico, sem texto do usuario. */
    public static function image(int $w, int $h, string $label, int $tone = 0): string {
        $w = max(16, min(1920, $w));
        $h = max(16, min(1080, $h));
        $tones = ['#334155', '#3f3f46', '#1e3a8a', '#134e4a', '#4c1d95', '#7c2d12', '#365314', '#831843'];
        $fill = $tones[abs($tone) % count($tones)];
        $label = (string)preg_replace('/[^A-Za-z0-9 ]/', '', $label);
        $size = max(10, (int)round(min($w, $h) / 6));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">'
             . '<rect width="' . $w . '" height="' . $h . '" fill="' . $fill . '"/>'
             . '<path d="M0 ' . $h . 'L' . (int)($w * 0.35) . ' ' . (int)($h * 0.45) . 'L' . (int)($w * 0.6) . ' ' . (int)($h * 0.75)
             . 'L' . (int)($w * 0.78) . ' ' . (int)($h * 0.55) . 'L' . $w . ' ' . $h . 'Z" fill="#ffffff" fill-opacity="0.12"/>'
             . '<text x="' . (int)($w / 2) . '" y="' . (int)($h / 2) . '" fill="#ffffff" fill-opacity="0.82" font-family="sans-serif" font-size="'
             . $size . '" text-anchor="middle" dominant-baseline="middle">' . $label . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Le um arquivo de idioma do Catalog em escopo isolado.
     * @return array<string, string>
     */
    private static function readLanguageFile(string $path): array {
        if (!is_file($path)) return [];
        $read = static function (string $file): array {
            $_ = [];
            include $file;
            return $_;
        };
        $out = [];
        foreach ($read($path) as $key => $value) {
            if (is_string($key) && is_string($value) && strlen($value) <= 4096) $out[$key] = $value;
        }
        return $out;
    }
}
