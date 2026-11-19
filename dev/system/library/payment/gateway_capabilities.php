<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * A provider's explicit payment-method/currency matrix and native FX pairs.
 * Currency precision is provider metadata; an unlisted pair fails closed.
 */
final class GatewayCapabilities {
    /** @var array<string, list<string>> */
    private array $methods;

    /** @var array<string, int> */
    private array $currencyFractionDigits;

    /** @var array<string, list<string>> */
    private array $fxPairs;

    /**
     * @param array<string, list<string>> $methodsByCurrency map method code => supported ISO currency codes
     * @param array<string, int>          $currencyFractionDigits map ISO currency code => minor-unit digits
     * @param array<string, list<string>> $fxPairs map source currency => natively quoted target currencies
     */
    public function __construct(array $methodsByCurrency, array $currencyFractionDigits, array $fxPairs = []) {
        if ($methodsByCurrency === [] || $currencyFractionDigits === []) {
            throw new \InvalidArgumentException('gateway capabilities: at least one method and currency are required');
        }

        $this->currencyFractionDigits = [];

        foreach ($currencyFractionDigits as $currency => $fractionDigits) {
            if (!is_string($currency) || preg_match('/^[A-Z]{3}$/D', $currency) !== 1
                || !is_int($fractionDigits) || $fractionDigits < 0 || $fractionDigits > 8) {
                throw new \InvalidArgumentException('gateway capabilities: invalid currency metadata');
            }

            $this->currencyFractionDigits[$currency] = $fractionDigits;
        }

        $this->methods = [];

        foreach ($methodsByCurrency as $method => $currencies) {
            if (!is_string($method) || preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $method) !== 1
                || !is_array($currencies) || $currencies === []) {
                throw new \InvalidArgumentException('gateway capabilities: invalid payment method');
            }

            $supported = [];

            foreach ($currencies as $currency) {
                if (!is_string($currency) || !isset($this->currencyFractionDigits[$currency])) {
                    throw new \InvalidArgumentException('gateway capabilities: method references an unknown currency');
                }

                if (in_array($currency, $supported, true)) {
                    throw new \InvalidArgumentException('gateway capabilities: duplicate method/currency pair');
                }

                $supported[] = $currency;
            }

            $this->methods[$method] = $supported;
        }

        $this->fxPairs = [];

        foreach ($fxPairs as $from => $targets) {
            if (!is_string($from) || !isset($this->currencyFractionDigits[$from]) || !is_array($targets)) {
                throw new \InvalidArgumentException('gateway capabilities: invalid FX source currency');
            }

            foreach ($targets as $to) {
                if (!is_string($to) || !isset($this->currencyFractionDigits[$to]) || $to === $from) {
                    throw new \InvalidArgumentException('gateway capabilities: invalid FX target currency');
                }

                if (in_array($to, $this->fxPairs[$from] ?? [], true)) {
                    throw new \InvalidArgumentException('gateway capabilities: duplicate FX pair');
                }

                $this->fxPairs[$from][] = $to;
            }
        }
    }

    public function supports(string $method, string $currency): bool {
        return in_array($currency, $this->methods[$method] ?? [], true);
    }

    public function fractionDigits(string $currency): int {
        if (!array_key_exists($currency, $this->currencyFractionDigits)) {
            throw new \InvalidArgumentException('gateway capabilities: currency is not supported');
        }

        return $this->currencyFractionDigits[$currency];
    }

    public function supportsFxQuote(string $sourceCurrency, string $targetCurrency): bool {
        return in_array($targetCurrency, $this->fxPairs[$sourceCurrency] ?? [], true);
    }

    /** @return array<string, list<string>> */
    public function methods(): array {
        return $this->methods;
    }

    /** @return array<string, int> */
    public function currencies(): array {
        return $this->currencyFractionDigits;
    }

    /** @return array<string, list<string>> */
    public function fxPairs(): array {
        return $this->fxPairs;
    }
}
