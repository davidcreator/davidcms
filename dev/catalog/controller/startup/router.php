<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Ação de rotas do catalog + RESOLUÇÃO SEO (F2-17, ADR 0017): keywords da
 * tabela rms_seo_url mapeiam caminho limpo único → rota gramatical interna:
 *   /jwa        → catalog/project.info|7
 *   /cookies    → cms/page.info|3
 * E o inverso: links montados com url->link('catalog/project.info|7') saem
 * amigáveis na reescrita. Rotas diretas (com '/') e aliases seguem valendo —
 * a resolução seo atua só em caminho de segmento único sem correspondente.
 */
namespace Reamur\Catalog\Controller\Startup;

class Router extends \Reamur\System\Engine\Controller {

    /** @var array<string, string> caminho limpo curto → rota interna longa */
    private const ALIASES = [
        'contato'   => 'common/contact',
        'contact'   => 'common/contact',
        'orcamento' => 'checkout/quote',
        'quote'     => 'checkout/quote',
        'portfolio' => 'catalog/project',
        'tools'     => 'tool/tool',
        // F4-07 (ADR 0031): endpoint dinâmico do índice público
        'sitemap.xml' => 'common/sitemap',
    ];

    /** Mapa das queries suportadas → prefixo de rota interna (padrão gramática F2-6; + tool_id F3-04) */
    private const SEO_ROUTES = [
        'project_id' => 'catalog/project.info|',
        'page_id'    => 'cms/page.info|',
        'tool_id'    => 'tool/tool.info|',
    ];

    /** Chamado pelo framework quando não há ?route= — resolve e prepara saída limpa */
    public function index(): string {
        $url = $this->registry->get('url');

        if ($url instanceof \Reamur\System\Library\Url) {
            $url->addRewrite([$this, 'rewrite']);
        }

        return $this->resolve();
    }

    /** Converte o REQUEST_URI do pedido em rota interna (ou) */
    private function resolve(): string {
        $uri  = (string)($this->request->server['REQUEST_URI'] ?? '');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '');

        // Base da app vem do config (site_url = HTTP_SERVER gerado pelo wizard):
        // fonte única e sensível ao deploy real — funciona em qualquer SAPI.
        $base = rtrim((string)(parse_url((string)$this->config->get('site_url', ''), PHP_URL_PATH) ?: ''), '/');

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = trim($path, '/');

        if ($path === '' || strtolower($path) === 'index.php') {
            return '';
        }

        if (array_key_exists($path, self::ALIASES)) {
            return self::ALIASES[$path];
        }

        // Segmento único sem barra: tentativa seo_url (keyword amigável).
        // Caminhos com '/' seguem a regra de rota direta (404 do dispatcher se inexistente).
        if (!str_contains($path, '/')) {
            $route = $this->resolveSeo($path);

            if ($route !== null) {
                return $route;
            }
        }

        return (string)preg_replace('/[^a-zA-Z0-9_\\/]/', '', $path);
    }

    /**
     * keyword da tabela seo_url → rota interna gramatical, ou null.
     * Cache por requisição (uma home hidrata vários links).
     */
    private function resolveSeo(string $keyword): ?string {
        $this->load->model('design/seo_url');

        $query_key = $this->model_design_seo_url->getQueryByKeyword($keyword);

        if ($query_key === null) {
            return null;
        }

        // 'project_id=7' / 'page_id=3' → prefixo de rota declarado + id validado
        [$k, $id] = array_pad(explode('=', $query_key, 2), 2, '');
        $id = (int)$id;

        if ($id <= 0 || !isset(self::SEO_ROUTES[$k])) {
            return null;
        }

        return self::SEO_ROUTES[$k] . $id;
    }

    /**
     * Reescrita transparente de links internos:
     *   - gramática com método/args tem seo_url ↹ keyword quando disponível;
     *     sem keyword, mantém 'index.php?route=...' (comportamento estável anterior);
     *   - rotas diretas tradicionais: 'grupo/modulo?x=y' (+ alias curto).
     */
    public function rewrite(string $url): string {
        return (string)preg_replace_callback(
            '#index\.php\?route=([a-zA-Z0-9_/.|]+)(?:&(amp;)?(.*))?#',
            function (array $m): string {
                $route = (string)$m[1];

                // SEO: 'catalog/project.info|7' / 'cms/page.info|3' / 'tool/tool.info|1' → '/jwa' (keyword do idioma)
                if (preg_match('/^(catalog\/project|cms\/page|tool\/tool)\.info\|(\d+)$/', $route, $g)) {
                    $prefix = ['catalog/project' => 'project_id=', 'cms/page' => 'page_id=', 'tool/tool' => 'tool_id='];
                    $query_key = $prefix[$g[1]] . (int)$g[2];

                    $this->load->model('design/seo_url');

                    $keyword = $this->model_design_seo_url->getKeywordByQuery($query_key);

                    if ($keyword !== null && $keyword !== '') {
                        $query = (isset($m[3]) && $m[3] !== '') ? '?' . str_replace('&amp;', '&', $m[3]) : '';

                        return $keyword . $query;
                    }

                    return (string)$m[0]; // sem keyword: não-limpo (estável)
                }

                if (str_contains($route, '.') || str_contains($route, '|')) {
                    return (string)$m[0]; // demais métodos/args: link não-limpo (Adendo F2-6)
                }

                foreach (self::ALIASES as $clean => $long) {
                    if ($route === $long) {
                        $route = $clean;

                        break;
                    }
                }

                $query = (isset($m[3]) && $m[3] !== '') ? '?' . str_replace('&amp;', '&', $m[3]) : '';

                return $route . $query;
            },
            $url
        );
    }
}
