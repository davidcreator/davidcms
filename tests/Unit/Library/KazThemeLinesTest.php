<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Theme\ThemeStrings;

/**
 * KZ-24 (ADR 0117): texto do tema com quebra de linha no KAZEditor-Theme.
 *
 * O servidor ja aceitava \n em ThemeStrings; esta etapa libera o editor. Este
 * teste amarra os dois lados: o servidor continua aceitando quebra de linha (com
 * o teto de 2000 caracteres) e recusando controle (menos \n), e o contrato
 * estatico do editor (themeClean preserva \n, THEME_REF aceita nl2br, themeWhy
 * nao recusa mais, pre-line na previa, Enter quebra na caixa).
 */
final class KazThemeLinesTest extends TestCase {
    private const ROOT = __DIR__ . '/../../../..';

    private function read(string $rel): string {
        $p = self::ROOT . '/' . $rel;
        self::assertFileExists($p, $rel);
        return (string) file_get_contents($p);
    }

    public function testServidorAceitaQuebraDeLinha(): void {
        self::assertTrue(ThemeStrings::isText("linha um\nlinha dois"));
        self::assertTrue(ThemeStrings::isText(str_repeat("\n", 2000)));
        self::assertFalse(ThemeStrings::isText(str_repeat("\n", 2001)));
        self::assertFalse(ThemeStrings::isText("a\tb"));   // tab e controle: recusado
        self::assertFalse(ThemeStrings::isText("a\u{7}b")); // controle: recusado
    }

    public function testEditorPreservaQuebra(): void {
        $js = $this->read('dev/admin/view/js/kaz-canvas.js');
        self::assertStringContainsString('KZ-24 (ADR 0117)', $js);
        self::assertStringContainsString("(upper|lower|nl2br)", $js);
        self::assertStringNotContainsString("return 'theme_lines'", $js);
        self::assertStringContainsString("replace(/\\r\\n?/g, '\\n')", $js);
        self::assertStringContainsString("'white-space', 'pre-line'", $js);
        self::assertStringContainsString('inline.theme && !ev.ctrlKey && !ev.metaKey', $js);
    }

    public function testChaveDeAjudaNosTresIdiomas(): void {
        foreach (['pt-br', 'en-us', 'es-eu'] as $lang) {
            $php = $this->read('dev/admin/language/' . $lang . '/design/theme.php');
            self::assertStringContainsString("text_canvas_theme_lines_help'", $php, $lang);
        }
    }
}
