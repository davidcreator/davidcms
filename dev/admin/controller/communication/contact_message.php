<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * communication/contact_message — caixa de entrada do formulário de contato
 * (F2-15, ADR 0015). PRIMEIRO CRUD ASSIMÉTRICO do molde-ouro: sem .add e
 * sem .edit — mensagens nascem no front (catálogo "Let's have a chat?"),
 * o admin apenas lê, operacionaliza status (0 novo/1 lido/2 respondido)
 * e exclui. F8-10: o botão Ler faz POST+ACL+CSRF antes de marcar lida;
 * GET direto apenas visualiza (decisão explícita do usuário). 'Respondido' é manual — a resposta
 * real acontece por e-mail (mailto: pré-preenchido na leitura).
 * Rotas: /communication/contact_message · .read|N · .status|N (POST)
 * · .delete|N (POST). Sem upload, sem SEO, sem tabela _description.
 */
namespace Reamur\Admin\Controller\Communication;

use Reamur\Admin\Model\Communication\Contact_message as MessageModel;

class Contact_message extends \Reamur\System\Engine\Controller {

    /** Caixa de entrada com busca e paginação; contadores acompanham a busca. */
    public function index(): void {
        $this->load->language('communication/contact_message');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('communication/contact_message');
        /** @var MessageModel $model */
        $model = $this->model_communication_contact_message;
        $filter = $this->statusValue($this->request->get['filter_status'] ?? null);
        $rawSearch = $this->request->get['filter_search'] ?? '';
        $search = is_string($rawSearch) ? mb_substr(trim($rawSearch), 0, 255) : '';
        $requested = $this->request->get['page'] ?? 1;
        $options = ['search' => $search];
        $limit = 20;
        $total = $model->getTotalMessages($filter, $options);
        $page = is_scalar($requested) ? max(1, (int)$requested) : 1;
        $page = min($page, max(1, (int)ceil($total / $limit)));
        foreach ([
            'heading_title', 'text_list', 'text_confirm_delete', 'text_all', 'text_new', 'text_read', 'text_replied',
            'text_empty_title', 'text_empty_hint', 'text_search_placeholder',
            'column_name', 'column_email', 'column_subject', 'column_status', 'column_date_added', 'column_action',
            'button_read', 'button_delete', 'button_close', 'button_filter', 'button_reset',
        ] as $key) { $data[$key] = $this->language->get($key); }
        $data['text_no_results'] = $this->language->get($filter === null ? 'text_no_results' : 'text_empty_filter');
        $data['can_modify'] = $this->user->hasPermission('modify', 'communication/contact_message');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['text_flash'] = $this->flash();
        $counts = $model->getCounts($options);
        $data['filters'] = [];
        foreach ([['text_all', 'all', null], ['text_new', 'new', 0], ['text_read', 'read', 1], ['text_replied', 'replied', 2]] as [$label, $key, $status]) {
            $args = [];
            if ($search !== '') { $args['filter_search'] = $search; }
            if ($status !== null) { $args['filter_status'] = $status; }
            $data['filters'][] = ['label' => $this->language->get($label), 'count' => $counts[$key],
                'href' => $this->url->link('communication/contact_message', http_build_query($args)), 'active' => $filter === $status];
        }
        $data['messages'] = [];
        foreach ($model->getMessages($filter, $options + ['start' => ($page - 1) * $limit, 'limit' => $limit]) as $row) {
            $row = (array)$row; $mid = (int)$row['contact_message_id'];
            $data['messages'][] = [
                'contact_message_id' => $mid, 'name' => (string)$row['name'], 'email' => (string)$row['email'],
                'subject' => (string)$row['subject'], 'status' => (int)$row['status'],
                'status_label' => $this->statusLabel((int)$row['status']),
                'is_new' => (int)$row['status'] === MessageModel::STATUS_NEW, 'date_added' => (string)$row['date_added'],
                'read' => $this->url->link('communication/contact_message.read|' . $mid),
                'delete' => $this->url->link('communication/contact_message.delete|' . $mid),
            ];
        }
        $data['total_messages'] = $total; $data['filter_search'] = $search;
        $data['filter_status'] = $filter === null ? 'all' : (string)$filter;
        $data['filter_action'] = $this->url->link('communication/contact_message');
        $data['filter_reset'] = $this->url->link('communication/contact_message');
        $data['showing'] = $total > 0 ? sprintf($this->language->get('text_showing'), ($page - 1) * $limit + 1, min($page * $limit, $total), $total) : '';
        require_once DIR_SYSTEM . 'library/pagination.php';
        $pagination = new \Pagination(); $pagination->total = $total; $pagination->page = $page; $pagination->limit = $limit;
        $pagination->css_class = 'pagination pagination-sm mb-0';
        $pagination->text_first = $this->language->get('button_first_page');
        $pagination->text_last = $this->language->get('button_last_page');
        $pagination->text_next = $this->language->get('button_next_page');
        $pagination->text_prev = $this->language->get('button_previous_page');
        $pagination->url = $this->url->link('communication/contact_message', 'page={page}'
            . ($search !== '' ? '&filter_search=' . rawurlencode($search) : '')
            . ($filter !== null ? '&filter_status=' . $filter : ''));
        $data['pagination'] = str_replace('&amp;amp;', '&amp;', $pagination->render());
        $this->chrome($data);
        $this->response->setOutput($this->load->view('communication/contact_message_list', $data));
    }

