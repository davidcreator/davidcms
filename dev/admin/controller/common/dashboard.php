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
        $data['text_modules']         = $this->language->get('text_modules');
        $data['button_logout']        = $this->language->get('button_logout');

        // Módulos visíveis = módulos PERMITIDOS ao grupo (ACL manda na âncora, F2-6)
        $data['modules'] = [];

        if ($this->user->hasPermission('access', 'catalog/project')) {
            $data['modules'][] = [
                'name' => $this->language->get('text_manage_portfolio'),
                'href' => $this->url->link('catalog/project'),
            ];
        }

        if ($this->user->hasPermission('access', 'catalog/category')) {
            $data['modules'][] = [
                'name' => $this->language->get('text_manage_categories'),
                'href' => $this->url->link('catalog/category'),
            ];
        }

        if ($this->user->hasPermission('access', 'cms/page')) {
            $data['modules'][] = [
                'name' => $this->language->get('text_manage_pages'),
                'href' => $this->url->link('cms/page'),
            ];
        }

        if ($this->user->hasPermission('access', 'user/user')) {
            $data['modules'][] = [
                'name' => $this->language->get('text_manage_users'),
                'href' => $this->url->link('user/user'),
            ];
        }

        if ($this->user->hasPermission('access', 'user/user_group')) {
            $data['modules'][] = [
                'name' => $this->language->get('text_manage_user_groups'),
                'href' => $this->url->link('user/user_group'),
            ];
        }

        $data['username']      = $this->user->getUsername();
        $data['email']         = $this->user->getEmail();
        $data['user_group_id'] = $this->user->getUserGroupId();
        $data['access_routes'] = $this->user->countPermissions('access');
        $data['modify_routes'] = $this->user->countPermissions('modify');

        $data['logout'] = $this->url->link('common/logout');

        // Chrome compartilhado (F2-4)
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);

        $this->response->setOutput($this->load->view('common/dashboard', $data));
    }
}
