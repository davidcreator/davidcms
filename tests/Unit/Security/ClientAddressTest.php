<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Request;
use Reamur\System\Library\Security\ClientAddress;
use Reamur\System\Library\Session;

/**
 * S5 (ADR 0089): atras de CDN ou proxy, o IP do visitante e o HTTPS chegam em
 * cabecalhos que qualquer cliente tambem pode escrever. So valem quando a
 * instalacao declara em quem confiar.
 */
final class ClientAddressTest extends TestCase {
    private const CDN = '203.0.113.0/24';

    /** @param array<string, mixed> $extra */
    private static function server(string $peer, ?string $xff = null, ?string $proto = null, array $extra = []): array {
        $server = ['REMOTE_ADDR' => $peer] + $extra;

        if ($xff !== null) {
            $server['HTTP_X_FORWARDED_FOR'] = $xff;
        }

        if ($proto !== null) {
            $server['HTTP_X_FORWARDED_PROTO'] = $proto;
        }

        return $server;
    }

    // ── Sem confianca declarada: tudo como sempre foi ─────────────────────

    public function testWithoutTrustTheConnectionAddressWins(): void {
        $c = ClientAddress::resolve(self::server('198.51.100.7', '9.9.9.9', 'https'));

        self::assertSame('198.51.100.7', $c->ip);
        self::assertSame('198.51.100.7', $c->peer);
        self::assertFalse($c->https, 'X-Forwarded-Proto de quem nao e confiavel nao vale');
        self::assertSame(ClientAddress::MODE_DIRECT, $c->mode);
        self::assertTrue($c->forwarded, 'o cabecalho foi visto (o painel avisa)');
        self::assertFalse($c->honoured);
    }

    /** @return array<string, array{0: mixed, 1: bool}> */
    public static function directHttps(): array {
        return [
            'on'        => ['on', true],
            'ON'        => ['ON', true],
            '1'         => ['1', true],
            'off'       => ['off', false],
            'OFF'       => ['OFF', false],
            'vazio'     => ['', false],
            'ausente'   => [null, false],
            'nao texto' => [['on'], false],
        ];
    }

    #[DataProvider('directHttps')]
    public function testDirectHttpsFollowsTheServerVariable(mixed $value, bool $expected): void {
        $server = ['REMOTE_ADDR' => '198.51.100.7'];

        if ($value !== null) {
            $server['HTTPS'] = $value;
        }

        self::assertSame($expected, ClientAddress::resolve($server)->https);
    }

