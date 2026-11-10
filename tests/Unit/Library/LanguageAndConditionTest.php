<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\LanguageNegotiator;
use Reamur\System\Library\Theme\ConditionEngine;

final class LanguageAndConditionTest extends TestCase {
    public function testLanguageNegotiatorPrecedenceAndSiteSwitch(): void {
        $res = LanguageNegotiator::resolve([
            'enabled'       => ['en-us', 'pt-br', 'es-eu'],
            'default'       => 'en-us',
            'requested'     => 'pt-br',
            'stored'        => 'es-eu',
            'stored_site'   => 'main',
            'site'          => 'main',
            'site_language' => 'en-us',
        ]);
        self::assertSame('pt-br', $res['code']);
        self::assertSame('request', $res['source']);

        // Troca de site invalida idioma herdado da sessao de outro site
        $switched = LanguageNegotiator::resolve([
            'enabled'       => ['en-us', 'pt-br', 'es-eu'],
            'default'       => 'en-us',
            'requested'     => '',
            'stored'        => 'es-eu',
            'stored_site'   => 'main',
            'site'          => 'shop',
            'site_language' => 'pt-br',
        ]);
        self::assertSame('pt-br', $switched['code']);
        self::assertSame('site', $switched['source']);
        self::assertTrue($switched['site_changed']);
    }

    public function testConditionEngineAppliesValidRulesAndRejectsUnsafe(): void {
        $engine = new ConditionEngine();

        $rules = [
            [
                'id'   => 'ok_rule',
                'when' => ['field' => 'language', 'op' => 'eq', 'value' => 'pt-br'],
                'then' => ['primary' => '#112233', 'flags' => ['promo_banner']],
            ],
            [
                'id'   => 'bad_color',
                'when' => ['field' => 'language', 'op' => 'eq', 'value' => 'pt-br'],
                'then' => ['primary' => 'javascript:alert(1)'],
            ],
        ];

        $out = $engine->evaluate($rules, ['language' => 'pt-br']);
        self::assertSame('#112233', $out['primary']);
        self::assertContains('promo_banner', $out['flags']);
        self::assertSame(['ok_rule'], $out['matched']);
        self::assertContains('bad_color', $out['ignored']);
    }
}
