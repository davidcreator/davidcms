<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

require_once __DIR__ . '/ExtensionTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;
use Reamur\System\Library\Consent;
use Reamur\System\Library\Extension\Manager;
use Reamur\System\Library\Extension\Manifest;
use Reamur\System\Library\Extension\PageOutput;
use Reamur\System\Library\Security\Csp;

/**
 * EX-02 (ADR 0096): as quatro extensoes de medicao que acompanham a etapa
 * (Google Analytics 4, Google Tag Manager, Pixel da Meta, Tag do Pinterest),
 * rodando pela pasta de verdade. Garante: nenhuma roda sem a aceitacao da
 * categoria dela; o que sai na pagina e exatamente o codigo esperado; o
 * identificador so entra se tiver o formato certo; o aviso de formulario
 * enviado leva so o nome do evento.
 */
final class TrackingExtensionsTest extends ExtensionTestCase {
    private const NOW = 1763000000;

    private const ALL = ['analytics' => true, 'marketing' => true];

    /** codigo => [categorias, configuracao valida, origem do script] */
    private const SHIPPED = [
        'google_analytics'   => [['analytics'], ['measurement_id' => 'G-ABC1234567', 'form_events' => true], 'https://www.googletagmanager.com'],
        'google_tag_manager' => [['analytics', 'marketing'], ['container_id' => 'GTM-ABC1234', 'custom_html' => false, 'form_events' => true], 'https://www.googletagmanager.com'],
        'meta_pixel'         => [['marketing'], ['pixel_id' => '123456789012345', 'auto_config' => false, 'form_events' => true], 'https://connect.facebook.net'],
        'pinterest_tag'      => [['marketing'], ['tag_id' => '2612345678901', 'form_events' => true], 'https://s.pinimg.com'],
    ];

    private static function repo(): string {
        return dirname(__DIR__, 3) . '/';
    }

    /**
     * Liga uma extensao da pasta de verdade e monta uma pagina.
     *
     * @param array<string, mixed>  $config
     * @param array<string, mixed>  $choices escolha do visitante
     * @param list<string>          $signals
     * @return array{head: string, body: string, running: list<string>, policy: string, rejected: list<string>, log: string, page: PageOutput}
     */
    private function page(string $code, array $config, array $choices = self::ALL, array $signals = []): array {
        [$registry, $db, $log] = $this->registry();
        $db->put($code . '.enabled', '1');
        $db->put($code . '.config', (string)json_encode($config));

        $consent = Consent::of($choices, self::NOW);
        $registry->set('consent', $consent);

        $manager = $this->manager($registry, self::repo() . 'dev/extension/');
        $manager->boot('catalog');

        $page = new PageOutput('common/home', 'pt-br', $consent, $signals);
        $args = [$page];
        $registry->get('event')->trigger(Manager::EVENT_PAGE_OUTPUT, $args);

        return [
            'head'     => $page->render(PageOutput::HEAD, 'NONCE'),
            'body'     => $page->render(PageOutput::BODY_START, 'NONCE'),
            'running'  => $manager->running(),
            'policy'   => $registry->get('csp')->header(),
            'rejected' => $page->rejected(),
            'log'      => $log->all(),
            'page'     => $page,
        ];
    }

    private static function manifestOf(string $code): Manifest {
        return Manifest::read(self::repo() . 'dev/extension/', $code);
    }

    /** @return array<string, array{string}> */
    public static function codes(): array {
        return array_combine(array_keys(self::SHIPPED), array_map(static fn (string $c): array => [$c], array_keys(self::SHIPPED)));
    }

    // ── o que vale para as quatro ───────────────────────────────────────────

    public function testTheFourAreInTheFolder(): void {
        $found = array_map('basename', glob(self::repo() . 'dev/extension/*', GLOB_ONLYDIR) ?: []);
        sort($found);

        self::assertSame(['google_analytics', 'google_tag_manager', 'meta_pixel', 'pinterest_tag', 'site_verification'], $found);
    }

