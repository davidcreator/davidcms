<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

require_once __DIR__ . '/MemoryLedger.php';
require_once __DIR__ . '/FictitiousMethod.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\Charge;
use Reamur\System\Library\Payment\Checkout;
use Reamur\System\Library\Payment\Link;
use Reamur\System\Library\Payment\Notification;
use Reamur\System\Library\Payment\Reply;
use Reamur\System\Library\Payment\Verdict;

/**
 * EX-03 (ADR 0097): as regras de pagamento que valem para qualquer meio.
 * Todos os testes usam um meio FICTICIO (FictitiousMethod), diferente do
 * Mercado Pago em tudo o que da para ser diferente: se as regras funcionam
 * com ele, o contrato nao depende do Mercado Pago.
 */
final class CheckoutTest extends TestCase {
    private MemoryLedger $ledger;
    private FictitiousMethod $method;
    private Checkout $checkout;

    /** @var list<string> */
    private array $log = [];

    protected function setUp(): void {
        $this->ledger   = new MemoryLedger();
        $this->method   = new FictitiousMethod();
        $this->log      = [];
        $this->checkout = new Checkout($this->ledger, function (string $line): void {
            $this->log[] = $line;
        });
    }

    private function link(int $order = 41, float $total = 150.0): Link {
        return $this->checkout->link($this->method, $order, $total, 'Pedido ' . $order, 'https://loja.example/avisar');
    }

    private function notify(Notification $notification): Reply {
        return $this->checkout->notify($this->method, $notification, static fn (string $payment, string $status): string => "Caixa: $payment / $status");
    }

    // ── gerar o link ────────────────────────────────────────────────────────

    public function testNewLinkCreatesOneChargeAndStoresItWithARandomKey(): void {
        $link = $this->link(41, 150.0);

        self::assertSame(Link::CREATED, $link->state);
        self::assertMatchesRegularExpression('/^[0-9a-f]{48}$/D', $link->token);
        self::assertCount(1, $this->method->requests);

        $request = $this->method->requests[0];
        self::assertSame(41, $request->orderId);
        self::assertSame(150.0, $request->amount);
        self::assertSame('Pedido 41', $request->title);
        self::assertSame('https://loja.example/avisar', $request->notifyUrl);

        self::assertCount(1, $this->ledger->charges);
        $stored = $this->ledger->charges[0];
        self::assertSame($link->token, $stored['token']);
        self::assertSame(41, $stored['order_id']);
        self::assertSame(150.0, $stored['amount']);
        self::assertSame('ch_41', $stored['reference']);
        self::assertSame('https://caixa.example/pagar/ch_41', $stored['url']);
        self::assertSame('USD', $stored['currency']);
        self::assertSame(0.0, $stored['fee']);
        self::assertSame(0, $stored['account']);
        self::assertSame('pending', $stored['status']);
    }

    public function testKeyIsDrawnAgainForEveryChargeAndNeverComesFromTheOrderNumber(): void {
        $tokens = [];

        foreach ([41, 42, 43, 44, 45] as $order) {
            $tokens[] = $this->link($order)->token;
        }

        self::assertCount(5, array_unique($tokens));

        // Mesmo pedido, depois de recusado: chave nova, nao a anterior.
        $this->ledger->charges[0]['status'] = 'rejected';
        $again = $this->link(41);
        self::assertSame(Link::CREATED, $again->state);
        self::assertNotSame($tokens[0], $again->token);
    }

    public function testPendingLinkIsReusedWithoutAskingTheProviderAgain(): void {
        $first  = $this->link();
        $second = $this->link();

        self::assertSame(Link::REUSED, $second->state);
        self::assertSame($first->token, $second->token);
        self::assertCount(1, $this->method->requests, 'nenhuma cobranca nova no provedor');
        self::assertCount(1, $this->ledger->charges);
    }

    public function testPendingChargeWithoutAddressIsNotReused(): void {
        $this->ledger->seed(['order_id' => 41, 'url' => '']);

        $link = $this->link();

        self::assertSame(Link::CREATED, $link->state);
        self::assertCount(1, $this->method->requests);
        self::assertCount(2, $this->ledger->charges);
    }

