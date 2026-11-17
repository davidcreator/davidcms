<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

/**
 * F918-06 (P1, D07=B): o template publico do blog imprime post.block_html sem |raw.
 * Se o controller entregasse string, o Twig escaparia o HTML do renderer e o
 * conteudo sairia como texto (regressao funcional). Contrato: o controller entrega
 * Twig\Markup (o HTML vem do BlockRenderer allowlistado) e o template NAO usa |raw.
 */
final class BlogBlockHtmlMarkupTest extends TestCase {
    private const ROOT = __DIR__ . '/../../..';

    private function read(string $rel): string {
        $p = self::ROOT . '/' . $rel;
        self::assertFileExists($p, $rel);
        return (string) file_get_contents($p);
    }

    public function testControllerDeliversBlockHtmlAsTwigMarkup(): void {
        $c = $this->read('dev/catalog/controller/blog/blog.php');
        self::assertStringContainsString('use Twig\\Markup;', $c);
        self::assertStringContainsString("new Markup(\$blockHtml, 'UTF-8')", $c);
    }

    public function testTemplateDoesNotUseRaw(): void {
        $t = $this->read('dev/catalog/view/template/blog/blog_info.twig');
        self::assertStringNotContainsString('|raw', $t);
        self::assertStringContainsString('{{ post.block_html }}', $t);
    }

    public function testF918FixturesCoverFailClosedAndImageBase(): void {
        $f = $this->read('tests/fixtures/f918_regressions.json');
        self::assertStringContainsString('query_error_true_and_public_blocks_branch_fail_closed', $f);
        self::assertStringContainsString('expected_src', $f);
    }
}
