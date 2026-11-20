<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Onde o fluxo do pedido encontra os dois ledgers (ADR 0119).
 *
 *   gateway()   tentativas por provedor  → rms_payment_transaction (ledger novo)
 *   legacy()    pagamento manual e links emitidos antes do deploy → rms_mp_payment
 *
 * Esta classe não decide nada: só entrega as duas portas para quem precisa
 * ler uma e a outra (o link do pedido e o `target()` do Checkout). A regra
 * de negócio fica em `OrderPaymentLink`; a escolha de qual ledger escrever
 * fica em `OrderFlowLedger`.
 */
namespace Reamur\System\Library\Payment;

final class OrderGatewayLedger {
    public function __construct(
        private readonly GatewayLedger $gateway,
        private readonly Ledger $legacy,
    ) {}

    public function gateway(): GatewayLedger {
        return $this->gateway;
    }

    public function legacy(): Ledger {
        return $this->legacy;
    }

    /** Tentativa mais recente do pedido no ledger novo; null quando não há. */
    public function latestAttempt(int $orderId): ?GatewayPaymentRecord {
        if ($orderId <= 0) {
            return null;
        }

        return $this->gateway->latestForSource('order', $orderId);
    }

    /**
     * Cobrança do pedido para a tela e para o aviso do provedor, no formato do
     * contrato da ADR 0097 (`id`, `order_id`, `token`, `status`, `url`,
     * `payment_id`), procurando primeiro no ledger novo.
     *
     * O `id` devolvido é o `transaction_id` quando a linha vem do ledger novo e
     * o `mp_payment_id` quando vem do legado — em ambos os casos é o
     * identificador que `record()` da mesma origem espera receber.
     *
     * @return array<string, mixed>
     */
    public function latestForOrder(int $orderId): array {
        $record = $this->latestAttempt($orderId);

        if ($record !== null) {
            return self::rowFromRecord($record);
        }

        return $this->legacy->latestForOrder($orderId);
    }

    /**
     * Mesma conversão de `latestForOrder()`, mas pelo token público.
     *
     * @return array<string, mixed>
     */
    public function byToken(string $token): array {
        $token = trim($token);

        if ($token === '') {
            return [];
        }

        $record = $this->gateway->byPublicToken($token);

        if ($record !== null) {
            return self::rowFromRecord($record);
        }

        return $this->legacy->byToken($token);
    }

    /**
     * Linha do ledger novo no vocabulário do contrato antigo.
     *
     * `status` é traduzido para as palavras que o `Checkout` já compara
     * (`approved`, `pending`, ...): sem isso um pedido pago no ledger novo
     * pareceria em aberto e um link pendente seria recriado.
     *
     * @return array<string, mixed>
     */
    public static function rowFromRecord(GatewayPaymentRecord $record): array {
        return [
            'id'         => $record->transactionId,
            'source'     => 'gateway',
            'order_id'   => $record->sourceType === 'order' ? $record->sourceId : 0,
            'token'      => $record->publicToken,
            'status'     => self::legacyStatus($record->status),
            'url'        => $record->customerAction->url,
            'payment_id' => $record->providerPaymentId,
        ];
    }

    /**
     * Situação do ledger novo nas palavras do contrato da ADR 0097.
     *
     * `paid` vira `approved` porque é isso que o `Checkout` procura para não
     * gerar um segundo link; estados encerrados sem pagamento viram `rejected`
     * ou `cancelled` para que o link pare de levar o cliente ao provedor.
     */
    public static function legacyStatus(string $gatewayStatus): string {
        return match ($gatewayStatus) {
            GatewayPaymentStatus::PAID => Verdict::APPROVED,
            GatewayPaymentStatus::REFUNDED,
            GatewayPaymentStatus::PARTIALLY_REFUNDED,
            GatewayPaymentStatus::DISPUTED => Verdict::APPROVED,
            GatewayPaymentStatus::FAILED => Verdict::REJECTED,
            GatewayPaymentStatus::CANCELLED,
            GatewayPaymentStatus::EXPIRED => Verdict::CANCELLED,
            default => 'pending',
        };
    }
}
