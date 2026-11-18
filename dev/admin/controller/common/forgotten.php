<?php declare(strict_types=1);
/**
 * common/forgotten (ADMIN, KZ-44) — "esqueci minha senha" do painel.
 * Rota pública (startup/login libera): grava token type='admin_password' em
 * dracaunos_recovery e responde SEMPRE igual (não revela se o usuário existe).
 * Aprovação acontece no painel Dracaunos (security/dracaunos), que mostra o
 * link de reset uma vez. Rate limit por IP igual ao do login.
 */
namespace Reamur\Admin\Controller\Common;

class Forgotten extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->load->language('common/forgotten');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['error'] = '';
        $data['sent']  = false;

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $username = trim($this->postScalar('username'));

            $limiter = new \Reamur\System\Library\Security\RateLimit($this->db);
            $limitOk = $limiter->allow('forgotten', (string)$this->request->ip(), 6, 900);

            if (!$this->csrfValid()) {
                $data['error'] = $this->language->get('error_csrf');
            } elseif (!$limitOk) {
                $data['error'] = $this->language->get('error_rate_limit');
            } elseif ($username === '') {
                $data['error'] = $this->language->get('error_username');
            } else {
                $this->load->model('security/dracaunos');
                $u = $this->model_security_dracaunos->getUserByUsername($username);
                if ($u && (int)$u['status'] === 1) {
                    $this->model_security_dracaunos->addRecovery(
                        (int)$u['user_id'], (string)($u['email'] ?? $username), 'admin_password', (string)$this->request->ip());
                }
                // resposta sempre igual (não revela se o usuário existe)
                $data['sent'] = true;
            }
        }

        $data['action']     = $this->url->link('common/forgotten');
        $data['login']      = $this->url->link('common/login');
        $data['asset_url']  = $this->adminAssetUrl(); // KZ-53
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
        $this->response->setOutput($this->load->view('common/forgotten', $data));
    }
}
