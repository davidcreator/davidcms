<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

use JsonException;

/** MySQL/MariaDB account store. Secrets are encrypted per field and never returned by find(). */
final class DbGatewayAccountStore implements GatewayAccountStore {
    public function __construct(
        private readonly object $db,
        private readonly string $prefix,
        private readonly GatewayRegistry $registry,
        private readonly GatewayCredentialVault $vault,
    ) {
        if ($prefix !== '' && preg_match('/^[A-Za-z0-9_]+$/D', $prefix) !== 1) {
            throw new \InvalidArgumentException('gateway account store: invalid table prefix');
        }
    }

    public function find(int $gatewayId): ?GatewayAccount {
        if ($gatewayId <= 0) {
            return null;
        }

        $query = $this->query(
            "SELECT `gateway_id`, `provider_code`, `account_label`, `environment`, `status`, `public_config_json`"
            . " FROM `" . $this->table() . "` WHERE `gateway_id` = '" . $gatewayId . "' LIMIT 1"
        );
        $row = ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];

        if ($row === []) {
            return null;
        }

        $code = (string)($row['provider_code'] ?? '');
        $publicConfig = $this->decodePublicConfig((string)($row['public_config_json'] ?? ''));

        return new GatewayAccount(
            (int)($row['gateway_id'] ?? 0),
            $code,
            (string)($row['account_label'] ?? ''),
            (string)($row['environment'] ?? ''),
            (int)($row['status'] ?? 0) === 1,
            $publicConfig
        );
    }

    /** @return list<GatewayAccount> */
    public function all(): array {
        $query = $this->query(
            "SELECT `gateway_id`, `provider_code`, `account_label`, `environment`, `status`, `public_config_json`"
            . " FROM `" . $this->table() . "` ORDER BY `gateway_id` ASC"
        );
        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
        $accounts = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = (string)($row['provider_code'] ?? '');

            // One unreadable row must not hide the healthy accounts from
            // reconciliation; invalid metadata is simply not listed.
            try {
                $accounts[] = new GatewayAccount(
                    (int)($row['gateway_id'] ?? 0),
                    $code,
                    (string)($row['account_label'] ?? ''),
                    (string)($row['environment'] ?? ''),
                    (int)($row['status'] ?? 0) === 1,
                    $this->decodePublicConfig((string)($row['public_config_json'] ?? ''))
                );
            } catch (\InvalidArgumentException | \RuntimeException) {
                continue;
            }
        }

        return $accounts;
    }

    public function credentials(int $gatewayId): GatewayCredentials {
        if ($gatewayId <= 0) {
            throw new \InvalidArgumentException('gateway account store: invalid gateway ID');
        }

        $query = $this->query(
            "SELECT `provider_code`, `credentials_encrypted_json` FROM `" . $this->table() . "`"
            . " WHERE `gateway_id` = '" . $gatewayId . "' LIMIT 1"
        );
        $row = ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];

        if ($row === []) {
            throw new \OutOfBoundsException('gateway account store: account was not found');
        }

        $providerCode = (string)($row['provider_code'] ?? '');
        $this->assertInstalledProvider($providerCode);

        return $this->vault->openJson(
            $gatewayId,
            $providerCode,
            (string)($row['credentials_encrypted_json'] ?? '')
        );
    }

    public function create(
        string $providerCode,
        string $accountLabel,
        string $environment,
        bool $enabled,
        array $publicConfig,
        array $secretFields
    ): GatewayAccount {
        $this->assertInstalledProvider($providerCode);
        $this->validateMetadata(1, $providerCode, $accountLabel, $environment, $enabled, $publicConfig);
        new GatewayCredentials($secretFields);
        $publicJson = json_encode($publicConfig, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        // New rows start disabled. If encryption fails, there is no enabled
        // account whose credentials fell back to plaintext.
        $this->query(
            "INSERT INTO `" . $this->table() . "` SET"
            . " `provider_code` = '" . $this->escape($providerCode) . "',"
            . " `account_label` = '" . $this->escape($accountLabel) . "',"
            . " `environment` = '" . $this->escape($environment) . "',"
            . " `status` = 0,"
            . " `public_config_json` = '" . $this->escape($publicJson) . "',"
            . " `credentials_encrypted_json` = NULL,"
            . " `date_added` = UTC_TIMESTAMP(), `date_modified` = UTC_TIMESTAMP()"
        );

        $gatewayId = method_exists($this->db, 'getLastId') ? (int)$this->db->getLastId() : 0;

        if ($gatewayId <= 0) {
            throw new \RuntimeException('gateway account store: new account ID was not returned by the database');
        }

        $credentialJson = $this->vault->sealAll($gatewayId, $providerCode, $secretFields);
        $this->query(
            "UPDATE `" . $this->table() . "` SET"
            . " `credentials_encrypted_json` = '" . $this->escape($credentialJson) . "',"
            . " `status` = '" . (int)$enabled . "', `date_modified` = UTC_TIMESTAMP()"
            . " WHERE `gateway_id` = '" . $gatewayId . "' AND `status` = 0"
        );

        return new GatewayAccount($gatewayId, $providerCode, $accountLabel, $environment, $enabled, $publicConfig);
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
        $existing = $this->find($gatewayId);

        if ($existing === null) {
            throw new \OutOfBoundsException('gateway account store: account was not found');
        }

        if ($enabled) {
            $this->assertInstalledProvider($existing->providerCode);
        }

        $this->validateMetadata(
            $gatewayId,
            $existing->providerCode,
            $accountLabel,
            $environment,
            $enabled,
            $publicConfig
        );
        $query = $this->query(
            "SELECT `credentials_encrypted_json` FROM `" . $this->table() . "`"
            . " WHERE `gateway_id` = '" . $gatewayId . "' LIMIT 1"
        );
        $row = ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];

        if ($row === []) {
            throw new \OutOfBoundsException('gateway account store: account was not found');
        }

        $credentialJson = $this->vault->mergeJson(
            $gatewayId,
            $existing->providerCode,
            (string)($row['credentials_encrypted_json'] ?? ''),
            $secretUpdates,
            $clearFields
        );

        // Never re-enable an account whose stored credentials cannot be opened
        // by the currently configured application key.
        if ($enabled) {
            $this->vault->openJson($gatewayId, $existing->providerCode, $credentialJson);
        }

        $publicJson = json_encode($publicConfig, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $this->query(
            "UPDATE `" . $this->table() . "` SET"
            . " `account_label` = '" . $this->escape($accountLabel) . "',"
            . " `environment` = '" . $this->escape($environment) . "',"
            . " `status` = '" . (int)$enabled . "',"
            . " `public_config_json` = '" . $this->escape($publicJson) . "',"
            . " `credentials_encrypted_json` = '" . $this->escape($credentialJson) . "',"
            . " `date_modified` = UTC_TIMESTAMP()"
            . " WHERE `gateway_id` = '" . $gatewayId . "'"
        );

        return new GatewayAccount(
            $gatewayId,
            $existing->providerCode,
            $accountLabel,
            $environment,
            $enabled,
            $publicConfig
        );
    }

    private function assertInstalledProvider(string $providerCode): void {
        $adapter = $this->registry->get($providerCode);

        if ($adapter->code() !== $providerCode) {
            throw new \LogicException('gateway account store: registered provider code mismatch');
        }
    }

    /** @param array<string, mixed> $publicConfig */
    private function validateMetadata(
        int $gatewayId,
        string $providerCode,
        string $accountLabel,
        string $environment,
        bool $enabled,
        array $publicConfig
    ): void {
        // Instantiation centralizes validation of labels, environment and secret-like config fields.
        new GatewayAccount(max(1, $gatewayId), $providerCode, $accountLabel, $environment, $enabled, $publicConfig);
    }

    /** @return array<string, mixed> */
    private function decodePublicConfig(string $json): array {
        if ($json === '') {
            return [];
        }

        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new \RuntimeException('gateway account store: public configuration is invalid', 0, $e);
        }

        if (!is_array($data)) {
            throw new \RuntimeException('gateway account store: public configuration is invalid');
        }

        return $data;
    }

    private function table(): string {
        return $this->prefix . 'jow_gateway';
    }

    private function query(string $sql): mixed {
        return $this->db->query($sql);
    }

    private function escape(string $value): string {
        return (string)$this->db->escape($value);
    }
}
