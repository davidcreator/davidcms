<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * error/permission — resposta 403 do painel (usuário logado, rota negada).
 * Destino do redirecionamento da pré-ação startup/permission; aplica o
 * status HTTP honesto e oferece retorno ao dashboard (rota da allowlist).
 */
namespace Reamur\Admin\Controller\Error;

class Permission extends \Reamur\System\Engine\Controller {

    /** Renderiza a negação de acesso (status 403) */
    public function index(): void {
        $this->load->language('error/permission');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->response->addHeader('HTTP/1.1 403 Forbidden');

        $data['heading_title']   = $this->language->get('heading_title');
        $data['text_code']       = $this->language->get('text_code');
        $data['text_error']      = $this->language->get('text_error');
        $data['text_suggestion'] = $this->language->get('text_suggestion');
        $data['button_back']     = $this->language->get('button_back');

        $data['back'] = $this->url->link('common/dashboard');

        // Chrome compartilhado (F2-4)
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-404';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);

        $this->response->setOutput($this->load->view('error/permission', $data));
    }
}
