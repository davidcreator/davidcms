<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Renderizador interno dos slots editoriais ligados às rotas do Catalog (F10-01).
 */
namespace Reamur\Catalog\Controller\Cms;

class Slot extends \Reamur\System\Engine\Controller {

    /** Renderiza conteúdo público validado de uma chave de rota fixa. */
    public function render(string $system_key = '', int $apply_meta = 1): string {
        $system_key = strtolower(trim($system_key));
        $allowed = ['home','portfolio','portfolio_detail','blog','blog_post','tools','tool_detail','contact','quote','not_found'];
        if (!in_array($system_key, $allowed, true)) return '';

        $cache = (array)$this->config->get('_cms_system_page_cache', []);
        if (array_key_exists($system_key, $cache)) {
            $page = (array)$cache[$system_key];
        } else {
            $this->load->model('catalog/page');
            $page = $this->model_catalog_page->getSystemPage($system_key);
            $cache[$system_key] = $page;
            $this->config->set('_cms_system_page_cache', $cache);
        }
        if (!$page) {
            $layoutCache = (array)$this->config->get('_cms_system_page_layout_cache', []);
            $layoutCache[$system_key] = $this->cms_page_layout->defaults($system_key, false);
            $this->config->set('_cms_system_page_layout_cache', $layoutCache);
            return '';
        }

        if ($apply_meta === 1) {
            if ((string)($page['meta_title'] ?? '') !== '') {
                $this->document->setTitle((string)$page['meta_title']);
            }
            if ((string)($page['meta_description'] ?? '') !== '') {
                $this->document->setDescription((string)$page['meta_description']);
            }
        }

        $content = (string)($page['description'] ?? '');
        $layoutCache = (array)$this->config->get('_cms_system_page_layout_cache', []);
        $layout = $this->cms_page_layout->decode($content, $system_key);
        $hasEditorial = trim($content) !== '';
        $invalidLayoutEnvelope = str_starts_with($content, \Reamur\System\Library\Cms\PageLayout::PREFIX) && $layout === null;
        if ($invalidLayoutEnvelope) {
            // Nunca tratar um envelope estrutural inválido como HTML legado.
            $content = '';
            $hasEditorial = false;
        }
        if ($layout !== null) {
            $layoutCache[$system_key] = $layout['sections'];
            $hasEditorial = !empty($layout['content']['blocks']);
            $content = 'REAMURCMS_BDRE_JSON_V2:' . (new \Reamur\System\Library\Blog\BlockDocument(true))->serializeDocument($layout['content']);
        } elseif (str_starts_with($content, 'REAMURCMS_BDRE_JSON_V2:')) {
            $decoded = (new \Reamur\System\Library\Blog\BlockDocument(true))->deserializeDocument(substr($content, strlen('REAMURCMS_BDRE_JSON_V2:')));
            $hasEditorial = !empty($decoded['document']['blocks']) && !empty($decoded['valid']);
        }
        if (!array_key_exists($system_key, $layoutCache)) {
            $layoutCache[$system_key] = $this->cms_page_layout->defaults($system_key, $hasEditorial);
        }
        $this->config->set('_cms_system_page_layout_cache', $layoutCache);
        $htmlCache = (array)$this->config->get('_cms_system_page_html_cache', []);
        if (array_key_exists($system_key, $htmlCache)) return (string)$htmlCache[$system_key];

        if ($content === '') return $this->cacheHtml($system_key, '');

        $prefix = 'REAMURCMS_BDRE_JSON_V2:';
        if (!str_starts_with($content, $prefix)) return $this->cacheHtml($system_key, $content);

        $downloadRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/download/blog';
        $audioRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/audio/blog';
        $videoRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/video/blog';
        $assetBase = (string)$this->config->get('site_url', '');
        $rendered = (new \Reamur\System\Library\Blog\BlockRenderer($downloadRoot, $audioRoot, $videoRoot))
            ->renderJson(substr($content, strlen($prefix)), 2, $assetBase);

        if (empty($rendered['valid'])) return $this->cacheHtml($system_key, '');

        $this->document->addStyle($assetBase . 'view/css/blog-blocks.css');
        if (!empty($rendered['requires_threejs'])) {
            $this->document->addStyle($assetBase . 'view/css/threejs-thumb.css?v=20261026c');
            $this->document->addScript($assetBase . $this->three_asset_manifest->publicBasePath() . 'threejs-thumb.js?v=20261026c');
        }

        return $this->cacheHtml($system_key, (string)$rendered['html']);
    }

    /** Layout is a validated list of fixed section identifiers, never template code. */
    public function sections(string $system_key = ''): array {
        $system_key = strtolower(trim($system_key));
        $cache = (array)$this->config->get('_cms_system_page_layout_cache', []);
        return is_array($cache[$system_key] ?? null) ? $cache[$system_key] : [];
    }

    private function cacheHtml(string $system_key, string $html): string {
        $cache = (array)$this->config->get('_cms_system_page_html_cache', []);
        $cache[$system_key] = $html;
        $this->config->set('_cms_system_page_html_cache', $cache);
        return $html;
    }
}
