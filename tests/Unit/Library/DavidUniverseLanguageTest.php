<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Template\Twig;
use Reamur\System\Library\Theme\ThemeSourceMap;
use Reamur\System\Library\Theme\ThemeStrings;

/**
 * KZ-20 (ADR 0113): o David Universe 3D acompanha o idioma do site.
 *
 * Os tres arquivos do tema (pacote do `docs/temas`, pacote da cena real e o
 * arquivo so com o `config`) nao guardam mais frase num idioma so: o que e do
 * tema vem de `config.strings` (KZ-19) e o que e do site vem das variaveis do
 * sistema. A cena le as palavras do HUD em atributos `data-du-*` da pagina.
 */
final class DavidUniverseLanguageTest extends TestCase {
    private const LANGUAGES = ['en-us', 'pt-br', 'es-eu'];
    private const UNIVERSE = 'docs/temas/David-Universe-3D.reamurtheme.json';
    private const BO_PACKAGE = 'tema/david_universe_3d_bo.reamurtheme.json';
    private const BO_CONFIG = 'tema/bo_config.json';
    private const SCENE = 'dev/system/library/three/runtime/three/scenes/david-universe.js';
    /** O que fica igual em qualquer idioma: marca, unidade e rotulos de jogo. */
    private const SAME_EVERYWHERE = ['DA', 'KM', 'LVL', 'OK', 'Power-up'];