    #[DataProvider('codes')]
    public function testManifestDeclaresConsentCookiesAndOnlyThePublicSite(string $code): void {
        $manifest = self::manifestOf($code);

        self::assertSame(self::SHIPPED[$code][0], $manifest->consent());
        self::assertNotSame([], $manifest->cookies(), 'os cookies que a etiqueta cria tem de estar declarados');
        self::assertSame(['catalog'], $manifest->apps());
        self::assertSame(['page/output'], array_column($manifest->events(), 'trigger'), 'nenhuma escuta pedidos nem outro evento do servidor');
        self::assertSame([self::SHIPPED[$code][2]], $manifest->csp()['script-src']);
        self::assertArrayNotHasKey('style-src', $manifest->csp());
        self::assertArrayNotHasKey('form-action', $manifest->csp());

        foreach (['en-us', 'pt-br', 'es-eu'] as $language) {
            self::assertNotSame('', $manifest->description($language), $language);
        }
    }

    #[DataProvider('codes')]
    public function testNoOriginIsAWholeSchemeOrAnUnrelatedHost(string $code): void {
        $allowed = '/^https:\/\/(?:\*\.)?(?:[a-z0-9-]+\.)*(?:googletagmanager\.com|google-analytics\.com|google\.com|facebook\.com|facebook\.net|pinimg\.com|pinterest\.com)$/';

        foreach (self::manifestOf($code)->csp() as $directive => $sources) {
            foreach ($sources as $source) {
                self::assertMatchesRegularExpression($allowed, $source, $code . ' ' . $directive);
            }
        }
    }

    #[DataProvider('codes')]
    public function testItCannotBeEnabledWithoutItsIdentifier(string $code): void {
        [$registry] = $this->registry();
        $manager    = $this->manager($registry, self::repo() . 'dev/extension/');

        self::assertCount(1, $manager->blockers(self::manifestOf($code), [], []));
        self::assertSame([], $manager->blockers(self::manifestOf($code), self::SHIPPED[$code][1], []));
    }

    #[DataProvider('codes')]
    public function testWithoutAChoiceItDoesNotRun(string $code): void {
        $out = $this->page($code, self::SHIPPED[$code][1], []);

        self::assertSame([], $out['running']);
        self::assertSame('', $out['head'] . $out['body']);
        self::assertStringNotContainsString(self::SHIPPED[$code][2], $out['policy']);
        self::assertSame('', $out['log']);
    }

    #[DataProvider('codes')]
    public function testRefusingItsCategoriesKeepsItOff(string $code): void {
        $refused = array_fill_keys(self::SHIPPED[$code][0], false) + array_fill_keys(Consent::CATEGORIES, true);
        $out     = $this->page($code, self::SHIPPED[$code][1], $refused);

        self::assertSame([], $out['running'], 'aceitar outra categoria nao libera esta extensao');
        self::assertSame('', $out['head'] . $out['body']);
        self::assertStringNotContainsString(self::SHIPPED[$code][2], $out['policy']);
    }

    #[DataProvider('codes')]
    public function testWithAcceptanceItRunsAndItsScriptOriginEntersThePolicy(string $code): void {
        $out = $this->page($code, self::SHIPPED[$code][1]);

        self::assertSame([$code], $out['running']);
        self::assertSame([], $out['rejected']);
        self::assertSame('', $out['log']);
        self::assertSame('', $out['body']);
        self::assertStringStartsWith('<script nonce="NONCE">', $out['head']);
        self::assertSame(1, substr_count($out['head'], '<script nonce='), 'um unico bloco de codigo');

        preg_match("/script-src ([^;]+)/", $out['policy'], $m);
        self::assertStringContainsString(' ' . self::SHIPPED[$code][2], $m[1] ?? '');
        self::assertStringNotContainsString("'unsafe-inline'", $m[1] ?? '');
        self::assertStringNotContainsString("'unsafe-eval'", $m[1] ?? '', 'nenhuma etiqueta volta a liberar eval (ADR 0093)');
    }

    #[DataProvider('codes')]
    public function testEveryExternalScriptIsCoveredByADeclaredOrigin(string $code): void {
        $out = $this->page($code, self::SHIPPED[$code][1]);

        preg_match_all('/<script src="([^"]+)"/', $out['head'], $m);

        foreach ($m[1] as $src) {
            self::assertTrue(Csp::covers(self::SHIPPED[$code][2], html_entity_decode($src)), $src);
        }

        self::assertLessThanOrEqual(1, count($m[1]));
    }

