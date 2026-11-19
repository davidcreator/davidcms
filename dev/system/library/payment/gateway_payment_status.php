<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

final class GatewayPaymentStatus {
    public const PENDING = 'pending';
    public const REQUIRES_ACTION = 'requires_action';
    public const AUTHORIZED = 'authorized';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    public const REFUNDED = 'refunded';
    public const DISPUTED = 'disputed';

    public static function isKnown(string $status): bool {
        return in_array($status, [
            self::PENDING, self::REQUIRES_ACTION, self::AUTHORIZED, self::PAID,
            self::FAILED, self::CANCELLED, self::EXPIRED, self::REFUNDED, self::DISPUTED,
        ], true);
    }

    /** Safe monotonic transition policy for normalized provider notifications. */
    public static function canTransition(string $from, string $to): bool {
        if (!self::isKnown($from) || !self::isKnown($to)) {
            return false;
        }

        if ($from === $to) {
            return true;
        }

        $allowed = [
            self::PENDING => [self::REQUIRES_ACTION, self::AUTHORIZED, self::PAID, self::FAILED, self::CANCELLED, self::EXPIRED, self::DISPUTED],
            self::REQUIRES_ACTION => [self::AUTHORIZED, self::PAID, self::FAILED, self::CANCELLED, self::EXPIRED],
            self::AUTHORIZED => [self::PAID, self::CANCELLED, self::EXPIRED, self::DISPUTED],
            self::PAID => [self::REFUNDED, self::DISPUTED],
            self::DISPUTED => [self::PAID, self::REFUNDED],
            self::FAILED => [],
            self::CANCELLED => [],
            self::EXPIRED => [],
            self::REFUNDED => [],
        ];

        return in_array($to, $allowed[$from], true);
    }
}
