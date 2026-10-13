<?php
namespace Reamur\System\Library\Template;

/**
 * Class TwigExtension
 * Extensão para o sistema de templates Twig com funcionalidades adicionais
 */
class TwigExtension extends \Twig\Extension\AbstractExtension {
    
    protected array $config = [];
    protected array $cache = [];
    
    /**
     * Constructor
     * @param array $config Configurações da extensão
     */
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'cache_enabled' => true,
            'minify_html' => false,
            'asset_version' => '1.0',
            'base_url' => '',
            'debug_mode' => false
        ], $config);
    }

    /**
     * Retorna nome da extensão
     */
    public function getName(): string {
        return 'reamur_twig_extension';
    }

    /**
     * Registra filtros customizados
     */
    public function getFilters(): array {
        return [
            new \Twig\TwigFilter('asset', [$this, 'asset']),
            new \Twig\TwigFilter('truncate', [$this, 'truncate']),
            new \Twig\TwigFilter('slug', [$this, 'slug']),
            new \Twig\TwigFilter('format_date', [$this, 'formatDate']),
            new \Twig\TwigFilter('format_currency', [$this, 'formatCurrency']),
            new \Twig\TwigFilter('minify_css', [$this, 'minifyCss']),
            new \Twig\TwigFilter('minify_js', [$this, 'minifyJs']),
            new \Twig\TwigFilter('encrypt', [$this, 'encrypt']),
            new \Twig\TwigFilter('decrypt', [$this, 'decrypt'])
        ];
    }

    /**
     * Registra funções customizadas
     */
    public function getFunctions(): array {
        return [
            new \Twig\TwigFunction('config', [$this, 'getConfig']),
            new \Twig\TwigFunction('csrf_token', [$this, 'csrfToken']),
            new \Twig\TwigFunction('url', [$this, 'generateUrl']),
            new \Twig\TwigFunction('include_partial', [$this, 'includePartial']),
            new \Twig\TwigFunction('cache_bust', [$this, 'cacheBust']),
            new \Twig\TwigFunction('is_mobile', [$this, 'isMobile']),
            new \Twig\TwigFunction('get_breadcrumb', [$this, 'getBreadcrumb']),
            new \Twig\TwigFunction('dump_debug', [$this, 'dumpDebug'])
        ];
    }

    /**
     * Registra testes customizados
     */
    public function getTests(): array {
        return [
            new \Twig\TwigTest('numeric', [$this, 'isNumeric']),
            new \Twig\TwigTest('email', [$this, 'isEmail']),
            new \Twig\TwigTest('url', [$this, 'isUrl']),
            new \Twig\TwigTest('json', [$this, 'isJson'])
        ];
    }

    // FILTROS

    /**
     * Gera URL para assets com versionamento
     */
    public function asset(string $path): string {
        $baseUrl = rtrim($this->config['base_url'], '/');
        $version = $this->config['asset_version'];
        return $baseUrl . '/' . ltrim($path, '/') . '?v=' . $version;
    }

    /**
     * Trunca texto com opção de preservar palavras
     */
    public function truncate(string $text, int $length = 100, string $suffix = '...', bool $preserveWords = true): string {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $length);
        
        if ($preserveWords) {
            $lastSpace = mb_strrpos($truncated, ' ');
            if ($lastSpace !== false) {
                $truncated = mb_substr($truncated, 0, $lastSpace);
            }
        }

        return $truncated . $suffix;
    }

    /**
     * Converte string em slug URL-friendly
     */
    public function slug(string $text): string {
        $text = mb_strtolower($text);
        $text = preg_replace('/[áàâãäå]/u', 'a', $text);
        $text = preg_replace('/[éèêë]/u', 'e', $text);
        $text = preg_replace('/[íìîï]/u', 'i', $text);
        $text = preg_replace('/[óòôõö]/u', 'o', $text);
        $text = preg_replace('/[úùûü]/u', 'u', $text);
        $text = preg_replace('/[ç]/u', 'c', $text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * Formata data com localização
     */
    public function formatDate(string $date, string $format = 'd/m/Y H:i', string $locale = 'pt_BR'): string {
        try {
            $dateTime = new \DateTime($date);
            return $dateTime->format($format);
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Formata valores monetários
     */
    public function formatCurrency(float $value, string $currency = 'BRL', string $locale = 'pt_BR'): string {
        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            return $formatter->formatCurrency($value, $currency);
        }
        
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    /**
     * Minifica CSS removendo comentários e espaços extras
     */
    public function minifyCss(string $css): string {
        if (!$this->config['minify_html']) {
            return $css;
        }

        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
        return trim($css);
    }

    /**
     * Minifica JavaScript básico
     */
    public function minifyJs(string $js): string {
        if (!$this->config['minify_html']) {
            return $js;
        }

        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        $js = preg_replace('/\/\/.*$/m', '', $js);
        $js = preg_replace('/\s+/', ' ', $js);
        return trim($js);
    }

    /**
     * Criptografia simples (base64)
     */
    public function encrypt(string $data): string {
        return base64_encode($data);
    }

    /**
     * Descriptografia simples (base64)
     */
    public function decrypt(string $data): string {
        return base64_decode($data) ?: $data;
    }

    // FUNÇÕES

    /**
     * Obtém configuração do sistema
     */
    public function getConfig(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    /**
     * Gera token CSRF
     */
    public function csrfToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    /**
     * Gera URL completa
     */
    public function generateUrl(string $path, array $params = []): string {
        $baseUrl = rtrim($this->config['base_url'], '/');
        $url = $baseUrl . '/' . ltrim($path, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }

    /**
     * Inclui partial/fragmento de template
     */
    public function includePartial(string $partial, array $data = []): string {
        $cacheKey = 'partial_' . md5($partial . serialize($data));
        
        if ($this->config['cache_enabled'] && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Simulação de inclusão de partial
        $content = "<!-- Partial: {$partial} -->";
        
        if ($this->config['cache_enabled']) {
            $this->cache[$cacheKey] = $content;
        }
        
        return $content;
    }

    /**
     * Cache busting para arquivos estáticos
     */
    public function cacheBust(string $file): string {
        $filePath = rtrim(DIR_REAMUR, '/') . '/public/' . ltrim($file, '/');
        
        if (file_exists($filePath)) {
            return $file . '?t=' . filemtime($filePath);
        }
        
        return $file . '?t=' . time();
    }

    /**
     * Detecta se é dispositivo móvel
     */
    public function isMobile(): bool {
        return isset($_SERVER['HTTP_USER_AGENT']) && 
               preg_match('/Mobile|Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * Gera breadcrumb baseado na URL atual
     */
    public function getBreadcrumb(string $separator = ' > '): array {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $segments = array_filter(explode('/', trim($path, '/')));
        
        $breadcrumb = [['name' => 'Home', 'url' => '/']];
        $currentPath = '';
        
        foreach ($segments as $segment) {
            $currentPath .= '/' . $segment;
            $breadcrumb[] = [
                'name' => ucfirst(str_replace(['-', '_'], ' ', $segment)),
                'url' => $currentPath
            ];
        }
        
        return $breadcrumb;
    }

    /**
     * Debug dump (apenas em modo debug)
     */
    public function dumpDebug($var): string {
        if (!$this->config['debug_mode']) {
            return '';
        }
        
        ob_start();
        var_dump($var);
        $output = ob_get_clean();
        
        return '<pre class="debug-dump">' . htmlspecialchars($output) . '</pre>';
    }

    // TESTES

    /**
     * Testa se valor é numérico
     */
    public function isNumeric($value): bool {
        return is_numeric($value);
    }

    /**
     * Testa se valor é email válido
     */
    public function isEmail($value): bool {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Testa se valor é URL válida
     */
    public function isUrl($value): bool {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Testa se valor é JSON válido
     */
    public function isJson($value): bool {
        if (!is_string($value)) {
            return false;
        }
        
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Limpa cache interno
     */
    public function clearCache(): void {
        $this->cache = [];
    }

    /**
     * Define configuração
     */
    public function setConfig(string $key, $value): void {
        $this->config[$key] = $value;
    }
}