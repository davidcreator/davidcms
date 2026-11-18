<?php declare(strict_types=1);
/**
 * account/register (CATALOG, KZ-38) — cadastro com verificação de e-mail:
 * cria customer status=0 e grava token type='verification' em dracaunos_recovery.
 */
namespace Reamur\Catalog\Controller\Account;

class Register extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->load->language('account/register');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['errors'] = [];
        $data['old'] = ['firstname' => '', 'lastname' => '', 'email' => '', 'telephone' => ''];

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->load->model('account/dracaunos');
            $drac = $this->model_account_dracaunos;
            $post = $this->request->post;
            $data['old'] = array_map('strval', $post);

            if (!$this->csrfValid()) {
                $data['errors'][] = $this->language->get('error_csrf');
            } else {
                $email = trim((string)($post['email'] ?? ''));
                $pass  = (string)($post['password'] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $data['errors'][] = $this->language->get('error_email');
                if (strlen($pass) < 8) $data['errors'][] = $this->language->get('error_password');
                if ($drac->getCustomerByEmail($email)) $data['errors'][] = $this->language->get('error_exists');

                if (!$data['errors']) {
                    $id = $drac->createCustomer(
                        (string)($post['firstname'] ?? ''), (string)($post['lastname'] ?? ''),
                        $email, (string)($post['telephone'] ?? ''), $pass);
                    $token = $drac->addRecovery($id, $email, 'verification', (string)$this->request->ip());
                    // link de verificação (e-mail real fica para a fila de mail do CMS)
                    $data['verify_link'] = $this->url->link('account/verify') . '&token=' . $token;
                    $data['success'] = true;
                }
            }
        }

        $data['action']     = $this->url->link('account/register');
        $data['login']      = $this->url->link('account/login');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['header']     = $this->load->controller('common/header');
        $data['footer']     = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('account/register', $data));
    }
}
