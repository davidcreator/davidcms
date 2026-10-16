<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Wizard — Passo 4: Conta do administrador.
 * Só roda após o passo 3 (credenciais na sessão efêmera do wizard).
 * Colunas escritas 1:1 com docs/Dicionario-de-Dados.md (rms_user / rms_user_group).
 */
namespace Reamur\Install\Controller\Install;

class Step_4 extends \Reamur\System\Engine\Controller {

    /** GET exibe o formulário; POST cria grupo + usuário e conclui a instalação */
    public function index(): void {
        $this->load->language('install/step_4');
        $this->load->model('install/install');

        $this->document->setTitle($this->language->get('heading_title'));

        // Guarda de sequência: sem credenciais validadas, volta ao banco
        if (empty($this->session->data['install_db'])) {
            $this->response->redirect($this->url->link('install/step_3'));
            return;
        }

        $data['error'] = [];

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $data['error'] = $this->validate();

            if (!$data['error']) {
                $db = $this->session->data['install_db'];

                if (!$this->model_install_install->connect($db, $error_msg)) {
                    $data['error']['warning'] = $this->language->get('error_connect') . ' ' . $error_msg;
                } else {
                    $this->model_install_install->createAdministrator([
                        'username'  => trim((string)$this->request->post['username']),
                        'firstname' => trim((string)$this->request->post['firstname']),
                        'lastname'  => trim((string)$this->request->post['lastname']),
                        'email'     => trim((string)$this->request->post['email']),
                        'password'  => (string)$this->request->post['password'],
                    ], $db['db_prefix']);

                    $this->session->data['install_done'] = true;

                    $this->response->redirect($this->url->link('install/step_5'));
                    return;
                }
            }
        }

        foreach (['username', 'firstname', 'lastname', 'email'] as $field) {
            $data[$field] = $this->request->post[$field] ?? '';
        }

        $data['action'] = $this->url->link('install/step_4');
        $data['back']   = $this->url->link('install/step_3');

        $this->response->setOutput($this->load->view('install/step_4', $data));
    }

    /**
     * Limites conforme Dicionario-de-Dados (username/firstname/lastname 32, email 96).
     *
     * @return array<string, string>
     */
    private function validate(): array {
        $error = [];

        $username = trim((string)($this->request->post['username'] ?? ''));

        if (mb_strlen($username) < 3 || mb_strlen($username) > 32) {
            $error['username'] = $this->language->get('error_username');
        }

        $email = trim((string)($this->request->post['email'] ?? ''));

        if ($email === '' || mb_strlen($email) > 96 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error['email'] = $this->language->get('error_email');
        }

        foreach (['firstname', 'lastname'] as $field) {
            $value = trim((string)($this->request->post[$field] ?? ''));

            if (mb_strlen($value) < 1 || mb_strlen($value) > 32) {
                $error[$field] = $this->language->get('error_' . $field);
            }
        }

        $password = (string)($this->request->post['password'] ?? '');

        if (strlen($password) < 8) {
            $error['password'] = $this->language->get('error_password');
        } elseif (!hash_equals($password, (string)($this->request->post['confirm'] ?? ''))) {
            $error['confirm'] = $this->language->get('error_confirm');
        }

        return $error;
    }
}
