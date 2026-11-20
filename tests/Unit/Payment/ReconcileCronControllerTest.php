<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\Catalog\Controller\Cron\Reconcile as ReconcileController;
use Reamur\Catalog\Model\Checkout\GatewayPayment as GatewayPaymentModel;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\SecretBox;
use Reamur\System\Library\Payment\GatewayCredentialVault;

/**
 * P0-b: a rota pública de reconciliação é um endpoint aberto na internet.
 * Este teste prova o portão (token, parâmetros, schema) e que a varredura
 * completa roda sem rede: o provedor responde "sem âncora", não há chamada externa.
 */
final class ReconcileCronControllerTest extends TestCase {
    private const TOKEN = 'reconcile-token-0123456789-abcdefghij';

    private function db(array $options = []): object {
        return new class($options) {
            /** @var list<string> */
            public array $queries = [];

            public function __construct(private array $options) {}

            public function query(string $sql): \stdClass|bool {
                $this->queries[] = $sql;
                $schemaReady = (bool)($this->options['schema'] ?? true);

                if (str_starts_with($sql, 'SHOW TABLES')) {
                    return $this->result($schemaReady ? [['x' => 'x']] : []);
                }

                if (str_starts_with($sql, 'SHOW COLUMNS')) {
                    return $this->result([]);
                }

                if (str_contains($sql, 'FROM `rms_jow_gateway`') && str_contains($sql, 'ORDER BY `gateway_id`')) {
                    return $this->result($this->options['accounts'] ?? []);
                }

                if (str_contains($sql, 'credentials_encrypted_json')) {
                    return $this->result($this->options['credentialRows'] ?? []);
                }

                if (str_starts_with($sql, 'SELECT') && str_contains($sql, 'rms_payment_transaction')) {
                    // ADR 0123: duas varreduras na mesma tabela. Sem distinguir
                    // pelo filtro de status, as duas leem a mesma lista e
                    // `scanned` dobra.
                    if (str_contains($sql, "IN ('paid', 'partially_refunded')")) {
                        return $this->result($this->options['settled'] ?? []);
                    }

                    return $this->result($this->options['pending'] ?? []);
                }

                return true;
            }

            private function result(array $rows): \stdClass {
                $q = new \stdClass();
                $q->row = $rows[0] ?? [];
                $q->rows = $rows;
                $q->num_rows = count($rows);

                return $q;
            }

            public function escape(string $value): string {
                return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
            }

            public function getLastId(): int {
                return 0;
            }

            public function countAffected(): int {
                return 0;
            }
        };
    }

    /** @return array{0: Response, 1: object, 2: object} */
    private function executeCron(object $db, array $get, array $server = [], ?string $tokenOverride = null, ?SecretBox $box = null): array {
        $request = new Request();
        $request->get = $get;
        $request->post = [];
        $request->server = $server;

        $response = new Response();
        $log = new class {
            /** @var list<string> */
            public array $lines = [];
            public function write(string $m): void { $this->lines[] = $m; }
        };

        $registry = new Registry();
        $registry->set('db', $db);
        $registry->set('request', $request);
        $registry->set('response', $response);
        $registry->set('log', $log);
        $registry->set('secret_box', $box ?? new SecretBox(SecretBox::generateKey()));

        $load = new class($registry) {
            public function __construct(private Registry $registry) {}

            public function model(string $route): void {
                if ($route === 'checkout/gateway_payment') {
                    $this->registry->set('model_checkout_gateway_payment', new GatewayPaymentModel($this->registry));
                }
            }
        };
        $registry->set('load', $load);

        $controller = new class($registry, $tokenOverride) extends ReconcileController {
            public function __construct(Registry $registry, private ?string $tokenOverride) {
                parent::__construct($registry);
            }

            protected function expectedToken(): string {
                return $this->tokenOverride ?? parent::expectedToken();
            }
        };

        $controller->index();

        return [$response, $db, $log];
    }

