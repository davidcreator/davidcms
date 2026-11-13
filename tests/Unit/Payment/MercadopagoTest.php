<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Library\MercadopagoMarketplace;
use Reamur\System\Library\Payment\ChargeRequest;
use Reamur\System\Library\Payment\Mercadopago;
use Reamur\System\Library\Payment\Notification;
use Reamur\System\Library\Payment\Verdict;
use Reamur\System\Library\Security\SecretBox;

/**
 * EX-03 (ADR 0097): o Mercado Pago atras do contrato. As regras sao as de
 * antes (ADR 0035 e 0094); so mudaram de lugar. A API e de mentira: nenhuma
 * chamada sai da maquina.
 */
final class MercadopagoTest extends TestCase {
    private const SELLER_A = ['mp_seller_id' => 1, 'mp_user_id' => 902901, 'nickname' => 'A', 'access_token' => 'TOKEN-A', 'token_locked' => false];
    private const SELLER_B = ['mp_seller_id' => 2, 'mp_user_id' => 902902, 'nickname' => 'B', 'access_token' => 'TOKEN-B', 'token_locked' => false];
    private const LOCKED   = ['mp_seller_id' => 3, 'mp_user_id' => 902903, 'nickname' => 'C', 'access_token' => '', 'token_locked' => true];

    /** API de mentira: guarda as chamadas e responde pelo roteiro dado. */
    private function api(array $payments = [], array $preference = ['_http_status' => 201, 'id' => 'pref-1', 'init_point' => 'https://mp.example/pagar/pref-1']): MercadopagoMarketplace {
        return new class($payments, $preference) extends MercadopagoMarketplace {
            /** @var list<string> */
            public array $calls = [];

            /** @var list<array<string, mixed>> */
            public array $payloads = [];

            public function __construct(private array $payments, private array $preference) {
                parent::__construct([]);
            }

            public function createPreference(array $payload, string $access_token): array {
                $this->calls[]    = 'preference ' . $access_token;
                $this->payloads[] = $payload;

                return $this->preference;
            }

            public function getPayment(int $payment_id, string $access_token): array {
                $this->calls[] = 'payment ' . $payment_id . ' ' . $access_token;

                return $this->payments[$access_token][$payment_id] ?? ['_http_status' => 404, 'message' => 'not found'];
            }
        };
    }

    /**
     * @param list<array<string, mixed>> $sellers
     * @param array<string, mixed>       $settings
     */
    private function method(array $sellers, array $settings = [], ?MercadopagoMarketplace $api = null, ?SecretBox $box = null, ?\Closure $onSettings = null): Mercadopago {
        return new Mercadopago(
            $box ?? new SecretBox(),
            static function () use ($settings, $onSettings): array {
                if ($onSettings !== null) {
                    $onSettings();
                }

                return $settings;
            },
            static fn (): array => array_values(array_filter($sellers, static fn (array $s): bool => true)),
            static function (int $user) use ($sellers): array {
                foreach ($sellers as $seller) {
                    if ($seller['mp_user_id'] === $user) {
                        return $seller;
                    }
                }

                return [];
            },
            $api ?? $this->api()
        );
    }

    private static function approved(int $id, string $reference = '41', string $status = 'approved'): array {
        return ['_http_status' => 200, 'id' => $id, 'status' => $status, 'external_reference' => $reference];
    }

    private static function v2(int $id, int|string|array|null $user = null, array $server = []): Notification {
        $body = ['type' => 'payment', 'data' => ['id' => (string)$id]];

        if ($user !== null) {
            $body['user_id'] = $user;
        }

        return new Notification(['type' => 'payment', 'data_id' => (string)$id], [], $server, (string)json_encode($body));
    }

    public function testCodeIsTheShortNameUsedInLogLines(): void {
        self::assertSame('mp', $this->method([])->code());
    }

    // ── pronto para cobrar? ─────────────────────────────────────────────────

    public function testReadyNeedsAnActiveSellerWhoseTokenOpens(): void {
        self::assertSame('no_seller', $this->method([])->blockedBy());
        self::assertSame('token_locked', $this->method([self::LOCKED])->blockedBy());
        self::assertSame('', $this->method([self::SELLER_A])->blockedBy());
    }

