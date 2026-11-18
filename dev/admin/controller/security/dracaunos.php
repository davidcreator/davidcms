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
        // KZ-41: 2FA obrigatório — estado global e flag de bloqueio da conta atual
        $data['2fa_required'] = $this->model_security_dracaunos->is2faRequired();
        $data['force_2fa']    = !empty($this->session->data['dracaunos_2fa_required_pending']);
        $data['totp_now']    = $this->model_security_dracaunos->totp(
            !empty($data['twofa']['secret']) ? $data['twofa']['secret'] : $this->model_security_dracaunos->generateSecret()
        );
        $data['recovery']    = $this->model_security_dracaunos->getRecovery('pending', 30);
        $data['audit']       = $this->model_security_dracaunos->getAuditLogs(50);
        // KZ-45: sessões ativas do admin (a própria sessão fica marcada)
        $data['sessions']    = $this->model_security_dracaunos->getAdminSessions(50);
        $data['current_sid'] = $this->session->getId();
        // KZ-42: links de exportação CSV das seções de auditoria e log de logins
        $data['export_audit']  = $this->url->link('security/dracaunos.exportAudit');
        $data['export_logins'] = $this->url->link('security/dracaunos.exportLogins');
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
                    unset($this->session->data['dracaunos_2fa_required_pending']); // KZ-41: 2FA ativo desbloqueia o painel
                    $this->session->data['success'] = $this->language->get('text_2fa_enabled');
                } else {
                    $this->session->data['error'] = $this->language->get('error_2fa_code');
                }
                break;
            case '2fa_required':
                $this->model_security_dracaunos->save2faRequired(!empty($post['2fa_required']));
                $this->session->data['success'] = $this->language->get('text_2fa_required_saved');
                break;
            case 'twofa_disable':
                $this->model_security_dracaunos->save2fa((int)$this->user->getId(), '', false);
                $this->session->data['success'] = $this->language->get('text_2fa_disabled');
                break;
            case 'recovery_handle':
                $rid = (int)($post['recovery_id'] ?? 0);
                $row = $this->model_security_dracaunos->getRecoveryById($rid);
                $this->model_security_dracaunos->setRecoveryStatus($rid, (string)($post['status'] ?? 'pending'));
                // KZ-44: aprovar pedido de reset do ADMIN mostra o link de reset uma vez
                if ($row && $row['type'] === 'admin_password' && (string)($post['status'] ?? '') === 'approved') {
                    $this->session->data['success'] = $this->language->get('text_recovery_handled')
                        . ' ' . $this->url->link('common/reset') . '&token=' . $row['token'];
                } else {
                    $this->session->data['success'] = $this->language->get('text_recovery_handled');
                }
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
            case 'ip_test':
                // KZ-46: simula a decisão de uma regra para um IP informado
                $ip = trim($this->postScalar('test_ip'));
                $scope = (string)($post['test_scope'] ?? 'admin');
                $this->session->data['ip_test_result'] = $this->model_security_dracaunos->explainIp($ip, $scope);
                break;
            case 'session_kill':
                // KZ-45: encerra sessão remota; nunca a sessão atual (fail-safe)
                $sid = (string)($post['session_id'] ?? '');
                if ($sid !== '' && $sid !== $this->session->getId()) {
                    $this->model_security_dracaunos->destroySession($sid);
                }
                $this->session->data['success'] = $this->language->get('text_session_killed');
                break;
            case 'prune':
                // KZ-42: limpeza por retencao (auditoria + log de logins)
                $n1 = $this->model_security_dracaunos->pruneAudit();
                $n2 = $this->model_security_dracaunos->pruneLoginLogs();
                $this->session->data['success'] = sprintf($this->language->get('text_pruned'), $n1, $n2);
                break;
        }
        $this->response->redirect($this->url->link('security/dracaunos'));
    }

    // ── KZ-42: exportación CSV (GET, sem estado; exige ACL access) ──────
    public function exportAudit(): void {
        $this->load->language('security/dracaunos');
        $this->load->model('security/dracaunos');
        if (!$this->user->hasPermission('access', 'security/dracaunos')) {
            $this->response->redirect($this->url->link('error/permission'));
            return;
        }
        $header = ['user_id', 'username', 'route', 'method', 'ip', 'user_agent', 'date_added'];
        $this->csvDownload('dracaunos-audit-' . date('Ymd-His') . '.csv', $header, $this->model_security_dracaunos->exportAuditLogs());
    }

    public function exportLogins(): void {
        $this->load->language('security/dracaunos');
        $this->load->model('security/dracaunos');
        if (!$this->user->hasPermission('access', 'security/dracaunos')) {
            $this->response->redirect($this->url->link('error/permission'));
            return;
        }
        $header = ['user_id', 'username', 'ip', 'user_agent', 'success', 'date_added'];
        $this->csvDownload('dracaunos-login-log-' . date('Ymd-His') . '.csv', $header, $this->model_security_dracaunos->exportLoginLogs());
    }

    /** CSV com BOM (Excel abre em UTF-8) e separador ; — padrao pt-BR */
    private function csvDownload(string $filename, array $header, array $rows): void {
        $out = "\xEF\xBB\xBF" . implode(';', $header) . "\r\n";
        foreach ($rows as $r) {
            $line = [];
            foreach ($header as $col) {
                $line[] = '"' . str_replace('"', '""', (string)($r[$col] ?? '')) . '"';
            }
            $out .= implode(';', $line) . "\r\n";
        }
        $this->response->addHeader('Content-Type: text/csv; charset=utf-8');
        $this->response->addHeader('Cache-Control: no-store');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
        $this->response->setOutput($out);
    }
}
