<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

require_once __DIR__ . '/ExtensionTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Consent;
use Reamur\System\Library\Extension\Manager;
use Reamur\System\Library\Extension\Manifest;
use Reamur\System\Library\Extension\ManifestException;
use Reamur\System\Library\Extension\PageOutput;

/**
 * EX-02 (ADR 0096): quem barra a etiqueta e o CMS, nao a extensao. A extensao
 * so declara de qual aceitacao depende (`consent`) e quais cookies cria
 * (`cookies`); para o visitante que nao aceitou, ela conta como desligada:
 * o PHP dela nao e carregado e nenhuma origem dela entra na politica.
 */
final class ConsentGateTest extends ExtensionTestCase {
    private const NOW = 1763000000;

    private const TRACKER = [
        'consent' => ['analytics'],
        'cookies' => ['_tk', '_tk_*'],
        'events'  => [['trigger' => 'page/output', 'handler' => 'onPage']],
        'csp'     => ['script-src' => ['https://cdn.tracker.example'], 'connect-src' => ['https://collect.tracker.example']],
    ];

    private const ON_PAGE = 'public function onPage($p): void { self::$seen[] = "rodou"; $p->meta("x-tracker", "1"); }';

    /**
     * @param array<string, mixed> $over
     */
    private static function parse(array $over = [], string $code = 'demo'): Manifest {
        return Manifest::parse((string)json_encode(self::manifest($code, $over)), $code);
    }

    /**
     * @param array<string, mixed> $over
     * @return list<string>
     */
    private static function problems(array $over, string $code = 'demo'): array {
        try {
            self::parse($over, $code);
        } catch (ManifestException $e) {
            return $e->problems();
        }

        return [];
    }

    private static function pageOutput(Registry $registry, ?Consent $consent = null, array $signals = []): PageOutput {
        $page = new PageOutput('common/home', 'pt-br', $consent, $signals);
        $args = [$page];
        $registry->get('event')->trigger(Manager::EVENT_PAGE_OUTPUT, $args);

        return $page;
    }

