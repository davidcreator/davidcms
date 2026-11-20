<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * ADR 0122 (F2) — o endereço antigo de aviso do Mercado Pago passa a alimentar
 * o núcleo novo.
 *
 * O Mercado Pago já tem `checkout/payment.notify` configurado como URL de
 * aviso há anos. Trocar o endereço abriria uma janela em que aviso de pagamento
 * se perde — o provedor bate em uma URL que não existe mais. Então o endereço
 * fica e o **encaminhamento** acontece aqui.
 *
 * Duas coisas que este adaptador deliberadamente NÃO faz:
 *
 *  - não chama provedor: quem fala com o Mercado Pago é o adapter de gateway,
 *    com assinatura verificada e leitura somente;
 *  - não decide situação: ele devolve o mesmo `GatewayWebhookResult` que o
 *    `GatewayWebhookService` produziria, e quem aplica continua sendo
 *    `GatewayEventApplier`, com as mesmas checagens de valor, id e transição
 *    monotônica.
 *
 * Regra de escolha da conta, em ordem, e por que ela é conservadora:
 *  1. tentativa com `provider_reference` = id da ordem (a única prova direta de
 *     qual conta criou aquela ordem);
 *  2. sem tentativa: a conta habilitada do provedor `mp` — porque a conta
 *     desabilitada não cria mais tentativa nenhuma, e um aviso de ordem antiga
 *     precisa continuar entrando.
 * Nenhuma das duas é adivinhação sobre o corpo do aviso.
 */
final class LegacyWebhookForwarder {
    private const PROVIDER = 'mp';

    public function __construct(
        private readonly GatewayAccountStore $accounts,
        private readonly GatewayLedger $ledger,
        private readonly GatewayWebhookService $webhooks
    ) {
    }

    /**
     * Devolve a conta usada e o resultado, porque `GatewayWebhookResult` não
     * carrega `gateway_id` e o chamador precisa dele para sincronizar a
     * cobrança interna.
     *
     * @return array{account: GatewayAccount, result: GatewayWebhookResult}|null
     *         null quando não há conta utilizável para este aviso — o chamador
     *         decide o que responder (o caminho legado responde 200 e
     *         registra, para não provocar reentrega em laço).
     */
    public function forward(Notification $notification): ?array {
        $reference = self::orderReference($notification);

        $account = $this->accountForReference($reference) ?? $this->fallbackAccount();

        if ($account === null) {
            return null;
        }

        return [
            'account' => $account,
            'result'  => $this->webhooks->process($account, $this->accounts->credentials($account->gatewayId), $notification),
        ];
    }

    /**
     * O id da ordem no aviso do Mercado Pago vem na query (`data.id`) ou no
     * corpo (`data.id`), e os dois precisam concordar quando ambos existem —
     * mesma regra do adapter do provedor.
     */
    public static function orderReference(Notification $notification): string {
        $queryId = self::scalar($notification->get, ['data.id', 'data_id']);
        $body = self::decode($notification->body);
        $bodyId = self::scalar((array)($body['data'] ?? []), ['id']);

        if ($queryId !== '' && $bodyId !== '' && !hash_equals($queryId, $bodyId)) {
            return '';
        }

        $id = $queryId !== '' ? $queryId : $bodyId;

        return preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $id) === 1 ? $id : '';
    }

    private function accountForReference(string $reference): ?GatewayAccount {
        if ($reference === '') {
            return null;
        }

        foreach ($this->accounts->all() as $account) {
            if ($account->providerCode !== self::PROVIDER) {
                continue;
            }

            if ($this->ledger->byProviderReference($account->gatewayId, $reference) !== null) {
                return $account;
            }
        }

        return null;
    }

    private function fallbackAccount(): ?GatewayAccount {
        foreach ($this->accounts->all() as $account) {
            if ($account->providerCode === self::PROVIDER && $account->enabled) {
                return $account;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $source */
    private static function scalar(array $source, array $keys): string {
        foreach ($keys as $key) {
            $value = $source[$key] ?? null;

            if (is_scalar($value) && (string)$value !== '') {
                return (string)$value;
            }
        }

        return '';
    }

    /** @return array<string, mixed> */
    private static function decode(string $body): array {
        if ($body === '' || strlen($body) > 65536) {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
