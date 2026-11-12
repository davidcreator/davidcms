<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Theme\ThemeLiveTokens;
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\ThemePreviewState;
use Reamur\System\Library\Theme\TokenRegistry;

/**
 * KZ-03 (ADR 0085): tokens ao vivo. Cobre o mapa token -> variavel CSS que a
 * previa imediata usa e a leitura das variaveis declaradas pelo proprio tema.
 */
final class KazLiveTokensTest extends TestCase {
    /** @return array<string,string> */
    private function declarations(string $css): array {
        $out = [];
        foreach (explode(';', $css) as $part) {
            $at = strpos($part, ':');
            if ($at !== false) $out[trim(substr($part, 0, $at))] = trim(substr($part, $at + 1));
        }
        return $out;
    }

    public function testEveryTokenMapsToExactlyOneVariable(): void {
        $registry = new TokenRegistry();
        $spec = ThemeLiveTokens::spec($registry);

        self::assertSame(array_keys($registry->fields()), array_keys($spec));
        $vars = array_column($spec, 'var');
        self::assertSame($vars, array_values(array_unique($vars)));
        foreach ($spec as $key => $entry) {
            self::assertMatchesRegularExpression('/^--rms-[a-z-]+$/D', $entry['var'], $key);
            self::assertContains($entry['kind'], ['color', 'map', 'number'], $key);
            self::assertSame($registry->fields()[$key]['group'], $entry['group'], $key);
        }
    }

    /** O valor que a previa aplica na hora e o mesmo que o servidor gera ao salvar. */
    public function testImmediatePreviewValueMatchesRegistryCss(): void {
        $registry = new TokenRegistry();
        $defaults = $registry->defaults();
        foreach (ThemeLiveTokens::spec($registry) as $key => $entry) {
            $meta = $registry->fields()[$key];
            $samples = match ($entry['kind']) {
                'color' => ['#123456', '#abcdef'],
                'map' => array_map('strval', array_keys($entry['map'])),
                default => $meta['type'] === 'float'
                    ? [number_format((float)$meta['min'], 2, '.', ''), number_format(((float)$meta['min'] + (float)$meta['max']) / 2, 2, '.', ''), number_format((float)$meta['max'], 2, '.', '')]
                    : [(string)$meta['min'], (string)intdiv((int)$meta['min'] + (int)$meta['max'], 2), (string)$meta['max']],
            };
            foreach ($samples as $value) {
                $expected = $this->declarations($registry->css(array_replace($defaults, [$key => $value])))[$entry['var']];
                $live = match ($entry['kind']) {
                    'color' => $value,
                    'map' => $entry['map'][$value],
                    default => $value . $entry['suffix'],
                };
                self::assertSame($expected, $live, $key . '=' . $value);
            }
        }
    }

    public function testMapTokensListEveryAllowedOption(): void {
        $registry = new TokenRegistry();
        $spec = ThemeLiveTokens::spec($registry);

        self::assertSame(['mono', 'rounded', 'serif', 'system'], array_keys($spec['font_family']['map']));
        self::assertSame(['comfortable', 'compact', 'spacious'], array_keys($spec['density']['map']));
        self::assertSame([0, 1, 2, 3, 4], array_keys($spec['shadow']['map']));
        self::assertSame('none', $spec['shadow']['map'][0]);
        self::assertSame('px', $spec['font_size']['suffix']);
        self::assertSame('', $spec['line_height']['suffix']);
    }

    public function testGeneralColorsUseTheVariablesThePreviewStateEmits(): void {
        $css = ThemePreviewState::colorVars(['primary' => '#112233', 'surface' => '#445566']);

        self::assertStringContainsString(ThemeLiveTokens::GENERAL['primary'] . ':#112233', $css);
        self::assertStringContainsString(ThemeLiveTokens::GENERAL['surface'] . ':#445566', $css);
    }

