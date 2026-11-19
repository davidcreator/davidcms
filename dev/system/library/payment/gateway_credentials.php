<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/** In-memory credential bag. Debug output reveals field names, never values. */
final class GatewayCredentials {
    /** @var array<string, string> */
    private array $values;

    /** @param array<string, string> $values */
    public function __construct(#[\SensitiveParameter] array $values) {
        $this->values = [];

        foreach ($values as $field => $value) {
            if (!is_string($field) || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $field) !== 1
                || !is_string($value) || strlen($value) > 8192) {
                throw new \InvalidArgumentException('gateway credentials: invalid field');
            }

            if ($value !== '') {
                $this->values[$field] = $value;
            }
        }
    }

    public function has(string $field): bool {
        return isset($this->values[$field]);
    }

    public function get(string $field): string {
        return $this->values[$field] ?? '';
    }

    /** @return list<string> */
    public function names(): array {
        return array_keys($this->values);
    }

    /** @return array{credential_fields: list<string>} */
    public function __debugInfo(): array {
        return ['credential_fields' => $this->names()];
    }
}
