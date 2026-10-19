<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * user/profile (ADMIN) — auto-perfil do usuário logado (F4-04, ADR 0027):
 *  · GET  → formulário com dados próprios (email, firstname, lastname);
 *  · POST → valida e persiste dados próprios (sem mudar username/grupo/status);
 *    troca de senha exige senha atual + nova + confirma (bcrypt, via model).
 * ACL: rota liberada pelo bootstrap startup/permission ADR 0005 §Regra —
 * é função "todo usuário logado" (como common/dashboard) — NÃO depende de
 * grant 'user/user' no grupo (documentado na ADR 0027).
 */
namespace Reamur\Admin\Controller\User;

class Profile extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->load->language('user/profile');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('user/user');

        $user_id = $this->user->getUserId();
        $user    = $this->model_user_user->getUser($user_id);

        if (!$user) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $errors = [];

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $post   = $this->sanitize($this->request->post);
            $errors = $this->validate($post, (string)$user['password']);

            if ($errors === []) {
                $this->model_user_user->editProfile($user_id, $post);

                $this->flashSet($this->language->get('text_success'), 'success');

                $this->response->redirect($this->url->link('user/profile'));

                return;
            }

            // POST inválido: repovoa do próprio POST (não perde o digitado)
            $user = array_merge($user, $post);
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_profile']  = $this->language->get('text_profile');
        $data['text_flash']    = $this->flash();
        $data['error']         = implode("\n", array_filter(array_map('strval', $errors)));

        // Subseções
        $data['fieldset_identity'] = $this->language->get('fieldset_identity');
        $data['fieldset_password'] = $this->language->get('fieldset_password');

        // Campos
        foreach (['username', 'email', 'firstname', 'lastname', 'password_current', 'password_new', 'password_confirm'] as $field) {
            $data['entry_' . $field] = $this->language->get('entry_' . $field);
            $data['help_' . $field]  = $this->language->get('help_' . $field);
        }

        // Valores efetivos (username é informativo — não editável aqui)
        $data['value_username']     = (string)$user['username'];
        $data['value_email']        = (string)$user['email'];
        $data['value_firstname']    = (string)$user['firstname'];
        $data['value_lastname']     = (string)$user['lastname'];

        $data['button_save'] = $this->language->get('button_save');
        $data['button_back'] = $this->language->get('button_back');

        $data['action'] = $this->url->link('user/profile');
        $data['back']   = $this->url->link('common/dashboard');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('user/profile', $data));
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private function sanitize(array $post): array {
        $str = static function (string $k, int $max = 96) use ($post): string {
            return mb_substr(trim((string)($post[$k] ?? '')), 0, $max);
        };

        return [
            'email'            => $str('email'),
            'firstname'        => $str('firstname', 32),
            'lastname'         => $str('lastname', 32),
            'password_current' => (string)($post['password_current'] ?? ''), // sem trim — espaços fazem parte da senha
            'password_new'     => (string)($post['password_new'] ?? ''),
            'password_confirm' => (string)($post['password_confirm'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $post
     * @return array<string, string> field → mensagem
     */
    private function validate(array $post, string $current_hash): array {
        $errors = [];

        if (!filter_var($post['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = $this->language->get('error_email');
        }

        if (mb_strlen($post['firstname']) < 1 || mb_strlen($post['firstname']) > 32) {
            $errors['firstname'] = $this->language->get('error_firstname');
        }

        if (mb_strlen($post['lastname']) < 1 || mb_strlen($post['lastname']) > 32) {
            $errors['lastname'] = $this->language->get('error_lastname');
        }

        // Troca de senha: opcional — se QUALQUER campo vier preenchido, exige trio completo
        $wants_password = $post['password_current'] !== '' || $post['password_new'] !== '' || $post['password_confirm'] !== '';

        if ($wants_password) {
            if (!password_verify($post['password_current'], $current_hash)) {
                $errors['password_current'] = $this->language->get('error_password_current');
            } elseif (mb_strlen($post['password_new']) < 8) {
                $errors['password_new'] = $this->language->get('error_password_new');
            } elseif ($post['password_new'] !== $post['password_confirm']) {
                $errors['password_confirm'] = $this->language->get('error_password_confirm');
            }
        }

        return $errors;
    }

    /** @return array{type: string, text: string} */
    private function flash(): array {
        $session = $this->session;

        $data = ['type' => '', 'text' => ''];

        if ($session instanceof \Reamur\System\Library\Session && !empty($session->data['flash'])) {
            $flash        = (array)$session->data['flash'];
            $data['type'] = (string)($flash['type'] ?? 'success');
            $data['text'] = (string)($flash['text'] ?? '');

            unset($session->data['flash']);
        }

        return $data;
    }

    private function flashSet(string $text, string $type = 'success'): void {
        if ($this->session instanceof \Reamur\System\Library\Session) {
            $this->session->data['flash'] = ['type' => $type, 'text' => $text];
        }
    }

    /** Chrome compartilhado com as telas do dashboard (mesma assinatura do módulo) */
    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
