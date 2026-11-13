<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * O que um aviso de pagamento vale, segundo o meio que o conferiu (ADR 0097).
 *
 *   answer()     nada a fazer: o provedor recebe 200 "ok:<rótulo>" e para de reenviar
 *   retry()      não deu para conferir agora: o provedor recebe 503 e reenvia depois
 *   confirmed()  pagamento conferido: o Checkout aplica a situação ao pedido
 */
namespace Reamur\System\Library\Payment;

final class Verdict {
    public const ANSWER    = 'answer';
    public const RETRY     = 'retry';
    public const CONFIRMED = 'confirmed';

    /** Situações que mexem no pedido. Qualquer outra só fica registrada na cobrança. */
    public const APPROVED  = 'approved';
    public const REJECTED  = 'rejected';
    public const CANCELLED = 'cancelled';

    private function __construct(
        public readonly string $kind,
        public readonly string $label = '',
        public readonly string $log = '',
        public readonly int $orderId = 0,
        public readonly string $paymentId = '',
        public readonly string $status = '',
    ) {}

    /** Aviso que não muda nada (não é pagamento, assinatura errada, pagamento de outro site). */
    public static function answer(string $label, string $log = ''): self {
        return new self(self::ANSWER, self::label($label), $log);
    }

    /** Falha da instalação que tem conserto: jogar o aviso fora perderia a confirmação. */
    public static function retry(string $label, string $log): self {
        return new self(self::RETRY, self::label($label), $log);
    }

    /**
     * Pagamento conferido junto ao provedor.
     *
     * @param int    $orderId   pedido a que o pagamento pertence
     * @param string $paymentId identificador do pagamento no provedor
     * @param string $status    APPROVED, REJECTED, CANCELLED ou outra palavra do provedor
     */
    public static function confirmed(int $orderId, string $paymentId, string $status): self {
        if ($orderId <= 0 || $paymentId === '' || $status === '') {
            throw new \InvalidArgumentException('payment verdict: pedido, pagamento ou situação inválidos');
        }

        return new self(self::CONFIRMED, '', '', $orderId, $paymentId, $status);
    }

    /** O rótulo vai no corpo da resposta ao provedor: só letras minúsculas, dígitos e hífen. */
    private static function label(string $label): string {
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/D', $label) !== 1) {
            throw new \InvalidArgumentException('payment verdict: rótulo inválido');
        }

        return $label;
    }
}
