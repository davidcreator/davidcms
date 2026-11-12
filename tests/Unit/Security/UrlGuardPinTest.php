<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\MediaUpload;
use Reamur\System\Library\Security\UrlGuard;
use Reamur\System\Library\Webhook\Dispatcher;

/**
 * S6 + S7 (ADR 0090): a guarda confere a URL crua, e quem conecta usa os
 * endereços que ela conferiu. Nenhum teste aqui usa a rede: o DNS é injetado.
 */
final class UrlGuardPinTest extends TestCase {
    /** @param array<int, string> $ips */
    private static function dns(array $ips, int &$calls = 0): callable {
        return static function (string $host) use ($ips, &$calls): array {
            $calls++;

            return $ips;
        };
    }

    // ── S7: endereços conferidos são os que seguem para o cURL ────────────

    public function testInspectReturnsTheCheckedAddresses(): void {
        $calls  = 0;
        $target = UrlGuard::inspect('https://hooks.example.org/in?x=1', true, true, self::dns(['93.184.216.34'], $calls));

        self::assertNotNull($target);
        self::assertSame(1, $calls, 'uma consulta por conferência');
        self::assertSame('https', $target['scheme']);
        self::assertSame('hooks.example.org', $target['host']);
        self::assertSame(443, $target['port']);
        self::assertSame(['93.184.216.34'], $target['ips']);
        self::assertSame(['hooks.example.org:443:93.184.216.34'], $target['resolve']);
    }

    public function testRebindingAnswerIsNeverHandedToCurl(): void {
        // 1ª consulta: público. 2ª consulta (a que o cURL faria): 127.0.0.1.
        $answers  = [['93.184.216.34'], ['127.0.0.1']];
        $resolver = static function (string $host) use (&$answers): array {
            return array_shift($answers) ?? [];
        };

        $first = UrlGuard::inspect('http://rebind.example.org/', false, true, $resolver);

        self::assertNotNull($first);
        self::assertSame(['rebind.example.org:80:93.184.216.34'], $first['resolve']);
        self::assertSame([CURLOPT_RESOLVE => ['rebind.example.org:80:93.184.216.34']], array_intersect_key(UrlGuard::curlOptions($first), [CURLOPT_RESOLVE => 1]));

        // Uma nova conferência (nova tentativa, novo salto) vê a resposta nova e recusa.
        self::assertNull(UrlGuard::inspect('http://rebind.example.org/', false, true, $resolver));
    }

    /** @return array<string, array{0: string, 1: array<int, string>, 2: list<string>}> */
    public static function pinCases(): array {
        return [
            'porta padrão http'          => ['http://a.example.org/', ['93.184.216.34'], ['a.example.org:80:93.184.216.34']],
            'porta explícita'            => ['https://a.example.org:8443/x', ['93.184.216.34'], ['a.example.org:8443:93.184.216.34']],
            'vários endereços'           => ['http://a.example.org/', ['93.184.216.34', '93.184.216.35'], ['a.example.org:80:93.184.216.34,93.184.216.35']],
            'endereço repetido'          => ['http://a.example.org/', ['93.184.216.34', '93.184.216.34'], ['a.example.org:80:93.184.216.34']],
            'IPv6'                       => ['http://a.example.org/', ['2606:2800:0220:0001:0000:0000:0000:0001'], ['a.example.org:80:2606:2800:220:1::1']],
            'IPv4 e IPv6'                => ['http://a.example.org/', ['93.184.216.34', '2606:2800:220:1::1'], ['a.example.org:80:93.184.216.34,2606:2800:220:1::1']],
            'nome em maiúsculas'         => ['http://A.Example.ORG/', ['93.184.216.34'], ['a.example.org:80:93.184.216.34']],
            'nome com ponto final'       => ['http://a.example.org./', ['93.184.216.34'], ['a.example.org.:80:93.184.216.34', 'a.example.org:80:93.184.216.34']],
        ];
    }

    /**
     * @param array<int, string> $ips
     * @param list<string>       $expected
     */
    #[DataProvider('pinCases')]
    public function testPinLines(string $url, array $ips, array $expected): void {
        $target = UrlGuard::inspect($url, false, true, self::dns($ips));

        self::assertNotNull($target);
        self::assertSame($expected, $target['resolve']);
    }

