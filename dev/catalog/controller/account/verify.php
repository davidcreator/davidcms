<?php declare(strict_types=1);
/** account/verify (CATALOG, KZ-38) — confirma e-mail via token (dracaunos_recovery). */
namespace Reamur\Catalog\Controller\Account;

class Verify extends \Reamur\System\Engine\Controller {
    public function index(): void {
        $this->load->language('account/account');
        $this->document->setTitle($this->language->get('heading_verify'));
        $data['ok'] = false;

        $token = trim((string)($this->request->get['token'] ?? ''));
        if ($token !== '') {
            $this->load->model('account/dracaunos');
            $drac = $this->model_account_dracaunos;
            $r = $drac->getRecoveryByToken($token);
            if ($r && $r['type'] === 'verification' && (int)$r['user_id'] > 0) {
                $drac->activateCustomer((int)$r['user_id']);
                $drac->consumeToken((int)$r['recovery_id'], 'approved');
                $data['ok'] = true;
            }
        }
        $data['login']  = $this->url->link('account/login');
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('account/verify', $data));
    }
}
