<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

use Reamur\System\Library\MercadopagoMarketplace;

/**
 * Mercado Pago Orders API adapter for JOW's platform-owned account.
 *
 * This deliberately does not read Marketplace seller tokens or add a
 * marketplace_fee. Public seller checkout remains on the legacy OAuth
 * Marketplace adapter until its separate migration/cutover is approved.
 */
final class MercadoPagoGatewayAdapter implements GatewayAdapter {
    private ?MercadopagoMarketplace $api;

    public function __construct(?MercadopagoMarketplace $api = null) {
        $this->api = $api;
    }

    public function code(): string {
        return 'mp';
    }

    public function label(): string {
        return 'Mercado Pago';
    }

    public function capabilities(): GatewayCapabilities {
        return new GatewayCapabilities(
            ['checkout_pro' => ['BRL']],
            ['BRL' => 2]
        );
    }

    public function quote(Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?FxQuote {
        if ($sourceAmount->currency !== $chargeCurrency) {
            throw new \DomainException('Mercado Pago JOW account does not provide foreign-exchange quotes');
        }

        if ($chargeCurrency !== 'BRL') {
            throw new \DomainException('Mercado Pago JOW account supports BRL only');
        }

        return null;
    }

    public function createPayment(
        GatewayPaymentRequest $request,
        GatewayAccount $account,
        GatewayCredentials $credentials,
        ?FxQuote $quote
    ): GatewayPaymentResult {
        if ($account->providerCode !== $this->code() || $request->paymentMethod !== 'checkout_pro'
            || $request->sourceAmount->currency !== 'BRL' || $request->chargeCurrency !== 'BRL'
            || $quote !== null) {
            throw new \DomainException('Mercado Pago JOW account received an unsupported payment request');
        }

        $accessToken = $credentials->get('access_token');

        if ($accessToken === '') {
            throw new \RuntimeException('Mercado Pago JOW platform access token is not configured');
        }

        // The Orders API limits external_reference to 64 characters. Our JOW
        // idempotency key is also sent as that reference for reconciliation.
        if (strlen($request->idempotencyKey) > 64) {
            throw new \DomainException('Mercado Pago JOW idempotency key exceeds the Orders API reference limit');
        }

        if ($account->environment === 'sandbox' && $request->payerEmail !== ''
            && !str_ends_with(strtolower($request->payerEmail), '@testuser.com')) {
            throw new \DomainException('Mercado Pago sandbox checkout requires a test-user payer email ending in @testuser.com');
        }

        $amount = $request->sourceAmount->amount();
        $payload = [
            'type' => 'online',
            'processing_mode' => 'manual',
            'capture_mode' => 'automatic_async',
            'total_amount' => $amount,
            'external_reference' => $request->idempotencyKey,
            'description' => $request->title,
            'items' => [[
                'title' => $request->title,
                'quantity' => 1,
                'unit_measure' => 'unit',
                'unit_price' => $amount,
                'total_amount' => $amount,
            ]],
            'config' => [
                'online' => [
                    'success_url' => $request->returnUrl,
                    'pending_url' => $request->returnUrl,
                    'failure_url' => $request->returnUrl,
                    'auto_return' => 'approved',
                ],
            ],
        ];

        if ($request->payerEmail !== '') {
            $payload['payer'] = ['email' => $request->payerEmail];
        }

        $order = $this->api()->createOrder($payload, $accessToken, $request->idempotencyKey);
        $http = (int)($order['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300) {
            // Provider response details may contain private customer or account data.
            throw new \RuntimeException('Mercado Pago Orders API did not create the checkout order (HTTP ' . $http . ')');
        }

        $orderId = self::scalarString($order['id'] ?? null);
        $externalReference = self::scalarString($order['external_reference'] ?? null);
        $checkoutUrl = self::scalarString($order['checkout_url'] ?? null);
        $currency = self::scalarString($order['currency'] ?? $order['currency_id'] ?? null);

        if (preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $orderId) !== 1
            || !hash_equals($request->idempotencyKey, $externalReference)
            || $currency !== 'BRL' || $checkoutUrl === '') {
            throw new \RuntimeException('Mercado Pago Orders API returned an incomplete or mismatched checkout order');
        }

        $returnedAmount = self::money($order['total_amount'] ?? null, $currency);

        if (!$returnedAmount->equals($request->sourceAmount)) {
            throw new \RuntimeException('Mercado Pago Orders API returned a checkout amount different from the requested amount');
        }

        $status = self::normalizeOrderStatus(
            self::scalarString($order['status'] ?? null),
            self::scalarString($order['status_detail'] ?? null)
        );

        if ($status === null) {
            throw new \RuntimeException('Mercado Pago Orders API returned an unsupported checkout status');
        }

        return new GatewayPaymentResult(
            $orderId,
            GatewayCustomerAction::redirect($checkoutUrl),
            $request->sourceAmount,
            $status,
            null,
            ''
        );
    }

    public function verifyNotification(
        Notification $notification,
        GatewayAccount $account,
        GatewayCredentials $credentials
    ): GatewayWebhookResult {
        if ($account->providerCode !== $this->code()) {
            return GatewayWebhookResult::ignored('wrong_provider');
        }

        $body = self::decodeBody($notification->body);
        $queryType = self::firstScalar($notification->get, ['type', 'topic']);
        $bodyType = self::firstScalar($body, ['type', 'topic']);
        $type = $queryType !== '' ? $queryType : $bodyType;

        if ($type !== 'order' || ($queryType !== '' && $bodyType !== '' && $queryType !== $bodyType)) {
            return GatewayWebhookResult::ignored('not_order_event');
        }

        $queryId = self::firstScalar($notification->get, ['data.id', 'data_id']);
        $bodyId = self::firstScalar((array)($body['data'] ?? []), ['id']);

        if ($queryId !== '' && $bodyId !== '' && !hash_equals($queryId, $bodyId)) {
            return GatewayWebhookResult::ignored('order_id_mismatch');
        }

        $orderId = $queryId !== '' ? $queryId : $bodyId;

        if (preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $orderId) !== 1) {
            return GatewayWebhookResult::ignored('invalid_order_id');
        }

        $accessToken = $credentials->get('access_token');
        $webhookSecret = $credentials->get('webhook_secret');

        if ($accessToken === '' || strlen($webhookSecret) < 16 || strlen($webhookSecret) > 255) {
            return GatewayWebhookResult::retry('credentials_missing');
        }

        if (!self::signatureValid($webhookSecret, $orderId, $notification->header('x-signature'), $notification->header('x-request-id'))) {
            return GatewayWebhookResult::ignored('signature_rejected');
        }

        $order = $this->api()->getOrder($orderId, $accessToken);
        $http = (int)($order['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300) {
            return GatewayWebhookResult::retry('provider_unavailable');
        }

        $confirmedId = self::scalarString($order['id'] ?? null);
        $reference = self::scalarString($order['external_reference'] ?? null);
        $currency = self::scalarString($order['currency'] ?? $order['currency_id'] ?? null);

        if (!hash_equals($orderId, $confirmedId)
            || preg_match('/^[A-Za-z0-9._:-]{16,64}$/D', $reference) !== 1
            || $currency !== 'BRL') {
            return GatewayWebhookResult::ignored('order_not_reconciled');
        }

        try {
            $amount = self::money($order['total_amount'] ?? null, $currency);
        } catch (\Throwable) {
            return GatewayWebhookResult::retry('invalid_provider_amount');
        }

        $status = self::normalizeOrderStatus(
            self::scalarString($order['status'] ?? null),
            self::scalarString($order['status_detail'] ?? null)
        );

        if ($status === null) {
            return GatewayWebhookResult::retry('unknown_provider_status');
        }

        $paymentId = self::firstPaymentId($order);
        $eventDigest = self::eventDigest($order, $orderId, $status, $amount);
        $eventId = 'mp-order-' . substr(hash('sha256', $orderId . "\0" . $eventDigest), 0, 56);

        return GatewayWebhookResult::verified($eventId, $orderId, $paymentId, $status, $eventDigest, $amount);
    }

    /** Validate a Mercado Pago Orders API webhook signature (data.id is lowercase in the manifest). */
    public static function signatureValid(string $secret, string $resourceId, string $signatureHeader, string $requestId): bool {
        if ($secret === '' || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $resourceId) !== 1
            || preg_match('/[\r\n\0]/', $requestId) === 1) {
            return false;
        }

        $timestamp = '';
        $signature = '';

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if ($key === 'ts') {
                if ($timestamp !== '') {
                    return false;
                }
                $timestamp = $value;
            } elseif ($key === 'v1') {
                if ($signature !== '') {
                    return false;
                }
                $signature = $value;
            }
        }

