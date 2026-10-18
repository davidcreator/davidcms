<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * sale/order — CRUD de pedidos/orçamentos (F2-14, ADR 0014) — sétimo CRUD
 * F2 e o primeiro do grupo sale/. Relacional: order + order_product (linhas
 * dinâmicas) + order_history (vira registro a cada mudança de status,
 * Dicionário-de-Dados) + order_status (seeded, coalesce). Totais SEMPRE
 * recalculados no servidor (qty*price, Σ) — o POST nunca é confiado.
 * Sem SEO, sem imagem/upload. Form operacional monolíngue (dados de cliente).
 * Rotas: /sale/order · .add · .edit|N · .delete|N (POST)
 */
namespace Reamur\Admin\Controller\Sale;

class Order extends \Reamur\System\Engine\Controller {

    /** Lista pedidos (200) */
    public function index(): void {
        $this->load->language('sale/order');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('sale/order');

        $data['heading_title']       = $this->language->get('heading_title');
        $data['text_list']           = $this->language->get('text_list');
        $data['text_no_results']     = $this->language->get('text_no_results');
        $data['text_confirm_delete'] = $this->language->get('text_confirm_delete');
        $data['column_order']        = $this->language->get('column_order');
        $data['column_customer']     = $this->language->get('column_customer');
        $data['column_email']        = $this->language->get('column_email');
        $data['column_total']        = $this->language->get('column_total');
        $data['column_status']       = $this->language->get('column_status');
        $data['column_date_added']   = $this->language->get('column_date_added');
        $data['column_date_modified'] = $this->language->get('column_date_modified');
        $data['column_action']       = $this->language->get('column_action');
        $data['button_add']          = $this->language->get('button_add');
        $data['button_edit']         = $this->language->get('button_edit');
        $data['button_delete']       = $this->language->get('button_delete');
        $data['text_flash']          = $this->flash();

        $data['orders'] = [];

        foreach ($this->model_sale_order->getOrders() as $order) {
            $order = (array)$order;
            $oid   = (int)$order['order_id'];

            $data['orders'][] = [
                'order_id'      => $oid,
                'customer'      => trim((string)$order['firstname'] . ' ' . (string)$order['lastname']),
                'email'         => (string)$order['email'],
                'total'         => number_format((float)$order['total'], 2, '.', ''),
                'status_name'   => (string)$order['status_name'],
                'date_added'    => (string)$order['date_added'],
                'date_modified' => (string)$order['date_modified'],
                'edit'          => $this->url->link('sale/order.edit|' . $oid),
                'delete'        => $this->url->link('sale/order.delete|' . $oid),
            ];
        }

        $data['add'] = $this->url->link('sale/order.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('sale/order_list', $data));
    }

    /** GET: formulário de criação · POST: valida e persiste; sucesso → lista + flash */
    public function add(): void {
        if (!$this->user->hasPermission('modify', 'sale/order') && $this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('sale/order');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('sale/order');

        $data = $this->formData([]);

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validateOrder();

            if ($errors === []) {
                $order_id = $this->model_sale_order->addOrder($this->postPayload());

                if ($order_id) {
                    $this->setFlash($this->language->get('text_success_add'));

                    $this->response->redirect($this->url->link('sale/order'));

                    return;
                }
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('sale/order.add');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('sale/order_form', $data));
    }

    /** GET: formulário de edição · POST: valida e persiste (args do Action são strings) */
    public function edit(string $order_id = '0'): void {
        $order_id = (int)$order_id;

        $this->load->language('sale/order');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('sale/order');

        $order = $this->model_sale_order->getOrder($order_id);

        if (!$order) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $data          = $this->formData($order);
        $data['error'] = '';

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if (!$this->user->hasPermission('modify', 'sale/order')) {
                $this->response->redirect($this->url->link('error/permission'));

                return;
            }

            $errors = $this->validateOrder();

            if ($errors === []) {
                $this->model_sale_order->editOrder($order_id, $this->postPayload($order));

                $this->setFlash($this->language->get('text_success_edit'));

                $this->response->redirect($this->url->link('sale/order'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('sale/order.edit|' . $order_id);

        $this->chrome($data);

        $this->response->setOutput($this->load->view('sale/order_form', $data));
    }

    /** Exclui via POST (cascata manual: itens + histórico; GET jamais muta) */
    public function delete(string $order_id = '0'): void {
        $order_id = (int)$order_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'sale/order')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('sale/order');

        $this->load->model('sale/order');

        if ($this->model_sale_order->getOrder($order_id)) {
            $this->model_sale_order->deleteOrder($order_id);

            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('sale/order'));
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $order Linha do banco ou [] (create)
     * @return array<string, mixed> Dados do formulário (GET) ou do POST
     */
    private function formData(array $order): array {
        $this->load->language('sale/order');

        foreach ([
            'heading_title', 'text_edit', 'text_add', 'text_customer', 'text_products', 'text_data',
            'text_history', 'text_status_hint', 'text_no_products',
            'entry_firstname', 'entry_lastname', 'entry_email', 'entry_telephone',
            'entry_product', 'entry_quantity', 'entry_price', 'entry_total', 'entry_comment', 'entry_status',
            'button_save', 'button_cancel', 'button_add_product', 'button_remove',
            'column_date_added', 'column_status', 'column_comment',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error']      = '';
        $data['text_flash'] = '';

        $oid    = (int)($order['order_id'] ?? 0);
        $isPost = $this->request->server['REQUEST_METHOD'] === 'POST';
        $post   = $this->request->post;

        $data['order_id']       = $oid;
        $data['firstname']      = (string)($post['firstname'] ?? ($order['firstname'] ?? ''));
        $data['lastname']       = (string)($post['lastname'] ?? ($order['lastname'] ?? ''));
        $data['email']          = (string)($post['email'] ?? ($order['email'] ?? ''));
        $data['telephone']      = (string)($post['telephone'] ?? ($order['telephone'] ?? ''));
        $data['comment']        = (string)($post['comment'] ?? ($order['comment'] ?? ''));
        $data['order_status_id'] = (int)($post['order_status_id'] ?? (int)($order['order_status_id'] ?? 1));

        // Itens: POST (repovoa após erro) → banco; sempre reformatados p/ exibição
        $source = [];
        if ($isPost) {
            foreach ((array)($post['order_product'] ?? []) as $p) {
                $p = (array)$p;
                $source[] = [
                    'name'     => (string)($p['name'] ?? ''),
                    'quantity' => (string)($p['quantity'] ?? '1'),
                    'price'    => (string)($p['price'] ?? '0.0000'),
                ];
            }
        } else {
            foreach ($oid ? $this->model_sale_order->getOrderProducts($oid) : [] as $p) {
                $p = (array)$p;
                $source[] = [
                    'name'     => (string)$p['name'],
                    'quantity' => (string)(int)$p['quantity'],
                    'price'    => number_format((float)$p['price'], 4, '.', ''),
                ];
            }
        }
        $data['products'] = $source;
        $data['history']  = $oid ? $this->model_sale_order->getOrderHistory($oid) : [];

        $data['statuses'] = array_map(
            fn(array $row): array => ['order_status_id' => (int)$row['order_status_id'], 'name' => (string)$row['name']],
            $this->model_sale_order->getOrderStatusOptions()
        );

        $data['cancel']   = $this->url->link('sale/order');

        return $data;
    }

    /**
     * Payload normalizado do POST — chaves SEMPRE presentes (lição F2).
     * RECALCULA tudo: total da linha = qty*price; total do pedido = Σ linhas
     * (formatado DECIMAL(15,4)). Linhas totalmente vazias são descartadas.
     *
     * @param array<string, mixed> $existing Linha atual (p/ detectar mudança de status; [] no create)
     * @return array<string, mixed>
     */
    private function postPayload(array $existing = []): array {
        $post = $this->request->post;

        $products = [];
        $total    = 0.0;

        foreach ((array)($post['order_product'] ?? []) as $p) {
            $p = (array)$p;

            $name     = mb_substr(trim((string)($p['name'] ?? '')), 0, 255);
            $quantity = (int)($p['quantity'] ?? 1);
            $price    = $this->toDecimal($p['price'] ?? '0');

            if ($name === '') {
                continue; // linha vazia → fora
            }

            $quantity  = max(1, $quantity);
            $lineTotal = round($quantity * $price, 4);
            $total    += $lineTotal;

            $products[] = [
                'name'     => $name,
                'quantity' => $quantity,
                'price'    => number_format($price, 4, '.', ''),
                'total'    => number_format($lineTotal, 4, '.', ''),
            ];
        }

        $status_id = (int)($post['order_status_id'] ?? 1);

        return [
            'customer_id'     => 0, // orçamento/visitante (admin não edita customers — Fase futura)
            'firstname'       => mb_substr(trim((string)($post['firstname'] ?? '')), 0, 32),
            'lastname'        => mb_substr(trim((string)($post['lastname'] ?? '')), 0, 32),
            'email'           => mb_substr(trim((string)($post['email'] ?? '')), 0, 96),
            'telephone'       => mb_substr(trim((string)($post['telephone'] ?? '')), 0, 32),
            'total'           => number_format($total, 4, '.', ''),
            'order_status_id' => $status_id,
            'comment'         => trim((string)($post['comment'] ?? '')),
            'ip'              => (string)($this->request->server['REMOTE_ADDR'] ?? ''),
            'status_changed'  => $existing !== [] && (int)($existing['order_status_id'] ?? 0) !== $status_id ? 1 : 0,
            'products'        => $products,
        ];
    }

    /**
     * @return array<int, string> Mensagens de erro (vazio = válido)
     */
    private function validateOrder(): array {
        $errors = [];
        $post   = $this->request->post;

        $firstname = trim((string)($post['firstname'] ?? ''));

        if ($firstname === '' || mb_strlen($firstname) > 32) {
            $errors[] = $this->language->get('error_firstname');
        }

        $email = trim((string)($post['email'] ?? ''));

        if ($email === '' || mb_strlen($email) > 96 || !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
            $errors[] = $this->language->get('error_email');
        }

        // Linhas de item: se algum campo veio preenchido mas o nome ficou vazio
        foreach ((array)($post['order_product'] ?? []) as $p) {
            $p = (array)$p;

            $name = trim((string)($p['name'] ?? ''));

            if ($name === '' && ((string)($p['price'] ?? '') !== '' || (string)($p['quantity'] ?? '') !== '')) {
                $errors[] = $this->language->get('error_product');

                break;
            }
        }

        // Status precisa existir (POST adulterado)
        $status_id = (int)($post['order_status_id'] ?? 1);
        $valid     = array_column($this->model_sale_order->getOrderStatusOptions(), 'order_status_id');

        if (!in_array($status_id, array_map('intval', $valid), true)) {
            $errors[] = $this->language->get('error_status');
        }

        return $errors;
    }

    /** Converte "1234.56" (com vírgula ou ponto) para float ≥ 0 — sem warnings */
    private function toDecimal(mixed $raw): float {
        $raw = str_replace(',', '.', trim((string)$raw));

        if ($raw === '' || !preg_match('/^\d+(\.\d{1,4})?$/', $raw)) {
            return 0.0;
        }

        return max(0.0, (float)$raw);
    }

    /** Leia-uma-vez (consume) do flash da sessão */
    private function flash(): array {
        $session = $this->session;

        $data = ['type' => '', 'text' => ''];

        if ($session instanceof \Reamur\System\Library\Session && !empty($session->data['flash'])) {
            $data         = (array)$session->data['flash'];
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
