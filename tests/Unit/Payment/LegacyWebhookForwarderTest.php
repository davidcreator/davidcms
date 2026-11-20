<?php declare(strict_types=1);
namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayAccountStore;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayPaymentRecord;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\LegacyWebhookForwarder;
use Reamur\System\Library\Payment\Notification;

/**
 * ADR 0122 (F2) — o aviso que chega no endereço antigo do Mercado Pago passa a
 * alimentar o núcleo novo. O que este teste protege é a **escolha da conta**:
 * errar a conta significa verificar assinatura com o segredo errado e recusar
 * um pagamento legítimo.
 */
final class LegacyWebhookForwarderTest extends TestCase {
    public function testItPrefersTheAccountThatOwnsTheOrder(): void {
        // Duas contas mp: a #1 está habilitada, a #2 (a dona da ordem) está
        // desabilitada. O aviso de uma ordem da #2 tem de ir para a #2: a conta
        // que criou a tentativa é a que tem o segredo certo.
        $store = new ForwarderAccountStore([
            new GatewayAccount(1, 'mp', 'principal', 'live', true),
            new GatewayAccount(2, 'mp', 'antiga', 'live', false),
        ]);
        $ledger = new ForwarderLedger(['ORDER-2' => 2]);

        $forwarder = new LegacyWebhookForwarder($store, $ledger, self::webhookService());
        $forwarded = $forwarder->forward($this->notification(['data.id' => 'ORDER-2'], ''));

        self::assertNotNull($forwarded);
        self::assertSame(2, $forwarded['account']->gatewayId);
        self::assertSame(1, $store->credentialsCalls, 'as credenciais da conta dona foram as usadas');
    }

    public function testItFallsBackToTheEnabledAccountWhenTheReferenceIsUnknown(): void {
        // Aviso de ordem antiga, sem tentativa no ledger: quem pode responder é
        // a conta habilitada — a desabilitada não cria tentativa nenhuma.
        $store = new ForwarderAccountStore([
            new GatewayAccount(2, 'mp', 'antiga', 'live', false),
            new GatewayAccount(3, 'mp', 'atual', 'live', true),
        ]);
        $forwarder = new LegacyWebhookForwarder($store, new ForwarderLedger([]), self::webhookService());

        $forwarded = $forwarder->forward($this->notification(['data.id' => 'ORDER-X'], ''));

        self::assertNotNull($forwarded);
        self::assertSame(3, $forwarded['account']->gatewayId);
    }

    public function testWithoutAnyUsableAccountItDeclinesInsteadOfGuessing(): void {
        // Sem conta mp habilitada e sem dono conhecido, o encaminhador devolve
        // null: quem responde é o caminho legado. Adivinhar conta aqui seria
        // verificar assinatura com o segredo errado.
        $store = new ForwarderAccountStore([
            new GatewayAccount(1, 'stripe', 'outro', 'live', true),
            new GatewayAccount(2, 'mp', 'desligada', 'live', false),
        ]);
        $forwarder = new LegacyWebhookForwarder($store, new ForwarderLedger([]), self::webhookService());

        self::assertNull($forwarder->forward($this->notification(['data.id' => 'ORDER-X'], '')));
        self::assertSame(0, $store->credentialsCalls);
    }

    public function testItReadsTheOrderIdFromTheBodyWhenTheQueryIsEmpty(): void {
        $body = json_encode(['type' => 'order', 'data' => ['id' => 'ORDER-B']]);
        self::assertSame('ORDER-B', LegacyWebhookForwarder::orderReference($this->notification([], (string)$body)));
    }

    public function testAQueryAndBodyDisagreementYieldsNoReference(): void {
        // Mesma regra do adapter do provedor: se as duas fontes discordam, não
        // há id confiável — e sem id confiável não há escolha de conta.
        $body = json_encode(['data' => ['id' => 'ORDER-C']]);
        self::assertSame('', LegacyWebhookForwarder::orderReference($this->notification(['data.id' => 'ORDER-D'], (string)$body)));
    }

    public function testItRejectsAReferenceThatIsNotAnOrderId(): void {
        self::assertSame('', LegacyWebhookForwarder::orderReference($this->notification(['data.id' => '../../etc/passwd'], '')));
        self::assertSame('', LegacyWebhookForwarder::orderReference($this->notification([], '')));
    }

    public function testAnOversizedBodyIsNotParsed(): void {
        // O corpo do aviso é entrada de terceiro: 64 KB é o mesmo teto que o
        // caminho novo usa antes de olhar o JSON.
        $body = '{"data":{"id":"ORDER-E"}}' . str_repeat(' ', 70000);
        self::assertSame('', LegacyWebhookForwarder::orderReference($this->notification([], $body)));
    }

    /**
     * Serviço **real** com um adapter de mentira no registro: `GatewayWebhookService`
     * é `final`, e usar o caminho de verdade (verifyNotification → process) vale
     * mais no teste do que substituí-lo.
     */
    private static function webhookService(): \Reamur\System\Library\Payment\GatewayWebhookService {
        return new \Reamur\System\Library\Payment\GatewayWebhookService(
            new \Reamur\System\Library\Payment\GatewayRegistry([new ForwarderAdapter()]),
            new ForwarderLedger([])
        );
    }

