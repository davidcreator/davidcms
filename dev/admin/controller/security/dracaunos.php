<?php
/**
 * Dracaunos-Security — painel interno de segurança do ReamurCMS (KZ-34).
 * Rota: security/dracaunos (somente admin; ACL access/modify via user_group).
 * Seções: resumo, política de senhas, log de logins, 2FA (TOTP), rota do admin,
 * recuperação/verificação de conta, regras de IP e gestão de usuários.
 * A ferramenta é INTERNA: não aparece no catálogo público.
 */
namespace Reamur\Admin\Controller\Security;

class Dracaunos extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->load->language('security/dracaunos');
        $this->load->model('security/dracaunos');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [
            ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard')],
            ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('security/dracaunos')],
        ];

        $data['can_modify'] = $this->user->hasPermission('modify', 'security/dracaunos');
        $data['action']     = $this->url->link('security/dracaunos');
        $data['csrf_token'] = $this->session->data['csrf_token'] ?? '';

        // dados das seções
        $data['policy']      = $this->model_security_dracaunos->getPasswordPolicy();
        $data['logins']      = $this->model_security_dracaunos->getLoginLogs(30);
        $data['admin_route'] = $this->model_security_dracaunos->getAdminRoute();
        $data['twofa']       = $this->model_security_dracaunos->get2fa((int)$this->user->getId());
        $data['totp_now']    = $this->model_security_dracaunos->totp(
            !empty($data['twofa']['secret']) ? $data['twofa']['secret'] : $this->model_security_dracaunos->generateSecret()
        );
        $data['recovery']    = $this->model_security_dracaunos->getRecovery('pending', 30);
        $data['audit']       = $this->model_security_dracaunos->getAuditLogs(50);
        $data['ip_rules']    = $this->model_security_dracaunos->getIpRules();
        $data['users']       = $this->model_security_dracaunos->getUsers(30);
        $data['user_edit']   = $this->url->link('user/user.edit');
        $data['error']       = $this->session->data['error'] ?? '';
        $data['success']     = $this->session->data['success'] ?? '';
        unset($this->session->data['error'], $this->session->data['success']);

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('security/dracaunos', $data));
    }

    /** POST unificado: cada ação valida CSRF + modify ACL (fail-closed). */
    public function save(): void {
        $this->load->language('security/dracaunos');
        if (!$this->user->hasPermission('modify', 'security/dracaunos')
            || $this->request->server['REQUEST_METHOD'] !== 'POST'
            || !$this->user->csrfValid()) {
            $this->response->redirect($this->url->link('security/dracaunos'));
        }
        $this->load->model('security/dracaunos');
        $post = $this->request->post;
        $action = (string)($post['action'] ?? '');

        switch ($action) {
            case 'password_policy':
                $this->model_security_dracaunos->savePasswordPolicy($post['policy'] ?? []);
                $this->session->data['success'] = $this->language->get('text_policy_saved');
                break;
            case 'admin_route':
                $ok = $this->model_security_dracaunos->saveAdminRoute(trim((string)($post['admin_route'] ?? '')));
                $this->session->data[$ok ? 'success' : 'error'] = $this->language->get($ok ? 'text_route_saved' : 'error_route_invalid');
                break;
            case 'twofa_enable':
                $secret = trim((string)($post['secret'] ?? ''));
                $code   = (string)($post['code'] ?? '');
                if ($this->model_security_dracaunos->verifyTotp($secret, $code)) {
                    $this->model_security_dracaunos->save2fa((int)$this->user->getId(), $secret, true);
                    $this->session->data['success'] = $this->language->get('text_2fa_enabled');
                } else {
                    $this->session->data['error'] = $this->language->get('error_2fa_code');
                }
                break;
            case 'twofa_disable':
                $this->model_security_dracaunos->save2fa((int)$this->user->getId(), '', false);
                $this->session->data['success'] = $this->language->get('text_2fa_disabled');
                break;
            case 'recovery_handle':
                $this->model_security_dracaunos->setRecoveryStatus((int)($post['recovery_id'] ?? 0), (string)($post['status'] ?? 'pending'));
                $this->session->data['success'] = $this->language->get('text_recovery_handled');
                break;
            case 'ip_add':
                $this->model_security_dracaunos->addIpRule(
                    trim((string)($post['ip'] ?? '')), (string)($post['ip_action'] ?? 'deny'),
                    (string)($post['scope'] ?? 'admin'), (string)($post['note'] ?? ''));
                $this->session->data['success'] = $this->language->get('text_ip_saved');
                break;
            case 'ip_delete':
                $this->model_security_dracaunos->deleteIpRule((int)($post['rule_id'] ?? 0));
                $this->session->data['success'] = $this->language->get('text_ip_deleted');
                break;
        }
        $this->response->redirect($this->url->link('security/dracaunos'));
    }
}
