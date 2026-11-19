<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Payment\DbGatewayAccountStore;
use Reamur\System\Library\Payment\GatewayAccount;
use Reamur\System\Library\Payment\GatewayAdapter;
use Reamur\System\Library\Payment\GatewayCapabilities;
use Reamur\System\Library\Payment\GatewayCredentials;
use Reamur\System\Library\Payment\GatewayCredentialVault;
use Reamur\System\Library\Payment\GatewayCustomerAction;
use Reamur\System\Library\Payment\GatewayPaymentRequest;
use Reamur\System\Library\Payment\GatewayPaymentResult;
use Reamur\System\Library\Payment\GatewayPaymentStatus;
use Reamur\System\Library\Payment\GatewayRegistry;
use Reamur\System\Library\Payment\GatewayWebhookResult;
use Reamur\System\Library\Payment\Money;
use Reamur\System\Library\Payment\Notification;
use Reamur\System\Library\Security\SecretBox;

final class DbGatewayAccountStoreTest extends TestCase {
    public function testCreateStartsDisabledAndStoresOnlyCiphertext(): void {
        $db = new GatewayAccountStoreTestDb();
        $vault = new GatewayCredentialVault(new SecretBox(SecretBox::generateKey()));
        $store = $this->store($db, $vault);

        $account = $store->create(
            'store_stub', 'Store A', 'sandbox', true, ['merchant_id' => 'm-1'],
            ['secret_key' => 'never-store-plaintext']
        );

        self::assertSame(5, $account->gatewayId);
        self::assertTrue($account->enabled);
        self::assertStringContainsString('`status` = 0', $db->queries[0]);
        self::assertStringNotContainsString('never-store-plaintext', $db->queries[0]);
        self::assertStringNotContainsString('never-store-plaintext', $db->queries[1]);
        self::assertStringContainsString('rmsenc:v1:', $db->queries[1]);
        self::assertStringContainsString("`status` = '1'", $db->queries[1]);
    }

    public function testReadCredentialsDecryptsOnlyForRuntimeAndDebugDoesNotRevealValues(): void {
        $secret = 'runtime-only-secret';
        $vault = new GatewayCredentialVault(new SecretBox(SecretBox::generateKey()));
        $ciphertext = $vault->sealAll(5, 'store_stub', ['secret_key' => $secret]);
        $db = new GatewayAccountStoreTestDb();
        $db->rows[] = ['provider_code' => 'store_stub', 'credentials_encrypted_json' => $ciphertext];
        $store = $this->store($db, $vault);

        $credentials = $store->credentials(5);

        self::assertSame($secret, $credentials->get('secret_key'));
        self::assertStringNotContainsString($secret, print_r($credentials, true));
        self::assertStringNotContainsString($secret, $db->queries[0]);
    }

    public function testUpdatePreservesBlankSecretFieldsAndCanClearExplicitly(): void {
        $vault = new GatewayCredentialVault(new SecretBox(SecretBox::generateKey()));
        $ciphertext = $vault->sealAll(5, 'store_stub', ['secret_key' => 'keep-me', 'webhook_secret' => 'remove-me']);
        $db = new GatewayAccountStoreTestDb();
        $db->rows[] = [
            'gateway_id' => 5,
            'provider_code' => 'store_stub',
            'account_label' => 'Store A',
            'environment' => 'sandbox',
            'status' => 1,
            'public_config_json' => '{"merchant_id":"m-1"}',
        ];
        $db->rows[] = ['credentials_encrypted_json' => $ciphertext];
        $store = $this->store($db, $vault);

        $store->update(5, 'Store A renamed', 'sandbox', false, ['merchant_id' => 'm-2'], ['secret_key' => ''], ['webhook_secret']);

        $updatedJson = $this->extractEncryptedJson(end($db->queries));
        $decoded = json_decode($updatedJson, true, 32, JSON_THROW_ON_ERROR);

        self::assertSame(['secret_key'], array_keys($decoded));
        self::assertSame('keep-me', $vault->open(5, 'store_stub', 'secret_key', $decoded['secret_key']));
        self::assertStringNotContainsString('keep-me', end($db->queries));
        self::assertStringNotContainsString('remove-me', end($db->queries));
        self::assertStringContainsString("`status` = '0'", end($db->queries));
    }

