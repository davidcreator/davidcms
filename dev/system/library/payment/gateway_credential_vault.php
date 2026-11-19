<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

use Reamur\System\Library\Security\SecretBox;

/**
 * Context-bound storage for gateway secrets. Unlike legacy SecretBox callers,
 * this vault fails closed rather than accepting the SecretBox plaintext fallback.
 */
final class GatewayCredentialVault {
    public function __construct(private readonly SecretBox $box) {}

    public function seal(int $gatewayId, string $providerCode, string $field, #[\SensitiveParameter] string $plain): string {
        $context = $this->context($gatewayId, $providerCode, $field);

        if ($plain === '') {
            return '';
        }

        if (strlen($plain) > 8192) {
            throw new \InvalidArgumentException('gateway credentials: secret is too large');
        }

        if (SecretBox::isSealed($plain)) {
            throw new \InvalidArgumentException('gateway credentials: submit plaintext through the secure form, not stored ciphertext');
        }

        if (!$this->box->canSeal()) {
            throw new \RuntimeException('gateway credentials: encryption key is unavailable or admin/catalog keys do not match');
        }

        $sealed = $this->box->seal($plain, $context);

        if (!SecretBox::isSealed($sealed)) {
            throw new \RuntimeException('gateway credentials: refusing to store an unencrypted secret');
        }

        return $sealed;
    }

    public function open(int $gatewayId, string $providerCode, string $field, string $stored): string {
        if ($stored === '') {
            return '';
        }

        if (!SecretBox::isSealed($stored)) {
            throw new \RuntimeException('gateway credentials: plaintext credentials are not accepted by the new store');
        }

        $context = $this->context($gatewayId, $providerCode, $field);

        if ($this->box->locked($stored, $context)) {
            throw new \RuntimeException('gateway credentials: stored secret cannot be opened with the configured key/context');
        }

        return $this->box->open($stored, $context);
    }

    /**
     * Seal a complete credential map for the gateway row's JSON column. Each
     * value gets its own SecretBox context; field names remain visible.
     *
     * @param array<string, string> $plainFields
     */
    public function sealAll(int $gatewayId, string $providerCode, array $plainFields): string {
        $validated = new GatewayCredentials($plainFields);
        $sealed = [];

        foreach ($validated->names() as $field) {
            $plain = $validated->get($field);
            if (!is_string($field) || !is_string($plain)) {
                throw new \InvalidArgumentException('gateway credentials: malformed credential map');
            }

            if ($plain !== '') {
                $sealed[$field] = $this->seal($gatewayId, $providerCode, $field, $plain);
            }
        }

        return json_encode($sealed, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** Decode and open the per-field ciphertext JSON stored for one account. */
    public function openJson(int $gatewayId, string $providerCode, string $storedJson): GatewayCredentials {
        if ($storedJson === '') {
            return new GatewayCredentials([]);
        }

        try {
            $fields = json_decode($storedJson, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('gateway credentials: stored credential document is invalid', 0, $e);
        }

        if (!is_array($fields)) {
            throw new \RuntimeException('gateway credentials: stored credential document is invalid');
        }

        return $this->openAll($gatewayId, $providerCode, $fields);
    }

    /**
     * Merge secret changes without decrypting or returning existing values.
     * Empty updates mean "keep current"; clearFields is explicit removal.
     *
     * @param array<string, string> $secretUpdates
     * @param list<string> $clearFields
     */
    public function mergeJson(
        int $gatewayId,
        string $providerCode,
        string $storedJson,
        array $secretUpdates,
        array $clearFields = []
    ): string {
        $stored = [];

        if ($storedJson !== '') {
            try {
                $decoded = json_decode($storedJson, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \RuntimeException('gateway credentials: stored credential document is invalid', 0, $e);
            }

            if (!is_array($decoded)) {
                throw new \RuntimeException('gateway credentials: stored credential document is invalid');
            }

            foreach ($decoded as $field => $ciphertext) {
                if (!is_string($field) || !is_string($ciphertext) || $ciphertext === '' || !SecretBox::isSealed($ciphertext)) {
                    throw new \RuntimeException('gateway credentials: refusing to preserve malformed or plaintext stored credentials');
                }

                $this->context($gatewayId, $providerCode, $field);
                $stored[$field] = $ciphertext;
            }
        }

        foreach ($clearFields as $field) {
            if (!is_string($field)) {
                throw new \InvalidArgumentException('gateway credentials: malformed clear-field list');
            }

            $this->context($gatewayId, $providerCode, $field);
            unset($stored[$field]);
        }

        $validated = new GatewayCredentials($secretUpdates);

        foreach ($validated->names() as $field) {
            $stored[$field] = $this->seal($gatewayId, $providerCode, $field, $validated->get($field));
        }

        ksort($stored);

        return json_encode($stored, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, string> $storedFields */
    public function openAll(int $gatewayId, string $providerCode, array $storedFields): GatewayCredentials {
        $plain = [];

        foreach ($storedFields as $field => $stored) {
            if (!is_string($field) || !is_string($stored)) {
                throw new \InvalidArgumentException('gateway credentials: malformed stored fields');
            }

            if ($stored !== '') {
                $plain[$field] = $this->open($gatewayId, $providerCode, $field, $stored);
            }
        }

        return new GatewayCredentials($plain);
    }

    private function context(int $gatewayId, string $providerCode, string $field): string {
        if ($gatewayId <= 0 || preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $providerCode) !== 1
            || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $field) !== 1) {
            throw new \InvalidArgumentException('gateway credentials: invalid context');
        }

        return SecretBox::extensionContext('jow_payments', 'gateway.' . $gatewayId . '.' . $providerCode . '.' . $field);
    }
}