    public function testPaidOrderGetsNoNewLink(): void {
        $this->ledger->seed(['order_id' => 41, 'status' => 'approved']);

        $link = $this->link();

        self::assertSame(Link::PAID, $link->state);
        self::assertSame('', $link->token);
        self::assertSame([], $this->method->requests);
        self::assertCount(1, $this->ledger->charges);
    }

    /** @return iterable<string, array{string}> */
    public static function reopenable(): iterable {
        yield 'recusada'   => ['rejected'];
        yield 'cancelada'  => ['cancelled'];
        yield 'devolvida'  => ['refunded'];
        yield 'em analise' => ['in_process'];
    }

    #[DataProvider('reopenable')]
    public function testOtherSituationsGetANewCharge(string $status): void {
        $this->ledger->seed(['order_id' => 41, 'status' => $status]);

        $link = $this->link();

        self::assertSame(Link::CREATED, $link->state);
        self::assertNotSame(str_repeat('a', 48), $link->token);
        self::assertCount(2, $this->ledger->charges);
    }

    public function testOnlyTheLatestChargeOfTheOrderCounts(): void {
        $this->ledger->seed(['order_id' => 41, 'status' => 'approved']);
        $this->ledger->seed(['order_id' => 41, 'status' => 'rejected', 'token' => str_repeat('b', 48)]);
        $this->ledger->seed(['order_id' => 77, 'status' => 'approved', 'token' => str_repeat('c', 48)]);

        self::assertSame(Link::CREATED, $this->link(41)->state, 'a ultima do pedido 41 foi recusada');
    }

    public function testMethodThatIsNotReadyIsNotAskedToCharge(): void {
        $this->method->blocked = 'sem_conta';

        $link = $this->link();

        self::assertSame(Link::BLOCKED, $link->state);
        self::assertSame('sem_conta', $link->detail);
        self::assertSame('', $link->token);
        self::assertSame([], $this->method->requests);
        self::assertSame([], $this->ledger->charges);
    }

    /** @return iterable<string, array{float}> */
    public static function noTotal(): iterable {
        yield 'zero'               => [0.0];
        yield 'negativo'           => [-10.0];
        yield 'menos de um centavo' => [0.004];
    }

    #[DataProvider('noTotal')]
    public function testOrderWithoutValueIsNotCharged(float $total): void {
        $link = $this->link(41, $total);

        self::assertSame(Link::NO_TOTAL, $link->state);
        self::assertSame([], $this->method->requests);
        self::assertSame([], $this->ledger->charges);
    }

    public function testAmountIsRoundedToCentsBeforeChargingAndStoring(): void {
        $this->link(41, 33.336);
        $this->link(42, 0.005);

        self::assertSame(33.34, $this->method->requests[0]->amount);
        self::assertSame(33.34, $this->ledger->charges[0]['amount']);
        self::assertSame(0.01, $this->method->requests[1]->amount);
    }

    public function testRulesAreAppliedInAFixedOrder(): void {
        // Ja pago vem antes de "meio nao esta pronto" e de "sem valor".
        $this->method->blocked = 'sem_conta';
        $this->ledger->seed(['order_id' => 41, 'status' => 'approved']);
        self::assertSame(Link::PAID, $this->link(41, 0.0)->state);

        // Link pendente e devolvido mesmo com o meio parado.
        $this->ledger->seed(['order_id' => 42, 'token' => str_repeat('d', 48)]);
        self::assertSame(Link::REUSED, $this->link(42, 0.0)->state);

        // Meio parado vem antes de "sem valor".
        self::assertSame(Link::BLOCKED, $this->link(43, 0.0)->state);
    }

    public function testProviderRefusalIsReportedAndNothingIsStored(): void {
        $this->method->nextCharge = Charge::failed('cartao de teste recusado');

        $link = $this->link();

        self::assertSame(Link::FAILED, $link->state);
        self::assertSame('cartao de teste recusado', $link->detail);
        self::assertSame('', $link->token);
        self::assertSame([], $this->ledger->charges);
    }

    /** @return iterable<string, array{Charge}> */
    public static function incomplete(): iterable {
        yield 'sem endereco'      => [Charge::created('ch_1', '')];
        yield 'sem identificador' => [Charge::created('', 'https://caixa.example/pagar/1')];
    }

