<?php declare(strict_types=1);
/**
 * account/login (CATALOG, KZ-38) — login do cliente com Dracaunos-Security:
 * verificação de IP (escopo catalog) + log de tentativa em dracaunos_login_log.
 */
namespace Reamur\Catalog\Controller\Account;

class Login extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->load->language('account/login');
        $this->document->setTitle($this->language->get('heading_title'));

        if (!empty($this->session->data['customer_id'])) {
            $this->response->redirect($this->url->link('account/account'));
            return;
        }

        $data['error'] = '';
        $data['email'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $email = $this->postScalar('email');
            $pass  = $this->postScalar('password');
            $data['email'] = $email;

            $drac = $this->load->model('account/dracaunos');
            $ip   = (string)$this->request->ip();
            $ua   = (string)($this->request->server['HTTP_USER_AGENT'] ?? '');

            if (!$drac->ipAllowed($ip)) {
                $drac->addLoginLog(0, $email, $ip, $ua, false);
                $data['error'] = $this->language->get('error_ip_blocked');
            } elseif (!$this->csrfValid()) {
                $data['error'] = $this->language->get('error_csrf');
            } else {
                $c = $drac->verifyCustomerLogin($email, $pass);
                if ($c) {
                    $drac->addLoginLog((int)$c['customer_id'], $email, $ip, $ua, true);
                    $this->session->regenerateId();
                    $this->session->data['customer_id'] = (int)$c['customer_id'];
                    $this->response->redirect($this->url->link('account/account'));
                    return;
                }
                $drac->addLoginLog(0, $email, $ip, $ua, false);
                $data['error'] = $this->language->get('error_match');
            }
        }

        $data['action']       = $this->url->link('account/login');
        $data['register']     = $this->url->link('account/register');
        $data['forgotten']    = $this->url->link('account/forgotten');
        $data['csrf_token']   = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['header']       = $this->load->controller('common/header');
        $data['footer']       = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('account/login', $data));
    }
}
