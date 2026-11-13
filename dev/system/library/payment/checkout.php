<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Regras de pagamento que valem para qualquer meio (ADR 0097, EX-03).
 *
 *   link()    gerar o link de pagamento de um pedido
 *   target()  para onde mandar o cliente que abriu o link
 *   notify()  o que fazer com um aviso do provedor e o que responder
 *
 * O meio de pagamento (Method) só fala com o provedor. Aqui ficam a chave do
 * link, o reaproveitamento, o aviso repetido, a situação do pedido e o
 * histórico. Esta classe não conhece nenhum provedor pelo nome.
 */
namespace Reamur\System\Library\Payment;

final class Checkout {
    /** Situações de pedido (rms_order_status) para onde um pagamento leva. */
    private const ORDER_APPROVED = 4;
    private const ORDER_CANCELED = 6;

    /** Cobrança nestas situações não leva mais o cliente ao provedor. */
    private const CLOSED = [Verdict::APPROVED, Verdict::REJECTED, Verdict::CANCELLED];

    /** @param (\Closure(string): void)|null $log quem escreve no log do sistema */
    public function __construct(
        private Ledger $ledger,
        private ?\Closure $log = null,
    ) {}

    /**
     * Link de pagamento do pedido. Ordem das regras (a tela mostra a primeira
     * que barrar): já pago; link pendente reaproveitado; meio pronto; valor
     * maior que zero; cobrança criada no provedor.
     *
     * @param float  $total     total do pedido, como está gravado
     * @param string $title     texto que o cliente vê na cobrança
     * @param string $notifyUrl endereço público que o provedor deve avisar
     */
    public function link(Method $method, int $orderId, float $total, string $title, string $notifyUrl): Link {
        $current = $this->ledger->latestForOrder($orderId);
        $status  = (string)($current['status'] ?? '');

        if ($current !== [] && $status === Verdict::APPROVED) {
            return new Link(Link::PAID);
        }

        // Um pedido, um link pendente: pedir de novo devolve o mesmo, sem
        // criar outra cobrança no provedor.
        if ($current !== [] && $status === 'pending' && (string)($current['url'] ?? '') !== '') {
            return new Link(Link::REUSED, (string)($current['token'] ?? ''));
        }

        $blocked = $method->blockedBy();

        if ($blocked !== '') {
            return new Link(Link::BLOCKED, '', $blocked);
        }

        $amount = round($total, 2);

        if ($amount <= 0) {
            return new Link(Link::NO_TOTAL);
        }

        $charge = $method->charge(new ChargeRequest($orderId, $amount, $title, $notifyUrl));

        // Sem identificador ou sem endereço não há o que gravar nem o que abrir.
        if (!$charge->ok || $charge->reference === '' || $charge->url === '') {
            return new Link(Link::FAILED, '', $charge->error);
        }

        // A chave do link é sorteada; nunca se deduz do número do pedido.
        $this->ledger->add($orderId, bin2hex(random_bytes(24)), $amount, $charge);

        return new Link(Link::CREATED, (string)($this->ledger->latestForOrder($orderId)['token'] ?? ''));
    }

    /**
     * Endereço do provedor para o cliente que abriu o link; '' = link que não
     * vale (chave desconhecida, cobrança encerrada, endereço que não é https).
     */
    public function target(string $token): string {
        $charge = $this->ledger->byToken($token);
        $url    = (string)($charge['url'] ?? '');

        $valid = $url !== ''
            && preg_match('/[\r\n\0]/', $url) !== 1
            && str_starts_with($url, 'https://')
            && filter_var($url, FILTER_VALIDATE_URL) !== false;

        if ($charge === [] || !$valid || in_array((string)($charge['status'] ?? ''), self::CLOSED, true)) {
            return '';
        }

        return $url;
    }

    /**
     * Aviso do provedor. Nunca lança: falha local vira resposta "ok:error"
     * (200), para o provedor não reenviar em laço.
     *
     * @param \Closure(string, string): string $history linha do histórico do pedido, a partir do pagamento e da situação
     */
    public function notify(Method $method, Notification $notification, \Closure $history): Reply {
        try {
            $verdict = $method->notification($notification);

            if ($verdict->kind !== Verdict::CONFIRMED) {
                if ($verdict->log !== '') {
                    $this->log($method, ': ' . $verdict->log);
                }

                return new Reply($verdict->kind === Verdict::RETRY, ($verdict->kind === Verdict::RETRY ? 'retry:' : 'ok:') . $verdict->label);
            }

            $charge = $this->ledger->latestForOrder($verdict->orderId);

            if ($charge === []) {
                $this->log($method, ': pedido ' . $verdict->orderId . ' (payment ' . $verdict->paymentId . ') sem lançamento local');

                return new Reply(false, 'ok:no-local-record');
            }

            // Aviso repetido: mesmo pagamento, mesma situação. Nada muda.
            if ((string)($charge['payment_id'] ?? '') === $verdict->paymentId && (string)($charge['status'] ?? '') === $verdict->status) {
                return new Reply(false, 'ok:already-processed');
            }

            $this->ledger->record((int)$charge['id'], $verdict->paymentId, $verdict->status);

            $orderStatus = match ($verdict->status) {
                Verdict::APPROVED                     => self::ORDER_APPROVED,
                Verdict::REJECTED, Verdict::CANCELLED => self::ORDER_CANCELED,
                default                               => 0, // pendente, devolvido...: só fica na cobrança
            };

            $orderId = (int)($charge['order_id'] ?? 0);

            if ($orderStatus > 0 && $orderId > 0) {
                $this->ledger->moveOrder($orderId, $orderStatus, (string)$history($verdict->paymentId, $verdict->status));
            }

            return new Reply(false, 'ok:processed', $verdict->status === Verdict::APPROVED ? $verdict->orderId : 0);
        } catch (\Throwable $e) {
            $this->log($method, ' falhou e foi isolado: ' . $e->getMessage());

            return new Reply(false, 'ok:error');
        }
    }

    private function log(Method $method, string $text): void {
        if ($this->log === null) {
            return;
        }

        try {
            ($this->log)($method->code() . ' notify' . $text);
        } catch (\Throwable) {
            // log fora do ar não pode mudar a resposta ao provedor
        }
    }
}