    /** @return array{0: Registry, 1: Manager, 2: object, 3: string} registry, manager, log, codigo */
    private function site(?Consent $consent, array $over = self::TRACKER, bool $enabled = true): array {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, $over), self::ON_PAGE);

        [$registry, $db, $log] = $this->registry();

        if ($enabled) {
            $db->put($code . '.enabled', '1');
        }

        if ($consent !== null) {
            $registry->set('consent', $consent);
        }

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        return [$registry, $manager, $log, $code];
    }

    private static function policy(Registry $registry): string {
        return $registry->get('csp')->header();
    }

    // ── manifesto ───────────────────────────────────────────────────────────

    public function testWithoutTheKeysNothingDependsOnConsent(): void {
        $manifest = self::parse();

        self::assertSame([], $manifest->consent());
        self::assertSame([], $manifest->cookies());

        // O manifesto continua estrito: uma chave parecida, escrita errado, invalida o arquivo.
        foreach (['consents', 'Consent', 'cookie'] as $typo) {
            self::assertCount(1, self::problems([$typo => ['analytics']]), $typo);
        }
    }

    public function testConsentIsReadInTheFixedOrder(): void {
        self::assertSame(['analytics'], self::parse(['consent' => ['analytics']])->consent());
        self::assertSame(['marketing'], self::parse(['consent' => ['marketing']])->consent());
        self::assertSame(['analytics', 'marketing'], self::parse(['consent' => ['marketing', 'analytics']])->consent());
        self::assertSame(['analytics', 'marketing'], Consent::CATEGORIES);
    }

    /** @return array<string, array{mixed}> */
    public static function badConsent(): array {
        return [
            'texto'              => ['analytics'],
            'verdadeiro'         => [true],
            'objeto'             => [['a' => 'analytics']],
            'desconhecida'       => [['necessary']],
            'maiuscula'          => [['Analytics']],
            'com espaco'         => [['analytics ']],
            'numero'             => [[1]],
            'repetida'           => [['analytics', 'analytics']],
            'tres itens'         => [['analytics', 'marketing', 'analytics']],
            'todas por curinga'  => [['*']],
        ];
    }

    #[DataProvider('badConsent')]
    public function testConsentAcceptsOnlyTheKnownCategories(mixed $value): void {
        $problems = self::problems(['consent' => $value]);

        self::assertCount(1, $problems, implode(' | ', $problems));
        self::assertStringStartsWith('consent: ', $problems[0]);
    }

    public function testConsentIsOnlyForExtensionsOfThePublicSiteAlone(): void {
        foreach ([['admin'], ['catalog', 'admin']] as $apps) {
            $problems = self::problems(['consent' => ['analytics'], 'apps' => $apps]);

            self::assertSame(['consent: only for an extension that runs on the public site alone ("apps": ["catalog"])'], $problems);
        }

        self::assertSame(['marketing'], self::parse(['consent' => ['marketing'], 'apps' => ['catalog']])->consent());
    }

    public function testCookiesAreNamesOrPrefixes(): void {
        $manifest = self::parse(['consent' => ['analytics'], 'cookies' => ['_ga', '_ga_*', 'a.b-c', '_ga']]);

        self::assertSame(['_ga', '_ga_*', 'a.b-c'], $manifest->cookies(), 'repetido aparece uma vez');
        self::assertSame(30, Manifest::MAX_COOKIES);
    }

    /** @return array<string, array{mixed}> */
    public static function badCookies(): array {
        return [
            'texto'                 => ['_ga'],
            'objeto'                => [['a' => '_ga']],
            'numero'                => [[1]],
            'vazio'                 => [['']],
            'asterisco sozinho'     => [['*']],
            'prefixo de uma letra'  => [['_*']],
            'asterisco no meio'     => [['_g*a']],
            'dois asteriscos'       => [['_ga**']],
            'com espaco'            => [['_ga x']],
            'com ponto e virgula'   => [['_ga;Domain=x']],
            'com igual'             => [['_ga=1']],
            'quebra de linha'       => [["_ga\n"]],
            'longo demais'          => [[str_repeat('a', 64)]],
            'itens demais'          => [array_map(static fn (int $i): string => 'c' . $i, range(1, 31))],
        ];
    }

    #[DataProvider('badCookies')]
    public function testCookieNamesThatAreRefused(mixed $value): void {
        $problems = self::problems(['consent' => ['analytics'], 'cookies' => $value]);

        self::assertCount(1, $problems, implode(' | ', $problems));
        self::assertStringStartsWith('cookies: ', $problems[0]);
    }

    public function testCookieLimits(): void {
        self::assertSame([], self::problems(['consent' => ['analytics'], 'cookies' => [str_repeat('a', 63), str_repeat('a', 62) . '*', 'ab*']]));
        self::assertSame([], self::problems(['consent' => ['analytics'], 'cookies' => array_map(static fn (int $i): string => 'c' . $i, range(1, 30))]));
    }

    public function testCookiesNeedConsent(): void {
        self::assertSame(
            ['cookies: only together with "consent" (the cookies are removed when the visitor withdraws it)'],
            self::problems(['cookies' => ['_ga']])
        );
    }

    public function testNamesInEveryLanguageAreAvailable(): void {
        self::assertSame(['*' => 'Extensao demo'], self::parse()->names());
        self::assertSame(
            ['en-us' => 'Tracker', 'pt-br' => 'Rastreador'],
            self::parse(['name' => ['en-us' => 'Tracker', 'pt-br' => 'Rastreador']])->names()
        );
    }

    // ── o bloqueio ──────────────────────────────────────────────────────────

    public function testWithoutAChoiceTheExtensionCodeIsNotEvenLoaded(): void {
        [$registry, $manager, $log, $code] = $this->site(Consent::none());

        self::assertFalse(class_exists(self::classOf($code), false), 'o PHP da extensao foi carregado sem aceitacao');
        self::assertSame([], $manager->running());
        self::assertSame([], $manager->failed(), 'esperar a escolha nao e defeito');
        self::assertFalse($registry->get('event')->has(Manager::EVENT_PAGE_OUTPUT));
        self::assertSame('', self::pageOutput($registry)->render(PageOutput::HEAD, 'n'));
        self::assertStringNotContainsString('tracker.example', self::policy($registry));
        self::assertSame('', $log->all(), 'nada vai para o log a cada visita sem aceitacao');
    }

    public function testWithoutTheConsentServiceNobodyAccepted(): void {
        [$registry, $manager, , $code] = $this->site(null);

        self::assertFalse(class_exists(self::classOf($code), false));
        self::assertSame([], $manager->running());
        self::assertStringNotContainsString('tracker.example', self::policy($registry));
        self::assertSame(['analytics'], $manager->consentCategories(), 'o aviso continua sabendo o que perguntar');
    }

    public function testAnythingThatIsNotAConsentObjectCountsAsNoAcceptance(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::TRACKER), self::ON_PAGE);

        foreach ([true, 'analytics', ['analytics' => true], new \stdClass()] as $fake) {
            [$registry, $db] = $this->registry();
            $db->put($code . '.enabled', '1');
            $registry->set('consent', $fake);

            $manager = $this->manager($registry, $folder);
            $manager->boot('catalog');

            self::assertSame([], $manager->running(), get_debug_type($fake));
            self::assertFalse(class_exists(self::classOf($code), false), get_debug_type($fake));
        }
    }

    public function testRefusalKeepsItOff(): void {
        [$registry, $manager, , $code] = $this->site(Consent::of(['analytics' => false, 'marketing' => true], self::NOW));

        self::assertFalse(class_exists(self::classOf($code), false), 'aceitar OUTRA categoria nao libera esta');
        self::assertSame([], $manager->running());
        self::assertStringNotContainsString('tracker.example', self::policy($registry));
    }

    public function testAcceptanceStartsItWithItsOrigins(): void {
        $consent = Consent::of(['analytics' => true], self::NOW);
        [$registry, $manager, , $code] = $this->site($consent);

        self::assertSame([$code], $manager->running());
        self::assertStringContainsString('<meta name="x-tracker" content="1">', self::pageOutput($registry, $consent)->render(PageOutput::HEAD, 'n'));
        self::assertStringContainsString('https://cdn.tracker.example', self::policy($registry));
        self::assertStringContainsString('https://collect.tracker.example', self::policy($registry));
    }

    public function testAnExtensionOfTwoCategoriesRunsWithEitherOne(): void {
        $both = ['consent' => ['analytics', 'marketing']] + self::TRACKER;

        foreach ([
            [['analytics' => true, 'marketing' => false], true],
            [['analytics' => false, 'marketing' => true], true],
            [['analytics' => true], true],
            [['analytics' => false, 'marketing' => false], false],
            [['marketing' => false], false],
        ] as [$choices, $runs]) {
            [, $manager, , $code] = $this->site(Consent::of($choices, self::NOW), $both);

            self::assertSame($runs ? [$code] : [], $manager->running(), json_encode($choices));
        }
    }

    public function testAnExtensionWithoutConsentRunsForEveryone(): void {
        $free = self::TRACKER;
        unset($free['consent'], $free['cookies']);

        [$registry, $manager, , $code] = $this->site(Consent::none(), $free);

        self::assertSame([$code], $manager->running());
        self::assertSame([], $manager->consented());
        self::assertSame([], $manager->consentCategories());
        self::assertStringContainsString('https://cdn.tracker.example', self::policy($registry));
    }

    public function testTheChoiceIsAskedOnlyForEnabledAndReadyExtensions(): void {
        [, $off] = $this->site(Consent::none(), self::TRACKER, false);
        self::assertSame([], $off->consentCategories(), 'extensao desligada nao faz o aviso aparecer');

        $needsId = self::TRACKER + ['settings' => [['key' => 'id', 'type' => 'text', 'label' => 'ID', 'required' => true]]];
        [, $blocked] = $this->site(Consent::none(), $needsId);
        self::assertSame([], $blocked->consentCategories(), 'extensao ligada mas sem a configuracao obrigatoria nao pergunta nada');
        self::assertCount(1, $blocked->failed());
    }

    public function testMasterSwitchAlsoSilencesTheNotice(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::TRACKER), self::ON_PAGE);

        [$registry, $db] = $this->registry();
        $registry->get('config')->set('extension_off', true);
        $registry->set('consent', Consent::of(['analytics' => true], self::NOW));
        $db->put($code . '.enabled', '1');

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        self::assertSame([], $manager->consentCategories());
        self::assertSame([], $manager->consented());
        self::assertSame([], $manager->staleCookieLines(Consent::of(['analytics' => false], self::NOW), ['_tk'], ''));
    }

    public function testConsentedTellsWhatEachExtensionAsksFor(): void {
        $folder = $this->folder();
        $a      = self::code();
        $b      = self::code();
        $c      = self::code();

        $this->install($folder, $a, self::manifest($a, ['name' => ['en-us' => 'Meter', 'pt-br' => 'Medidor']] + self::TRACKER), self::ON_PAGE);
        $this->install($folder, $b, self::manifest($b, ['consent' => ['marketing'], 'cookies' => ['_ad']] + self::TRACKER), self::ON_PAGE);
        $this->install($folder, $c, self::manifest($c), '');

        [$registry, $db] = $this->registry();
        $registry->set('consent', Consent::of(['marketing' => true], self::NOW));

        foreach ([$a, $b, $c] as $code) {
            $db->put($code . '.enabled', '1');
        }

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        self::assertSame([
            $a => ['name' => ['en-us' => 'Meter', 'pt-br' => 'Medidor'], 'categories' => ['analytics'], 'cookies' => ['_tk', '_tk_*'], 'running' => false],
            $b => ['name' => ['*' => 'Extensao ' . $b], 'categories' => ['marketing'], 'cookies' => ['_ad'], 'running' => true],
        ], $manager->consented());
        self::assertSame(['analytics', 'marketing'], $manager->consentCategories());
        self::assertSame([$b, $c], $manager->running());
        self::assertFalse(class_exists(self::classOf($a), false));
    }

    public function testInTheAdminNothingAboutConsentHappens(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::TRACKER), self::ON_PAGE);

        [$registry, $db] = $this->registry('admin');
        $db->put($code . '.enabled', '1');
        $registry->set('consent', Consent::of(['analytics' => true], self::NOW));

        $manager = $this->manager($registry, $folder);
        $manager->boot('admin');

        self::assertSame([], $manager->running());
        self::assertSame([], $manager->consented());
        self::assertFalse(class_exists(self::classOf($code), false));
    }

    // ── cookies que sobram ──────────────────────────────────────────────────

    /** @return array{0: Manager, 1: string, 2: string} manager, extensao de medicao, extensao de duas categorias */
    private function twoTrackers(): array {
        $folder = $this->folder();
        $meter  = self::code();
        $tags   = self::code();

        $this->install($folder, $meter, self::manifest($meter, ['consent' => ['analytics'], 'cookies' => ['_ga', '_ga_*']] + self::TRACKER), self::ON_PAGE);
        $this->install($folder, $tags, self::manifest($tags, ['consent' => ['analytics', 'marketing'], 'cookies' => ['_ga', '_ad_*']] + self::TRACKER), self::ON_PAGE);

        [$registry, $db] = $this->registry();
        $db->put($meter . '.enabled', '1');
        $db->put($tags . '.enabled', '1');
        $registry->set('consent', Consent::none());

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        return [$manager, $meter, $tags];
    }

    public function testCookiesOfRefusedExtensionsAreExpired(): void {
        [$manager] = $this->twoTrackers();
        $present   = ['RMSSESSION', 'rms_consent', '_ga', '_ga_X1', '_ad_1', 'tema'];

        self::assertSame(
            ['_ga=; Max-Age=0; Path=/', '_ga_X1=; Max-Age=0; Path=/', '_ad_1=; Max-Age=0; Path=/'],
            $manager->staleCookieLines(Consent::of(['analytics' => false, 'marketing' => false], self::NOW), $present, '', ['RMSSESSION'])
        );
    }

    public function testACookieStillUsedByARunningExtensionIsKept(): void {
        [$manager] = $this->twoTrackers();
        $present   = ['_ga', '_ga_X1', '_ad_1'];

        // Medicao recusada, publicidade aceita: a de duas categorias continua rodando e usa _ga e _ad_*.
        self::assertSame(
            ['_ga_X1=; Max-Age=0; Path=/'],
            $manager->staleCookieLines(Consent::of(['analytics' => false, 'marketing' => true], self::NOW), $present, '')
        );
    }

    public function testNothingIsExpiredWhileTheVisitorHasNotAnswered(): void {
        [$manager] = $this->twoTrackers();
        $present   = ['_ga', '_ga_X1', '_ad_1'];

        self::assertSame([], $manager->staleCookieLines(Consent::none(), $present, ''));
        self::assertSame([], $manager->staleCookieLines(Consent::of(['analytics' => true, 'marketing' => true], self::NOW), $present, ''));

        // Medicao recusada, publicidade sem resposta: so a extensao inteiramente respondida perde os cookies.
        self::assertSame(
            ['_ga=; Max-Age=0; Path=/', '_ga_X1=; Max-Age=0; Path=/'],
            $manager->staleCookieLines(Consent::of(['analytics' => false], self::NOW), $present, '')
        );
    }

    public function testNamesToKeepAreNeverExpired(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, ['cookies' => ['RMS*', '_tk']] + self::TRACKER), self::ON_PAGE);

        [$registry, $db] = $this->registry();
        $db->put($code . '.enabled', '1');
        $registry->set('consent', Consent::none());

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        $refused = Consent::of(['analytics' => false], self::NOW);

        self::assertSame(['_tk=; Max-Age=0; Path=/'], $manager->staleCookieLines($refused, ['RMSSESSION', '_tk'], '', ['RMSSESSION']));
        self::assertSame(['RMSOUTRO=; Max-Age=0; Path=/'], $manager->staleCookieLines($refused, ['RMSSESSION', 'RMSOUTRO'], '', ['RMSSESSION']));
    }

    public function testExpiryCoversTheSiteDomain(): void {
        [$manager] = $this->twoTrackers();

        self::assertSame(
            ['_ga=; Max-Age=0; Path=/', '_ga=; Max-Age=0; Path=/; Domain=www.example.com', '_ga=; Max-Age=0; Path=/; Domain=example.com'],
            $manager->staleCookieLines(Consent::of(['analytics' => false, 'marketing' => false], self::NOW), ['_ga'], 'www.example.com')
        );
    }

    // ── o que a extensao enxerga ────────────────────────────────────────────

    public function testPageTellsTheExtensionWhichCategoryWasAccepted(): void {
        $page = new PageOutput('common/home', 'pt-br', Consent::of(['analytics' => true, 'marketing' => false], self::NOW));

        self::assertTrue($page->allows('analytics'));
        self::assertFalse($page->allows('marketing'));
        self::assertFalse($page->allows('necessary'));

        $plain = new PageOutput('common/home', 'pt-br');

        self::assertFalse($plain->allows('analytics'), 'sem a escolha, nada foi aceito');
        self::assertFalse($plain->happened(PageOutput::SIGNAL_CONTACT));
    }

    public function testPageSignalsAreOnlyTheKnownOnes(): void {
        self::assertSame(['contact_sent', 'quote_sent'], PageOutput::SIGNALS);

        $page = new PageOutput('common/contact', 'pt-br', null, ['quote_sent', 'visitante@example.com', ['x'], 7, 'contact_sent', 'contact_sent']);

        self::assertTrue($page->happened(PageOutput::SIGNAL_CONTACT));
        self::assertTrue($page->happened(PageOutput::SIGNAL_QUOTE));
        self::assertFalse($page->happened('visitante@example.com'), 'so nomes conhecidos passam: nenhum dado de formulario chega a extensao');

        $only = new PageOutput('common/contact', 'pt-br', null, ['contact_sent']);

        self::assertTrue($only->happened('contact_sent'));
        self::assertFalse($only->happened('quote_sent'));
        self::assertFalse($only->happened(''));
    }
}
