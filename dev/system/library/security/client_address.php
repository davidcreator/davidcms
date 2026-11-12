<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Quem é o visitante e se a conexão dele é HTTPS (ADR 0089, achado S5).
 *
 * Atrás de CDN ou proxy reverso o PHP recebe a conexão do proxy: REMOTE_ADDR
 * é o IP do proxy e HTTPS vem vazio. O IP real e o esquema chegam em
 * X-Forwarded-For e X-Forwarded-Proto, cabeçalhos que qualquer cliente
 * também pode escrever. Esta classe só acredita neles quando a instalação
 * declara em quem confiar:
 *
 *   - lista: REAMUR_TRUSTED_PROXIES = endereços e faixas dos proxies;
 *   - saltos: REAMUR_TRUSTED_PROXY_HOPS = quantos proxies ficam na frente.
 *
 * Sem nenhuma das duas, vale a conexão direta, como sempre valeu.
 *
 * Classe pura: recebe o array do servidor e devolve a leitura. Não lê
 * constantes, config, banco nem $_SERVER.
 */
namespace Reamur\System\Library\Security;

final class ClientAddress {
    public const MODE_DIRECT = 'direct';
    public const MODE_LIST   = 'list';
    public const MODE_HOPS   = 'hops';

    /** Limite de entradas lidas de X-Forwarded-For (cabeçalho gigante não vira trabalho). */
    private const MAX_CHAIN = 32;

    /**
     * @param string       $ip        IP atribuído ao visitante (normalizado; '' se nem a conexão tem IP válido)
     * @param string       $peer      IP de quem abriu a conexão com o servidor (REMOTE_ADDR normalizado)
     * @param bool         $https     A conexão do visitante é HTTPS (direta ou informada por proxy confiável)
     * @param string       $mode      MODE_DIRECT, MODE_LIST ou MODE_HOPS: a regra em vigor na instalação
     * @param bool         $forwarded O pedido trouxe X-Forwarded-For ou X-Forwarded-Proto
     * @param bool         $honoured  Os cabeçalhos do proxy foram levados em conta neste pedido
     * @param int          $hops      Saltos declarados (modo saltos) — 0 nos outros modos
     * @param int          $entries   Entradas válidas da lista (modo lista) — 0 nos outros modos
     * @param list<string> $invalid   Entradas da lista que não são endereço nem faixa
     *
     * As propriedades são só de leitura por convenção (o projeto declara PHP >= 8.0,
     * e `readonly` é do 8.1).
     */
    private function __construct(
        public string $ip,
        public string $peer,
        public bool $https,
        public string $mode,
        public bool $forwarded,
        public bool $honoured,
        public int $hops,
        public int $entries,
        public array $invalid,
    ) {}

    /**
     * @param array<string, mixed>        $server  $_SERVER (ou equivalente)
     * @param string|array<int, mixed>    $trusted Endereços e faixas dos proxies ('a, b' ou ['a', 'b'])
     * @param int                         $hops    Quantos proxies ficam na frente (usado só sem lista)
     */
    public static function resolve(array $server, string|array $trusted = '', int $hops = 0): self {
        [$ranges, $invalid] = self::parseTrusted($trusted);

        $peer  = self::normalize(self::text($server['REMOTE_ADDR'] ?? null));
        $chain = self::chain(self::text($server['HTTP_X_FORWARDED_FOR'] ?? null));
        $proto = self::proto($server);

        $direct    = self::text($server['HTTPS'] ?? null);
        $https     = $direct !== '' && strtolower($direct) !== 'off';
        $forwarded = $chain !== [] || $proto !== '';

        $mode = $ranges !== [] ? self::MODE_LIST : ($hops > 0 ? self::MODE_HOPS : self::MODE_DIRECT);
        $hops = $mode === self::MODE_HOPS ? min($hops, self::MAX_CHAIN) : 0;

        $ip       = $peer;
        $honoured = false;

        if ($mode === self::MODE_LIST && $peer !== '' && self::inRanges($peer, $ranges)) {
            // Da direita para a esquerda: cada proxy acrescenta à direita o IP
            // de quem falou com ele. O primeiro endereço que NÃO é proxy
            // confiável é o visitante; o que estiver à esquerda dele foi
            // escrito pelo próprio visitante e não vale nada.
            $honoured = true;

            for ($i = count($chain) - 1; $i >= 0; $i--) {
                if ($chain[$i] === '') {
                    break; // entrada ilegível: para no último endereço bom
                }

                $ip = $chain[$i];

                if (!self::inRanges($ip, $ranges)) {
                    break;
                }
            }
        } elseif ($mode === self::MODE_HOPS && $peer !== '') {
            // N proxies na frente: o visitante é o N-ésimo endereço contando
            // da direita. Pedido com menos saltos que o declarado (acesso por
            // fora da CDN) fica com o endereço mais à esquerda que existir.
            $honoured = true;

            for ($i = 1; $i <= $hops; $i++) {
                $next = $chain[count($chain) - $i] ?? '';

                if ($next === '') {
                    break;
                }

                $ip = $next;
            }
        }

        if ($honoured && $proto !== '') {
            $https = $proto === 'https';
        }

        return new self(
            $ip, $peer, $https, $mode, $forwarded, $honoured,
            $hops, count($ranges), $invalid
        );
    }

