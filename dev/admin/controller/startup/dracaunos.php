<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Pré-ação startup/dracaunos (KZ-39, Fase B do Dracaunos-Security):
 * roda DEPOIS de startup/setting (config/DB prontos) e ANTES de startup/login,
 * cobrindo TODAS as rotas do admin:
 *   1) Regras de IP (dracaunos_ip_rule, escopo 'admin') — o bloqueio vale para
 *      qualquer rota do painel, não só para o POST de login.
 *   2) Rota real do admin aplicada: com rota custom configurada, o acesso pela
 *      pasta física /admin/ é recusado com 403 (o servidor reescreve /rota/ → admin/).
 *   3) Auditoria: toda requisição autenticada do admin é registrada em
 *      dracaunos_audit (usuário, rota, IP, user agent).
 *   4) Expiração de senha (política max_age_days): usuário logado com senha
 *      vencida é mandado para user/profile trocar a senha (fail-closed).
 */
namespace Reamur\Admin\Controller\Startup;

class Dracaunos extends \Reamur\System\Engine\Controller {

    /** Rotas que continuam acessíveis com senha vencida (trocar senha / sair) */
    private const EXPIRY_FREE_ROUTES = ['user/profile', 'common/logout'];

    public function index(): void {
        $this->load->model('security/dracaunos');
        $drac = $this->model_security_dracaunos;

        $ip = (string)$this->request->ip();
        $ua = (string)($this->request->server['HTTP_USER_AGENT'] ?? '');
        $route = (string)$this->config->get('_route', '');

        // 1) Regras de IP em todas as rotas do admin (escopo 'admin').
        if (!$drac->ipAllowed($ip, 'admin')) {
            $drac->addLoginLog(0, '', $ip, $ua, false);
            $this->response->addHeader('HTTP/1.1 403 Forbidden');
            if (!in_array($route, ['common/login', 'common/logout'], true)) {
                $this->response->redirect($this->url->link('common/login'));
            }
            return;
        }

        // 2) Rota real do admin aplicada: rota custom configurada → /admin/ física recusada.
        $adminRoute = $drac->getAdminRoute();
        $path = (string)(parse_url((string)($this->request->server['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        if ($adminRoute !== 'admin' && preg_match('#/admin(/|$)#', $path) === 1) {
            $this->response->addHeader('HTTP/1.1 403 Forbidden');
            if (!in_array($route, ['common/login', 'common/logout'], true)) {
                $this->response->redirect($this->url->link('common/login'));
            }
            return;
        }

        // 3) Auditoria + 4) expiração de senha (só com sessão ativa).
        $uid = (int)($this->session->data['user_id'] ?? 0);
        if ($uid > 0) {
            $drac->addAudit($uid, $route, (string)($this->request->server['REQUEST_METHOD'] ?? 'GET'), $ip, $ua);

            if ($drac->passwordExpired($uid) && !in_array($route, self::EXPIRY_FREE_ROUTES, true)) {
                $this->session->data['dracaunos_password_expired'] = true;
                $this->response->redirect($this->url->link('user/profile'));
            }

            // 5) 2FA obrigatório (KZ-41): com dracaunos_2fa_required=1, quem não tem
            //    2FA ativo só acessa security/dracaunos (para ativar) e common/logout.
            if ($drac->is2faRequired() && !$drac->userHas2fa($uid)
                && !in_array($route, ['security/dracaunos', 'common/logout'], true)) {
                $this->session->data['dracaunos_2fa_required_pending'] = true;
                $this->response->redirect($this->url->link('security/dracaunos'));
            }
        }
    }
}
