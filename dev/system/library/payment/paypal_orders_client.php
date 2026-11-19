<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

/**
 * PayPal Orders API v2 client for JOW's platform-owned account.
 *
 * OAuth2 client-credentials token, PayPal-Request-Id idempotency and the
 * webhook-signature verification endpoint. The transport is injectable for
 * tests; production defaults to the shared GatewayHttpClient.
 */
class PaypalOrdersClient {
    public const SANDBOX_API_URL = 'https://api-m.sandbox.paypal.com';
    public const LIVE_API_URL = 'https://api-m.paypal.com';

    /** @var callable(string, string, list<string>, string): array{status: int, body: string, error: string} */
    private $transport;

    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
    public function __construct(?callable $transport = null, array $config = []) {
        $this->transport = $transport ?? (new GatewayHttpClient())->request(...);
        $this->config = $config;
    }

    /** @return array<string, mixed> decoded token response + _http_status/_curl_error */
    public function oauthToken(string $baseUrl, string $clientId, string $clientSecret): array {
        if (!self::validBaseUrl($baseUrl) || trim($clientId) === '' || trim($clientSecret) === '') {
            return ['_http_status' => 0, '_curl_error' => 'invalid token credentials or base URL'];
        }

        return $this->send(
            $baseUrl . '/v1/oauth2/token',
            http_build_query(['grant_type' => 'client_credentials'], '', '&', PHP_QUERY_RFC1738),
            [
                'accept: application/json',
                'authorization: Basic ' . base64_encode(trim($clientId) . ':' . trim($clientSecret)),
                'content-type: application/x-www-form-urlencoded',
            ],
            'POST'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOrder(string $baseUrl, string $accessToken, array $payload, string $requestId): array {
        if (!self::validBaseUrl($baseUrl) || trim($accessToken) === ''
            || preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $requestId) !== 1) {
            return ['_http_status' => 0, '_curl_error' => 'invalid order credentials or request ID'];
        }

        return $this->send(
            $baseUrl . '/v2/checkout/orders',
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            self::orderHeaders($accessToken, $requestId),
            'POST'
        );
    }

    /** @return array<string, mixed> */
    public function getOrder(string $baseUrl, string $accessToken, string $orderId): array {
        if (!self::validBaseUrl($baseUrl) || trim($accessToken) === ''
            || preg_match('/^[A-Za-z0-9_-]{1,32}$/D', $orderId) !== 1) {
            return ['_http_status' => 0, '_curl_error' => 'invalid order ID or credentials'];
        }

        return $this->send(
            $baseUrl . '/v2/checkout/orders/' . rawurlencode($orderId),
            '',
            ['accept: application/json', 'authorization: Bearer ' . trim($accessToken)],
            'GET'
        );
    }

    /** @return array<string, mixed> */
    public function captureOrder(string $baseUrl, string $accessToken, string $orderId, string $requestId): array {
        if (!self::validBaseUrl($baseUrl) || trim($accessToken) === ''
            || preg_match('/^[A-Za-z0-9_-]{1,32}$/D', $orderId) !== 1
            || preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $requestId) !== 1) {
            return ['_http_status' => 0, '_curl_error' => 'invalid capture credentials or request ID'];
        }

        return $this->send(
            $baseUrl . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture',
            '',
            self::orderHeaders($accessToken, $requestId),
            'POST'
        );
    }

    /**
     * @param array<string, mixed> $verification payload for /v1/notifications/verify-webhook-signature
     * @return array<string, mixed>
     */
    public function verifyWebhookSignature(string $baseUrl, string $accessToken, array $verification): array {
        if (!self::validBaseUrl($baseUrl) || trim($accessToken) === '') {
            return ['_http_status' => 0, '_curl_error' => 'invalid verification credentials or base URL'];
        }

        return $this->send(
            $baseUrl . '/v1/notifications/verify-webhook-signature',
            json_encode($verification, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ['accept: application/json', 'authorization: Bearer ' . trim($accessToken), 'content-type: application/json'],
            'POST'
        );
    }

    /** Environment seam for e2e batteries: the default is always an official host. */
    public static function baseUrlFor(string $environment, array $config = []): string {
        $override = trim((string)($config['api_url'] ?? ''));

        if (GatewayHttpClient::isHttpUrl($override)) {
            return rtrim($override, '/');
        }

        return $environment === 'live' ? self::LIVE_API_URL : self::SANDBOX_API_URL;
    }

    public static function validBaseUrl(string $url): bool {
        if (!GatewayHttpClient::isHttpUrl($url)) {
            return false;
        }

        return strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    /** @return list<string> */
    private static function orderHeaders(string $accessToken, string $requestId): array {
        return [
            'accept: application/json',
            'authorization: Bearer ' . trim($accessToken),
            'content-type: application/json',
            'PayPal-Request-Id: ' . $requestId,
        ];
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
