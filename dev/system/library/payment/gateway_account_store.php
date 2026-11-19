<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/** Secure persistent metadata/credential store for configured gateway accounts. */
interface GatewayAccountStore {
    public function find(int $gatewayId): ?GatewayAccount;

    public function credentials(int $gatewayId): GatewayCredentials;

    /** @param array<string, mixed> $publicConfig @param array<string, string> $secretFields */
    public function create(
        string $providerCode,
        string $accountLabel,
        string $environment,
        bool $enabled,
        array $publicConfig,
        array $secretFields
    ): GatewayAccount;

    /**
     * Omitted/blank secret updates preserve stored ciphertext. Use clearFields
     * for intentional removal; callers never need to read secrets back.
     *
     * @param array<string, mixed>  $publicConfig
     * @param array<string, string> $secretUpdates
     * @param list<string>          $clearFields
     */
    public function update(
        int $gatewayId,
        string $accountLabel,
        string $environment,
        bool $enabled,
        array $publicConfig,
        array $secretUpdates = [],
        array $clearFields = []
    ): GatewayAccount;
}
