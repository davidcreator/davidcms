<?php declare(strict_types=1);
/**
 * Exact monetary value for the JOW multi-gateway core.
 * Amounts enter as decimal strings, never binary floats. Fraction digits are
 * supplied by the selected gateway capability for the transaction currency.
 */
namespace Reamur\System\Library\Payment;

final class Money {
    private const MAX_INTEGER_DIGITS = 20;
    private const MAX_FRACTION_DIGITS = 8;

    private readonly string $decimalAmount;
    private readonly string $minorAmount;

    public readonly string $currency;
    public readonly int $fractionDigits;

    public function __construct(string $amount, string $currency, int $fractionDigits) {
        if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new \InvalidArgumentException('payment money: currency must be an uppercase ISO-4217 code');
        }

        if ($fractionDigits < 0 || $fractionDigits > self::MAX_FRACTION_DIGITS) {
            throw new \InvalidArgumentException('payment money: unsupported currency precision');
        }

        $amount = trim($amount);

        if (preg_match('/^\d+(?:\.\d+)?$/D', $amount) !== 1) {
            throw new \InvalidArgumentException('payment money: amount must be a non-negative decimal string');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        if (strlen($whole) > self::MAX_INTEGER_DIGITS) {
            throw new \InvalidArgumentException('payment money: amount is too large');
        }

        if (strlen($fraction) > $fractionDigits) {
            $excess = substr($fraction, $fractionDigits);

            if (trim($excess, '0') !== '') {
                throw new \InvalidArgumentException('payment money: amount has more precision than the currency allows');
            }

            $fraction = substr($fraction, 0, $fractionDigits);
        }

        $fraction = str_pad($fraction, $fractionDigits, '0');
        $this->decimalAmount = $fractionDigits > 0 ? $whole . '.' . $fraction : $whole;
        $minorAmount = ltrim($whole . $fraction, '0');
        $this->minorAmount = $minorAmount === '' ? '0' : $minorAmount;
        $this->currency = $currency;
        $this->fractionDigits = $fractionDigits;
    }

    public static function fromMinorUnits(string $minorAmount, string $currency, int $fractionDigits): self {
        if ($fractionDigits < 0 || $fractionDigits > self::MAX_FRACTION_DIGITS) {
            throw new \InvalidArgumentException('payment money: unsupported currency precision');
        }

        if (preg_match('/^\d+$/D', $minorAmount) !== 1) {
            throw new \InvalidArgumentException('payment money: minor amount must contain digits only');
        }

        $minorAmount = ltrim($minorAmount, '0');
        $minorAmount = $minorAmount === '' ? '0' : $minorAmount;

        if ($fractionDigits === 0) {
            $decimal = $minorAmount;
        } else {
            $digits = str_pad($minorAmount, $fractionDigits + 1, '0', STR_PAD_LEFT);
            $decimal = substr($digits, 0, -$fractionDigits) . '.' . substr($digits, -$fractionDigits);
        }

        return new self($decimal, $currency, $fractionDigits);
    }

    public function amount(): string {
        return $this->decimalAmount;
    }

    public function minorUnits(): string {
        return $this->minorAmount;
    }

    public function isPositive(): bool {
        return $this->minorAmount !== '0';
    }

    public function equals(self $other): bool {
        return $this->currency === $other->currency
            && $this->fractionDigits === $other->fractionDigits
            && $this->minorAmount === $other->minorAmount;
    }

    public function compareTo(self $other): int {
        if ($this->currency !== $other->currency || $this->fractionDigits !== $other->fractionDigits) {
            throw new \InvalidArgumentException('payment money: cannot compare different currency units');
        }

        if (strlen($this->minorAmount) !== strlen($other->minorAmount)) {
            return strlen($this->minorAmount) <=> strlen($other->minorAmount);
        }

        return strcmp($this->minorAmount, $other->minorAmount) <=> 0;
    }
}
