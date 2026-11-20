<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\FxQuote;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayAccountStore;
use Reamur\System\Library\Payment\GatewayAdapter;
use Reamur\System\Library\Payment\GatewayCapabilities;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayLedger;
use Reamur\System\Library\Payment\GatewayPaymentRecord;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentResult;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayRegistry;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\Link;
use Reamur\System\Library\Payment\Money;
use Reamur\System\Library\Payment\Notification;
use Reamur\System\Library\Payment\OrderGatewayLedger;
use Reamur\System\Library\Payment\OrderPaymentLink;

/**
 * ADR 0119: o link de pagamento de um PEDIDO nasce no ledger novo.
 *
 * O que estes testes protegem, em ordem de dano se quebrarem:
 *  - pedido já pago não ganha segundo link;
 *  - link pendente é reaproveitado (o cliente não recebe duas cobranças);
 *  - provedor sem conta habilitada dá BLOCKED, não erro genérico;
 *  - a chave de idempotência é derivada e diferente a cada nova tentativa;
 *  - conta em produção com a trava de segurança desligada falha em vez de cobrar.
 */
final class OrderPaymentLinkTest extends TestCase {
    private function account(bool $enabled = true, string $environment = 'sandbox', int $id = 7): GatewayAccount {
        return new GatewayAccount($id, 'ordertest', 'JOW platform', $environment, $enabled);
    }

    private function linker(
        OrderPaymentLinkTestAdapter $adapter,
        OrderPaymentLinkTestLedger $ledger,
        array $accounts = [],
        bool $allowLive = false
    ): OrderPaymentLink {
        return new OrderPaymentLink(
            new GatewayRegistry([$adapter]),
            new OrderPaymentLinkTestStore($accounts),
            new OrderGatewayLedger($ledger, new OrderFlowTestLegacyLedger()),
            $allowLive
        );
    }

    public function testCreatesTheAttemptInTheNewLedgerAndReturnsThePublicToken(): void {
        $adapter = new OrderPaymentLinkTestAdapter();
        $ledger = new OrderPaymentLinkTestLedger();
        $link = $this->linker($adapter, $ledger, [$this->account()])
            ->link('ordertest', 12, 120.0, 'Pedido #12', 'https://shop.example/notify', 'https://shop.example/return');

        self::assertSame(Link::CREATED, $link->state);
        self::assertSame(str_repeat('f', 48), $link->token);
        self::assertSame(1, $adapter->createCalls);
        self::assertSame('order', $adapter->lastRequest?->sourceType);
        self::assertSame(12, $adapter->lastRequest?->sourceId);
        self::assertSame('120.00', $adapter->lastRequest?->sourceAmount->amount());
        self::assertSame(OrderPaymentLink::idempotencyKey(12, 1), $adapter->lastRequest?->idempotencyKey);
    }

    public function testPaidOrderNeverGetsASecondLink(): void {
        $adapter = new OrderPaymentLinkTestAdapter();
        $ledger = new OrderPaymentLinkTestLedger($this->record(GatewayPaymentStatus::PAID));

        $link = $this->linker($adapter, $ledger, [$this->account()])
            ->link('ordertest', 12, 120.0, 'Pedido #12', 'https://shop.example/notify', 'https://shop.example/return');

        self::assertSame(Link::PAID, $link->state);
        self::assertSame(0, $adapter->createCalls);
    }

    public function testPendingLinkWithUrlIsReusedInsteadOfChargingTwice(): void {
        $adapter = new OrderPaymentLinkTestAdapter();
        $ledger = new OrderPaymentLinkTestLedger($this->record(GatewayPaymentStatus::REQUIRES_ACTION));
        $ledger->record = $this->record(GatewayPaymentStatus::REQUIRES_ACTION);

        $link = $this->linker($adapter, $ledger, [$this->account()])
            ->link('ordertest', 12, 120.0, 'Pedido #12', 'https://shop.example/notify', 'https://shop.example/return');

        self::assertSame(Link::REUSED, $link->state);
        self::assertSame(str_repeat('f', 48), $link->token);
        self::assertSame(0, $adapter->createCalls);
    }

