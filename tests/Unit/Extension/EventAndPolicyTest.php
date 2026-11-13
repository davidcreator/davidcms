<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Engine\Event;
use Reamur\System\Library\Security\Csp;

/**
 * EX-01 (ADR 0095): as duas pecas do nucleo que as extensoes passaram a usar:
 * eventos com prioridade e origens acrescentadas a politica de conteudo.
 */
final class EventAndPolicyTest extends TestCase {
    // ── eventos ─────────────────────────────────────────────────────────────

    public function testListenersRunByPriorityThenByRegistrationOrder(): void {
        $event = new Event();
        $order = [];
        $add   = static function (string $tag, int $priority) use ($event, &$order): void {
            $event->register('t', static function (array &$args) use ($tag, &$order): void {
                $order[] = $tag;
            }, $priority);
        };

        $add('c', 10);
        $add('a', -10);
        $add('b1', 0);
        $add('b2', 0);
        $add('a2', -10);

        $args = [];
        $event->trigger('t', $args);

        self::assertSame(['a', 'a2', 'b1', 'b2', 'c'], $order);
    }

    public function testDefaultPriorityKeepsTheOldBehaviour(): void {
        $event = new Event();
        $order = [];

        foreach (['um', 'dois', 'tres'] as $tag) {
            $event->register('t', static function (array &$args) use ($tag, &$order): void {
                $order[] = $tag;
            });
        }

        $args = [];
        $event->trigger('t', $args);
        $event->trigger('t', $args);

        self::assertSame(['um', 'dois', 'tres', 'um', 'dois', 'tres'], $order);
    }

    public function testListenerAddedAfterATriggerStillTakesItsPlace(): void {
        $event = new Event();
        $order = [];
        $event->register('t', static function (array &$a) use (&$order): void { $order[] = 'tarde'; }, 5);

        $args = [];
        $event->trigger('t', $args);

        $event->register('t', static function (array &$a) use (&$order): void { $order[] = 'cedo'; }, -5);
        $order = [];
        $event->trigger('t', $args);

        self::assertSame(['cedo', 'tarde'], $order);
    }

    public function testHasTellsIfSomeoneListens(): void {
        $event = new Event();

        self::assertFalse($event->has('t'));

        $event->register('t', static function (array &$args): void {});

        self::assertTrue($event->has('t'));
        self::assertFalse($event->has('outro'));
    }

    public function testArgumentsAreSharedByReferenceAmongCoreListeners(): void {
        $event = new Event();
        $event->register('t', static function (array &$args): void { $args[0] .= 'b'; });
        $event->register('t', static function (array &$args): void { $args[0] .= 'c'; });

        $args = ['a'];
        $event->trigger('t', $args);

        self::assertSame(['abc'], $args);
    }

    public function testTriggerWithoutListenersDoesNothing(): void {
        $args = ['a'];
        (new Event())->trigger('nada', $args);

        self::assertSame(['a'], $args);
    }

    public function testCoreEventDoesNotSwallowErrors(): void {
        // O isolamento de erro e das extensoes (Manager); um ouvinte do nucleo que falha continua visivel.
        $event = new Event();
        $event->register('t', static function (array &$args): void {
            throw new \LogicException('do nucleo');
        });

        $this->expectException(\LogicException::class);
        $args = [];
        $event->trigger('t', $args);
    }

    // ── politica de conteudo ────────────────────────────────────────────────

    #[DataProvider('coverage')]
    public function testCovers(string $source, string $url, bool $expected): void {
        self::assertSame($expected, Csp::covers($source, $url));
    }