    #[DataProvider('incomplete')]
    public function testChargeWithoutAddressOrIdentifierIsNeverStored(Charge $charge): void {
        $this->method->nextCharge = $charge;

        self::assertSame(Link::FAILED, $this->link()->state);
        self::assertSame([], $this->ledger->charges);
    }

    // ── o cliente abre o link ───────────────────────────────────────────────

    public function testValidKeyLeadsToTheProviderAddress(): void {
        $link = $this->link();

        self::assertSame('https://caixa.example/pagar/ch_41', $this->checkout->target($link->token));
    }

    public function testUnknownKeyLeadsNowhere(): void {
        $this->link();

        self::assertSame('', $this->checkout->target(str_repeat('0', 48)));
        self::assertSame('', $this->checkout->target(''));
    }

    /** @return iterable<string, array{string, string}> */
    public static function deadLinks(): iterable {
        yield 'pago'                  => ['approved', 'https://caixa.example/pagar/1'];
        yield 'recusado'              => ['rejected', 'https://caixa.example/pagar/1'];
        yield 'cancelado'             => ['cancelled', 'https://caixa.example/pagar/1'];
        yield 'endereco http'         => ['pending', 'http://caixa.example/pagar/1'];
        yield 'endereco vazio'        => ['pending', ''];
        yield 'outro esquema'         => ['pending', 'javascript:alert(1)'];
        yield 'https sem dominio'     => ['pending', 'https://'];
        yield 'quebra de linha'       => ['pending', "https://caixa.example/pagar/1\r\nSet-Cookie: x=1"];
        yield 'byte nulo'             => ['pending', "https://caixa.example/pagar/1\0"];
        yield 'https no meio'         => ['pending', 'data:text/html,https://caixa.example'];
    }

    #[DataProvider('deadLinks')]
    public function testLinkStopsWorkingWhenClosedOrWhenTheAddressIsNotHttps(string $status, string $url): void {
        $this->ledger->seed(['status' => $status, 'url' => $url]);

        self::assertSame('', $this->checkout->target(str_repeat('a', 48)));
    }

    #[DataProvider('reopenableOrPending')]
    public function testLinkKeepsWorkingInTheOtherSituations(string $status): void {
        $this->ledger->seed(['status' => $status]);

        self::assertSame('https://pay.example/c/1', $this->checkout->target(str_repeat('a', 48)));
    }

    /** @return iterable<string, array{string}> */
    public static function reopenableOrPending(): iterable {
        yield 'pendente'   => ['pending'];
        yield 'em analise' => ['in_process'];
        yield 'devolvido'  => ['refunded'];
    }

    // ── aviso do provedor ───────────────────────────────────────────────────

    public function testConfirmedPaymentMovesTheOrderWritesHistoryAndReportsThePaidOrder(): void {
        $this->link(41);

        $reply = $this->notify(FictitiousMethod::signed('pay_ok'));

        self::assertFalse($reply->retry);
        self::assertSame('ok:processed', $reply->body);
        self::assertSame(41, $reply->paidOrder);
        self::assertSame('approved', $this->ledger->charges[0]['status']);
        self::assertSame('pay_ok', $this->ledger->charges[0]['payment_id']);
        self::assertSame([['order' => 41, 'status' => 4, 'comment' => 'Caixa: pay_ok / approved']], $this->ledger->orders);
        self::assertSame([], $this->log);
    }

    /** @return iterable<string, array{string, int, string, int}> */
    public static function closing(): iterable {
        yield 'recusado'  => ['pay_declined', 42, 'rejected', 6];
        yield 'cancelado' => ['pay_void', 43, 'cancelled', 6];
    }

    #[DataProvider('closing')]
    public function testRefusedOrCancelledPaymentCancelsTheOrderAndIsNotReportedAsPaid(string $payment, int $order, string $status, int $orderStatus): void {
        $this->link($order);

        $reply = $this->notify(FictitiousMethod::signed($payment));

        self::assertSame('ok:processed', $reply->body);
        self::assertSame(0, $reply->paidOrder);
        self::assertSame($status, $this->ledger->charges[0]['status']);
        self::assertSame([['order' => $order, 'status' => $orderStatus, 'comment' => "Caixa: $payment / $status"]], $this->ledger->orders);
    }

