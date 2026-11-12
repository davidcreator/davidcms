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
 *
 * Quem conecta usa inspect() + curlOptions(): o cURL só fala com os endereços
 * que a guarda conferiu (ADR 0090).
 */
namespace Reamur\System\Library\Security;

final class UrlGuard {

    /** @var int Quantos endereços conferidos são entregues ao cURL por destino */
    private const MAX_PINNED = 8;

    /**
     * Verifica se o endereço é http(s) válido, público e seguro contra SSRF.
     *
     * Responde só sim ou não. Quem vai CONECTAR usa inspect(): entre esta
     * resposta e a conexão o DNS pode mudar (ADR 0090).
     */
    public static function isSafeExternalUrl(string $url, bool $requireHttps = false, bool $resolveDns = true): bool {
        return self::inspect($url, $requireHttps, $resolveDns) !== null;
    }

    /**
     * Confere a URL e devolve o destino aprovado, ou null se ela for recusada.
     *
     * ADR 0090 (S7): a guarda consultava o DNS, aprovava, e o cURL consultava
     * de novo por conta própria. Um nome que responde um IP público na primeira
     * consulta e 127.0.0.1 na seguinte passava pela guarda e conectava na rede
     * interna (DNS rebinding). Agora quem conecta entrega ao cURL os endereços
     * conferidos aqui:
     *
     *     $target = UrlGuard::inspect($url);
     *     if ($target === null) { ...recusar... }
     *     curl_setopt_array($ch, UrlGuard::curlOptions($target) + [...]);
     *
     * ADR 0090 (S6): a URL é conferida CRUA. Espaço, quebra de linha, tab ou
     * NUL nas pontas reprovam; quem aceita espaços nas pontas faz o trim antes
     * e usa o valor limpo.
     *
     * @param callable(string): array<int, string>|null $resolver Só para testes: devolve os IPs de um nome
     * @return array{url: string, scheme: string, host: string, port: int, ips: list<string>, resolve: list<string>}|null
     *         ips = endereços conferidos (vazio com $resolveDns = false);
     *         resolve = linhas para CURLOPT_RESOLVE (vazio quando o host já é um IP)
     */
    public static function inspect(string $url, bool $requireHttps = false, bool $resolveDns = true, ?callable $resolver = null): ?array {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $allowedSchemes = $requireHttps ? ['https'] : ['http', 'https'];
        if (!in_array($scheme, $allowedSchemes, true)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) {
            return null;
        }

        $rawHost = strtolower((string)($parts['host'] ?? ''));
        $host    = trim($rawHost, '[]');

        // "localhost." é o mesmo nome que "localhost": o ponto final só marca a raiz do DNS.
        if (str_ends_with($host, '.') && !str_ends_with($host, '..')) {
            $host = substr($host, 0, -1);
        }

        if ($host === '') {
            return null;
        }

        if (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
        ) {
            return null;
        }

        $target = ['url' => $url, 'scheme' => $scheme, 'host' => $host, 'port' => $port, 'ips' => [], 'resolve' => []];

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (!self::isPublicIp($host)) {
                return null;
            }

            $target['ips'] = [$host];

            return $target;
        }

        // IP escrito de outro jeito: 2130706433, 0x7f000001, 0177.0.0.1 e 127.1
        // são todos 127.0.0.1 para o cURL e para os navegadores. Um destino de
        // verdade não precisa disso; o último rótulo numérico decide.
        $dot  = strrpos($host, '.');
        $last = $dot === false ? $host : substr($host, $dot + 1);
        if (preg_match('/^(?:0x[0-9a-f]*|[0-9]+)$/', $last) === 1) {
            return null;
        }

        if (!$resolveDns) {
            return $target;
        }

        $ips = $resolver !== null ? array_values($resolver($host)) : self::resolveHostIps($host);
        if ($ips === []) {
            return null;
        }

        $checked = [];

        foreach ($ips as $ip) {
            if (!is_string($ip) || !self::isPublicIp($ip)) {
                return null;
            }

            $packed = @inet_pton(trim($ip, '[]'));
            $text   = $packed === false ? false : inet_ntop($packed);

            if ($text === false) {
                return null;
            }

            $checked[$text] = true;
        }

        $target['ips'] = array_slice(array_keys($checked), 0, self::MAX_PINNED);

        // Uma linha por jeito de escrever o nome que o cURL pode usar como chave.
        $target['resolve'] = self::pinLines(array_values(array_unique([trim($rawHost, '[]'), $host])), $port, $target['ips']);