    private function pendingRow(string $status = 'pending'): array {
        return [
            'transaction_id' => 41,
            'gateway_id' => 12,
            'source_type' => 'jow_charge',
            'source_id' => 42,
            'provider_code' => 'stripe',
            'method_code' => 'checkout_session',
            'source_currency' => 'BRL',
            'source_fraction_digits' => 2,
            'source_amount' => '125.50',
            'charge_currency' => 'BRL',
            'charge_fraction_digits' => 2,
            'charge_amount' => '125.50',
            'fx_provider_code' => '',
            'fx_quote_reference' => null,
            'fx_rate' => null,
            'fx_quoted_at' => null,
            'fx_expires_at' => null,
            'provider_reference' => '71e3f12c-6b0c-5ac5-9b83-d1d43c90d0c9',
            'provider_payment_id' => null,
            'public_token' => str_repeat('e', 48),
            'idempotency_key' => '71e3f12c-6b0c-5ac5-9b83-d1d43c90d0c9',
            'status' => $status,
            'customer_action_json' => (string)json_encode([
                'kind' => 'redirect',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_x',
                'copy_paste' => '',
                'expires_at' => null,
            ]),
        ];
    }

    public function testRouteIsForbiddenWithoutAConfiguredToken(): void {
        $db = $this->db();

        [$response, $db] = $this->executeCron($db, ['token' => self::TOKEN], [], '');

        $this->assertSame("forbidden\n", $response->getOutput());
        $this->assertContains('HTTP/1.1 403 Forbidden', $response->getHeaders());
        $this->assertSame([], $db->queries);
    }

    public function testShortConfiguredTokenIsRefused(): void {
        $db = $this->db();

        [$response, $db] = $this->executeCron($db, ['token' => 'curto-demais'], [], str_repeat('a', 31));

        $this->assertSame("forbidden\n", $response->getOutput());
        $this->assertSame([], $db->queries);
    }

