<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * cms/page (CATALOG) — página de conteúdo pública (F2-17): ?route=cms/page.info|N
 * ou keyword seo_url ('politicas', 'cookies') resolvida pelo startup/router.
 * Publicada (status=1) apenas; coalesce idioma ativo → en-US; descrição é HTML
 * do editor do admin (F2-10) → render raw (conteúdo confiável, sanitizado lá).
 */
namespace Reamur\Catalog\Controller\Cms;

class Page extends \Reamur\System\Engine\Controller {

    /** Página por id (604 se inexistente/inativa → rota de erro da app) */
    public function info(string $page_id = '0'): void {
        $page_id = (int)$page_id;

        $this->load->model('catalog/page');

        $page = $this->model_catalog_page->getPage($page_id);

        if (!$page) {
            $action = new \Reamur\System\Engine\Action('error/not_found');

            $action->execute($this->registry);

            return;
        }

        $this->document->setTitle((string)($page['meta_title'] !== '' ? $page['meta_title'] : $page['title']));
        $this->document->setDescription((string)$page['meta_description']);

        $data['heading_title'] = (string)$page['title'];
        $data['description']   = (string)$page['description'];

        $data['button_back_home'] = null;
        $data['home'] = $this->config->get('site_url', '');

        $this->document->addStyle($this->config->get('site_url') . 'view/css/common.css');

        $data['body_class'] = 'rms-page';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);

        $this->response->setOutput($this->load->view('cms/page_info', $data));
    }
}
