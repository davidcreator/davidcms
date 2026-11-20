<?php declare(strict_types=1);
namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\DbGatewayLedger;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\GatewayWebhookService;
use Reamur\System\Library\Payment\GatewayRegistry;
use Reamur\System\Library\Payment\MercadoPagoGatewayAdapter;
use Reamur\System\Library\Payment\Notification;

/**
 * O que o **teste B** do roteiro de validação vai provar em sandbox, provado
 * antes dele: a cadeia inteira, com assinatura de verdade.
 *
 * `MercadoPagoGatewayAdapterTest` prova que a assinatura é verificada e que o
 * estado normaliza para `paid`. `GatewayWebhookServiceTest` prova as regras
 * pós-verificação com um evento já pronto. **Faltava o meio**: alguém provar
 * que os dois se encaixam — assinatura → leitura no provedor → evento →
 * `GatewayEventApplier` → linha `paid` no ledger.
 *
 * Isto não substitui o sandbox: aqui o provedor é um dublê. O que ele elimina é
 * a classe de erro mais cara de descobrir tarde — a de fiação, em que cada
 * peça funciona sozinha e o conjunto grava o estado errado.
 */
final class WebhookChainToPaidTest extends TestCase {
    private const ORDER_ID = 'ORDERTEST123456';
    private const ACCESS_TOKEN = 'APP_USR-token-de-teste-0123456789';
    private const WEBHOOK_SECRET = 'segredo-de-webhook-1234567890';
    private const IDEMPOTENCY = 'order:0123456789abcdef0123456789abcdef';
    private const AMOUNT = '125.50';

    public function testASignedProviderNoticeEndsAsAPaidRowInTheLedger(): void {
        $api = new ChainProviderDouble([
            '_http_status' => 200,
            'id' => self::ORDER_ID,
            'external_reference' => self::IDEMPOTENCY,
            'currency' => 'BRL',
            'total_amount' => self::AMOUNT,
            'total_paid_amount' => self::AMOUNT,
            'status' => 'processed',
            'status_detail' => 'accredited',
            'transactions' => ['payments' => [['id' => 'PAY01MERCADOPAGO123']]],
        ]);

        $ledger = new ChainLedger(status: GatewayPaymentStatus::PENDING);
        $service = new GatewayWebhookService(
            new GatewayRegistry([new MercadoPagoGatewayAdapter($api)]),
            $ledger
        );

        $result = $service->process($this->account(), $this->credentials(), $this->signedNotice());

        self::assertSame(GatewayWebhookResult::VERIFIED, $result->kind, 'o aviso assinado foi aceito');
        self::assertSame(GatewayPaymentStatus::PAID, $result->status, 'o provedor disse processed');
        self::assertSame(self::AMOUNT, $result->chargeAmount?->amount());

        // O elo que faltava: o applier aplicou no ledger.
        self::assertSame([GatewayPaymentStatus::PAID], $ledger->applied, 'o ledger recebeu o estado pago');
        self::assertSame([[self::ORDER_ID, self::ACCESS_TOKEN]], $api->getCalls, 'a leitura foi na conta certa');
    }

    public function testATamperedSignatureNeverReachesTheLedger(): void {
        $api = new ChainProviderDouble(['_http_status' => 200]);
        $ledger = new ChainLedger(status: GatewayPaymentStatus::PENDING);
        $service = new GatewayWebhookService(
            new GatewayRegistry([new MercadoPagoGatewayAdapter($api)]),
            $ledger
        );

        $notice = new Notification(
            ['type' => 'order', 'data.id' => self::ORDER_ID],
            [],
            ['HTTP_X_SIGNATURE' => 'ts=1784300000,v1=' . str_repeat('0', 64), 'HTTP_X_REQUEST_ID' => 'req-1'],
            '{}'
        );

        $result = $service->process($this->account(), $this->credentials(), $notice);

        self::assertSame(GatewayWebhookResult::IGNORED, $result->kind);
        self::assertSame('signature_rejected', $result->reasonCode);
        self::assertSame([], $ledger->applied, 'assinatura errada nao escreve nada');
        self::assertSame([], $api->getCalls, 'e nem consulta o provedor');
    }