    /** GET só visualiza. POST Ler protegido faz auto-mark e PRG para GET. */
    public function read(string $contact_message_id = '0'): void {
        $contact_message_id = (int)$contact_message_id;

        $this->load->language('communication/contact_message');

        $this->document->setTitle($this->language->get('heading_title'));
        $isPost = $this->request->server['REQUEST_METHOD'] === 'POST';
        if ($isPost && !$this->user->hasPermission('modify', 'communication/contact_message')) {
            $this->response->redirect($this->url->link('error/permission'));
            return;
        }
        if ($isPost && !$this->csrfValid()) {
            $this->setFlash($this->language->get('error_csrf'), 'danger');
            $this->response->redirect($this->url->link('communication/contact_message'));
            return;
        }

        $this->load->model('communication/contact_message');
        /** @var MessageModel $model */
        $model = $this->model_communication_contact_message;

        $message = $model->getMessage($contact_message_id);

        if (!$message) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        if ($isPost) {
            if ((int)$message['status'] === MessageModel::STATUS_NEW) {
                $model->updateStatus($contact_message_id, MessageModel::STATUS_READ);
            }
            $this->response->redirect($this->url->link('communication/contact_message.read|' . $contact_message_id));
            return;
        }
        $data['can_modify'] = $this->user->hasPermission('modify', 'communication/contact_message');
        $data['csrf_token'] = \Reamur\System\Library\Security\Csrf::token($this->session);
        $data['button_close'] = $this->language->get('button_close');
        $data['text_readonly_notice'] = $this->language->get('text_readonly_notice');

        $data['heading_title']      = $this->language->get('heading_title');
        $data['text_message_detail']= $this->language->get('text_message_detail');
        $data['text_actions']       = $this->language->get('text_actions');
        $data['text_confirm_delete']= $this->language->get('text_confirm_delete');
        $data['entry_name']         = $this->language->get('entry_name');
        $data['entry_email']        = $this->language->get('entry_email');
        $data['entry_telephone']    = $this->language->get('entry_telephone');
        $data['entry_subject']      = $this->language->get('entry_subject');
        $data['entry_message']      = $this->language->get('entry_message');
        $data['entry_status']       = $this->language->get('entry_status');
        $data['entry_ip']           = $this->language->get('entry_ip');
        $data['entry_date_added']   = $this->language->get('entry_date_added');
        $data['button_back']        = $this->language->get('button_back');
        $data['button_reply']       = $this->language->get('button_reply');
        $data['button_delete']      = $this->language->get('button_delete');
        $data['button_mark_replied']= $this->language->get('button_mark_replied');
        $data['button_mark_read']   = $this->language->get('button_mark_read');
        $data['button_mark_new']    = $this->language->get('button_mark_new');
        $data['text_new']           = $this->language->get('text_new');
        $data['text_flash']         = $this->flash();

        $status = (int)$message['status'];
        $email  = (string)$message['email'];
        $subject= (string)$message['subject'];

        $data['message'] = [
            'contact_message_id' => $contact_message_id,
            'name'               => (string)$message['name'],
            'email'              => $email,
            'telephone'          => (string)$message['telephone'],
            'subject'            => $subject,
            'message'            => (string)$message['message'],
            'ip'                 => (string)$message['ip'],
            'status'             => $status,
            'status_label'       => $this->statusLabel($status),
            'date_added'         => (string)$message['date_added'],
        ];

        // Resposta real por e-mail: mailto pré-preenchido (assunto Re: + corpo citado)
        $data['reply_href']  = 'mailto:' . $email
            . '?subject=' . rawurlencode('Re: ' . $subject)
            . '&body=' . rawurlencode("\n\n---\n" . (string)$message['message']);
        $data['status_action'] = $this->url->link('communication/contact_message.status|' . $contact_message_id);
        $data['delete']        = $this->url->link('communication/contact_message.delete|' . $contact_message_id);
        $data['back']          = $this->url->link('communication/contact_message');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('communication/contact_message_read', $data));
    }

    /** Muda status via POST (whitelist 0/1/2 no model; GET jamais muta) */
    public function status(string $contact_message_id = '0'): void {
        $contact_message_id = (int)$contact_message_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'communication/contact_message')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('communication/contact_message');
        if (!$this->csrfValid()) {
            $this->setFlash($this->language->get('error_csrf'), 'danger');
            $this->response->redirect($this->url->link('communication/contact_message'));
            return;
        }

        $this->load->model('communication/contact_message');
        /** @var MessageModel $model */
        $model = $this->model_communication_contact_message;

        $message = $model->getMessage($contact_message_id);

        $back = $this->readBackUrl($contact_message_id);

        if (!$message) {
            $this->response->redirect($this->url->link('communication/contact_message'));

            return;
        }

        $status = $this->statusValue($this->request->post['status'] ?? null);

        if ($status !== null && $model->updateStatus($contact_message_id, $status)) {
            $this->setFlash($this->language->get('text_success_status'));
        } else {
            // Status adulterado (ex.: 9, -1, 'x'): sem mutação, flash de erro
            $this->setFlash($this->language->get('error_status'), 'danger');
        }

        $this->response->redirect($back);
    }

    /** Exclui via POST (tabela única; GET jamais muta) */
    public function delete(string $contact_message_id = '0'): void {
        $contact_message_id = (int)$contact_message_id;

        if ($this->request->server['REQUEST_METHOD'] !== 'POST' || !$this->user->hasPermission('modify', 'communication/contact_message')) {
            $this->response->redirect($this->url->link('error/permission'));

            return;
        }

        $this->load->language('communication/contact_message');
        if (!$this->csrfValid()) {
            $this->setFlash($this->language->get('error_csrf'), 'danger');
            $this->response->redirect($this->url->link('communication/contact_message'));
            return;
        }

        $this->load->model('communication/contact_message');
        /** @var MessageModel $model */
        $model = $this->model_communication_contact_message;

        if ($model->getMessage($contact_message_id)) {
            $model->deleteMessage($contact_message_id);

            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('communication/contact_message'));
    }

    /** Evita que 'x', arrays, floats e strings não canônicas virem status 0. */
    private function statusValue(mixed $value): ?int {
        if (!is_string($value) && !is_int($value)) { return null; }
        $text = (string)$value;
        return in_array($text, ['0', '1', '2'], true) ? (int)$text : null;
    }

    private function csrfValid(): bool {
        return \Reamur\System\Library\Security\Csrf::check($this->session, $this->request->post['csrf_token'] ?? '');
    }

    /** Voltar para o detalhe (from=read) ou para a grade (padrão) */
    private function readBackUrl(int $id): string {
        return (($this->request->post['from'] ?? '') === 'read')
            ? $this->url->link('communication/contact_message.read|' . $id)
            : $this->url->link('communication/contact_message');
    }

    /** Rótulo do status (0 novo / 1 lido / 2 respondido; fallback novo) */
    private function statusLabel(int $status): string {
        return match ($status) {
            MessageModel::STATUS_READ    => $this->language->get('text_read'),
            MessageModel::STATUS_REPLIED => $this->language->get('text_replied'),
            default                      => $this->language->get('text_new'),
        };
    }

    /** Leia-uma-vez (consume) do flash da sessão — mesmo helper dos demais CRUDs */
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
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css?v=' . \Reamur\Admin\Controller\Common\Header::ASSET_V);

        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
