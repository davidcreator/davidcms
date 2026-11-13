<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

require_once __DIR__ . '/ExtensionTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;
use Reamur\Catalog\Controller\Common\Consent as Screen;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Consent;
use Reamur\System\Library\Extension\PageOutput;
use Reamur\System\Library\Request;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Session;

/**
 * EX-02 (ADR 0096): o caminho da escolha de cookies pelo site publico, com os
 * controladores e o template de verdade: a pre-acao que le o cookie antes das
 * extensoes, a tela que grava a escolha, e as ligacoes nos arquivos que vao
 * para o servidor.
 */
final class ConsentWiringTest extends ExtensionTestCase {
    private const TRACKER = [
        'name'    => ['en-us' => 'Meter', 'pt-br' => 'Medidor'],
        'consent' => ['analytics'],
        'cookies' => ['_tk', '_tk_*'],
        'events'  => [['trigger' => 'page/output', 'handler' => 'onPage']],
        'csp'     => ['script-src' => ['https://cdn.tracker.example']],
    ];

    private const ADS = [
        'name'    => 'Anuncios',
        'consent' => ['marketing'],
        'cookies' => ['_ad'],
        'events'  => [['trigger' => 'page/output', 'handler' => 'onPage']],
    ];

    private const ON_PAGE = 'public function onPage($p): void {}';

    private static function repo(): string {
        return dirname(__DIR__, 3) . '/';
    }

    private static function read(string $path): string {
        $text = file_get_contents(self::repo() . $path);
        self::assertIsString($text, $path);

        return $text;
    }

    /**
     * Um pedido ao site publico: pre-acao + (se pedido) a tela de preferencias.
     *
     * @param array{cookies?: array<string|int, mixed>, method?: string, post?: array<string, mixed>, csrf?: bool, language?: string,
     *              site?: string, days?: mixed, extensions?: list<array<string, mixed>>, session?: Session} $o
     * @return object{registry: Registry, response: Response, session: Session, texts: object, codes: list<string>, screen: Screen}
     */
    private function request(array $o = []): object {
        $folder = $this->folder();
        $codes  = [];

        [$registry, $db] = $this->registry();

        foreach ($o['extensions'] ?? [self::TRACKER] as $manifest) {
            $code    = self::code();
            $codes[] = $code;
            $this->install($folder, $code, self::manifest($code, $manifest), self::ON_PAGE);
            $db->put($code . '.enabled', '1');
        }

        $language = $o['language'] ?? 'en-us';
        $config   = $registry->get('config');
        $config->set('language_directory', $language);
        $config->set('site_url', $o['site'] ?? 'http://localhost/');
        $config->set('session_name', 'RMSSESSION');

        if (array_key_exists('days', $o)) {
            $config->set('config_consent_days', $o['days']);
        }

        if (isset($o['session'])) {
            $session = $o['session'];
        } else {
            /** @var Session $session */
            $session       = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
            $session->data = [];
        }

        $request         = new Request();
        $request->server = ['REQUEST_METHOD' => $o['method'] ?? 'GET'];
        $request->cookie = $o['cookies'] ?? [];
        $request->post   = ($o['post'] ?? []) + (($o['csrf'] ?? true) ? ['csrf_token' => Csrf::token($session)] : []);

        $texts = new class {
            /** @var array<string, string> */
            public array $data = [];

            /** @var array<string, mixed> */
            public array $view = [];

            public string $html = '';

            public function get(string $key): string {
                return $this->data[$key] ?? $key;
            }
        };

        $twig = new \Twig\Environment(
            new \Twig\Loader\FilesystemLoader([self::repo() . 'dev/catalog/view/template/']),
            ['autoescape' => 'html', 'cache' => false]
        );

        $registry->set('request', $request);
        $registry->set('session', $session);
        $registry->set('language', $texts);
        $registry->set('document', new class {
            public function setTitle(string $title): void {}
        });
        $registry->set('url', new class {
            public function link(string $route): string {
                return 'http://localhost/index.php?route=' . $route;
            }
        });
        $registry->set('load', new class($texts, $twig, $language) {
            public function __construct(private object $texts, private \Twig\Environment $twig, private string $language) {}

            public function language(string $route): void {
                $_ = [];
                require dirname(__DIR__, 3) . '/dev/catalog/language/' . $this->language . '/' . $route . '.php';
                $this->texts->data = $_ + $this->texts->data;
            }

            /** @param array<string, mixed> $data */
            public function view(string $route, array $data = []): string {
                $this->texts->view = $data;

                return $this->texts->html = $this->twig->render($route . '.twig', $data);
            }
        });

        $this->manager($registry, $folder);

        (new \Reamur\Catalog\Controller\Startup\Extension($registry))->index();

        return (object)[
            'registry' => $registry,
            'response' => $registry->get('response'),
            'session'  => $session,
            'texts'    => $texts,
            'codes'    => $codes,
            'screen'   => new Screen($registry),
        ];
    }

    /** @return list<string> */
    private static function cookiesSet(object $r): array {
        $out = [];

        foreach ($r->response->getHeaders() as $header) {
            if (stripos($header, 'Set-Cookie: ') === 0) {
                $out[] = substr($header, 12);
            }
        }

        return $out;
    }

