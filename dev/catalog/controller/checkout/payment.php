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
use Reamur\System\Library\Payment\DbGatewayAccountStore;
use Reamur\System\Library\Payment\DbGatewayLedger;
use Reamur\System\Library\Payment\DbLedger;
use Reamur\System\Library\Payment\GatewayCredentialVault;
use Reamur\System\Library\Payment\GatewayEventApplier;
use Reamur\System\Library\Payment\InstalledGatewayAdapters;
use Reamur\System\Library\Payment\LegacyWebhookForwarder;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\GatewayWebhookService;
use Reamur\System\Library\Payment\Mercadopago;
use Reamur\System\Library\Payment\Method;
use Reamur\System\Library\Payment\Notification;
use Reamur\System\Library\Payment\OrderFlowLedger;
use Reamur\System\Library\Payment\OrderGatewayLedger;

/**
 * ADR 0119: o `notify()` continua no contrato da ADR 0097 (é o aviso do
 * Mercado Pago pelo formato antigo, um só meio), mas a leitura das cobranças
 * passa a enxergar o ledger novo primeiro — o aviso de um pedido criado pelo
 * caminho novo não pode ser descartado como "sem lançamento local".
 *
 * O link de pedido novo não nasce aqui: ver `OrderPaymentLink`.
 */
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

            // ADR 0122 (F2): este endereco continua sendo o que o Mercado Pago
            // tem configurado. Quando existe conta de gateway do provedor, o
            // aviso entra pelo nucleo novo (mesma verificacao de assinatura,
            // mesmo GatewayEventApplier); sem conta, cai no caminho legado
            // abaixo, que continua cobrindo o checkout de vendedor.
            $forwarded = $this->forwardToGatewayCore($notification);

            if ($forwarded !== null) {
                $this->respondForwarded($forwarded['result']);

                return;
            }

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
     * ADR 0122 (F2): entrega o aviso ao nucleo novo quando ha conta de gateway.
     * Devolve null quando nao ha nada que o nucleo possa fazer com ele — nesse
     * caso o caminho legado responde, para nao mudar o contrato HTTP.
     */
    /** @return array{account: GatewayAccount, result: GatewayWebhookResult}|null */
    private function forwardToGatewayCore(Notification $notification): ?array {
        try {
            if (!$this->gatewaySchemaReady()) {
                return null;
            }

            $registry = InstalledGatewayAdapters::registry();
            $accounts = new DbGatewayAccountStore(
                $this->db,
                DB_PREFIX,
                $registry,
                new GatewayCredentialVault($this->secrets())
            );
            $ledger = new DbGatewayLedger($this->db, DB_PREFIX);
            $forwarder = new LegacyWebhookForwarder(
                $accounts,
                $ledger,
                new GatewayWebhookService($registry, $ledger)
            );

            $forwarded = $forwarder->forward($notification);

            if ($forwarded === null) {
                return null;
            }

            // Cobranca interna do JOW sincronizada pelo codigo do provedor
            // verificado, como no webhook novo.
            $account = $forwarded['account'] ?? null;
            $result = $forwarded['result'] ?? null;

            if (!$account instanceof GatewayAccount || !$result instanceof GatewayWebhookResult) {
                return null;
            }

            if ($result->kind === GatewayWebhookResult::VERIFIED) {
                $record = $ledger->byProviderReference($account->gatewayId, $result->providerReference);

                if ($record !== null && $record->sourceType === 'jow_charge') {
                    $this->load->model('checkout/gateway_payment');
                    $this->model_checkout_gateway_payment->syncJowChargeStatus(
                        $record->sourceId,
                        $record->status,
                        $record->providerCode
                    );
                }
            }

            return $forwarded;
        } catch (\Throwable $e) {
            $this->log->write('mp notify (nucleo novo) falhou e foi isolado: ' . $e->getMessage());

            return null;
        }
    }

    private function respondForwarded(GatewayWebhookResult $result): void {
        if ($result->kind === GatewayWebhookResult::RETRY) {
            $this->response->addHeader('HTTP/1.1 503 Service Unavailable');
            $this->response->addHeader('Retry-After: 60');
            $this->response->setOutput('retry');

            return;
        }

        $this->response->setOutput('ok');
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
        $legacy = new DbLedger($this->db, DB_PREFIX);
        $gateway = new DbGatewayLedger($this->db, DB_PREFIX);

        // Aplicador só existe quando a migração do núcleo novo foi aplicada:
        // sem as tabelas, `record()` de uma linha de gateway precisa falhar
        // alto em vez de escrever pela metade.
        $applier = $this->gatewaySchemaReady() ? new GatewayEventApplier($gateway) : null;

        return new Checkout(
            new OrderFlowLedger(new OrderGatewayLedger($gateway, $legacy), $legacy, $applier, $gateway),
            function (string $line): void {
                $this->log->write($line);
            }
        );
    }

    /** As três tabelas do núcleo novo, sem cache: a migração é aplicada à mão. */
    private function gatewaySchemaReady(): bool {
        try {
            $this->load->model('checkout/gateway_payment');

            return $this->model_checkout_gateway_payment->gatewaySchemaReady();
        } catch (\Throwable) {
            return false;
        }
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