    // ── Modo lista ────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: string|null, 2: string, 3: string}> */
    public static function listCases(): array {
        return [
            'proxy confiavel repassa o visitante'       => ['203.0.113.10', '198.51.100.7', self::CDN, '198.51.100.7'],
            'visitante forja a esquerda'                => ['203.0.113.10', '9.9.9.9, 198.51.100.7', self::CDN, '198.51.100.7'],
            'visitante forja varios'                    => ['203.0.113.10', '1.1.1.1, 2.2.2.2, 198.51.100.7', self::CDN, '198.51.100.7'],
            'visitante forja o IP de um proxy'          => ['203.0.113.10', '203.0.113.99, 198.51.100.7', self::CDN, '198.51.100.7'],
            'dois proxies confiaveis em cadeia'         => ['10.0.0.5', '198.51.100.7, 203.0.113.10', self::CDN . ', 10.0.0.0/8', '198.51.100.7'],
            'cadeia com forja na ponta'                 => ['10.0.0.5', '9.9.9.9, 198.51.100.7, 203.0.113.10', self::CDN . ', 10.0.0.0/8', '198.51.100.7'],
            'conexao de fora da lista: cabecalho nulo'  => ['198.51.100.7', '9.9.9.9', self::CDN, '198.51.100.7'],
            'proxy confiavel sem cabecalho'             => ['203.0.113.10', null, self::CDN, '203.0.113.10'],
            'proxy confiavel com cabecalho vazio'       => ['203.0.113.10', '  ', self::CDN, '203.0.113.10'],
            'so proxies na cadeia: o mais a esquerda'   => ['203.0.113.10', '203.0.113.20, 203.0.113.30', self::CDN, '203.0.113.20'],
            'entrada ilegivel a direita'                => ['203.0.113.10', '198.51.100.7, lixo', self::CDN, '203.0.113.10'],
            'entrada ilegivel no meio'                  => ['10.0.0.5', '198.51.100.7, <script>, 203.0.113.10', self::CDN . ', 10.0.0.0/8', '203.0.113.10'],
            'entrada com porta'                         => ['203.0.113.10', '198.51.100.7:51234', self::CDN, '198.51.100.7'],
            'visitante IPv6'                            => ['203.0.113.10', '2001:DB8:0:0:0:0:0:7', self::CDN, '2001:db8::7'],
            'visitante IPv6 entre colchetes com porta'  => ['203.0.113.10', '[2001:db8::7]:443', self::CDN, '2001:db8::7'],
            'proxy IPv6 em faixa'                       => ['2001:db8:aa::1', '198.51.100.7', '2001:db8:aa::/48', '198.51.100.7'],
            'proxy IPv6 fora da faixa'                  => ['2001:db8:ab::1', '198.51.100.7', '2001:db8:aa::/48', '2001:db8:ab::1'],
            'proxy como IPv4 embutido em IPv6'          => ['::ffff:203.0.113.10', '198.51.100.7', self::CDN, '198.51.100.7'],
            'endereco exato na lista'                   => ['203.0.113.10', '198.51.100.7', '203.0.113.10', '198.51.100.7'],
            'endereco vizinho nao esta na lista'        => ['203.0.113.11', '198.51.100.7', '203.0.113.10', '203.0.113.11'],
            'faixa que nao fecha em byte: dentro'       => ['203.0.127.255', '198.51.100.7', '203.0.112.0/20', '198.51.100.7'],
            'faixa que nao fecha em byte: fora'         => ['203.0.128.0', '198.51.100.7', '203.0.112.0/20', '203.0.128.0'],
            'faixa IPv6 que nao fecha em byte: dentro'  => ['2001:db8:7fff::1', '198.51.100.7', '2001:db8::/33', '198.51.100.7'],
            'faixa IPv6 que nao fecha em byte: fora'    => ['2001:db8:8000::1', '198.51.100.7', '2001:db8::/33', '2001:db8:8000::1'],
            'faixa IPv4 nao casa com proxy IPv6'        => ['2001:db8::1', '198.51.100.7', '0.0.0.0/1, 128.0.0.0/1', '2001:db8::1'],
        ];
    }

    #[DataProvider('listCases')]
    public function testListMode(string $peer, ?string $xff, string $trusted, string $expected): void {
        $c = ClientAddress::resolve(self::server($peer, $xff), $trusted);

        self::assertSame($expected, $c->ip);
        self::assertSame(ClientAddress::MODE_LIST, $c->mode);
    }

    public function testListModeAcceptsAnArray(): void {
        $c = ClientAddress::resolve(self::server('203.0.113.10', '198.51.100.7'), [self::CDN, '10.0.0.0/8']);

        self::assertSame('198.51.100.7', $c->ip);
        self::assertSame(2, $c->entries);
    }

    public function testProtoIsOnlyBelievedFromATrustedProxy(): void {
        $trusted   = ClientAddress::resolve(self::server('203.0.113.10', '198.51.100.7', 'https'), self::CDN);
        $untrusted = ClientAddress::resolve(self::server('198.51.100.7', '9.9.9.9', 'https'), self::CDN);

        self::assertTrue($trusted->https);
        self::assertTrue($trusted->honoured);
        self::assertFalse($untrusted->https);
        self::assertFalse($untrusted->honoured);
    }

    public function testTrustedProxyCanSayTheVisitorIsOnPlainHttp(): void {
        // CDN fala com o servidor por TLS, mas o visitante chegou por HTTP.
        $c = ClientAddress::resolve(self::server('203.0.113.10', '198.51.100.7', 'http', ['HTTPS' => 'on']), self::CDN);

        self::assertFalse($c->https);
    }

    public function testTrustedProxyWithoutProtoKeepsTheDirectReading(): void {
        $c = ClientAddress::resolve(self::server('203.0.113.10', '198.51.100.7', null, ['HTTPS' => 'on']), self::CDN);

        self::assertTrue($c->https);
    }

