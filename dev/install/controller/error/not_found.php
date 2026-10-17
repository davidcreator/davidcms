<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Página 404 da aplicação install (rota de erro do Router: error/not_found).
 * Rotas inexistentes do wizard caem aqui com status HTTP honesto, no mesmo
 * shell visual dos passos (ADR 0007) e retorno para o passo 1.
 */
namespace Reamur\Install\Controller\Error;

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

        // Install não tem config.php gerado (site_url vazio): volta sempre ao passo 1
        $data['home'] = $this->url->link('install/step_1');

        // Chrome do wizard (ADR 0007): mesmo shell e estilos dos passos
        $data['page_title'] = $this->language->get('heading_title');
        $data['header']     = $this->load->controller('common/header', $data);
        $data['language']   = $this->load->controller('common/language');
        $data['footer']     = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('error/not_found', $data));
    }
}
