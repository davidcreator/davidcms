<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

require_once __DIR__ . '/ExtensionTestCase.php';

use Reamur\System\Library\Extension\Manager;
use Reamur\System\Library\Extension\Manifest;
use Reamur\System\Library\Extension\PageOutput;

/**
 * EX-01 (ADR 0095): os exemplos de docs/guias/Guia-Extensoes.md passam pelo
 * codigo de verdade. Quem copia um exemplo do guia recebe algo que funciona;
 * se o manifesto mudar e o guia ficar para tras, este teste acusa.
 */
final class GuideTest extends ExtensionTestCase {
    private const GUIDE = '/docs/guias/Guia-Extensoes.md';

    private static function guide(): string {
        return (string)file_get_contents(dirname(__DIR__, 3) . self::GUIDE);
    }

    /** @return list<string> */
    private static function blocks(string $language): array {
        preg_match_all('/^ {0,3}```' . $language . '\n(.*?)^ {0,3}```$/ms', self::guide(), $m);

        return $m[1];
    }

    /** @return array<string, mixed> o exemplo minimo do guia, ja lido */
    private static function minimal(): array {
        foreach (self::blocks('json') as $block) {
            if (str_contains($block, '"schema"')) {
                return (array)json_decode($block, true, 16, JSON_THROW_ON_ERROR);
            }
        }

        self::fail('o guia perdeu o extension.json minimo');
    }

    public function testEveryJsonSampleIsAcceptedByTheManifest(): void {
        $base = self::minimal();
        $code = (string)$base['code'];
        $seen = [];

        foreach (self::blocks('json') as $block) {
            $block = trim($block);

            if (str_starts_with($block, '{') && str_contains($block, '"schema"')) {
                $raw    = $base;
                $seen[] = 'manifest';
            } elseif (str_starts_with($block, '{')) {
                // Um item solto de "events".
                $raw    = ['events' => [json_decode($block, true, 16, JSON_THROW_ON_ERROR)], 'apps' => ['catalog', 'admin']] + $base;
                $seen[] = 'event';
            } else {
                // Um trecho "chave": valor do manifesto.
                $part   = (array)json_decode('{' . $block . '}', true, 16, JSON_THROW_ON_ERROR);
                $raw    = $part + $base;
                $seen[] = (string)array_key_first($part);
            }

            $manifest = Manifest::parse((string)json_encode($raw), $code);

            self::assertSame($code, $manifest->code());
        }

        sort($seen);

        self::assertSame(['csp', 'event', 'manifest', 'settings'], $seen);
    }

    public function testTheSettingsSampleBehavesAsTheGuideSays(): void {
        $base = self::minimal();

        foreach (self::blocks('json') as $block) {
            if (!str_starts_with(trim($block), '"settings"')) {
                continue;
            }

            $manifest = Manifest::parse((string)json_encode((array)json_decode('{' . $block . '}', true) + $base), (string)$base['code']);

            self::assertSame(['id' => '', 'modo' => 'basico', 'depurar' => false], $manifest->defaults());
            self::assertSame(['token'], $manifest->secretKeys());

            // "required": true impede ligar enquanto o campo estiver vazio.
            self::assertSame(['id'], $manifest->missing([], []));
            self::assertSame([], $manifest->missing(['id' => 'G-ABCD1234'], []));

            // Valor gravado que nao passa no pattern chega a extensao como o padrao.
            self::assertSame('', $manifest->resolve(['id' => 'G-<script>'])['id']);
            self::assertSame('G-ABCD1234', $manifest->resolve(['id' => 'G-ABCD1234'])['id']);

            return;
        }

        self::fail('o guia perdeu o exemplo de settings');
    }