    public function testProviderWithoutEnabledAccountIsBlocked(): void {
        $link = $this->linker(new OrderPaymentLinkTestAdapter(), new OrderPaymentLinkTestLedger(), [$this->account(false)])
            ->link('ordertest', 12, 120.0, 'Pedido #12', 'https://shop.example/notify', 'https://shop.example/return');

        self::assertSame(Link::BLOCKED, $link->state);
        self::assertSame('no_gateway_account', $link->detail);
    }

    public function testUnknownProviderIsReportedAsNotInstalledInsteadOfThrowing(): void {
        $link = $this->linker(new OrderPaymentLinkTestAdapter(), new OrderPaymentLinkTestLedger(), [$this->account()])
            ->link('nao_existe', 12, 120.0, 'Pedido #12', 'https://shop.example/notify', 'https://shop.example/return');

        self::assertSame(Link::FAILED, $link->state);
        self::assertSame('order_gateway_not_installed', $link->detail);
    }

    public function testOrderWithoutAmountIsNotSentToTheProvider(): void {
        $adapter = new OrderPaymentLinkTestAdapter();

        $link = $this->linker($adapter, new OrderPaymentLinkTestLedger(), [$this->account()])
            ->link('ordertest', 12, 0.0, 'Pedido #12', 'https://shop.example/notify', 'https://shop.example/return');

        self::assertSame(Link::NO_TOTAL, $link->state);
        self::assertSame(0, $adapter->createCalls);
    }

    public function testLiveAccountStaysBlockedWhileTheSafetyGateIsOff(): void {
        $adapter = new OrderPaymentLinkTestAdapter();

        $link = $this->linker($adapter, new OrderPaymentLinkTestLedger(), [$this->account(true, 'live')])
            ->link('ordertest', 12, 120.0, 'Pedido #12', 'https://shop.example/notify', 'https://shop.example/return');

        self::assertSame(Link::FAILED, $link->state);
        self::assertSame('order_gateway_live_disabled', $link->detail);
        self::assertSame(0, $adapter->createCalls);
    }

    public function testProviderAnswerWithoutRedirectIsAFailureNotALink(): void {
        $adapter = new OrderPaymentLinkTestAdapter();
        $adapter->omitRedirect = true;

        $link = $this->linker($adapter, new OrderPaymentLinkTestLedger(), [$this->account()])
            ->link('ordertest', 12, 120.0, 'Pedido #12', 'https://shop.example/notify', 'https://shop.example/return');

        self::assertSame(Link::FAILED, $link->state);
        self::assertSame('order_gateway_without_redirect', $link->detail);
    }

    public function testIdempotencyKeyIsDerivedStableAndChangesPerAttempt(): void {
        $first = OrderPaymentLink::idempotencyKey(12, 1);
        $sameFirst = OrderPaymentLink::idempotencyKey(12, 1);
        $second = OrderPaymentLink::idempotencyKey(12, 2);

        self::assertSame($first, $sameFirst);
        self::assertNotSame($first, $second);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9._:-]{16,128}$/', $first);
        self::assertStringNotContainsString('12', substr($first, strlen('order:')));
    }

    public function testSecondAttemptAfterAFailureUsesANewKey(): void {
        $adapter = new OrderPaymentLinkTestAdapter();
        $ledger = new OrderPaymentLinkTestLedger($this->record(GatewayPaymentStatus::FAILED));

        $this->linker($adapter, $ledger, [$this->account()])
            ->link('ordertest', 12, 120.0, 'Pedido #12', 'https://shop.example/notify', 'https://shop.example/return');

        self::assertSame(OrderPaymentLink::idempotencyKey(12, 2), $adapter->lastRequest?->idempotencyKey);
    }

    private function record(string $status): GatewayPaymentRecord {
        return new GatewayPaymentRecord(
            501, 7, 'order', 12, 'ordertest', 'card',
            new Money('120.00', 'BRL', 2), new Money('120.00', 'BRL', 2),
            str_repeat('f', 48), 'order-12-attempt-01', $status, 'ref-501', '',
            GatewayCustomerAction::redirect('https://provider.example/pay/501'),
            null
        );
    }
}

