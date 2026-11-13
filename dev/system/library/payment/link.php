<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Resultado de "gere o link de pagamento deste pedido" (ADR 0097).
 */
namespace Reamur\System\Library\Payment;

final class Link {
    public const CREATED  = 'created';   // cobrança nova; token = chave do link
    public const REUSED   = 'reused';    // já havia link pendente; token = a mesma chave
    public const PAID     = 'paid';      // pedido já pago: não se gera outro
    public const BLOCKED  = 'blocked';   // o meio não está pronto; detail = motivo do meio
    public const NO_TOTAL = 'no_total';  // pedido sem valor
    public const FAILED   = 'failed';    // o provedor não criou a cobrança; detail = texto do erro

    public function __construct(
        public readonly string $state,
        public readonly string $token = '',
        public readonly string $detail = '',
    ) {}
}
