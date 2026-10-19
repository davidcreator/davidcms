<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * user/user — CRUD de usuários do painel (F2-6).
 * Rotas: /user/user (lista) · ?route=user/user.add · ?route=user/user.edit|N
 * · ?route=user/user.delete|N (POST por linha — GET jamais muta).
 * Args de método via gramática 'rota.metodo|arg1' do Action — NUNCA via
 * querystring (Action não transporta $_GET para argumentos de método).
 * ACL (Adendo F2-6 do ADR 0005): granularidade base-route — 'user/user'
 * cobre TODOS os métodos; POST exige direito 'modify'.
 */
namespace Reamur\Admin\Controller\User;

class User extends \Reamur\System\Engine\Controller {

    /** Lista usuários com nome do grupo (200) */
    public function index(): void {
        $this->load->language('user/user');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('user/user');

        $data['heading_title']        = $this->language->get('heading_title');
        $data['text_list']            = $this->language->get('text_list');
        $data['text_no_results']      = $this->language->get('text_no_results');
        $data['text_confirm_delete']  = $this->language->get('text_confirm_delete');
        $data['text_enabled']         = $this->language->get('text_enabled');
        $data['text_disabled']        = $this->language->get('text_disabled');
        $data['column_username']      = $this->language->get('column_username');
        $data['column_email']         = $this->language->get('column_email');
        $data['column_group']         = $this->language->get('column_group');
        $data['column_status']        = $this->language->get('column_status');
        $data['column_action']        = $this->language->get('column_action');
        $data['button_add']           = $this->language->get('button_add');
        $data['button_edit']          = $this->language->get('button_edit');
        $data['button_delete']        = $this->language->get('button_delete');
        $data['text_flash']           = $this->flash();

        $data['users'] = [];

        foreach ($this->model_user_user->getUsers() as $user) {
            $user = (array)$user;
            $uid  = (int)$user['user_id'];

            $data['users'][] = [
                'user_id'    => $uid,
                'username'   => (string)$user['username'],
                'email'      => (string)$user['email'],
                'group_name' => (string)($user['group_name'] ?? ''),
                'status'     => (int)$user['status'],
                'self'       => $uid === $this->user->getUserId(),
                'edit'       => $this->url->link('user/user.edit|' . $uid),
                'delete'     => $this->url->link('user/user.delete|' . $uid),
            ];
        }

        $data['add'] = $this->url->link('user/user.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('user/user_list', $data));
    }

    /** GET: formulário de criação · POST: valida e persiste; sucesso → lista + flash */
    public function add(): void {
        if (!$this->user->hasPermission('modify', 'user/user') && $this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('user/user');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('user/user');

        $data = $this->formData([
            'user_group_id' => 0, 'username' => '', 'email' => '',
            'firstname' => '', 'lastname' => '', 'status' => '1', 'password' => '',
        ]);

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validateUser(0, $data);

            if ($errors === []) {
                // Payload normalizado do POST (NÃO o $data de view — que
                // propositalmente não carrega 'password')
                $user_id = $this->model_user_user->addUser($this->postPayload());

                if ($user_id) {
                    $this->setFlash($this->language->get('text_success_add'));

                    $this->response->redirect($this->url->link('user/user'));

                    return;
                }
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('user/user.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('user/user_form', $data));
    }

    /** GET: formulário de edição · POST: valida e persiste (args do Action são strings) */
    public function edit(string $user_id = '0'): void {
        $user_id = (int)$user_id;

        $this->load->language('user/user');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('user/user');

        $user = $this->model_user_user->getUser($user_id);

        if (!$user) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $data = $this->formData($user);
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'user/user')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $errors = $this->validateUser($user_id, $data);

            // F4-04 (ADR 0027): guardas de auto-trava do USUÁRIO LOGADO —
            // desativar a si mesmo fecha o painel; inativar/demovê-lo do
            // grupo que concede user/user quando é o último ativo idem.
            if ($user_id === $this->user->getUserId() && ($this->request->post['status'] ?? null) === null && (int)($user['status'] ?? 0) === 1) {
                $errors[] = $this->language->get('error_self_disable');
            }

            if ($errors === []) {
                $this->load->model('user/user_group');

                if (!$this->model_user_user_group->hasOtherActiveUserAdmin($user_id)) {
                    $errors[] = $this->language->get('error_last_active_admin');
                }
            }

            if ($errors === []) {
                $this->model_user_user->editUser($user_id, $this->postPayload());

                $this->setFlash($this->language->get('text_success_edit'));

                $this->response->redirect($this->url->link('user/user'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('user/user.edit|' . $user_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('user/user_form', $data));
    }

    /** Exclui via POST; jamais a si mesmo (guarda no modelo contratual do botão + servidor) */
    public function delete(string $user_id = '0'): void {
        $user_id = (int)$user_id;

        // GET/CLI nunca muta: trata como listagem
        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'user/user')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('user/user');

        $this->load->model('user/user');

        if ($user_id > 0 && $user_id !== $this->user->getUserId()) {
            if ($this->model_user_user->getUser($user_id)) {
                // F4-04 (ADR 0027): evitar deletar o último usuário ativo capaz de administrar usuários
                $this->load->model('user/user_group');

                if ($this->model_user_user_group->hasOtherActiveUserAdmin($user_id)) {
                    $this->model_user_user->deleteUser($user_id);

                    $this->setFlash($this->language->get('text_success_delete'));
                } else {
                    $this->setFlash($this->language->get('error_last_active_admin'), 'error');
                }
            }
        } elseif ($user_id === $this->user->getUserId()) {
            $this->setFlash($this->language->get('error_self_delete'), 'error');
        }

        $this->response->redirect($this->url->link('user/user'));
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $user Linha do banco ou defaults
     * @return array<string, mixed> Dados do formulário (GET) ou do POST
     */
    private function formData(array $user): array {
        $this->load->language('user/user');

        $data['heading_title']     = $this->language->get('heading_title');
        $data['text_edit']         = $this->language->get('text_edit');
        $data['text_add']          = $this->language->get('text_add');
        $data['entry_username']    = $this->language->get('entry_username');
        $data['entry_firstname']   = $this->language->get('entry_firstname');
        $data['entry_lastname']    = $this->language->get('entry_lastname');
        $data['entry_email']       = $this->language->get('entry_email');
        $data['entry_password']    = $this->language->get('entry_password');
        $data['entry_confirm']     = $this->language->get('entry_confirm');
        $data['entry_group']       = $this->language->get('entry_group');
        $data['entry_status']      = $this->language->get('entry_status');
        $data['text_password_hint'] = $this->language->get('text_password_hint');
        $data['button_save']       = $this->language->get('button_save');
        $data['button_cancel']     = $this->language->get('button_cancel');
        $data['error']             = '';
        $data['text_flash']        = '';

        $data['user_id']       = (int)($user['user_id'] ?? 0);
        $data['user_group_id'] = (int)($this->request->post['user_group_id'] ?? ($user['user_group_id'] ?? 0));
        $data['username']      = (string)($this->request->post['username'] ?? ($user['username'] ?? ''));
        $data['firstname']     = (string)($this->request->post['firstname'] ?? ($user['firstname'] ?? ''));
        $data['lastname']      = (string)($this->request->post['lastname'] ?? ($user['lastname'] ?? ''));
        $data['email']         = (string)($this->request->post['email'] ?? ($user['email'] ?? ''));
        $data['status']        = isset($this->request->post['status']) ? '1' : (string)($this->request->server['REQUEST_METHOD'] === 'POST' ? 0 : ($user['status'] ?? '1'));
        $data['cancel']        = $this->url->link('user/user');

        $data['user_groups'] = $this->model_user_user->getUserGroups();

        return $data;
    }

    /**
     * Payload normalizado do POST para os models (chaves SEMPRE presentes —
     * um ausente aqui viraria ErrorException em 'password' no add).
     *
     * @return array{user_group_id: int, username: string, password: string, email: string, firstname: string, lastname: string, status: int}
     */
    private function postPayload(): array {
        $post = $this->request->post;

        return [
            'user_group_id' => (int)($post['user_group_id'] ?? 0),
            'username'      => (string)($post['username'] ?? ''),
            'password'      => (string)($post['password'] ?? ''),
            'email'         => (string)($post['email'] ?? ''),
            'firstname'     => (string)($post['firstname'] ?? ''),
            'lastname'      => (string)($post['lastname'] ?? ''),
            'status'        => isset($post['status']) ? 1 : 0,
        ];
    }

    /**
     * Regras identicas às do wizard passo 4 (espelhamento honesto).
     *
     * @return array<int, string> Mensagens de erro (vazio = válido)
     */
    private function validateUser(int $exclude_user_id, array $data): array {
        $errors = [];
        $post   = $this->request->post;

        $username = (string)($post['username'] ?? '');

        if (mb_strlen($username) < 3 || mb_strlen($username) > 32) {
            $errors[] = $this->language->get('error_username');
        } else {
            $existing = $this->model_user_user->getUserByUsername($username);

            if ($existing && (int)$existing['user_id'] !== $exclude_user_id) {
                $errors[] = $this->language->get('error_username_exists');
            }
        }

        if (!filter_var((string)($post['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->language->get('error_email');
        }

        if (mb_strlen((string)($post['firstname'] ?? '')) < 1 || mb_strlen((string)$post['firstname']) > 32) {
            $errors[] = $this->language->get('error_firstname');
        }

        if (mb_strlen((string)($post['lastname'] ?? '')) < 1 || mb_strlen((string)$post['lastname']) > 32) {
            $errors[] = $this->language->get('error_lastname');
        }

        $password = (string)($post['password'] ?? '');

        if ($exclude_user_id === 0 || $password !== '') {
            if (mb_strlen($password) < 8) {
                $errors[] = $this->language->get('error_password');
            } elseif ($password !== (string)($post['confirm'] ?? '')) {
                $errors[] = $this->language->get('error_confirm');
            }
        }

        if ((int)($post['user_group_id'] ?? 0) <= 0) {
            $errors[] = $this->language->get('error_group');
        }

        return $errors;
    }

    /** Leia-uma-vez (consume) do flash da sessão */
    private function flash(): array {
        $session = $this->session;

        $data  = ['type' => '', 'text' => ''];

        if ($session instanceof \Reamur\System\Library\Session && !empty($session->data['flash'])) {
            $data = (array)$session->data['flash'];
            $data['type'] = (string)($data['type'] ?? 'success');
            $data['text'] = (string)($data['text'] ?? '');

            unset($session->data['flash']);
        }

        return $data;
    }

    private function setFlash(string $text, string $type = 'success'): void {
        $session = $this->session;

        if ($session instanceof \Reamur\System\Library\Session) {
            $session->data['flash'] = ['type' => $type, 'text' => $text];
        }
    }

    /** Chrome compartilhado + estilo do módulo */
    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
