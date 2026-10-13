<?php
namespace Reamur\System\Library;

class MercadopagoMarketplace {
    private const AUTHORIZATION_URL = 'https://auth.mercadopago.com.br/authorization';
    private const TOKEN_URL = 'https://api.mercadopago.com/oauth/token';

    private array $config;

    public function __construct(array $config = []) {
        $this->config = $config;
    }

    public function appId(): string {
        return trim((string)($this->config['app_id'] ?? ''));
    }

    public function clientSecret(): string {
        return trim((string)($this->config['client_secret'] ?? ''));
    }

    public function redirectUri(): string {
        return trim((string)($this->config['redirect_uri'] ?? ''));
    }

    public function isConfigured(): bool {
        return $this->appId() !== ''
            && $this->clientSecret() !== ''
            && filter_var($this->redirectUri(), FILTER_VALIDATE_URL) !== false;
    }

    public function authorizationUrl(string $state): string {
        if (!$this->isConfigured()) {
            return '';
        }

        return self::AUTHORIZATION_URL . '?' . http_build_query([
            'client_id' => $this->appId(),
            'response_type' => 'code',
            'platform_id' => 'mp',
            'redirect_uri' => $this->redirectUri(),
            'state' => trim($state)
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code, string $state = ''): array {
        if (!$this->isConfigured()) {
            return [
                'error' => 'Mercado Pago marketplace OAuth is not configured.'
            ];
        }

        if (trim($code) === '') {
            return [
                'error' => 'Missing Mercado Pago authorization code.'
            ];
        }

        $payload = [
            'client_id' => $this->appId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'authorization_code',
            'code' => trim($code),
            'redirect_uri' => $this->redirectUri()
        ];

        if (trim($state) !== '') {
            $payload['state'] = trim($state);
        }

        return $this->oauthTokenRequest($payload);
    }

    public function refreshSellerToken(string $refresh_token): array {
        if (!$this->isConfigured()) {
            return [
                'error' => 'Mercado Pago marketplace OAuth is not configured.'
            ];
        }

        if (trim($refresh_token) === '') {
            return [
                'error' => 'Missing Mercado Pago refresh token.'
            ];
        }

        return $this->oauthTokenRequest([
            'client_id' => $this->appId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'refresh_token',
            'refresh_token' => trim($refresh_token)
        ]);
    }

    public function extractErrorMessage(array $response): string {
        foreach ([
            $response['message'] ?? '',
            $response['error_description'] ?? '',
            $response['description'] ?? '',
            $response['error'] ?? ''
        ] as $candidate) {
            $candidate = trim((string)$candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        if (!empty($response['_curl_error'])) {
            return 'Mercado Pago OAuth request failed: ' . trim((string)$response['_curl_error']);
        }

        if (!empty($response['_http_status'])) {
            return 'Mercado Pago OAuth request returned HTTP ' . (int)$response['_http_status'] . '.';
        }

        return 'Mercado Pago OAuth request failed.';
    }

    private function oauthTokenRequest(array $payload): array {
        $request = $this->request(
            self::TOKEN_URL,
            http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
            [
                'accept: application/json',
                'content-type: application/x-www-form-urlencoded'
            ]
        );

        $data = json_decode($request['body'], true);

        if (!is_array($data)) {
            $data = [];
        }

        $data['_http_status'] = $request['status'];
        $data['_curl_error'] = $request['error'];
        $data['_raw_body'] = $request['body'];

        return $data;
    }

    private function caBundlePath(): string {
        $candidates = array_filter([
            getenv('REAMUR_CURL_CAINFO') ?: '',
            getenv('CURL_CA_BUNDLE') ?: '',
            (string)ini_get('curl.cainfo'),
            (string)ini_get('openssl.cafile'),
            defined('DIR_STORAGE') ? rtrim(DIR_STORAGE, '/\\') . '/certs/cacert.pem' : '',
            defined('DIR_SYSTEM') ? rtrim(DIR_SYSTEM, '/\\') . '/storage/certs/cacert.pem' : ''
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function request(string $url, string $body, array $headers = [], string $method = 'POST'): array {
        $ch = curl_init($url);
        $ca_bundle = $this->caBundlePath();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ReamurCMS MercadoPago Marketplace/1.0');

        if ($ca_bundle !== '') {
            curl_setopt($ch, CURLOPT_CAINFO, $ca_bundle);
        }

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => $response ?: '',
            'error' => $error ?: ''
        ];
    }
}
