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
    public function render(string $system_key = ''): string {
        $this->load->model('catalog/page');
        $page = $this->model_catalog_page->getSystemPage($system_key);
        if (!$page) {
            return '';
        }

        if ((string)($page['meta_title'] ?? '') !== '') {
            $this->document->setTitle((string)$page['meta_title']);
        }
        if ((string)($page['meta_description'] ?? '') !== '') {
            $this->document->setDescription((string)$page['meta_description']);
        }

        $content = (string)($page['description'] ?? '');
        if ($content === '') {
            return '';
        }

        $prefix = 'REAMURCMS_BDRE_JSON_V2:';
        if (!str_starts_with($content, $prefix)) {
            return $content;
        }

        $downloadRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/download/blog';
        $audioRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/audio/blog';
        $videoRoot = rtrim(dirname(DIR_APPLICATION), '/\\') . '/catalog/view/video/blog';
        $assetBase = (string)$this->config->get('site_url', '');
        $rendered = (new \Reamur\System\Library\Blog\BlockRenderer($downloadRoot, $audioRoot, $videoRoot))
            ->renderJson(substr($content, strlen($prefix)), 2, $assetBase);

        if (empty($rendered['valid'])) {
            return '';
        }

        $this->document->addStyle($assetBase . 'view/css/blog-blocks.css');
        if (!empty($rendered['requires_threejs'])) {
            $this->document->addStyle($assetBase . 'view/css/threejs-thumb.css?v=20261026c');
            $this->document->addScript($assetBase . $this->three_asset_manifest->publicBasePath() . 'threejs-thumb.js?v=20261026c');
        }

        return (string)$rendered['html'];
    }
}