    public function testOnlyTheFirstActiveSellerCounts(): void {
        self::assertSame('', $this->method([self::SELLER_A, self::LOCKED])->blockedBy());
        self::assertSame('token_locked', $this->method([self::LOCKED, self::SELLER_A])->blockedBy(), 'o primeiro recebe; nao se pula para o seguinte');
    }

    // ── criar a cobranca ────────────────────────────────────────────────────

    public function testChargeCreatesOnePreferenceWithTheFirstSellersToken(): void {
        $api    = $this->api();
        $charge = $this->method([self::SELLER_A, self::SELLER_B], [], $api)
            ->charge(new ChargeRequest(41, 150.0, 'Order #41', 'https://loja.example/catalog/index.php?route=checkout/payment.notify'));

        self::assertTrue($charge->ok);
        self::assertSame('pref-1', $charge->reference);
        self::assertSame('https://mp.example/pagar/pref-1', $charge->url);
        self::assertSame(15.0, $charge->fee);
        self::assertSame(1, $charge->account);
        self::assertSame('BRL', $charge->currency);
        self::assertSame('', $charge->error);

        self::assertSame(['preference TOKEN-A'], $api->calls);
        self::assertSame([[
            'items' => [['title' => 'Order #41', 'quantity' => 1, 'currency_id' => 'BRL', 'unit_price' => 150.0]],
            'marketplace_fee'    => 15.0,
            'external_reference' => '41',
            'notification_url'   => 'https://loja.example/catalog/index.php?route=checkout/payment.notify',
        ]], $api->payloads);
    }

    /** @return iterable<string, array{array<string, mixed>, float}> */
    public static function fees(): iterable {
        yield 'sem configuracao: 10%' => [[], 8.05];
        yield '12,5 com virgula'      => [['mp_marketplace_fee_percent' => '12,5'], 10.06];
        yield '12.5 com ponto'        => [['mp_marketplace_fee_percent' => '12.5'], 10.06];
        yield 'zero'                  => [['mp_marketplace_fee_percent' => '0'], 0.0];
        yield 'texto vira zero'       => [['mp_marketplace_fee_percent' => 'abc'], 0.0];
        yield 'negativo vira zero'    => [['mp_marketplace_fee_percent' => '-5'], 0.0];
        yield 'acima de 100 vira 100' => [['mp_marketplace_fee_percent' => '250'], 80.5];
        yield 'cem'                   => [['mp_marketplace_fee_percent' => '100'], 80.5];
    }

    #[DataProvider('fees')]
    public function testPlatformFeeComesFromTheSettingClampedAndRounded(array $settings, float $expected): void {
        $api    = $this->api();
        $charge = $this->method([self::SELLER_A], $settings, $api)->charge(new ChargeRequest(42, 80.5, 't', 'https://loja.example/n'));

        self::assertSame($expected, $charge->fee);
        self::assertSame($expected, $api->payloads[0]['marketplace_fee']);
    }

