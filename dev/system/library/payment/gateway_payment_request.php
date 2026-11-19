<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/** A payment request can originate from an order or an internal JOW charge. */
final class GatewayPaymentRequest {
    public readonly string $sourceType;
    public readonly int $sourceId;
    public readonly Money $sourceAmount;
    public readonly string $chargeCurrency;
    public readonly string $paymentMethod;
    public readonly string $idempotencyKey;
    public readonly string $title;
    public readonly string $returnUrl;
    public readonly string $notificationUrl;

    public function __construct(
        string $sourceType,
        int $sourceId,
        Money $sourceAmount,
        string $chargeCurrency,
        string $paymentMethod,
        string $idempotencyKey,
        string $title,
        string $returnUrl,
        string $notificationUrl,
    ) {
        if (!in_array($sourceType, ['order', 'jow_charge'], true) || $sourceId <= 0) {
            throw new \InvalidArgumentException('gateway payment request: invalid source');
        }

        if (!$sourceAmount->isPositive() || preg_match('/^[A-Z]{3}$/D', $chargeCurrency) !== 1
            || preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $paymentMethod) !== 1) {
            throw new \InvalidArgumentException('gateway payment request: invalid amount, currency or method');
        }

        if (preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $idempotencyKey) !== 1) {
            throw new \InvalidArgumentException('gateway payment request: invalid idempotency key');
        }

        $title = trim($title);

        if ($title === '' || strlen($title) > 255 || preg_match('/[\r\n\0]/', $title) === 1) {
            throw new \InvalidArgumentException('gateway payment request: invalid title');
        }

        self::assertHttpsUrl($returnUrl);
        self::assertHttpsUrl($notificationUrl);

        $this->sourceType = $sourceType;
        $this->sourceId = $sourceId;
        $this->sourceAmount = $sourceAmount;
        $this->chargeCurrency = $chargeCurrency;
        $this->paymentMethod = $paymentMethod;
        $this->idempotencyKey = $idempotencyKey;
        $this->title = $title;
        $this->returnUrl = $returnUrl;
        $this->notificationUrl = $notificationUrl;
    }

    private static function assertHttpsUrl(string $url): void {
        $parts = parse_url($url);

        if ($url === '' || preg_match('/[\r\n\0]/', $url) === 1 || !is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('gateway payment request: callback and return URLs must be valid HTTPS URLs');
        }
    }
}