    #[DataProvider('codes')]
    public function testFormEventsCanBeSwitchedOff(string $code): void {
        $config = ['form_events' => false] + self::SHIPPED[$code][1];
        $plain  = $this->page($code, $config);
        $sent   = $this->page($code, $config, self::ALL, [PageOutput::SIGNAL_CONTACT, PageOutput::SIGNAL_QUOTE]);

        self::assertSame($plain['head'], $sent['head']);
    }

    #[DataProvider('codes')]
    public function testWithoutASentFormThePageHasNoLeadEvent(string $code): void {
        $out = $this->page($code, self::SHIPPED[$code][1]);

        self::assertDoesNotMatchRegularExpression('/generate_lead|\'lead\'|"Contact"|"Lead"/', $out['head']);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function tampered(): array {
        return [
            'GA4 com script'        => ['google_analytics', ['measurement_id' => '");alert(1);//']],
            'GA4 minusculo'         => ['google_analytics', ['measurement_id' => 'g-abc1234567']],
            'GA4 de outro produto'  => ['google_analytics', ['measurement_id' => 'UA-12345-1']],
            'GA4 com quebra'        => ['google_analytics', ['measurement_id' => "G-ABC1234567\n"]],
            'GA4 lista'             => ['google_analytics', ['measurement_id' => ['G-ABC1234567']]],
            'GTM com script'        => ['google_tag_manager', ['container_id' => 'GTM-ABC1234</script>']],
            'GTM com parametro'     => ['google_tag_manager', ['container_id' => 'GTM-ABC1234&l=x']],
            'GTM curto'             => ['google_tag_manager', ['container_id' => 'GTM-AB']],
            'Meta com letra'        => ['meta_pixel', ['pixel_id' => '12345678901234x']],
            'Meta curto'            => ['meta_pixel', ['pixel_id' => '1234567']],
            'Meta com aspas'        => ['meta_pixel', ['pixel_id' => "1234567890');alert('1"]],
            'Pinterest com letra'   => ['pinterest_tag', ['tag_id' => '26123456789ab']],
            'Pinterest curto'       => ['pinterest_tag', ['tag_id' => '123456789']],
        ];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('tampered')]
    public function testAnIdentifierTamperedInTheDatabaseNeverReachesThePage(string $code, array $config): void {
        $out = $this->page($code, $config);

        self::assertSame('', $out['head'] . $out['body']);
    }

    /**
     * A extensao sozinha, sem o Manager na frente: recebe a configuracao exatamente como o teste a der.
     *
     * @param array<string, mixed> $settings
     */
    private function direct(string $code, array $settings, array $signals = []): string {
        require_once self::repo() . 'dev/extension/' . $code . '/extension.php';

        $class     = Manifest::className($code);
        $manifest  = self::manifestOf($code);
        $extension = new $class($this->registry()[0], $manifest, $settings, 'catalog');

        $page = new PageOutput('common/home', 'pt-br', Consent::of(self::ALL, self::NOW), $signals);
        $page->actingAs($code, $manifest->csp()['script-src'] ?? []);
        $extension->onPageOutput($page);

        return $page->render(PageOutput::HEAD, 'NONCE');
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('tampered')]
    public function testTheExtensionItselfAlsoRefusesABadIdentifier(string $code, array $config): void {
        // Segunda tranca: mesmo que o valor passasse pelo Manager, a extensao nao o escreveria.
        $key = array_key_first($config);

        if (!is_string($config[$key])) {
            $this->expectException(\TypeError::class);
        }

        self::assertSame('', $this->direct($code, $config + self::SHIPPED[$code][1]));
    }

    #[DataProvider('codes')]
    public function testTheExtensionAloneWritesTheSameAsThroughTheManager(string $code): void {
        self::assertSame($this->page($code, self::SHIPPED[$code][1])['head'], $this->direct($code, self::SHIPPED[$code][1]));
        self::assertSame('', $this->direct($code, []), 'sem identificador nada e escrito');
    }

    public function testCheckboxesThatWidenCollectionNeedTheBooleanTrue(): void {
        // O Manager so entrega booleanos; a extensao, sozinha, tambem nao aceita "quase verdadeiro".
        foreach (['1', 'on', 'true'] as $value) {
            self::assertStringNotContainsString(
                'setAttribute',
                $this->direct('google_tag_manager', ['custom_html' => $value] + self::SHIPPED['google_tag_manager'][1]),
                var_export($value, true)
            );
            self::assertStringContainsString(
                "fbq('set','autoConfig',false,",
                $this->direct('meta_pixel', ['auto_config' => $value] + self::SHIPPED['meta_pixel'][1]),
                var_export($value, true)
            );
        }
    }

    // ── Google Analytics 4 ──────────────────────────────────────────────────

    public function testGoogleAnalyticsOutput(): void {
        $out = $this->page('google_analytics', self::SHIPPED['google_analytics'][1], ['analytics' => true]);

        self::assertSame(
            '<script nonce="NONCE">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
            . "gtag('consent','default',{analytics_storage:'granted',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'});"
            . "gtag('js',new Date());"
            . 'gtag(\'config\',"G-ABC1234567",{allow_google_signals:false,allow_ad_personalization_signals:false});</script>' . "\n"
            . '<script src="https://www.googletagmanager.com/gtag/js?id=G-ABC1234567" async></script>' . "\n",
            $out['head']
        );
    }

    public function testGoogleAnalyticsKeepsAdvertisingDeniedEvenWhenMarketingWasAccepted(): void {
        $out = $this->page('google_analytics', self::SHIPPED['google_analytics'][1], self::ALL);

        self::assertStringContainsString("ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'", $out['head']);
        self::assertStringNotContainsString("ad_storage:'granted'", $out['head']);
        self::assertStringNotContainsString('doubleclick', $out['policy'] . $out['head']);
    }

    public function testGoogleAnalyticsLeadEvents(): void {
        $config  = self::SHIPPED['google_analytics'][1];
        $contact = $this->page('google_analytics', $config, self::ALL, [PageOutput::SIGNAL_CONTACT]);
        $quote   = $this->page('google_analytics', $config, self::ALL, [PageOutput::SIGNAL_QUOTE]);

        self::assertStringContainsString("gtag('event','generate_lead',{lead_source:\"contact\"});</script>", $contact['head']);
        self::assertStringNotContainsString('"quote"', $contact['head']);
        self::assertStringContainsString("gtag('event','generate_lead',{lead_source:\"quote\"});</script>", $quote['head']);
        self::assertSame(1, substr_count($quote['head'], 'generate_lead'));
        self::assertLessThan(strpos($contact['head'], 'generate_lead'), strpos($contact['head'], "gtag('config'"), 'o evento vem depois do config');
    }

    // ── Google Tag Manager ──────────────────────────────────────────────────

    /** @return array<string, array{array<string, bool>, string, string}> */
    public static function gtmConsent(): array {
        return [
            'so medicao'     => [['analytics' => true, 'marketing' => false], 'granted', 'denied'],
            'so publicidade' => [['analytics' => false, 'marketing' => true], 'denied', 'granted'],
            'as duas'        => [['analytics' => true, 'marketing' => true], 'granted', 'granted'],
            'medicao, publicidade sem resposta' => [['analytics' => true], 'granted', 'denied'],
        ];
    }

    /** @param array<string, bool> $choices */
    #[DataProvider('gtmConsent')]
    public function testTagManagerTellsTheContainerWhatWasAccepted(array $choices, string $analytics, string $marketing): void {
        $out = $this->page('google_tag_manager', self::SHIPPED['google_tag_manager'][1], $choices);

        self::assertStringContainsString(
            "gtag('consent','default',{analytics_storage:'" . $analytics . "',ad_storage:'" . $marketing . "',ad_user_data:'" . $marketing . "',ad_personalization:'" . $marketing . "'});",
            $out['head']
        );
    }

    public function testTagManagerDefaultOutputDoesNotPassTheNonceOn(): void {
        $out = $this->page('google_tag_manager', self::SHIPPED['google_tag_manager'][1], ['analytics' => true, 'marketing' => false]);

        self::assertSame(
            '<script nonce="NONCE">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
            . "gtag('consent','default',{analytics_storage:'granted',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'});"
            . "dataLayer.push({'gtm.start':new Date().getTime(),event:'gtm.js'});</script>" . "\n"
            . '<script src="https://www.googletagmanager.com/gtm.js?id=GTM-ABC1234" async></script>' . "\n",
            $out['head']
        );
        self::assertStringNotContainsString('setAttribute', $out['head']);
        self::assertStringNotContainsString('createElement', $out['head']);
    }

    public function testTagManagerWithCustomHtmlUsesTheLoaderThatCopiesTheNonce(): void {
        $config = ['custom_html' => true] + self::SHIPPED['google_tag_manager'][1];
        $out    = $this->page('google_tag_manager', $config);

        self::assertStringContainsString('j.src="https://www.googletagmanager.com/gtm.js?id=GTM-ABC1234";', $out['head']);
        self::assertStringContainsString('j.setAttribute("nonce",n.nonce||n.getAttribute("nonce"));', $out['head']);
        self::assertStringNotContainsString('<script src=', $out['head'], 'nesse modo quem cria a tag do carregador e o proprio codigo');
        self::assertSame([], $out['rejected']);
    }

    public function testTagManagerCustomHtmlIsOffUnlessTheStoredValueIsTheBooleanTrue(): void {
        foreach ([null, 0, 1, '1', 'true', 'on', []] as $value) {
            $config = self::SHIPPED['google_tag_manager'][1];

            if ($value === null) {
                unset($config['custom_html']);
            } else {
                $config['custom_html'] = $value;
            }

            self::assertStringNotContainsString('setAttribute', $this->page('google_tag_manager', $config)['head'], var_export($value, true));
        }
    }

    public function testTagManagerLeadEventGoesToTheDataLayerBeforeTheContainerStarts(): void {
        $out = $this->page('google_tag_manager', self::SHIPPED['google_tag_manager'][1], self::ALL, [PageOutput::SIGNAL_QUOTE]);

        self::assertStringContainsString("dataLayer.push({event:'generate_lead',lead_source:\"quote\"});", $out['head']);
        self::assertLessThan(strpos($out['head'], "'gtm.start'"), strpos($out['head'], 'generate_lead'));
        self::assertLessThan(strpos($out['head'], 'generate_lead'), strpos($out['head'], "'consent','default'"), 'o consentimento vem antes de qualquer evento');
    }

    // ── Pixel da Meta ───────────────────────────────────────────────────────

    public function testMetaPixelOutput(): void {
        $out = $this->page('meta_pixel', self::SHIPPED['meta_pixel'][1], ['marketing' => true]);

        self::assertSame(
            '<script nonce="NONCE">(function(f){if(f.fbq){return;}var n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments);};'
            . "if(!f._fbq){f._fbq=n;}n.push=n;n.loaded=true;n.version='2.0';n.queue=[];})(window);"
            . "fbq('set','autoConfig',false,\"123456789012345\");"
            . "fbq('init',\"123456789012345\");fbq('track','PageView');</script>" . "\n"
            . '<script src="https://connect.facebook.net/en_US/fbevents.js" async></script>' . "\n",
            $out['head']
        );
    }

    public function testMetaPixelAutomaticCollectionOnlyWhenAsked(): void {
        $on = $this->page('meta_pixel', ['auto_config' => true] + self::SHIPPED['meta_pixel'][1]);

        self::assertStringNotContainsString('autoConfig', $on['head']);

        $config = self::SHIPPED['meta_pixel'][1];
        unset($config['auto_config']);

        self::assertStringContainsString("fbq('set','autoConfig',false,", $this->page('meta_pixel', $config)['head'], 'sem valor gravado, a coleta automatica fica desligada');
    }

    public function testMetaPixelSendsNoVisitorDataOnInit(): void {
        $out = $this->page('meta_pixel', self::SHIPPED['meta_pixel'][1], self::ALL, [PageOutput::SIGNAL_CONTACT]);

        // Terceiro argumento de init seria o "advanced matching" (e-mail, telefone...).
        self::assertSame(1, preg_match("/fbq\\('init',\"[0-9]+\"\\);/", $out['head']));
        self::assertStringNotContainsString('em:', $out['head']);
    }

    public function testMetaPixelLeadEvents(): void {
        $config  = self::SHIPPED['meta_pixel'][1];
        $contact = $this->page('meta_pixel', $config, self::ALL, [PageOutput::SIGNAL_CONTACT]);
        $quote   = $this->page('meta_pixel', $config, self::ALL, [PageOutput::SIGNAL_QUOTE]);

        self::assertStringEndsWith("fbq('track','PageView');fbq('track',\"Contact\");</script>\n" . '<script src="https://connect.facebook.net/en_US/fbevents.js" async></script>' . "\n", $contact['head']);
        self::assertStringNotContainsString('"Lead"', $contact['head']);
        self::assertStringContainsString("fbq('track',\"Lead\");", $quote['head']);
        self::assertStringNotContainsString('"Contact"', $quote['head']);
    }

    // ── Tag do Pinterest ────────────────────────────────────────────────────

    public function testPinterestOutput(): void {
        $out = $this->page('pinterest_tag', self::SHIPPED['pinterest_tag'][1], ['marketing' => true]);

        self::assertSame(
            '<script nonce="NONCE">(function(w){if(w.pintrk){return;}w.pintrk=function(){w.pintrk.queue.push(Array.prototype.slice.call(arguments));};'
            . "w.pintrk.queue=[];w.pintrk.version='3.0';})(window);"
            . "pintrk('load',\"2612345678901\");pintrk('page');</script>" . "\n"
            . '<script src="https://s.pinimg.com/ct/core.js" async></script>' . "\n",
            $out['head']
        );
    }

    public function testPinterestLeadEventsAndTheFrameItNeeds(): void {
        $config = self::SHIPPED['pinterest_tag'][1];

        self::assertStringContainsString("pintrk('track','lead',{lead_type:\"contact\"});", $this->page('pinterest_tag', $config, self::ALL, [PageOutput::SIGNAL_CONTACT])['head']);
        self::assertStringContainsString("pintrk('track','lead',{lead_type:\"quote\"});", $this->page('pinterest_tag', $config, self::ALL, [PageOutput::SIGNAL_QUOTE])['head']);

        // Medido no navegador: sem esta origem em frame-src a tag acusa violacao da politica a cada pagina.
        self::assertSame(['https://ct.pinterest.com'], self::manifestOf('pinterest_tag')->csp()['frame-src']);
        self::assertSame(1, preg_match('/frame-src [^;]*https:\/\/ct\.pinterest\.com/', $this->page('pinterest_tag', $config)['policy']));
    }

    // ── juntas ──────────────────────────────────────────────────────────────

    public function testEachVisitorGetsOnlyTheExtensionsOfTheCategoriesAccepted(): void {
        $run = function (array $choices): array {
            [$registry, $db] = $this->registry();

            foreach (self::SHIPPED as $code => [, $config]) {
                $db->put($code . '.enabled', '1');
                $db->put($code . '.config', (string)json_encode($config));
            }

            $registry->set('consent', Consent::of($choices, self::NOW));
            $manager = $this->manager($registry, self::repo() . 'dev/extension/');
            $manager->boot('catalog');

            return [$manager->running(), $manager->consentCategories(), $registry->get('csp')->header()];
        };

        [$running, $asked, $policy] = $run([]);
        self::assertSame([], $running);
        self::assertSame(['analytics', 'marketing'], $asked);
        self::assertDoesNotMatchRegularExpression('/google|facebook|pinimg|pinterest/', $policy);

        [$running, , $policy] = $run(['analytics' => true, 'marketing' => false]);
        self::assertSame(['google_analytics', 'google_tag_manager'], $running);
        self::assertDoesNotMatchRegularExpression('/facebook|pinimg|pinterest/', $policy);

        [$running, , $policy] = $run(['analytics' => false, 'marketing' => true]);
        self::assertSame(['google_tag_manager', 'meta_pixel', 'pinterest_tag'], $running);
        self::assertStringContainsString('https://connect.facebook.net', $policy);
        self::assertStringContainsString('https://s.pinimg.com', $policy);

        [$running] = $run(self::ALL);
        self::assertSame(['google_analytics', 'google_tag_manager', 'meta_pixel', 'pinterest_tag'], $running);

        [$running] = $run(['analytics' => false, 'marketing' => false]);
        self::assertSame([], $running);
    }
}
