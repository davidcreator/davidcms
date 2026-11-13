<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Onde o Checkout guarda as cobranças e mexe no pedido (ADR 0097).
 *
 * Uma cobrança é uma tentativa de pagamento de um pedido. Chaves de cada
 * linha devolvida: `id`, `order_id`, `token`, `status`, `url`, `payment_id`.
 */
namespace Reamur\System\Library\Payment;

interface Ledger {
    /**
     * Cobrança mais recente do pedido, em qualquer situação; [] se não há.
     *
     * @return array<string, mixed>
     */
    public function latestForOrder(int $orderId): array;

    /**
     * Cobrança pela chave do link do cliente; [] se não há.
     *
     * @return array<string, mixed>
     */
    public function byToken(string $token): array;

    /** Grava uma cobrança nova, pendente. */
    public function add(int $orderId, string $token, float $amount, Charge $charge): void;

    /** Anota na cobrança o pagamento e a situação que o provedor confirmou. */
    public function record(int $id, string $paymentId, string $status): void;

    /** Muda a situação do pedido e escreve a linha de histórico. */
    public function moveOrder(int $orderId, int $orderStatusId, string $comment): void;
}
