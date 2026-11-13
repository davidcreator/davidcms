<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use Reamur\System\Library\Payment\Charge;
use Reamur\System\Library\Payment\ChargeRequest;
use Reamur\System\Library\Payment\Method;
use Reamur\System\Library\Payment\Notification;
use Reamur\System\Library\Payment\Verdict;

/**
 * Meio de pagamento ficticio ("Caixa Exemplo"), de proposito diferente do
 * Mercado Pago: nao tem vendedores, cobra em dolar, nao tem comissao, o
 * identificador do pagamento e texto ("pay_..."), o aviso chega em JSON com
 * assinatura num cabecalho proprio e traz palavras proprias de situacao.
 * Serve para provar que o contrato nao depende do Mercado Pago.
 */
final class FictitiousMethod implements Method {
    public const SECRET = 'segredo-do-caixa-exemplo';

    public string $blocked = '';
    public ?Charge $nextCharge = null;
    public bool $explode = false;

    /** @var list<ChargeRequest> */
    public array $requests = [];

    /** O que o "provedor" sabe: pagamento => [pedido, situacao dele]. */
    public array $provider = [
        'pay_ok'       => [41, 'PAID'],
        'pay_declined' => [42, 'DECLINED'],
        'pay_void'     => [43, 'VOIDED'],
        'pay_review'   => [44, 'UNDER_REVIEW'],
        'pay_lost'     => [999, 'PAID'],
    ];

    public function code(): string {
        return 'caixa';
    }

    public function blockedBy(): string {
        return $this->blocked;
    }

    public function charge(ChargeRequest $request): Charge {
        $this->requests[] = $request;

        return $this->nextCharge ?? Charge::created('ch_' . $request->orderId, 'https://caixa.example/pagar/ch_' . $request->orderId, 0.0, 0, 'USD');
    }

    public function notification(Notification $notification): Verdict {
        if ($this->explode) {
            throw new \RuntimeException('provedor fora do ar');
        }

        $event = json_decode($notification->body, true);

        if (!is_array($event) || ($event['kind'] ?? '') !== 'payment.updated') {
            return Verdict::answer('ignored');
        }

        if (!hash_equals(hash_hmac('sha256', $notification->body, self::SECRET), $notification->header('x-caixa-signature'))) {
            return Verdict::answer('signature-rejected', 'assinatura rejeitada');
        }

        $payment = (string)($event['payment'] ?? '');

        if ($payment === 'pay_busy') {
            return Verdict::retry('provider-busy', 'provedor ocupado');
        }

        if (!isset($this->provider[$payment])) {
            return Verdict::answer('not-ours', "pagamento $payment desconhecido");
        }

        [$order, $theirs] = $this->provider[$payment];

        // Cada provedor tem as suas palavras; o contrato pede as do nucleo.
        $status = match ($theirs) {
            'PAID'     => Verdict::APPROVED,
            'DECLINED' => Verdict::REJECTED,
            'VOIDED'   => Verdict::CANCELLED,
            default    => strtolower($theirs),
        };

        return Verdict::confirmed($order, $payment, $status);
    }

    /** Aviso bem formado e assinado, como o "provedor" mandaria. */
    public static function signed(string $payment, string $kind = 'payment.updated'): Notification {
        $body = (string)json_encode(['kind' => $kind, 'payment' => $payment]);

        return new Notification([], [], ['HTTP_X_CAIXA_SIGNATURE' => hash_hmac('sha256', $body, self::SECRET)], $body);
    }
}