    private static function location(object $r): string {
        foreach ($r->response->getHeaders() as $header) {
            if (stripos($header, 'Location: ') === 0) {
                return substr($header, 10);
            }
        }

        return '';
    }

    /** Valor gravado, com a hora trocada por um marcador. */
    private static function saved(object $r): string {
        foreach (self::cookiesSet($r) as $line) {
            if (str_starts_with($line, 'rms_consent=')) {
                return (string)preg_replace('/\.[0-9]{10}\./', '.T.', $line);
            }
        }

        return '';
    }

    private static function choice(string $pairs): string {
        return '1.' . time() . '.' . $pairs;
    }

    // ── pre-acao ────────────────────────────────────────────────────────────

    public function testPreActionReadsTheChoiceBeforeStartingExtensions(): void {
        $none = $this->request();

        self::assertInstanceOf(Consent::class, $none->registry->get('consent'));
        self::assertSame([], $none->registry->get('extensions')->running());
        self::assertSame(['analytics'], $none->registry->get('extensions')->consentCategories());

        $yes = $this->request(['cookies' => ['rms_consent' => self::choice('a1')]]);

        self::assertTrue($yes->registry->get('consent')->allows('analytics'));
        self::assertSame($yes->codes, $yes->registry->get('extensions')->running());

        $no = $this->request(['cookies' => ['rms_consent' => self::choice('a0')]]);

        self::assertSame([], $no->registry->get('extensions')->running());
        self::assertSame([], self::cookiesSet($no), 'nada a apagar: nenhum cookie de etiqueta veio no pedido');
    }

    public function testPreActionIgnoresAForgedOrExpiredChoice(): void {
        foreach (['all', '1.9999999999.a1', ['a1'], '1.' . (time() - 181 * 86400) . '.a1'] as $value) {
            $r = $this->request(['cookies' => ['rms_consent' => $value]]);

            self::assertSame([], $r->registry->get('extensions')->running(), var_export($value, true));
        }
    }

    public function testPreActionHonoursTheConfiguredValidity(): void {
        $old = '1.' . (time() - 40 * 86400) . '.a1';

        self::assertNotSame([], $this->request(['cookies' => ['rms_consent' => $old]])->registry->get('extensions')->running());
        self::assertSame([], $this->request(['cookies' => ['rms_consent' => $old], 'days' => '30'])->registry->get('extensions')->running());
        self::assertNotSame([], $this->request(['cookies' => ['rms_consent' => $old], 'days' => 'lixo'])->registry->get('extensions')->running(), 'prazo invalido vale o padrao');
    }

    public function testPreActionExpiresLeftoverCookiesOfARefusedExtensionOnEveryRequest(): void {
        $r = $this->request([
            'cookies' => ['rms_consent' => self::choice('a0'), 'RMSSESSION' => 'abc', '_tk' => '1', '_tk_9' => '2', 'tema' => 'x'],
            'site'    => 'https://www.example.com/',
        ]);

        self::assertSame([
            '_tk=; Max-Age=0; Path=/',
            '_tk=; Max-Age=0; Path=/; Domain=www.example.com',
            '_tk=; Max-Age=0; Path=/; Domain=example.com',
            '_tk_9=; Max-Age=0; Path=/',
            '_tk_9=; Max-Age=0; Path=/; Domain=www.example.com',
            '_tk_9=; Max-Age=0; Path=/; Domain=example.com',
        ], self::cookiesSet($r));
    }

    public function testAnExtensionCanNeverMakeTheSiteExpireTheSessionOrTheChoice(): void {
        // Manifesto descuidado (ou mal-intencionado): padroes que alcancariam os cookies do proprio site.
        $greedy  = ['cookies' => ['RMS*', 'rms_*', '_tk']] + self::TRACKER;
        $cookies = ['rms_consent' => self::choice('a0'), 'RMSSESSION' => 'abc', '_tk' => '1'];

        $visit = $this->request(['cookies' => $cookies, 'extensions' => [$greedy]]);
        self::assertSame(['_tk=; Max-Age=0; Path=/'], self::cookiesSet($visit));

        $save = $this->request(['method' => 'POST', 'post' => ['decision' => 'reject_all'], 'cookies' => ['rms_consent' => self::choice('a1')] + $cookies, 'extensions' => [$greedy]]);
        $save->screen->index();

        self::assertSame(
            ['rms_consent=1.T.a0; Max-Age=15552000; Path=/; SameSite=Lax; HttpOnly', '_tk=; Max-Age=0; Path=/'],
            array_map(static fn (string $l): string => (string)preg_replace('/\.[0-9]{10}\./', '.T.', $l), self::cookiesSet($save))
        );
    }

    public function testPreActionLeavesCookiesAloneWithoutARefusal(): void {
        $cookies = ['_tk' => '1', '_tk_9' => '2'];

        self::assertSame([], self::cookiesSet($this->request(['cookies' => $cookies])), 'sem resposta ainda');
        self::assertSame([], self::cookiesSet($this->request(['cookies' => $cookies + ['rms_consent' => self::choice('a1')]])), 'aceito');
        self::assertSame([], self::cookiesSet($this->request(['cookies' => $cookies + ['rms_consent' => self::choice('a0')], 'extensions' => [self::manifest('x')]])), 'nenhuma extensao depende de aceitacao');
    }

