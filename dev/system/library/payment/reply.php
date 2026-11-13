<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Resposta a dar ao provedor que mandou um aviso de pagamento (ADR 0097).
 */
namespace Reamur\System\Library\Payment;

final class Reply {
    /**
     * @param bool   $retry     true = responder 503 com Retry-After (o provedor reenvia)
     * @param string $body      corpo em texto puro ("ok:processed", "retry:token-locked")
     * @param int    $paidOrder pedido que ACABOU de passar a pago por este aviso; 0 nos demais casos
     */
    public function __construct(
        public readonly bool $retry,
        public readonly string $body,
        public readonly int $paidOrder = 0,
    ) {}
}
