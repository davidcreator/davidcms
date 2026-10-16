<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * common/dashboard — primeira página do painel (ação padrão do admin, Contrato §4).
 * Requer sessão válida (muralha startup/login). Por ora: perfil real do usuário,
 * diagnóstico honesto da ACL do grupo e âncora de navegação da Fase 2.
 */
namespace Reamur\Admin\Controller\Common;

class Dashboard extends \Reamur\System\Engine\Controller {

    /** Renderiza o painel inicial (200) */
    public function index(): void {
        if (!$this->user->isLogged()) {
            $this->response->redirect($this->url->link('common/login'));

            return;
        }

        $this->load->language('common/dashboard');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['heading_title']        = $this->language->get('heading_title');
        $data['text_welcome']         = sprintf($this->language->get('text_welcome'), $this->user->getFullname());
        $data['text_profile']         = $this->language->get('text_profile');
        $data['text_username']        = $this->language->get('text_username');
        $data['text_email']           = $this->language->get('text_email');
        $data['text_user_group']      = $this->language->get('text_user_group');
        $data['text_acl']             = $this->language->get('text_acl');
        $data['text_access_routes']   = $this->language->get('text_access_routes');
        $data['text_modify_routes']   = $this->language->get('text_modify_routes');
        $data['text_acl_note']        = $this->language->get('text_acl_note');
        $data['text_version']         = sprintf($this->language->get('text_version'), REAMUR_VERSION);
        $data['button_logout']        = $this->language->get('button_logout');

        $data['username']      = $this->user->getUsername();
        $data['email']         = $this->user->getEmail();
        $data['user_group_id'] = $this->user->getUserGroupId();
        $data['access_routes'] = $this->user->countPermissions('access');
        $data['modify_routes'] = $this->user->countPermissions('modify');

        $data['logout'] = $this->url->link('common/logout');

        $this->response->setOutput($this->load->view('common/dashboard', $data));
    }
}
