<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * common/contact (CATALOG) — formulário público "Let's have a chat?" (F2-17):
 * cria a ÚNICA escrita do front — INSERT em rms_contact_message (status 0,
 * IP do cliente) + notificação por e-mail ao dono do site (F4-05, ADR 0029 —
 * falha silenciosa). Anti-spam: honeypot invisível 'company' (bot preenche —
 * fingimos sucesso e não gravamos/notificamos). Validação espelha a régua do
 * schema; repovo padrão molde-ouro; PRG com flash (sessão) para a própria página.
 */
namespace Reamur\Catalog\Controller\Common;

class Contact extends \Reamur\System\Engine\Controller {

    /** GET: formulário · POST: valida e grava; sucesso → flash + redirect */
    public function index(): void {
        $this->load->language('common/contact');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->setDescription($this->language->get('text_lead'));

        $data = $this->formData();

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if ($this->isBot()) {
                // Finge sucesso para bots: flash normal também redireciona
                $this->setFlash($this->language->get('text_success'));

                $this->response->redirect($this->url->link('common/contact'));

                return;
            }

            // F2-18 (ADR 0018): CSRF antes de qualquer validação; rate limit por IP.
            $errors = [];

            if (!\Reamur\System\Library\Security\Csrf::check($this->session, $this->request->post['csrf_token'] ?? '')) {
                $errors[] = $this->language->get('error_csrf');
            }

            if ($errors === [] && !$this->rateLimitAllow('contact', 5, 600)) {
                $errors[] = $this->language->get('error_rate_limit');
            }

            if ($errors === []) {
                $errors = $this->validateContact();
            }

            if ($errors === []) {
                $this->load->model('communication/contact_message');

                $contact = [
                    'name'      => trim((string)($this->request->post['name'] ?? '')),
                    'email'     => trim((string)($this->request->post['email'] ?? '')),
                    'telephone' => trim((string)($this->request->post['telephone'] ?? '')),
                    'subject'   => trim((string)($this->request->post['subject'] ?? '')),
                    'message'   => trim((string)($this->request->post['message'] ?? '')),
                    'ip'        => (string)($this->request->server['REMOTE_ADDR'] ?? ''),
                ];

                $this->model_communication_contact_message->addContactMessage($contact);

                // F4-05 (ADR 0029): notificação por e-mail — nunca atrapalha o visitante
                $this->notifyOwner($contact);

                $this->setFlash($this->language->get('text_success'));

                $this->response->redirect($this->url->link('common/contact'));

                return;
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('common/contact');

        $this->chrome($data);

        $this->response->setOutput($this->load->view('common/contact', $data));
    }

    /** Honeypot: humanos não veem nem preenchem o campo 'company' */
    private function isBot(): bool {
        return trim((string)($this->request->post['company'] ?? '')) !== '';
    }

    /**
     * F4-05 (ADR 0029): envia cópia da mensagem para o dono do site via a
     * engine efetiva (mail_engine do painel — mail/smtp/capture). Falha é
     * SILENCIOSA para o visitante (mesma renúncia da ADR 0023 — Latest News):
     * o INSERT já garantiu a mensagem; o erro vai só para o log.
     * Reply-To = e-mail do visitante (responder cai direto nele), salvo quando
     * o painel define mail_reply_to fixo — esse vence.
     *
     * @param array<string, string> $contact campos já validados/sanitizados
     */
    private function notifyOwner(array $contact): void {
        $to = (string)$this->config->get('config_contact_email', '');

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $fixedReplyTo = (string)$this->config->get('mail_reply_to', '');

        try {
            $mail = \Reamur\System\Library\Mail\Factory::create($this->config, [
                'to'       => $to,
                'reply_to' => $fixedReplyTo !== '' ? $fixedReplyTo : (string)$contact['email'],
                'subject'  => sprintf($this->language->get('text_mail_subject'), (string)$contact['subject']),
                'text'     => sprintf(
                    $this->language->get('text_mail_body'),
                    (string)$contact['name'],
                    (string)$contact['email'],
                    (string)$contact['telephone'],
                    (string)$contact['message']
                ),
            ]);

            $mail->send();
        } catch (\Throwable $e) {
            $this->log->write('contact.notifyOwner: ' . $e->getMessage());
        }
    }

    /** F2-18: rate limit — true quando a janela ainda não estourou */
    private function rateLimitAllow(string $scope, int $max, int $windowSeconds): bool {
        $limiter = new \Reamur\System\Library\Security\RateLimit($this->db);

        return $limiter->allow($scope, (string)($this->request->server['REMOTE_ADDR'] ?? ''), $max, $windowSeconds);
    }

    /**
     * @return array<int, string>
     */
    private function validateContact(): array {
        $post   = $this->request->post;
        $errors = [];

        $name    = trim((string)($post['name'] ?? ''));
        $email   = trim((string)($post['email'] ?? ''));
        $tel     = trim((string)($post['telephone'] ?? ''));
        $subject = trim((string)($post['subject'] ?? ''));
        $message = trim((string)($post['message'] ?? ''));

        if ($name === '' || mb_strlen($name) > 128) {
            $errors[] = $this->language->get('error_name');
        }

        if ($email === '' || mb_strlen($email) > 96 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->language->get('error_email');
        }

        if (mb_strlen($tel) > 32) {
            $errors[] = $this->language->get('error_telephone');
        }

        if ($subject === '' || mb_strlen($subject) > 128) {
            $errors[] = $this->language->get('error_subject');
        }

        if (mb_strlen($message) < 3 || mb_strlen($message) > 5000) {
            $errors[] = $this->language->get('error_message');
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array {
        $post = $this->request->post;

        return [
            'heading_title'   => $this->language->get('heading_title'),
            'text_lead'       => $this->language->get('text_lead'),
            'text_honeypot'   => '', // nunca renderizado visivelmente
            'entry_name'      => $this->language->get('entry_name'),
            'entry_email'     => $this->language->get('entry_email'),
            'entry_telephone' => $this->language->get('entry_telephone'),
            'entry_subject'   => $this->language->get('entry_subject'),
            'entry_message'   => $this->language->get('entry_message'),
            'button_send'     => $this->language->get('button_send'),
            'button_back_home'=> $this->language->get('button_back_home'),
            'text_flash'      => $this->flash(),
            'csrf_token'      => \Reamur\System\Library\Security\Csrf::token($this->session),
            'value_name'      => (string)($post['name'] ?? ''),
            'value_email'     => (string)($post['email'] ?? ''),
            'value_telephone' => (string)($post['telephone'] ?? ''),
            'value_subject'   => (string)($post['subject'] ?? ''),
            'value_message'   => (string)($post['message'] ?? ''),
            'home'            => $this->config->get('site_url', ''),
            'error'           => '',
        ];
    }

    /** Flash da sessão (PRG) — mesmo padrão leia-uma-vez do admin */
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

    private function chrome(array &$data): void {
        $this->document->addStyle($this->config->get('site_url') . 'view/css/common.css');

        $data['body_class'] = 'rms-contact';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