    public function testProtoUsesTheFirstValueAndIgnoresCase(): void {
        self::assertTrue(ClientAddress::resolve(self::server('203.0.113.10', '198.51.100.7', ' HTTPS , http'), self::CDN)->https);
        self::assertFalse(ClientAddress::resolve(self::server('203.0.113.10', '198.51.100.7', 'http, https'), self::CDN)->https);
        self::assertFalse(ClientAddress::resolve(self::server('203.0.113.10', '198.51.100.7', 'javascript'), self::CDN)->https);
    }

    // ── Modo saltos ───────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: string|null, 2: int, 3: string}> */
    public static function hopCases(): array {
        return [
            'um proxy'                               => ['203.0.113.10', '198.51.100.7', 1, '198.51.100.7'],
            'um proxy, visitante forja a esquerda'   => ['203.0.113.10', '9.9.9.9, 198.51.100.7', 1, '198.51.100.7'],
            'dois proxies'                           => ['10.0.0.5', '198.51.100.7, 203.0.113.10', 2, '198.51.100.7'],
            'dois proxies, forja a esquerda'         => ['10.0.0.5', '9.9.9.9, 198.51.100.7, 203.0.113.10', 2, '198.51.100.7'],
            'menos saltos que o declarado'           => ['203.0.113.10', '198.51.100.7', 3, '198.51.100.7'],
            'sem cabecalho (acesso por fora)'        => ['198.51.100.7', null, 1, '198.51.100.7'],
            'entrada ilegivel no caminho'            => ['10.0.0.5', '198.51.100.7, lixo', 2, '10.0.0.5'],
            'saltos zero e o modo direto'            => ['203.0.113.10', '198.51.100.7', 0, '203.0.113.10'],
            'saltos negativos e o modo direto'       => ['203.0.113.10', '198.51.100.7', -2, '203.0.113.10'],
        ];
    }

    #[DataProvider('hopCases')]
    public function testHopsMode(string $peer, ?string $xff, int $hops, string $expected): void {
        self::assertSame($expected, ClientAddress::resolve(self::server($peer, $xff), '', $hops)->ip);
    }

    public function testHopsModeBelievesProtoAndReportsItself(): void {
        $c = ClientAddress::resolve(self::server('203.0.113.10', '198.51.100.7', 'https'), '', 1);

        self::assertTrue($c->https);
        self::assertSame(ClientAddress::MODE_HOPS, $c->mode);
        self::assertSame(1, $c->hops);
    }

    public function testListWinsOverHops(): void {
        // Com os dois declarados vale a lista: conexao de fora da lista nao e proxy.
        $c = ClientAddress::resolve(self::server('198.51.100.7', '9.9.9.9', 'https'), self::CDN, 1);

        self::assertSame(ClientAddress::MODE_LIST, $c->mode);
        self::assertSame('198.51.100.7', $c->ip);
        self::assertFalse($c->https);
        self::assertSame(0, $c->hops);
    }

    public function testInvalidListDoesNotSilentlyBecomeTrust(): void {
        // Lista so com lixo = nenhuma entrada valida = modo direto, com o lixo relatado.
        $c = ClientAddress::resolve(self::server('203.0.113.10', '198.51.100.7', 'https'), 'cdn.example, 300.1.1.1, 10.0.0.0/33');

        self::assertSame(ClientAddress::MODE_DIRECT, $c->mode);
        self::assertSame('203.0.113.10', $c->ip);
        self::assertSame(['cdn.example', '300.1.1.1', '10.0.0.0/33'], $c->invalid);
    }

    // ── Lista de confianca ────────────────────────────────────────────────

    /** @return array<string, array{0: string}> */
    public static function rejectedEntries(): array {
        return [
            'confiar em todo IPv4'       => ['0.0.0.0/0'],
            'confiar em todo IPv6'       => ['::/0'],
            'prefixo maior que o IPv4'   => ['10.0.0.0/33'],
            'prefixo maior que o IPv6'   => ['2001:db8::/129'],
            'prefixo vazio'              => ['10.0.0.0/'],
            'prefixo negativo'           => ['10.0.0.0/-8'],
            'prefixo nao numerico'       => ['10.0.0.0/abc'],
            'nome de host'               => ['cdn.example.com'],
            'curinga'                    => ['*'],
            'octeto invalido'            => ['256.0.0.1'],
            'IPv4 embutido, prefixo curto' => ['::ffff:0:0/95'],
            'IPv4 embutido inteiro'      => ['::ffff:0:0/96'],
        ];
    }