        return $target;
    }

    /**
     * Linhas de CURLOPT_RESOLVE ("nome:porta:ip1,ip2").
     *
     * libcurl anterior a 7.59.0 não entende vários endereços na mesma linha:
     * descarta a linha inteira e consulta o DNS de novo, que é justamente o
     * que o pin existe para impedir. Nessas versões segue só o primeiro
     * endereço (perde-se a alternativa quando ele não responde; não a trava).
     *
     * @param list<string> $names
     * @param list<string> $ips         Endereços já conferidos, em forma canônica
     * @param int|null     $curlVersion Só para testes; null = libcurl em uso
     *
     * @return list<string>
     */
    public static function pinLines(array $names, int $port, array $ips, ?int $curlVersion = null): array {
        if ($names === [] || $ips === []) {
            return [];
        }

        if ($curlVersion === null) {
            $info        = function_exists('curl_version') ? curl_version() : false;
            $curlVersion = is_array($info) ? (int)$info['version_number'] : 0;
        }

        if ($curlVersion < 0x073B00) {
            $ips = [$ips[0]];
        }

        $lines = [];

        foreach ($names as $name) {
            $lines[] = $name . ':' . $port . ':' . implode(',', $ips);
        }

        return $lines;
    }

    /**
     * Opções do cURL que prendem a conexão aos endereços conferidos por
     * inspect(): sem nova consulta ao DNS, só http/https, sem seguir 3xx
     * sozinho (cada salto precisa de um inspect() próprio).
     *
     * O nome continua valendo para o certificado (SNI) e para o cabeçalho Host.
     *
     * @param array{resolve: list<string>} $target Retorno de inspect()
     * @return array<int, mixed>
     */
    public static function curlOptions(array $target): array {
        $options = [
            CURLOPT_PROTOCOLS      => self::allowedCurlProtocols(),
            CURLOPT_FOLLOWLOCATION => false,
        ];

        if ($target['resolve'] !== []) {
            $options[CURLOPT_RESOLVE] = $target['resolve'];
        }

        return $options;
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
            // 192.0.0.0/24 (uso de protocolo), 192.0.2.0/24, 198.51.100.0/24 e
            // 203.0.113.0/24 (documentação), 224.0.0.0/4 (multicast)
            if (($long & 0xFFFFFF00) === 0xC0000000 || ($long & 0xFFFFFF00) === 0xC0000200
                || ($long & 0xFFFFFF00) === 0xC6336400 || ($long & 0xFFFFFF00) === 0xCB007100
                || ($long & 0xF0000000) === 0xE0000000) {
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

        // ADR 0090: IPv6 que carrega um IPv4 dentro. O IPv4 embutido é o destino
        // de verdade e precisa passar pela mesma régua.
        $hex = bin2hex($bin);

        // NAT64 (64:ff9b::/96, RFC 6052): os 4 últimos bytes. Em rede só IPv6
        // com DNS64, todo site IPv4 chega assim; por isso não é recusa direta.
        if (str_starts_with($hex, '0064ff9b0000000000000000')) {
            $embedded = @inet_ntop(substr($bin, 12, 4));

            return is_string($embedded) && self::isPublicIp($embedded);
        }

        // 6to4 (2002::/16, RFC 3056): bytes 2 a 5.
        if (str_starts_with($hex, '2002')) {
            $embedded = @inet_ntop(substr($bin, 2, 4));

            return is_string($embedded) && self::isPublicIp($embedded);
        }

        // Sem uso legítimo como destino: IPv4-compatível (::/96, obsoleto),
        // NAT64 de uso local (64:ff9b:1::/48), Teredo (2001::/32), descarte
        // (100::/64), documentação (2001:db8::/32) e multicast (ff00::/8).
        if (str_starts_with($hex, '000000000000000000000000')
            || str_starts_with($hex, '0064ff9b0001')
            || str_starts_with($hex, '20010000')
            || str_starts_with($hex, '0100000000000000')
            || str_starts_with($hex, '20010db8')
            || str_starts_with($hex, 'ff')) {
            return false;
        }

        return true;
    }

    /**
     * Resolve Location de resposta 3xx relativo a uma URL base.
     */
    public static function resolveRedirect(string $baseUrl, string $location): string {
        // Espaço e tab em volta do valor são parte da sintaxe do cabeçalho;
        // NUL e quebra de linha não (trim() esconderia os dois da checagem).
        $location = trim($location, " \t");
        if ($location === '' || preg_match('/[\x00-\x20\x7f]/', $location) === 1) {
            return '';
        }

        // Com esquema, só http e https; "file:///etc/passwd" não vira caminho relativo.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return preg_match('#^https?://#i', $location) === 1 ? $location : '';
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