    /** @return array<string, array{0: array<int, string>}> */
    public static function refusedAnswers(): array {
        return [
            'sem resposta'                    => [[]],
            'loopback'                        => [['127.0.0.1']],
            'rede privada'                    => [['10.0.0.8']],
            'metadata cloud'                  => [['169.254.169.254']],
            'público + interno'               => [['93.184.216.34', '192.168.0.10']],
            'IPv4 público + IPv6 loopback'    => [['93.184.216.34', '::1']],
            'IPv4 público + IPv6 ULA'         => [['93.184.216.34', 'fd00::1']],
            'IPv4 embutido em IPv6'           => [['::ffff:10.0.0.1']],
            'resposta que não é endereço'     => [['93.184.216.34', 'lixo']],
        ];
    }

    /** @param array<int, string> $ips */
    #[DataProvider('refusedAnswers')]
    public function testAnyNonPublicAnswerRefusesTheWholeName(array $ips): void {
        self::assertNull(UrlGuard::inspect('http://a.example.org/', false, true, self::dns($ips)));
    }

    public function testAtMostEightAddressesArePinnedAndAllAreChecked(): void {
        $many = array_map(static fn (int $i): string => '93.184.216.' . $i, range(1, 12));

        $target = UrlGuard::inspect('http://a.example.org/', false, true, self::dns($many));

        self::assertNotNull($target);
        self::assertCount(8, $target['ips']);

        $many[] = '127.0.0.1'; // o 13º, fora do corte, ainda reprova o nome

        self::assertNull(UrlGuard::inspect('http://a.example.org/', false, true, self::dns($many)));
    }

    public function testOldLibcurlGetsOneAddressPerLine(): void {
        $ips = ['93.184.216.34', '2606:2800:220:1::1'];

        // 7.59.0 em diante aceita vários endereços por linha.
        self::assertSame(['a.example.org:443:93.184.216.34,2606:2800:220:1::1'], UrlGuard::pinLines(['a.example.org'], 443, $ips, 0x073B00));
        // Antes disso a linha com vírgula é descartada inteira pela libcurl: segue só o primeiro.
        self::assertSame(['a.example.org:443:93.184.216.34'], UrlGuard::pinLines(['a.example.org'], 443, $ips, 0x073A00));
        self::assertSame(['a.example.org:443:93.184.216.34'], UrlGuard::pinLines(['a.example.org'], 443, $ips, 0x071D00));
        self::assertSame(['a.:80:93.184.216.34', 'a:80:93.184.216.34'], UrlGuard::pinLines(['a.', 'a'], 80, $ips, 0));
        self::assertSame([], UrlGuard::pinLines(['a.example.org'], 443, []));
        self::assertSame([], UrlGuard::pinLines([], 443, $ips));
    }

    public function testLiteralPublicIpNeedsNoPin(): void {
        $calls  = 0;
        $target = UrlGuard::inspect('http://93.184.216.34/wp-json', false, true, self::dns(['127.0.0.1'], $calls));

        self::assertNotNull($target);
        self::assertSame(0, $calls);
        self::assertSame(['93.184.216.34'], $target['ips']);
        self::assertSame([], $target['resolve']);
        self::assertArrayNotHasKey(CURLOPT_RESOLVE, UrlGuard::curlOptions($target));
    }

    public function testWithoutDnsNothingIsPinnedAndNothingIsResolved(): void {
        $calls  = 0;
        $target = UrlGuard::inspect('https://hooks.example.org/in', true, false, self::dns(['127.0.0.1'], $calls));

        self::assertNotNull($target);
        self::assertSame(0, $calls);
        self::assertSame([], $target['ips']);
        self::assertSame([], $target['resolve']);
    }

    public function testCurlOptionsAlwaysRestrictProtocolsAndRedirects(): void {
        $target  = UrlGuard::inspect('http://a.example.org/', false, true, self::dns(['93.184.216.34']));
        self::assertNotNull($target);
        $options = UrlGuard::curlOptions($target);

        self::assertSame(UrlGuard::allowedCurlProtocols(), $options[CURLOPT_PROTOCOLS]);
        self::assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        self::assertSame($target['resolve'], $options[CURLOPT_RESOLVE]);
    }

