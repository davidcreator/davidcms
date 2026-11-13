<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Consent;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Consent;

/**
 * EX-02 (ADR 0096): a escolha de cookies do visitante. O valor vem do
 * navegador, entao tudo o que nao for exatamente o formato gravado pelo site
 * conta como "nenhuma escolha": na duvida, nenhuma etiqueta roda.
 */
final class ConsentTest extends TestCase {
    private const NOW = 1763000000;

    public function testNoChoiceAllowsNothingAndEverythingIsPending(): void {
        $none = Consent::none();

        self::assertFalse($none->allows('analytics'));
        self::assertFalse($none->allows('marketing'));
        self::assertFalse($none->allowsAny(['analytics', 'marketing']));
        self::assertFalse($none->decided('analytics'));
        self::assertSame(['analytics', 'marketing'], $none->pending(['analytics', 'marketing']));
        self::assertSame([], $none->granted());
        self::assertSame(0, $none->time());
        self::assertSame('', $none->value());
    }

    public function testChoiceKeepsEachCategoryApart(): void {
        $choice = Consent::of(['analytics' => true, 'marketing' => false], self::NOW);

        self::assertTrue($choice->allows('analytics'));
        self::assertFalse($choice->allows('marketing'));
        self::assertTrue($choice->allowsAny(['marketing', 'analytics']));
        self::assertFalse($choice->allowsAny(['marketing']));
        self::assertFalse($choice->allowsAny([]));
        self::assertTrue($choice->decided('analytics'));
        self::assertTrue($choice->decided('marketing'));
        self::assertSame([], $choice->pending(['analytics', 'marketing']));
        self::assertSame(['analytics'], $choice->granted());
        self::assertSame(self::NOW, $choice->time());
        self::assertSame('1.1763000000.a1m0', $choice->value());
    }

    public function testACategoryNotAnsweredStaysPending(): void {
        $choice = Consent::of(['analytics' => true], self::NOW);

        self::assertFalse($choice->decided('marketing'));
        self::assertFalse($choice->allows('marketing'));
        self::assertSame(['marketing'], $choice->pending(['analytics', 'marketing']));
        self::assertSame('1.1763000000.a1', $choice->value());
    }

    public function testOnlyTheBooleanTrueIsAcceptance(): void {
        foreach ([1, '1', 'true', 'yes', 'on', [], null, 1.0] as $value) {
            $choice = Consent::of(['analytics' => $value], self::NOW);

            self::assertFalse($choice->allows('analytics'), var_export($value, true));
            self::assertTrue($choice->decided('analytics'), var_export($value, true));
        }
    }

    public function testUnknownCategoriesAreIgnored(): void {
        $choice = Consent::of(['necessary' => true, 'ads' => true, 0 => true], self::NOW);

        self::assertSame('', $choice->value());
        self::assertSame(0, $choice->time());
        self::assertFalse($choice->allows('ads'));
        self::assertSame([], $choice->pending(['ads', 'necessary', 7]));
    }

    public function testCookieRoundTrip(): void {
        foreach ([
            ['analytics' => true, 'marketing' => true],
            ['analytics' => true, 'marketing' => false],
            ['analytics' => false, 'marketing' => true],
            ['analytics' => false, 'marketing' => false],
            ['analytics' => true],
            ['marketing' => false],
        ] as $choices) {
            $value = Consent::of($choices, self::NOW)->value();
            $back  = Consent::fromCookie($value, self::NOW + 60);

            self::assertSame($value, $back->value(), $value);
            self::assertSame(self::NOW, $back->time());

            foreach (Consent::CATEGORIES as $category) {
                self::assertSame($choices[$category] ?? false, $back->allows($category), $value . ' ' . $category);
                self::assertSame(isset($choices[$category]), $back->decided($category), $value . ' ' . $category);
            }
        }
    }

    public function testTheOrderOfThePairsInTheCookieDoesNotMatter(): void {
        $back = Consent::fromCookie('1.1763000000.m1a0', self::NOW);

        self::assertTrue($back->allows('marketing'));
        self::assertFalse($back->allows('analytics'));
        self::assertSame('1.1763000000.a0m1', $back->value());
    }

