<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/** Explicit allowlist of gateway adapters; DB values never become class names. */
final class GatewayRegistry {
    /** @var array<string, GatewayAdapter> */
    private array $adapters = [];

    /** @param iterable<GatewayAdapter> $adapters */
    public function __construct(iterable $adapters = []) {
        foreach ($adapters as $adapter) {
            $this->register($adapter);
        }
    }

    public function register(GatewayAdapter $adapter): void {
        $code = $adapter->code();
        $label = trim($adapter->label());

        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $code) !== 1 || $label === '' || strlen($label) > 64) {
            throw new \InvalidArgumentException('gateway registry: adapter identity is invalid');
        }

        if (isset($this->adapters[$code])) {
            throw new \LogicException('gateway registry: duplicate adapter code');
        }

        $adapter->capabilities(); // force capability validation during registration
        $this->adapters[$code] = $adapter;
    }

    public function get(string $code): GatewayAdapter {
        if (!isset($this->adapters[$code])) {
            throw new \OutOfBoundsException('gateway registry: provider is not installed');
        }

        return $this->adapters[$code];
    }

    /** @return list<GatewayAdapter> */
    public function all(): array {
        return array_values($this->adapters);
    }
}
