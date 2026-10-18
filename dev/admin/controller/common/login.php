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

            // F2-18 (ADR 0018): CSRF da sessão + rate limit por IP (6 tentativas / 15 min).
            $csrfOk = \Reamur\System\Library\Security\Csrf::check($this->session, $this->request->post['csrf_token'] ?? '');

            $limitOk = true;

            if ($csrfOk) {
                $limiter = new \Reamur\System\Library\Security\RateLimit($this->db);

                $limitOk = $limiter->allow('login', (string)($this->request->server['REMOTE_ADDR'] ?? ''), 6, 900);
            }

            if (!$csrfOk) {
                $data['error'] = $this->language->get('error_csrf');
            } elseif (!$limitOk) {
                $data['error'] = $this->language->get('error_rate_limit');
            } elseif ($this->user->login($username, $password)) {
                // Previna session fixation: novo id pós-login (o token CSRF muda junto)
                $this->session->regenerateId();

                $this->response->redirect($this->url->link('common/dashboard'));

                return;
            } else {
                $data['error'] = $this->language->get('error_match');
            }

            $data['username'] = $username;
        }

        $data['action']     = $this->url->link('common/login');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);

        // Chrome compartilhado (F2-4)
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-login';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);

        $this->response->setOutput($this->load->view('common/login', $data));
    }
}
