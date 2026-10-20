<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * checkout/payment (CATALOG) — endpoints PÚBLICOS do checkout MP (F5-03,
 * ADR 0035):
 *  · pay|TOKEN   → link do cliente: 302 ao init_point (Checkout Pro) do
 *                  seller; token inválido → 404 da casa. Sem sessão/login.
 *  · notify      → webhook/IPN do MP. A fonte da verdade NUNCA é o POST:
 *                  o handler extrai o payment id, CONSULTA /v1/payments/{id}
 *                  com o token do seller e só então reflete o status
 *                  (idempotente por payment_id+status — ADR 0035 §4).
 *                  Sequência de defesa quando mp_webhook_secret estiver
 *                  configurado: valida x-signature ts/v1 (HMAC sha256 do
 *                  manifest "id:<id>;request-id:<rid>;ts:<ts>;") ANTES de
 *                  chamar a API do MP (ADR 0035 §6).
 */
namespace Reamur\Catalog\Controller\Checkout;

class Payment extends \Reamur\System\Engine\Controller {

    /** Cliente abre o link: valida token e manda ao Checkout Pro do seller */
    public function pay(string $token = ''): void {
        $this->load->language('checkout/payment');
        $this->load->model('checkout/mp_payment');

        $payment = $this->model_checkout_mp_payment->getPaymentByToken((string)$token);

        $init_point = (string)($payment['init_point'] ?? '');

        // Estados em que o link não deve mais levar ao checkout
        if (!$payment || $init_point === '' || in_array((string)$payment['status'], ['approved', 'rejected', 'cancelled'], true)) {
            $this->response->redirect($this->url->link('error/not_found'));

            return;
        }

        $this->response->redirect($init_point);
    }

    /** Webhook (v2) / IPN (clássico) do MP — resposta SEMPRE 200 c/ rótulo */
    public function notify(): void {
        $this->response->addHeader('Content-Type: text/plain; charset=utf-8');

        try {
            $payment_id = $this->extractPaymentId();

            if ($payment_id <= 0) {
                $this->finish('ignored'); // merchant_order / lixo — 200 p/ o MP parar de reenviar
                return;
            }

            $this->load->language('checkout/payment');
            $this->load->model('checkout/mp_payment');

            $settings  = $this->catalogSettings();
            $mp        = $this->mpFromSettings($settings);
            $raw       = file_get_contents('php://input') ?: '';
            $body      = (json_decode($raw, true) ?: []);

            // §6 — validação de assinatura quando segredo configurado
            if (!$this->signatureOk((string)($settings['mp_webhook_secret'] ?? ''), $payment_id)) {
                $this->log->write("mp notify: assinatura rejeitada (payment $payment_id)");

                $this->finish('signature-rejected');
                return;
            }

            // Descobre o seller dono do pagamento: user_id do payload (v2) →
            // direto; sem ele (IPN clássico), itera os sellers ativos e para no
            // primeiro em cuja /v1/payments o id existe (ADR 0035 §5).
            $seller  = [];
            $payment = [];
            $mp_user_id = (int)($body['user_id'] ?? 0);

            if ($mp_user_id > 0) {
                $seller = $this->model_checkout_mp_payment->getSellerByMpUserId($mp_user_id);
            }

            if ($seller) {
                $payment = $this->confirmedPayment($mp, $payment_id, (string)$seller['access_token']);
            }

            if (!$payment) {
                foreach ($this->model_checkout_mp_payment->getActiveSellers() as $candidate) {
                    if ($seller && (int)$candidate['mp_seller_id'] === (int)$seller['mp_seller_id']) {
                        continue;
                    }

                    $payment = $this->confirmedPayment($mp, $payment_id, (string)$candidate['access_token']);

                    if ($payment) {
                        $seller = $candidate;
                        break;
                    }
                }
            }

            if (!$payment) {
                $this->log->write("mp notify: payment $payment_id não confirmado em nenhum seller ativo");

                $this->finish('not-ours');
                return;
            }

            // Liga ao lançamento local: external_reference = order_id (paylink)
            $order_id = (int)($payment['external_reference'] ?? 0);

            if ($order_id <= 0) {
                $this->log->write("mp notify: payment $payment_id sem external_reference");

                $this->finish('no-reference');
                return;
            }

            $record = $this->model_checkout_mp_payment->getPaymentByOrder($order_id);

            if (!$record) {
                $this->log->write("mp notify: pedido {$order_id} (payment $payment_id) sem lançamento local");

                $this->finish('no-local-record');
                return;
            }

            $status = (string)($payment['status'] ?? 'pending');

            $changed = $this->model_checkout_mp_payment->reflectPaymentStatus(
                (int)$record['mp_payment_id'],
                $payment_id,
                $status,
                sprintf($this->language->get('text_history_mp'), $payment_id, $status)
            );

            if ($changed && $status === 'approved') {
                $this->dispatchOrderWebhook('order_history_edit', $order_id);
            }

            $this->finish($changed ? 'processed' : 'already-processed');
        } catch (\Throwable $e) {
            $this->log->write('mp notify falhou e foi isolado: ' . $e->getMessage());

            $this->finish('error'); // ainda 200: falha local não deve re-entregar em loop
        }
    }