    private function repo(string $path): string {
        $source = file_get_contents(DIR_REAMUR . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    /** @return array<string, mixed> */
    private function config(string $path): array {
        $data = json_decode($this->repo($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data['config'] ?? null, $path);

        return $data['config'];
    }

    /** @return list<string> */
    private function files(): array {
        return [self::UNIVERSE, self::BO_PACKAGE, self::BO_CONFIG];
    }

    /** Fonte sem comentario Twig, sem a folha de estilo e sem os desenhos: sobra o que o visitante le. */
    private function readable(string $source): string {
        $source = (string)preg_replace('/\{#.*?#\}/s', '', $source);
        $source = (string)preg_replace('/<style\b.*?<\/style>/s', '', $source);

        return (string)preg_replace('/<svg\b.*?<\/svg>/s', '', $source);
    }

    public function testEveryTextOfTheThemeExistsInTheThreeLanguages(): void {
        $all = [];
        foreach ($this->files() as $file) {
            $strings = $this->config($file)['strings'] ?? null;
            self::assertIsArray($strings, $file);
            self::assertSame([], ThemeStrings::validate($strings), $file);
            self::assertSame(['en-us' => [], 'pt-br' => [], 'es-eu' => []], ThemeStrings::missing($strings, self::LANGUAGES), $file);
            foreach ($strings as $key => $texts) {
                self::assertSame(self::LANGUAGES, array_keys($texts), $file . ' ' . $key);
                foreach ($texts as $language => $text) self::assertNotSame('', trim((string)$text), $file . ' ' . $key . ' ' . $language);
            }
            $all[$file] = $strings;
        }
        // Os dois arquivos da cena real carregam o mesmo dicionario; o pacote sem a cena, os mesmos textos
        // menos os tres avisos que so a cena mostra.
        self::assertSame($all[self::BO_PACKAGE], $all[self::BO_CONFIG]);
        $first = $all[self::BO_PACKAGE];
        self::assertSame(['toast_start', 'toast_orb', 'toast_all_orbs'], array_keys(array_diff_key($first, $all[self::UNIVERSE])));
        self::assertSame($all[self::UNIVERSE], array_intersect_key($first, $all[self::UNIVERSE]));
        self::assertGreaterThanOrEqual(60, count($first));
        // Traducao de verdade, nao copia: quase nenhuma frase e igual nos tres idiomas.
        $same = array_filter($first, static fn (array $t): bool => count(array_unique($t)) === 1);
        self::assertLessThanOrEqual(3, count($same), implode(', ', array_keys($same)));
    }

    public function testSourcesAskOnlyForTextsThatExistAndNoTextIsLeftUnused(): void {
        foreach ($this->files() as $file) {
            $config = $this->config($file);
            $used = [];
            foreach ((array)$config['templates'] as $key => $source) {
                preg_match_all('/\btheme_text\.([A-Za-z0-9_]+)/', (string)$source, $m);
                foreach ($m[1] as $name) $used[$name] = true;
                // So a forma `theme_text.chave`: nada de indice montado, que este teste nao enxergaria.
                self::assertSame(0, preg_match('/\btheme_text\s*\[|attribute\s*\(\s*theme_text/', (string)$source), $file . ' ' . $key);
            }
            $used = array_keys($used);
            $have = array_keys((array)$config['strings']);
            sort($used);
            sort($have);
            self::assertSame($have, $used, $file);
        }
    }

    public function testNoWordIsLeftWrittenInOneLanguage(): void {
        foreach ($this->files() as $file) {
            $words = [];
            foreach ((array)$this->config($file)['templates'] as $key => $source) {
                $text = $this->readable((string)$source);
                // Atributos que o visitante le ou ouve, e os que a cena mostra no HUD.
                preg_match_all('/\s(?:aria-label|title|alt|placeholder|value|data-du-[a-z-]+)="([^"]*)"/', $text, $attrs);
                foreach ($attrs[1] as $value) {
                    $fixed = (string)preg_replace('/\{\{.*?\}\}/s', '', $value);
                    self::assertSame(0, preg_match('/\p{L}/u', $fixed), $file . ' ' . $key . ': ' . $value);
                }
                $text = (string)preg_replace('/\{\{.*?\}\}|\{%.*?%\}/s', ' ', $text);
                $text = (string)preg_replace('/<[^>]*>/', ' ', $text);
                preg_match_all('/\p{L}[\p{L}\-]*/u', $text, $m);
                foreach ($m[0] as $word) $words[$word] = true;
            }
            $words = array_keys($words);
            sort($words);
            self::assertSame(self::SAME_EVERYWHERE, $words, $file);
        }
    }

    public function testEveryElementStaysOpenToTheEditorAndImagesTakeTheirNameFromTheContent(): void {
        foreach ([self::UNIVERSE, self::BO_PACKAGE] as $file) {
            $images = 0;
            foreach ((array)$this->config($file)['templates'] as $key => $source) {
                $source = ThemeSourceMap::normalize((string)$source);
                foreach (ThemeSourceMap::scan($source) as $el) {
                    self::assertIsArray($el['attrs'], $file . ' ' . $key . ' #' . $el['n'] . ': tag fechada para o editor');
                    if ($el['tag'] !== 'img') continue;
                    $images++;
                    $alt = array_values(array_filter($el['attrs'], static fn (array $a): bool => $a['name'] === 'alt'));
                    self::assertCount(1, $alt, $file . ' ' . $key);
                    self::assertSame('variable', $alt[0]['kind'], $file . ' ' . $key);
                }
            }
            self::assertSame(7, $images, $file);
        }
    }

    public function testPublishedPageSpeaksTheVisitorLanguage(): void {
        $expected = ['en-us' => 'Back to liftoff →', 'pt-br' => 'Voltar à decolagem →', 'es-eu' => 'Volver al despegue →'];
        foreach ([self::UNIVERSE, self::BO_PACKAGE] as $file) {
            $config = $this->config($file);
            foreach ($expected as $language => $button) {
                $twig = new Twig('', false);
                $twig->setThemeStrings(ThemeStrings::resolve($config['strings'], $language), $language);
                $twig->setThemeTemplates((array)$config['templates']);
                $html = $twig->render('error/not_found', [
                    'header' => '', 'footer' => '', 'home' => '/inicio', 'cms_page_sections' => ['not_found_content'],
                    'heading_title' => 'T<b>', 'text_error' => 'E.', 'text_suggestion' => 'S.',
                ]);
                self::assertStringContainsString('<a class="du-btn" href="/inicio">' . $button . '</a>', $html, $file . ' ' . $language);
                // O titulo e do sistema e sai escapado; a fonte do tema foi a usada (classe do tema na pagina).
                self::assertStringContainsString('<h1>T&lt;b&gt;</h1>', $html, $file . ' ' . $language);
                self::assertStringContainsString('class="du du-page"', $html, $file . ' ' . $language);
                foreach (array_diff_key($expected, [$language => true]) as $other) self::assertStringNotContainsString($other, $html, $file . ' ' . $language);
            }
        }
    }

    public function testSceneTakesTheHudWordsFromThePage(): void {
        $scene = $this->repo(self::SCENE);
        // A cena so escreve texto (nunca HTML) e corta o que vier comprido demais.
        self::assertSame(1, preg_match('/function themeText\(node, name, fallback\) \{[^}]*node\.getAttribute\(name\)[^}]*text\.length <= 120 \? text : fallback;\s*\}/s', $scene));
        self::assertStringNotContainsString('innerHTML', $scene);
        $hooks = [
            'data-du-rotulo' => 'theme_text.hud_phase',
            'data-du-msg-inicio' => 'theme_text.toast_start',
            'data-du-msg-orbe' => 'theme_text.toast_orb',
            'data-du-msg-todos' => 'theme_text.toast_all_orbs',
        ];
        foreach ([self::BO_PACKAGE, self::BO_CONFIG] as $file) {
            $templates = (array)$this->config($file)['templates'];
            $header = (string)$templates['partial/site_header'];
            foreach ($hooks as $attr => $text) {
                self::assertStringContainsString("'" . $attr . "'", $scene, $attr);
                self::assertSame(1, substr_count($header, ' ' . $attr . '="{{ ' . $text . ' }}"'), $file . ' ' . $attr);
            }
            // Cada fase da home diz o proprio nome a cena, no idioma da pagina.
            self::assertStringContainsString("'data-du-fase-nome'", $scene);
            $named = [];
            foreach ($templates as $key => $source) {
                if ($key === 'partial/hero-3d') continue; // mesma secao do `partial/hero`
                preg_match_all('/<section id="(fase-0\d)(?:-[a-z]+)?"[^>]* data-du-fase-nome="\{\{ theme_text\.(phase_0\d[a-z_]*) \}\}"/', (string)$source, $m, PREG_SET_ORDER);
                foreach ($m as $hit) {
                    self::assertStringStartsWith('phase_' . substr($hit[1], 5), $hit[2], $file . ' ' . $key);
                    $named[] = $hit[1];
                }
                // Nenhuma secao de fase sem nome para a cena.
                self::assertSame(count($m), preg_match_all('/<section id="fase-/', (string)$source), $file . ' ' . $key);
            }
            sort($named);
            self::assertSame(['fase-00', 'fase-01', 'fase-02', 'fase-03', 'fase-04', 'fase-05', 'fase-06', 'fase-07', 'fase-07', 'fase-08'], $named, $file);
        }
        // O pacote sem a cena real nao carrega os avisos dela.
        foreach ((array)$this->config(self::UNIVERSE)['templates'] as $key => $source) {
            self::assertStringNotContainsString('data-du-msg-', (string)$source, (string)$key);
        }
    }

    public function testSceneKeepsTheOldWordsWhenThePageSaysNothing(): void {
        // Tema antigo (sem os atributos) continua a mostrar o HUD como antes.
        $scene = $this->repo(self::SCENE);
        foreach (['FASE', 'Orbe coletado', 'Todos os orbes coletados', 'Mova o mouse para pilotar · Role para voar'] as $fallback) {
            self::assertStringContainsString("'" . $fallback . "'", $scene, $fallback);
        }
    }

    public function testSkillChipsOfTheHomeComeFromTheLanguageFile(): void {
        $counts = [];
        $lists = [];
        foreach (self::LANGUAGES as $language) {
            $load = static function (string $file): array {
                $_ = [];
                require $file;

                return $_;
            };
            $texts = $load(DIR_REAMUR . 'dev/catalog/language/' . $language . '/common/home.php');
            self::assertIsString($texts['text_skill_chips'] ?? null, $language);
            $chips = explode('|', $texts['text_skill_chips']);
            foreach ($chips as $chip) self::assertNotSame('', trim($chip), $language);
            $counts[$language] = count($chips);
            $lists[$language] = $texts['text_skill_chips'];
        }
        self::assertSame(['en-us' => 6, 'pt-br' => 6, 'es-eu' => 6], $counts);
        self::assertCount(3, array_unique($lists));

        $controller = $this->repo('dev/catalog/controller/common/home.php');
        self::assertStringContainsString("\$this->language->get('text_skill_chips')", $controller);
        // Arquivo de idioma antigo (sem a chave): language->get() devolve o nome da chave e vale a lista de antes.
        self::assertStringContainsString("\$skillChips === 'text_skill_chips'", $controller);
    }
}
