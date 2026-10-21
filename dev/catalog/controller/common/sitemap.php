<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * common/sitemap (CATALOG) — sitemap.xml DINÂMICO (F4-07, ADR 0031):
 *  · home + aliases públicas estáticas (portfolio, tools, contato, orcamento)
 *  · keywords de rms_seo_url resolvidas para rotas e FILTRADAS por entidade
 *    ativa (status=1 via models de conteúdo — projeto/page/tool/blog despublicado
 *    sai do índice junto); dedup por alvo preferindo language_id=1 (en-us,
 *    ADR 0001 — matriz hreflang é tarefa futura, renúncia na ADR)
 *  · lastmod de date_modified/date_added quando a coluna existe
 * Descoberta: alias 'sitemap.xml' no startup/router + <link rel="sitemap">
 * injetado no header (robots.txt estático não hardcoda domínio — ADR 0031).
 */
namespace Reamur\Catalog\Controller\Common;

class Sitemap extends \Reamur\System\Engine\Controller {

    /** Prefixo de rota por query-chave — espelha SEO_ROUTES do startup/router (comentário lá) */
    private const TARGET_ROUTES = [
        'project_id' => 'catalog/project.info|',
        'page_id'    => 'cms/page.info|',
        'tool_id'    => 'tool/tool.info|',
        // F7-02 (ADR 0043): posts do blog interno entram no índice
        'blog_id'    => 'blog/blog.info|',
    ];

    public function index(): void {
        $urls = [];

        // Home — raiz da publicação
        $urls[] = ['loc' => (string)$this->config->get('site_url'), 'lastmod' => '', 'changefreq' => 'weekly', 'priority' => '1.0'];

        // Aliases estáticas (mesmo conjunto público do startup/router)
        foreach ([
            'catalog/project' => ['changefreq' => 'weekly',  'priority' => '0.8'],
            'tool/tool'       => ['changefreq' => 'weekly',  'priority' => '0.8'],
            'blog/blog'       => ['changefreq' => 'weekly',  'priority' => '0.7'],
            'common/contact'  => ['changefreq' => 'monthly', 'priority' => '0.5'],
            'checkout/quote'  => ['changefreq' => 'monthly', 'priority' => '0.5'],
        ] as $route => $seoHints) {
            $urls[] = ['loc' => $this->url->link($route), 'lastmod' => ''] + $seoHints;
        }

        // Keywords → rotas com entidade ativa, dedup por alvo preferindo en-us
        foreach ($this->resolveSeoEntries() as $entry) {
            $urls[] = [
                'loc'        => $this->url->link($entry['route']),
                'lastmod'    => $entry['lastmod'],
                'changefreq' => 'monthly',
                'priority'   => '0.6',
            ];
        }

        $data['urls'] = $urls;

        $this->response->addHeader('Content-Type: application/xml; charset=utf-8');

        $this->response->setOutput($this->load->view('common/sitemap', $data));
    }

    /**
     * keyword deduplicada → rota gramatical + lastmod da entidade, ou pula
     * quando alvo inexistente/inativo (status ≠ 1 filtrado nos models).
     *
     * @return array<int, array{route: string, lastmod: string}>
     */
    private function resolveSeoEntries(): array {
        $this->load->model('design/seo_url');

        $byTarget = []; // value (query) → keyword preferida (language_id=1 primeiro, depois 1ª ocorrência)

        foreach ($this->model_design_seo_url->getEntriesForSitemap() as $row) {
            $target = (string)$row['key'];

            if ($target === '') {
                continue;
            }

            if (!isset($byTarget[$target])) {
                $byTarget[$target] = null;
            }

            if ($byTarget[$target] === null || (int)$row['language_id'] === 1) {
                $byTarget[$target] = (string)$row['value'];
            }
        }

        $out = [];

        foreach ($byTarget as $target => $keyword) {
            if ($keyword === null || $keyword === '') {
                continue;
            }

            [$k, $id] = array_pad(explode('=', $target, 2), 2, '');
            $id = (int)$id;

            if ($id <= 0 || !isset(self::TARGET_ROUTES[$k])) {
                continue;
            }

            $entity = $this->loadActiveEntity($k, $id);

            if ($entity === null) {
                continue; // despublicado/inexistente — NÃO entra no índice
            }

            $out[] = [
                'route'   => self::TARGET_ROUTES[$k] . $id,
                'lastmod' => $this->lastmodOf($entity),
            ];
        }

        return $out;
    }

    /**
     * Entidade ativa (status=1) ou null. Models catalog existentes já filtram
     * — reutilizados sem novos métodos (escopo cirúrgico).
     *
     * @return array<string, mixed>|null
     */
    private function loadActiveEntity(string $kind, int $id): ?array {
        $found = null;

        if ($kind === 'project_id') {
            $this->load->model('catalog/project');
            $found = $this->model_catalog_project->getProject($id);
        } elseif ($kind === 'page_id') {
            $this->load->model('catalog/page');
            $found = $this->model_catalog_page->getPage($id);
        } elseif ($kind === 'tool_id') {
            $this->load->model('tool/tool');
            $found = $this->model_tool_tool->getTool($id);
        } elseif ($kind === 'blog_id') {
            $this->load->model('blog/blog');
            $found = $this->model_blog_blog->getPost($id);
        }

        $row = is_array($found) ? $found : [];

        return $row ? $row : null;
    }

    /** @param array<string, mixed> $entity */
    private function lastmodOf(array $entity): string {
        $raw = (string)($entity['date_modified'] ?? $entity['date_added'] ?? '');

        $ts = $raw !== '' ? strtotime($raw) : false;

        return $ts !== false ? date('Y-m-d', $ts) : '';
    }
}