    /** @param array<string, string> $get */
    private function notification(array $get, string $body): Notification {
        return new Notification($get, [], [], $body);
    }
}

/** @internal */
final class ForwarderAccountStore implements GatewayAccountStore {
    public int $credentialsCalls = 0;

    /** @param list<GatewayAccount> $accounts */
    public function __construct(private readonly array $accounts) {
    }

    public function find(int $gatewayId): ?GatewayAccount {
        foreach ($this->accounts as $account) {
            if ($account->gatewayId === $gatewayId) {
                return $account;
            }
        }

        return null;
    }

    /** @return list<GatewayAccount> */
    public function all(): array {
        return $this->accounts;
    }

    public function credentials(int $gatewayId): GatewayCredentials {
        $this->credentialsCalls++;

        return new GatewayCredentials(['access_token' => 'token-' . $gatewayId, 'webhook_secret' => str_repeat('s', 20)]);
    }

    public function create(
        string $providerCode,
        string $accountLabel,
        string $environment,
        bool $enabled,
        array $publicConfig,
        array $secretFields
    ): GatewayAccount {
        throw new \LogicException('not used');
    }

    public function update(
        int $gatewayId,
        string $accountLabel,
        string $environment,
        bool $enabled,
        array $publicConfig,
        array $secretUpdates = [],
        array $clearFields = []
    ): GatewayAccount {
        throw new \LogicException('not used');
    }
}

/** @internal */
final class ForwarderLedger implements \Reamur\System\Library\Payment\GatewayLedger {
    /** @param array<string, int> $owners reference => gatewayId */
    public function __construct(private readonly array $owners) {
    }

    /** A conta dona responde; as outras não conhecem a referência. */
    public function byProviderReference(int $gatewayId, string $providerReference): ?GatewayPaymentRecord {
        if (($this->owners[$providerReference] ?? 0) !== $gatewayId) {
            return null;
        }

        return new GatewayPaymentRecord(
            1, $gatewayId, 'order', 7, 'mp', 'mp',
            new \Reamur\System\Library\Payment\Money('100.0000', 'BRL', 2),
            new \Reamur\System\Library\Payment\Money('100.0000', 'BRL', 2),
            str_repeat('a', 48), 'order:' . str_repeat('b', 26), 'pending', $providerReference, '',
            \Reamur\System\Library\Payment\GatewayCustomerAction::redirect('https://example.test/checkout'), null
        );
    }

    public function pendingAttempts(int $gatewayId, string $modifiedBeforeUtc, string $createdAfterUtc, int $limit): array {
        return [];
    }

    public function latestForSource(string $sourceType, int $sourceId): ?GatewayPaymentRecord {
        return null;
    }

    public function byPublicToken(string $token): ?GatewayPaymentRecord {
        return null;
    }

    public function byTransactionId(int $transactionId): ?GatewayPaymentRecord {
        return null;
    }

    public function byIdempotencyKey(int $gatewayId, \Reamur\System\Library\Payment\GatewayPaymentRequest $request): ?GatewayPaymentRecord {
        return null;
    }

    public function createAttempt(int $gatewayId, string $providerCode, \Reamur\System\Library\Payment\GatewayPaymentRequest $request, \Reamur\System\Library\Payment\GatewayPaymentResult $result): GatewayPaymentRecord {
        throw new \LogicException('not used');
    }

    public function recordWebhookOnce(int $gatewayId, GatewayWebhookResult $event): bool {
        return true;
    }

    public function applyVerifiedEvent(int $gatewayId, GatewayWebhookResult $event): void {
    }
}

/**
 * Adapter mínimo do provedor `mp`: não fala com ninguém e devolve "ignorado".
 * O que o teste mede é a **escolha da conta**, não a conversa com o provedor.
 *
 * @internal
 */
final class ForwarderAdapter implements \Reamur\System\Library\Payment\GatewayAdapter, \Reamur\System\Library\Payment\ReconcilableGatewayAdapter {
    public function code(): string {
        return 'mp';
    }

    public function label(): string {
        return 'Mercado Pago';
    }

    public function capabilities(): \Reamur\System\Library\Payment\GatewayCapabilities {
        return new \Reamur\System\Library\Payment\GatewayCapabilities(['mp' => ['BRL']], ['BRL' => 2]);
    }

    public function quote(\Reamur\System\Library\Payment\Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?\Reamur\System\Library\Payment\FxQuote {
        return null;
    }

    public function createPayment(\Reamur\System\Library\Payment\GatewayPaymentRequest $request, GatewayAccount $account, GatewayCredentials $credentials, ?\Reamur\System\Library\Payment\FxQuote $quote): \Reamur\System\Library\Payment\GatewayPaymentResult {
        throw new \LogicException('not used');
    }

    public function verifyNotification(Notification $notification, GatewayAccount $account, GatewayCredentials $credentials): GatewayWebhookResult {
        return GatewayWebhookResult::ignored('adapter_do_teste');
    }

    public function reconcile(GatewayPaymentRecord $record, GatewayAccount $account, GatewayCredentials $credentials): GatewayWebhookResult {
        return GatewayWebhookResult::ignored('adapter_do_teste');
    }
}
