<?php declare(strict_types=1);
/**
 * common/reset (ADMIN, KZ-44) — define nova senha do admin com token válido
 * (type='admin_password', status pending, criado por common/forgotten e
 * aprovado no painel Dracaunos). Rota pública; consome o token (uso único),
 * grava password_changed_at e registra no log de logins. Rate limit por IP.
 */
namespace Reamur\Admin\Controller\Common;

class Reset extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->load->language('common/forgotten');
        $this->document->setTitle($this->language->get('heading_reset'));

        $data['error'] = '';
        $data['done']  = false;
        $data['token'] = trim((string)($this->request->get['token'] ?? ''));

        $this->load->model('security/dracaunos');
        $drac = $this->model_security_dracaunos;
        $row  = $data['token'] !== '' ? $drac->getRecoveryByToken($data['token']) : [];
        if (!$row || $row['type'] !== 'admin_password') $data['error'] = $this->language->get('error_token');

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && !$data['error']) {
            $pass = (string)($this->request->post['password'] ?? '');

            $limiter = new \Reamur\System\Library\Security\RateLimit($this->db);
            $limitOk = $limiter->allow('reset', (string)$this->request->ip(), 6, 900);

            if (!$this->csrfValid()) {
                $data['error'] = $this->language->get('error_csrf');
            } elseif (!$limitOk) {
                $data['error'] = $this->language->get('error_rate_limit');
            } elseif (!$drac->passwordMeetsPolicy($pass)) {
                $data['error'] = $this->language->get('error_password');
            } else {
                $drac->setUserPassword((int)$row['user_id'], $pass);
                $drac->setRecoveryStatus((int)$row['recovery_id'], 'used');
                $drac->addLoginLog((int)$row['user_id'], (string)$row['email'], (string)$this->request->ip(),
                    (string)($this->request->server['HTTP_USER_AGENT'] ?? ''), true);
                $data['done'] = true;
            }
        }

        $data['action']     = $this->url->link('common/reset') . ($data['token'] !== '' ? '&token=' . $data['token'] : '');
        $data['login']      = $this->url->link('common/login');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
        $this->response->setOutput($this->load->view('common/reset', $data));
    }
}
