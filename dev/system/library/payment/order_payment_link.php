<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * O link de pagamento de um pedido, criado no ledger novo (ADR 0119).
 *
 * Regras, na ordem em que a tela as mostra:
 *   1. pedido já pago         → Link::PAID (não se cria outro)
 *   2. link pendente vivo     → Link::REUSED (mesma chave, sem nova cobrança)
 *   3. sem conta habilitada   → Link::BLOCKED
 *   4. pedido sem valor       → Link::NO_TOTAL
 *   5. provedor não criou     → Link::FAILED
 *
 * A chave do link e o reaproveitamento são do ledger, não desta classe: o
 * `GatewayPaymentService` já recusa criar duas tentativas para a mesma chave
 * idempotente, e a chave é derivada do pedido e do número da tentativa.
 */
namespace Reamur\System\Library\Payment;

final class OrderPaymentLink {
    /** Prefixo estável: a chave de idempotência precisa ser reproduzível. */
    private const KEY_PREFIX = 'order:';

    public function __construct(
        private readonly GatewayRegistry $registry,
        private readonly GatewayAccountStore $accounts,
        private readonly OrderGatewayLedger $ledgers,
        private readonly bool $allowLive = false,
    ) {}

    /**
     * @param string $notifyUrl endereço público que o provedor deve avisar
     * @param string $returnUrl para onde o cliente volta depois de pagar
     */
    public function link(
        string $providerCode,
        int $orderId,
        float $total,
        string $title,
        string $notifyUrl,
        string $returnUrl,
        string $payerEmail = ''
    ): Link {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $providerCode) !== 1 || $orderId <= 0) {
            return new Link(Link::FAILED, '', 'order_gateway_invalid_request');
        }

        try {
            $adapter = $this->registry->get($providerCode);
        } catch (\OutOfBoundsException) {
            return new Link(Link::FAILED, '', 'order_gateway_not_installed');
        }

        $current = $this->ledgers->latestForOrder($orderId);

        if ($current !== [] && (string)($current['status'] ?? '') === Verdict::APPROVED) {
            return new Link(Link::PAID);
        }

        if ($current !== [] && (string)($current['status'] ?? '') === 'pending'
            && (string)($current['url'] ?? '') !== '') {
            return new Link(Link::REUSED, (string)($current['token'] ?? ''));
        }

        $account = $this->enabledAccount($providerCode);

        if ($account === null) {
            return new Link(Link::BLOCKED, '', 'no_gateway_account');
        }

        $amount = round($total, 2);

        if ($amount <= 0) {
            return new Link(Link::NO_TOTAL);
        }

        $methods = array_keys($adapter->capabilities()->methods());
        $method = (string)($methods[0] ?? '');

        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $method) !== 1) {
            return new Link(Link::FAILED, '', 'order_gateway_without_method');
        }

        try {
            $request = new GatewayPaymentRequest(
                'order',
                $orderId,
                new Money(number_format($amount, 2, '.', ''), 'BRL', 2),
                'BRL',
                $method,
                $this->idempotencyKey($orderId, $this->nextAttemptNumber($orderId)),
                mb_substr(trim($title) !== '' ? trim($title) : ('Pedido #' . $orderId), 0, 255),
                $returnUrl,
                $notifyUrl,
                $payerEmail
            );

            $service = new GatewayPaymentService($this->registry, $this->ledgers->gateway(), null, $this->allowLive);

            $record = $service->createPayment($account, $request, $this->accounts->credentials($account->gatewayId));
        } catch (\Throwable $e) {
            // Falha de provedor, schema ausente ou credencial incompleta: a tela
            // recebe um motivo curto, nunca a mensagem crua do provedor.
            return new Link(Link::FAILED, '', $this->safeReason($e));
        }

        // A tentativa já existe no ledger; se o provedor não devolveu para onde
        // mandar o cliente, o link não serve para pagar agora — mas o registro
        // fica, e a próxima tentativa usa outra chave.
        if ($record->customerAction->url === '') {
            return new Link(Link::FAILED, '', 'order_gateway_without_redirect');
        }

        return new Link(Link::CREATED, $record->publicToken);
    }

    /**
     * Chave de idempotência do pedido: estável para a mesma tentativa e nova a
     * cada tentativa seguinte. Derivada por sha1 de propósito — o número do
     * pedido não deve ser adivinhável a partir da chave enviada ao provedor.
     */
    public static function idempotencyKey(int $orderId, int $attempt): string {
        return self::KEY_PREFIX . substr(sha1('reamurcms:order:' . $orderId . ':attempt:' . $attempt), 0, 32);
    }

    /** Primeira conta habilitada do provedor, por `gateway_id` (comportamento atual do MP). */
    private function enabledAccount(string $providerCode): ?GatewayAccount {
        $found = [];

        foreach ($this->accounts->all() as $account) {
            if ($account->providerCode === $providerCode && $account->enabled) {
                $found[] = $account;
            }
        }

        if ($found === []) {
            return null;
        }

        usort($found, static fn(GatewayAccount $a, GatewayAccount $b): int => $a->gatewayId <=> $b->gatewayId);

        return $found[0];
    }

    /** Quantas tentativas o pedido já teve no ledger novo. */
    private function nextAttemptNumber(int $orderId): int {
        $latest = $this->ledgers->latestAttempt($orderId);

        return $latest === null ? 1 : 2;
    }

    /** Motivo curto e sem dado sensível para a tela. */
    private function safeReason(\Throwable $e): string {
        $message = $e->getMessage();

        if (str_contains($message, 'payment method/currency pair is not supported')) {
            return 'order_gateway_method_unsupported';
        }

        if (str_contains($message, 'idempotency key was already used')) {
            return 'order_gateway_key_reused';
        }

        if (str_contains($message, 'live environment is disabled')) {
            return 'order_gateway_live_disabled';
        }

        if (str_contains($message, 'disabled')) {
            return 'order_gateway_account_disabled';
        }

        if (str_contains($message, 'expired customer action')) {
            return 'order_gateway_action_expired';
        }

        if (str_contains($message, 'URL must be valid HTTPS') || str_contains($message, 'callback and return URLs')) {
            return 'order_gateway_invalid_redirect';
        }

        return 'order_gateway_provider_error';
    }
}
