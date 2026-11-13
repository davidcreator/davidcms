<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * checkout/payment (CATALOG) — endereços PÚBLICOS de pagamento, sem sessão
 * nem login (ADR 0035; contrato de meio de pagamento: ADR 0097):
 *  · pay|CHAVE  → link do cliente: 302 para o endereço de pagamento do
 *                 provedor; chave que não vale → página "não encontrado".
 *  · notify     → aviso do provedor. Resposta 200 com rótulo; 503 só quando
 *                 um segredo gravado não abre (ADR 0094).
 *
 * As regras estão em system/library/payment/: Checkout (o que vale para
 * qualquer meio) e Mercadopago (o que é do Mercado Pago). Este controller só
 * junta as peças, responde HTTP e entrega o evento de pedido pago.
 */
namespace Reamur\Catalog\Controller\Checkout;

use Reamur\System\Library\Payment\Checkout;
use Reamur\System\Library\Payment\DbLedger;
use Reamur\System\Library\Payment\Mercadopago;
use Reamur\System\Library\Payment\Method;
use Reamur\System\Library\Payment\Notification;

class Payment extends \Reamur\System\Engine\Controller {

    public function index(): void {
        (new \Reamur\System\Engine\Action('error/not_found'))->execute($this->registry);
    }

    /** Cliente abre o link: chave válida leva ao pagamento no provedor */
    public function pay(string $token = ''): void {
        $target = $this->checkout()->target((string)$token);

        $this->response->redirect($target !== '' ? $target : $this->url->link('error/not_found'));
    }

    /** Aviso do Mercado Pago (formato novo e antigo) */
    public function notify(): void {
        $this->response->addHeader('Content-Type: text/plain; charset=utf-8');

        try {
            $this->load->language('checkout/payment');
            $this->load->model('checkout/mp_payment');

            $notification = new Notification(
                (array)$this->request->get,
                (array)$this->request->post,
                (array)$this->request->server,
                file_get_contents('php://input') ?: ''
            );

            $reply = $this->checkout()->notify(
                $this->method(),
                $notification,
                fn (string $payment, string $status): string => sprintf((string)$this->language->get('text_history_mp'), $payment, $status)
            );
        } catch (\Throwable $e) {
            $this->log->write('mp notify falhou e foi isolado: ' . $e->getMessage());

            $this->response->setOutput('ok:error'); // ainda 200: falha local não deve re-entregar em loop

            return;
        }

        if ($reply->retry) {
            // Única exceção ao "sempre 200" (ADR 0094): com 503 o provedor
            // reenvia o aviso mais tarde, quando a chave estiver corrigida.
            $this->response->addHeader('HTTP/1.1 503 Service Unavailable');
            $this->response->addHeader('Retry-After: 3600');
        }

        if ($reply->paidOrder > 0) {
            $order_id = $reply->paidOrder;

            $this->dispatchWebhookEvent('order_history_edit', function () use ($order_id): array {
                $this->load->model('checkout/quote');
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
                    'items'     => (array)($order['items'] ?? []),
                ];
            }, 'pedido #' . $order_id);
        }

        $this->response->setOutput($reply->body);
    }

    /**
     * Conferência do cabeçalho x-signature do Mercado Pago. Mantido aqui por
     * compatibilidade; a regra mora em Mercadopago::signatureValid().
     */
    public static function verifyWebhookSignature(string $secret, int $paymentId, string $signatureHeader, string $requestId): bool {
        return Mercadopago::signatureValid($secret, $paymentId, $signatureHeader, $requestId);
    }

    // ── internos ──────────────────────────────────────────────────────────

    private function checkout(): Checkout {
        return new Checkout(
            new DbLedger($this->db, DB_PREFIX),
            function (string $line): void {
                $this->log->write($line);
            }
        );
    }

    /** Meio de pagamento deste endereço. Hoje há um só: Mercado Pago. */
    private function method(): Method {
        return new Mercadopago(
            $this->secrets(),
            fn (): array => $this->catalogSettings(),
            fn (): array => (array)$this->model_checkout_mp_payment->getActiveSellers(),
            fn (int $mpUserId): array => (array)$this->model_checkout_mp_payment->getSellerByMpUserId($mpUserId)
        );
    }

    /**
     * rms_setting direto do banco: o catálogo só carrega na memória as chaves
     * da lista de startup/setting, e as do Mercado Pago não estão nela.
     *
     * @return array<string, string>
     */
    private function catalogSettings(): array {
        $query = $this->db->query("SELECT `key`, `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'config'");

        $map = [];

        if ($query instanceof \stdClass && !empty($query->rows)) {
            foreach ($query->rows as $row) {
                $row = (array)$row;
                $map[(string)$row['key']] = (string)$row['value'];
            }
        }

        return $map;
    }
}
