<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Theme\ThemePreviewState;
use Reamur\System\Library\Theme\ThemeReleaseProbe;

/**
 * KZ-15 (ADR 0108): `ThemeReleaseProbe` calcula o aviso "Liberacao" do editor
 * de tema (publicado, rascunho pendente, nenhum tema ativo) e nao tinha teste
 * proprio. A classe e pura: recebe arrays e devolve escalares.
 */
final class ThemeReleaseProbeTest extends TestCase {
    private const KEYS = ['verdict', 'pending', 'pending_count', 'pending_keys', 'css_parts', 'css_bytes',
        'scene_layers', 'active', 'asset_ok', 'asset_version', 'verdict_label', 'hint'];
    private const VERSION = '8.1.3-kaz-theme-editor-kz15';

    /** @return array<string,mixed> */
    private function config(): array {
        return [
            'name' => 'Tema',
            'primary' => '#112233',
            'tokens' => ['radius' => 4, 'density' => 'compact'],
            'sections' => ['hero', 'news', 'contact'],
            'three' => ['enabled' => true, 'count' => 200],
        ];
    }

    /**
     * @param array<string,mixed> $draft
     * @param array<string,mixed> $published
     * @return array<string,scalar>
     */
    private function probe(array $draft, array $published, bool $active = true): array {
        return ThemeReleaseProbe::summarize($draft, $published, [], [], $active, self::VERSION);
    }

    public function testTheSummaryHasOnlyTheseScalars(): void {
        $out = $this->probe($this->config(), $this->config());
        self::assertSame(self::KEYS, array_keys($out));
        foreach ($out as $key => $value) {
            self::assertIsScalar($value, $key);
        }
    }

    public function testActiveThemeWithTheSameConfigIsPublished(): void {
        $out = $this->probe($this->config(), $this->config());
        self::assertSame('PUBLICADO', $out['verdict']);
        self::assertSame(0, $out['pending']);
        self::assertSame(0, $out['pending_count']);
        self::assertSame('', $out['pending_keys']);
        self::assertSame(1, $out['active']);
    }

    public function testActiveThemeWithADifferentDraftIsPending(): void {
        $draft = $this->config();
        $draft['primary'] = '#445566';
        $draft['tokens']['radius'] = 8;
        $draft['novo'] = 'x';
        $out = $this->probe($draft, $this->config());
        self::assertSame('RASCUNHO_PENDENTE', $out['verdict']);
        self::assertSame(1, $out['pending']);
        self::assertSame(3, $out['pending_count']);
        // Nomes das chaves do primeiro nivel, em ordem alfabetica.
        self::assertSame('novo, primary, tokens', $out['pending_keys']);
    }

    public function testAKeyThatExistsOnlyInThePublishedConfigIsPendingToo(): void {
        $published = $this->config();
        $published['antiga'] = 'x';
        $out = $this->probe($this->config(), $published);
        self::assertSame('RASCUNHO_PENDENTE', $out['verdict']);
        self::assertSame('antiga', $out['pending_keys']);
        // Junto com uma chave do rascunho, a lista continua em ordem alfabetica:
        // "antiga" so existe no publicado e mesmo assim vem antes de "primary".
        $draft = $this->config();
        $draft['primary'] = '#445566';
        self::assertSame('antiga, primary', $this->probe($draft, $published)['pending_keys']);
    }

    public function testThemeThatIsNotActiveHasNoVerdictAboutTheDraft(): void {
        $draft = $this->config();
        $draft['primary'] = '#445566';
        foreach ([$draft, $this->config()] as $candidate) {
            $out = $this->probe($candidate, $this->config(), false);
            self::assertSame('SEM_TEMA_ATIVO', $out['verdict']);
            self::assertSame(0, $out['active']);
        }
        // A diferenca continua sendo informada: a tela mostra quais chaves estao pendentes.
        $out = $this->probe($draft, $this->config(), false);
        self::assertSame(1, $out['pending']);
        self::assertSame('primary', $out['pending_keys']);
    }

    public function testTheOrderOfTheKeysDoesNotMakeADraftPending(): void {
        $published = [
            'three' => ['count' => 200, 'enabled' => true],
            'sections' => ['hero', 'news', 'contact'],
            'tokens' => ['density' => 'compact', 'radius' => 4],
            'primary' => '#112233',
            'name' => 'Tema',
        ];
        self::assertSame('PUBLICADO', $this->probe($this->config(), $published)['verdict']);
    }

    public function testTheOrderOfAListDoesMakeADraftPending(): void {
        $published = $this->config();
        $published['sections'] = ['news', 'hero', 'contact'];
        $out = $this->probe($this->config(), $published);
        self::assertSame('RASCUNHO_PENDENTE', $out['verdict']);
        self::assertSame('sections', $out['pending_keys']);
    }

    public function testValuesOfTheFirstLevelAreComparedAsText(): void {
        $draft = ['on' => true, 'off' => false, 'n' => 5, 'vazio' => null];
        $published = ['on' => '1', 'off' => '0', 'n' => '5', 'vazio' => ''];
        self::assertSame('PUBLICADO', $this->probe($draft, $published)['verdict']);
        // Dentro de um mapa o tipo conta: 4 e "4" sao valores diferentes no JSON.
        $out = $this->probe(['tokens' => ['radius' => 4]], ['tokens' => ['radius' => '4']]);
        self::assertSame('tokens', $out['pending_keys']);
    }