    /** @return array<string, array{mixed}> */
    public static function forged(): array {
        return [
            'vazio'                 => [''],
            'nulo'                  => [null],
            'lista'                 => [['1.1763000000.a1']],
            'numero'                => [1],
            'verdadeiro'            => [true],
            'sem versao'            => ['1763000000.a1'],
            'outra versao'          => ['2.1763000000.a1'],
            'hora curta'            => ['1.176300000.a1'],
            'hora longa'            => ['1.17630000000.a1'],
            'hora com letra'        => ['1.17630000x0.a1'],
            'sem pares'             => ['1.1763000000.'],
            'letra desconhecida'    => ['1.1763000000.x1'],
            'valor 2'               => ['1.1763000000.a2'],
            'par incompleto'        => ['1.1763000000.a1m'],
            'tres pares'            => ['1.1763000000.a1m1a1'],
            'letra repetida'        => ['1.1763000000.a1a0'],
            'repetida igual'        => ['1.1763000000.m1m1'],
            'maiuscula'             => ['1.1763000000.A1'],
            'espaco antes'          => [' 1.1763000000.a1'],
            'quebra de linha'       => ["1.1763000000.a1\n"],
            'ponto e virgula'       => ['1.1763000000.a1; Domain=evil.example'],
            'tudo aceito por texto' => ['all'],
            'json'                  => ['{"analytics":true}'],
        ];
    }

    #[DataProvider('forged')]
    public function testAnythingElseInTheCookieIsNoChoice(mixed $raw): void {
        $back = Consent::fromCookie($raw, self::NOW);

        self::assertSame('', $back->value());
        self::assertFalse($back->allowsAny(Consent::CATEGORIES));
        self::assertSame(Consent::CATEGORIES, $back->pending(Consent::CATEGORIES));
    }

    public function testChoiceExpiresAfterTheConfiguredDays(): void {
        $value = '1.1763000000.a1m1';
        $day   = 86400;

        self::assertTrue(Consent::fromCookie($value, self::NOW + 180 * $day)->allows('analytics'), 'no ultimo segundo do prazo padrao');
        self::assertFalse(Consent::fromCookie($value, self::NOW + 180 * $day + 1)->allows('analytics'), 'um segundo depois');
        self::assertSame(Consent::CATEGORIES, Consent::fromCookie($value, self::NOW + 180 * $day + 1)->pending(Consent::CATEGORIES));

        self::assertTrue(Consent::fromCookie($value, self::NOW + 30 * $day, 30)->allows('analytics'));
        self::assertFalse(Consent::fromCookie($value, self::NOW + 30 * $day + 1, 30)->allows('analytics'));
        self::assertTrue(Consent::fromCookie($value, self::NOW + 390 * $day, '390')->allows('marketing'));
        self::assertFalse(Consent::fromCookie($value, self::NOW + 390 * $day + 1, '390')->allows('marketing'));
    }

    public function testAChoiceDatedInTheFutureIsRefused(): void {
        $value = '1.1763000000.a1';

        self::assertTrue(Consent::fromCookie($value, self::NOW - 300)->allows('analytics'), 'relogios podem diferir 5 minutos');
        self::assertFalse(Consent::fromCookie($value, self::NOW - 301)->allows('analytics'));
        self::assertFalse(Consent::fromCookie('1.9999999999.a1', self::NOW)->allows('analytics'), 'data forjada para nunca vencer');
    }

    public function testDaysStayInsideTheLimits(): void {
        self::assertSame(180, Consent::DEFAULT_DAYS);
        self::assertSame(30, Consent::MIN_DAYS);
        self::assertSame(390, Consent::MAX_DAYS);

        self::assertSame(30, Consent::days(30));
        self::assertSame(390, Consent::days(390));
        self::assertSame(90, Consent::days('90'));
        self::assertSame(365, Consent::days('0365'));

        foreach ([29, 391, 0, -1, '29', '391', '', 'abc', '90 ', '9e1', '90.0', 90.0, null, true, [], '99999'] as $value) {
            self::assertSame(180, Consent::days($value), var_export($value, true));
        }
    }

    public function testCookieLine(): void {
        $choice = Consent::of(['analytics' => true, 'marketing' => false], self::NOW);

        self::assertSame(
            'rms_consent=1.1763000000.a1m0; Max-Age=15552000; Path=/; SameSite=Lax; HttpOnly',
            $choice->cookieLine(180, false)
        );
        self::assertSame(
            'rms_consent=1.1763000000.a1m0; Max-Age=2592000; Path=/; SameSite=Lax; HttpOnly; Secure',
            $choice->cookieLine('30', true)
        );
        self::assertSame(
            'rms_consent=1.1763000000.a1m0; Max-Age=15552000; Path=/; SameSite=Lax; HttpOnly',
            $choice->cookieLine('nao e numero', false),
            'prazo invalido cai no padrao'
        );
        self::assertSame('rms_consent=; Max-Age=0; Path=/; SameSite=Lax; HttpOnly', Consent::none()->cookieLine(180, false));
        self::assertSame('rms_consent=; Max-Age=0; Path=/; SameSite=Lax; HttpOnly; Secure', Consent::none()->cookieLine(180, true));
    }