    public function testPreActionWithoutTheRequestServiceStillWorks(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::TRACKER), self::ON_PAGE);

        [$registry, $db] = $this->registry();
        $db->put($code . '.enabled', '1');
        $manager = $this->manager($registry, $folder);

        (new \Reamur\Catalog\Controller\Startup\Extension($registry))->index();

        self::assertSame([], $manager->running());
        self::assertInstanceOf(Consent::class, $registry->get('consent'));
    }

    // ── Response::addCookie ─────────────────────────────────────────────────

    public function testAddCookieKeepsEveryLine(): void {
        $response = new Response();
        $response->addCookie('a=1; Path=/');
        $response->addCookie('b=2; Path=/');
        $response->addCookie('a=; Max-Age=0; Path=/');
        $response->redirect('/destino', 303);

        self::assertSame(
            ['Set-Cookie: a=1; Path=/', 'Set-Cookie: b=2; Path=/', 'Set-Cookie: a=; Max-Age=0; Path=/', 'Location: /destino'],
            array_values(array_filter($response->getHeaders(), static fn (string $h): bool => preg_match('/^(Set-Cookie|Location):/', $h) === 1))
        );
    }

    public function testAddCookieRefusesWhatIsNotACookieLine(): void {
        $response = new Response();
        $before   = $response->getHeaders();

        foreach (['', '   ', 'sem-igual', '=valor', 'nome com espaco=1', "\r\n", 'X-Outro: 1', 'a;b=1'] as $line) {
            $response->addCookie($line);
        }

        self::assertSame($before, $response->getHeaders());

        $response->addCookie("a=1; Path=/\r\nX-Injetado: 1");

        self::assertSame(['Set-Cookie: a=1; Path=/X-Injetado: 1'], array_values(array_diff($response->getHeaders(), $before)), 'quebra de linha some: nunca vira outro cabecalho');
    }

    // ── avisos de formulario enviado ────────────────────────────────────────

    private function signals(?Session $session): object {
        $registry = new Registry();

        if ($session !== null) {
            $registry->set('session', $session);
        }

        return new class($registry) extends \Reamur\System\Engine\Controller {
            public function put(string $signal): void {
                $this->pageSignal($signal);
            }

            /** @return list<string> */
            public function take(): array {
                return $this->takePageSignals();
            }
        };
    }

    public function testPageSignalWaitsInTheSessionAndLeavesOnce(): void {
        /** @var Session $session */
        $session       = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $session->data = ['flash' => ['text' => 'x']];

        $c = $this->signals($session);
        $c->put(PageOutput::SIGNAL_QUOTE);
        $c->put(PageOutput::SIGNAL_CONTACT);
        $c->put(PageOutput::SIGNAL_CONTACT);

        self::assertSame(['quote_sent', 'contact_sent'], $session->data['page_signals']);
        self::assertSame(['contact_sent', 'quote_sent'], $c->take(), 'na ordem fixa de PageOutput::SIGNALS');
        self::assertArrayNotHasKey('page_signals', $session->data);
        self::assertSame([], $c->take(), 'a segunda pagina nao recebe de novo');
        self::assertSame(['flash' => ['text' => 'x']], $session->data, 'o resto da sessao fica como estava');
    }

    public function testPageSignalAcceptsOnlyKnownNames(): void {
        /** @var Session $session */
        $session       = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $session->data = [];

        $c = $this->signals($session);
        $c->put('visitante@example.com');
        $c->put('');
        $c->put('CONTACT_SENT');

        self::assertSame([], $session->data);

        // Sessao adulterada ou de versao antiga: so nomes conhecidos saem.
        $session->data['page_signals'] = ['contact_sent', 'visitante@example.com', ['x'], 7];
        self::assertSame(['contact_sent'], $c->take());

        $session->data['page_signals'] = 'contact_sent';
        self::assertSame([], $c->take());
        self::assertArrayNotHasKey('page_signals', $session->data);
    }

    public function testPageSignalWithoutSessionDoesNothing(): void {
        $c = $this->signals(null);
        $c->put(PageOutput::SIGNAL_CONTACT);

        self::assertSame([], $c->take());
    }

    // ── tela de preferencias ────────────────────────────────────────────────

    public function testGetShowsEachCategoryInUseUncheckedByDefault(): void {
        $r = $this->request(['language' => 'pt-br', 'extensions' => [self::TRACKER, self::ADS]]);
        $r->screen->index();

        $html = $r->texts->html;

        self::assertSame([], self::cookiesSet($r), 'abrir a tela nao grava nada');
        self::assertSame(1, preg_match('/<input type="checkbox" id="rms-consent-analytics" name="category\[analytics\]" value="1" aria-describedby=/', $html), 'medicao desmarcada');
        self::assertSame(1, preg_match('/<input type="checkbox" id="rms-consent-marketing" name="category\[marketing\]" value="1" aria-describedby=/', $html), 'publicidade desmarcada');
        self::assertStringContainsString('Serviços: Medidor', $html);
        self::assertStringContainsString('Cookies: _tk, _tk_*', $html);
        self::assertStringContainsString('Serviços: Anuncios', $html);
        self::assertStringContainsString('Você ainda não fez uma escolha.', $html);
        self::assertStringContainsString('Sempre ativos', $html);
        self::assertSame(3, substr_count($html, 'name="decision"'));
        self::assertStringContainsString('name="csrf_token" value="' . Csrf::token($r->session) . '"', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString(' style="', $html);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $html);
    }

    public function testGetShowsTheStoredChoice(): void {
        $r = $this->request(['extensions' => [self::TRACKER, self::ADS], 'cookies' => ['rms_consent' => self::choice('a1m0')]]);
        $r->screen->index();

        self::assertSame(1, preg_match('/name="category\[analytics\]" value="1" checked/', $r->texts->html));
        self::assertSame(0, preg_match('/name="category\[marketing\]" value="1" checked/', $r->texts->html));
        self::assertStringContainsString('Choice recorded on ' . date('F j, Y') . '.', $r->texts->html);
    }

    public function testGetWithNothingToChoose(): void {
        $r = $this->request(['extensions' => [self::manifest('x')]]);
        $r->screen->index();

        self::assertStringContainsString('This site uses only necessary cookies.', $r->texts->html);
        self::assertStringNotContainsString('name="decision"', $r->texts->html);
        self::assertStringNotContainsString('type="checkbox"', $r->texts->html);
    }

    public function testExtensionNameIsEscapedOnThePublicPage(): void {
        $evil = ['name' => 'Medidor <img src=x onerror=alert(1)>'] + self::TRACKER;
        $r    = $this->request(['extensions' => [$evil]]);
        $r->screen->index();

        self::assertStringNotContainsString('<img', $r->texts->html);
        self::assertStringContainsString('Medidor &lt;img src=x onerror=alert(1)&gt;', $r->texts->html);
    }

    /** @return array<string, array{string, array<string, mixed>, string, string}> decisao, extras do POST, cookie ja gravado, esperado */
    public static function decisions(): array {
        return [
            'aviso: aceitar, primeira vez'              => ['accept', [], '', 'a1m1'],
            'aviso: recusar, primeira vez'              => ['reject', [], '', 'a0m0'],
            'aviso: aceitar so o que falta'             => ['accept', [], 'a0', 'a0m1'],
            'aviso: recusar so o que falta'             => ['reject', [], 'a1', 'a1m0'],
            'aviso: aceitar com tudo ja respondido'     => ['accept', [], 'a0m0', 'a0m0'],
            'aviso: recusar com tudo ja respondido'     => ['reject', [], 'a1m1', 'a1m1'],
            'tela: aceitar todos'                       => ['accept_all', [], 'a0m0', 'a1m1'],
            'tela: recusar todos'                       => ['reject_all', [], 'a1m1', 'a0m0'],
            'tela: so medicao'                          => ['custom', ['category' => ['analytics' => '1']], '', 'a1m0'],
            'tela: so publicidade'                      => ['custom', ['category' => ['marketing' => '1']], 'a1m0', 'a0m1'],
            'tela: nada marcado'                        => ['custom', [], 'a1m1', 'a0m0'],
            'tela: valor que nao e 1'                   => ['custom', ['category' => ['analytics' => 'on', 'marketing' => 'true']], '', 'a0m0'],
            'tela: categoria como lista'                => ['custom', ['category' => ['analytics' => ['1']]], '', 'a0m0'],
            'tela: category nao e lista'                => ['custom', ['category' => '1'], '', 'a0m0'],
            'decisao desconhecida vale recusa'          => ['tudo', [], 'a1m1', 'a0m0'],
            'decisao vazia vale recusa'                 => ['', [], '', 'a0m0'],
            'decisao em maiusculas vale recusa'         => ['ACCEPT', [], '', 'a0m0'],
        ];
    }

    /** @param array<string, mixed> $extra */
    #[DataProvider('decisions')]
    public function testPostSavesTheDecision(string $decision, array $extra, string $stored, string $expected): void {
        $r = $this->request([
            'method'     => 'POST',
            'post'       => ['decision' => $decision] + $extra,
            'cookies'    => $stored === '' ? [] : ['rms_consent' => self::choice($stored)],
            'extensions' => [self::TRACKER, self::ADS],
        ]);
        $r->screen->index();

        self::assertSame('rms_consent=1.T.' . $expected . '; Max-Age=15552000; Path=/; SameSite=Lax; HttpOnly', self::saved($r));
        self::assertSame('http://localhost/index.php?route=common/consent', self::location($r));
        self::assertSame('', $r->texts->html, 'gravou e redirecionou: nenhuma pagina e montada');
        self::assertSame('Cookie preferences saved.', $r->session->data['flash']['text']);
    }

    public function testOnlyCategoriesInUseAreAnswered(): void {
        // So medicao em uso: publicidade nao e perguntada, e o que vier no POST sobre ela e ignorado.
        $r = $this->request(['method' => 'POST', 'post' => ['decision' => 'custom', 'category' => ['analytics' => '1', 'marketing' => '1', 'necessary' => '1']]]);
        $r->screen->index();

        self::assertStringStartsWith('rms_consent=1.T.a1;', self::saved($r));
    }

    public function testAnswerAboutACategoryNoLongerInUseIsKept(): void {
        $r = $this->request(['method' => 'POST', 'post' => ['decision' => 'reject_all'], 'cookies' => ['rms_consent' => self::choice('a1m1')]]);
        $r->screen->index();

        self::assertStringStartsWith('rms_consent=1.T.a0m1;', self::saved($r), 'publicidade nao esta em uso: a resposta antiga continua valendo');
    }

    public function testPostWithoutCsrfSavesNothing(): void {
        foreach ([['csrf' => false], ['csrf' => false, 'post' => ['decision' => 'accept_all', 'csrf_token' => 'errado']], ['csrf' => false, 'post' => ['decision' => 'accept_all', 'csrf_token' => ['x']]]] as $o) {
            $r = $this->request(['method' => 'POST', 'post' => ['decision' => 'accept_all']] + $o);
            $r->screen->index();

            self::assertSame([], self::cookiesSet($r));
            self::assertSame('', self::location($r));
            self::assertStringContainsString('The page was open for too long. Please try again.', $r->texts->html);
            self::assertStringContainsString('role="alert"', $r->texts->html);
        }
    }

    public function testGetNeverSavesEvenWithADecisionInTheAddress(): void {
        $r = $this->request(['method' => 'GET', 'post' => ['decision' => 'accept_all']]);
        $r->registry->get('request')->get = ['decision' => 'accept_all'];
        $r->screen->index();

        self::assertSame([], self::cookiesSet($r), 'um link de fora nao consegue aceitar pelo visitante');
    }

    public function testSecureFlagAndValidityFollowTheSiteConfiguration(): void {
        $r = $this->request(['method' => 'POST', 'post' => ['decision' => 'accept_all'], 'site' => 'https://www.example.com/', 'days' => '90']);
        $r->screen->index();

        self::assertSame('rms_consent=1.T.a1; Max-Age=7776000; Path=/; SameSite=Lax; HttpOnly; Secure', self::saved($r));
    }

    public function testWithdrawalExpiresTheCookiesOfTheRefusedExtensions(): void {
        $r = $this->request([
            'method'     => 'POST',
            'post'       => ['decision' => 'custom', 'category' => ['marketing' => '1']],
            'cookies'    => ['rms_consent' => self::choice('a1m1'), 'RMSSESSION' => 's', '_tk' => '1', '_tk_x' => '2', '_ad' => '3', 'tema' => 'x'],
            'extensions' => [self::TRACKER, self::ADS],
        ]);
        $r->screen->index();

        self::assertSame(
            ['rms_consent=1.T.a0m1; Max-Age=15552000; Path=/; SameSite=Lax; HttpOnly', '_tk=; Max-Age=0; Path=/', '_tk_x=; Max-Age=0; Path=/'],
            array_map(static fn (string $l): string => (string)preg_replace('/\.[0-9]{10}\./', '.T.', $l), self::cookiesSet($r)),
            'so os cookies da extensao recusada; o da sessao, o da escolha, o da outra extensao e os do site ficam'
        );
    }

    public function testAcceptingExpiresNothing(): void {
        $r = $this->request(['method' => 'POST', 'post' => ['decision' => 'accept_all'], 'cookies' => ['_tk' => '1', '_tk_x' => '2']]);
        $r->screen->index();

        self::assertCount(1, self::cookiesSet($r));
    }

    /** @return array<string, array{mixed, string}> */
    public static function returns(): array {
        $home = 'http://localhost/index.php?route=common/consent';

        return [
            'caminho do site'            => ['/catalog/blog', '/catalog/blog'],
            'com consulta'               => ['/catalog/index.php?route=blog/blog&page=2', '/catalog/index.php?route=blog/blog&page=2'],
            'raiz'                       => ['/', '/'],
            'vazio'                      => ['', $home],
            'outro site'                 => ['https://evil.example/', $home],
            'outro site sem esquema'     => ['//evil.example/', $home],
            'barra invertida'            => ['/\\evil.example/', $home],
            'barra invertida no meio'    => ['/catalog\\..\\x', $home],
            'sem barra inicial'          => ['catalog/blog', $home],
            'javascript'                 => ['javascript:alert(1)', $home],
            'com quebra de linha'        => ["/catalog/\r\nSet-Cookie: a=1", $home],
            'com espaco'                 => ['/catalog/ x', $home],
            'com tab'                    => ["/catalog/\tx", $home],
            'com nulo'                   => ["/catalog/\0x", $home],
            'longo demais'               => ['/' . str_repeat('a', 2000), $home],
            'lista'                      => [['/catalog/'], $home],
        ];
    }

    #[DataProvider('returns')]
    public function testAfterSavingTheVisitorGoesBackOnlyToAPathOfThisSite(mixed $return, string $expected): void {
        $r = $this->request(['method' => 'POST', 'post' => ['decision' => 'accept', 'return' => $return]]);
        $r->screen->index();

        self::assertSame($expected, self::location($r));
        self::assertNotSame('', self::saved($r), 'a escolha e gravada mesmo quando o destino pedido e recusado');
    }

    public function testTheWrittenConfirmationIsKeptOnlyForThePreferencesPage(): void {
        // Vindo do aviso, o visitante volta para a pagina em que estava; ela nao mostra mensagens.
        // Se a confirmacao ficasse guardada, apareceria depois dentro do formulario de contato.
        foreach (['accept', 'reject'] as $decision) {
            $r = $this->request(['method' => 'POST', 'post' => ['decision' => $decision, 'return' => '/catalog/blog?page=2']]);
            $r->screen->index();

            self::assertSame('/catalog/blog?page=2', self::location($r));
            self::assertNotSame('', self::saved($r));
            self::assertArrayNotHasKey('flash', $r->session->data, $decision);
        }

        // Pela tela de preferencias (sem destino, ou destino recusado) a volta e para ela mesma, que mostra.
        foreach ([[], ['return' => 'https://evil.example/']] as $extra) {
            $r = $this->request(['method' => 'POST', 'post' => ['decision' => 'custom'] + $extra]);
            $r->screen->index();

            self::assertSame('http://localhost/index.php?route=common/consent', self::location($r));
            self::assertSame(['type' => 'success', 'text' => 'Cookie preferences saved.'], $r->session->data['flash']);
        }
    }

    // ── ligacoes nos arquivos que vao para o servidor ───────────────────────

    public function testHeaderShowsTheNoticeAsAPlainFormWithTwoEqualButtons(): void {
        $twig = self::read('dev/catalog/view/template/common/header.twig');

        self::assertSame(1, preg_match('/\{% if consent_banner\|default\(\[\]\) %\}(.*?)\{% endif %\}\n/s', $twig, $m));
        $banner = $m[1];

        self::assertStringContainsString('<form class="rms-consent__actions" method="post" action="{{ consent_banner.action }}">', $banner);
        self::assertStringContainsString('<input type="hidden" name="csrf_token" value="{{ consent_banner.csrf_token }}">', $banner);
        self::assertStringContainsString('<button class="rms-consent__button" type="submit" name="decision" value="reject">{{ consent_banner.reject }}</button>', $banner);
        self::assertStringContainsString('<button class="rms-consent__button" type="submit" name="decision" value="accept">{{ consent_banner.accept }}</button>', $banner);
        self::assertSame(2, substr_count($banner, '<button '), 'dois botoes, com a mesma classe: nenhum tem mais destaque');
        self::assertLessThan(strpos($banner, 'value="accept"'), strpos($banner, 'value="reject"'), 'recusar vem primeiro na ordem do teclado');
        self::assertStringNotContainsString('<script', $banner);
        self::assertStringNotContainsString('|raw', $banner);
        self::assertStringNotContainsString('style=', $banner);
        self::assertStringNotContainsString('checked', $banner, 'nada vem marcado de antemao');
        self::assertStringNotContainsString('csp_nonce', $banner);

        // Antes do cabecalho do site (que o tema redefine), para ser o primeiro item do teclado.
        self::assertLessThan(strpos($twig, "theme_partial('partial/site_header'"), strpos($twig, 'data-rms-consent'));
        self::assertGreaterThan(strpos($twig, '<body '), strpos($twig, 'data-rms-consent'));
    }

    public function testNoThemeTemplateCanCarryOrHideTheNotice(): void {
        $found = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::repo() . 'dev/catalog/view/template', \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'twig' && str_contains((string)file_get_contents($file->getPathname()), 'consent_banner')) {
                $found[] = str_replace(self::repo() . 'dev/catalog/view/template/', '', $file->getPathname());
            }
        }

        self::assertSame(['common/header.twig'], $found);
    }

    public function testHeaderControllerBuildsTheNoticeAndHandsTheChoiceToExtensions(): void {
        $php = self::read('dev/catalog/controller/common/header.php');

        self::assertStringContainsString("\$data['consent_banner'] = \$this->consentBanner();", $php);
        self::assertStringContainsString('$pending = $consent->pending($manager->consentCategories());', $php);
        self::assertStringContainsString("if (\$pending === [] || explode('|', (string)\$this->config->get('_route', ''), 2)[0] === 'common/consent') {", $php);
        self::assertStringContainsString('$signals = $this->takePageSignals();', $php);
        self::assertStringContainsString('$consent instanceof \\Reamur\\System\\Library\\Consent ? $consent : null,', $php);
        self::assertLessThan(strpos($php, '$events->has('), strpos($php, '$signals = $this->takePageSignals();'), 'os avisos saem da sessao mesmo sem extensao ouvindo');
        self::assertStringContainsString("'/view/css/consent.css'", $php);
    }

    public function testFormsSignalOnlyARealSubmission(): void {
        $contact = self::read('dev/catalog/controller/common/contact.php');
        $quote   = self::read('dev/catalog/controller/checkout/quote.php');

        self::assertSame(1, substr_count($contact, '$this->pageSignal('));
        self::assertSame(1, substr_count($quote, '$this->pageSignal('));
        self::assertStringContainsString('$this->pageSignal(\\Reamur\\System\\Library\\Extension\\PageOutput::SIGNAL_CONTACT);', $contact);
        self::assertStringContainsString('$this->pageSignal(\\Reamur\\System\\Library\\Extension\\PageOutput::SIGNAL_QUOTE);', $quote);

        // O ramo do robo (sucesso de mentira) termina antes do aviso; a mensagem gravada vem antes dele.
        foreach ([$contact, $quote] as $php) {
            $bot = strpos($php, '$this->isBot()');
            self::assertIsInt($bot);
            $end = strpos($php, 'return;', $bot);
            self::assertStringNotContainsString('pageSignal', substr($php, $bot, $end - $bot));
            self::assertGreaterThan($end, strpos($php, '$this->pageSignal('));
        }

        self::assertLessThan(strpos($contact, '$this->pageSignal('), strpos($contact, 'addContactMessage($contact)'));
    }

    public function testFooterLinksToThePreferencesWhileSomethingDependsOnConsent(): void {
        $php = self::read('dev/catalog/controller/common/footer.php');

        self::assertStringContainsString('$extensions->consentCategories() !== []', $php);
        self::assertStringContainsString("'href' => \$this->url->link('common/consent'),", $php);
        self::assertLessThan(strpos($php, "\$data['legal'] = \$legal;"), strpos($php, "'text_consent_preferences'"));
    }

    public function testTextsExistInTheThreeLanguages(): void {
        $keys = [];

        foreach (['en-us', 'pt-br', 'es-eu'] as $language) {
            foreach (['common/consent', 'common/header', 'common/footer'] as $file) {
                $_ = [];
                require self::repo() . 'dev/catalog/language/' . $language . '/' . $file . '.php';

                foreach ($_ as $key => $text) {
                    self::assertNotSame('', trim((string)$text), $language . ' ' . $file . ' ' . $key);
                }

                $keys[$file][$language] = array_keys($_);
                sort($keys[$file][$language]);
            }

            foreach (['fieldset_privacy', 'entry_config_consent_days', 'help_config_consent_days', 'error_config_consent_days'] as $key) {
                $_ = [];
                require self::repo() . 'dev/admin/language/' . $language . '/setting/setting.php';
                self::assertNotSame('', $_[$key] ?? '', $language . ' ' . $key);
            }

            foreach (['text_consent', 'text_consent_analytics', 'text_consent_marketing', 'text_cookies'] as $key) {
                $_ = [];
                require self::repo() . 'dev/admin/language/' . $language . '/extension/extension.php';
                self::assertNotSame('', $_[$key] ?? '', $language . ' ' . $key);
            }
        }

        foreach ($keys as $file => $byLanguage) {
            self::assertSame($byLanguage['en-us'], $byLanguage['pt-br'], $file);
            self::assertSame($byLanguage['en-us'], $byLanguage['es-eu'], $file);
        }

        foreach (['text_consent_title', 'text_consent_banner', 'text_consent_and', 'text_consent_purpose_analytics', 'text_consent_purpose_marketing',
            'text_consent_policy', 'button_consent_accept', 'button_consent_reject', 'button_consent_customize'] as $key) {
            self::assertContains($key, $keys['common/header']['pt-br']);
        }

        self::assertContains('text_consent_preferences', $keys['common/footer']['pt-br']);
    }

    public function testNoticeTextsUseNamesThatCannotReplaceThePageTexts(): void {
        // common/header e common/footer sao carregados em toda pagina, sobre os textos dela.
        $before = [];

        foreach (['common/header', 'common/footer'] as $file) {
            $_ = [];
            require self::repo() . 'dev/catalog/language/pt-br/' . $file . '.php';

            foreach (array_keys($_) as $key) {
                if (str_contains((string)$key, 'consent')) {
                    $before[] = $key;
                    self::assertMatchesRegularExpression('/^(text|button)_consent_[a-z_]+$/', (string)$key);
                }
            }
        }

        self::assertCount(10, $before);
    }

    public function testStyleSheetIsSelfContained(): void {
        $css = self::read('dev/catalog/view/css/consent.css');

        self::assertStringNotContainsString('url(', $css);
        self::assertStringNotContainsString('@import', $css);
        self::assertStringNotContainsString('http', $css);

        // Os dois botoes do aviso usam uma classe so; nao ha regra que destaque um deles.
        self::assertStringNotContainsString('[value=', $css);
        self::assertStringNotContainsString(':first-child', $css);
        self::assertStringNotContainsString(':last-child', $css);
        self::assertStringNotContainsString('nth-', $css);
        self::assertStringContainsString(':focus-visible', $css);
    }

    public function testValidityIsConfigurableWithinTheLimits(): void {
        self::assertStringContainsString("\$_['config_consent_days']  = 180;", self::read('dev/system/config/default.php'));
        self::assertStringContainsString("'config_consent_days',", self::read('dev/catalog/controller/startup/setting.php'));

        $admin = self::read('dev/admin/controller/setting/setting.php');

        self::assertStringContainsString("(int)\$post['config_consent_days'] < \\Reamur\\System\\Library\\Consent::MIN_DAYS", $admin);
        self::assertStringContainsString("(int)\$post['config_consent_days'] > \\Reamur\\System\\Library\\Consent::MAX_DAYS", $admin);
        self::assertStringContainsString('name="config_consent_days"', self::read('dev/admin/view/template/setting/setting.twig'));
    }

    /**
     * O que a tela de configuracoes do painel faz com o campo: limpa (sanitize) e depois valida.
     *
     * @return array{0: string, 1: bool} valor que iria para o banco e se houve recusa
     */
    private static function adminDays(mixed $typed, bool $sent = true): array {
        $registry = new Registry();
        $registry->set('language', new class {
            public function get(string $key): string {
                return $key;
            }
        });
        $registry->set('three_scene_config', new \Reamur\System\Library\Three\SceneConfig());

        $screen   = new \Reamur\Admin\Controller\Setting\Setting($registry);
        $sanitize = new \ReflectionMethod($screen, 'sanitize');
        $validate = new \ReflectionMethod($screen, 'validate');

        $clean  = $sanitize->invoke($screen, $sent ? ['config_consent_days' => $typed] : []);
        $errors = $validate->invoke($screen, $clean);

        return [$clean['config_consent_days'], isset($errors['config_consent_days'])];
    }

    public function testAdminAcceptsOnlyDaysWithinTheLimits(): void {
        foreach (['30', '31', '180', '389', '390', ' 90 ', "90\n"] as $typed) {
            self::assertSame([trim($typed), false], self::adminDays($typed), $typed);
        }

        foreach (['29', '391', '0', '1800', '18000000000', '-90', '90.5', '9e1', '0x5A', 'noventa', '90 dias', '9 0', '+90'] as $typed) {
            [$stored, $refused] = self::adminDays($typed);

            self::assertTrue($refused, var_export($typed, true) . ' deveria ser recusado (ficaria ' . var_export($stored, true) . ')');
        }
    }

    public function testAdminEmptyFieldMeansTheDefault(): void {
        self::assertSame(['180', false], self::adminDays(''));
        self::assertSame(['180', false], self::adminDays('   '));
        self::assertSame(['180', false], self::adminDays(['90']), 'lista no lugar do texto');
        self::assertSame(['180', false], self::adminDays(null, false), 'formulario antigo, sem o campo');
    }

    /** @return list<string> comandos enviados ao banco ao salvar estes valores pela camada de dados do painel */
    private static function adminSave(array $values): array {
        $db = new class {
            /** @var list<string> */
            public array $sql = [];

            public function escape(string $value): string {
                return addslashes($value);
            }

            public function query(string $sql): \stdClass {
                $this->sql[] = $sql;

                $res = new \stdClass();
                $res->rows = [];
                $res->row = [];
                $res->num_rows = 0;

                return $res;
            }
        };

        $registry = new Registry();
        $registry->set('db', $db);
        $registry->set('config', new \Reamur\System\Engine\Config());
        $registry->set('cache', new class {
            public function get(string $key): mixed {
                return null;
            }

            public function set(string $key, mixed $value, int $expire = 0): bool {
                return true;
            }

            public function delete(string $key): bool {
                return true;
            }
        });

        (new \Reamur\Admin\Model\Setting\Setting($registry))->editSettings($values);

        return $db->sql;
    }

    public function testTheValueAcceptedByTheScreenReallyReachesTheDatabase(): void {
        // Visto no ambiente de teste: a tela dizia "salvo" e a camada de dados descartava a chave.
        $sql = implode("\n", self::adminSave(['config_consent_days' => '45']));

        self::assertStringContainsString("SET `value` = '45'", $sql);
        self::assertStringContainsString("`key` = 'config_consent_days'", $sql);
        self::assertStringContainsString("SELECT 'config', 'config_consent_days', '45', '0'", $sql);
    }

    public function testEveryFieldOfTheSettingsScreenIsStored(): void {
        // Regra geral, para o mesmo descuido nao voltar com outro campo: tudo o que a tela
        // limpa e valida tem de estar na lista do que a camada de dados grava.
        $registry = new Registry();
        $registry->set('three_scene_config', new \Reamur\System\Library\Three\SceneConfig());

        $screen = new \Reamur\Admin\Controller\Setting\Setting($registry);
        $fields = array_keys((new \ReflectionMethod($screen, 'sanitize'))->invoke($screen, []));

        self::assertContains('config_consent_days', $fields);

        // Senha do SMTP fica de fora: vai cifrada e precisa do cofre (coberta em SecretStorageTest).
        $fields = array_values(array_diff($fields, \Reamur\System\Library\Security\SecretBox::SETTING_KEYS));
        $sql    = implode("\n", self::adminSave(array_fill_keys($fields, 'x')));

        foreach ($fields as $field) {
            self::assertStringContainsString("`key` = '" . $field . "'", $sql, $field . ' e aceito pela tela e nunca gravado');
        }
    }

    public function testWhatAdminAcceptsIsWhatTheSiteUses(): void {
        // As duas pontas tem de concordar: valor aceito no painel nao pode cair no padrao no site.
        foreach (['30', '90', '390', '030'] as $typed) {
            [$stored, $refused] = self::adminDays($typed);

            self::assertFalse($refused, $typed);
            self::assertSame((int)$typed, Consent::days($stored), $typed);
        }
    }

    public function testAdminListShowsWhatEachExtensionDependsOn(): void {
        $twig = self::read('dev/admin/view/template/extension/extension_list.twig');
        $php  = self::read('dev/admin/controller/extension/extension.php');

        self::assertStringContainsString('{% if ext.consent %}<div class="small mt-1" data-rms-extension-consent>', $twig);
        self::assertStringContainsString("{{ ext.consent|join(', ') }}", $twig);
        self::assertStringContainsString('{% for c in ext.cookies %}<code>{{ c }}</code>', $twig);
        self::assertStringContainsString("\$entry['consent'][] = \$this->language->get('text_consent_' . \$category);", $php);
        self::assertStringContainsString("\$entry['cookies'] = \$manifest->cookies();", $php);
    }
}
