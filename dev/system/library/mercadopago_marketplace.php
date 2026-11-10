<?php
namespace Reamur\System\Library;

use Reamur\System\Library\Security\UrlGuard;

/**
 * Mercado Pago Marketplace — OAuth do conector de vendedores (F5-02/ADR 0034).
 *
 * F5-03 (ADR 0035): createPreference() (checkout com split marketplace_fee)
 * e getPayment() (consulta usada pelo controller público de notificação —
 * a fonte da verdade NUNCA é o POST do webhook).
 *
 * Config aceita no construtor: app_id, client_secret, redirect_uri e
 * (opcional) token_url / users_me_url / api_url — costuras de ambiente documentadas
 * no ADR 0034 §4: permitem apontar o handshake para o MOCK das baterias e2e
 * (e para qualquer gateway de sandbox) sem tocar o código; o default é
 * sempre o endpoint oficial do Mercado Pago.
 */
class MercadopagoMarketplace {
    private const AUTHORIZATION_URL = 'https://auth.mercadopago.com.br/authorization';
    private const TOKEN_URL = 'https://api.mercadopago.com/oauth/token';
    private const USERS_ME_URL = 'https://api.mercadopago.com/users/me';

    /** Base da API (F5-03): preferences + consulta de pagamentos */
    private const API_URL = 'https://api.mercadopago.com';

    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
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

    public static function isHttpUrl(string $url): bool {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    public function isConfigured(): bool {
        return $this->appId() !== ''
            && $this->clientSecret() !== ''
            && self::isHttpUrl($this->redirectUri());
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

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
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

    /** @param array<string, mixed> $response */
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

    /**
     * Cria uma preference (Checkout Pro) autenticada no SELLER (split real).
     * O $payload é montado pelo caller (items/marketplace_fee/forms externos
     * são domínio do pedido) — o método só adiciona transporte+auth.
     *
     * @param array<string, mixed> $payload   corpo JSON /checkout/preferences
     * @return array<string, mixed> resposta decodificada + _http_status
     */
    public function createPreference(array $payload, string $access_token): array {
        $request = $this->request(
            $this->apiUrl() . '/checkout/preferences',
            json_encode($payload) ?: '{}',
            [
                'accept: application/json',
                'content-type: application/json',
                'authorization: Bearer ' . trim($access_token),
            ]
        );

        $data = json_decode($request['body'], true);

        if (!is_array($data)) {
            $data = [];
        }

        $data['_http_status'] = $request['status'];
        $data['_curl_error']  = $request['error'];

        return $data;
    }

    /**
     * Consulta um pagamento no MP autenticado no SELLER. É A VERIFICAÇÃO de
     * verdade do webhook/IPN: o notify do controller público NUNCA confia no
     * corpo recebido — sempre confirma o status aqui (ADR 0035 §4).
     *
     * @return array<string, mixed> recurso do pagamento + _http_status ({} fora de 2xx vira checagem)
     */
    public function getPayment(int $payment_id, string $access_token): array {
        if ($payment_id <= 0) {
            return ['_http_status' => 0, '_curl_error' => 'invalid payment id'];
        }

        $request = $this->request(
            $this->apiUrl() . '/v1/payments/' . $payment_id,
            '',
            ['accept: application/json', 'authorization: Bearer ' . trim($access_token)],
            'GET'
        );

        $data = json_decode($request['body'], true);

        if (!is_array($data)) {
            $data = [];
        }

        $data['_http_status'] = $request['status'];
        $data['_curl_error']  = $request['error'];

        return $data;
    }

    /**
     * Dados públicos do vendedor recém-conectado (nickname/email para a
     * grade admin). Falha aqui NÃO invalida a conexão — o caller segue com
     * campos vazios (o token já foi concedido). Nunca lança exceção.
     *
     * @return array{nickname: string, email: string}
     */
    public function getUserInfo(string $access_token): array {
        $empty = ['nickname' => '', 'email' => ''];

        if (trim($access_token) === '') {
            return $empty;
        }

        $request = $this->request(
            $this->usersMeUrl(),
            '',
            ['accept: application/json', 'authorization: Bearer ' . trim($access_token)],
            'GET'
        );

        $data = json_decode($request['body'], true);

        if (!is_array($data) || ($request['status'] < 200 || $request['status'] >= 300)) {
            return $empty;
        }

        return [
            'nickname' => trim((string)($data['nickname'] ?? '')),
            'email'    => trim((string)($data['email'] ?? '')),
        ];
    }

    /** Costura de ambiente (ADR 0034 §4): mock das baterias × endpoint oficial */
    private function tokenUrl(): string {
        return $this->resolveOverrideUrl('token_url', self::TOKEN_URL);
    }

    private function usersMeUrl(): string {
        return $this->resolveOverrideUrl('users_me_url', self::USERS_ME_URL);
    }

    /** Costura de ambiente (ADR 0034 §4 / 0035 §3): mock das baterias × API oficial */
    private function apiUrl(): string {
        return rtrim($this->resolveOverrideUrl('api_url', self::API_URL), '/');
    }

    private function resolveOverrideUrl(string $key, string $default): string {
        $override = trim((string)($this->config[$key] ?? ''));

        return self::isHttpUrl($override) ? $override : $default;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function oauthTokenRequest(array $payload): array {
        $request = $this->request(
            $this->tokenUrl(),
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

    /**
     * @param array<int, string> $headers
     * @return array{status: int, body: string, error: string}
     */
    private function request(string $url, string $body, array $headers = [], string $method = 'POST'): array {
        if (!function_exists('curl_init') || !self::isHttpUrl($url)) {
            return [
                'status' => 0,
                'body'   => '',
                'error'  => 'Invalid or unsupported cURL request target.',
            ];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'status' => 0,
                'body'   => '',
                'error'  => 'Failed to initialize cURL session.',
            ];
        }

        $ca_bundle = $this->caBundlePath();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_PROTOCOLS, UrlGuard::allowedCurlProtocols());
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, UrlGuard::allowedCurlProtocols());
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
            'body' => is_string($response) ? $response : '',
            'error' => $error ?: ''
        ];
    }
}
