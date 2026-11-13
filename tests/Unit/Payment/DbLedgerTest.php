<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\Charge;
use Reamur\System\Library\Payment\DbLedger;

/**
 * EX-03 (ADR 0097): as cobrancas no banco. Nesta etapa a tabela e a mesma de
 * antes (mp_payment) e o SQL e o mesmo que os dois models faziam.
 */
final class DbLedgerTest extends TestCase {
    /** Banco de mentira: guarda o SQL e responde com as linhas dadas. */
    private function db(array $rows = []): object {
        return new class($rows) {
            /** @var list<string> */
            public array $sql = [];

            public function __construct(private array $rows) {}

            public function escape(string $value): string {
                return addslashes($value);
            }

            public function query(string $sql): \stdClass {
                $this->sql[] = (string)preg_replace('/\s+/', ' ', $sql);
                $res = new \stdClass();
                $res->rows     = $this->rows;
                $res->row      = $this->rows[0] ?? [];
                $res->num_rows = count($this->rows);

                return $res;
            }
        };
    }

    private const ROW = [
        'mp_payment_id' => '7', 'order_id' => '41', 'mp_seller_id' => '3', 'preference_id' => 'pref-1', 'payment_id' => '5001',
        'public_token' => 'abcdef', 'amount' => '150.0000', 'marketplace_fee' => '15.00', 'currency_id' => 'BRL',
        'status' => 'approved', 'init_point' => 'https://mp.example/pagar',
    ];

    public function testLatestChargeOfTheOrderIsReadFromTheSameTableAsBefore(): void {
        $db  = $this->db([self::ROW]);
        $row = (new DbLedger($db, 'rms_'))->latestForOrder(41);

        self::assertSame(["SELECT * FROM `rms_mp_payment` WHERE `order_id` = '41' ORDER BY `mp_payment_id` DESC LIMIT 1"], $db->sql);
        self::assertSame(
            ['id' => 7, 'order_id' => 41, 'token' => 'abcdef', 'status' => 'approved', 'url' => 'https://mp.example/pagar', 'payment_id' => '5001'],
            $row
        );
    }

    public function testNoRowIsAnEmptyArray(): void {
        self::assertSame([], (new DbLedger($this->db(), 'rms_'))->latestForOrder(41));
        self::assertSame([], (new DbLedger($this->db(), 'rms_'))->byToken('abc'));
    }

    public function testTablePrefixIsTheOneGiven(): void {
        $db = $this->db();
        $ledger = new DbLedger($db, 'loja_');

        $ledger->latestForOrder(1);
        $ledger->byToken('abc');
        $ledger->add(1, 'abc', 1.0, Charge::created('r', 'https://x.example/'));
        $ledger->record(1, '5', 'approved');
        $ledger->moveOrder(1, 4, 'x');

        self::assertCount(6, $db->sql);
        self::assertStringContainsString('`loja_mp_payment`', $db->sql[0]);
        self::assertStringContainsString('`loja_mp_payment`', $db->sql[1]);
        self::assertStringContainsString('`loja_mp_payment`', $db->sql[2]);
        self::assertStringContainsString('`loja_mp_payment`', $db->sql[3]);
        self::assertStringContainsString('`loja_order`', $db->sql[4]);
        self::assertStringContainsString('`loja_order_history`', $db->sql[5]);
        self::assertStringNotContainsString('rms_', implode("\n", $db->sql));
    }

    public function testKeyLookupEscapesTheValue(): void {
        $db = $this->db([self::ROW]);

        self::assertSame(7, (new DbLedger($db, 'rms_'))->byToken("a' OR '1'='1")['id']);
        self::assertSame(["SELECT * FROM `rms_mp_payment` WHERE `public_token` = 'a\\' OR \\'1\\'=\\'1'"], $db->sql);
    }

    public function testKeyLookupIgnoresSpacesAround(): void {
        $db = $this->db([self::ROW]);
        (new DbLedger($db, 'rms_'))->byToken("  abcdef\n");

        self::assertSame(["SELECT * FROM `rms_mp_payment` WHERE `public_token` = 'abcdef'"], $db->sql);
    }

    /** @return iterable<string, array{string}> */
    public static function keysNotLookedUp(): iterable {
        yield 'vazia'        => [''];
        yield 'so espacos'   => ['   '];
        yield '49 caracteres' => [str_repeat('a', 49)];
        yield 'muito longa'  => [str_repeat('a', 5000)];
    }