    #[DataProvider('rejectedEntries')]
    public function testTrustedListRejects(string $entry): void {
        [$ranges, $invalid] = ClientAddress::parseTrusted($entry);

        self::assertSame([], $ranges);
        self::assertSame([$entry], $invalid);
    }

    public function testTrustedListAcceptsCommonSeparators(): void {
        [$ranges, $invalid] = ClientAddress::parseTrusted("203.0.113.0/24,10.0.0.1 ; 2001:db8::/32\n::ffff:192.0.2.0/120\t192.0.2.9");

        self::assertCount(5, $ranges);
        self::assertSame([], $invalid);
    }

    public function testTrustedListIgnoresNonStringsAndBlanks(): void {
        [$ranges, $invalid] = ClientAddress::parseTrusted(['10.0.0.1', '', '  ', 42, null, ['x'], '10.0.0.2']);

        self::assertCount(2, $ranges);
        self::assertSame([], $invalid);
    }

    public function testEmbeddedIpv4RangeMatchesIpv4Proxy(): void {
        $c = ClientAddress::resolve(self::server('192.0.2.77', '198.51.100.7'), '::ffff:192.0.2.0/120');

        self::assertSame('198.51.100.7', $c->ip);
    }

    // ── Entradas hostis ───────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: string}> */
    public static function addresses(): array {
        return [
            'IPv4'                  => ['198.51.100.7', '198.51.100.7'],
            'com espacos'           => ['  198.51.100.7 ', '198.51.100.7'],
            'IPv4 com porta'        => ['198.51.100.7:8080', '198.51.100.7'],
            'IPv6 por extenso'      => ['2001:0DB8:0000:0000:0000:0000:0000:0001', '2001:db8::1'],
            'IPv6 entre colchetes'  => ['[2001:db8::1]', '2001:db8::1'],
            'IPv6 com zona'         => ['fe80::1%eth0', 'fe80::1'],
            'IPv4 embutido'         => ['::ffff:198.51.100.7', '198.51.100.7'],
            'vazio'                 => ['', ''],
            'texto'                 => ['unknown', ''],
            'marcacao'              => ['<script>alert(1)</script>', ''],
            'aspas de SQL'          => ["1.1.1.1' OR '1'='1", ''],
            'quebra de linha'       => ["198.51.100.7\r\nX-Outro: 1", ''],
            'colchete aberto'       => ['[2001:db8::1', ''],
            'gigante'               => [str_repeat('1', 200), ''],
        ];
    }

    #[DataProvider('addresses')]
    public function testNormalize(string $input, string $expected): void {
        self::assertSame($expected, ClientAddress::normalize($input));
    }

