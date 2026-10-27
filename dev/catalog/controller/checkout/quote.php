<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * checkout/quote (CATALOG) — pedido de orçamento público (F2-17): cliente
 * escolhe serviços (taxonomy estática do Dicionário/service keys) + briefing
 * e grava rms_order de valor 0.0000 (orçamento — semântica do schema):
 * itens com price/total 0.0000 aceitos server-side, histórico inicial.
 * Anti-spam: honeypot 'company'; POST jamais carrega preço do cliente.
 */
namespace Reamur\Catalog\Controller\Checkout;

class Quote extends \Reamur\System\Engine\Controller {

    /** Catálogo de serviços oferecidos (chaves → lang entries). Estático por decisão de produto. */
    private const SERVICES = [
        'gd_brand', 'gd_ui', 'gd_illustration', 'gd_social', 'gd_installations',
        'wd_landing', 'wd_cms', 'wd_ecommerce', 'wd_webapp', 'wd_institutional',
        'seo_keyword', 'seo_onpage', 'seo_content', 'seo_linkbuilding', 'seo_audit',
    ];

    /** GET: formulário · POST: valida e grava orçamento; sucesso → flash + redirect */
    public function index(): void {
        $this->load->language('checkout/quote');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->setDescription($this->language->get('text_lead'));

        $data = $this->formData();

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            if ($this->isBot()) {
                $this->setFlash($this->language->get('text_success'));

                $this->response->redirect($this->url->link('checkout/quote'));

                return;
            }

            // F2-18 (ADR 0018): CSRF + rate limit por IP antes de validar/gravar.
            $errors   = [];
            $products = [];

            if (!\Reamur\System\Library\Security\Csrf::check($this->session, $this->request->post['csrf_token'] ?? '')) {
                $errors[] = $this->language->get('error_csrf');
            }

            if ($errors === [] && !$this->rateLimitAllow('quote', 5, 600)) {
                $errors[] = $this->language->get('error_rate_limit');
            }

            if ($errors === []) {
                [$errors, $products] = $this->validateQuote();
            }

            if ($errors === []) {
                $this->load->model('checkout/quote');

                $order_id = $this->model_checkout_quote->addQuote(
                    [
                        'firstname' => trim((string)($this->request->post['firstname'] ?? '')),
                        'lastname'  => trim((string)($this->request->post['lastname'] ?? '')),
                        'email'     => trim((string)($this->request->post['email'] ?? '')),
                        'telephone' => trim((string)($this->request->post['telephone'] ?? '')),
                        'comment'   => trim((string)($this->request->post['comment'] ?? '')),
                        'ip'        => (string)($this->request->server['REMOTE_ADDR'] ?? ''),
                    ],
                    $products
                );

                if ($order_id) {
                    $this->dispatchOrderWebhook('order_add', (int)$order_id);

                    $this->setFlash(sprintf($this->language->get('text_success'), $order_id));

                    $this->response->redirect($this->url->link('checkout/quote'));

                    return;
                }
            }

            $data['error'] = implode("\n", $errors);
        }

        $data['action'] = $this->url->link('checkout/quote');

        $this->chrome($data);

