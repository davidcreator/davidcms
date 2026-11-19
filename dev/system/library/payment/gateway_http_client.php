<?php declare(strict_types=1);
namespace Reamur\System\Library\Payment;

use Reamur\System\Library\Security\UrlGuard;

/**
 * Shared cURL transport for JOW gateway adapters (Mercado Pago, Stripe, PayPal).
 * Provider clients may inject their own transport callable in tests; production
 * always goes through here: UrlGuard-pinned target, TLS verification and no
 * redirect following.
 */
final class GatewayHttpClient {
    /**
     * @param list<string> $headers
     * @return array{status: int, body: string, error: string}
     */
    public function request(string $url, string $body, array $headers = [], string $method = 'POST'): array {
        if (!function_exists('curl_init') || !self::isHttpUrl($url)) {
            return ['status' => 0, 'body' => '', 'error' => 'Invalid or unsupported cURL request target.'];
        }

        $target = UrlGuard::inspect($url, true);

        if ($target === null) {
            return ['status' => 0, 'body' => '', 'error' => 'Request target rejected by UrlGuard.'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => '', 'error' => 'Failed to initialize cURL session.'];
        }

        $ca_bundle = self::caBundlePath();

        curl_setopt_array($ch, UrlGuard::curlOptions($target) + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'ReamurCMS JOW-Payments/1.0',
        ]);

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
            'error' => $error ?: '',
        ];
    }

    /**
     * Validate the EXACT string received: no trim() here, otherwise a
     * space/CR/LF/NUL at the edges would pass the check and continue raw.
     */
    public static function isHttpUrl(string $url): bool {
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private static function caBundlePath(): string {
        $candidates = array_filter([
            getenv('REAMUR_CURL_CAINFO') ?: '',
            getenv('CURL_CA_BUNDLE') ?: '',
            (string)ini_get('curl.cainfo'),
            (string)ini_get('openssl.cafile'),
            defined('DIR_STORAGE') ? rtrim((string)DIR_STORAGE, '/\\') . '/certs/cacert.pem' : '',
            defined('DIR_SYSTEM') ? rtrim((string)DIR_SYSTEM, '/\\') . '/storage/certs/cacert.pem' : '',
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}
