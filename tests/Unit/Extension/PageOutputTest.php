<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Extension\PageOutput;

/**
 * EX-01 (ADR 0095): o que uma extensao pode colocar na pagina publica. A
 * extensao pede; quem escreve a tag (com escape e nonce) e o CMS.
 */
final class PageOutputTest extends TestCase {
    private const NONCE = 'bm9uY2UtZGUtdGVzdGU';

    private static function page(array $scriptSources = ['https://cdn.example.com', 'https://*.tags.example.net/lib/']): PageOutput {
        $page = new PageOutput('common/home', 'pt-br');
        $page->actingAs('demo', $scriptSources);

        return $page;
    }

    public function testKnowsThePageItIsBuilding(): void {
        $page = self::page();

        self::assertSame('common/home', $page->route());
        self::assertSame('pt-br', $page->language());
        self::assertFalse($page->has(PageOutput::HEAD));
        self::assertSame('', $page->render(PageOutput::HEAD, self::NONCE));
    }

    public function testMetaTagsAreEscaped(): void {
        $page = self::page();

        self::assertTrue($page->meta('google-site-verification', 'abc"><script>alert(1)</script>'));
        self::assertTrue($page->property('og:site_name', "Loja & Cia 'ok'"));

        self::assertSame(
            '<meta name="google-site-verification" content="abc&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;">' . "\n"
            . '<meta property="og:site_name" content="Loja &amp; Cia &apos;ok&apos;">' . "\n",
            $page->render(PageOutput::HEAD, self::NONCE)
        );
        self::assertSame('', $page->render(PageOutput::BODY_START, self::NONCE), 'meta so vai no head');
    }

