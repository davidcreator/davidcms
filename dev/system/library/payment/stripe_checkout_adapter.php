<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * Stripe hosted Checkout Session adapter for JOW's platform-owned account.
 *
 * The payer is redirected to a Stripe-hosted page (mode=payment). Completion
 * is decided ONLY by the signed webhook reconciled against
 * GET /v1/checkout/sessions/{id} (or /v1/charges/{id} for refunds); the
 * success_url and the webhook body alone never settle a payment.
 */
final class StripeCheckoutAdapter implements GatewayAdapter, ReconcilableGatewayAdapter {
    /** Signature age tolerance in seconds (Stripe SDK default). */
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    private ?StripeCheckoutClient $client;

    public function __construct(?StripeCheckoutClient $client = null) {
        $this->client = $client;
    }

    public function code(): string {
        return 'stripe';
    }

    public function label(): string {
        return 'Stripe';
    }

    public function capabilities(): GatewayCapabilities {
        return new GatewayCapabilities(
            ['checkout_session' => ['BRL', 'USD', 'EUR']],
            ['BRL' => 2, 'USD' => 2, 'EUR' => 2]
        );
    }

    public function quote(Money $sourceAmount, string $chargeCurrency, GatewayAccount $account, GatewayCredentials $credentials): ?FxQuote {
        if ($sourceAmount->currency !== $chargeCurrency) {
            throw new \DomainException('Stripe JOW account does not provide foreign-exchange quotes');
        }

        return null;
    }

    public function createPayment(
        GatewayPaymentRequest $request,
        GatewayAccount $account,
        GatewayCredentials $credentials,
        ?FxQuote $quote
    ): GatewayPaymentResult {
        if ($account->providerCode !== $this->code() || $request->paymentMethod !== 'checkout_session'
            || $quote !== null || !in_array($request->chargeCurrency, ['BRL', 'USD', 'EUR'], true)
            || $request->sourceAmount->currency !== $request->chargeCurrency) {
            throw new \DomainException('Stripe JOW account received an unsupported payment request');
        }

        $secretKey = $credentials->get('secret_key');

        if ($secretKey === '') {
            throw new \RuntimeException('Stripe JOW platform secret key is not configured');
        }

        $currency = strtolower($request->chargeCurrency);
        $form = [
            'mode' => 'payment',
            'success_url' => $request->returnUrl,
            'cancel_url' => $request->returnUrl,
            'client_reference_id' => $request->idempotencyKey,
            'metadata[jow_source_type]' => $request->sourceType,
            'metadata[jow_source_id]' => (string)$request->sourceId,
            'metadata[jow_reference]' => $request->idempotencyKey,
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][unit_amount]' => $request->sourceAmount->minorUnits(),
            'line_items[0][price_data][product_data][name]' => mb_substr($request->title, 0, 500),
        ];

        if ($request->payerEmail !== '') {
            $form['customer_email'] = $request->payerEmail;
        }

        $session = $this->client()->createSession($form, $secretKey, $request->idempotencyKey);
        $http = (int)($session['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300) {
            // Provider response details may contain private customer or account data.
            throw new \RuntimeException('Stripe Checkout API did not create the session (HTTP ' . $http . ')');
        }

        $sessionId = self::scalarString($session['id'] ?? null);
        $sessionUrl = self::scalarString($session['url'] ?? null);
        $reference = self::scalarString($session['client_reference_id'] ?? null);
        $currencyReturned = self::scalarString($session['currency'] ?? null);

        if (preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $sessionId) !== 1
            || !hash_equals($request->idempotencyKey, $reference)
            || strtolower($currencyReturned) !== $currency
            || !self::isHttps($sessionUrl)) {
            throw new \RuntimeException('Stripe Checkout API returned an incomplete or mismatched session');
        }

        $amount = self::minorMoney($session['amount_total'] ?? null, strtoupper($currencyReturned));

        if (!$amount->equals($request->sourceAmount)) {
            throw new \RuntimeException('Stripe Checkout API returned a session amount different from the requested amount');
        }

        $status = self::normalizeSessionStatus(
            self::scalarString($session['status'] ?? null),
            self::scalarString($session['payment_status'] ?? null)
        );

        if ($status === null) {
            throw new \RuntimeException('Stripe Checkout API returned an unsupported session status');
        }

