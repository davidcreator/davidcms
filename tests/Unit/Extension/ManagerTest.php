<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

require_once __DIR__ . '/ExtensionTestCase.php';

use Reamur\System\Engine\Config;
use Reamur\System\Engine\Event;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Extension\Manager;
use Reamur\System\Library\Extension\ManifestException;
use Reamur\System\Library\Extension\PageOutput;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\Csp;
use Reamur\System\Library\Security\SecretBox;

/**
 * EX-01 (ADR 0095): o Manager. O que se garante aqui: extensao desligada nao
 * tem o PHP carregado; a ligada so entra inteira; e erro de extensao vai para
 * o log, nunca derruba a pagina.
 */
final class ManagerTest extends ExtensionTestCase {
    private const KEY   = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';
    private const OTHER = 'ZmVkY2JhOTg3NjU0MzIxMGZlZGNiYTk4NzY1NDMyMTA=';

    private const ON_PAGE = ['events' => [['trigger' => 'page/output', 'handler' => 'onPage']]];

    /** Dispara page/output como o cabecalho publico faz. */
    private static function pageOutput(Registry $registry): PageOutput {
        $page = new PageOutput('common/home', 'pt-br');
        $args = [$page];
        $registry->get('event')->trigger(Manager::EVENT_PAGE_OUTPUT, $args);

        return $page;
    }

    // ── chave geral ─────────────────────────────────────────────────────────

    public function testSwitchValues(): void {
        foreach ([false, 0, '0', 'off', 'OFF', ' false ', 'no', 'No'] as $value) {
            self::assertTrue(Manager::switchedOff($value), var_export($value, true));
        }

        foreach ([true, 1, '1', 'on', 'yes', '', null, [], 'desligado', 0.0] as $value) {
            self::assertFalse(Manager::switchedOff($value), var_export($value, true));
        }
    }

