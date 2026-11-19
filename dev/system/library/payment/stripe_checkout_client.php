<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * Stripe API client for hosted Checkout Sessions (REST, form-encoded).
 *
 * The transport is injectable for tests; production defaults to the shared
 * GatewayHttpClient. Every method returns the decoded response plus
 * _http_status/_curl_error, mirroring the Mercado Pago client contract.
 */
class StripeCheckoutClient {
    private const DEFAULT_API_URL = 'https://api.stripe.com';

    /** @var callable(string, string, list<string>, string): array{status: int, body: string, error: string} */
    private $transport;

    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
    public function __construct(?callable $transport = null, array $config = []) {
        $this->transport = $transport ?? (new GatewayHttpClient())->request(...);
        $this->config = $config;
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    public function createSession(array $form, string $secretKey, string $idempotencyKey): array {
        if (trim($secretKey) === '' || preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $idempotencyKey) !== 1) {
            return ['_http_status' => 0, '_curl_error' => 'invalid session credentials or idempotency key'];
        }

        return $this->send(
            $this->apiUrl() . '/v1/checkout/sessions',
            http_build_query($form, '', '&', PHP_QUERY_RFC1738),
            [
                'accept: application/json',
                'content-type: application/x-www-form-urlencoded',
                'authorization: Bearer ' . trim($secretKey),
                'Idempotency-Key: ' . $idempotencyKey,
            ],
            'POST'
        );
    }

    /** @return array<string, mixed> */
    public function getSession(string $sessionId, string $secretKey): array {
        if (trim($secretKey) === '' || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $sessionId) !== 1) {
            return ['_http_status' => 0, '_curl_error' => 'invalid session ID or credentials'];
        }

        return $this->send(
            $this->apiUrl() . '/v1/checkout/sessions/' . rawurlencode($sessionId),
            '',
            ['accept: application/json', 'authorization: Bearer ' . trim($secretKey)],
            'GET'
        );
    }

    /** @return array<string, mixed> */
    public function getCharge(string $chargeId, string $secretKey): array {
        if (trim($secretKey) === '' || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $chargeId) !== 1) {
            return ['_http_status' => 0, '_curl_error' => 'invalid charge ID or credentials'];
        }

        return $this->send(
            $this->apiUrl() . '/v1/charges/' . rawurlencode($chargeId),
            '',
            ['accept: application/json', 'authorization: Bearer ' . trim($secretKey)],
            'GET'
        );
    }

    /** Environment seam for e2e batteries: default is always the official endpoint. */
    private function apiUrl(): string {
        $override = trim((string)($this->config['api_url'] ?? ''));

        return GatewayHttpClient::isHttpUrl($override) ? rtrim($override, '/') : self::DEFAULT_API_URL;
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private function send(string $url, string $body, array $headers, string $method): array {
        $response = ($this->transport)($url, $body, $headers, $method);
        $data = json_decode((string)($response['body'] ?? ''), true);

        if (!is_array($data)) {
            $data = [];
        }

        $data['_http_status'] = (int)($response['status'] ?? 0);
        $data['_curl_error'] = (string)($response['error'] ?? '');

        return $data;
    }
}