    public function testSandboxAddressIsUsedOnlyWhenThereIsNoOther(): void {
        $only = $this->method([self::SELLER_A], [], $this->api([], ['_http_status' => 201, 'id' => 'p', 'sandbox_init_point' => 'https://sandbox.mp.example/p']))
            ->charge(new ChargeRequest(1, 1.0, 't', 'n'));
        $both = $this->method([self::SELLER_A], [], $this->api([], ['_http_status' => 201, 'id' => 'p', 'init_point' => 'https://mp.example/p', 'sandbox_init_point' => 'https://sandbox.mp.example/p']))
            ->charge(new ChargeRequest(1, 1.0, 't', 'n'));

        self::assertSame('https://sandbox.mp.example/p', $only->url);
        self::assertSame('https://mp.example/p', $both->url);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function refusals(): iterable {
        yield 'recusa com mensagem'   => [['_http_status' => 400, 'message' => 'unit_price invalid'], 'unit_price invalid'];
        yield 'erro 500'              => [['_http_status' => 500], 'Mercado Pago OAuth request returned HTTP 500.'];
        yield 'sem resposta'          => [['_http_status' => 0, '_curl_error' => 'timeout'], 'Mercado Pago OAuth request failed: timeout'];
        yield '201 sem identificador' => [['_http_status' => 201, 'init_point' => 'https://mp.example/p'], 'Mercado Pago OAuth request returned HTTP 201.'];
        yield '201 sem endereco'      => [['_http_status' => 201, 'id' => 'p'], 'Mercado Pago OAuth request returned HTTP 201.'];
        yield '199'                   => [['_http_status' => 199, 'id' => 'p', 'init_point' => 'https://mp.example/p'], 'Mercado Pago OAuth request returned HTTP 199.'];
        yield '300'                   => [['_http_status' => 300, 'id' => 'p', 'init_point' => 'https://mp.example/p'], 'Mercado Pago OAuth request returned HTTP 300.'];
    }

    #[DataProvider('refusals')]
    public function testChargeThatMercadoPagoDidNotCreateComesBackAsFailureWithItsMessage(array $preference, string $message): void {
        $charge = $this->method([self::SELLER_A], [], $this->api([], $preference))->charge(new ChargeRequest(1, 1.0, 't', 'n'));

        self::assertFalse($charge->ok);
        self::assertSame($message, $charge->error);
        self::assertSame('', $charge->url);
        self::assertSame('', $charge->reference);
    }

    public function testBoundaryStatusCodesAreAccepted(): void {
        foreach ([200, 299] as $http) {
            $charge = $this->method([self::SELLER_A], [], $this->api([], ['_http_status' => $http, 'id' => 'p', 'init_point' => 'https://mp.example/p']))
                ->charge(new ChargeRequest(1, 1.0, 't', 'n'));

            self::assertTrue($charge->ok, (string)$http);
        }
    }

    public function testChargeWithoutAUsableSellerCallsNoOne(): void {
        foreach ([[], [self::LOCKED]] as $sellers) {
            $api    = $this->api();
            $charge = $this->method($sellers, [], $api)->charge(new ChargeRequest(1, 1.0, 't', 'n'));

            self::assertFalse($charge->ok);
            self::assertSame([], $api->calls);
        }
    }

    // ── aviso: e de pagamento? ──────────────────────────────────────────────

    /** @return iterable<string, array{Notification}> */
    public static function notPayments(): iterable {
        yield 'vazio'                       => [new Notification()];
        yield 'merchant_order no endereco'  => [new Notification(['topic' => 'merchant_order', 'id' => '5001'])];
        yield 'tipo desconhecido no corpo'  => [new Notification([], [], [], '{"type":"plan","data":{"id":"5001"}}')];
        yield 'endereco manda no tipo'      => [new Notification(['topic' => 'merchant_order'], [], [], '{"type":"payment","data":{"id":"5001"}}')];
        yield 'numero zero'                 => [new Notification(['type' => 'payment', 'data_id' => '0'])];
        yield 'numero negativo'             => [new Notification(['type' => 'payment', 'data_id' => '-5'])];
        yield 'numero em texto'             => [new Notification(['type' => 'payment', 'data_id' => 'abc'])];
        yield 'numero como lista'           => [new Notification(['type' => 'payment', 'id' => ['5001']])];
        yield 'lista no formulario'         => [new Notification([], ['topic' => 'payment', 'id' => ['5001']])];
    }

    #[DataProvider('notPayments')]
    public function testNoticeThatIsNotAPaymentIsIgnoredWithoutReadingSettingsOrCallingAnyone(Notification $notification): void {
        $api  = $this->api();
        $read = 0;
        $sellers = 0;
        $method = new Mercadopago(
            new SecretBox(),
            static function () use (&$read): array { $read++; return []; },
            static function () use (&$sellers): array { $sellers++; return []; },
            static function () use (&$sellers): array { $sellers++; return []; },
            $api
        );

        $verdict = $method->notification($notification);

        self::assertSame(Verdict::ANSWER, $verdict->kind);
        self::assertSame('ignored', $verdict->label);
        self::assertSame('', $verdict->log);
        self::assertSame(0, $read, 'configuracoes nao lidas');
        self::assertSame(0, $sellers, 'vendedores nao lidos');
        self::assertSame([], $api->calls);
    }

    /** @return iterable<string, array{Notification, int}> */
    public static function paymentNumbers(): iterable {
        yield 'formato novo: data.id'        => [new Notification(['type' => 'payment', 'data.id' => '5001']), 5001];
        yield 'formato novo: data_id'        => [new Notification(['type' => 'payment', 'data_id' => '5001']), 5001];
        yield 'antigo por GET'               => [new Notification(['topic' => 'payment', 'id' => '5001']), 5001];
        yield 'antigo por POST'              => [new Notification([], ['topic' => 'payment', 'id' => '5001']), 5001];
        yield 'so no corpo'                  => [new Notification([], [], [], '{"type":"payment","data":{"id":"5001"}}'), 5001];
        yield 'corpo com topic'              => [new Notification([], [], [], '{"topic":"payment","data":{"id":5001}}'), 5001];
        yield 'sem tipo nenhum'              => [new Notification(['id' => '5001']), 5001];
        yield 'endereco antes do corpo'      => [new Notification(['type' => 'payment', 'data_id' => '5001'], [], [], '{"type":"payment","data":{"id":"7777"}}'), 5001];
        yield 'data.id antes de id'          => [new Notification(['type' => 'payment', 'data.id' => '5001', 'id' => '7777']), 5001];
        yield 'GET antes de POST'            => [new Notification(['id' => '5001'], ['id' => '7777']), 5001];
        yield 'pula fonte invalida'          => [new Notification(['type' => 'payment', 'data_id' => 'abc', 'id' => '5001']), 5001];
        yield 'pula fonte negativa'          => [new Notification(['type' => 'payment', 'data_id' => '-5', 'id' => '5001']), 5001];
        yield 'tipo em lista cai no corpo'   => [new Notification(['type' => ['payment'], 'data_id' => '5001'], [], [], '{"type":"payment"}'), 5001];
        yield 'corpo que nao e JSON'         => [new Notification(['type' => 'payment', 'data_id' => '5001'], [], [], '{{{'), 5001];
        yield 'corpo JSON que nao e objeto'  => [new Notification(['type' => 'payment', 'data_id' => '5001'], [], [], '7777'), 5001];
        yield 'numero com casas'             => [new Notification(['type' => 'payment', 'data_id' => '5001.9']), 5001];
    }

    #[DataProvider('paymentNumbers')]
    public function testPaymentNumberIsFoundInBothNoticeFormats(Notification $notification, int $expected): void {
        $api = $this->api(['TOKEN-A' => [$expected => self::approved($expected)]]);

        $verdict = $this->method([self::SELLER_A], [], $api)->notification($notification);

        self::assertSame(Verdict::CONFIRMED, $verdict->kind);
        self::assertSame((string)$expected, $verdict->paymentId);
        self::assertSame(['payment ' . $expected . ' TOKEN-A'], $api->calls);
    }

    // ── aviso: assinatura ───────────────────────────────────────────────────

    public function testSignatureSecretThatDoesNotOpenAsksForRetryBeforeAnythingElse(): void {
        $sealed = (new SecretBox(SecretBox::generateKey()))->seal('assinatura-0123456789abcdef', SecretBox::settingContext('mp_webhook_secret'));
        $api    = $this->api(['TOKEN-A' => [5001 => self::approved(5001)]]);

        foreach ([new SecretBox(SecretBox::generateKey()), new SecretBox()] as $box) {
            $verdict = $this->method([self::SELLER_A], ['mp_webhook_secret' => $sealed], $api, $box)->notification(self::v2(5001, 902901));

            self::assertSame(Verdict::RETRY, $verdict->kind);
            self::assertSame('secret-locked', $verdict->label);
            self::assertSame('o segredo de assinatura gravado não abre com a REAMUR_SECRET_KEY do catalog/config.php (payment 5001); notificação devolvida para reenvio', $verdict->log);
        }

        self::assertSame([], $api->calls);
    }

    public function testSignatureIsCheckedWithTheOpenedSecretBeforeCallingMercadoPago(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $secret = 'assinatura-0123456789abcdef';
        $sealed = $box->seal($secret, SecretBox::settingContext('mp_webhook_secret'));
        $good   = hash_hmac('sha256', 'id:5001;request-id:req-1;ts:1700000000;', $secret);
        $api    = $this->api(['TOKEN-A' => [5001 => self::approved(5001)]]);
        $method = $this->method([self::SELLER_A], ['mp_webhook_secret' => $sealed], $api, $box);

        $wrong = $method->notification(self::v2(5001, 902901, ['HTTP_X_SIGNATURE' => 'ts=1700000000,v1=' . str_repeat('a', 64), 'HTTP_X_REQUEST_ID' => 'req-1']));
        self::assertSame(Verdict::ANSWER, $wrong->kind);
        self::assertSame('signature-rejected', $wrong->label);
        self::assertSame('assinatura rejeitada (payment 5001)', $wrong->log);

        $missing = $method->notification(self::v2(5001, 902901));
        self::assertSame('signature-rejected', $missing->label);

        // Assinar com o texto cifrado como se fosse o segredo nao passa.
        $withCipher = hash_hmac('sha256', 'id:5001;request-id:req-1;ts:1700000000;', $sealed);
        self::assertSame('signature-rejected', $method->notification(self::v2(5001, 902901, ['HTTP_X_SIGNATURE' => 'ts=1700000000,v1=' . $withCipher, 'HTTP_X_REQUEST_ID' => 'req-1']))->label);

        self::assertSame([], $api->calls, 'nenhuma chamada antes de a assinatura conferir');

        $right = $method->notification(self::v2(5001, 902901, ['HTTP_X_SIGNATURE' => 'ts=1700000000,v1=' . $good, 'HTTP_X_REQUEST_ID' => 'req-1']));
        self::assertSame(Verdict::CONFIRMED, $right->kind);
        self::assertSame(['payment 5001 TOKEN-A'], $api->calls);
    }

    public function testSignatureCoversThePaymentNumberOfTheNotice(): void {
        $secret = 'assinatura-0123456789abcdef';
        $forOther = hash_hmac('sha256', 'id:7777;request-id:req-1;ts:1700000000;', $secret);
        $api    = $this->api(['TOKEN-A' => [5001 => self::approved(5001)]]);

        $verdict = $this->method([self::SELLER_A], ['mp_webhook_secret' => $secret], $api)
            ->notification(self::v2(5001, 902901, ['HTTP_X_SIGNATURE' => 'ts=1700000000,v1=' . $forOther, 'HTTP_X_REQUEST_ID' => 'req-1']));

        self::assertSame('signature-rejected', $verdict->label);
        self::assertSame([], $api->calls);
    }

    public function testWithoutASignatureSecretTheConsultationIsTheProof(): void {
        $api = $this->api(['TOKEN-A' => [5001 => self::approved(5001)]]);

        $verdict = $this->method([self::SELLER_A], ['mp_webhook_secret' => ''], $api)
            ->notification(self::v2(5001, 902901, ['HTTP_X_SIGNATURE' => 'ts=1,v1=00', 'HTTP_X_REQUEST_ID' => 'x']));

        self::assertSame(Verdict::CONFIRMED, $verdict->kind);
    }

    public function testSignatureRuleItself(): void {
        $secret = 'segredo';
        $sig    = hash_hmac('sha256', 'id:987;request-id:r;ts:1700000000;', $secret);

        self::assertTrue(Mercadopago::signatureValid($secret, 987, "ts=1700000000,v1=$sig", 'r'));
        self::assertTrue(Mercadopago::signatureValid($secret, 987, 'ts=1700000000, v1=' . strtoupper($sig), 'r'));
        self::assertTrue(Mercadopago::signatureValid('', 987, '', ''));
        self::assertFalse(Mercadopago::signatureValid($secret, 987, '', 'r'));
        self::assertFalse(Mercadopago::signatureValid($secret, 0, "ts=1700000000,v1=$sig", 'r'));
        self::assertFalse(Mercadopago::signatureValid($secret, 987, "ts=abc,v1=$sig", 'r'));
        self::assertFalse(Mercadopago::signatureValid($secret, 987, 'ts=1700000000,v1=curto', 'r'));
        self::assertFalse(Mercadopago::signatureValid($secret, 987, 'ts=1700000000,v1=' . str_repeat('z', 64), 'r'));
        self::assertFalse(Mercadopago::signatureValid($secret, 987, "ts=1700000000,v1=$sig", "r\r\nx"));
        self::assertFalse(Mercadopago::signatureValid($secret, 987, "ts=1700000001,v1=$sig", 'r'));
        self::assertFalse(Mercadopago::signatureValid($secret, 987, "ts=1700000000,v1=$sig", 'outro'));
        self::assertFalse(Mercadopago::signatureValid('outro', 987, "ts=1700000000,v1=$sig", 'r'));
    }

    public function testSignatureThatMatchesButBreaksTheFormatIsStillRefused(): void {
        $secret = 'segredo';
        $sign   = static fn (string $id, string $rid, string $ts): string => hash_hmac('sha256', "id:$id;request-id:$rid;ts:$ts;", $secret);

        // Cada assinatura abaixo CONFERE com o texto assinado; o que a derruba e o formato.
        self::assertFalse(Mercadopago::signatureValid($secret, 0, 'ts=1700000000,v1=' . $sign('0', 'r', '1700000000'), 'r'), 'pagamento zero');
        self::assertFalse(Mercadopago::signatureValid($secret, -5, 'ts=1700000000,v1=' . $sign('-5', 'r', '1700000000'), 'r'), 'pagamento negativo');
        self::assertFalse(Mercadopago::signatureValid($secret, 987, 'ts=1700000000,v1=' . $sign('987', "r\r\nx", '1700000000'), "r\r\nx"), 'quebra de linha no request-id');
        self::assertFalse(Mercadopago::signatureValid($secret, 987, 'ts=1700000000,v1=' . $sign('987', "r\0", '1700000000'), "r\0"), 'byte nulo no request-id');
        self::assertFalse(Mercadopago::signatureValid($secret, 987, 'ts=abc,v1=' . $sign('987', 'r', 'abc'), 'r'), 'ts que nao e numero');
        self::assertFalse(Mercadopago::signatureValid($secret, 987, 'ts=-1,v1=' . $sign('987', 'r', '-1'), 'r'), 'ts negativo');
        self::assertFalse(Mercadopago::signatureValid($secret, 987, 'v1=' . $sign('987', 'r', ''), 'r'), 'sem ts');

        // E o mesmo texto, com o formato certo, passa.
        self::assertTrue(Mercadopago::signatureValid($secret, 987, 'ts=1700000000,v1=' . $sign('987', 'r', '1700000000'), 'r'));
    }

    // ── aviso: de quem e o pagamento ────────────────────────────────────────

    public function testSellerNamedInTheNoticeIsAskedFirst(): void {
        $api = $this->api(['TOKEN-B' => [6001 => self::approved(6001)]]);

        $verdict = $this->method([self::SELLER_A, self::SELLER_B], [], $api)->notification(self::v2(6001, 902902));

        self::assertSame(Verdict::CONFIRMED, $verdict->kind);
        self::assertSame(['payment 6001 TOKEN-B'], $api->calls, 'direto no vendedor do aviso');
    }

    public function testWhenTheNamedSellerDoesNotHaveItTheOthersAreAskedOnce(): void {
        $api = $this->api(['TOKEN-B' => [6002 => self::approved(6002, '42', 'rejected')]]);

        $verdict = $this->method([self::SELLER_A, self::SELLER_B], [], $api)->notification(self::v2(6002, 902901));

        self::assertSame(Verdict::CONFIRMED, $verdict->kind);
        self::assertSame(42, $verdict->orderId);
        self::assertSame('rejected', $verdict->status);
        self::assertSame(['payment 6002 TOKEN-A', 'payment 6002 TOKEN-B'], $api->calls, 'o vendedor do aviso nao e consultado duas vezes');
    }

    /** @return iterable<string, array{int|string|array<int, int>|null}> */
    public static function unknownUsers(): iterable {
        yield 'sem vendedor no aviso' => [null];
        yield 'vendedor desconhecido' => [123];
        yield 'vendedor zero'         => [0];
        yield 'vendedor como lista'   => [[902902]];
        yield 'vendedor em texto'     => ['abc'];
    }

    #[DataProvider('unknownUsers')]
    public function testWithoutAKnownSellerEveryActiveOneIsAskedInOrderUntilFound(int|string|array|null $user): void {
        $api = $this->api(['TOKEN-B' => [6001 => self::approved(6001)]]);

        $verdict = $this->method([self::SELLER_A, self::SELLER_B], [], $api)->notification(self::v2(6001, $user));

        self::assertSame(Verdict::CONFIRMED, $verdict->kind);
        self::assertSame(['payment 6001 TOKEN-A', 'payment 6001 TOKEN-B'], $api->calls);
    }

    public function testSearchStopsAtTheFirstSellerThatHasThePayment(): void {
        $api = $this->api(['TOKEN-A' => [5001 => self::approved(5001)], 'TOKEN-B' => [5001 => self::approved(5001, '99')]]);

        $verdict = $this->method([self::SELLER_A, self::SELLER_B], [], $api)->notification(self::v2(5001));

        self::assertSame(41, $verdict->orderId);
        self::assertSame(['payment 5001 TOKEN-A'], $api->calls);
    }

    public function testUserIdAsNumericTextIsAccepted(): void {
        $api = $this->api(['TOKEN-B' => [6001 => self::approved(6001)]]);

        $this->method([self::SELLER_A, self::SELLER_B], [], $api)->notification(self::v2(6001, '902902'));

        self::assertSame(['payment 6001 TOKEN-B'], $api->calls);
    }

    public function testSellerWhoseTokenDoesNotOpenIsNeverAskedAndTheNoticeIsSentBack(): void {
        $api = $this->api();

        foreach ([902903, null] as $user) {
            $verdict = $this->method([self::LOCKED], [], $api)->notification(self::v2(5001, $user));

            self::assertSame(Verdict::RETRY, $verdict->kind);
            self::assertSame('token-locked', $verdict->label);
            self::assertSame('token de vendedor gravado não abre com a REAMUR_SECRET_KEY do catalog/config.php (payment 5001); notificação devolvida para reenvio', $verdict->log);
        }

        self::assertSame([], $api->calls, 'token vazio nao e enviado a ninguem');
    }

    public function testLockedSellerDoesNotHideAPaymentFoundInAnother(): void {
        $api = $this->api(['TOKEN-A' => [5001 => self::approved(5001)]]);

        foreach ([902903, null] as $user) {
            $verdict = $this->method([self::LOCKED, self::SELLER_A], [], $api)->notification(self::v2(5001, $user));

            self::assertSame(Verdict::CONFIRMED, $verdict->kind);
        }
    }

    public function testLockedSellerPlusPaymentNotFoundElsewhereAsksForRetry(): void {
        $verdict = $this->method([self::SELLER_A, self::LOCKED], [], $this->api())->notification(self::v2(5999));

        self::assertSame(Verdict::RETRY, $verdict->kind);
        self::assertSame('token-locked', $verdict->label);
    }

    public function testPaymentNobodyHasIsNotOurs(): void {
        $api = $this->api();

        $verdict = $this->method([self::SELLER_A, self::SELLER_B], [], $api)->notification(self::v2(5999, 902901));

        self::assertSame(Verdict::ANSWER, $verdict->kind);
        self::assertSame('not-ours', $verdict->label);
        self::assertSame('payment 5999 não confirmado em nenhum seller ativo', $verdict->log);
        self::assertSame(['payment 5999 TOKEN-A', 'payment 5999 TOKEN-B'], $api->calls);
    }

    public function testWithoutSellersNothingIsOurs(): void {
        self::assertSame('not-ours', $this->method([])->notification(self::v2(5001, 902901))->label);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function notProof(): iterable {
        yield 'erro 500'         => [['_http_status' => 500, 'id' => 5001, 'status' => 'approved', 'external_reference' => '41']];
        yield '199'              => [['_http_status' => 199, 'id' => 5001, 'status' => 'approved', 'external_reference' => '41']];
        yield '300'              => [['_http_status' => 300, 'id' => 5001, 'status' => 'approved', 'external_reference' => '41']];
        yield 'sem status http'  => [['id' => 5001, 'status' => 'approved', 'external_reference' => '41']];
        yield 'sem id'           => [['_http_status' => 200, 'status' => 'approved', 'external_reference' => '41']];
        yield 'sem situacao'     => [['_http_status' => 200, 'id' => 5001, 'external_reference' => '41']];
        yield 'situacao vazia'   => [['_http_status' => 200, 'id' => 5001, 'status' => '', 'external_reference' => '41']];
    }

    #[DataProvider('notProof')]
    public function testOnlyAWellFormedSuccessAnswerCountsAsProof(array $answer): void {
        $verdict = $this->method([self::SELLER_A], [], $this->api(['TOKEN-A' => [5001 => $answer]]))->notification(self::v2(5001, 902901));

        self::assertSame('not-ours', $verdict->label);
    }

    public function testBodyOfTheNoticeIsNeverTheProof(): void {
        // O aviso "diz" aprovado e traz pedido; o Mercado Pago diz outra coisa.
        $body = (string)json_encode(['type' => 'payment', 'data' => ['id' => '5002'], 'user_id' => 902901, 'status' => 'approved', 'external_reference' => '41']);
        $api  = $this->api(['TOKEN-A' => [5002 => self::approved(5002, '42', 'rejected')]]);

        $verdict = $this->method([self::SELLER_A], [], $api)->notification(new Notification(['type' => 'payment', 'data_id' => '5002'], [], [], $body));

        self::assertSame(42, $verdict->orderId);
        self::assertSame('rejected', $verdict->status);
    }

    // ── aviso: de qual pedido ───────────────────────────────────────────────

    /** @return iterable<string, array{mixed}> */
    public static function noReference(): iterable {
        yield 'vazio'    => [''];
        yield 'texto'    => ['abc'];
        yield 'zero'     => ['0'];
        yield 'negativo' => ['-41'];
    }

    #[DataProvider('noReference')]
    public function testPaymentWithoutAnOrderNumberIsAnsweredAndLogged(mixed $reference): void {
        $api = $this->api(['TOKEN-A' => [5006 => self::approved(5006, $reference)]]);

        $verdict = $this->method([self::SELLER_A], [], $api)->notification(self::v2(5006, 902901));

        self::assertSame(Verdict::ANSWER, $verdict->kind);
        self::assertSame('no-reference', $verdict->label);
        self::assertSame('payment 5006 sem external_reference', $verdict->log);
    }

    public function testConfirmedVerdictCarriesOrderPaymentAndTheSituationAsMercadoPagoSaysIt(): void {
        foreach (['approved', 'rejected', 'cancelled', 'pending', 'in_process', 'refunded', 'charged_back'] as $status) {
            $api     = $this->api(['TOKEN-A' => [5001 => self::approved(5001, '41', $status)]]);
            $verdict = $this->method([self::SELLER_A], [], $api)->notification(self::v2(5001, 902901));

            self::assertSame(Verdict::CONFIRMED, $verdict->kind);
            self::assertSame(41, $verdict->orderId);
            self::assertSame('5001', $verdict->paymentId);
            self::assertSame($status, $verdict->status);
            self::assertSame('', $verdict->log);
        }
    }

    // ── cliente da API ──────────────────────────────────────────────────────

    public function testApiClientOpensTheApplicationSecretOnlyAtThePointOfUse(): void {
        $box    = new SecretBox(SecretBox::generateKey());
        $sealed = $box->seal('segredo-do-aplicativo-0123456789', SecretBox::settingContext('mp_client_secret'));

        $client = Mercadopago::client(['mp_app_id' => '1234567890', 'mp_client_secret' => $sealed, 'mp_redirect_uri' => 'https://loja.example/admin/callback'], $box);

        self::assertSame('1234567890', $client->appId());
        self::assertSame('segredo-do-aplicativo-0123456789', $client->clientSecret());
        self::assertSame('https://loja.example/admin/callback', $client->redirectUri());
        self::assertTrue($client->isConfigured());

        // Chave errada: o texto cifrado nunca e usado como se fosse o segredo.
        $other = Mercadopago::client(['mp_app_id' => '1234567890', 'mp_client_secret' => $sealed], new SecretBox(SecretBox::generateKey()));
        self::assertSame('', $other->clientSecret());

        self::assertFalse(Mercadopago::client([], new SecretBox())->isConfigured());
    }

    public function testSettingsAreReadOnceAndOnlyWhenNeeded(): void {
        $read   = 0;
        $api    = $this->api(['TOKEN-A' => [5001 => self::approved(5001)]]);
        $method = $this->method([self::SELLER_A], [], $api, null, static function () use (&$read): void {
            $read++;
        });

        self::assertSame('', $method->blockedBy());
        self::assertSame(0, $read, 'saber se ha vendedor nao precisa das configuracoes');

        $method->charge(new ChargeRequest(1, 1.0, 't', 'n'));
        $method->notification(self::v2(5001, 902901));
        $method->notification(self::v2(5001, 902901));

        self::assertSame(1, $read);
    }
}