    #[DataProvider('keysNotLookedUp')]
    public function testEmptyOrOversizedKeyNeverReachesTheDatabase(string $token): void {
        $db = $this->db([self::ROW]);

        self::assertSame([], (new DbLedger($db, 'rms_'))->byToken($token));
        self::assertSame([], $db->sql);
    }

    public function testKeyOfExactly48CharactersIsLookedUp(): void {
        $db = $this->db([self::ROW]);
        (new DbLedger($db, 'rms_'))->byToken(str_repeat('a', 48));

        self::assertCount(1, $db->sql);
    }

    public function testNewChargeIsStoredPendingWithEveryValueEscaped(): void {
        $db = $this->db();

        (new DbLedger($db, 'rms_'))->add(41, "tok'en", 80.5, Charge::created("pref'1", "https://mp.example/p?a='b'", 8.05, 3, "B'L"));

        self::assertSame([
            "INSERT INTO `rms_mp_payment` SET `order_id` = '41', `mp_seller_id` = '3', `preference_id` = 'pref\\'1', `public_token` = 'tok\\'en',"
            . " `amount` = '80.5', `marketplace_fee` = '8.05', `currency_id` = 'B\\'L', `status` = 'pending',"
            . " `init_point` = 'https://mp.example/p?a=\\'b\\'', `date_added` = NOW(), `date_modified` = NOW()",
        ], $db->sql);
    }

    public function testConfirmedPaymentIsWrittenOnTheChargeRow(): void {
        $db = $this->db();

        (new DbLedger($db, 'rms_'))->record(7, '5001', "appro'ved");

        self::assertSame(["UPDATE `rms_mp_payment` SET `payment_id` = '5001', `status` = 'appro\\'ved', `date_modified` = NOW() WHERE `mp_payment_id` = '7'"], $db->sql);
    }

    /** @return iterable<string, array{string}> */
    public static function nonNumericPayments(): iterable {
        yield 'texto'         => ['pay_ok'];
        yield 'vazio'         => [''];
        yield 'negativo'      => ['-5'];
        yield 'com aspas'     => ["5' OR '1'='1"];
        yield 'com casas'     => ['5.5'];
        yield 'com espaco'    => ['5 '];
    }

    #[DataProvider('nonNumericPayments')]
    public function testPaymentIdentifierThatIsNotANumberIsRefusedInsteadOfBecomingZero(string $payment): void {
        $db = $this->db();

        try {
            (new DbLedger($db, 'rms_'))->record(7, $payment, 'approved');
            self::fail('devia recusar');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('não numérico', $e->getMessage());
        }

        self::assertSame([], $db->sql);
    }

    public function testOrderMoveUpdatesTheOrderAndWritesOneHistoryLine(): void {
        $db = $this->db();

        (new DbLedger($db, 'rms_'))->moveOrder(41, 4, "Mercado Pago: payment #5001 → approved 'x'");

        self::assertSame([
            "UPDATE `rms_order` SET `order_status_id` = '4', `date_modified` = NOW() WHERE `order_id` = '41'",
            "INSERT INTO `rms_order_history` SET `order_id` = '41', `order_status_id` = '4', `comment` = 'Mercado Pago: payment #5001 → approved \\'x\\'', `date_added` = NOW()",
        ], $db->sql);
    }

    public function testModelsNoLongerCarryTheirOwnCopyOfTheChargeRules(): void {
        $root    = dirname(__DIR__, 3) . '/dev/';
        $catalog = (string)file_get_contents($root . 'catalog/model/checkout/mp_payment.php');
        $admin   = (string)file_get_contents($root . 'admin/model/setting/marketplace.php');

        foreach (['function addPayment', 'function getPaymentByOrder', 'function getPaymentByToken', 'function reflectPaymentStatus', 'INSERT INTO', 'order_history'] as $gone) {
            self::assertStringNotContainsString($gone, $catalog, $gone);
        }

        foreach (['function addPayment', 'function getPaymentByOrder', 'order_history'] as $gone) {
            self::assertStringNotContainsString($gone, $admin, $gone);
        }

        // E quem grava e so o livro de cobrancas.
        foreach (['catalog/controller/checkout/payment.php', 'admin/controller/setting/marketplace.php', 'system/library/payment/checkout.php', 'system/library/payment/mercadopago.php'] as $file) {
            self::assertDoesNotMatchRegularExpression('/INSERT INTO|UPDATE `/', (string)file_get_contents($root . $file), $file);
        }
    }
}