final class OrderPaymentLinkTestAdapter implements GatewayAdapter {
    public int $createCalls = 0;
    public ?GatewayPaymentRequest $lastRequest = null;
    public string $redirectUrl = 'https://provider.example/pay/501';
    public bool $omitRedirect = false;

    public function code(): string {
        return 'ordertest';
    }

    public function label(): string {
        return 'Order test gateway';
    }

    public function capabilities(): GatewayCapabilities {
        return new GatewayCapabilities(['card' => ['BRL']], ['BRL' => 2]);
    }

    public function quote(Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?FxQuote {
        return null;
    }

    public function createPayment(GatewayPaymentRequest $request, GatewayAccount $account, GatewayCredentials $credentials, ?FxQuote $quote): GatewayPaymentResult {
        $this->createCalls++;
        $this->lastRequest = $request;

        if ($account->environment === 'live') {
            throw new \RuntimeException('gateway payment: live environment is disabled by the core safety gate');
        }

        // Um provedor pode responder "criado" sem dar endereço de pagamento (PIX
        // copia-e-cola, por exemplo). Aqui isso é simulado com a ação nula.
        return new GatewayPaymentResult(
            'ref-501',
            $this->omitRedirect
                ? GatewayCustomerAction::pixCopyPaste('00020126580014BR.GOV.BCB.PIX')
                : GatewayCustomerAction::redirect($this->redirectUrl),
            $request->sourceAmount,
            GatewayPaymentStatus::REQUIRES_ACTION,
            null,
            ''
        );
    }

    public function verifyNotification(Notification $notification, GatewayAccount $account, GatewayCredentials $credentials): GatewayWebhookResult {
        return GatewayWebhookResult::ignored('not_used');
    }
}

final class OrderPaymentLinkTestStore implements GatewayAccountStore {
    /** @param list<GatewayAccount> $accounts */
    public function __construct(private readonly array $accounts) {}

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
        return new GatewayCredentials([]);
    }

    public function create(string $providerCode, string $accountLabel, string $environment, bool $enabled, array $publicConfig, array $secretFields): GatewayAccount {
        return new GatewayAccount(1, $providerCode, $accountLabel, $environment, $enabled);
    }

    public function update(int $gatewayId, string $accountLabel, string $environment, bool $enabled, array $publicConfig, array $secretUpdates = [], array $clearFields = []): GatewayAccount {
        return new GatewayAccount($gatewayId, 'ordertest', $accountLabel, $environment, $enabled);
    }
}

final class OrderPaymentLinkTestLedger implements GatewayLedger {
    public ?GatewayPaymentRecord $record;

    public function __construct(?GatewayPaymentRecord $record = null) {
        $this->record = $record;
    }

    public function latestForSource(string $sourceType, int $sourceId): ?GatewayPaymentRecord {
        return $this->record;
    }

    public function byPublicToken(string $token): ?GatewayPaymentRecord {
        return null;
    }

    public function byProviderReference(int $gatewayId, string $providerReference): ?GatewayPaymentRecord {
        return null;
    }

    public function byIdempotencyKey(int $gatewayId, GatewayPaymentRequest $request): ?GatewayPaymentRecord {
        return null;
    }

    public function createAttempt(int $gatewayId, string $providerCode, GatewayPaymentRequest $request, GatewayPaymentResult $result): GatewayPaymentRecord {
        return new GatewayPaymentRecord(
            501, $gatewayId, $request->sourceType, $request->sourceId, $providerCode, $request->paymentMethod,
            $request->sourceAmount, $result->chargeAmount, str_repeat('f', 48), $request->idempotencyKey,
            $result->status, $result->providerReference, $result->providerPaymentId, $result->customerAction, null
        );
    }

    public function recordWebhookOnce(int $gatewayId, GatewayWebhookResult $event): bool {
        return true;
    }

    public function applyVerifiedEvent(int $gatewayId, GatewayWebhookResult $event): void {}
}