    public function testWrongOrMissingTokenIsForbidden(): void {
        foreach ([['token' => 'outro-token-qualquer-0000000000'], []] as $get) {
            $db = $this->db();
            [$response, $db] = $this->executeCron($db, $get, [], self::TOKEN);

            $this->assertSame("forbidden\n", $response->getOutput());
            $this->assertContains('HTTP/1.1 403 Forbidden', $response->getHeaders());
            $this->assertSame([], $db->queries);
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testBearerHeaderIsAccepted(): void {
        $db = $this->db(['schema' => false]);

        [$response] = $this->executeCron($db, [], ['HTTP_AUTHORIZATION' => 'Bearer ' . self::TOKEN], self::TOKEN);

        $this->assertSame("schema_missing\n", $response->getOutput(), 'passou pelo portão e parou no schema');
    }

    public function testInvalidParametersAreRejectedBeforeAnyQuery(): void {
        foreach ([['limit' => 'abc'], ['limit' => '0'], ['limit' => '201'], ['min_age' => '0'], ['lookback' => '91']] as $get) {
            $db = $this->db();
            [$response, $db] = $this->executeCron($db, $get + ['token' => self::TOKEN], [], self::TOKEN);

            $this->assertSame("invalid parameters\n", $response->getOutput());
            $this->assertContains('HTTP/1.1 400 Bad Request', $response->getHeaders());
            $this->assertSame([], $db->queries);
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testMissingSchemaAnswers503AndNeverQueriesTheLedger(): void {
        $db = $this->db(['schema' => false]);

        [$response, $db] = $this->executeCron($db, ['token' => self::TOKEN], [], self::TOKEN);

        $this->assertSame("schema_missing\n", $response->getOutput());
        $this->assertContains('HTTP/1.1 503 Service Unavailable', $response->getHeaders());
        $this->assertSame([], array_filter($db->queries, static fn (string $q): bool => str_contains($q, 'rms_payment_transaction')));
    }

    public function testRunReportsAnAttemptWithoutProviderAnchorWithoutCallingTheProvider(): void {
        $box = new SecretBox(SecretBox::generateKey());
        $db = $this->db([
            'accounts' => [[
                'gateway_id' => 12,
                'provider_code' => 'stripe',
                'account_label' => 'JOW platform',
                'environment' => 'sandbox',
                'status' => 1,
                'public_config_json' => '{"flow":"jow_internal"}',
            ]],
            'credentialRows' => [[
                'provider_code' => 'stripe',
                'credentials_encrypted_json' => (new GatewayCredentialVault($box))
                    ->sealAll(12, 'stripe', ['secret_key' => 'sk_test_0123456789abcdef', 'webhook_secret' => 'whsec_0123456789abcdef']),
            ]],
            'pending' => [$this->pendingRow()],
        ]);

        [$response, , $log] = $this->executeCron($db, ['token' => self::TOKEN, 'format' => 'json'], [], self::TOKEN, $box);
        $body = (string)$response->getOutput();
        $decoded = json_decode($body, true);

        $this->assertNotContains('HTTP/1.1 503 Service Unavailable', $response->getHeaders());
        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['scanned']);
        $this->assertSame(1, $decoded['skipped']);
        $this->assertSame('missing_provider_anchor', $decoded['details'][0]['reason']);
        $this->assertSame('stripe', $decoded['details'][0]['provider_code']);
        $this->assertStringNotContainsString('sk_test_0123456789abcdef', $body);
        $this->assertStringNotContainsString('whsec_0123456789abcdef', $body);
        $this->assertNotEmpty($log->lines);
        $this->assertStringContainsString('scanned=1', implode("\n", $log->lines));
    }

    public function testTextFormatIsReadableAndSkipsUnchangedLines(): void {
        $box = new SecretBox(SecretBox::generateKey());
        $db = $this->db([
            'accounts' => [[
                'gateway_id' => 12,
                'provider_code' => 'stripe',
                'account_label' => 'JOW platform',
                'environment' => 'sandbox',
                'status' => 1,
                'public_config_json' => '{}',
            ]],
            'credentialRows' => [[
                'provider_code' => 'stripe',
                'credentials_encrypted_json' => (new GatewayCredentialVault($box))
                    ->sealAll(12, 'stripe', ['secret_key' => 'sk_test_0123456789abcdef']),
            ]],
            'pending' => [$this->pendingRow()],
        ]);

        [$response] = $this->executeCron($db, ['token' => self::TOKEN], [], self::TOKEN, $box);
        $body = (string)$response->getOutput();

        $this->assertStringContainsString('jow reconciliation', $body);
        $this->assertStringContainsString('scanned=1 applied=0 unchanged=0 skipped=1 retry=0 failed=0', $body);
        $this->assertStringContainsString('- tx=41 stripe (pending) skipped missing_provider_anchor', $body);
    }

    public function testCredentialFailureMarksTheRunAsFailed(): void {
        $sealing = new SecretBox(SecretBox::generateKey());
        $other = new SecretBox(SecretBox::generateKey());
        $db = $this->db([
            'accounts' => [[
                'gateway_id' => 12,
                'provider_code' => 'stripe',
                'account_label' => 'JOW platform',
                'environment' => 'sandbox',
                'status' => 1,
                'public_config_json' => '{}',
            ]],
            'credentialRows' => [[
                'provider_code' => 'stripe',
                'credentials_encrypted_json' => (new GatewayCredentialVault($sealing))
                    ->sealAll(12, 'stripe', ['secret_key' => 'sk_test_0123456789abcdef']),
            ]],
            'pending' => [],
        ]);

        [$response] = $this->executeCron($db, ['token' => self::TOKEN], [], self::TOKEN, $other);

        $this->assertContains('HTTP/1.1 503 Service Unavailable', $response->getHeaders());
        $this->assertStringContainsString('failed=1', (string)$response->getOutput());
        $this->assertStringNotContainsString('sk_test_0123456789abcdef', (string)$response->getOutput());
    }
}