    /** @return array<string, array{0: string, 1: string, 2: bool}> */
    public static function coverage(): array {
        return [
            'mesmo site'                    => ['https://cdn.example.com', 'https://cdn.example.com/a.js', true],
            'mesmo site, raiz'              => ['https://cdn.example.com', 'https://cdn.example.com', true],
            'com consulta'                  => ['https://cdn.example.com', 'https://cdn.example.com/a.js?x=1#y', true],
            'maiusculas no host'            => ['https://CDN.example.com', 'https://cdn.EXAMPLE.com/a.js', true],
            'porta 443 explicita'           => ['https://cdn.example.com', 'https://cdn.example.com:443/a.js', true],
            'origem com barra'              => ['https://cdn.example.com/', 'https://cdn.example.com/a/b.js', true],
            'curinga cobre subdominio'      => ['https://*.example.com', 'https://a.example.com/x.js', true],
            'curinga cobre dois niveis'     => ['https://*.example.com', 'https://a.b.example.com/x.js', true],
            'curinga nao cobre a raiz'      => ['https://*.example.com', 'https://example.com/x.js', false],
            'curinga nao cobre parecido'    => ['https://*.example.com', 'https://evilexample.com/x.js', false],
            'outro site'                    => ['https://cdn.example.com', 'https://evil.example.org/a.js', false],
            'sufixo'                        => ['https://cdn.example.com', 'https://cdn.example.com.evil.org/a.js', false],
            'subdominio sem curinga'        => ['https://example.com', 'https://a.example.com/a.js', false],
            'http'                          => ['https://cdn.example.com', 'http://cdn.example.com/a.js', false],
            'origem http'                   => ['http://cdn.example.com', 'http://cdn.example.com/a.js', false],
            'outra porta'                   => ['https://cdn.example.com', 'https://cdn.example.com:8443/a.js', false],
            'porta declarada'               => ['https://cdn.example.com:8443', 'https://cdn.example.com:8443/a.js', true],
            'porta declarada, url sem'      => ['https://cdn.example.com:8443', 'https://cdn.example.com/a.js', false],
            'usuario na url'                => ['https://cdn.example.com', 'https://cdn.example.com@evil.org/a.js', false],
            'usuario e senha'               => ['https://cdn.example.com', 'https://u:p@cdn.example.com/a.js', false],
            'pasta, dentro'                 => ['https://cdn.example.com/lib/', 'https://cdn.example.com/lib/a.js', true],
            'pasta, fora'                   => ['https://cdn.example.com/lib/', 'https://cdn.example.com/outra/a.js', false],
            'pasta, nome parecido'          => ['https://cdn.example.com/lib/', 'https://cdn.example.com/libx/a.js', false],
            'pasta, sobe um nivel'          => ['https://cdn.example.com/lib/', 'https://cdn.example.com/lib/../x.js', false],
            'pasta, sobe codificado'        => ['https://cdn.example.com/lib/', 'https://cdn.example.com/lib/%2E%2E/x.js', false],
            'arquivo exato'                 => ['https://cdn.example.com/lib/a.js', 'https://cdn.example.com/lib/a.js', true],
            'arquivo exato, outro'          => ['https://cdn.example.com/lib/a.js', 'https://cdn.example.com/lib/a.js.map', false],
            'arquivo exato, url sem caminho' => ['https://cdn.example.com/lib/a.js', 'https://cdn.example.com', false],
            'sem esquema'                   => ['https://cdn.example.com', '//cdn.example.com/a.js', false],
            'lixo'                          => ['https://cdn.example.com', 'nao e url', false],
            'vazio'                         => ['', '', false],
        ];
    }

    public function testAddedSourcesEnterThePublicPolicy(): void {
        $csp   = new Csp('catalog');
        $added = $csp->addSources(['script-src' => ['https://cdn.example.com'], 'img-src' => ['https://img.example.com']]);

        self::assertSame(['script-src' => ['https://cdn.example.com'], 'img-src' => ['https://img.example.com']], $added);
        self::assertMatchesRegularExpression("~script-src 'self' 'nonce-[^']+' https://cdn\\.example\\.com(;|$)~", $csp->policy());
        self::assertMatchesRegularExpression('~img-src [^;]*https://img\.example\.com~', $csp->policy());
    }

    public function testAddedSourcesGoThroughTheSameFilterAsTheConfigFile(): void {
        $csp    = new Csp('catalog');
        $before = $csp->policy();

        $added = $csp->addSources([
            'script-src'  => ["'unsafe-inline'", "'unsafe-eval'", '*', 'https:', 'data:', 'http://a.example.com', "'strict-dynamic'", 'https://ok.example.com; default-src *'],
            'default-src' => ['https://a.example.com'],
            'object-src'  => ['https://a.example.com'],
            'base-uri'    => ['https://a.example.com'],
        ]);

        self::assertSame([], $added);
        self::assertSame($before, $csp->policy());
        self::assertSame([], $csp->addSources('script-src https://a.example.com'));
        self::assertSame([], $csp->addSources(null));
    }

    public function testAdminPolicyNeverTakesOutsideSources(): void {
        $csp    = new Csp('admin');
        $before = $csp->policy();

        self::assertSame([], $csp->addSources(['script-src' => ['https://cdn.example.com']]));
        self::assertSame($before, $csp->policy());
    }

    public function testSameSourceIsNotAddedTwice(): void {
        $csp = new Csp('catalog', Csp::MODE_ENFORCE, ['script-src' => ['https://cdn.example.com']]);

        self::assertSame([], $csp->addSources(['script-src' => ['https://cdn.example.com']]), 'ja estava no config.php');
        self::assertSame(['script-src' => ['https://b.example.com']], $csp->addSources(['script-src' => ['https://b.example.com', 'https://b.example.com']]));
        self::assertSame([], $csp->addSources(['script-src' => ['https://b.example.com']]));
        self::assertSame(1, substr_count($csp->policy(), 'https://cdn.example.com'));
        self::assertSame(1, substr_count($csp->policy(), 'https://b.example.com'));
    }

    public function testAddingSourcesKeepsTheNonceOfTheResponse(): void {
        $csp   = new Csp('catalog');
        $nonce = $csp->nonce();

        $csp->addSources(['script-src' => ['https://cdn.example.com']]);

        self::assertSame($nonce, $csp->nonce());
        self::assertStringContainsString("'nonce-" . $nonce . "'", $csp->header());
    }

    public function testAddedSourcesAlsoApplyInReportOnlyMode(): void {
        $csp = new Csp('catalog', Csp::MODE_REPORT);
        $csp->addSources(['script-src' => ['https://cdn.example.com']]);

        self::assertStringStartsWith('Content-Security-Policy-Report-Only: ', $csp->header());
        self::assertStringContainsString('https://cdn.example.com', $csp->header());
    }
}
