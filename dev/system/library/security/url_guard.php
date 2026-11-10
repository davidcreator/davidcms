<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Validação anti-SSRF para URLs externas (webhooks e importador de blog):
 * restringe a http/https sem credenciais embutidas e bloqueia loopback,
 * redes privadas, link-local / metadata cloud (169.254.169.254) e ULA IPv6.
 */
namespace Reamur\System\Library\Security;

final class UrlGuard {

    /**
     * Verifica se o endereço é http(s) válido, público e seguro contra SSRF.
     */
    public static function isSafeExternalUrl(string $url, bool $requireHttps = false, bool $resolveDns = true): bool {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $allowedSchemes = $requireHttps ? ['https'] : ['http', 'https'];
        if (!in_array($scheme, $allowedSchemes, true)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(trim((string)($parts['host'] ?? ''), '[]'));
        if ($host === '') {
            return false;
        }

        if (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
        ) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        if (!$resolveDns) {
            return true;
        }

        $ips = self::resolveHostIps($host);
        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Garante que um IP (v4 ou v6) seja publicamente roteável.
     */
    public static function isPublicIp(string $ip): bool {
        $ip = trim($ip, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        // Bloqueia faixas privadas/reservadas pelo filtro padrão do PHP
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }

        if (strlen($bin) === 4) {
            $long = (int)sprintf('%u', ip2long($ip));
            // 0.0.0.0/8, 100.64.0.0/10 (CGNAT), 169.254.0.0/16 (link-local/metadata), 198.18.0.0/15 (benchmark)
            if (($long & 0xFF000000) === 0x00000000) {
                return false;
            }
            if (($long & 0xFFC00000) === 0x64400000) {
                return false;
            }
            if (($long & 0xFFFF0000) === 0xA9FE0000) {
                return false;
            }
            if (($long & 0xFFFE0000) === 0xC6120000) {
                return false;
            }

            return true;
        }

        // IPv6 mapeado IPv4 (::ffff:a.b.c.d) → valida o IPv4 embutido
        if (str_starts_with(bin2hex($bin), '00000000000000000000ffff')) {
            $mapped = @inet_ntop(substr($bin, 12, 4));

            return is_string($mapped) && self::isPublicIp($mapped);
        }

        // Bloqueia IPv6 link-local (fe80::/10) e unique-local (fc00::/7) explicitamente
        $firstWord = (ord($bin[0]) << 8) | ord($bin[1]);
        if (($firstWord & 0xFFC0) === 0xFE80 || ($firstWord & 0xFE00) === 0xFC00) {
            return false;
        }

        return true;
    }

    /**
     * Resolve Location de resposta 3xx relativo a uma URL base.
     */
    public static function resolveRedirect(string $baseUrl, string $location): string {
        $location = trim($location);
        if ($location === '' || preg_match('/[\x00-\x20\x7f]/', $location) === 1) {
            return '';
        }

        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $base = parse_url($baseUrl);
        if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return '';
        }

        $scheme = strtolower((string)$base['scheme']);
        $host   = (string)$base['host'];
        $port   = isset($base['port']) ? ':' . (int)$base['port'] : '';

        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $port . $location;
        }

        $basePath = (string)($base['path'] ?? '/');
        $dir = str_contains($basePath, '/') ? substr($basePath, 0, (int)strrpos($basePath, '/') + 1) : '/';

        return $scheme . '://' . $host . $port . $dir . $location;
    }

    /** Bitmask segura para CURLOPT_PROTOCOLS / CURLOPT_REDIR_PROTOCOLS. */
    public static function allowedCurlProtocols(): int {
        $http  = defined('CURLPROTO_HTTP') ? (int)CURLPROTO_HTTP : 1;
        $https = defined('CURLPROTO_HTTPS') ? (int)CURLPROTO_HTTPS : 2;

        return $http | $https;
    }

    /** @return array<int, string> */
    private static function resolveHostIps(string $host): array {
        $ips = [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!is_array($record)) {
                        continue;
                    }
                    if (!empty($record['ip']) && is_string($record['ip'])) {
                        $ips[] = $record['ip'];
                    } elseif (!empty($record['ipv6']) && is_string($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        if ($ips === [] && function_exists('gethostbynamel')) {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                foreach ($v4 as $ip) {
                    $ips[] = $ip;
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
