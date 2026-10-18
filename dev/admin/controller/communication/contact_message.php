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
 * e exclui. Abrir uma mensagem nova marca-a como lida automaticamente
 * (semântica 'lido' do Dicionário §9). 'Respondido' é manual — a resposta
 * real acontece por e-mail (mailto: pré-preenchido na leitura).
 * Rotas: /communication/contact_message · .read|N · .status|N (POST)
 * · .delete|N (POST). Sem upload, sem SEO, sem tabela _description.
 */
namespace Reamur\Admin\Controller\Communication;

use Reamur\Admin\Model\Communication\Contact_message as MessageModel;

class Contact_message extends \Reamur\System\Engine\Controller {

    /** Grade com abas de filtro por status (Dicionário §9) */
    public function index(): void {
        $this->load->language('communication/contact_message');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('communication/contact_message');
        /** @var MessageModel $model */
        $model = $this->model_communication_contact_message;

        // Filtro: valor fora da whitelist vira "todas" (POST/GET adulterado não quebra a grade)
        $raw    = $this->request->get['filter_status'] ?? null;
        $filter = ($raw !== null && in_array((int)$raw, MessageModel::STATUSES, true)) ? (int)$raw : null;

        $data['heading_title']       = $this->language->get('heading_title');
        $data['text_list']           = $this->language->get('text_list');
        $data['text_no_results']     = ($filter === null) ? $this->language->get('text_no_results') : $this->language->get('text_empty_filter');
        $data['text_confirm_delete'] = $this->language->get('text_confirm_delete');
        $data['column_name']         = $this->language->get('column_name');
        $data['column_email']        = $this->language->get('column_email');
        $data['column_subject']      = $this->language->get('column_subject');
        $data['column_status']       = $this->language->get('column_status');
        $data['column_date_added']   = $this->language->get('column_date_added');
        $data['column_action']       = $this->language->get('column_action');
        $data['button_read']         = $this->language->get('button_read');
        $data['button_delete']       = $this->language->get('button_delete');
        $data['text_flash']          = $this->flash();

        $counts = $model->getCounts();

        $data['filters'] = [
            ['label' => $this->language->get('text_all'),     'count' => $counts['all'],     'href' => $this->url->link('communication/contact_message'), 'active' => ($filter === null)],
            ['label' => $this->language->get('text_new'),     'count' => $counts['new'],     'href' => $this->url->link('communication/contact_message') . '&filter_status=0', 'active' => ($filter === 0)],
            ['label' => $this->language->get('text_read'),    'count' => $counts['read'],    'href' => $this->url->link('communication/contact_message') . '&filter_status=1', 'active' => ($filter === 1)],
            ['label' => $this->language->get('text_replied'), 'count' => $counts['replied'], 'href' => $this->url->link('communication/contact_message') . '&filter_status=2', 'active' => ($filter === 2)],
        ];

        $data['messages'] = [];

        foreach ($model->getMessages($filter) as $msg) {
            $msg           = (array)$msg;
            $mid           = (int)$msg['contact_message_id'];

            $data['messages'][] = [
                'contact_message_id' => $mid,
                'name'               => (string)$msg['name'],
                'email'              => (string)$msg['email'],
                'subject'            => (string)$msg['subject'],
                'status'             => (int)$msg['status'],
                'status_label'       => $this->statusLabel((int)$msg['status']),
                'is_new'             => ((int)$msg['status'] === MessageModel::STATUS_NEW),
                'date_added'         => (string)$msg['date_added'],
                'read'               => $this->url->link('communication/contact_message.read|' . $mid),
                'delete'             => $this->url->link('communication/contact_message.delete|' . $mid),
            ];
        }

        $this->chrome($data);

        $this->response->setOutput($this->load->view('communication/contact_message_list', $data));
    }

    /** Detalhe read-only. Mensagem nova vira 'lida' ao abrir (auto-mark). */
    public function read(string $contact_message_id = '0'): void {
        $contact_message_id = (int)$contact_message_id;

        $this->load->language('communication/contact_message');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('communication/contact_message');
        /** @var MessageModel $model */
        $model = $this->model_communication_contact_message;

        $message = $model->getMessage($contact_message_id);

        if (!$message) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        // Auto-mark: abrir mensagem 'nova' → 'lida' (uma vez; reload não repete efeito visível)
        if ((int)$message['status'] === MessageModel::STATUS_NEW) {
            $model->updateStatus($contact_message_id, MessageModel::STATUS_READ);

            $message['status'] = MessageModel::STATUS_READ;
        }

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

        $this->load->model('communication/contact_message');
        /** @var MessageModel $model */
        $model = $this->model_communication_contact_message;

        $message = $model->getMessage($contact_message_id);

        $back = $this->readBackUrl($contact_message_id);

        if (!$message) {
            $this->response->redirect($this->url->link('communication/contact_message'));

            return;
        }

        $status = (int)($this->request->post['status'] ?? -1);

        if ($model->updateStatus($contact_message_id, $status)) {
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

        $this->load->model('communication/contact_message');
        /** @var MessageModel $model */
        $model = $this->model_communication_contact_message;

        if ($model->getMessage($contact_message_id)) {
            $model->deleteMessage($contact_message_id);

            $this->setFlash($this->language->get('text_success_delete'));
        }

        $this->response->redirect($this->url->link('communication/contact_message'));
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
        $this->document->addStyle($this->config->get('site_url') . 'view/css/admin.css');

        $data['body_class'] = 'rms-dash';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