    // ── Host escrito de jeito que o cURL lê como IP interno ───────────────

    /** @return array<string, array{0: string}> */
    public static function disguisedHosts(): array {
        return [
            'IP decimal'                    => ['http://2130706433/'],
            'IP decimal com porta'          => ['https://2130706433:8443/hook'],
            'IP hexadecimal'                => ['http://0x7f000001/'],
            'IP hexadecimal em maiúsculas'  => ['http://0X7F000001/'],
            'IP octal'                      => ['http://0177.0.0.1/'],
            'IP curto 127.1'                => ['http://127.1/'],
            'IP curto 10.1'                 => ['http://10.1/'],
            'zero'                          => ['http://0/'],
            'último rótulo hexadecimal'     => ['http://exemplo.0x1/'],
            'IP público em decimal'         => ['http://134744072/'],
            'localhost com ponto final'     => ['http://localhost./'],
            'subdomínio de localhost.'      => ['http://x.localhost./'],
            '.internal com ponto final'     => ['http://metadata.google.internal./'],
            'IPv4-compatível'               => ['http://[::127.0.0.1]/'],
            'NAT64 de 127.0.0.1'            => ['http://[64:ff9b::7f00:1]/'],
            'NAT64 de 169.254.169.254'      => ['http://[64:ff9b::a9fe:a9fe]/'],
            'NAT64 de uso local'            => ['http://[64:ff9b:1::1]/'],
            '6to4 de 127.0.0.1'             => ['http://[2002:7f00:1::]/'],
            '6to4 de 10.0.0.1'              => ['http://[2002:a00:1::1]/'],
            'Teredo'                        => ['http://[2001:0:4136:e378:8000:63bf:3fff:fdd2]/'],
            'IPv6 multicast'                => ['http://[ff02::1]/'],
            'IPv6 de documentação'          => ['http://[2001:db8::1]/'],
            'IPv4 multicast'                => ['http://224.0.0.1/'],
            'IPv4 de documentação'          => ['http://203.0.113.9/'],
        ];
    }