        $data['cms_page_content'] = (string)$this->load->controller('cms/slot.render', 'quote');
        $this->response->setOutput($this->load->view('checkout/quote', $data));
    }

    private function isBot(): bool {
        return trim((string)($this->request->post['company'] ?? '')) !== '';
    }

    /**
     * F5-01 (ADR 0033): despacha o evento de pedido aos webhooks ativos.
     * GUARDA-CHUVA — qualquer falha (endpoint fora, tabela ausente,
     * erro de rede) é registrada em log e NUNCA afeta a resposta do
     * orçamento: a operação de negócio tem prioridade sobre a notificação.
     */
    private function dispatchOrderWebhook(string $event, int $order_id): void {
        try {
            $this->load->model('setting/webhook');

            $hooks = $this->model_setting_webhook->getHooksForEvent($event);

            if (!$hooks) {
                return;
            }

            $this->load->model('checkout/quote');

            $dispatcher = new \Reamur\System\Library\Webhook\Dispatcher($this->log);

            $results = $dispatcher->dispatch($event, $this->webhookOrderPayload($order_id), $hooks);

            foreach ($results as $r) {
                $this->model_setting_webhook->addLog(
                    (int)$r['webhook_id'],
                    (string)$r['delivery'],
                    (string)$r['payload'],
                    (int)$r['http_code'],
                    (float)$r['duration'],
                    (int)$r['attempt']
                );
            }
        } catch (\Throwable $e) {
            $this->log->write("webhook {$event} (pedido #{$order_id}) falhou e foi isolado: " . $e->getMessage());
        }
    }

    /**
     * Payload de domínio do pedido para o envelope do Dispatcher.
     * Contém PII (nome/email/telefone) — o CRUD de endpoints só aceita
     * HTTPS (ADR 0016), o que cobre o transporte.
     *
     * @return array<string, mixed>
     */
    private function webhookOrderPayload(int $order_id): array {
        $order = $this->model_checkout_quote->getQuoteForNotification($order_id);

        return [
            'order_id'  => $order_id,
            'firstname' => (string)($order['firstname'] ?? ''),
            'lastname'  => (string)($order['lastname'] ?? ''),
            'email'     => (string)($order['email'] ?? ''),
            'telephone' => (string)($order['telephone'] ?? ''),
            'comment'   => (string)($order['comment'] ?? ''),
            'total'     => (float)($order['total'] ?? 0),
            'status_id' => (int)($order['order_status_id'] ?? 0),
            'items'     => $order['items'] ?? [],
        ];
    }

    /** F2-18: rate limit — true quando a janela ainda não estourou */
    private function rateLimitAllow(string $scope, int $max, int $windowSeconds): bool {
        $limiter = new \Reamur\System\Library\Security\RateLimit($this->db);

        return $limiter->allow($scope, (string)($this->request->server['REMOTE_ADDR'] ?? ''), $max, $windowSeconds);
    }

    /**
     * Valida e normaliza: serviços vêm da whitelist (chaves POSTadas
     * são apenas referências ao catálogo estático — nunca texto livre ).
     *
     * @return array{0: array<int, string>, 1: array<int, array{name: string, quantity: int}>}
     */
    private function validateQuote(): array {
        $post     = $this->request->post;
        $errors   = [];
        $products = [];

        $firstname = trim((string)($post['firstname'] ?? ''));
        $lastname  = trim((string)($post['lastname'] ?? ''));
        $email     = trim((string)($post['email'] ?? ''));
        $tel       = trim((string)($post['telephone'] ?? ''));
        $comment   = trim((string)($post['comment'] ?? ''));
        $picked    = (array)($post['services'] ?? []);

        if ($firstname === '' || mb_strlen($firstname) > 32) {
            $errors[] = $this->language->get('error_firstname');
        }

        if (mb_strlen($lastname) > 32) {
            $errors[] = $this->language->get('error_lastname');
        }

        if ($email === '' || mb_strlen($email) > 96 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->language->get('error_email');
        }

        if (mb_strlen($tel) > 32) {
            $errors[] = $this->language->get('error_telephone');
        }

        if (mb_strlen($comment) > 5000) {
            $errors[] = $this->language->get('error_comment');
        }

        foreach ($picked as $key) {
            $key = (string)$key;

            if (in_array($key, self::SERVICES, true)) {
                $products[] = ['name' => $this->language->get('service_' . $key), 'quantity' => 1];
            }
        }

        if ($products === []) {
            $errors[] = $this->language->get('error_services');
        }

        return [$errors, array_values($products)];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array {
        $post = $this->request->post;
        $picked = (array)($post['services'] ?? []);

        $services = [];

        foreach (self::SERVICES as $key) {
            $services[] = [
                'key'     => $key,
                'label'   => $this->language->get('service_' . $key),
                'checked' => in_array($key, $picked, true),
            ];
        }

        return [
            'heading_title'     => $this->language->get('heading_title'),
            'text_lead'         => $this->language->get('text_lead'),
            'text_services'     => $this->language->get('text_services'),
            'text_briefing'     => $this->language->get('text_briefing'),
            'entry_firstname'   => $this->language->get('entry_firstname'),
            'entry_lastname'    => $this->language->get('entry_lastname'),
            'entry_email'       => $this->language->get('entry_email'),
            'entry_telephone'   => $this->language->get('entry_telephone'),
            'entry_services'    => $this->language->get('entry_services'),
            'entry_comment'     => $this->language->get('entry_comment'),
            'button_send'       => $this->language->get('button_send'),
            'button_back_home'  => $this->language->get('button_back_home'),
            'text_flash'        => $this->flash(),
            'csrf_token'        => \Reamur\System\Library\Security\Csrf::token($this->session),
            'services'          => $services,
            'value_firstname'   => (string)($post['firstname'] ?? ''),
            'value_lastname'    => (string)($post['lastname'] ?? ''),
            'value_email'       => (string)($post['email'] ?? ''),
            'value_telephone'   => (string)($post['telephone'] ?? ''),
            'value_comment'     => (string)($post['comment'] ?? ''),
            'home'              => $this->config->get('site_url', ''),
            'error'             => '',
        ];
    }

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

        $data['body_class'] = 'rms-quote';
        $data['header']     = $this->load->controller('common/header', $data);
        $data['footer']     = $this->load->controller('common/footer', $data);
    }
}
