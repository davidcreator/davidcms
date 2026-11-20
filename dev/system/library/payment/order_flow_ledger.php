<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * O `Ledger` da ADR 0097 atendido por dois armazenamentos (ADR 0119).
 *
 * Leitura  → ledger novo primeiro, legado depois (link emitido antes do deploy
 *            precisa continuar abrindo o cliente).
 * Escrita  → um dono por pagamento:
 *              · tentativa de gateway (`provider_payment_id` presente)
 *                    → rms_payment_transaction, pelo `GatewayEventApplier`
 *              · pagamento manual (pix/card/boleto, sem provedor)
 *                    → rms_mp_payment, pelo `DbLedger` legado
 *
 * A invariante da ADR 0118 — nada escreve nos dois ledgers no mesmo caminho —
 * é garantida aqui: `record()` escolhe **um** destino por chamada.
 */
namespace Reamur\System\Library\Payment;

final class OrderFlowLedger implements Ledger {
    /**
     * @param GatewayEventApplier|null $applier     aplicador único de evento verificado;
     *                                              null quando o schema novo ainda não foi aplicado
     * @param GatewayAddressableLedger|null $addressable ledger novo que sabe buscar pelo
     *                                              `transaction_id` (ADR 0119); null desliga a
     *                                              escrita de linha de gateway, que passa a
     *                                              falhar alto em vez de perder pagamento
     */
    public function __construct(
        private readonly OrderGatewayLedger $ledgers,
        private readonly Ledger $legacy,
        private readonly ?GatewayEventApplier $applier = null,
        private readonly ?GatewayAddressableLedger $addressable = null,
    ) {}

    public function latestForOrder(int $orderId): array {
        return $this->ledgers->latestForOrder($orderId);
    }

    public function byToken(string $token): array {
        return $this->ledgers->byToken($token);
    }

    /**
     * Grava a cobrança nova. Chegar aqui significa que o provedor **já criou** a
     * cobrança: se não houver onde gravar, falhar é melhor do que fingir
     * sucesso — o cliente ficaria sem link e o pedido sem registro.
     */
    public function add(int $orderId, string $token, float $amount, Charge $charge): void {
        if ($this->ledgers->gateway() instanceof GatewayLedger) {
            throw new \RuntimeException(
                'order flow ledger: cobrança de gateway é criada por OrderPaymentLink, nunca por add()'
            );
        }

        $this->legacy->add($orderId, $token, $amount, $charge);
    }

    /**
     * Aplica o pagamento confirmado pelo provedor.
     *
     * `$id` é o `id` devolvido por `latestForOrder()`/`byToken()`: `transaction_id`
     * para linha do ledger novo, `mp_payment_id` para linha legada.
     */
    public function record(int $id, string $paymentId, string $status): void {
        $row = $this->findById($id);

        if (($row['source'] ?? '') === 'gateway') {
            $this->recordGateway($row, $paymentId, $status);

            return;
        }

        $this->legacy->record($id, $paymentId, $status);
    }

    public function moveOrder(int $orderId, int $orderStatusId, string $comment): void {
        $this->legacy->moveOrder($orderId, $orderStatusId, $comment);
    }

    // ── internos ──────────────────────────────────────────────────────────

    /**
     * Linha já convertida para o vocabulário do contrato (a mesma que
     * `latestForOrder()` devolve), pelo `transaction_id` do ledger novo.
     *
     * @return array<string, mixed>
     */
    private function findById(int $id): array {
        if ($id <= 0) {
            return [];
        }

        $record = $this->addressable?->byTransactionId($id);

        return $record === null ? [] : OrderGatewayLedger::rowFromRecord($record);
    }

    /**
     * Reaplica o estado confirmado como evento verificado, pelo ponto único que
     * o webhook e a reconciliação já usam (ADR 0118, decisão 3).
     *
     * A referência do provedor vem da própria linha, não do aviso: assim um
     * aviso que fale de outra tentativa não encontra nada para aplicar.
     *
     * @param array<string, mixed> $row
     */
    private function recordGateway(array $row, string $paymentId, string $status): void {
        if ($this->applier === null) {
            throw new \RuntimeException('order flow ledger: gateway schema is not available');
        }

        $record = $this->addressable?->byTransactionId((int)($row['id'] ?? 0));

        if ($record === null) {
            throw new \RuntimeException('order flow ledger: gateway attempt disappeared');
        }

        $event = GatewayWebhookResult::verified(
            (string)($record->providerPaymentId !== '' ? $record->providerPaymentId : $paymentId),
            $record->providerReference,
            $paymentId,
            $status,
            hash('sha256', $record->providerReference . '|' . $paymentId . '|' . $status),
            $record->chargeAmount
        );

        $this->applier->apply($record->gatewayId, $record->providerCode, $event);
    }
}
