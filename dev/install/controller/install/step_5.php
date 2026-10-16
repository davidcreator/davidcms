<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Wizard — Passo 5: Conclusão.
 * Tela final com aviso obrigatório de remover/renomear dev/install/ (segurança).
 */
namespace Reamur\Install\Controller\Install;

class Step_5 extends \Reamur\System\Engine\Controller {

    /** Só exibe se a instalação foi concluída nesta sessão do wizard */
    public function index(): void {
        if (empty($this->session->data['install_done'])) {
            $this->response->redirect($this->url->link('install/step_1'));
            return;
        }

        $this->load->language('install/step_5');

        $this->document->setTitle($this->language->get('heading_title'));

        // Textos da view
        $data['step']                = sprintf($this->language->get('text_step'), 5);
        $data['heading_title']       = $this->language->get('heading_title');
        $data['text_success']        = $this->language->get('text_success');
        $data['text_remove_install'] = $this->language->get('text_remove_install');
        $data['text_catalog']        = $this->language->get('text_catalog');
        $data['text_admin']          = $this->language->get('text_admin');

        // URLs das aplicações derivadas do próprio request do wizard (../{app}/)
        $base = rtrim(dirname((string)($this->request->server['SCRIPT_NAME'] ?? '/install/index.php')), '/\\') . '/';

        $data['catalog'] = preg_replace('#install/$#', '', $base) . 'index.php';
        $data['admin']   = preg_replace('#install/$#', '', $base) . 'admin/index.php';

        // Sessão efêmera: encerra os dados do wizard antes da despedida
        unset($this->session->data['install_db'], $this->session->data['install_done']);

        $this->response->setOutput($this->load->view('install/step_5', $data));
    }
}