    public function testAtMostSixteenPendingKeysAreListed(): void {
        // Gravadas de tras para a frente: as 16 listadas sao as primeiras em ordem
        // alfabetica, nao as primeiras que aparecem no rascunho.
        $draft = [];
        $expected = [];
        for ($i = 20; $i >= 1; $i--) {
            $draft[sprintf('chave_%02d', $i)] = $i;
        }
        for ($i = 1; $i <= 16; $i++) {
            $expected[] = sprintf('chave_%02d', $i);
        }
        $out = $this->probe($draft, []);
        self::assertSame(16, $out['pending_count']);
        self::assertSame(implode(', ', $expected), $out['pending_keys']);
        self::assertSame('RASCUNHO_PENDENTE', $out['verdict']);
    }

    public function testCssPartsAndBytesComeFromTheResolvedState(): void {
        $state = [
            'token_css' => '--rms-primary:#112233;',
            'component_css' => '--rms-card-radius:4px',
            'schedule_css' => '   ',
            'media_css' => '',
            'site_css' => ['nao', 'e', 'texto'],
            'scene_css' => '--rms-scene:1',
        ];
        $out = ThemeReleaseProbe::summarize([], [], $state, [], true, self::VERSION);
        self::assertSame(3, $out['css_parts']);
        self::assertSame(strlen(ThemePreviewState::css($state)), $out['css_bytes']);
        self::assertGreaterThan(40, $out['css_bytes']);

        $none = ThemeReleaseProbe::summarize([], [], [], [], true, self::VERSION);
        self::assertSame(0, $none['css_parts']);
        self::assertSame(0, $none['css_bytes']);
    }

    public function testSceneLayersAreCountedUpToTheLimitOfTheScene(): void {
        $count = static fn (array $view): int => (int)ThemeReleaseProbe::summarize([], [], [], $view, true, self::VERSION)['scene_layers'];
        self::assertSame(0, $count([]));
        self::assertSame(0, $count(['layers' => 'nao e lista']));
        self::assertSame(1, $count(['layers' => [['id' => 'a']]]));
        self::assertSame(5, $count(['layers' => array_fill(0, 5, ['id' => 'a'])]));
        self::assertSame(48, $count(['layers' => array_fill(0, 60, ['id' => 'a'])]));
    }

    public function testAssetMarkIsThePartOfTheVersionThatDoesNotChange(): void {
        self::assertSame('-kaz-theme-editor-', ThemeReleaseProbe::ASSET_MARK);
        $ok = static fn (string $version): int => (int)ThemeReleaseProbe::summarize([], [], [], [], true, $version)['asset_ok'];
        self::assertSame(1, $ok(self::VERSION));
        self::assertSame(1, $ok('8.1.3-kaz-theme-editor-kz27'));
        self::assertSame(0, $ok('8.1.3-theme-builder-c'));
        self::assertSame(0, $ok(''));

        // O controller entrega ao resumo a mesma versao que poe no endereco do script do editor.
        $controller = (string)file_get_contents(DIR_REAMUR . 'dev/admin/controller/design/theme.php');
        self::assertSame(1, preg_match("/\\\$kazAssetAJ=\\\\Reamur\\\\Admin\\\\Controller\\\\Common\\\\Header::ASSET_V \\. '([^']+)';/", $controller, $m));
        self::assertStringStartsWith(ThemeReleaseProbe::ASSET_MARK, $m[1]);
        self::assertStringContainsString("'view/js/kaz-theme-editor.js?v=' . \$kazAssetAJ", $controller);
        self::assertStringContainsString('(int)($theme[\'status\']??0)===1,$kazAssetAJ);', $controller);
    }

    public function testAssetVersionKeepsOnlyTheLastThirtyTwoCharacters(): void {
        $long = str_repeat('x', 40) . self::VERSION;
        $out = ThemeReleaseProbe::summarize([], [], [], [], true, $long);
        self::assertSame(substr($long, -32), $out['asset_version']);
        self::assertSame(32, strlen((string)$out['asset_version']));
        self::assertSame(self::VERSION, ThemeReleaseProbe::summarize([], [], [], [], true, self::VERSION)['asset_version']);
    }

    /** A tela nao imprime mais estes dois (ADR 0107), mas o resumo continua a devolve-los. */
    public function testLabelAndHintStillComeWithTheSummary(): void {
        $draft = $this->config();
        $draft['primary'] = '#445566';
        $cases = [
            'Publicado' => $this->probe($this->config(), $this->config()),
            'Rascunho pendente' => $this->probe($draft, $this->config()),
            'Nenhum tema ativo' => $this->probe($this->config(), $this->config(), false),
        ];
        foreach ($cases as $label => $out) {
            self::assertSame($label, $out['verdict_label']);
            self::assertNotSame('', $out['hint']);
        }
        self::assertCount(3, array_unique(array_column($cases, 'hint')));
    }
}
