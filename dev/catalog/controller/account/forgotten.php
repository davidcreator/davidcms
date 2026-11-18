<?php declare(strict_types=1);
/**
 * account/forgotten (CATALOG, KZ-38) — recuperação de senha: grava token
 * type='password' em dracaunos_recovery e entrega o link de reset.
 */
namespace Reamur\Catalog\Controller\Account;

class Forgotten extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->load->language('account/forgotten');
        $this->document->setTitle($this->language->get('heading_title'));
        $data['error'] = '';
        $data['sent']  = false;

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $email = trim($this->postScalar('email'));
            if (!$this->csrfValid()) {
                $data['error'] = $this->language->get('error_csrf');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $data['error'] = $this->language->get('error_email');
            } else {
                $drac = $this->load->model('account/dracaunos');
                $c = $drac->getCustomerByEmail($email);
                if ($c) {
                    $token = $drac->addRecovery((int)$c['customer_id'], $email, 'password', (string)$this->request->ip());
                    $data['reset_link'] = $this->url->link('account/reset') . '&token=' . $token;
                }
                // resposta sempre igual (não revela se o e-mail existe)
                $data['sent'] = true;
            }
        }

        $data['action']     = $this->url->link('account/forgotten');
        $data['login']      = $this->url->link('account/login');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['header']     = $this->load->controller('common/header');
        $data['footer']     = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('account/forgotten', $data));
    }
}