    public function testAnyOtherSituationIsOnlyRecordedOnTheCharge(): void {
        $this->link(44);

        $reply = $this->notify(FictitiousMethod::signed('pay_review'));

        self::assertSame('ok:processed', $reply->body);
        self::assertSame(0, $reply->paidOrder);
        self::assertSame('under_review', $this->ledger->charges[0]['status']);
        self::assertSame('pay_review', $this->ledger->charges[0]['payment_id']);
        self::assertSame([], $this->ledger->orders, 'pedido e historico intocados');
    }

    public function testRepeatedNoticeChangesNothingAndIsNotReportedAsPaidAgain(): void {
        $this->link(41);
        $this->notify(FictitiousMethod::signed('pay_ok'));
        $this->ledger->calls = [];

        $reply = $this->notify(FictitiousMethod::signed('pay_ok'));

        self::assertSame('ok:already-processed', $reply->body);
        self::assertFalse($reply->retry);
        self::assertSame(0, $reply->paidOrder, 'o evento de pedido pago nao sai duas vezes');
        self::assertSame(['latest 41'], $this->ledger->calls, 'so leitura');
        self::assertCount(1, $this->ledger->orders);
    }

    public function testSamePaymentWithANewSituationIsNotARepeat(): void {
        $this->link(41);
        $this->notify(FictitiousMethod::signed('pay_ok'));

        $this->method->provider['pay_ok'] = [41, 'VOIDED'];
        $reply = $this->notify(FictitiousMethod::signed('pay_ok'));

        self::assertSame('ok:processed', $reply->body);
        self::assertSame(0, $reply->paidOrder);
        self::assertSame('cancelled', $this->ledger->charges[0]['status']);
        self::assertSame([4, 6], array_column($this->ledger->orders, 'status'));
    }

    public function testAnotherPaymentWithTheSameSituationIsNotARepeat(): void {
        $this->link(41);
        $this->notify(FictitiousMethod::signed('pay_ok'));

        $this->method->provider['pay_two'] = [41, 'PAID'];
        $reply = $this->notify(FictitiousMethod::signed('pay_two'));

        self::assertSame('ok:processed', $reply->body);
        self::assertSame(41, $reply->paidOrder);
        self::assertSame('pay_two', $this->ledger->charges[0]['payment_id']);
        self::assertCount(2, $this->ledger->orders);
    }

    public function testNoticeGoesToTheLatestChargeOfTheOrder(): void {
        $this->ledger->seed(['id' => 1, 'order_id' => 41, 'status' => 'rejected', 'payment_id' => 'pay_old']);
        $this->ledger->seed(['id' => 2, 'order_id' => 41, 'token' => str_repeat('b', 48)]);

        $this->notify(FictitiousMethod::signed('pay_ok'));

        self::assertSame('rejected', $this->ledger->charges[0]['status']);
        self::assertSame('approved', $this->ledger->charges[1]['status']);
    }

    public function testConfirmedPaymentOfAnOrderWithoutChargeIsLoggedAndAnswered(): void {
        $reply = $this->notify(FictitiousMethod::signed('pay_lost'));

        self::assertSame('ok:no-local-record', $reply->body);
        self::assertFalse($reply->retry);
        self::assertSame(0, $reply->paidOrder);
        self::assertSame(['caixa notify: pedido 999 (payment pay_lost) sem lançamento local'], $this->log);
        self::assertSame([], $this->ledger->orders);
    }

    public function testChargeWithoutOrderIsRecordedButNoOrderIsTouched(): void {
        $this->ledger->seed(['order_id' => 41]);
        // Linha que chega sem numero de pedido: a cobranca e anotada, mas nao ha pedido a mexer.
        $odd = new class($this->ledger) implements \Reamur\System\Library\Payment\Ledger {
            public function __construct(private MemoryLedger $inner) {}
            public function latestForOrder(int $orderId): array { return ['order_id' => 0] + $this->inner->latestForOrder($orderId); }
            public function byToken(string $token): array { return $this->inner->byToken($token); }
            public function add(int $orderId, string $token, float $amount, Charge $charge): void {}
            public function record(int $id, string $paymentId, string $status): void { $this->inner->record($id, $paymentId, $status); }
            public function moveOrder(int $orderId, int $orderStatusId, string $comment): void { $this->inner->moveOrder($orderId, $orderStatusId, $comment); }
        };

        $reply = (new Checkout($odd))->notify($this->method, FictitiousMethod::signed('pay_ok'), static fn (): string => 'x');

        self::assertSame('ok:processed', $reply->body);
        self::assertSame('approved', $this->ledger->charges[0]['status']);
        self::assertSame([], $this->ledger->orders);
    }

