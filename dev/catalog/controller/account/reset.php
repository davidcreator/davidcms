<?php declare(strict_types=1);
/** account/reset (CATALOG, KZ-38) — define nova senha com token válido. */
namespace Reamur\Catalog\Controller\Account;

class Reset extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->load->language('account/forgotten');
        $this->document->setTitle($this->language->get('heading_reset'));
        $data['error'] = '';
        $data['done']  = false;
        $data['token'] = trim((string)($this->request->get['token'] ?? ''));

        $drac  = $this->load->model('account/dracaunos');
        $row   = $data['token'] !== '' ? $drac->getRecoveryByToken($data['token']) : [];
        if (!$row || $row['type'] !== 'password') $data['error'] = $this->language->get('error_token');

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && !$data['error']) {
            $pass = (string)($this->request->post['password'] ?? '');
            if (!$this->csrfValid()) {
                $data['error'] = $this->language->get('error_csrf');
            } elseif (strlen($pass) < 8) {
                $data['error'] = $this->language->get('error_password');
            } else {
                $drac->setPassword((int)$row['user_id'], $pass);
                $drac->consumeToken((int)$row['recovery_id'], 'approved');
                $data['done'] = true;
            }
        }

        $data['action']     = $this->url->link('account/reset') . ($data['token'] !== '' ? '&token=' . $data['token'] : '');
        $data['login']      = $this->url->link('account/login');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['header']     = $this->load->controller('common/header');
        $data['footer']     = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('account/reset', $data));
    }
}