    public function testNameMatchesExactOrPrefix(): void {
        self::assertTrue(Consent::nameMatches('_ga', '_ga'));
        self::assertFalse(Consent::nameMatches('_gat', '_ga'));
        self::assertFalse(Consent::nameMatches('_g', '_ga'));
        self::assertFalse(Consent::nameMatches('_GA', '_ga'), 'nome de cookie diferencia maiusculas');
        self::assertTrue(Consent::nameMatches('_ga_ABC123', '_ga_*'));
        self::assertTrue(Consent::nameMatches('_ga_', '_ga_*'));
        self::assertFalse(Consent::nameMatches('_ga', '_ga_*'));
        self::assertFalse(Consent::nameMatches('x_ga_1', '_ga_*'));
        self::assertFalse(Consent::nameMatches('qualquer', '*'), 'um asterisco sozinho nao apaga tudo');
        self::assertFalse(Consent::nameMatches('', ''));
        self::assertFalse(Consent::nameMatches('_ga', ''));
    }

    public function testExpireLinesOnlyForDeclaredCookiesThatCame(): void {
        $lines = Consent::expireLines(['_ga', '_ga_ABC', 'RMSSESSION', 'tema', '_fbp'], ['_ga', '_ga_*'], '');

        self::assertSame(['_ga=; Max-Age=0; Path=/', '_ga_ABC=; Max-Age=0; Path=/'], $lines);
        self::assertSame([], Consent::expireLines([], ['_ga'], 'example.com'));
        self::assertSame([], Consent::expireLines(['_ga'], [], 'example.com'));
    }

    public function testExpireLinesCoverTheParentDomains(): void {
        self::assertSame(
            [
                '_ga=; Max-Age=0; Path=/',
                '_ga=; Max-Age=0; Path=/; Domain=loja.example.com.br',
                '_ga=; Max-Age=0; Path=/; Domain=example.com.br',
                '_ga=; Max-Age=0; Path=/; Domain=com.br',
            ],
            Consent::expireLines(['_ga'], ['_ga'], 'Loja.Example.com.br')
        );
        self::assertSame(
            ['_ga=; Max-Age=0; Path=/', '_ga=; Max-Age=0; Path=/; Domain=example.com'],
            Consent::expireLines(['_ga'], ['_ga'], 'example.com')
        );
    }

    public function testExpireLinesWithoutAUsableHostHaveNoDomain(): void {
        foreach (['', 'localhost', '127.0.0.1', '10.0.0.5', 'exa mple.com', "example.com\r\nSet-Cookie: x=1", 'example.com; Path=/x', '[::1]'] as $host) {
            self::assertSame(['_ga=; Max-Age=0; Path=/'], Consent::expireLines(['_ga'], ['_ga'], $host), var_export($host, true));
        }
    }

    public function testExpireLinesNeverTouchTheChoiceNorTheKeptNames(): void {
        self::assertSame([], Consent::expireLines(['rms_consent'], ['rms_*'], ''));
        self::assertSame([], Consent::expireLines(['RMSSESSION'], ['RMS*'], '', ['RMSSESSION']));
        self::assertSame(['RMSOUTRO=; Max-Age=0; Path=/'], Consent::expireLines(['RMSSESSION', 'RMSOUTRO'], ['RMS*'], '', ['RMSSESSION']));
    }

    public function testExpireLinesSkipNamesThatCouldBreakTheHeader(): void {
        $odd = ["_ga\r\nSet-Cookie: admin=1", '_ga;x', '_ga x', '_ga=1', '_ga,', str_repeat('_ga', 30), '', '_ga"'];

        self::assertSame([], Consent::expireLines($odd, ['_ga*'], ''));
        self::assertSame(['123=; Max-Age=0; Path=/'], Consent::expireLines([123], ['12*'], ''), 'o PHP entrega nome numerico como inteiro');
    }

    public function testExpireLinesHaveACeiling(): void {
        $names = [];

        for ($i = 0; $i < 100; $i++) {
            $names[] = '_ga_' . $i;
        }

        self::assertSame(40, Consent::MAX_EXPIRE_LINES);
        self::assertCount(40, Consent::expireLines($names, ['_ga_*'], ''));
        self::assertCount(40, Consent::expireLines($names, ['_ga_*'], 'a.b.example.com'));
    }
}
