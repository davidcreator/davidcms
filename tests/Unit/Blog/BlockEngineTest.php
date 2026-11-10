<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Blog;

use PHPUnit\Framework\TestCase;
use Reamur\System\Library\Blog\BlockDocument;
use Reamur\System\Library\Blog\BlockRenderer;

final class BlockEngineTest extends TestCase {
    public function testLegacyConversionFixtures(): void {
        $fixtures = json_decode((string)file_get_contents(dirname(__DIR__, 2) . '/fixtures/legacy-conversion.json'), true);
        self::assertIsArray($fixtures);

        $converter = new BlockDocument();

        foreach ($fixtures as $fixture) {
            $id     = (string)$fixture['id'];
            $result = $converter->convertLegacy((string)$fixture['html']);
            self::assertSame($fixture['expected_status'], $result['status'], $id);

            $types = [];
            if (is_array($result['document'])) {
                foreach ($result['document']['blocks'] as $block) {
                    $types[] = $block['type'];
                }
            }
            self::assertSame($fixture['expected_types'], $types, $id);

            if ($result['status'] === 'review_required') {
                self::assertSame($fixture['html'], $result['source_html'], $id);
                self::assertNull($result['document'], $id);
            }

            if ($result['status'] === 'converted') {
                if (isset($fixture['expected_content']) && is_array($fixture['expected_content'])) {
                    foreach ($fixture['expected_content'] as $i => $text) {
                        self::assertSame($text, $result['document']['blocks'][$i]['content'] ?? null, $id);
                    }
                }

                $json      = $converter->serializeDocument($result['document']);
                $roundTrip = $converter->deserializeDocument($json);
                self::assertTrue($roundTrip['valid'], $id);
                self::assertSame($result['document'], $roundTrip['document'], $id);

                $again = $converter->convertLegacy((string)$fixture['html']);
                self::assertSame($json, $converter->serializeDocument($again['document']), $id);
            }
        }
    }

    public function testRendererFixtures(): void {
        $fixtures = json_decode((string)file_get_contents(dirname(__DIR__, 2) . '/fixtures/renderer.json'), true);
        self::assertIsArray($fixtures);

        $renderer = new BlockRenderer();

        foreach ($fixtures as $fixture) {
            $id     = (string)$fixture['id'];
            $json   = (string)json_encode($fixture['document'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $result = $renderer->renderJson($json, (int)$fixture['stored_version']);

            self::assertSame((bool)$fixture['valid'], (bool)$result['valid'], $id);
            if (!$fixture['valid']) {
                self::assertSame('', $result['html'], $id);
            }
            if (isset($fixture['contains'])) {
                self::assertStringContainsString((string)$fixture['contains'], $result['html'], $id);
            }
            foreach ((array)($fixture['contains_all'] ?? []) as $fragment) {
                self::assertStringContainsString((string)$fragment, $result['html'], $id);
            }
            if (isset($fixture['not_contains'])) {
                self::assertStringNotContainsString((string)$fixture['not_contains'], $result['html'], $id);
            }
        }
    }
}
