<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

/**
 * KZ-12 (ADR 0105): as duas telas cheias do editor de tema.
 *  - "Tela cheia em nova pagina" (`kaz_fullscreen=1`) abria uma pagina sem
 *    folha de estilo e sem script: o modelo deixava de incluir o cabecalho do
 *    Admin, que e quem os carrega. O editor nao iniciava.
 *  - Nas duas telas cheias sobrava no pe da janela uma faixa de 104px com o
 *    Admin: a regra de altura do editor comum tinha o mesmo peso da regra de
 *    tela cheia e vinha depois dela na folha.
 *  - O menu, a barra e o rodape do Admin ficavam atras do editor, e o Tab
 *    passava por eles.
 * Os testes leem os arquivos; o comportamento na tela e conferido pelo roteiro
 * de navegador da etapa.
 */
final class KazFullscreenTest extends TestCase {
    private const FORM = 'dev/admin/view/template/design/theme_form.twig';
    private const CONTROLLER = 'dev/admin/controller/design/theme.php';
    private const CSS = 'dev/admin/view/css/kaz-theme-editor.css';
    private const SCRIPT = 'dev/admin/view/js/kaz-theme-editor.js';
    private const CHROME = ['.admin-sidebar', '.admin-topbar', '.site-footer', '.sidebar-backdrop'];