    public function testNoticeThatIsNotAPaymentIsAnsweredWithoutTouchingAnything(): void {
        $this->link(41);
        $this->ledger->calls = [];

        $reply = $this->notify(FictitiousMethod::signed('pay_ok', 'customer.created'));

        self::assertSame('ok:ignored', $reply->body);
        self::assertFalse($reply->retry);
        self::assertSame(0, $reply->paidOrder);
        self::assertSame([], $this->ledger->calls);
        self::assertSame([], $this->log, 'sem texto de log, nada e escrito');
    }

    public function testRejectedSignatureIsAnsweredLoggedAndTouchesNothing(): void {
        $this->link(41);
        $this->ledger->calls = [];
        $body = (string)json_encode(['kind' => 'payment.updated', 'payment' => 'pay_ok']);

        $reply = $this->notify(new Notification([], [], ['HTTP_X_CAIXA_SIGNATURE' => str_repeat('0', 64)], $body));

        self::assertSame('ok:signature-rejected', $reply->body);
        self::assertSame(['caixa notify: assinatura rejeitada'], $this->log);
        self::assertSame([], $this->ledger->calls);
        self::assertSame('pending', $this->ledger->charges[0]['status']);
    }

    public function testRetryVerdictAsksTheProviderToSendAgain(): void {
        $reply = $this->notify(FictitiousMethod::signed('pay_busy'));

        self::assertTrue($reply->retry);
        self::assertSame('retry:provider-busy', $reply->body);
        self::assertSame(0, $reply->paidOrder);
        self::assertSame(['caixa notify: provedor ocupado'], $this->log);
    }

    public function testFailureInsideTheMethodBecomesAnAnswerAndALogLine(): void {
        $this->method->explode = true;

        $reply = $this->notify(FictitiousMethod::signed('pay_ok'));

        self::assertSame('ok:error', $reply->body);
        self::assertFalse($reply->retry, 'falha local nao pede reenvio em laco');
        self::assertSame(0, $reply->paidOrder);
        self::assertSame(['caixa notify falhou e foi isolado: provedor fora do ar'], $this->log);
    }

    public function testFailureWhileWritingBecomesAnAnswerAndIsNotReportedAsPaid(): void {
        $this->link(41);
        $this->ledger->failOnWrite = true;

        $reply = $this->notify(FictitiousMethod::signed('pay_ok'));

        self::assertSame('ok:error', $reply->body);
        self::assertSame(0, $reply->paidOrder);
        self::assertSame(['caixa notify falhou e foi isolado: banco fora do ar'], $this->log);
        self::assertSame([], $this->ledger->orders);
    }

    public function testFailureWhileBuildingTheHistoryLineBecomesAnAnswer(): void {
        $this->link(41);

        $reply = $this->checkout->notify($this->method, FictitiousMethod::signed('pay_ok'), static function (): string {
            throw new \RuntimeException('idioma fora do ar');
        });

        self::assertSame('ok:error', $reply->body);
        self::assertSame(0, $reply->paidOrder);
    }

    public function testBrokenLogDoesNotChangeTheAnswer(): void {
        $checkout = new Checkout($this->ledger, static function (): void {
            throw new \RuntimeException('disco cheio');
        });

        self::assertSame('ok:no-local-record', $checkout->notify($this->method, FictitiousMethod::signed('pay_lost'), static fn (): string => 'x')->body);

        $this->method->explode = true;
        self::assertSame('ok:error', $checkout->notify($this->method, FictitiousMethod::signed('pay_ok'), static fn (): string => 'x')->body);
    }

    public function testWorksWithoutALog(): void {
        $checkout = new Checkout($this->ledger);

        self::assertSame('ok:no-local-record', $checkout->notify($this->method, FictitiousMethod::signed('pay_lost'), static fn (): string => 'x')->body);
        self::assertTrue($checkout->notify($this->method, FictitiousMethod::signed('pay_busy'), static fn (): string => 'x')->retry);
    }

