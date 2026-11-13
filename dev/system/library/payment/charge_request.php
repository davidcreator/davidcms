<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * O que o Checkout pede ao meio de pagamento: cobrar um pedido (ADR 0097).
 */
namespace Reamur\System\Library\Payment;

final class ChargeRequest {
    /**
     * @param int    $orderId   número do pedido
     * @param float  $amount    valor a cobrar, maior que zero, com 2 casas
     * @param string $title     texto que o cliente vê na cobrança
     * @param string $notifyUrl endereço público que o provedor deve avisar
     */
    public function __construct(
        public readonly int $orderId,
        public readonly float $amount,
        public readonly string $title,
        public readonly string $notifyUrl,
    ) {}
}
