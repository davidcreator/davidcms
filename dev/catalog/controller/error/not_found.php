<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Página 404 do catalog (rota de erro do Router: error/not_found — Contrato §4).
 * Recebe o dispatch por fallback, marca o status HTTP honesto e devolve a
 * página estilizada com retorno para a home da aplicação.
 */
namespace Reamur\Catalog\Controller\Error;

class Not_found extends \Reamur\System\Engine\Controller {

    /** Renderiza a 404 (status 404) */
    public function index(): void {
        $this->load->language('error/not_found');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->response->addHeader('HTTP/1.1 404 Not Found');

        $data['heading_title']   = $this->language->get('heading_title');
        $data['text_code']       = $this->language->get('text_code');
        $data['text_error']      = $this->language->get('text_error');
        $data['text_suggestion'] = $this->language->get('text_suggestion');
        $data['button_continue'] = $this->language->get('button_continue');

        $data['home'] = $this->config->get('site_url');

        $this->response->setOutput($this->load->view('error/not_found', $data));
    }
}