    private function repo(string $path): string {
        $source = file_get_contents(DIR_REAMUR . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    /**
     * Regras da folha do editor: cada seletor com as declaracoes do bloco dele.
     * As regras de dentro de `@media` entram tambem (a largura da janela nao
     * muda o peso do seletor).
     *
     * @return list<array{selector:string,body:string}>
     */
    private function rules(): array {
        $css = (string)preg_replace('~/\*.*?\*/~s', '', $this->repo(self::CSS));
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $blocks, PREG_SET_ORDER);
        $out = [];
        foreach ($blocks as $block) {
            foreach (explode(',', $block[1]) as $selector) {
                $selector = trim((string)preg_replace('/\s+/', ' ', $selector));
                if ($selector === '' || $selector[0] === '@') continue;
                $out[] = ['selector' => $selector, 'body' => $block[2]];
            }
        }
        self::assertGreaterThan(400, count($out), 'a folha foi lida');

        return $out;
    }

    /** Peso do seletor: ids valem 100, classes, atributos e pseudoclasses 1 (a folha nao usa mais que isso). */
    private function weight(string $selector): int {
        $plain = (string)preg_replace('/"[^"]*"/', '""', $selector);

        return 100 * preg_match_all('/#[a-zA-Z]/', $plain)
            + preg_match_all('/\.[a-zA-Z]/', $plain)
            + preg_match_all('/\[/', $plain)
            + preg_match_all('/(?<!:):(?!:)[a-z]/', $plain);
    }

    public function testTheFullPageKeepsTheAdminHeaderAndFooter(): void {
        $form = (string)preg_replace('/\{#.*?#\}/s', '', $this->repo(self::FORM));
        // Sem condicao nenhuma: cabecalho, editor, rodape.
        self::assertSame("{{ header|raw }}\n{% include 'design/kaz_theme_editor.twig' %}\n{{ footer|raw }}", trim($form));
        self::assertStringNotContainsString('{% if', $form);

        $controller = $this->repo(self::CONTROLLER);
        self::assertStringContainsString("\$data['kaz_fullscreen_page']=(\$this->queryValue('kaz_fullscreen')==='1');", $controller);
        // A classe do <body> so existe na pagina cheia; na tela normal o Admin fica como sempre.
        self::assertStringContainsString("\$this->adminChrome(\$data, \\Reamur\\Admin\\Controller\\Common\\Header::ASSET_V, \$data['kaz_fullscreen_page'] ? 'kaz-page-fullscreen' : null);", $controller);
        self::assertSame(1, substr_count($controller, "'kaz-page-fullscreen'"));
        // Folha e script mudaram: o endereco muda junto.
        self::assertStringContainsString("'-kaz-theme-editor-kz12'", $controller);
    }

    public function testTheFullScreenHeightBeatsTheOrdinaryHeightOfTheEditor(): void {
        $ordinary = 0;
        $full = ['data-kaz-fullscreen="1"' => 0, 'data-kaz-page-mode="fullscreen"' => 0];
        $seen = [];
        foreach ($this->rules() as $rule) {
            if (preg_match('/(^|;)\s*height\s*:/', $rule['body']) !== 1) continue;
            // So interessa a regra cujo alvo e o proprio editor (.kaz-shell no fim do seletor).
            $parts = preg_split('/[ >+~]+/', $rule['selector']) ?: [];
            $subject = (string)end($parts);
            if (!str_contains($subject, '.kaz-shell')) continue;
            $seen[] = $rule['selector'];
            $weight = $this->weight($rule['selector']);
            $mode = null;
            foreach (array_keys($full) as $marker) if (str_contains($subject, $marker)) $mode = $marker;
            if ($mode === null) {
                $ordinary = max($ordinary, $weight);
                continue;
            }
            preg_match_all('/(?:^|;)\s*height\s*:\s*([^;]+)/', $rule['body'], $heights);
            // Vale a ultima declaracao do bloco (100dvh onde o navegador conhece, 100vh nos demais).
            self::assertContains(trim((string)end($heights[1])), ['100vh', '100dvh'], $rule['selector'] . ': a tela cheia tem a altura da janela');
            $full[$mode] = max($full[$mode], $weight);
        }
        self::assertGreaterThanOrEqual(4, count($seen), implode(' | ', $seen));
        self::assertGreaterThan(0, $ordinary, 'a regra de altura do editor comum foi encontrada');
        foreach ($full as $marker => $weight) {
            // Peso MAIOR, nao igual: com peso igual ganha quem vem depois na folha, e foi assim que sobrou a faixa.
            self::assertGreaterThan($ordinary, $weight, '[' . $marker . ']: a altura de tela cheia precisa pesar mais que a do editor comum (' . $ordinary . ')');
        }
        // O medidor de peso mede mesmo.
        self::assertSame(2, $this->weight('.kaz-shell.kaz-card'));
        self::assertSame(3, $this->weight('.kaz-shell.kaz-card[data-kaz-fullscreen="1"]'));
        self::assertSame(102, $this->weight('.kaz-shell > form#kaz-theme-form:hover'));
        self::assertSame(1, $this->weight('body.kaz-x::before'));
    }

    public function testTheAdminBehindTheEditorLeavesTheScreenInBothModes(): void {
        $hidden = [];
        $locked = [];
        foreach ($this->rules() as $rule) {
            if (preg_match('/(^|;)\s*display\s*:\s*none\s*(;|$)/', $rule['body']) === 1) $hidden[$rule['selector']] = true;
            if (preg_match('/(^|;)\s*overflow\s*:\s*hidden\s*(;|$)/', $rule['body']) === 1) $locked[$rule['selector']] = true;
        }
        foreach (['kaz-page-fullscreen', 'kaz-tab-fullscreen'] as $class) {
            foreach (self::CHROME as $part) {
                self::assertArrayHasKey('body.' . $class . ' ' . $part, $hidden, 'falta esconder ' . $part . ' com ' . $class);
            }
            self::assertArrayHasKey('body.' . $class, $locked, 'a pagina de tras nao rola com ' . $class);
        }
        // Fora da tela cheia nada disso vale: toda regra que esconde uma parte do Admin exige uma das duas classes.
        foreach (array_keys($hidden) as $selector) {
            foreach (self::CHROME as $part) {
                if (!str_contains($selector, $part)) continue;
                self::assertMatchesRegularExpression('/^body\.kaz-(page|tab)-fullscreen /', $selector, $selector);
            }
        }

        // A classe da tela cheia "nesta aba" e posta e retirada pelo script, junto com o atributo do editor.
        $script = $this->repo(self::SCRIPT);
        self::assertStringContainsString("root.setAttribute('data-kaz-fullscreen', fullscreenLocal ? '1' : '0');", $script);
        self::assertStringContainsString("if (document.body) document.body.classList.toggle('kaz-tab-fullscreen', fullscreenLocal);", $script);
        self::assertSame(1, substr_count($script, 'kaz-tab-fullscreen'));
        self::assertStringNotContainsString('kaz-page-fullscreen', $script, 'a classe da pagina cheia vem do servidor');
    }
}
