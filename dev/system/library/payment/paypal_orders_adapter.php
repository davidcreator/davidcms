<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * PayPal Orders API v2 adapter for JOW's platform-owned account.
 *
 * The payer approves on a PayPal-hosted page; CHECKOUT.ORDER.APPROVED is
 * completed server-side with an idempotent capture. State is applied only
 * after the signed webhook is verified against
 * POST /v1/notifications/verify-webhook-signature and the order is queried
 * from GET /v2/checkout/orders/{id}. The order is the source of truth.
 */
final class PaypalOrdersAdapter implements GatewayAdapter {
    private ?PaypalOrdersClient $client;

    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
    public function __construct(?PaypalOrdersClient $client = null, array $config = []) {
        $this->client = $client;
        $this->config = $config;
    }

    public function code(): string {
        return 'paypal';
    }

    public function label(): string {
        return 'PayPal';
    }

    public function capabilities(): GatewayCapabilities {
        return new GatewayCapabilities(
            ['checkout_orders' => ['BRL', 'USD', 'EUR']],
            ['BRL' => 2, 'USD' => 2, 'EUR' => 2]
        );
    }

    public function quote(Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?FxQuote {
        if ($sourceAmount->currency !== $chargeCurrency) {
            throw new \DomainException('PayPal JOW account does not provide foreign-exchange quotes');
        }

        return null;
    }

    public function createPayment(
        GatewayPaymentRequest $request,
        GatewayAccount $account,
        GatewayCredentials $credentials,
        ?FxQuote $quote
    ): GatewayPaymentResult {
        if ($account->providerCode !== $this->code() || $request->paymentMethod !== 'checkout_orders'
            || $quote !== null || !in_array($request->chargeCurrency, ['BRL', 'USD', 'EUR'], true)
            || $request->sourceAmount->currency !== $request->chargeCurrency) {
            throw new \DomainException('PayPal JOW account received an unsupported payment request');
        }

        $clientId = $credentials->get('client_id');
        $clientSecret = $credentials->get('client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('PayPal JOW platform client credentials are not configured');
        }

        // PayPal limits invoice_id to 127 characters and custom_id to 256.
        if (strlen($request->idempotencyKey) > 127) {
            throw new \DomainException('PayPal JOW idempotency key exceeds the Orders API reference limit');
        }

        $baseUrl = PaypalOrdersClient::baseUrlFor($account->environment, $this->config);
        $token = $this->client()->oauthToken($baseUrl, $clientId, $clientSecret);
        $http = (int)($token['_http_status'] ?? 0);
        $accessToken = self::scalarString($token['access_token'] ?? null);

        if ($http < 200 || $http >= 300 || $accessToken === '') {
            throw new \RuntimeException('PayPal OAuth token request failed (HTTP ' . $http . ')');
        }

        $currency = $request->chargeCurrency;
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id' => $request->idempotencyKey,
                'invoice_id' => $request->idempotencyKey,
                'description' => mb_substr($request->title, 0, 127),
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $request->sourceAmount->amount(),
                ],
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'user_action' => 'PAY_NOW',
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'shipping_preference' => 'NO_SHIPPING',
                        'landing_page' => 'NO_PREFERENCE',
                        'return_url' => $request->returnUrl,
                        'cancel_url' => $request->returnUrl,
                    ],
                ],
            ],
        ];

        if ($request->payerEmail !== '') {
            $payload['payer'] = ['email_address' => $request->payerEmail];
        }

        $order = $this->client()->createOrder($baseUrl, $accessToken, $payload, $request->idempotencyKey);
        $http = (int)($order['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300) {
            // Provider response details may contain private customer or account data.
            throw new \RuntimeException('PayPal Orders API did not create the order (HTTP ' . $http . ')');
        }

        $orderId = self::scalarString($order['id'] ?? null);
        $customId = self::firstCustomId($order);
        $checkoutUrl = self::payerActionUrl($order);
        $returnedCurrency = self::scalarString(
            $order['purchase_units'][0]['amount']['currency_code'] ?? null
        );
        $returnedValue = self::scalarString($order['purchase_units'][0]['amount']['value'] ?? null);

        if (preg_match('/^[A-Za-z0-9_-]{6,64}$/D', $orderId) !== 1
            || !hash_equals($request->idempotencyKey, $customId)
            || $returnedCurrency !== $currency
            || !self::sameAmount($returnedValue, $request->sourceAmount->amount())
            || !self::isHttps($checkoutUrl)) {
            throw new \RuntimeException('PayPal Orders API returned an incomplete or mismatched order');
        }

        $status = self::normalizeOrderStatus(self::scalarString($order['status'] ?? null));

        if ($status === null) {
            throw new \RuntimeException('PayPal Orders API returned an unsupported order status');
        }

        return new GatewayPaymentResult(
            $request->idempotencyKey,
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

        $clientId = $credentials->get('client_id');
        $clientSecret = $credentials->get('client_secret');
        $webhookId = $credentials->get('webhook_id');

        if ($clientId === '' || $clientSecret === '' || strlen($webhookId) < 8 || strlen($webhookId) > 255) {
            return GatewayWebhookResult::retry('credentials_missing');
        }

        $event = self::decodeBody($notification->body);

        if ($event === [] || !is_string($event['id'] ?? null)) {
            return GatewayWebhookResult::ignored('malformed_event_body');
        }

        $baseUrl = PaypalOrdersClient::baseUrlFor($account->environment, $this->config);
        $accessToken = $this->token($baseUrl, $clientId, $clientSecret);
        $verificationStatus = $this->verifySignature($baseUrl, $accessToken, $webhookId, $notification, $event);

        if ($verificationStatus === 'FAILURE') {
            return GatewayWebhookResult::ignored('signature_rejected');
        }

        if ($verificationStatus !== 'SUCCESS') {
            return GatewayWebhookResult::retry('verification_inconclusive');
        }

        $eventType = self::scalarString($event['event_type'] ?? null);

        try {
            return match ($eventType) {
                'CHECKOUT.ORDER.APPROVED',
                'CHECKOUT.ORDER.COMPLETED' => $this->verifyOrderEvent($event, $baseUrl, $accessToken, $eventType),
                'PAYMENT.CAPTURE.COMPLETED',
                'PAYMENT.CAPTURE.DENIED',
                'PAYMENT.CAPTURE.REFUNDED',
                'PAYMENT.CAPTURE.REVERSED' => $this->verifyCaptureEvent($event, $baseUrl, $accessToken),
                default => GatewayWebhookResult::ignored('unhandled_event_type'),
            };
        } catch (\RuntimeException) {
            return GatewayWebhookResult::retry('provider_unavailable');
        } catch (\UnexpectedValueException) {
            return GatewayWebhookResult::retry('invalid_provider_amount');
        }
    }

    private function verifyOrderEvent(array $event, string $baseUrl, string $accessToken, string $eventType): GatewayWebhookResult {
        $orderId = self::scalarString($event['resource']['id'] ?? null);

        if (preg_match('/^[A-Za-z0-9_-]{6,64}$/D', $orderId) !== 1) {
            return GatewayWebhookResult::ignored('invalid_order_id');
        }

        // An approved order is completed server-side with an idempotent
        // capture before anything is reconciled.
        if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
            $captureRequestId = self::captureRequestId(
                self::scalarString($event['resource']['purchase_units'][0]['custom_id'] ?? null)
            );

            if ($captureRequestId === '') {
                return GatewayWebhookResult::ignored('order_not_reconciled');
            }

            $this->client()->captureOrder($baseUrl, $accessToken, $orderId, $captureRequestId);
        }

        $order = $this->client()->getOrder($baseUrl, $accessToken, $orderId);
        $http = (int)($order['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300) {
            return GatewayWebhookResult::retry('provider_unavailable');
        }

        return $this->reconcileOrder($order, $orderId);
    }

    private function verifyCaptureEvent(array $event, string $baseUrl, string $accessToken): GatewayWebhookResult {
        $resource = $event['resource'] ?? [];

        if (!is_array($resource)) {
            return GatewayWebhookResult::ignored('malformed_event_object');
        }

        $orderId = self::captureEventOrderId($resource);

        if ($orderId === '') {
            return GatewayWebhookResult::ignored('capture_without_order');
        }

        $order = $this->client()->getOrder($baseUrl, $accessToken, $orderId);
        $http = (int)($order['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300) {
            return GatewayWebhookResult::retry('provider_unavailable');
        }

        return $this->reconcileOrder($order, $orderId);
    }

    private function reconcileOrder(array $order, string $orderId): GatewayWebhookResult {
        $confirmedId = self::scalarString($order['id'] ?? null);
        $reference = self::firstCustomId($order);
        $unitAmount = $order['purchase_units'][0]['amount'] ?? [];

        if (!hash_equals($orderId, $confirmedId)
            || preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $reference) !== 1
            || !is_array($unitAmount)) {
            return GatewayWebhookResult::ignored('order_not_reconciled');
        }

        $currency = self::scalarString($unitAmount['currency_code'] ?? null);

        if (!in_array($currency, ['BRL', 'USD', 'EUR'], true)) {
            return GatewayWebhookResult::ignored('order_not_reconciled');
        }

        try {
            $amount = self::decimalMoney($unitAmount['value'] ?? null, $currency);
        } catch (\UnexpectedValueException) {
            return GatewayWebhookResult::retry('invalid_provider_amount');
        }

        $orderStatus = self::scalarString($order['status'] ?? null);
        $capture = self::firstCapture($order);
        $captureId = $capture === [] ? '' : self::scalarString($capture['id'] ?? null);
        $status = self::normalizeOrderStatus($orderStatus);

        // The capture inside the queried order is authoritative: a completed
        // order whose capture is refunded/partially refunded must map to the
        // refund state, not to paid.
        if ($capture !== []) {
            $captureStatus = self::normalizeCaptureStatus(self::scalarString($capture['status'] ?? null));

            if ($captureStatus !== null) {
                $status = $captureStatus;
            }
        }

        if ($status === null) {
            return GatewayWebhookResult::retry('unknown_provider_status');
        }

        $eventDigest = self::orderDigest($orderId, $order, $status, $amount, $captureId);
        $eventId = 'pp-order-' . substr(hash('sha256', $orderId . "\0" . $eventDigest), 0, 52);

        return GatewayWebhookResult::verified($eventId, $reference, $captureId, $status, $eventDigest, $amount);
    }

    private function token(string $baseUrl, string $clientId, string $clientSecret): string {
        $token = $this->client()->oauthToken($baseUrl, $clientId, $clientSecret);
        $http = (int)($token['_http_status'] ?? 0);
        $accessToken = self::scalarString($token['access_token'] ?? null);

        if ($http < 200 || $http >= 300 || $accessToken === '') {
            throw new \RuntimeException('PayPal OAuth token request failed (HTTP ' . $http . ')');
        }

        return $accessToken;
    }

    /** @param array<string, mixed> $event */
    private function verifySignature(
        string $baseUrl,
        string $accessToken,
        string $webhookId,
        Notification $notification,
        array $event
    ): string {
        $transmissionId = $notification->header('paypal-transmission-id');
        $transmissionTime = $notification->header('paypal-transmission-time');
        $transmissionSig = $notification->header('paypal-transmission-sig');
        $certUrl = $notification->header('paypal-cert-url');
        $authAlgo = $notification->header('paypal-auth-algo');

        if ($transmissionId === '' || $transmissionTime === '' || $transmissionSig === ''
            || $certUrl === '' || $authAlgo === ''
            || preg_match('/[\r\n\0]/', $transmissionId . $transmissionTime . $transmissionSig . $certUrl . $authAlgo) === 1
            || !PaypalOrdersClient::validBaseUrl($certUrl)) {
            return 'FAILURE';
        }

        $response = $this->client()->verifyWebhookSignature($baseUrl, $accessToken, [
            'auth_algo' => $authAlgo,
            'cert_url' => $certUrl,
            'transmission_id' => $transmissionId,
            'transmission_sig' => $transmissionSig,
            'transmission_time' => $transmissionTime,
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ]);
        $http = (int)($response['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300) {
            return 'INCONCLUSIVE';
        }

        $status = self::scalarString($response['verification_status'] ?? null);

        if ($status !== 'SUCCESS' && $status !== 'FAILURE') {
            return 'INCONCLUSIVE';
        }

        return $status;
    }

    private function client(): PaypalOrdersClient {
        return $this->client ??= new PaypalOrdersClient();
    }

    private static function normalizeOrderStatus(string $status): ?string {
        return match (strtoupper($status)) {
            'CREATED', 'SAVED' => GatewayPaymentStatus::REQUIRES_ACTION,
            'APPROVED' => GatewayPaymentStatus::AUTHORIZED,
            'COMPLETED' => GatewayPaymentStatus::PAID,
            'VOIDED' => GatewayPaymentStatus::CANCELLED,
            default => null,
        };
    }

    private static function normalizeCaptureStatus(string $status): ?string {
        return match (strtoupper($status)) {
            'COMPLETED' => GatewayPaymentStatus::PAID,
            'PENDING' => GatewayPaymentStatus::AUTHORIZED,
            'PARTIALLY_REFUNDED' => GatewayPaymentStatus::PARTIALLY_REFUNDED,
            'REFUNDED' => GatewayPaymentStatus::REFUNDED,
            'DECLINED' => GatewayPaymentStatus::FAILED,
            'VOIDED' => GatewayPaymentStatus::CANCELLED,
            default => null,
        };
    }

    /** @param array<string, mixed> $order */
    private static function firstCustomId(array $order): string {
        $units = $order['purchase_units'] ?? null;

        if (!is_array($units) || !isset($units[0]) || !is_array($units[0])) {
            return '';
        }

        return self::scalarString($units[0]['custom_id'] ?? null);
    }

    /** @param array<string, mixed> $order */
    private static function firstCapture(array $order): array {
        $payments = $order['purchase_units'][0]['payments'] ?? null;

        if (!is_array($payments) || !is_array($payments['captures'] ?? null) || !isset($payments['captures'][0])
            || !is_array($payments['captures'][0])) {
            return [];
        }

        return $payments['captures'][0];
    }

    /** @param array<string, mixed> $resource */
    private static function captureEventOrderId(array $resource): string {
        $related = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        if (is_scalar($related)) {
            $orderId = trim((string)$related);

            if (preg_match('/^[A-Za-z0-9_-]{6,64}$/D', $orderId) === 1) {
                return $orderId;
            }
        }

        // Fall back to the "up" link that points at the parent order.
        $links = $resource['links'] ?? [];

        if (!is_array($links)) {
            return '';
        }

        foreach ($links as $link) {
            if (!is_array($link) || strtolower(self::scalarString($link['rel'] ?? null)) !== 'up') {
                continue;
            }

            $href = self::scalarString($link['href'] ?? null);
            $path = (string)parse_url($href, PHP_URL_PATH);

            if (preg_match('#/v2/checkout/orders/([A-Za-z0-9_-]{6,64})$#D', $path, $m) === 1) {
                return $m[1];
            }
        }

        return '';
    }

    private static function captureRequestId(string $customId): string {
        if (preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $customId) !== 1) {
            return '';
        }

        return $customId . ':capture';
    }

    /** @param array<string, mixed> $order */
    private static function payerActionUrl(array $order): string {
        $links = $order['links'] ?? [];

        if (!is_array($links)) {
            return '';
        }

        foreach (['payer-action', 'payer_action', 'approve'] as $rel) {
            foreach ($links as $link) {
                if (is_array($link) && strtolower(self::scalarString($link['rel'] ?? null)) === $rel) {
                    $href = self::scalarString($link['href'] ?? null);

                    if (self::isHttps($href)) {
                        return $href;
                    }
                }
            }
        }

        return '';
    }

    private static function sameAmount(string $left, string $right): bool {
        // Pure string normalization: no bcmath dependency. Two amounts are
        // equal when their digits match after trimming insignificant zeros.
        $normalize = static function (string $value): string {
            if ($value === '' || preg_match('/^\d+(?:\.\d+)?$/D', $value) !== 1) {
                return '';
            }

            [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
            $whole = ltrim($whole, '0');
            $fraction = rtrim($fraction, '0');

            return ($whole === '' ? '0' : $whole) . ($fraction !== '' ? '.' . $fraction : '');
        };

        $left = $normalize($left);
        $right = $normalize($right);

        return $left !== '' && $right !== '' && $left === $right;
    }

    /** @param array<string, mixed> $order */
    private static function orderDigest(string $orderId, array $order, string $normalizedStatus, Money $amount, string $captureId): string {
        $material = [
            'order_id' => $orderId,
            'status' => self::scalarString($order['status'] ?? null),
            'normalized_status' => $normalizedStatus,
            'currency' => $amount->currency,
            'amount' => $amount->amount(),
            'capture_id' => $captureId,
            'capture_status' => self::scalarString($order['purchase_units'][0]['payments']['captures'][0]['status'] ?? null),
        ];

        return hash('sha256', json_encode($material, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function decimalMoney(mixed $amount, string $currency): Money {
        if (!is_string($amount) && !is_int($amount) && !is_float($amount)) {
            throw new \UnexpectedValueException('PayPal amount is missing');
        }

        return new Money((string)$amount, $currency, 2);
    }

    private static function isHttps(string $url): bool {
        if ($url === '' || preg_match('/[\r\n\0]/', $url) === 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    /** @return array<string, mixed> */
    private static function decodeBody(string $body): array {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function scalarString(mixed $value): string {
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