    public function testHistoryLineIsOnlyBuiltWhenTheOrderMoves(): void {
        $this->link(44);
        $built = 0;
        $history = static function () use (&$built): string {
            $built++;

            return 'x';
        };

        $this->checkout->notify($this->method, FictitiousMethod::signed('pay_review'), $history);
        $this->checkout->notify($this->method, FictitiousMethod::signed('pay_review'), $history);
        $this->checkout->notify($this->method, FictitiousMethod::signed('pay_lost'), $history);

        self::assertSame(0, $built);
    }

    // ── o que um meio pode devolver ─────────────────────────────────────────

    /** @return iterable<string, array{int, string, string}> */
    public static function badConfirmations(): iterable {
        yield 'pedido zero'     => [0, 'pay_ok', 'approved'];
        yield 'pedido negativo' => [-41, 'pay_ok', 'approved'];
        yield 'sem pagamento'   => [41, '', 'approved'];
        yield 'sem situacao'    => [41, 'pay_ok', ''];
    }

    #[DataProvider('badConfirmations')]
    public function testConfirmationWithoutOrderPaymentOrSituationCannotBeBuilt(int $order, string $payment, string $status): void {
        $this->expectException(\InvalidArgumentException::class);

        Verdict::confirmed($order, $payment, $status);
    }

    #[DataProvider('badConfirmations')]
    public function testMethodThatTriesItGetsAnErrorAnswerAndNothingIsWritten(int $order, string $payment, string $status): void {
        $this->link(41);
        $this->ledger->calls = [];
        $method = new class($order, $payment, $status) implements \Reamur\System\Library\Payment\Method {
            public function __construct(private int $order, private string $payment, private string $status) {}
            public function code(): string { return 'torto'; }
            public function blockedBy(): string { return ''; }
            public function charge(\Reamur\System\Library\Payment\ChargeRequest $request): Charge { return Charge::failed(''); }
            public function notification(Notification $notification): Verdict { return Verdict::confirmed($this->order, $this->payment, $this->status); }
        };

        $reply = $this->checkout->notify($method, new Notification(), static fn (): string => 'x');

        self::assertSame('ok:error', $reply->body);
        self::assertSame(0, $reply->paidOrder);
        self::assertSame([], $this->ledger->calls);
        self::assertCount(1, $this->log);
        self::assertStringStartsWith('torto notify falhou e foi isolado: ', $this->log[0]);
    }

    /** @return iterable<string, array{string}> */
    public static function badLabels(): iterable {
        yield 'vazio'            => [''];
        yield 'com espaco'       => ['not ours'];
        yield 'quebra de linha'  => ["ok\r\nSet-Cookie: x=1"];
        yield 'maiusculas'       => ['Ignored'];
        yield 'quebra no fim'    => ["ignored\n"];
        yield 'marcacao'         => ['<b>x</b>'];
        yield 'hifen no fim'     => ['ignored-'];
        yield 'dois pontos'      => ['ok:processed'];
    }

    #[DataProvider('badLabels')]
    public function testAnswerLabelIsPlainLowercaseWords(string $label): void {
        foreach (['answer', 'retry'] as $kind) {
            try {
                $kind === 'answer' ? Verdict::answer($label) : Verdict::retry($label, 'x');
                self::fail('devia recusar: ' . $label);
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('rótulo', $e->getMessage());
            }
        }
    }

    public function testLabelsInUseAreAccepted(): void {
        foreach (['ignored', 'signature-rejected', 'not-ours', 'no-reference', 'secret-locked', 'token-locked', 'provider-busy', 'v2'] as $label) {
            self::assertSame($label, Verdict::answer($label)->label);
            self::assertSame($label, Verdict::retry($label, 'x')->label);
        }
    }

    // ── o nucleo nao conhece nenhum provedor ────────────────────────────────

    public function testCoreRulesNameNoProvider(): void {
        foreach (['checkout', 'method', 'ledger', 'charge', 'charge_request', 'notification', 'verdict', 'link', 'reply'] as $file) {
            $source = (string)file_get_contents(dirname(__DIR__, 3) . '/dev/system/library/payment/' . $file . '.php');
            $code   = (string)preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $source);

            self::assertDoesNotMatchRegularExpression('/mercado|\bmp_|MercadopagoMarketplace|stripe|paypal/i', $code, $file);
        }
    }
}