    public function testAnAmountThatDoesNotMatchTheAttemptNeverBecomesPaid(): void {
        // Regra de dinheiro que o applier tem e o adapter nao tem: o provedor
        // pode dizer "pago" com valor diferente do que se cobrou. Isso e
        // recusado sem mudar o estado.
        $api = new ChainProviderDouble([
            '_http_status' => 200,
            'id' => self::ORDER_ID,
            'external_reference' => self::IDEMPOTENCY,
            'currency' => 'BRL',
            'total_amount' => '999.00',
            'total_paid_amount' => '999.00',
            'status' => 'processed',
            'status_detail' => 'accredited',
            'transactions' => ['payments' => [['id' => 'PAY01MERCADOPAGO123']]],
        ]);
        $ledger = new ChainLedger(status: GatewayPaymentStatus::PENDING);
        $service = new GatewayWebhookService(
            new GatewayRegistry([new MercadoPagoGatewayAdapter($api)]),
            $ledger
        );

        $result = $service->process($this->account(), $this->credentials(), $this->signedNotice());

        self::assertNotSame(GatewayWebhookResult::VERIFIED, $result->kind);
        self::assertSame([], $ledger->applied, 'valor divergente nunca vira pago');
    }

    private function account(): GatewayAccount {
        return new GatewayAccount(9, 'mp', 'teste', 'sandbox', true);
    }

    private function credentials(): GatewayCredentials {
        return new GatewayCredentials([
            'access_token' => self::ACCESS_TOKEN,
            'webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    /** Aviso no formato que o Mercado Pago envia, com assinatura valida. */
    private function signedNotice(): Notification {
        $timestamp = '1784300000';
        $requestId = 'req-42';
        $manifest = 'id:' . strtolower(self::ORDER_ID) . ';request-id:' . $requestId . ';ts:' . $timestamp . ';';
        $signature = 'ts=' . $timestamp . ',v1=' . hash_hmac('sha256', $manifest, self::WEBHOOK_SECRET);

        return new Notification(
            ['type' => 'order', 'data.id' => self::ORDER_ID],
            [],
            ['HTTP_X_SIGNATURE' => $signature, 'HTTP_X_REQUEST_ID' => $requestId],
            '{}'
        );
    }
}

/** Provedor de mentira: devolve a ordem e anota qual token foi usado. @internal */
final class ChainProviderDouble extends \Reamur\System\Library\MercadopagoMarketplace {
    /** @var list<array{0: string, 1: string}> */
    public array $getCalls = [];

    /** @param array<string, mixed> $order */
    public function __construct(private readonly array $order) {
        parent::__construct([]);
    }

    /** @return array<string, mixed> */
    public function getOrder(string $orderId, string $accessToken): array {
        $this->getCalls[] = [$orderId, $accessToken];

        return $this->order;
    }
}

/**
 * Ledger de mentira que registra o que o applier pediu para aplicar.
 * @internal
 */
final class ChainLedger implements \Reamur\System\Library\Payment\GatewayReconcilableLedger {
    /** @var list<string> */
    public array $applied = [];

    public function __construct(private readonly string $status = GatewayPaymentStatus::PENDING) {
    }

    public function byTransactionId(int $transactionId): ?\Reamur\System\Library\Payment\GatewayPaymentRecord {
        return null;
    }

    public function pendingAttempts(int $gatewayId, string $modifiedBeforeUtc, string $createdAfterUtc, int $limit): array {
        return [];
    }

    public function settledAttempts(int $gatewayId, string $modifiedBeforeUtc, string $createdAfterUtc, int $limit): array {
        return [];
    }

    public function latestForSource(string $sourceType, int $sourceId): ?\Reamur\System\Library\Payment\GatewayPaymentRecord {
        return null;
    }

    public function byPublicToken(string $token): ?\Reamur\System\Library\Payment\GatewayPaymentRecord {
        return null;
    }

    public function byIdempotencyKey(int $gatewayId, \Reamur\System\Library\Payment\GatewayPaymentRequest $request): ?\Reamur\System\Library\Payment\GatewayPaymentRecord {
        return null;
    }

    public function createAttempt(int $gatewayId, string $providerCode, \Reamur\System\Library\Payment\GatewayPaymentRequest $request, \Reamur\System\Library\Payment\GatewayPaymentResult $result): \Reamur\System\Library\Payment\GatewayPaymentRecord {
        throw new \LogicException('not used');
    }

    public function byProviderReference(int $gatewayId, string $providerReference): ?\Reamur\System\Library\Payment\GatewayPaymentRecord {
        return new \Reamur\System\Library\Payment\GatewayPaymentRecord(
            1, $gatewayId, 'order', 7, 'mp', 'mp',
            new \Reamur\System\Library\Payment\Money('125.5000', 'BRL', 2),
            new \Reamur\System\Library\Payment\Money('125.5000', 'BRL', 2),
            str_repeat('a', 48), 'order:' . str_repeat('b', 26), $this->status,
            $providerReference, '',
            \Reamur\System\Library\Payment\GatewayCustomerAction::redirect('https://example.test/checkout'), null
        );
    }

    public function applyVerifiedEvent(int $gatewayId, GatewayWebhookResult $event): void {
        $this->applied[] = $event->status;
    }

    public function recordWebhookOnce(int $gatewayId, GatewayWebhookResult $event): bool {
        return true;
    }
}