    public function testStyleVarsReadsOnlySimpleColorsAndPixelSizes(): void {
        $source = '<style>:root{--x-bg:#1B1F24;--x-line:rgba(0,0,0,.2);--x-max: 1100px ;--x-font:system-ui,sans-serif;'
            . '--x-ref:var(--theme-primary,#5cc6d0);--x-short:#fff;--x-gap:1.5px;--x-em:2em}</style><header>x</header>';

        $vars = ThemeLiveTokens::styleVars(['partial/site_header' => $source]);

        self::assertSame(
            [['--x-bg', '#1b1f24', 'color'], ['--x-max', '1100px', 'length'], ['--x-gap', '1.5px', 'length']],
            array_map(static fn(array $v): array => [$v['name'], $v['value'], $v['type']], $vars)
        );
        foreach ($vars as $var) {
            self::assertSame('partial/site_header', $var['template']);
            self::assertSame(0, $var['index']);
            self::assertSame(':root', $var['scope']);
        }
    }

    public function testStyleVarsIndexesRepeatedNamesAndSkipsComments(): void {
        $source = "<style>\n/* --x-bg:#000000; */\n:root{--x-bg:#111111}\n@media (max-width:600px){.x{--x-bg:#222222;}}\n</style>"
            . "<p>--x-bg:#333333;</p><style>.y{ --x-bg : #444444 }</style>";

        $vars = ThemeLiveTokens::styleVars(['common/home' => $source]);

        self::assertSame(
            [[0, '#111111', ':root'], [1, '#222222', '.x'], [2, '#444444', '.y']],
            array_map(static fn(array $v): array => [$v['index'], $v['value'], $v['scope']], $vars)
        );
    }

    public function testStyleVarsIgnoresUnknownKeysOversizedSourcesAndMarkupOutsideStyle(): void {
        $style = '<style>:root{--x-bg:#111111}</style>';

        self::assertSame([], ThemeLiveTokens::styleVars(['../../etc/passwd' => $style, 'nope/nope' => $style, 7 => $style]));
        self::assertSame([], ThemeLiveTokens::styleVars(['common/home' => '<div style="--x-bg:#111111">x</div>']));
        self::assertSame([], ThemeLiveTokens::styleVars(['common/home' => ['not', 'a', 'string']]));
        self::assertSame([], ThemeLiveTokens::styleVars(['common/home' => $style . str_repeat(' ', 131072)]));
        // Nome fora da gramatica (maiuscula, digito inicial) nao vira controle.
        self::assertSame([], ThemeLiveTokens::styleVars(['common/home' => '<style>:root{--X-bg:#111111;--1a:#222222}</style>']));
    }

    public function testStyleVarsAreCappedAndEveryDeliveredPresetIsReadable(): void {
        $many = '<style>:root{';
        for ($i = 0; $i < ThemeLiveTokens::MAX_VARS + 20; $i++) $many .= '--v' . $i . ':#101010;';
        self::assertCount(ThemeLiveTokens::MAX_VARS, ThemeLiveTokens::styleVars(['common/home' => $many . '}</style>']));

        $found = 0;
        foreach (glob(dirname(__DIR__, 3) . '/docs/temas/*.reamurtheme.json') ?: [] as $file) {
            $package = json_decode((string)file_get_contents($file), true);
            foreach (ThemeLiveTokens::styleVars((array)($package['config']['templates'] ?? [])) as $var) {
                $found++;
                self::assertContains($var['template'], ThemePackage::TEMPLATE_KEYS);
                self::assertMatchesRegularExpression('/^--[a-z][a-z0-9-]{0,47}$/D', $var['name']);
                self::assertMatchesRegularExpression($var['type'] === 'color' ? '/^#[0-9a-f]{6}$/D' : '/^\d{1,4}(\.\d{1,3})?px$/D', $var['value']);
                self::assertStringNotContainsString('<', $var['scope']);
            }
        }
        self::assertGreaterThan(0, $found);
    }

    public function testContrastColorsKeepOnlyValidHexOfColorTokens(): void {
        $registry = new TokenRegistry();
        $colors = ThemeLiveTokens::colors($registry, [
            'color_text' => '#FFFFFF', 'color_surface' => 'red', 'dark_text' => ['x'],
            'font_size' => '#123456', 'unknown' => '#123456', 'color_muted' => '#12345',
        ]);

        self::assertSame(['color_text' => '#ffffff'], $colors);
        // Com cores limpas, o contraste e sempre mensuravel.
        foreach ($registry->contrastPairs($colors) as $pair) {
            self::assertIsFloat($pair['ratio']);
        }
    }
}
