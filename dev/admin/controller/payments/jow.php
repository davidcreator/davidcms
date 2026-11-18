<?php
/**
 * JOW-payments — painel interno de pagamentos do ReamurCMS (KZ-35).
 * Rota: payments/jow (somente admin; ACL access/modify via user_group).
 * Seções: cobranças (com PIX Copia e Cola), chaves PIX, relatório de recebimentos, config da loja.
 * Ferramenta INTERNA: não aparece no catálogo público.
 */
namespace Reamur\Admin\Controller\Payments;

class Jow extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $this->load->language('payments/jow');
        $this->load->model('payments/jow');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [
            ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard')],
            ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('payments/jow')],
        ];
        $data['can_modify'] = $this->user->hasPermission('modify', 'payments/jow');
        $data['action']     = $this->url->link('payments/jow.save');
        $data['csrf_token'] = $this->session->data['csrf_token'] ?? '';

        $data['charges']   = $this->model_payments_jow->getCharges(50);
        $data['pix_keys']  = $this->model_payments_jow->getPixKeys();
        $data['report']    = $this->model_payments_jow->report();
        $data['store']     = [
            'jow_store_name'          => (string)$this->config->get('jow_store_name'),
            'jow_city'                => (string)$this->config->get('jow_city'),
            'jow_merchant_category'   => (string)$this->config->get('jow_merchant_category'),
        ];
        $data['error']     = $this->session->data['error'] ?? '';
        $data['success']   = $this->session->data['success'] ?? '';
        unset($this->session->data['error'], $this->session->data['success']);

        // KZ-54: admin.css é o layout do shell (sidebar/topbar); sem ele a página quebra
        $this->document->addStyle($this->adminAssetUrl() . 'view/css/admin.css?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('payments/jow', $data));
    }

    public function save(): void {
        $this->load->language('payments/jow');
        if (!$this->user->hasPermission('modify', 'payments/jow')
            || $this->request->server['REQUEST_METHOD'] !== 'POST'
            || !$this->user->csrfValid()) {
            $this->response->redirect($this->url->link('payments/jow'));
        }
        $this->load->model('payments/jow');
        $post = $this->request->post;
        $action = (string)($post['action'] ?? '');

        switch ($action) {
            case 'charge_add':
                $amount = (float)str_replace(',', '.', (string)($post['amount'] ?? '0'));
                if ($amount <= 0) {
                    $this->session->data['error'] = $this->language->get('error_amount');
                    break;
                }
                $method = (string)($post['method'] ?? 'pix');
                $id = $this->model_payments_jow->addCharge(
                    (string)($post['description'] ?? ''), $amount, $method,
                    (string)($post['payer_email'] ?? ''), $post['due_date'] ?? null);
                if ($method === 'pix') {
                    $key = $this->model_payments_jow->defaultPixKey();
                    if ($key !== '') {
                        $this->model_payments_jow->attachPix($id, $this->model_payments_jow->buildPixPayload($key, $amount, (string)($post['description'] ?? '')));
                    }
                }
                $this->session->data['success'] = $this->language->get('text_charge_added');
                break;
            case 'charge_status':
                $this->model_payments_jow->setChargeStatus((int)($post['charge_id'] ?? 0), (string)($post['status'] ?? 'pending'));
                $this->session->data['success'] = $this->language->get('text_charge_updated');
                break;
            case 'pix_add':
                $ok = $this->model_payments_jow->addPixKey((string)($post['type'] ?? ''), (string)($post['key'] ?? ''), (string)($post['holder'] ?? ''));
                $this->session->data[$ok ? 'success' : 'error'] = $this->language->get($ok ? 'text_pix_saved' : 'error_pix_invalid');
                break;
            case 'pix_delete':
                $this->model_payments_jow->deletePixKey((int)($post['key_id'] ?? 0));
                $this->session->data['success'] = $this->language->get('text_pix_deleted');
                break;
            case 'store_save':
                $this->model_payments_jow->saveStore($post);
                $this->session->data['success'] = $this->language->get('text_store_saved');
                break;
        }
        $this->response->redirect($this->url->link('payments/jow'));
    }
}