        // The ledger reference is the client_reference_id (our idempotency
        // key) so webhook events reconcile with the persisted attempt; the
        // session ID is validated but only travels inside event digests.
        return new GatewayPaymentResult(
            $request->idempotencyKey,
            GatewayCustomerAction::redirect($sessionUrl),
            $amount,
            $status,
            null,
            self::scalarString($session['payment_intent'] ?? null),
            $sessionId
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

        $secretKey = $credentials->get('secret_key');
        $webhookSecret = $credentials->get('webhook_secret');

        if ($secretKey === '' || strlen($webhookSecret) < 16 || strlen($webhookSecret) > 255) {
            return GatewayWebhookResult::retry('credentials_missing');
        }

        $body = $notification->body;
        $event = self::decodeBody($body);

        if ($event === [] || !self::signatureValid($webhookSecret, $body, $notification->header('stripe-signature'), time())) {
            return GatewayWebhookResult::ignored('signature_rejected');
        }

        $eventType = self::scalarString($event['type'] ?? null);
        $object = $event['data']['object'] ?? [];

        if (!is_array($object)) {
            return GatewayWebhookResult::ignored('malformed_event_object');
        }

        try {
            return match ($eventType) {
                'checkout.session.completed',
                'checkout.session.async_payment_succeeded',
                'checkout.session.async_payment_failed',
                'checkout.session.expired' => $this->verifySessionEvent($object, $eventType, $secretKey),
                'charge.refunded' => $this->verifyRefundEvent($object, $secretKey),
                default => GatewayWebhookResult::ignored('unhandled_event_type'),
            };
        } catch (\RuntimeException $e) {
            return GatewayWebhookResult::retry('provider_unavailable');
        } catch (\UnexpectedValueException) {
            return GatewayWebhookResult::retry('invalid_provider_amount');
        }
    }

    /**
     * Validate the Stripe-Signature header (t=…,v1=…) over "{t}.{body}".
     * The webhook secret's whsec_ scheme prefix is stripped before HMAC.
     */
    public static function signatureValid(string $secret, string $body, string $signatureHeader, int $now, int $tolerance = self::SIGNATURE_TOLERANCE_SECONDS): bool {
        if ($secret === '' || $body === '' || $tolerance < 0) {
            return false;
        }

        if (str_starts_with($secret, 'whsec_')) {
            $secret = substr($secret, 6);
        }

        $timestamp = '';
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            $part = trim($part);
            $key = self::prefixOf($part);
            $value = $key === '' ? '' : substr($part, strlen($key) + 1);

            if ($key === 't') {
                if ($timestamp !== '') {
                    return false;
                }
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== '' && count($signatures) < 8) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === '' || !ctype_digit($timestamp) || $signatures === [] || abs($now - (int)$timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        $matched = false;

        foreach ($signatures as $signature) {
            if (strlen($signature) === 64 && ctype_xdigit($signature)
                && hash_equals($expected, strtolower($signature))) {
                $matched = true;
            }
        }

        return $matched;
    }

    private function verifySessionEvent(array $object, string $eventType, string $secretKey): GatewayWebhookResult {
        $sessionId = self::scalarString($object['id'] ?? null);

        if (preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $sessionId) !== 1) {
            return GatewayWebhookResult::ignored('invalid_session_id');
        }

        // The session queried from the API is the source of truth; the event
        // body alone never settles a payment.
        $session = $this->client()->getSession($sessionId, $secretKey);
        $http = (int)($session['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300) {
            return GatewayWebhookResult::retry('provider_unavailable');
        }

        return $this->reconcileSession(
            $session,
            $sessionId,
            $eventType === 'checkout.session.async_payment_failed' ? GatewayPaymentStatus::FAILED : null
        );
    }

    /**
     * Read-only reconciliation by session id. No session, charge or refund is
     * created here; refunds still depend on the signed charge.refunded event.
     */
    public function reconcile(
        GatewayPaymentRecord $record,
        GatewayAccount $account,
        GatewayCredentials $credentials
    ): GatewayWebhookResult {
        if ($account->providerCode !== $this->code() || $record->providerCode !== $this->code()) {
            return GatewayWebhookResult::ignored('wrong_provider');
        }

        $sessionId = $record->providerCheckoutId;

        if (preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $sessionId) !== 1) {
            // Attempts created before the reconciliation release kept only the
            // client_reference_id, which Stripe cannot be queried by.
            return GatewayWebhookResult::ignored($sessionId === '' ? 'missing_provider_anchor' : 'invalid_session_id');
        }

        $secretKey = $credentials->get('secret_key');

        if ($secretKey === '') {
            return GatewayWebhookResult::retry('credentials_missing');
        }

        try {
            $session = $this->client()->getSession($sessionId, $secretKey);
        } catch (\RuntimeException) {
            return GatewayWebhookResult::retry('provider_unavailable');
        }

        $http = (int)($session['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300) {
            return GatewayWebhookResult::retry('provider_unavailable');
        }

        return $this->reconcileSession($session, $sessionId);
    }

    /** Shared by the signed webhook path and by reconciliation. */
    private function reconcileSession(array $session, string $sessionId, ?string $forcedStatus = null): GatewayWebhookResult {
        $confirmedId = self::scalarString($session['id'] ?? null);
        $reference = self::scalarString($session['client_reference_id'] ?? null);
        $currency = strtoupper(self::scalarString($session['currency'] ?? null));

        if (!hash_equals($sessionId, $confirmedId)
            || preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $reference) !== 1
            || !in_array($currency, ['BRL', 'USD', 'EUR'], true)) {
            return GatewayWebhookResult::ignored('session_not_reconciled');
        }

        try {
            $amount = self::minorMoney($session['amount_total'] ?? null, $currency);
        } catch (\UnexpectedValueException) {
            return GatewayWebhookResult::retry('invalid_provider_amount');
        }

        $status = self::normalizeSessionStatus(
            self::scalarString($session['status'] ?? null),
            self::scalarString($session['payment_status'] ?? null)
        );

        // The failed async event maps to failed even when the queried session
        // still reports the generic unpaid state.
        if ($status === null) {
            return GatewayWebhookResult::retry('unknown_provider_status');
        }

        if ($forcedStatus !== null) {
            $status = $forcedStatus;
        }

        $paymentIntent = self::scalarString($session['payment_intent'] ?? null);
        $eventDigest = self::sessionDigest($sessionId, $session, $status, $amount);
        $eventId = 'stripe-session-' . substr(hash('sha256', $sessionId . "\0" . $eventDigest), 0, 52);

        return GatewayWebhookResult::verified($eventId, $reference, $paymentIntent, $status, $eventDigest, $amount);
    }

