<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use Reamur\System\Library\Payment\Charge;
use Reamur\System\Library\Payment\Ledger;

/**
 * Cobrancas em memoria, para testar as regras do Checkout sem banco.
 * Guarda tambem o que foi pedido, na ordem, em $calls.
 */
final class MemoryLedger implements Ledger {
    /** @var list<array<string, mixed>> */
    public array $charges = [];

    /** @var list<array{order: int, status: int, comment: string}> */
    public array $orders = [];

    /** @var list<string> */
    public array $calls = [];

    public bool $failOnWrite = false;

    public function latestForOrder(int $orderId): array {
        $this->calls[] = 'latest ' . $orderId;

        foreach (array_reverse($this->charges) as $charge) {
            if ($charge['order_id'] === $orderId) {
                return $charge;
            }
        }

        return [];
    }

    public function byToken(string $token): array {
        $this->calls[] = 'token ' . $token;

        foreach ($this->charges as $charge) {
            if ($charge['token'] === $token) {
                return $charge;
            }
        }

        return [];
    }

    public function add(int $orderId, string $token, float $amount, Charge $charge): void {
        $this->calls[] = 'add ' . $orderId;

        if ($this->failOnWrite) {
            throw new \RuntimeException('banco fora do ar');
        }

        $this->charges[] = [
            'id' => count($this->charges) + 1, 'order_id' => $orderId, 'token' => $token, 'status' => 'pending',
            'url' => $charge->url, 'payment_id' => '0',
            'amount' => $amount, 'reference' => $charge->reference, 'fee' => $charge->fee,
            'account' => $charge->account, 'currency' => $charge->currency,
        ];
    }

    public function record(int $id, string $paymentId, string $status): void {
        $this->calls[] = 'record ' . $id . ' ' . $paymentId . ' ' . $status;

        if ($this->failOnWrite) {
            throw new \RuntimeException('banco fora do ar');
        }

        foreach ($this->charges as $i => $charge) {
            if ($charge['id'] === $id) {
                $this->charges[$i]['payment_id'] = $paymentId;
                $this->charges[$i]['status']     = $status;
            }
        }
    }

    public function moveOrder(int $orderId, int $orderStatusId, string $comment): void {
        $this->calls[] = 'order ' . $orderId . ' ' . $orderStatusId;
        $this->orders[] = ['order' => $orderId, 'status' => $orderStatusId, 'comment' => $comment];
    }

    /**
     * Poe uma cobranca pronta na memoria.
     *
     * @param array<string, mixed> $fields
     */
    public function seed(array $fields): void {
        $this->charges[] = $fields + [
            'id' => count($this->charges) + 1, 'order_id' => 1, 'token' => str_repeat('a', 48), 'status' => 'pending',
            'url' => 'https://pay.example/c/1', 'payment_id' => '0',
        ];
    }
}
