<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

/**
 * KZ-23 (ADR 0116): texto do tema dentro de atributo no KAZEditor-Theme.
 *
 * O lado do servidor nao muda (ThemeStrings continua o mesmo de KZ-19/KZ-21):
 * a mudanca e toda no editor (kaz-element-attrs.js + kaz-canvas.js). Este teste
 * amarra o contrato estatico: as regras de atributo que contam como "texto do
 * tema editavel" existem no JS, o bloco KZ-23 e a delegacao existem no canvas,
 * e as chaves novas de idioma existem nos tres idiomas do admin.
 */
final class KazThemeAttrTest extends TestCase {
    private const ROOT = __DIR__ . '/../../../..';

    private function read(string $rel): string {
        $p = self::ROOT . '/' . $rel;
        self::assertFileExists($p, $rel);
        return (string) file_get_contents($p);
    }

    public function testRegrasDeAtributoComTextoDoTema(): void {
        $js = $this->read('dev/admin/view/js/kaz-element-attrs.js');
        self::assertStringContainsString('KZ-23 (ADR 0116)', $js);
        self::assertStringContainsString('function themeAttr(', $js);
        self::assertStringContainsString('function themeAttrName(', $js);
        self::assertStringContainsString("['alt', 'aria-label', 'placeholder']", $js);
        self::assertStringContainsString("themeAttr: themeAttr", $js);
        /* A lista so tem os atributos permitidos e o prefixo da cena 3D. */
        self::assertStringContainsString("/^data-du-[a-z0-9-]+$/", $js);
    }

    public function testBlocoKZ23NoCanvas(): void {
        $js = $this->read('dev/admin/view/js/kaz-canvas.js');
        self::assertStringContainsString('KZ-23 (ADR 0116): texto do tema dentro de atributo', $js);
        self::assertStringContainsString('function setThemeAttr(', $js);
        self::assertStringContainsString('function onThemeAttrInput(', $js);
        self::assertStringContainsString('function paintThemeAttrField(', $js);
        self::assertStringContainsString("data-kaz-canvas-theme-attr", $js);
        /* A escrita vai para o JSON dos textos, nao para a fonte do tema. */
        self::assertStringContainsString('themeCommit(field, res.json)', $js);
    }

    public function testChavesDeIdiomaNosTresIdiomas(): void {
        foreach (['pt-br', 'en-us', 'es-eu'] as $lang) {
            $php = $this->read('dev/admin/language/' . $lang . '/design/theme.php');
            self::assertStringContainsString("text_canvas_theme_attr'", $php, $lang);
            self::assertStringContainsString("text_canvas_theme_attr_help'", $php, $lang);
        }
    }
}