    private function verifyRefundEvent(array $object, string $secretKey): GatewayWebhookResult {
        $chargeId = self::scalarString($object['id'] ?? null);

        if (preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $chargeId) !== 1) {
            return GatewayWebhookResult::ignored('invalid_charge_id');
        }

        $charge = $this->client()->getCharge($chargeId, $secretKey);
        $http = (int)($charge['_http_status'] ?? 0);

        if ($http < 200 || $http >= 300) {
            return GatewayWebhookResult::retry('provider_unavailable');
        }

        $confirmedId = self::scalarString($charge['id'] ?? null);
        $reference = self::scalarString($charge['metadata']['jow_reference'] ?? null);
        $currency = strtoupper(self::scalarString($charge['currency'] ?? null));

        if (!hash_equals($chargeId, $confirmedId)
            || preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $reference) !== 1
            || !in_array($currency, ['BRL', 'USD', 'EUR'], true)) {
            return GatewayWebhookResult::ignored('charge_not_reconciled');
        }

        try {
            $amount = self::minorMoney($charge['amount'] ?? null, $currency);
            $refunded = self::minorMoney($charge['amount_refunded'] ?? null, $currency);
        } catch (\UnexpectedValueException) {
            return GatewayWebhookResult::retry('invalid_provider_amount');
        }

        if ($refunded->compareTo($amount) > 0) {
            return GatewayWebhookResult::ignored('refund_amount_exceeds_charge');
        }

        $status = $refunded->compareTo($amount) === 0
            ? GatewayPaymentStatus::REFUNDED
            : GatewayPaymentStatus::PARTIALLY_REFUNDED;

        $eventDigest = self::refundDigest($chargeId, $charge, $status, $refunded);
        $eventId = 'stripe-charge-' . substr(hash('sha256', $chargeId . "\0" . $eventDigest), 0, 52);

        return GatewayWebhookResult::verified(
            $eventId,
            $reference,
            self::scalarString($charge['payment_intent'] ?? null),
            $status,
            $eventDigest,
            $amount
        );
    }

    private function client(): StripeCheckoutClient {
        return $this->client ??= new StripeCheckoutClient();
    }

    private static function normalizeSessionStatus(string $status, string $paymentStatus): ?string {
        return match (strtolower($status)) {
            'open' => GatewayPaymentStatus::REQUIRES_ACTION,
            'complete' => strtolower($paymentStatus) === 'paid'
                ? GatewayPaymentStatus::PAID
                : GatewayPaymentStatus::REQUIRES_ACTION,
            'expired' => GatewayPaymentStatus::EXPIRED,
            default => null,
        };
    }

    /** @param array<string, mixed> $session */
    private static function sessionDigest(string $sessionId, array $session, string $normalizedStatus, Money $amount): string {
        $material = [
            'session_id' => $sessionId,
            'status' => self::scalarString($session['status'] ?? null),
            'payment_status' => self::scalarString($session['payment_status'] ?? null),
            'normalized_status' => $normalizedStatus,
            'currency' => $amount->currency,
            'amount_total' => $amount->amount(),
            'payment_intent' => self::scalarString($session['payment_intent'] ?? null),
        ];

        return hash('sha256', json_encode($material, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed> $charge */
    private static function refundDigest(string $chargeId, array $charge, string $normalizedStatus, Money $refunded): string {
        $material = [
            'charge_id' => $chargeId,
            'amount' => self::scalarString($charge['amount'] ?? null),
            'amount_refunded' => $refunded->amount(),
            'normalized_status' => $normalizedStatus,
            'currency' => $refunded->currency,
            'payment_intent' => self::scalarString($charge['payment_intent'] ?? null),
        ];

        return hash('sha256', json_encode($material, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function minorMoney(mixed $minorAmount, string $currency): Money {
        if (!is_int($minorAmount) && (!is_string($minorAmount) || preg_match('/^\d+$/D', $minorAmount) !== 1)) {
            throw new \UnexpectedValueException('Stripe amount is missing or malformed');
        }

        return Money::fromMinorUnits((string)$minorAmount, $currency, 2);
    }

    private static function isHttps(string $url): bool {
        if ($url === '' || preg_match('/[\r\n\0]/', $url) === 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private static function prefixOf(string $part): string {
        foreach (['t', 'v1'] as $key) {
            if (str_starts_with($part, $key . '=')) {
                return $key;
            }
        }

        return '';
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