    #[DataProvider('disguisedHosts')]
    public function testDisguisedInternalHostsAreRefusedWithAndWithoutDns(string $url): void {
        $resolver = self::dns(['93.184.216.34']); // mesmo que o DNS "garanta" um IP público

        self::assertFalse(UrlGuard::isSafeExternalUrl($url, false, false), 'sem DNS (modo usado ao salvar um webhook)');
        self::assertNull(UrlGuard::inspect($url, false, true, $resolver), 'com DNS');
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function embeddedIpv4(): array {
        return [
            'NAT64 de IPv4 público'   => ['64:ff9b::5db8:d822', true],   // 93.184.216.34: rede só IPv6 com DNS64
            'NAT64 de IPv4 privado'   => ['64:ff9b::c0a8:1', false],
            '6to4 de IPv4 público'    => ['2002:5db8:d822::1', true],
            '6to4 de IPv4 privado'    => ['2002:c0a8:1::1', false],
            'IPv6 público comum'      => ['2606:2800:220:1::1', true],
            'vizinho do Teredo'       => ['2001:1::1', true],
            'vizinho da documentação' => ['2001:db9::1', true],
        ];
    }

    #[DataProvider('embeddedIpv4')]
    public function testIpv6CarryingIpv4FollowsTheEmbeddedAddress(string $ip, bool $expected): void {
        self::assertSame($expected, UrlGuard::isPublicIp($ip));
    }

    public function testNamesThatOnlyLookNumericStillWork(): void {
        foreach (['http://1e100.net/', 'http://0x10.example.org/', 'http://123.example/', 'http://a.b.c0/'] as $url) {
            self::assertNotNull(UrlGuard::inspect($url, false, true, self::dns(['93.184.216.34'])), $url);
        }
    }

    // ── S6: a URL é conferida crua ────────────────────────────────────────

    /** @return array<string, array{0: string}> */
    public static function rawUrls(): array {
        return [
            'quebra de linha no fim'   => ["https://example.org/hook\n"],
            'CR LF no fim'             => ["https://example.org/hook\r\n"],
            'tab no fim'               => ["https://example.org/hook\t"],
            'NUL no fim'               => ["https://example.org/hook\0"],
            'espaço no fim'            => ['https://example.org/hook '],
            'espaço no início'         => [' https://example.org/hook'],
            'quebra de linha no início' => ["\nhttps://example.org/hook"],
            'NUL no início'            => ["\0https://example.org/hook"],
            'DEL no fim'               => ["https://example.org/hook\x7f"],
            'vertical tab no fim'      => ["https://example.org/hook\x0b"],
        ];
    }

    #[DataProvider('rawUrls')]
    public function testControlBytesAtTheEdgesAreRefused(string $url): void {
        self::assertFalse(UrlGuard::isSafeExternalUrl($url, true, false));
        self::assertNull(UrlGuard::inspect($url, false, true, self::dns(['93.184.216.34'])));
        self::assertTrue(UrlGuard::isSafeExternalUrl(trim($url, "\x00..\x20\x7f"), true, false), 'a mesma URL, limpa, vale');
    }

    public function testInspectKeepsTheUrlItChecked(): void {
        $target = UrlGuard::inspect('https://example.org/hook?a=1', true, false);

        self::assertNotNull($target);
        self::assertSame('https://example.org/hook?a=1', $target['url']);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function redirects(): array {
        return [
            'absoluto http'          => ['http://cdn.example.org/a', 'http://cdn.example.org/a'],
            'absoluto com espaços'   => ["  https://cdn.example.org/a \t", 'https://cdn.example.org/a'],
            'relativo à raiz'        => ['/wp-json/x', 'https://example.org/wp-json/x'],
            'relativo ao caminho'    => ['next', 'https://example.org/blog/next'],
            'esquema file'           => ['file:///etc/passwd', ''],
            'esquema gopher'         => ['gopher://127.0.0.1:6379/_x', ''],
            'esquema javascript'     => ['javascript:alert(1)', ''],
            'esquema data'           => ['data:text/plain,x', ''],
            'NUL no fim'             => ["https://cdn.example.org/a\0", ''],
            'quebra de linha no fim' => ["https://cdn.example.org/a\n", ''],
            'CR no fim'              => ["/wp-json/x\r", ''],
            'vazio'                  => ['', ''],
        ];
    }

    #[DataProvider('redirects')]
    public function testRedirectTargets(string $location, string $expected): void {
        self::assertSame($expected, UrlGuard::resolveRedirect('https://example.org/blog/post', $location));
    }

    public function testMediaUploadAbsoluteChecksTheRawPath(): void {
        $root = sys_get_temp_dir() . '/rms-s6-' . bin2hex(random_bytes(4)) . '/';
        mkdir($root . 'view/image/x', 0777, true);
        file_put_contents($root . 'view/image/x/a.png', 'x');

        try {
            $media = new MediaUpload($root);

            self::assertSame($root . 'view/image/x/a.png', $media->absolute('view/image/x/a.png'));
            self::assertSame($root . 'view/image/x/a.png', $media->absolute(' view/image/x/a.png '), 'espaço nas pontas continua tolerado');

            foreach (["view/image/x/a.png\0", "view/image/x/a.png\n", "\tview/image/x/a.png", "view/image/x/a.png\r\n", "view/image/x/a.png\x7f"] as $path) {
                self::assertSame('', $media->absolute($path), bin2hex($path));
                $media->delete($path); // não apaga o arquivo "limpo" por tabela
            }

            self::assertFileExists($root . 'view/image/x/a.png');
        } finally {
            @unlink($root . 'view/image/x/a.png');
            @rmdir($root . 'view/image/x');
            @rmdir($root . 'view/image');
            @rmdir($root . 'view');
            @rmdir($root);
        }
    }

    // ── Quem conecta: webhook e importador ────────────────────────────────

    public function testDispatcherRefusesRawControlBytesWithoutRetriesAndLogsOneLine(): void {
        $log = new class {
            /** @var array<int, string> */
            public array $messages = [];

            public function write(string $msg): void {
                $this->messages[] = $msg;
            }
        };

        $started = microtime(true);
        $results = (new Dispatcher($log))->dispatch('order_add', ['order_id' => 1], [
            ['webhook_id' => 3, 'url' => "https://example.org/hook\n2026-01-01 linha forjada", 'secret' => 'segredo-de-teste'],
            ['webhook_id' => 4, 'url' => "https://example.org/hook\n", 'secret' => 'segredo-de-teste'],
            ['webhook_id' => 5, 'url' => 'https://2130706433/hook', 'secret' => 'segredo-de-teste'],
        ]);

        self::assertLessThan(1.5, microtime(true) - $started, 'recusa não espera as novas tentativas');
        self::assertCount(3, $results);

        foreach ($results as $entry) {
            self::assertSame(0, $entry['http_code']);
            self::assertSame(1, $entry['attempt']);
        }

        foreach ($log->messages as $message) {
            self::assertDoesNotMatchRegularExpression('/[\x00-\x1f\x7f]/', $message);
        }
    }

    public function testImporterNeverThrowsOnHostileUrls(): void {
        $import = new \Reamur\Admin\Model\Blog\Import(new Registry());
        $get    = new \ReflectionMethod($import, 'httpGet');

        foreach (["http://example.org/a.png\0", "http://example.org/a.png\n", 'http://127.0.0.1/a.png', 'http://2130706433/a.png', 'file:///etc/passwd', ''] as $url) {
            self::assertSame(['body' => '', 'error' => 'ssrf_blocked'], $get->invoke($import, $url), bin2hex($url));
        }

        self::assertSame('invalid_base_url', $import->fetchPage("http://127.0.0.1\n", 1, 5)['error']);
    }

    public function testImporterCleansTheCoverUrlBeforeCheckingIt(): void {
        $import    = new \Reamur\Admin\Model\Blog\Import(new Registry());
        $normalize = new \ReflectionMethod($import, 'normalize');

        $row = static fn (string $src): array => [
            'id' => 1, 'slug' => 'a', 'title' => ['rendered' => 'A'],
            '_embedded' => ['wp:featuredmedia' => [['source_url' => $src]]],
        ];

        self::assertSame('https://example.org/a.png', $normalize->invoke($import, $row("https://example.org/a.png\0"))['image_url']);
        self::assertSame('https://example.org/a.png', $normalize->invoke($import, $row("  https://example.org/a.png\n"))['image_url']);
        self::assertSame('', $normalize->invoke($import, $row('ftp://example.org/a.png'))['image_url']);
        self::assertSame('', $normalize->invoke($import, $row("java\nscript:alert(1)"))['image_url']);
    }

    public function testEveryCurlCallerGoesThroughTheGuard(): void {
        $root      = dirname(__DIR__, 3) . '/';
        $allowed   = ['dev/system/library/mercadopago_marketplace.php']; // só fala com a API fixa do Mercado Pago
        $offenders = [];
        $seen      = 0;

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . 'dev', \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));

            if ($file->getExtension() !== 'php' || str_contains($path, '/storage/') || in_array($path, $allowed, true)) {
                continue;
            }

            $source = (string)file_get_contents($file->getPathname());

            if (!str_contains($source, 'curl_init(')) {
                continue;
            }

            $seen++;

            if (!str_contains($source, 'UrlGuard::inspect(') || !str_contains($source, 'UrlGuard::curlOptions(')
                || preg_match('/CURLOPT_FOLLOWLOCATION\s*=>\s*true|CURLOPT_FOLLOWLOCATION,\s*true/', $source) === 1) {
                $offenders[] = $path;
            }
        }

        self::assertSame(2, $seen, 'webhook e importador de blog');
        self::assertSame([], $offenders, 'quem chama curl_init usa UrlGuard::inspect() + UrlGuard::curlOptions()');
    }

    public function testImporterLimitsTheResponseSize(): void {
        $source = (string)file_get_contents(dirname(__DIR__, 3) . '/dev/admin/model/blog/import.php');

        self::assertStringContainsString('CURLOPT_MAXFILESIZE    => $maxBytes', $source);
        self::assertStringContainsString('CURLOPT_WRITEFUNCTION', $source);
        self::assertStringContainsString('$this->httpGet($url, false, self::MAX_IMAGE_BYTES)', $source);
        self::assertStringNotContainsString('CURLOPT_RETURNTRANSFER', $source, 'resposta inteira na memória, sem limite');
    }
}