    // ── internos ──────────────────────────────────────────────────────────

    /** Webhook v2 (?type=payment&data.id=) e IPN clássico (?topic=payment&id=/POST) */
    private function extractPaymentId(): int {
        $sources = [
            $_GET['data.id']   ?? null,
            $_GET['data_id']   ?? null, // PHP converte o ponto de ?data.id=
            $_GET['id']        ?? null,
            $_POST['id']       ?? null, // IPN clássico = POST application/x-www-form-urlencoded
        ];

        $raw  = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);

        if (is_array($body) && isset($body['data']['id'])) {
            $sources[] = $body['data']['id'];
        }

        $type = (string)(($_GET['type'] ?? $_GET['topic'] ?? '') ?: ($body['type'] ?? $body['topic'] ?? ''));

        if (!in_array($type, ['payment', ''], true)) {
            return 0; // merchant_order & afins: fora do nosso recorte F5-03
        }

        foreach ($sources as $s) {
            $id = (int)$s;

            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /** Só aceita como verdade 2xx com recurso de pagamento bem formado */
    private function confirmedPayment(\Reamur\System\Library\MercadopagoMarketplace $mp, int $payment_id, string $access_token): array {
        $p = $mp->getPayment($payment_id, $access_token);

        if ((int)($p['_http_status'] ?? 0) < 200 || (int)($p['_http_status'] ?? 0) >= 300) {
            return [];
        }

        if (empty($p['id']) || empty($p['status'])) {
            return [];
        }

        return $p;
    }

    /** x-signature: ts=…,v1=… (hex) — valida só quando o segredo ESTIVER configurado */
    private function signatureOk(string $secret, int $payment_id): bool {
        if ($secret === '') {
            return true; // validação por consulta à API permanece (§4)
        }

        $header = (string)($_SERVER['HTTP_X_SIGNATURE'] ?? '');
        $rid    = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
        $ts     = '';
        $v1     = '';

        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($k === 'ts') {
                $ts = $v;
            } elseif ($k === 'v1') {
                $v1 = $v;
            }
        }

        if ($ts === '' || $v1 === '') {
            return false;
        }

        $manifest = 'id:' . $payment_id . ';request-id:' . $rid . ';ts:' . $ts . ';';

        return hash_equals(hash_hmac('sha256', $manifest, $secret), $v1);
    }

    private function mpFromSettings(array $settings): \Reamur\System\Library\MercadopagoMarketplace {
        return new \Reamur\System\Library\MercadopagoMarketplace([
            'app_id'        => (string)($settings['mp_app_id'] ?? ''),
            'client_secret' => (string)($settings['mp_client_secret'] ?? ''),
            'redirect_uri'  => (string)($settings['mp_redirect_uri'] ?? ''),
            'token_url'     => (string)($settings['mp_token_url'] ?? ''),
            'users_me_url'  => (string)($settings['mp_users_me_url'] ?? ''),
            'api_url'       => (string)($settings['mp_api_url'] ?? ''),
        ]);
    }

    /** rms_setting inteira, com a MESMA tolerância a cache do model admin */
    private function catalogSettings(): array {
        $query = $this->db->query("SELECT `key`, `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'config'");

        $map = [];

        foreach ((array)$query->rows as $row) {
            $map[(string)$row['key']] = (string)$row['value'];
        }

        return $map;
    }

    /** Reusa o transporte F5-01: pedido aprovado = order_history_edit nos hooks */
    private function dispatchOrderWebhook(string $event, int $order_id): void {
        try {
            $this->load->model('setting/webhook');

            $hooks = $this->model_setting_webhook->getHooksForEvent($event);

            if (!$hooks) {
                return;
            }

            $this->load->model('checkout/quote');

            $order = $this->model_checkout_quote->getQuoteForNotification($order_id);

            $payload_domain = [
                'order_id'  => $order_id,
                'firstname' => (string)($order['firstname'] ?? ''),
                'lastname'  => (string)($order['lastname'] ?? ''),
                'email'     => (string)($order['email'] ?? ''),
                'telephone' => (string)($order['telephone'] ?? ''),
                'comment'   => (string)($order['comment'] ?? ''),
                'total'     => (float)($order['total'] ?? 0),
                'status_id' => (int)($order['order_status_id'] ?? 0),
                'items'     => (array)($order['items'] ?? []),
            ];

            $dispatcher = new \Reamur\System\Library\Webhook\Dispatcher($this->log);

            $results = $dispatcher->dispatch($event, $payload_domain, $hooks);

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
            $this->log->write("webhook $event (pedido #$order_id) falhou e foi isolado: " . $e->getMessage());
        }
    }

    /** O MP reenvia a notificação se não receber 2xx: a resposta é SEMPRE 200 */
    private function finish(string $label): void {
        $this->response->setOutput('ok:' . $label);
    }
}