    public function testMasterSwitchStartsNothingAndLoadsNoExtensionCode(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::ON_PAGE), 'public function onPage($p): void {}');

        [$registry, $db] = $this->registry();
        $registry->get('config')->set('extension_off', true);
        $db->put($code . '.enabled', '1');

        $manager = $this->manager($registry, $folder);

        self::assertTrue($manager->isOff());
        $manager->boot('catalog');

        self::assertSame([], $manager->running());
        self::assertFalse(class_exists(self::classOf($code), false), 'o PHP da extensao nao foi carregado');
        self::assertFalse($registry->get('event')->has('page/output'));
        self::assertSame([], $db->sql, 'nem o estado e lido');
    }

    public function testIsOnWithoutConfigService(): void {
        self::assertFalse((new Manager(new Registry(), '/nao/existe', '1.0.0'))->isOff());
    }

    // ── descoberta (tela do admin) ──────────────────────────────────────────

    public function testDiscoverReadsOnlyTheJsonFiles(): void {
        $folder = $this->folder();
        $ok     = self::code();
        $bad    = self::code();

        // Se este arquivo fosse incluido, o teste morreria aqui.
        $this->install($folder, $ok, self::manifest($ok), null);
        file_put_contents($folder . $ok . '/extension.php', '<?php throw new \RuntimeException("o PHP da extensao foi carregado pela listagem");');
        $this->install($folder, $bad, '{"schema": 1, "code": "' . $bad . '"', null);
        $this->install($folder, 'Nome-Ruim', self::manifest('x'), null);
        mkdir($folder . 'sem_manifesto');
        mkdir($folder . '.oculta');
        file_put_contents($folder . '.oculta/extension.json', '{}');
        file_put_contents($folder . 'index.html', '');
        file_put_contents($folder . 'extension.json', '{}');

        [$registry] = $this->registry('admin');
        $found      = $this->manager($registry, $folder)->discover();

        self::assertSame(['Nome-Ruim', $ok, $bad], array_keys($found), 'em ordem de nome de pasta');
        self::assertSame($ok, $found[$ok]['manifest']->code());
        self::assertSame([], $found[$ok]['problems']);
        self::assertNull($found[$bad]['manifest']);
        self::assertStringContainsString('not valid JSON', $found[$bad]['problems'][0]);
        self::assertNull($found['Nome-Ruim']['manifest']);
        self::assertStringContainsString('folder name', $found['Nome-Ruim']['problems'][0]);
    }

    public function testDiscoverStopsAtTheFolderCeiling(): void {
        $folder = $this->folder();

        for ($i = 0; $i < Manager::MAX_FOLDERS + 5; $i++) {
            mkdir($folder . 'p' . $i);
            file_put_contents($folder . 'p' . $i . '/extension.json', '{}');
        }

        [$registry] = $this->registry('admin');

        self::assertCount(Manager::MAX_FOLDERS, $this->manager($registry, $folder)->discover());
    }

    public function testDiscoverWithoutFolderIsEmpty(): void {
        [$registry] = $this->registry('admin');

        self::assertSame([], $this->manager($registry, '')->discover());
        self::assertSame([], $this->manager($registry, '/nao/existe/mesmo')->discover());
        self::assertSame('', $this->manager($registry, '')->directory());
        self::assertSame('/a/b/', $this->manager($registry, '/a/b')->directory());
        self::assertSame('/a/b/', $this->manager($registry, '/a/b//')->directory());
    }

    public function testManifestWithoutFolderConfigured(): void {
        [$registry] = $this->registry('admin');

        $this->expectException(ManifestException::class);
        $this->expectExceptionMessage('DIR_EXTENSION');
        $this->manager($registry, '')->manifest('demo');
    }

    public function testBlockersSayWhyItCannotBeTurnedOn(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, [
            'requires' => ['reamur' => '>=2.0.0'],
            'settings' => [
                ['key' => 'id', 'type' => 'text', 'label' => 'ID', 'required' => true],
                ['key' => 'token', 'type' => 'secret', 'label' => 'T', 'required' => true],
            ],
        ]), null);

        [$registry] = $this->registry('admin');
        $manager    = $this->manager($registry, $folder, '1.4.0', '8.2.0');
        $manifest   = $manager->manifest($code);

        self::assertSame(
            ['reamur >=2.0.0 (this site: 1.4.0)', 'extension.php: file not found', 'setting "id" is required and empty', 'setting "token" is required and empty'],
            $manager->blockers($manifest)
        );

        file_put_contents($folder . $code . '/extension.php', '<?php throw new \RuntimeException("carregado");');

        self::assertSame([], $this->manager($registry, $folder, '2.0.0')->blockers($manifest, ['id' => 'x'], ['token']));
        self::assertSame('1.4.0', $manager->cmsVersion());
        self::assertSame('8.2.0', $manager->phpVersion());
    }

    // ── funcionamento ───────────────────────────────────────────────────────

    public function testDisabledExtensionIsNeverLoaded(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::ON_PAGE), 'public function onPage($p): void {}');

        [$registry, $db, $log] = $this->registry();
        $db->put($code . '.enabled', '0');
        $db->put($code . '.config', '{}');

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        self::assertSame([], $manager->running());
        self::assertSame([], $manager->failed());
        self::assertFalse(class_exists(self::classOf($code), false));
        self::assertSame([], $log->lines);
    }

    public function testFolderWithoutStateIsNotStarted(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::ON_PAGE), 'public function onPage($p): void {}');

        [$registry] = $this->registry();
        $manager    = $this->manager($registry, $folder);
        $manager->boot('catalog');

        self::assertSame([], $manager->running(), 'copiar a pasta para o servidor nao liga nada');
        self::assertFalse(class_exists(self::classOf($code), false));
    }

    public function testEnabledExtensionReceivesItsSettingsAndWritesToThePage(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::ON_PAGE + [
            'settings' => [
                ['key' => 'id', 'type' => 'text', 'label' => 'ID', 'pattern' => '[A-Z0-9-]+'],
                ['key' => 'debug', 'type' => 'checkbox', 'label' => 'D'],
                ['key' => 'mode', 'type' => 'select', 'label' => 'M', 'options' => [['value' => 'a', 'label' => 'A']], 'default' => 'a'],
            ],
        ]), <<<'PHP'
            public function onPage(\Reamur\System\Library\Extension\PageOutput $page): void {
                self::$seen = [$this->peek('id'), $this->peek('debug'), $this->peek('mode'), $this->peek('nada'), $this->peekApp(), $this->code(), $page->route()];
                $page->meta('x-id', (string)$this->peek('id'));
                $this->say("ola\ncom quebra");
            }
        PHP);

        [$registry, $db, $log] = $this->registry();
        $db->put($code . '.enabled', '1');
        $db->put($code . '.config', '{"id":"G-123","debug":true,"sobra":"x"}');

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        self::assertSame([$code], $manager->running());
        self::assertTrue($registry->get('event')->has('page/output'));

        $page  = self::pageOutput($registry);
        $class = self::classOf($code);

        self::assertSame(['G-123', true, 'a', '', 'catalog', $code, 'common/home'], $class::$seen);
        self::assertSame('<meta name="x-id" content="G-123">' . "\n", $page->render(PageOutput::HEAD, 'n'));
        self::assertSame(['extension ' . $code . ': ola com quebra'], $log->lines, 'log identificado e numa linha so');
    }

    public function testBootHappensOnlyOncePerRequest(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::ON_PAGE), 'public function onPage($p): void { self::$seen[] = 1; }');

        [$registry, $db] = $this->registry();
        $db->put($code . '.enabled', '1');

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');
        $manager->boot('catalog');
        $manager->boot('admin');

        self::pageOutput($registry);

        self::assertSame([1], (self::classOf($code))::$seen, 'um ouvinte so, nao dois');
    }

    public function testExtensionRunsOnlyInTheAppsItDeclares(): void {
        $folder  = $this->folder();
        $public  = self::code();
        $both    = self::code();
        $this->install($folder, $public, self::manifest($public, self::ON_PAGE), 'public function onPage($p): void {}');
        $this->install($folder, $both, self::manifest($both, self::ON_PAGE + ['apps' => ['catalog', 'admin']]), 'public function onPage($p): void {}');

        [$registry, $db] = $this->registry('admin');
        $db->put($public . '.enabled', '1');
        $db->put($both . '.enabled', '1');

        $manager = $this->manager($registry, $folder);
        $manager->boot('admin');

        self::assertSame([$both], $manager->running());
        self::assertFalse(class_exists(self::classOf($public), false), 'no admin, a extensao so-publica nem e carregada');
        self::assertSame([], $manager->failed());
    }

    public function testUnknownAppStartsNothing(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::ON_PAGE), 'public function onPage($p): void {}');

        [$registry, $db] = $this->registry();
        $db->put($code . '.enabled', '1');

        $manager = $this->manager($registry, $folder);
        $manager->boot('install');

        self::assertSame([], $manager->running());
        self::assertFalse(class_exists(self::classOf($code), false));
    }

    // ── o que nao entra ─────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: array<string, mixed>|string|null, 1: ?string, 2: string, 3?: array<string, mixed>}>
     *         manifesto (null = pasta ausente), corpo da classe (null = sem extension.php), trecho do log
     */
    public static function notStarted(): array {
        return [
            'pasta sumiu'            => [null, null, 'file not found'],
            'manifesto quebrado'     => ['{"schema":', '', 'not valid JSON'],
            'chave desconhecida'     => [['hack' => true], '', 'unknown key "hack"'],
            'cms antigo demais'      => [['requires' => ['reamur' => '>=9.0.0']], '', 'reamur >=9.0.0 (this site: 1.0.0)'],
            'php antigo demais'      => [['requires' => ['php' => '>=9.0']], '', 'php >=9.0 (this site: 8.2.0)'],
            'sem extension.php'      => [[], null, 'extension.php: file not found'],
            'obrigatorio vazio'      => [['settings' => [['key' => 'id', 'type' => 'text', 'label' => 'I', 'required' => true]]], '', 'setting "id" is required and empty'],
            'ouvinte nao existe'     => [['events' => [['trigger' => 'page/output', 'handler' => 'onNada']]], '', 'handler onNada is not a public method'],
            'ouvinte privado'        => [['events' => [['trigger' => 'page/output', 'handler' => 'onPage']]], 'private function onPage($p): void {}', 'handler onPage is not a public method'],
            'ouvinte estatico'       => [['events' => [['trigger' => 'page/output', 'handler' => 'onPage']]], 'public static function onPage($p): void {}', 'handler onPage is not a public method'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('notStarted')]
    public function testBrokenExtensionIsLoggedAndTheSiteGoesOn(array|string|null $manifest, ?string $body, string $expected): void {
        $folder = $this->folder();
        $code   = self::code();
        $good   = self::code();

        if ($manifest !== null) {
            $this->install($folder, $code, is_array($manifest) ? self::manifest($code, $manifest) : $manifest, $body);
        }

        $this->install($folder, $good, self::manifest($good, self::ON_PAGE), 'public function onPage($p): void { $p->meta("boa", "1"); }');

        [$registry, $db, $log] = $this->registry();
        $db->put($code . '.enabled', '1');
        $db->put($good . '.enabled', '1');

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        self::assertSame([$good], $manager->running(), 'a extensao boa segue funcionando');
        self::assertArrayHasKey($code, $manager->failed());
        self::assertStringContainsString($expected, $manager->failed()[$code]);
        self::assertCount(1, $log->lines);
        self::assertStringStartsWith('extension ' . $code . ': not started (', $log->lines[0]);
        self::assertStringContainsString($expected, $log->lines[0]);
        self::assertStringContainsString('name="boa"', self::pageOutput($registry)->render(PageOutput::HEAD, 'n'));
    }

    public function testEntryFileWithTheWrongClassIsNotStarted(): void {
        $folder = $this->folder();
        $wrong  = self::code();
        $loose  = self::code();
        $this->install($folder, $wrong, self::manifest($wrong), null);
        file_put_contents($folder . $wrong . '/extension.php', '<?php // nenhuma classe');
        $this->install($folder, $loose, self::manifest($loose), null);
        file_put_contents($folder . $loose . '/extension.php', '<?php namespace ' . substr(self::classOf($loose), 0, -10) . '; final class Extension {}');

        [$registry, $db, $log] = $this->registry();
        $db->put($wrong . '.enabled', '1');
        $db->put($loose . '.enabled', '1');

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        self::assertSame([], $manager->running());
        self::assertStringContainsString('must declare ' . self::classOf($wrong), $manager->failed()[$wrong]);
        self::assertStringContainsString('must declare ' . self::classOf($loose), $manager->failed()[$loose], 'classe que nao estende a base nao e aceita');
        self::assertCount(2, $log->lines);
    }

    public function testEntryFileThatFailsToLoadDoesNotBreakTheRequest(): void {
        $folder = $this->folder();
        $syntax = self::code();
        $throws = self::code();
        $this->install($folder, $syntax, self::manifest($syntax), null);
        file_put_contents($folder . $syntax . '/extension.php', '<?php this is not php');
        $this->install($folder, $throws, self::manifest($throws), null);
        file_put_contents($folder . $throws . '/extension.php', "<?php throw new \\DomainException(\"falha\\nao carregar\");");

        [$registry, $db, $log] = $this->registry();
        $db->put($syntax . '.enabled', '1');
        $db->put($throws . '.enabled', '1');

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        self::assertSame([], $manager->running());
        self::assertSame([$syntax, $throws], array_keys($manager->failed()));
        self::assertSame('falha ao carregar', $manager->failed()[$throws], 'motivo numa linha so');
        self::assertCount(2, $log->lines);
        self::assertStringNotContainsString("\n", $log->all() === '' ? '' : $log->lines[1]);
    }

    public function testEntryFileThatDoesNotStartWithThePhpTagIsNeverIncluded(): void {
        // Com BOM (ou qualquer texto) antes da abertura, o declare(strict_types=1) vira erro
        // fatal de compilacao, que derrubaria a pagina. O arquivo nem chega a ser incluido.
        $folder = $this->folder();
        $marker = $folder . 'incluido.txt';
        $cases  = ["\xEF\xBB\xBF", "\n", ' ', '<html>'];
        $codes  = [];

        [$registry, $db, $log] = $this->registry();

        foreach ($cases as $before) {
            $code    = self::code();
            $codes[] = $code;
            $this->install($folder, $code, self::manifest($code, self::ON_PAGE), null);
            file_put_contents(
                $folder . $code . '/extension.php',
                $before . '<?php file_put_contents(' . var_export($marker, true) . ", 'x');"
            );
            $db->put($code . '.enabled', '1');
        }

        $empty   = self::code();
        $codes[] = $empty;
        $this->install($folder, $empty, self::manifest($empty, self::ON_PAGE), null);
        file_put_contents($folder . $empty . '/extension.php', '');
        $db->put($empty . '.enabled', '1');

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        self::assertFileDoesNotExist($marker);
        self::assertSame([], $manager->running());
        self::assertSame($codes, array_keys($manager->failed()));

        foreach ($manager->failed() as $reason) {
            self::assertSame('extension.php must exist and start with "<?php" (no BOM, no text before it)', $reason);
        }

        self::assertCount(count($codes), $log->lines);
    }

    public function testStrayOutputOfTheEntryFileNeverReachesTheBrowser(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code), null);
        /* Texto ANTES da abertura do PHP nem compila num arquivo com namespace;
           o que pode acontecer e sobra depois do fechamento ou um echo solto. */
        file_put_contents($folder . $code . '/extension.php', self::source($code, '') . "\n?>\nlixo depois\n<?php echo 'mais lixo';");

        [$registry, $db] = $this->registry();
        $db->put($code . '.enabled', '1');

        $manager = $this->manager($registry, $folder);
        $level   = ob_get_level();

        ob_start();
        $manager->boot('catalog');
        $printed = (string)ob_get_clean();

        self::assertSame('', $printed);
        self::assertSame($level, ob_get_level(), 'os buffers ficam como estavam');
        self::assertSame([$code], $manager->running());
    }

    public function testAllOrNothingNoListenerIsRegisteredWhenOneIsWrong(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, ['events' => [
            ['trigger' => 'page/output', 'handler' => 'onPage'],
            ['trigger' => 'order_add', 'handler' => 'onFalta'],
        ]]), 'public function onPage($p): void {}');

        [$registry, $db] = $this->registry();
        $db->put($code . '.enabled', '1');

        $this->manager($registry, $folder)->boot('catalog');

        self::assertFalse($registry->get('event')->has('page/output'));
        self::assertFalse($registry->get('event')->has('order_add'));
    }

    public function testStateThatCannotBeReadStartsNothingAndIsLogged(): void {
        $folder = $this->folder();

        [$registry, $db, $log] = $this->registry();
        $db->fail = new \RuntimeException('tabela ausente');

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');

        self::assertSame([], $manager->running());
        self::assertSame(['extensions: state could not be read, none started (tabela ausente)'], $log->lines);
    }

    public function testWithoutFolderNothingIsReadFromTheDatabase(): void {
        [$registry, $db] = $this->registry();

        $this->manager($registry, '')->boot('catalog');

        self::assertSame([], $db->sql);
    }

    // ── erro de ouvinte ─────────────────────────────────────────────────────

    public function testListenerErrorGoesToTheLogAndTheOthersStillRun(): void {
        $folder = $this->folder();
        $bad    = self::code();
        $good   = self::code();
        $this->install($folder, $bad, self::manifest($bad, ['events' => [['trigger' => 'page/output', 'handler' => 'onPage', 'priority' => -10]]]), <<<'PHP'
            public function onPage(\Reamur\System\Library\Extension\PageOutput $page): void {
                $page->meta('antes', '1');
                throw new \LogicException("quebrou\naqui <b>");
            }
        PHP);
        $this->install($folder, $good, self::manifest($good, self::ON_PAGE), 'public function onPage($p): void { $p->meta("depois", "2"); }');

        [$registry, $db, $log] = $this->registry();
        $db->put($bad . '.enabled', '1');
        $db->put($good . '.enabled', '1');

        $this->manager($registry, $folder)->boot('catalog');

        $core = false;
        $registry->get('event')->register('page/output', static function (array &$args) use (&$core): void {
            $core = true;
        }, 100);

        $html = self::pageOutput($registry)->render(PageOutput::HEAD, 'n');

        self::assertStringContainsString('name="antes"', $html, 'o que foi pedido antes do erro fica');
        self::assertStringContainsString('name="depois"', $html);
        self::assertTrue($core, 'o ouvinte do nucleo roda');
        self::assertCount(1, $log->lines);
        self::assertSame('extension ' . $bad . ': error in onPage (page/output): quebrou aqui <b> at extension.php:13', $log->lines[0]);
    }

    public function testPhpErrorsOfAListenerAreAlsoContained(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::ON_PAGE), 'public function onPage($p): void { $x = null; $x->naoExiste(); }');

        [$registry, $db, $log] = $this->registry();
        $db->put($code . '.enabled', '1');

        $this->manager($registry, $folder)->boot('catalog');
        self::pageOutput($registry);

        self::assertCount(1, $log->lines);
        self::assertStringContainsString('error in onPage (page/output): Call to a member function naoExiste() on null', $log->lines[0]);
    }

    public function testWhatAListenerPrintsIsDiscardedAndLogged(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, ['events' => [
            ['trigger' => 'page/output', 'handler' => 'onPage'],
            ['trigger' => 'page/output', 'handler' => 'onDeep', 'priority' => 5],
            ['trigger' => 'page/output', 'handler' => 'onQuiet', 'priority' => 9],
        ]]), <<<'PHP'
            public function onPage($p): void { echo '<script>solto()</script>'; $p->meta('fica', '1'); }
            public function onDeep($p): void { ob_start(); echo 'abc'; ob_start(); echo 'de'; throw new \RuntimeException('caiu'); }
            public function onQuiet($p): void { $p->meta('quieto', '1'); }
        PHP);

        [$registry, $db, $log] = $this->registry();
        $db->put($code . '.enabled', '1');
        $this->manager($registry, $folder)->boot('catalog');

        $level = ob_get_level();
        ob_start();
        $page = new PageOutput('common/home');
        $args = [$page];
        $registry->get('event')->trigger('page/output', $args);
        $printed = (string)ob_get_clean();

        // Nada chega a saida, os niveis de buffer voltam ao que eram, e o pedido feito pelo PageOutput vale.
        self::assertSame('', $printed);
        self::assertSame($level, ob_get_level());
        self::assertSame('<meta name="fica" content="1">' . "\n" . '<meta name="quieto" content="1">' . "\n", $page->render(PageOutput::HEAD, 'N'));

        $lines = $log->all();
        self::assertStringContainsString('extension ' . $code . ': 24 bytes printed by onPage (page/output) were discarded', $lines);
        self::assertStringContainsString('extension ' . $code . ': error in onDeep (page/output): caiu', $lines);
        self::assertStringContainsString('extension ' . $code . ': 5 bytes printed by onDeep (page/output) were discarded', $lines);
        self::assertStringNotContainsString('onQuiet', $lines);
        self::assertStringNotContainsString('solto', $lines);
    }

    public function testLongErrorMessageIsCutInTheLog(): void {
        self::assertSame(603, strlen(Manager::oneLine(str_repeat('a', 5000))));
        self::assertSame('a b c', Manager::oneLine("a\r\nb\x00\x1Fc"));
    }

    public function testListenersRunByPriorityAcrossExtensions(): void {
        $folder = $this->folder();
        $late   = self::code();
        $early  = self::code();
        $this->install($folder, $late, self::manifest($late, ['events' => [['trigger' => 'page/output', 'handler' => 'onPage', 'priority' => 50]]]), 'public function onPage($p): void { $p->meta("tarde", "1"); }');
        $this->install($folder, $early, self::manifest($early, ['events' => [['trigger' => 'page/output', 'handler' => 'onPage', 'priority' => -50]]]), 'public function onPage($p): void { $p->meta("cedo", "1"); }');

        [$registry, $db] = $this->registry();
        $db->put($late . '.enabled', '1');
        $db->put($early . '.enabled', '1');

        $this->manager($registry, $folder)->boot('catalog');

        $html = self::pageOutput($registry)->render(PageOutput::HEAD, 'n');

        self::assertLessThan(strpos($html, 'tarde'), strpos($html, 'cedo'));
    }

    public function testListenerReceivesACopyOfTheEventArguments(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, ['events' => [['trigger' => 'order_add', 'handler' => 'onOrder']]]), <<<'PHP'
            public function onOrder(array &$payload, string $name): void {
                self::$seen = [$payload, $name];
                $payload['visto'] = true;
            }
        PHP);

        [$registry, $db, $log] = $this->registry();
        $db->put($code . '.enabled', '1');

        $this->manager($registry, $folder)->boot('catalog');

        $args = [['order_id' => 7], 'order_add'];
        $registry->get('event')->trigger('order_add', $args);

        self::assertSame([['order_id' => 7], 'order_add'], (self::classOf($code))::$seen);
        self::assertSame([['order_id' => 7], 'order_add'], $args, 'a extensao observa: nao altera os dados que o nucleo usa depois');
        self::assertSame([], $log->lines);
    }

    // ── segredos ────────────────────────────────────────────────────────────

    public function testSecretsAreOpenedForTheExtensionAndNeverDumped(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::ON_PAGE + [
            'settings' => [['key' => 'token', 'type' => 'secret', 'label' => 'T'], ['key' => 'vazio', 'type' => 'secret', 'label' => 'V']],
        ]), <<<'PHP'
            public function onPage($p): void {
                self::$seen = [$this->peek('token'), $this->peekLocked('token'), $this->peek('vazio'), $this->peekLocked('vazio'), print_r($this, true)];
            }
        PHP);

        $box = new SecretBox(self::KEY);
        [$registry, $db] = $this->registry('catalog', $box);
        $db->put($code . '.enabled', '1');
        $db->put($code . '.secret.token', $box->seal('valor-secreto', SecretBox::extensionContext($code, 'token')));
        $db->put($code . '.secret.sobra', 'campo que o manifesto nao tem mais');

        $this->manager($registry, $folder)->boot('catalog');
        self::pageOutput($registry);

        $seen = (self::classOf($code))::$seen;

        self::assertSame(['valor-secreto', false, '', false], array_slice($seen, 0, 4));
        self::assertStringNotContainsString('valor-secreto', $seen[4], 'print_r/var_dump da extensao nao mostra a configuracao');
    }

    public function testSecretSealedWithAnotherKeyComesEmptyAndFlagged(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::ON_PAGE + [
            'settings' => [['key' => 'token', 'type' => 'secret', 'label' => 'T', 'required' => true]],
        ]), 'public function onPage($p): void { self::$seen = [$this->peek("token"), $this->peekLocked("token")]; }');

        [$registry, $db] = $this->registry('catalog', new SecretBox(self::KEY));
        $db->put($code . '.enabled', '1');
        $db->put($code . '.secret.token', (new SecretBox(self::OTHER))->seal('valor-secreto', SecretBox::extensionContext($code, 'token')));

        $manager = $this->manager($registry, $folder);
        $manager->boot('catalog');
        self::pageOutput($registry);

        self::assertSame([$code], $manager->running());
        self::assertSame(['', true], (self::classOf($code))::$seen, 'nunca recebe o texto cifrado no lugar do valor');
    }

    public function testSecretOfAnotherExtensionDoesNotOpen(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::ON_PAGE + [
            'settings' => [['key' => 'token', 'type' => 'secret', 'label' => 'T']],
        ]), 'public function onPage($p): void { self::$seen = [$this->peek("token"), $this->peekLocked("token")]; }');

        $box = new SecretBox(self::KEY);
        [$registry, $db] = $this->registry('catalog', $box);
        $db->put($code . '.enabled', '1');
        // Linha copiada de outra extensao direto no banco.
        $db->put($code . '.secret.token', $box->seal('da-outra', SecretBox::extensionContext('outra', 'token')));

        $this->manager($registry, $folder)->boot('catalog');
        self::pageOutput($registry);

        self::assertSame(['', true], (self::classOf($code))::$seen);
    }

    // ── politica de conteudo ────────────────────────────────────────────────

    private const CSP = ['csp' => ['script-src' => ['https://cdn.example.com'], 'connect-src' => ['https://api.example.com']]];

    public function testDeclaredOriginsEnterThePublicPolicyOnlyWhileEnabled(): void {
        $folder = $this->folder();
        $on     = self::code();
        $off    = self::code();
        $this->install($folder, $on, self::manifest($on, self::CSP), '');
        $this->install($folder, $off, self::manifest($off, ['csp' => ['script-src' => ['https://desligada.example.com']]]), '');

        [$registry, $db] = $this->registry();
        $db->put($on . '.enabled', '1');
        $db->put($off . '.enabled', '0');

        $before = $registry->get('csp')->policy();
        self::assertStringNotContainsString('cdn.example.com', $before);

        $this->manager($registry, $folder)->boot('catalog');

        $policy = $registry->get('csp')->policy();

        self::assertMatchesRegularExpression('~script-src [^;]*https://cdn\.example\.com~', $policy);
        self::assertMatchesRegularExpression('~connect-src [^;]*https://api\.example\.com~', $policy);
        self::assertStringNotContainsString('desligada.example.com', $policy);
        self::assertStringNotContainsString("'unsafe-inline'", (string)preg_replace('~style-src[^;]*~', '', $policy));
        self::assertStringNotContainsString("'unsafe-eval'", $policy);

        $sent = implode("\n", $registry->get('response')->getHeaders());
        self::assertStringContainsString('https://cdn.example.com', $sent, 'o cabecalho da resposta foi reenviado com as origens');
    }

    public function testDeclaredOriginsNeverReachTheAdminPolicy(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::CSP + ['apps' => ['catalog', 'admin']]), '');

        [$registry, $db] = $this->registry('admin');
        $db->put($code . '.enabled', '1');

        $before  = (string)preg_replace("~'nonce-[^']+'~", '', $registry->get('csp')->policy());
        $manager = $this->manager($registry, $folder);
        $manager->boot('admin');

        self::assertSame([$code], $manager->running());
        self::assertSame($before, (string)preg_replace("~'nonce-[^']+'~", '', $registry->get('csp')->policy()));
        self::assertSame([], $registry->get('response')->getHeaders());
    }

    public function testFailedExtensionAddsNoOrigin(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, self::CSP + ['events' => [['trigger' => 'page/output', 'handler' => 'onFalta']]]), '');

        [$registry, $db] = $this->registry();
        $db->put($code . '.enabled', '1');

        $this->manager($registry, $folder)->boot('catalog');

        self::assertStringNotContainsString('cdn.example.com', $registry->get('csp')->policy());
    }

    public function testScriptAddressIsCheckedAgainstTheOriginsOfThatExtension(): void {
        $folder = $this->folder();
        $with   = self::code();
        $none   = self::code();
        $body   = 'public function onPage($p): void { self::$seen = [$p->scriptSrc("https://cdn.example.com/a.js"), $p->scriptSrc("https://evil.example.org/a.js")]; }';
        $this->install($folder, $with, self::manifest($with, self::CSP + self::ON_PAGE), $body);
        $this->install($folder, $none, self::manifest($none, self::ON_PAGE), $body);

        [$registry, $db] = $this->registry();
        $db->put($with . '.enabled', '1');
        $db->put($none . '.enabled', '1');

        $this->manager($registry, $folder)->boot('catalog');
        $page = self::pageOutput($registry);

        self::assertSame([true, false], (self::classOf($with))::$seen);
        self::assertSame([false, false], (self::classOf($none))::$seen, 'a origem declarada por uma extensao nao serve para a outra');
        self::assertSame(1, substr_count($page->render(PageOutput::HEAD, 'n'), '<script '));
        self::assertCount(3, $page->rejected());
        self::assertStringStartsWith($with . ': scriptSrc: https://evil.example.org/a.js is not covered', $page->rejected()[0]);

        // Depois do evento ninguem mais fala em nome da extensao.
        self::assertFalse($page->scriptSrc('https://cdn.example.com/b.js'));
        self::assertStringStartsWith('core: ', $page->rejected()[3]);
    }
}