    public function testResultAlwaysFitsTheDatabaseColumn(): void {
        // rms_rate_limit.ip e as colunas ip de contato e pedido sao VARCHAR(40).
        $longest = ClientAddress::resolve(
            self::server('203.0.113.10', 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'),
            self::CDN
        );

        self::assertSame(39, strlen($longest->ip));
    }

    public function testBrokenConnectionAddressGivesAnEmptyIp(): void {
        foreach ([[], ['REMOTE_ADDR' => ''], ['REMOTE_ADDR' => 'lixo'], ['REMOTE_ADDR' => ['1.1.1.1']]] as $server) {
            $c = ClientAddress::resolve($server + ['HTTP_X_FORWARDED_FOR' => '9.9.9.9'], self::CDN, 1);

            self::assertSame('', $c->ip);
            self::assertSame('', $c->peer);
        }
    }

    public function testHeadersThatAreNotTextAreIgnored(): void {
        $c = ClientAddress::resolve([
            'REMOTE_ADDR'            => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR'   => ['198.51.100.7'],
            'HTTP_X_FORWARDED_PROTO' => ['https'],
        ], self::CDN);

        self::assertSame('203.0.113.10', $c->ip);
        self::assertFalse($c->https);
        self::assertFalse($c->forwarded);
    }

    public function testVeryLongChainOnlyReadsTheEnd(): void {
        $forged = implode(', ', array_fill(0, 500, '9.9.9.9'));
        $c      = ClientAddress::resolve(self::server('203.0.113.10', $forged . ', 198.51.100.7'), self::CDN);

        self::assertSame('198.51.100.7', $c->ip);

        $hops = ClientAddress::resolve(self::server('203.0.113.10', $forged . ', 198.51.100.7'), '', 100000);

        self::assertSame(32, $hops->hops, 'saltos declarados tem teto');
        self::assertSame('9.9.9.9', $hops->ip);
    }

    // ── Request e Session ─────────────────────────────────────────────────

    public function testRequestDefaultsToTheDirectConnection(): void {
        $request = new Request();
        $request->server = self::server('203.0.113.10', '198.51.100.7', 'https');

        self::assertSame('203.0.113.10', $request->ip());
        self::assertFalse($request->isHttps());
    }

    public function testRequestUsesTheDeclaredTrust(): void {
        $request = new Request();
        $request->server = self::server('203.0.113.10', '9.9.9.9, 198.51.100.7', 'https');
        $request->setProxyTrust(self::CDN);

        self::assertSame('198.51.100.7', $request->ip());
        self::assertTrue($request->isHttps());
        self::assertSame('203.0.113.10', $request->connection()->peer);

        // lido na hora: pre-acoes e testes podem trocar o array
        $request->server = self::server('198.51.100.99', '9.9.9.9', 'https');

        self::assertSame('198.51.100.99', $request->ip());
        self::assertFalse($request->isHttps());
    }

    public function testRequestHopsMode(): void {
        $request = new Request();
        $request->server = self::server('203.0.113.10', '9.9.9.9, 198.51.100.7');
        $request->setProxyTrust('', 1);

        self::assertSame('198.51.100.7', $request->ip());
    }

    /** @return array<string, array{0: string, 1: bool, 2: bool}> */
    public static function cookieCases(): array {
        return [
            'site https, conexao vista como http' => ['https://example.test/catalog/', false, true],
            'site https, conexao https'           => ['https://example.test/catalog/', true, true],
            'site HTTPS em maiusculas'            => ['HTTPS://example.test/', false, true],
            'site http, conexao https'            => ['http://example.test/', true, true],
            'site http, conexao http'             => ['http://localhost/dev/catalog/', false, false],
            'sem endereco'                        => ['', false, false],
            'https no meio do endereco'           => ['http://example.test/?u=https://x', false, false],
        ];
    }

    #[DataProvider('cookieCases')]
    public function testSessionCookieSecureRule(string $siteUrl, bool $https, bool $expected): void {
        self::assertSame($expected, Session::cookieSecure($siteUrl, $https));
    }

    // ── Instalador ────────────────────────────────────────────────────────

    /** @return array<string, array{0: array<string, mixed>, 1: bool}> */
    public static function installerCases(): array {
        return [
            'http puro'                 => [['SERVER_PORT' => '80'], false],
            'HTTPS on'                  => [['HTTPS' => 'on'], true],
            'HTTPS off'                 => [['HTTPS' => 'off'], false],
            'porta 443'                 => [['SERVER_PORT' => '443'], true],
            'porta 443 como numero'     => [['SERVER_PORT' => 443], true],
            'proxy informa https'       => [['HTTP_X_FORWARDED_PROTO' => 'https'], true],
            'proxy informa HTTPS, http' => [['HTTP_X_FORWARDED_PROTO' => 'HTTPS, http'], true],
            'proxy informa http'        => [['HTTP_X_FORWARDED_PROTO' => 'http'], false],
            'cabecalho nao texto'       => [['HTTP_X_FORWARDED_PROTO' => ['https']], false],
            'vazio'                     => [[], false],
        ];
    }

    /** @param array<string, mixed> $server */
    #[DataProvider('installerCases')]
    public function testInstallerSchemeGuess(array $server, bool $expected): void {
        self::assertSame($expected, ClientAddress::looksHttps($server));
    }
}