    public function testThePhpSamplesRunAndProduceWhatTheGuideShows(): void {
        $php = self::blocks('php');
        $all = implode("\n", $php);

        self::assertStringContainsString("define('REAMUR_EXTENSIONS', false);", $all);

        // 1) O extension.php minimo, com o extension.json minimo, ligado de verdade.
        $base  = self::minimal();
        $code  = (string)$base['code'];
        $entry = '';

        foreach ($php as $block) {
            if (str_contains($block, 'namespace Reamur\\Extension\\')) {
                $entry = $block;
            }
        }

        self::assertStringContainsString('namespace ' . substr(Manifest::className($code), 0, -strlen('\\Extension')) . ';', $entry);

        $folder = $this->folder();
        mkdir($folder . $code);
        file_put_contents($folder . $code . '/' . Manifest::FILE, (string)json_encode($base));
        file_put_contents($folder . $code . '/' . Manifest::ENTRY, $entry);

        [$registry, $db, $log] = $this->registry('catalog');
        $db->put($code . '.enabled', '1');

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        self::assertSame([$code], $manager->running());

        $page = new PageOutput('common/home', 'pt-br');
        $args = [$page];
        $registry->get('event')->trigger(Manager::EVENT_PAGE_OUTPUT, $args);

        self::assertSame('<meta name="exemplo" content="valor">' . "\n", $page->render(PageOutput::HEAD, 'N'));
        self::assertSame('', $log->all());

        // 2) O exemplo de saida (§7): meta, script por endereco e script embutido com js().
        $method = '';

        foreach ($php as $block) {
            if (str_contains($block, 'scriptSrc(') && str_contains($block, 'PageOutput::js(')) {
                $method = $block;
            }
        }

        self::assertNotSame('', $method, 'o guia perdeu o exemplo de saida');

        $code2 = self::code();
        $this->install($folder, $code2, self::manifest($code2, [
            'settings' => [['key' => 'id', 'type' => 'text', 'label' => 'ID']],
            'events'   => [['trigger' => 'page/output', 'handler' => 'onPageOutput']],
            'csp'      => ['script-src' => ['https://cdn.exemplo.com']],
        ]), null);

        // O trecho do guia e so o metodo; a moldura (namespace, use, classe) e a do exemplo minimo.
        file_put_contents($folder . $code2 . '/' . Manifest::ENTRY, "<?php declare(strict_types=1);\nnamespace "
            . substr(Manifest::className($code2), 0, -strlen('\\Extension')) . ";\n\nuse Reamur\\System\\Library\\Extension\\PageOutput;\n\n"
            . "final class Extension extends \\Reamur\\System\\Library\\Extension\\Extension {\n" . $method . "}\n");

        [$registry, $db, $log] = $this->registry('catalog');
        $db->put($code2 . '.enabled', '1');
        $db->put($code2 . '.config', (string)json_encode(['id' => 'a"</script>b']));

        $this->manager($registry, $folder)->boot('catalog');

        $page = new PageOutput('common/home', 'pt-br');
        $args = [$page];
        $registry->get('event')->trigger(Manager::EVENT_PAGE_OUTPUT, $args);
        $html = $page->render(PageOutput::HEAD, 'N0nce');

        self::assertSame('', $log->all());
        self::assertSame([], $page->rejected());
        self::assertStringContainsString('<meta name="exemplo-verificacao" content="a&quot;&lt;/script&gt;b">', $html);
        self::assertStringContainsString('<script src="https://cdn.exemplo.com/lib.js" async></script>', $html);
        self::assertStringContainsString('<script nonce="N0nce">window.exemploId = "a\u0022\u003C/script\u003Eb";</script>', $html);
    }

    public function testNumbersQuotedInTheGuideAreTheOnesInTheCode(): void {
        $guide = self::guide();

        self::assertStringContainsString('Até ' . Manifest::MAX_SETTINGS . ' |', $guide);
        self::assertStringContainsString('Até ' . Manifest::MAX_EVENTS . ' |', $guide);
        self::assertStringContainsString('Até ' . Manifest::MAX_SOURCES . ' por tipo', $guide);
        self::assertStringContainsString('Tamanho máximo: ' . intdiv(Manifest::MAX_BYTES, 1024) . ' KB', $guide);
        self::assertStringContainsString('Até ' . PageOutput::MAX_INLINE . ' bytes', $guide);
        self::assertStringContainsString('Limite de ' . PageOutput::MAX_ITEMS . ' itens por página', $guide);

        foreach (\Reamur\System\Library\Security\Csp::EXTRA_DIRECTIVES as $directive) {
            self::assertStringContainsString('`' . $directive . '`', $guide);
        }

        foreach (Manifest::TYPES as $type) {
            self::assertStringContainsString('`' . $type . '`', $guide);
        }

        // Os nomes de pasta que o guia da como bons e ruins.
        self::assertTrue(Manifest::isCode('ga4_tags'));
        self::assertTrue(Manifest::isCode('site_verification'));
        self::assertTrue(Manifest::isCode('minha_extensao'));
        self::assertFalse(Manifest::isCode('ga_4tags'));
        self::assertFalse(Manifest::isCode('a_b_c'));
    }
}