    public function testUnknownProviderCannotBeStoredFromDatabaseInput(): void {
        $store = $this->store(new GatewayAccountStoreTestDb(), new GatewayCredentialVault(new SecretBox(SecretBox::generateKey())));

        $this->expectException(\OutOfBoundsException::class);
        $store->create('arbitrary_class_name', 'Unknown', 'sandbox', false, [], []);
    }

    public function testLockedCredentialCannotBeUsedToReenableAnAccount(): void {
        $writerVault = new GatewayCredentialVault(new SecretBox(SecretBox::generateKey()));
        $ciphertext = $writerVault->sealAll(5, 'store_stub', ['secret_key' => 'locked-secret']);
        $readerVault = new GatewayCredentialVault(new SecretBox(SecretBox::generateKey()));
        $db = new GatewayAccountStoreTestDb();
        $db->rows[] = [
            'gateway_id' => 5,
            'provider_code' => 'store_stub',
            'account_label' => 'Store A',
            'environment' => 'sandbox',
            'status' => 0,
            'public_config_json' => '{}',
        ];
        $db->rows[] = ['credentials_encrypted_json' => $ciphertext];
        $store = $this->store($db, $readerVault);

        $this->expectException(\RuntimeException::class);
        $store->update(5, 'Store A', 'sandbox', true, [], []);
    }

    private function store(GatewayAccountStoreTestDb $db, GatewayCredentialVault $vault): DbGatewayAccountStore {
        return new DbGatewayAccountStore($db, 'rms_', new GatewayRegistry([new GatewayAccountStoreTestAdapter()]), $vault);
    }

    private function extractEncryptedJson(string|false $sql): string {
        self::assertIsString($sql);
        self::assertMatchesRegularExpression("/`credentials_encrypted_json` = '((?:\\\\'|[^'])*)'/", $sql);
        preg_match("/`credentials_encrypted_json` = '((?:\\\\'|[^'])*)'/", $sql, $matches);
        return str_replace("\\\\'", "'", $matches[1] ?? '');
    }
}

final class GatewayAccountStoreTestDb {
    /** @var list<string> */
    public array $queries = [];
    /** @var list<array<string, mixed>> */
    public array $rows = [];
    public int $lastId = 5;

    public function query(string $sql): \stdClass|bool {
        $this->queries[] = $sql;

        if (str_starts_with($sql, 'SELECT')) {
            $query = new \stdClass();
            $query->row = array_shift($this->rows) ?? [];
            return $query;
        }

        return true;
    }

    public function escape(string $value): string {
        return str_replace(['\\', "'", "\0", "\n", "\r"], ['\\\\', "\\'", '\\0', '\\n', '\\r'], $value);
    }

    public function getLastId(): int {
        $id = $this->lastId;
        $this->lastId = 0;
        return $id;
    }
}

final class GatewayAccountStoreTestAdapter implements GatewayAdapter {
    public function code(): string { return 'store_stub'; }
    public function label(): string { return 'Store stub'; }
    public function capabilities(): GatewayCapabilities {
        return new GatewayCapabilities(['card' => ['BRL']], ['BRL' => 2]);
    }
    public function quote(Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?\Reamur\System\Library\Payment\FxQuote {
        return null;
    }
    public function createPayment(GatewayPaymentRequest $request, GatewayAccount $account, GatewayCredentials $credentials, ?\Reamur\System\Library\Payment\FxQuote $quote): GatewayPaymentResult {
        return new GatewayPaymentResult('ref', GatewayCustomerAction::redirect('https://gateway.example/pay'), $request->sourceAmount, GatewayPaymentStatus::PENDING);
    }
    public function verifyNotification(Notification $notification, GatewayAccount $account, GatewayCredentials $credentials): GatewayWebhookResult {
        return GatewayWebhookResult::ignored('ignored');
    }
}