    /**
     * O instalador roda antes de existir config.php, então não há lista de
     * confiança. Ali o esquema só decide os links da própria resposta: um
     * cabeçalho forjado atinge apenas quem o enviou. NÃO usar fora do
     * instalador.
     *
     * @param array<string, mixed> $server
     */
    public static function looksHttps(array $server): bool {
        $direct = self::text($server['HTTPS'] ?? null);
        $proto  = self::proto($server);

        return ($direct !== '' && strtolower($direct) !== 'off')
            || (int)self::text($server['SERVER_PORT'] ?? null) === 443
            || $proto === 'https';
    }

    /**
     * Endereço IP em forma única: sem porta, sem colchetes, sem zona, IPv6
     * compactado e IPv4 embutido em IPv6 (::ffff:1.2.3.4) como IPv4. Devolve
     * '' para o que não é endereço.
     */
    public static function normalize(string $value): string {
        $value = trim($value);

        if ($value === '' || strlen($value) > 64) {
            return '';
        }

        if ($value[0] === '[') {
            // [2001:db8::1] ou [2001:db8::1]:443
            $end = strpos($value, ']');

            if ($end === false) {
                return '';
            }

            $value = substr($value, 1, $end - 1);
        } elseif (substr_count($value, ':') === 1 && str_contains($value, '.')) {
            // 203.0.113.7:51234
            $value = (string)strstr($value, ':', true);
        }

        $zone = strpos($value, '%');

        if ($zone !== false) {
            $value = substr($value, 0, $zone);
        }

        $packed = @inet_pton($value);

        if ($packed === false) {
            return '';
        }

        if (strlen($packed) === 16 && str_starts_with($packed, "\0\0\0\0\0\0\0\0\0\0\xff\xff")) {
            $packed = substr($packed, 12);
        }

        $text = inet_ntop($packed);

        return $text === false ? '' : $text;
    }

    /**
     * @param string|array<int, mixed> $trusted
     * @return array{0: list<array{0: string, 1: int}>, 1: list<string>} [faixas válidas (binário, bits), entradas inválidas]
     */
    public static function parseTrusted(string|array $trusted): array {
        $items = is_array($trusted)
            ? $trusted
            : (preg_split('/[\s,;]+/', $trusted, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $ranges  = [];
        $invalid = [];

        foreach ($items as $item) {
            if (!is_string($item) || trim($item) === '') {
                continue;
            }

            $item  = trim($item);
            $range = self::parseRange($item);

            if ($range === null) {
                $invalid[] = substr($item, 0, 64);
            } else {
                $ranges[] = $range;
            }
        }

        return [$ranges, $invalid];
    }

    /** @return array{0: string, 1: int}|null */
    private static function parseRange(string $item): ?array {
        $bits = null;

        if (str_contains($item, '/')) {
            [$item, $suffix] = explode('/', $item, 2);

            if ($suffix === '' || !ctype_digit($suffix) || strlen($suffix) > 3) {
                return null;
            }

            $bits = (int)$suffix;
        }

        // Faixa e endereço são lidos do jeito escrito: '::ffff:0:0/96' não
        // pode virar IPv4 antes de o tamanho do prefixo ser conferido.
        $packed = @inet_pton(trim($item));

        if ($packed === false) {
            return null;
        }

        $max  = strlen($packed) * 8;
        $bits ??= $max;

        // /0 é "confio em qualquer um": com isso o endereço mais à esquerda,
        // escrito pelo visitante, seria aceito. Para esse cenário existe o
        // modo de saltos, que nunca lê além do que os proxies escreveram.
        if ($bits < 1 || $bits > $max) {
            return null;
        }

        if ($max === 128 && str_starts_with($packed, "\0\0\0\0\0\0\0\0\0\0\xff\xff")) {
            if ($bits < 96) {
                return null;
            }

            $packed = substr($packed, 12);
            $bits  -= 96;

            if ($bits < 1) {
                return null;
            }
        }

        return [$packed, $bits];
    }

    /** @param list<array{0: string, 1: int}> $ranges */
    private static function inRanges(string $ip, array $ranges): bool {
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        foreach ($ranges as [$network, $bits]) {
            if (strlen($network) !== strlen($packed)) {
                continue; // IPv4 só casa com faixa IPv4; IPv6 com IPv6
            }

            $bytes = intdiv($bits, 8);
            $rest  = $bits % 8;

            if ($bytes > 0 && strncmp($packed, $network, $bytes) !== 0) {
                continue;
            }

            if ($rest === 0) {
                return true;
            }

            $mask = 0xff << (8 - $rest) & 0xff;

            if ((ord($packed[$bytes]) & $mask) === (ord($network[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * X-Forwarded-For em lista, na ordem em que veio. Entrada ilegível vira
     * '' (quem percorre a lista para nela).
     *
     * @return list<string>
     */
    private static function chain(string $header): array {
        if (trim($header) === '') {
            return [];
        }

        $parts = explode(',', $header);

        if (count($parts) > self::MAX_CHAIN) {
            $parts = array_slice($parts, -self::MAX_CHAIN);
        }

        return array_map(static fn (string $part): string => self::normalize($part), $parts);
    }

    /**
     * Primeiro valor de X-Forwarded-Proto, em minúsculas ('' se ausente).
     *
     * @param array<string, mixed> $server
     */
    private static function proto(array $server): string {
        $header = self::text($server['HTTP_X_FORWARDED_PROTO'] ?? null);

        return strtolower(trim(explode(',', $header, 2)[0]));
    }

    private static function text(mixed $value): string {
        return is_string($value) || is_int($value) ? (string)$value : '';
    }
}
