<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

use DateTimeImmutable;

/** A gateway-issued, expiring quote. The provider's quoted amount is authoritative. */
final class FxQuote {
    public readonly string $providerCode;
    public readonly string $reference;
    public readonly Money $sourceAmount;
    public readonly Money $chargeAmount;
    public readonly string $rate;
    public readonly DateTimeImmutable $quotedAt;
    public readonly DateTimeImmutable $expiresAt;

    public function __construct(
        string $providerCode,
        string $reference,
        Money $sourceAmount,
        Money $chargeAmount,
        string $rate,
        DateTimeImmutable $quotedAt,
        DateTimeImmutable $expiresAt,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $providerCode) !== 1
            || trim($reference) === '' || strlen($reference) > 128
            || preg_match('/[\r\n\0]/', $reference) === 1) {
            throw new \InvalidArgumentException('payment FX quote: provider or quote reference is invalid');
        }

        if (!$sourceAmount->isPositive() || !$chargeAmount->isPositive()
            || $sourceAmount->currency === $chargeAmount->currency) {
            throw new \InvalidArgumentException('payment FX quote: conversion requires positive amounts in different currencies');
        }

        $rate = self::normalizeRate($rate);

        if ($expiresAt <= $quotedAt) {
            throw new \InvalidArgumentException('payment FX quote: expiry must be later than quote time');
        }

        $this->providerCode = $providerCode;
        $this->reference = trim($reference);
        $this->sourceAmount = $sourceAmount;
        $this->chargeAmount = $chargeAmount;
        $this->rate = $rate;
        $this->quotedAt = $quotedAt;
        $this->expiresAt = $expiresAt;
    }

    public function isUsableAt(DateTimeImmutable $at): bool {
        return $at >= $this->quotedAt && $at < $this->expiresAt;
    }

    private static function normalizeRate(string $rate): string {
        $rate = trim($rate);

        if (preg_match('/^\d+(?:\.\d{1,18})?$/D', $rate) !== 1
            || trim(str_replace('.', '', $rate), '0') === '') {
            throw new \InvalidArgumentException('payment FX quote: rate must be a positive decimal string');
        }

        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 18) {
            throw new \InvalidArgumentException('payment FX quote: rate is too large to persist safely');
        }

        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : $whole . '.' . $fraction;
    }
}