    #[DataProvider('badMeta')]
    public function testBadMetaIsRefused(string $name, string $content): void {
        $page = self::page();

        self::assertFalse($page->meta($name, $content));
        self::assertFalse($page->property($name, $content));
        self::assertSame('', $page->render(PageOutput::HEAD, self::NONCE));
        self::assertCount(2, $page->rejected());
        self::assertStringStartsWith('demo: meta', $page->rejected()[0]);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function badMeta(): array {
        return [
            'nome vazio'            => ['', 'x'],
            'nome com espaco'       => ['a b', 'x'],
            'nome fecha atributo'   => ['a" http-equiv="refresh', 'x'],
            'nome com igual'        => ['a=b', 'x'],
            'nome comeca digito'    => ['1a', 'x'],
            'nome longo'            => [str_repeat('a', 65), 'x'],
            'nome com quebra'       => ["viewport\n", 'x'],
            'conteudo vazio'        => ['a', ''],
            'conteudo longo'        => ['a', str_repeat('x', 1025)],
            'conteudo com quebra'   => ['a', "x\ny"],
            'conteudo com nulo'     => ['a', "x\x00y"],
            'conteudo nao UTF-8'    => ['a', "x\xC3\x28"],
        ];
    }

    public function testThereIsNoWayToAskForHttpEquiv(): void {
        self::assertSame(
            [],
            array_values(array_filter(
                get_class_methods(PageOutput::class),
                static fn (string $m): bool => stripos($m, 'equiv') !== false || stripos($m, 'html') !== false || stripos($m, 'raw') !== false
            )),
            'nenhum metodo aceita HTML livre'
        );
    }

    public function testInlineScriptGetsTheNonceOfTheResponse(): void {
        $page = self::page();

        self::assertTrue($page->script('window.a = 1;'));
        self::assertTrue($page->script('window.b = 2;', PageOutput::BODY_START));

        self::assertSame('<script nonce="' . self::NONCE . '">window.a = 1;</script>' . "\n", $page->render(PageOutput::HEAD, self::NONCE));
        self::assertSame('<script nonce="' . self::NONCE . '">window.b = 2;</script>' . "\n", $page->render(PageOutput::BODY_START, self::NONCE));
        self::assertTrue($page->has(PageOutput::BODY_START));
    }

    public function testNonceIsEscapedEvenIfItCameOdd(): void {
        $page = self::page();
        $page->script('a();');

        self::assertSame('<script nonce="&quot;&gt;x">a();</script>' . "\n", $page->render(PageOutput::HEAD, '">x'));
    }

    #[DataProvider('badScripts')]
    public function testInlineScriptThatCouldEscapeTheTagIsRefused(string $code): void {
        $page = self::page();

        self::assertFalse($page->script($code));
        self::assertSame('', $page->render(PageOutput::HEAD, self::NONCE));
        self::assertStringStartsWith('demo: script:', $page->rejected()[0]);
    }

    /** @return array<string, array{0: string}> */
    public static function badScripts(): array {
        return [
            'vazio'                 => ['   '],
            'fecha a tag'           => ['var a = "</script><script>alert(1)</script>";'],
            'fecha, maiusculas'     => ['var a = "</SCRIPT >";'],
            'fecha, misto'          => ['var a = "</ScRiPt";'],
            'comentario html'       => ['var a = "<!--";'],
            'grande demais'         => [str_repeat('a', PageOutput::MAX_INLINE + 1)],
            'nao UTF-8'             => ["var a = '\xC3\x28';"],
        ];
    }

    public function testLargestInlineScriptStillFits(): void {
        self::assertTrue(self::page()->script(str_repeat('a', PageOutput::MAX_INLINE)));
    }

    public function testUnknownPositionIsRefused(): void {
        $page = self::page();

        self::assertFalse($page->script('a();', 'footer'));
        self::assertFalse($page->scriptSrc('https://cdn.example.com/a.js', ['position' => 'items']));
        self::assertSame(['demo: script: unknown position', 'demo: scriptSrc: unknown position'], $page->rejected());
    }

    public function testJsTurnsValuesIntoLiteralsThatCannotCloseTheTag(): void {
        $literal = PageOutput::js('</script><!-- & \' " ' . "\u{2028}");

        self::assertStringNotContainsString('<', $literal);
        self::assertStringNotContainsString('>', $literal);
        self::assertStringNotContainsString('&', $literal);
        self::assertStringNotContainsString("'", $literal);
        self::assertSame(1, preg_match('/^"[^"]*"$/', $literal), 'uma so string, sem aspas no meio');
        self::assertSame('</script><!-- & \' " ' . "\u{2028}", json_decode($literal));

        self::assertSame('{"id":"G-1","n":3,"ok":true}', PageOutput::js(['id' => 'G-1', 'n' => 3, 'ok' => true]));
        self::assertSame('"https://example.com/a"', PageOutput::js('https://example.com/a'));
        self::assertSame('null', PageOutput::js(NAN));

        $page = self::page();
        self::assertTrue($page->script('window.x = ' . PageOutput::js('</script><script>alert(1)</script>') . ';'), 'valor hostil, por js(), passa e fica inofensivo');
        self::assertStringNotContainsString('</script><script>', $page->render(PageOutput::HEAD, self::NONCE));
    }

    public function testScriptByAddressNeedsADeclaredOrigin(): void {
        $page = self::page();

        self::assertTrue($page->scriptSrc('https://cdn.example.com/lib/a.js?id=G-1&x=2'));
        self::assertTrue($page->scriptSrc('https://eu.tags.example.net/lib/t.js', ['position' => PageOutput::BODY_START]));

        self::assertSame('<script src="https://cdn.example.com/lib/a.js?id=G-1&amp;x=2"></script>' . "\n", $page->render(PageOutput::HEAD, self::NONCE));
        self::assertSame('<script src="https://eu.tags.example.net/lib/t.js"></script>' . "\n", $page->render(PageOutput::BODY_START, self::NONCE));
    }

    #[DataProvider('badAddresses')]
    public function testScriptAddressOutsideTheManifestIsRefused(string $url): void {
        $page = self::page();

        self::assertFalse($page->scriptSrc($url));
        self::assertSame('', $page->render(PageOutput::HEAD, self::NONCE));
        self::assertStringStartsWith('demo: scriptSrc:', $page->rejected()[0]);
        self::assertStringNotContainsString('<', $page->rejected()[0]);
    }

    /** @return array<string, array{0: string}> */
    public static function badAddresses(): array {
        return [
            'outro site'              => ['https://evil.example.org/a.js'],
            'http'                    => ['http://cdn.example.com/a.js'],
            'sem esquema'             => ['//cdn.example.com/a.js'],
            'relativo'                => ['/a.js'],
            'javascript'              => ['javascript:alert(1)'],
            'data'                    => ['data:text/javascript,alert(1)'],
            'sufixo parecido'         => ['https://cdn.example.com.evil.org/a.js'],
            'prefixo parecido'        => ['https://evilcdn.example.com/a.js'],
            'usuario na URL'          => ['https://cdn.example.com@evil.org/a.js'],
            'outra porta'             => ['https://cdn.example.com:8443/a.js'],
            'curinga nao cobre raiz'  => ['https://tags.example.net/lib/t.js'],
            'fora do caminho'         => ['https://eu.tags.example.net/outro/t.js'],
            'sobe do caminho'         => ['https://eu.tags.example.net/lib/../outro/t.js'],
            'sobe, codificado'        => ['https://eu.tags.example.net/lib/%2e%2e/outro/t.js'],
            'com aspas'               => ['https://cdn.example.com/a.js" onload="alert(1)'],
            'com espaco'              => ['https://cdn.example.com/a.js x'],
            'com menor-que'           => ['https://cdn.example.com/<script>'],
            'barra invertida'         => ['https://cdn.example.com\\@evil.org/a.js'],
            'quebra de linha no fim'  => ["https://cdn.example.com/a.js\n"],
            'longo demais'            => ['https://cdn.example.com/' . str_repeat('a', 2000)],
            'vazio'                   => [''],
        ];
    }

    public function testAddressFormIsCheckedBeforeTheOrigins(): void {
        // Duas barreiras: a forma do endereco (https, sem aspas nem espaco) e a origem declarada.
        $page = self::page();
        $page->scriptSrc('http://cdn.example.com/a.js');
        $page->scriptSrc('https://cdn.example.com/a.js"x');
        $page->scriptSrc('https://evil.example.org/a.js');

        self::assertSame([
            'demo: scriptSrc: the address must be https://',
            'demo: scriptSrc: the address must be https://',
            'demo: scriptSrc: https://evil.example.org/a.js is not covered by a script-src origin of the manifest',
        ], $page->rejected());
    }

    public function testExtensionWithoutDeclaredOriginCannotLoadAnyScript(): void {
        $page = self::page([]);

        self::assertFalse($page->scriptSrc('https://cdn.example.com/a.js'));
    }

    public function testOriginsBelongToTheExtensionOfTheMoment(): void {
        $page = new PageOutput();
        $page->actingAs('uma', ['https://cdn.example.com']);
        self::assertTrue($page->scriptSrc('https://cdn.example.com/a.js'));

        $page->actingAs('outra', []);
        self::assertFalse($page->scriptSrc('https://cdn.example.com/a.js'), 'a declaracao de uma nao vale para a outra');
        self::assertStringStartsWith('outra: ', $page->rejected()[0]);

        $page->actingAs('');
        self::assertFalse($page->scriptSrc('https://cdn.example.com/a.js'));
        self::assertStringStartsWith('core: ', $page->rejected()[1]);
    }

    public function testScriptOptionsAreWrittenByTheCms(): void {
        $page = self::page();

        self::assertTrue($page->scriptSrc('https://cdn.example.com/a.js', [
            'async' => true, 'defer' => true, 'id' => 'tag-1', 'integrity' => 'sha384-AbC+/123=', 'crossorigin' => 'anonymous',
            'data'  => ['domain' => 'loja.example', 'n' => 5],
        ]));

        self::assertSame(
            '<script src="https://cdn.example.com/a.js" id="tag-1" integrity="sha384-AbC+/123=" crossorigin="anonymous" data-domain="loja.example" data-n="5" async defer></script>' . "\n",
            $page->render(PageOutput::HEAD, self::NONCE)
        );
    }

    #[DataProvider('badOptions')]
    public function testBadScriptOptionsRefuseTheScript(array $options): void {
        $page = self::page();

        self::assertFalse($page->scriptSrc('https://cdn.example.com/a.js', $options));
        self::assertSame('', $page->render(PageOutput::HEAD, self::NONCE));
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function badOptions(): array {
        return [
            'opcao desconhecida'      => [['onload' => 'alert(1)']],
            'nonce pedido a mao'      => [['nonce' => 'x']],
            'type'                    => [['type' => 'module']],
            'id com aspas'            => [['id' => 'a" onload="x']],
            'id com quebra no fim'    => [['id' => "a\n"]],
            'integrity solto'         => [['integrity' => 'md5-abc']],
            'integrity com aspas'     => [['integrity' => 'sha256-abc" x="']],
            'crossorigin inventado'   => [['crossorigin' => 'yes']],
            'data nome invalido'      => [['data' => ['a b' => 'x']]],
            'data nome maiusculo'     => [['data' => ['Domain' => 'x']]],
            'data nome numerico'      => [['data' => ['x']]],
            'data valor lista'        => [['data' => ['a' => ['x']]]],
            'data valor longo'        => [['data' => ['a' => str_repeat('x', 501)]]],
        ];
    }

    public function testDataValueIsEscaped(): void {
        $page = self::page();
        $page->scriptSrc('https://cdn.example.com/a.js', ['data' => ['v' => '"><b>']]);

        self::assertSame('<script src="https://cdn.example.com/a.js" data-v="&quot;&gt;&lt;b&gt;"></script>' . "\n", $page->render(PageOutput::HEAD, self::NONCE));
    }

    public function testOnePageHasACeilingOfItems(): void {
        $page = self::page();

        for ($i = 0; $i < PageOutput::MAX_ITEMS; $i++) {
            self::assertTrue($page->meta('m' . $i, 'x'));
        }

        self::assertFalse($page->meta('demais', 'x'));
        self::assertFalse($page->script('a();', PageOutput::BODY_START), 'o teto e da pagina, nao da posicao');
        self::assertFalse($page->scriptSrc('https://cdn.example.com/a.js'));
        self::assertSame(PageOutput::MAX_ITEMS, substr_count($page->render(PageOutput::HEAD, self::NONCE), '<meta '));
    }

    public function testRejectionListIsBounded(): void {
        $page = self::page();

        for ($i = 0; $i < 50; $i++) {
            $page->meta('', 'x');
        }

        self::assertCount(20, $page->rejected());
    }

    public function testOutputKeepsTheOrderOfTheRequests(): void {
        $page = self::page();
        $page->script('one();');
        $page->meta('two', '2');
        $page->scriptSrc('https://cdn.example.com/three.js');

        $html = $page->render(PageOutput::HEAD, self::NONCE);

        self::assertLessThan(strpos($html, 'two'), strpos($html, 'one()'));
        self::assertLessThan(strpos($html, 'three.js'), strpos($html, 'two'));
    }
}
