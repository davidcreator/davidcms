<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Public delivery adapter for the allowlisted Three.js files stored in System.
 */
namespace Reamur\Catalog\Controller\Common;

class Three extends \Reamur\System\Engine\Controller {

    /** Serve one manifest-approved Three.js module from the protected System tree. */
    public function asset(): void {
        $asset = trim((string)($this->request->get['asset'] ?? ''));
        $entry = $this->three_asset_manifest->resolve($asset);

        if ($entry === null || !is_file($entry['path']) || !is_readable($entry['path'])) {
            $this->load->language('common/three');
            $this->response->addHeader('HTTP/1.1 404 Not Found');
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $this->response->addHeader('Cache-Control: no-store');
            $this->response->setOutput((string)$this->language->get('error_three_asset_missing'));
            return;
        }

        $content = file_get_contents($entry['path']);

        if ($content === false) {
            $this->response->addHeader('HTTP/1.1 404 Not Found');
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $this->response->addHeader('Cache-Control: no-store');
            $this->response->setOutput('');
            return;
        }

        $etag = 'W/"' . hash('sha256', $content) . '"';
        $this->response->addHeader('Content-Type: ' . $entry['content_type']);
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $this->response->addHeader('Vary: Accept-Encoding');
        $this->response->addHeader('Cache-Control: public, max-age=3600, must-revalidate');
        $this->response->addHeader('ETag: ' . $etag);

        if (trim((string)($this->request->server['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            $this->response->addHeader('HTTP/1.1 304 Not Modified');
            $this->response->setOutput('');
            return;
        }

        $this->response->setOutput($content);
    }
}
