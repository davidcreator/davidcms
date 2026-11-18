<?php declare(strict_types=1);
/** account/account (CATALOG, KZ-38) — área de perfil do cliente (requer login). */
namespace Reamur\Catalog\Controller\Account;

class Account extends \Reamur\System\Engine\Controller {

    private function requireCustomer(): array {
        $id = (int)($this->session->data['customer_id'] ?? 0);
        $drac = $this->load->model('account/dracaunos');
        $c = $id > 0 ? $drac->getCustomer($id) : [];
        if (!$c) {
            $this->response->redirect($this->url->link('account/login'));
            exit;
        }
        return $c;
    }

    public function index(): void {
        $this->load->language('account/account');
        $this->document->setTitle($this->language->get('heading_title'));
        $c = $this->requireCustomer();

        $data['customer'] = $c;
        $data['edit']     = $this->url->link('account/edit');
        $data['password'] = $this->url->link('account/password');
        $data['logout']   = $this->url->link('account/logout');
        $data['header']   = $this->load->controller('common/header');
        $data['footer']   = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('account/account', $data));
    }

    public function edit(): void {
        $this->load->language('account/account');
        $c = $this->requireCustomer();
        $data['errors'] = [];
        $data['success'] = false;
        $data['old'] = $c;

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->csrfValid()) {
                $data['errors'][] = $this->language->get('error_csrf');
            } else {
                $drac = $this->load->model('account/dracaunos');
                $drac->updateCustomer((int)$c['customer_id'], $this->request->post);
                $data['success'] = true;
            }
        }
        $data['action']     = $this->url->link('account/edit');
        $data['back']       = $this->url->link('account/account');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['header']     = $this->load->controller('common/header');
        $data['footer']     = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('account/edit', $data));
    }

    public function password(): void {
        $this->load->language('account/account');
        $c = $this->requireCustomer();
        $data['error'] = '';
        $data['success'] = false;

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $old = (string)($this->request->post['password_old'] ?? '');
            $new = (string)($this->request->post['password_new'] ?? '');
            if (!$this->csrfValid()) {
                $data['error'] = $this->language->get('error_csrf');
            } elseif (!password_verify($old, (string)$c['password'])) {
                $data['error'] = $this->language->get('error_password_old');
            } elseif (strlen($new) < 8) {
                $data['error'] = $this->language->get('error_password');
            } else {
                $drac = $this->load->model('account/dracaunos');
                $drac->setPassword((int)$c['customer_id'], $new);
                $data['success'] = true;
            }
        }
        $data['action']     = $this->url->link('account/password');
        $data['back']       = $this->url->link('account/account');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['header']     = $this->load->controller('common/header');
        $data['footer']     = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('account/password', $data));
    }
}
