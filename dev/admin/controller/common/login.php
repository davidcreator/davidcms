<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * common/login — formulário de acesso ao painel (rota pública do fluxo).
 * POST valida credenciais via serviço user (status=1 + password_verify);
 * sucesso → dashboard, falha → mesma página com erro genérico (não vaza
 * se o usuário existe ou a senha errou).
 */
namespace Reamur\Admin\Controller\Common;

class Login extends \Reamur\System\Engine\Controller {

    /** GET exibe o formulário; POST autentica (ADM 0005) */
    public function index(): void {
        if ($this->user->isLogged()) {
            $this->response->redirect($this->url->link('common/dashboard'));

            return;
        }

        $this->load->language('common/login');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['heading_title']  = $this->language->get('heading_title');
        $data['text_login']     = $this->language->get('text_login');
        $data['entry_username'] = $this->language->get('entry_username');
        $data['entry_password'] = $this->language->get('entry_password');
        $data['button_login']   = $this->language->get('button_login');

        $data['error']    = '';
        $data['username'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $username = trim((string)($this->request->post['username'] ?? ''));
            $password = (string)($this->request->post['password'] ?? '');

            if ($this->user->login($username, $password)) {
                $this->response->redirect($this->url->link('common/dashboard'));

                return;
            }

            $data['error']    = $this->language->get('error_match');
            $data['username'] = $username;
        }

        $data['action'] = $this->url->link('common/login');

        $this->response->setOutput($this->load->view('common/login', $data));
    }
}
