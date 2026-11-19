<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/** Non-secret account configuration for one installed provider connection. */
final class GatewayAccount {
    /** @var array<string, mixed> */
    private array $publicConfig;

    /**
     * @param array<string, mixed> $publicConfig Adapter options only; credentials must go through GatewayCredentialVault.
     */
    public function __construct(
        public readonly int $gatewayId,
        public readonly string $providerCode,
        public readonly string $accountLabel,
        public readonly string $environment,
        public readonly bool $enabled,
        array $publicConfig = [],
    ) {
        if ($gatewayId <= 0 || preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $providerCode) !== 1) {
            throw new \InvalidArgumentException('gateway account: invalid identity');
        }

        if (strlen($accountLabel) > 64 || preg_match('/[\r\n\0]/', $accountLabel) === 1
            || !in_array($environment, ['sandbox', 'live'], true)) {
            throw new \InvalidArgumentException('gateway account: invalid label or environment');
        }

        self::assertSafePublicConfig($publicConfig);
        $encoded = json_encode($publicConfig, JSON_THROW_ON_ERROR);

        if (strlen($encoded) > 32768) {
            throw new \InvalidArgumentException('gateway account: public configuration is too large');
        }

        $this->publicConfig = $publicConfig;
    }

    /** @return array<string, mixed> */
    public function publicConfig(): array {
        return $this->publicConfig;
    }

    /** @return array{gateway_id: int, provider_code: string, account_label: string, environment: string, enabled: bool} */
    public function __debugInfo(): array {
        return [
            'gateway_id' => $this->gatewayId,
            'provider_code' => $this->providerCode,
            'account_label' => $this->accountLabel,
            'environment' => $this->environment,
            'enabled' => $this->enabled,
        ];
    }

    /** @param array<string, mixed> $config */
    private static function assertSafePublicConfig(array $config, int $depth = 0): void {
        if ($depth > 5 || count($config) > 128) {
            throw new \InvalidArgumentException('gateway account: public configuration structure is invalid');
        }

        foreach ($config as $key => $value) {
            if (is_int($key)) {
                if ($depth === 0 || !array_is_list($config)) {
                    throw new \InvalidArgumentException('gateway account: public configuration maps must use named fields');
                }
            } elseif (!is_string($key) || $key === '' || strlen($key) > 64
                || preg_match('/(?:secret|token|password|passwd|passphrase|credential|private|api_?key|access_?key|client_?key|bearer|signing_?key)/i', $key) === 1) {
                throw new \InvalidArgumentException('gateway account: secret-like fields are not allowed in public configuration');
            }

            if (is_array($value)) {
                self::assertSafePublicConfig($value, $depth + 1);
                continue;
            }

            if ($value !== null && !is_string($value) && !is_int($value) && !is_bool($value)) {
                throw new \InvalidArgumentException('gateway account: public configuration values must be scalar strings, integers, booleans or null');
            }

            if (is_string($value) && strlen($value) > 4096) {
                throw new \InvalidArgumentException('gateway account: public configuration value is too large');
            }
        }
    }
}