        if ($timestamp === '' || !ctype_digit($timestamp) || $signature === ''
            || strlen($signature) !== 64 || !ctype_xdigit($signature)) {
            return false;
        }

        $manifest = [];
        $manifest[] = 'id:' . strtolower($resourceId);

        if ($requestId !== '') {
            $manifest[] = 'request-id:' . $requestId;
        }

        $manifest[] = 'ts:' . $timestamp;
        $expected = hash_hmac('sha256', implode(';', $manifest) . ';', $secret);

        return hash_equals($expected, strtolower($signature));
    }

    private function api(): MercadopagoMarketplace {
        return $this->api ??= new MercadopagoMarketplace();
    }

    private static function normalizeOrderStatus(string $status, string $detail): ?string {
        return match (strtolower($status)) {
            'created', 'processing' => GatewayPaymentStatus::REQUIRES_ACTION,
            'action_required' => strtolower($detail) === 'waiting_capture'
                ? GatewayPaymentStatus::AUTHORIZED
                : GatewayPaymentStatus::REQUIRES_ACTION,
            'processed' => strtolower($detail) === 'partially_refunded'
                ? GatewayPaymentStatus::PARTIALLY_REFUNDED
                : GatewayPaymentStatus::PAID,
            'canceled', 'cancelled' => GatewayPaymentStatus::CANCELLED,
            'failed' => GatewayPaymentStatus::FAILED,
            'expired' => GatewayPaymentStatus::EXPIRED,
            'refunded' => GatewayPaymentStatus::REFUNDED,
            'charged_back' => strtolower($detail) === 'reimbursed'
                ? GatewayPaymentStatus::REFUNDED
                : GatewayPaymentStatus::DISPUTED,
            default => null,
        };
    }

    /** @param array<string, mixed> $order */
    private static function firstPaymentId(array $order): string {
        $payments = $order['transactions']['payments'] ?? [];

        if (!is_array($payments) || !isset($payments[0]) || !is_array($payments[0])) {
            return '';
        }

        $id = self::scalarString($payments[0]['id'] ?? null);

        return strlen($id) <= 128 && preg_match('/[\r\n\0]/', $id) !== 1 ? $id : '';
    }

    /** @param array<string, mixed> $order */
    private static function eventDigest(array $order, string $orderId, string $normalizedStatus, Money $amount): string {
        $material = [
            'order_id' => $orderId,
            'status' => self::scalarString($order['status'] ?? null),
            'status_detail' => self::scalarString($order['status_detail'] ?? null),
            'normalized_status' => $normalizedStatus,
            'currency' => $amount->currency,
            'amount' => $amount->amount(),
            'total_paid_amount' => self::scalarString($order['total_paid_amount'] ?? null),
            'payment_id' => self::firstPaymentId($order),
        ];

        return hash('sha256', json_encode($material, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function money(mixed $amount, string $currency): Money {
        if (!is_string($amount) && !is_int($amount) && !is_float($amount)) {
            throw new \UnexpectedValueException('Mercado Pago amount is missing');
        }

        return new Money((string)$amount, $currency, 2);
    }

    /** @return array<string, mixed> */
    private static function decodeBody(string $body): array {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<array-key, mixed> $data @param list<string> $keys */
    private static function firstScalar(array $data, array $keys): string {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_scalar($value)) {
                $result = trim((string)$value);

                if ($result !== '') {
                    return $result;
                }
            }
        }

        return '';
    }

    private static function scalarString(mixed $value): string {
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
