<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\Catalog\Model\Checkout\GatewayPayment;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Payment\GatewayPaymentStatus;

/**
 * P0-a: o status verificado no ledger precisa chegar em rms_jow_charge para
 * qualquer provedor (mp, stripe, paypal) — e nunca para uma cobrança manual.
 *
 * Regressão coberta: o WHERE era fixo em `method = 'mp'`, então uma cobrança
 * Stripe/PayPal paga continuava "pending" no painel e no relatório.
 */
final class JowChargeStatusSyncTest extends TestCase {
    private function model(object $db): GatewayPayment {
        if (!defined('DB_PREFIX')) {
            define('DB_PREFIX', 'rms_');
        }

        $registry = new Registry();
        $registry->set('db', $db);

        return new GatewayPayment($registry);
    }

    /** @return object Banco de mentira que só guarda o SQL recebido. */
    private function db(): object {
        return new class {
            /** @var list<string> */
            public array $queries = [];

            public function query(string $sql): \stdClass|bool {
                $this->queries[] = $sql;

                return true;
            }

            public function escape(string $value): string {
                return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
            }
        };
    }

    /** @return list<string> */
    private function providers(): array {
        return ['mp', 'stripe', 'paypal'];
    }

    public function testEveryInstalledProviderReachesItsOwnChargeRow(): void {
        foreach ($this->providers() as $provider) {
            $db = $this->db();
            $this->model($db)->syncJowChargeStatus(42, GatewayPaymentStatus::PAID, $provider);

            $this->assertCount(1, $db->queries, $provider . ' must update exactly one row');
            $this->assertStringContainsString("`status` = 'paid'", $db->queries[0], $provider);
            $this->assertStringContainsString("`charge_id` = 42", $db->queries[0], $provider);
            $this->assertStringContainsString("AND `method` = '" . $provider . "'", $db->queries[0], $provider);

            foreach (array_diff($this->providers(), [$provider]) as $other) {
                $this->assertStringNotContainsString(
                    "AND `method` = '" . $other . "'",
                    $db->queries[0],
                    'a ' . $provider . ' event must not address another provider row'
                );
            }
        }
    }

    public function testProviderStatusIsMappedIntoTHeLegacyVocabulary(): void {
        $cases = [
            GatewayPaymentStatus::PENDING => ['pending', false],
            GatewayPaymentStatus::REQUIRES_ACTION => ['pending', false],
            GatewayPaymentStatus::AUTHORIZED => ['pending', false],
            GatewayPaymentStatus::PAID => ['paid', true],
            GatewayPaymentStatus::PARTIALLY_REFUNDED => ['partial', true],
            GatewayPaymentStatus::FAILED => ['canceled', false],
            GatewayPaymentStatus::CANCELLED => ['canceled', false],
            GatewayPaymentStatus::EXPIRED => ['expired', false],
            GatewayPaymentStatus::REFUNDED => ['refunded', true],
            GatewayPaymentStatus::DISPUTED => ['disputed', true],
        ];

        foreach ($cases as $providerStatus => [$expected, $stampsPaidAt]) {
            $db = $this->db();
            $this->model($db)->syncJowChargeStatus(7, $providerStatus, 'stripe');

            $this->assertCount(1, $db->queries, $providerStatus);
            $this->assertStringContainsString("`status` = '" . $expected . "'", $db->queries[0], $providerStatus);

            if ($stampsPaidAt) {
                $this->assertStringContainsString('`paid_at` = COALESCE(`paid_at`, NOW())', $db->queries[0], $providerStatus);
            } else {
                $this->assertStringNotContainsString('`paid_at`', $db->queries[0], $providerStatus);
            }
        }
    }

    public function testUnknownStatusNeverTouchesTheRow(): void {
        $db = $this->db();
        $this->model($db)->syncJowChargeStatus(7, 'settled_by_hand', 'mp');

        $this->assertSame([], $db->queries);
    }

    public function testInvalidChargeOrProviderIsRefusedBeforeAnyQuery(): void {
        $db = $this->db();
        $model = $this->model($db);

        $model->syncJowChargeStatus(0, GatewayPaymentStatus::PAID, 'mp');
        $model->syncJowChargeStatus(-5, GatewayPaymentStatus::PAID, 'mp');
        $model->syncJowChargeStatus(7, GatewayPaymentStatus::PAID, '');
        $model->syncJowChargeStatus(7, GatewayPaymentStatus::PAID, 'MP');
        $model->syncJowChargeStatus(7, GatewayPaymentStatus::PAID, 'mercadopago checkout');
        $model->syncJowChargeStatus(7, GatewayPaymentStatus::PAID, str_repeat('a', 33));

        $this->assertSame([], $db->queries);
    }

    public function testManualChargeCanNeverMatch(): void {
        // `pix`, `card` e `boleto` não são códigos de provedor instalados, então
        // nenhuma notificação de gateway consegue alcançar essas linhas: o WHERE
        // exige igualdade exata com o código do provedor verificado.
        $db = $this->db();
        $this->model($db)->syncJowChargeStatus(9, GatewayPaymentStatus::PAID, 'pix');

        $this->assertCount(1, $db->queries);
        $this->assertStringContainsString("AND `method` = 'pix'", $db->queries[0]);
        $this->assertStringContainsString("`status` = 'paid'", $db->queries[0]);
    }
}
