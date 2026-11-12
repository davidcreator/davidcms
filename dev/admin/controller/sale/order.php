<?php declare(strict_types=1);
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

    /** Lista pedidos com filtros server-side e paginação. */
    public function index(): void {
        $this->load->language('sale/order');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('sale/order');

        foreach ([
            'heading_title', 'text_list', 'text_no_results', 'text_no_results_filtered',
            'text_confirm_delete', 'text_close', 'text_filter', 'text_all_status', 'text_showing',
            'column_order', 'column_customer', 'column_email', 'column_total', 'column_status',
            'column_date_added', 'column_date_modified', 'column_action',
            'entry_filter_order_id', 'entry_filter_customer', 'entry_filter_email',
            'button_filter', 'button_reset', 'button_add', 'button_edit', 'button_delete', 'button_mp_paylink',
        ] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['text_flash'] = $this->flash();
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['can_modify'] = $this->user->hasPermission('modify', 'sale/order');
        $data['can_mp_paylink'] = $this->user->hasPermission('modify', 'setting/marketplace');

        $filter_order_id = $this->queryValue('filter_order_id');
        if ($filter_order_id !== '' && !ctype_digit($filter_order_id)) {
            $filter_order_id = '';
        }
        $filter_order_id = $filter_order_id !== '' ? (string)(int)$filter_order_id : '';
        if ($filter_order_id === '0') {
            $filter_order_id = '';
        }

        $filter_customer = mb_substr($this->queryValue('filter_customer'), 0, 128);
        $filter_email = mb_substr($this->queryValue('filter_email'), 0, 96);
        $status_options = array_map(
            static fn(array $row): array => [
                'order_status_id' => (int)$row['order_status_id'],
                'name' => (string)$row['name'],
            ],
            $this->model_sale_order->getOrderStatusOptions()
        );
        $valid_status_ids = array_column($status_options, 'order_status_id');
        $filter_status = $this->queryValue('filter_status');
        $filter_status_id = ctype_digit($filter_status) ? (int)$filter_status : 0;
        if ($filter_status_id <= 0 || !in_array($filter_status_id, $valid_status_ids, true)) {
            $filter_status = 'all';
            $filter_status_id = 0;
        } else {
            $filter_status = (string)$filter_status_id;
        }

        $filters = [
            'order_id' => (int)$filter_order_id,
            'customer' => $filter_customer,
            'email' => $filter_email,
            'status_id' => $filter_status_id,
        ];
        $page = max(1, (int)$this->queryValue('page'));
        $limit = 20;
        $total = $this->model_sale_order->getTotalOrders($filters);
        $page = $total > 0 ? min($page, (int)ceil($total / $limit)) : 1;
        $rows = $this->model_sale_order->getOrders($filters, ($page - 1) * $limit, $limit);

        $data['orders'] = [];
        foreach ($rows as $order) {
            $order = (array)$order;
            $oid = (int)$order['order_id'];
            $data['orders'][] = [
                'order_id' => $oid,
                'customer' => trim((string)$order['firstname'] . ' ' . (string)$order['lastname']),
                'email' => (string)$order['email'],
                'total' => number_format((float)$order['total'], 2, '.', ''),
                'status_name' => (string)$order['status_name'],
                'date_added' => (string)$order['date_added'],
                'date_modified' => (string)$order['date_modified'],
                'edit' => $this->url->link('sale/order.edit|' . $oid),
                'delete' => $this->url->link('sale/order.delete|' . $oid),
                'paylink' => $data['can_mp_paylink'] ? $this->url->link('setting/marketplace.paylink|' . $oid) : '',
            ];
        }

        $data['statuses'] = $status_options;
        $data['add'] = $this->url->link('sale/order.add');
        $data['filter_action'] = $this->url->link('sale/order');
        $data['filter_order_id'] = $filter_order_id;
        $data['filter_customer'] = $filter_customer;
        $data['filter_email'] = $filter_email;
        $data['filter_status'] = $filter_status;
        $data['filter_status_id'] = $filter_status_id;
        $data['filters_active'] = $filter_order_id !== '' || $filter_customer !== '' || $filter_email !== '' || $filter_status !== 'all';
        $data['total_orders'] = $total;
        $data['showing'] = $total > 0
            ? sprintf($data['text_showing'], (($page - 1) * $limit) + 1, min($page * $limit, $total), $total)
            : '';

        $query = 'page={page}';
        foreach ([
            'filter_order_id' => $filter_order_id,
            'filter_customer' => $filter_customer,
            'filter_email' => $filter_email,
            'filter_status' => $filter_status === 'all' ? '' : $filter_status,
        ] as $key => $value) {
            if ($value !== '') {
                $query .= '&' . $key . '=' . rawurlencode($value);
            }
        }
        $data['pagination'] = $this->adminPagination(
            $total,
            $page,
            $limit,
            $this->url->link('sale/order', $query),
            $this->paginationLabels()
        );

        $this->adminChrome($data, \Reamur\Admin\Controller\Common\Header::ASSET_V, 'rms-dash');
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
            $errors = $this->csrfValid()
                ? $this->validateOrder()
                : [$this->language->get('error_csrf')];

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

        $this->adminChrome($data, \Reamur\Admin\Controller\Common\Header::ASSET_V, 'rms-dash');

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

            $errors = $this->csrfValid()
                ? $this->validateOrder()
                : [$this->language->get('error_csrf')];

            if ($errors === []) {
                $status_mudou = (int)($order['order_status_id'] ?? 0) !== (int)($this->request->post['order_status_id'] ?? ($order['order_status_id'] ?? 0));

                $this->model_sale_order->editOrder($order_id, $this->postPayload($order));

                $this->dispatchWebhookEvent('order_edit', fn(): array => $this->webhookOrderPayload($order_id), 'pedido #' . $order_id);

                if ($status_mudou) {
                    $this->dispatchWebhookEvent('order_history_edit', fn(): array => $this->webhookOrderPayload($order_id), 'pedido #' . $order_id);
                }

                $this->setFlash($this->language->get('text_success_edit'));

                $this->response->redirect($this->url->link('sale/order'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('sale/order.edit|' . $order_id);

        $this->adminChrome($data, \Reamur\Admin\Controller\Common\Header::ASSET_V, 'rms-dash');

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

        if (!$this->csrfValid()) {
            $this->setFlash($this->language->get('error_csrf'), 'error');
            $this->response->redirect($this->url->link('sale/order'));

            return;
        }

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
            'heading_title', 'text_list', 'text_edit', 'text_add', 'text_customer', 'text_products', 'text_data',
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
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['can_modify'] = $this->user->hasPermission('modify', 'sale/order');

        $oid    = (int)($order['order_id'] ?? 0);
        $isPost = $this->request->server['REQUEST_METHOD'] === 'POST';
        $post   = $this->request->post;

        $statusRaw = trim($this->postScalar('order_status_id', (string)($order['order_status_id'] ?? 1)));
        $scalarStr = static fn(mixed $v, string $default = ''): string => is_scalar($v) ? (string)$v : $default;

        $data['order_id']       = $oid;
        $data['firstname']      = $this->postScalar('firstname', (string)($order['firstname'] ?? ''));
        $data['lastname']       = $this->postScalar('lastname', (string)($order['lastname'] ?? ''));
        $data['email']          = $this->postScalar('email', (string)($order['email'] ?? ''));
        $data['telephone']      = $this->postScalar('telephone', (string)($order['telephone'] ?? ''));
        $data['comment']        = $this->postScalar('comment', (string)($order['comment'] ?? ''));
        $data['order_status_id'] = preg_match('/^-?\d+$/', $statusRaw) === 1 ? (int)$statusRaw : 1;

        // Itens: POST (repovoa após erro) → banco; sempre reformatados p/ exibição
        $source = [];
        if ($isPost) {
            $rawProducts = is_array($post['order_product'] ?? null) ? $post['order_product'] : [];
            foreach ($rawProducts as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $source[] = [
                    'name'     => $scalarStr($p['name'] ?? ''),
                    'quantity' => $scalarStr($p['quantity'] ?? '1', '1'),
                    'price'    => $scalarStr($p['price'] ?? '0.0000', '0.0000'),
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
        $post      = $this->request->post;
        $scalarStr = static fn(mixed $v, string $default = ''): string => is_scalar($v) ? (string)$v : $default;
        $scalarInt = static fn(mixed $v, int $default = 0): int => is_scalar($v) && preg_match('/^-?\d+$/', trim((string)$v)) === 1 ? (int)$v : $default;

        $products = [];
        $total    = 0.0;
        $rawProducts = is_array($post['order_product'] ?? null) ? $post['order_product'] : [];

        foreach ($rawProducts as $p) {
            if (!is_array($p)) {
                continue;
            }

            $name     = mb_substr(trim($scalarStr($p['name'] ?? '')), 0, 255);
            $quantity = $scalarInt($p['quantity'] ?? 1, 1);
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

        $status_id = $scalarInt($this->postScalar('order_status_id', '1'), 1);

        return [
            'customer_id'     => 0, // orçamento/visitante (admin não edita customers — Fase futura)
            'firstname'       => mb_substr(trim($this->postScalar('firstname')), 0, 32),
            'lastname'        => mb_substr(trim($this->postScalar('lastname')), 0, 32),
            'email'           => mb_substr(trim($this->postScalar('email')), 0, 96),
            'telephone'       => mb_substr(trim($this->postScalar('telephone')), 0, 32),
            'total'           => number_format($total, 4, '.', ''),
            'order_status_id' => $status_id,
            'comment'         => trim($this->postScalar('comment')),
            'ip'              => $this->request->ip(),
            'status_changed'  => $existing !== [] && (int)($existing['order_status_id'] ?? 0) !== $status_id ? 1 : 0,
            'products'        => $products,
        ];
    }

    /**
     * @return array<int, string> Mensagens de erro (vazio = válido)
     */
    private function validateOrder(): array {
        $errors    = [];
        $post      = $this->request->post;
        $scalarStr = static fn(mixed $v): string => is_scalar($v) ? (string)$v : '';

        $firstname = trim($this->postScalar('firstname'));

        if ($firstname === '' || mb_strlen($firstname) > 32) {
            $errors[] = $this->language->get('error_firstname');
        }

        $email = trim($this->postScalar('email'));

        if ($email === '' || mb_strlen($email) > 96 || !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
            $errors[] = $this->language->get('error_email');
        }

        // Linhas de item: se algum campo veio preenchido mas o nome ficou vazio
        $rawProducts = is_array($post['order_product'] ?? null) ? $post['order_product'] : [];
        foreach ($rawProducts as $p) {
            if (!is_array($p)) {
                continue;
            }

            $name = trim($scalarStr($p['name'] ?? ''));

            if ($name === '' && ($scalarStr($p['price'] ?? '') !== '' || $scalarStr($p['quantity'] ?? '') !== '')) {
                $errors[] = $this->language->get('error_product');

                break;
            }
        }

        // Status precisa existir (POST adulterado)
        $statusRaw = trim($this->postScalar('order_status_id', '1'));
        $status_id = preg_match('/^-?\d+$/', $statusRaw) === 1 ? (int)$statusRaw : 0;
        $valid     = array_column($this->model_sale_order->getOrderStatusOptions(), 'order_status_id');

        if (!in_array($status_id, array_map('intval', $valid), true)) {
            $errors[] = $this->language->get('error_status');
        }

        return $errors;
    }

    /** Converte "1234.56" (com vírgula ou ponto) para float ≥ 0 — sem warnings */
    private function toDecimal(mixed $raw): float {
        if (!is_scalar($raw)) {
            return 0.0;
        }
        $raw = str_replace(',', '.', trim((string)$raw));

        if ($raw === '' || !preg_match('/^\d+(\.\d{1,4})?$/', $raw)) {
            return 0.0;
        }

        return max(0.0, (float)$raw);
    }
    // ── F5-01 (ADR 0033) — notificação de webhooks ────────────────────────
    /**
     * Payload de domínio (mesmo formato do catálogo — receptores tratam um
     * único schema independente da aplicação de origem). Itens quando
     * existirem; model já expõe getOrderProducts.
     *
     * @return array<string, mixed>
     */
    private function webhookOrderPayload(int $order_id): array {
        $order = $this->model_sale_order->getOrder($order_id);

        return [
            'order_id'  => $order_id,
            'firstname' => (string)($order['firstname'] ?? ''),
            'lastname'  => (string)($order['lastname'] ?? ''),
            'email'     => (string)($order['email'] ?? ''),
            'telephone' => (string)($order['telephone'] ?? ''),
            'comment'   => (string)($order['comment'] ?? ''),
            'total'     => (float)($order['total'] ?? 0),
            'status_id' => (int)($order['order_status_id'] ?? 0),
            'items'     => $this->model_sale_order->getOrderProducts($order_id),
        ];
    }
}
