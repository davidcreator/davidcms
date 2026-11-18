<?php declare(strict_types=1);
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
            $username = trim($this->postScalar('username'));
            $password = $this->postScalar('password');

            // F2-18 (ADR 0018): CSRF da sessão + rate limit por IP (6 tentativas / 15 min).
            $csrfOk = $this->csrfValid();

            $limitOk = true;

            if ($csrfOk) {
                $limiter = new \Reamur\System\Library\Security\RateLimit($this->db);

                $limitOk = $limiter->allow('login', $this->request->ip(), 6, 900);
            }

            // KZ-36 (Dracaunos-Security): verificação de IP, log de tentativas e 2FA no login.
            $drac = $this->load->model('security/dracaunos');
            $ip   = (string)$this->request->ip();
            $ua   = (string)($this->request->server['HTTP_USER_AGENT'] ?? '');
            $logAttempt = function (int $uid, bool $ok) use ($drac, $username, $ip, $ua) {
                $drac->addLoginLog($uid, $username, $ip, $ua, $ok);
            };

            if (!$drac->ipAllowed($ip, 'admin')) {
                $logAttempt(0, false);
                $data['error'] = $this->language->get('error_ip_blocked');
            } elseif (!$csrfOk) {
                $data['error'] = $this->language->get('error_csrf');
            } elseif (!$limitOk) {
                $data['error'] = $this->language->get('error_rate_limit');
            } else {
                $urow  = $drac->getUserByUsername($username);
                $twofa = $urow ? (array)$drac->get2fa((int)$urow['user_id']) : [];
                $need2fa = !empty($twofa['enabled']);

                if ($need2fa && !empty($urow['password']) && password_verify($password, (string)$urow['password'])) {
                    // senha ok: exige o código TOTP antes de autenticar
                    $code = $this->postScalar('code_2fa');
                    if ($drac->verifyTotp((string)$twofa['secret'], $code)) {
                        $this->user->login($username, $password);
                        $logAttempt((int)$urow['user_id'], true);
                        $this->session->regenerateId();
                        $this->response->redirect($this->url->link('common/dashboard'));
                        return;
                    }
                    $logAttempt((int)$urow['user_id'], false);
                    $data['error']     = $this->language->get('error_2fa');
                    $data['show_2fa']  = true;
                } elseif ($this->user->login($username, $password)) {
                    // Previna session fixation: novo id pós-login (o token CSRF muda junto)
                    $logAttempt((int)$this->user->getId(), true);
                    $this->session->regenerateId();

                    $this->response->redirect($this->url->link('common/dashboard'));

                    return;
                } else {
                    $logAttempt($urow ? (int)$urow['user_id'] : 0, false);
                    $data['error'] = $this->language->get('error_match');
                }
            }

            $data['username'] = $username;
        }

        $data['action']     = $this->url->link('common/login');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);

        // Chrome compartilhado (F2-4)
        $this->document->addStyle((string)$this->config->get('site_url', '') . 'view/css/admin.css');

        $data['body_class'] = 'rms-login';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);

        $this->response->setOutput($this->load->view('common/login', $data));
    }
}
