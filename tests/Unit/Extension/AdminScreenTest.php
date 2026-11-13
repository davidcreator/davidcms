<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

require_once __DIR__ . '/ExtensionTestCase.php';

use Reamur\Admin\Controller\Extension\Extension as Screen;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Request;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Security\SecretBox;
use Reamur\System\Library\Session;

/**
 * EX-01 (ADR 0095): a tela "Extensoes" do admin. Usa o controlador e os
 * templates de verdade (Twig com o mesmo autoescape do CMS); so o que cerca
 * o controlador e substituido. Garante: nada muda sem POST + permissao +
 * CSRF; a tela nunca carrega o PHP da extensao; segredo nunca volta para o
 * HTML; texto do extension.json nunca vira HTML.
 */
final class AdminScreenTest extends ExtensionTestCase {
    private const KEY   = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';
    private const OTHER = 'ZmVkY2JhOTg3NjU0MzIxMGZlZGNiYTk4NzY1NDMyMTA=';

    private const FIELDS = ['settings' => [
        ['key' => 'id', 'type' => 'text', 'label' => 'Identificador', 'required' => true, 'pattern' => 'G-[A-Z0-9]{4,10}', 'max' => 20],
        ['key' => 'mode', 'type' => 'select', 'label' => 'Modo', 'options' => [['value' => 'a', 'label' => 'Opcao A'], ['value' => 'b', 'label' => 'Opcao B']], 'default' => 'a'],
        ['key' => 'debug', 'type' => 'checkbox', 'label' => 'Depurar'],
        ['key' => 'token', 'type' => 'secret', 'label' => 'Token'],
    ]];

    /**
     * Monta a tela.
     *
     * @param array{modify?: bool, method?: string, post?: array<string, mixed>, csrf?: bool, box?: SecretBox, language?: string, off?: bool} $o
     * @return object{screen: Screen, registry: Registry, db: object, log: object, session: Session, response: \Reamur\System\Library\Response}
     */
    private function screen(string $folder, array $o = []): object {
        [$registry, $db, $log] = $this->registry('admin', $o['box'] ?? null);

        $language = $o['language'] ?? 'en-us';
        $registry->get('config')->set('language_directory', $language);
        $registry->get('config')->set('extension_off', $o['off'] ?? false);

        /** @var Session $session */
        $session       = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $session->data = [];

        $request         = new Request();
        $request->server = ['REQUEST_METHOD' => $o['method'] ?? 'GET'];
        $request->post   = ($o['post'] ?? []) + (($o['csrf'] ?? true) ? ['csrf_token' => Csrf::token($session)] : []);

        $texts = new class {
            /** @var array<string, string> */
            public array $data = [];

            /** @var array<string, mixed> o que o controlador entregou ao template */
            public array $view = [];

            public function get(string $key): string {
                return $this->data[$key] ?? $key;
            }
        };

        $twig = new \Twig\Environment(
            new \Twig\Loader\FilesystemLoader([dirname(__DIR__, 3) . '/dev/admin/view/template/']),
            ['autoescape' => 'html', 'cache' => false]
        );

        $registry->set('request', $request);
        $registry->set('session', $session);
        $registry->set('language', $texts);
        $registry->set('document', new class {
            public string $title = '';

            public function setTitle(string $title): void {
                $this->title = $title;
            }
        });
        $registry->set('url', new class {
            public function link(string $route): string {
                return 'index.php?route=' . $route;
            }
        });
        $registry->set('user', new class($o['modify'] ?? true) {
            public function __construct(private bool $modify) {}

            public function hasPermission(string $type, string $route): bool {
                return $route === 'extension/extension' && ($type === 'access' || $this->modify);
            }

            public function getUsername(): string {
                return 'maria';
            }
        });
        $registry->set('load', new class($texts, $twig, $language) {
            public function __construct(private object $texts, private \Twig\Environment $twig, private string $language) {}

            public function language(string $route): void {
                $_ = [];
                require dirname(__DIR__, 3) . '/dev/admin/language/' . $this->language . '/' . $route . '.php';
                $this->texts->data = $_ + $this->texts->data;
            }

            /** @param array<string, mixed> $data */
            public function view(string $route, array $data = []): string {
                $this->texts->view = $data;

                return $this->twig->render($route . '.twig', $data);
            }
        });

        $this->manager($registry, $folder);

        return (object)[
            'screen'   => new Screen($registry),
            'registry' => $registry,
            'db'       => $db,
            'log'      => $log,
            'session'  => $session,
            'response' => $registry->get('response'),
        ];
    }

    private static function location(object $s): string {
        foreach ($s->response->getHeaders() as $header) {
            if (stripos($header, 'Location: ') === 0) {
                return substr($header, 10);
            }
        }

        return '';
    }

    /** Pasta com uma extensao cujo extension.php derruba o teste se for carregado. */
    private function withExtension(array $over = [], ?string $code = null): array {
        $folder = $this->folder();
        $code ??= self::code();
        $this->install($folder, $code, self::manifest($code, $over), null);
        file_put_contents($folder . $code . '/extension.php', '<?php throw new \RuntimeException("a tela do admin carregou o PHP da extensao");');

        return [$folder, $code];
    }

    // ── lista ───────────────────────────────────────────────────────────────

    public function testEmptyFolderShowsTheHint(): void {
        $s = $this->screen($this->folder());
        $s->screen->index();

        $html = $s->response->getOutput();

        self::assertStringContainsString('No extension in the folder yet.', $html);
        self::assertStringNotContainsString('data-rms-extension=', $html);
        self::assertStringNotContainsString('data-rms-extensions-off', $html);
    }

    public function testListShowsWhatTheManifestDeclaresBeforeAnyoneTurnsItOn(): void {
        [$folder, $code] = $this->withExtension([
            'name'        => 'Etiquetas',
            'description' => 'Carrega etiquetas.',
            'author'      => 'Alguem',
            'link'        => 'https://example.com/ext',
            'apps'        => ['catalog', 'admin'],
            'events'      => [['trigger' => 'page/output', 'handler' => 'onPage'], ['trigger' => 'order_add', 'handler' => 'onOrder']],
            'csp'         => ['script-src' => ['https://cdn.example.com'], 'connect-src' => ['https://api.example.com']],
        ]);

        $s = $this->screen($folder);
        $s->screen->index();

        $html = $s->response->getOutput();

        self::assertStringContainsString('<tr data-rms-extension="' . $code . '" data-rms-extension-status="disabled">', $html);
        self::assertStringContainsString('Etiquetas', $html);
        self::assertStringContainsString('Carrega etiquetas.', $html);
        self::assertStringContainsString('<a href="https://example.com/ext" target="_blank" rel="noopener noreferrer">Alguem</a>', $html);
        self::assertStringContainsString('<code>page/output</code>, <code>order_add</code>', $html);
        self::assertStringContainsString('<code>script-src https://cdn.example.com</code>', $html);
        self::assertStringContainsString('<code>connect-src https://api.example.com</code>', $html);
        self::assertStringContainsString('public site, admin', $html);
        self::assertStringContainsString('action="index.php?route=extension/extension.enable|' . $code . '"', $html);
        self::assertStringNotContainsString('.disable|', $html);
        self::assertStringNotContainsString('.purge|', $html, 'nada gravado, nada a apagar');
        self::assertStringNotContainsString('.edit|', $html, 'sem campos, sem botao Configurar');
        self::assertSame([], $s->log->lines);
    }

    public function testEventWithSeveralListenersIsListedOnce(): void {
        [$folder] = $this->withExtension(['events' => [
            ['trigger' => 'page/output', 'handler' => 'onHead'],
            ['trigger' => 'order_add', 'handler' => 'onOrder'],
            ['trigger' => 'page/output', 'handler' => 'onBody', 'priority' => 5],
        ]]);

        $s = $this->screen($folder);
        $s->screen->index();

        $html = $s->response->getOutput();

        self::assertStringContainsString('<code>page/output</code>, <code>order_add</code></dd>', $html);
        self::assertSame(1, substr_count($html, '<code>page/output</code>'));
    }

    public function testManifestTextNeverBecomesHtml(): void {
        $evil = '<img src=x onerror=alert(1)>';
        [$folder, $code] = $this->withExtension([
            'name'        => 'N' . $evil,
            'description' => 'D' . $evil . '"\'',
            'author'      => 'A' . $evil,
            'settings'    => [
                ['key' => 'k', 'type' => 'text', 'label' => 'L' . $evil, 'help' => 'H' . $evil, 'placeholder' => 'P"' . $evil],
                ['key' => 's', 'type' => 'select', 'label' => 'S', 'options' => [['value' => 'a', 'label' => 'O' . $evil]]],
            ],
        ]);

        $s = $this->screen($folder);
        $s->db->put($code . '.config', json_encode(['k' => 'V"' . $evil]));
        $s->screen->index();
        $list = $s->response->getOutput();

        $s = $this->screen($folder);
        $s->db->put($code . '.config', json_encode(['k' => 'V"' . $evil]));
        $s->screen->edit($code);
        $form = $s->response->getOutput();

        foreach (['lista' => $list, 'formulario' => $form] as $where => $html) {
            self::assertStringNotContainsString('<img', $html, $where);
            self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html, $where);
        }

        self::assertStringContainsString('value="V&quot;&lt;img', $form, 'valor gravado fica dentro do atributo');
        self::assertStringContainsString('placeholder="P&quot;&lt;img', $form);
    }

    public function testBrokenManifestIsListedWithItsProblemsAndCannotBeEnabled(): void {
        $folder = $this->folder();
        $code   = self::code();
        $this->install($folder, $code, self::manifest($code, ['evnts' => [], 'version' => '<b>x</b>']), '');

        $s = $this->screen($folder);
        $s->screen->index();
        $html = $s->response->getOutput();

        self::assertStringContainsString('data-rms-extension-status="invalid"', $html);
        self::assertStringContainsString('unknown key &quot;evnts&quot;', $html);
        self::assertStringNotContainsString('<b>x</b>', $html);
        self::assertStringNotContainsString('.enable|', $html);

        $s = $this->screen($folder, ['method' => 'POST']);
        $s->screen->enable($code);

        self::assertSame([], $s->db->rows);
        self::assertSame('error', $s->session->data['flash']['type']);
        self::assertSame('index.php?route=extension/extension', self::location($s));
    }

    public function testIncompatibleExtensionSaysWhyAndCannotBeEnabled(): void {
        [$folder, $code] = $this->withExtension(['requires' => ['reamur' => '>=9.0.0']]);

        $s = $this->screen($folder);
        $s->screen->index();
        $html = $s->response->getOutput();

        self::assertStringContainsString('data-rms-extension-status="incompatible"', $html);
        self::assertStringContainsString('reamur &gt;=9.0.0 (this site: 1.0.0)', $html);
        self::assertStringNotContainsString('.enable|', $html);

        $s = $this->screen($folder, ['method' => 'POST']);
        $s->screen->enable($code);

        self::assertNull($s->db->value($code . '.enabled'));
        self::assertStringContainsString('cannot be enabled: reamur >=9.0.0', $s->session->data['flash']['text']);
        self::assertSame([], $s->log->lines);
    }

    public function testRequiredSettingBlocksEnableUntilFilled(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);

        $s = $this->screen($folder, ['method' => 'POST']);
        $s->screen->enable($code);

        self::assertNull($s->db->value($code . '.enabled'));
        self::assertStringContainsString('setting "id" is required and empty', $s->session->data['flash']['text']);

        $s = $this->screen($folder, ['method' => 'POST']);
        $s->db->put($code . '.config', '{"id":"G-ABCD"}');
        $s->screen->enable($code);

        self::assertSame('1', $s->db->value($code . '.enabled'));
    }

    public function testStateWithoutFolderIsListedAsMissingAndCanBeTurnedOffAndRemoved(): void {
        $folder = $this->folder();

        $s = $this->screen($folder);
        $s->db->put('sumiu.enabled', '1');
        $s->db->put('sumiu.config', '{"a":"b"}');
        $s->screen->index();
        $html = $s->response->getOutput();

        self::assertStringContainsString('<tr data-rms-extension="sumiu" data-rms-extension-status="missing on">', $html);
        self::assertStringContainsString('.disable|sumiu', $html);
        self::assertStringNotContainsString('.enable|sumiu', $html);
        self::assertStringNotContainsString('.purge|sumiu', $html, 'apagar so depois de desligar');

        $s2 = $this->screen($folder, ['method' => 'POST']);
        $s2->db->rows = $s->db->rows;
        $s2->screen->disable('sumiu');

        self::assertSame('0', $s2->db->value('sumiu.enabled'));
        self::assertSame('Extension "sumiu" disabled.', $s2->session->data['flash']['text']);

        $s3 = $this->screen($folder, ['method' => 'POST']);
        $s3->db->rows = $s2->db->rows;
        $s3->screen->purge('sumiu');

        self::assertSame([], $s3->db->rows);
        self::assertSame(['extension sumiu: stored data removed by maria'], $s3->log->lines);
    }

    public function testMasterSwitchIsAnnounced(): void {
        [$folder] = $this->withExtension();

        $s = $this->screen($folder, ['off' => true]);
        $s->screen->index();

        self::assertStringContainsString('data-rms-extensions-off="1"', $s->response->getOutput());
        self::assertStringContainsString('REAMUR_EXTENSIONS', $s->response->getOutput());
    }

    public function testReadOnlyUserSeesTheListWithoutActions(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);

        $s = $this->screen($folder, ['modify' => false]);
        $s->db->put($code . '.enabled', '1');
        $s->db->put($code . '.config', '{"id":"G-ABCD"}');
        $s->screen->index();
        $html = $s->response->getOutput();

        self::assertStringContainsString('data-rms-extension="' . $code . '"', $html);
        self::assertStringNotContainsString('<form', $html);
        self::assertStringNotContainsString('csrf_token', $html);
        self::assertStringNotContainsString('.edit|', $html);
    }

    // ── ligar, desligar, apagar ─────────────────────────────────────────────

    public function testEnableAndDisableWriteTheStateAndTheLog(): void {
        [$folder, $code] = $this->withExtension(['name' => ['en-us' => 'Tags', 'pt-br' => 'Etiquetas']]);

        $s = $this->screen($folder, ['method' => 'POST']);
        $s->screen->enable($code);

        self::assertSame('1', $s->db->value($code . '.enabled'));
        self::assertSame(['type' => 'success', 'text' => 'Extension "Tags" enabled.'], $s->session->data['flash']);
        self::assertSame(['extension ' . $code . ' 1.2.3: enabled by maria'], $s->log->lines);
        self::assertSame('index.php?route=extension/extension', self::location($s));

        $s2 = $this->screen($folder, ['method' => 'POST', 'language' => 'pt-br']);
        $s2->db->rows = $s->db->rows;
        $s2->screen->disable($code);

        self::assertSame('0', $s2->db->value($code . '.enabled'));
        self::assertSame('Extensão "Etiquetas" desligada.', $s2->session->data['flash']['text']);
        self::assertSame(['extension ' . $code . ': disabled by maria'], $s2->log->lines);
    }

    /** @return array<string, array{0: string}> */
    public static function changes(): array {
        return ['ligar' => ['enable'], 'desligar' => ['disable'], 'apagar' => ['purge']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('changes')]
    public function testNoChangeByGet(string $action): void {
        [$folder, $code] = $this->withExtension();

        $s = $this->screen($folder, ['method' => 'GET']);
        $s->db->put($code . '.config', '{"a":"b"}');
        $before = $s->db->rows;
        $s->screen->{$action}($code);

        self::assertSame($before, $s->db->rows);
        self::assertSame('index.php?route=extension/extension', self::location($s));
        self::assertSame([], $s->log->lines);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('changes')]
    public function testNoChangeWithoutModifyPermission(string $action): void {
        [$folder, $code] = $this->withExtension();

        $s = $this->screen($folder, ['method' => 'POST', 'modify' => false]);
        $s->db->put($code . '.config', '{"a":"b"}');
        $before = $s->db->rows;
        $s->screen->{$action}($code);

        self::assertSame($before, $s->db->rows);
        self::assertSame('index.php?route=error/permission', self::location($s));
        self::assertSame([], $s->log->lines);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('changes')]
    public function testNoChangeWithoutCsrfToken(string $action): void {
        [$folder, $code] = $this->withExtension();

        foreach ([['csrf' => false], ['csrf' => false, 'post' => ['csrf_token' => 'errado']], ['csrf' => false, 'post' => ['csrf_token' => ['x']]]] as $o) {
            $s = $this->screen($folder, ['method' => 'POST'] + $o);
            $s->db->put($code . '.config', '{"a":"b"}');
            $before = $s->db->rows;
            $s->screen->{$action}($code);

            self::assertSame($before, $s->db->rows);
            self::assertSame('error', $s->session->data['flash']['type']);
            self::assertSame([], $s->log->lines);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('changes')]
    public function testBadCodeNeverReachesTheDatabase(string $action): void {
        [$folder] = $this->withExtension();

        foreach (['', '..', '../x', 'A', "x' OR '1'='1", 'a.b', '%'] as $code) {
            $s = $this->screen($folder, ['method' => 'POST']);
            $s->screen->{$action}($code);

            self::assertSame([], $s->db->rows, $code);
            self::assertSame('error', $s->session->data['flash']['type'], $code);
            self::assertSame([], $s->log->lines, $code);

            foreach ($s->db->sql as $sql) {
                self::assertStringStartsWith('SELECT', $sql, 'nada alem de leitura');
            }
        }
    }

    public function testPurgeIsRefusedWhileEnabled(): void {
        [$folder, $code] = $this->withExtension();

        $s = $this->screen($folder, ['method' => 'POST']);
        $s->db->put($code . '.enabled', '1');
        $s->db->put($code . '.config', '{"a":"b"}');
        $s->screen->purge($code);

        self::assertCount(2, $s->db->rows);
        self::assertSame('Disable the extension before removing its data.', $s->session->data['flash']['text']);
    }

    public function testPurgeRemovesOnlyThatExtension(): void {
        [$folder, $code] = $this->withExtension();

        $s = $this->screen($folder, ['method' => 'POST']);
        $s->db->put($code . '.enabled', '0');
        $s->db->put($code . '.config', '{"a":"b"}');
        $s->db->put($code . '.secret.token', 'x');
        $s->db->put('outra.enabled', '1');
        $s->db->put('config_name', 'Loja', 'config');
        $s->screen->purge($code);

        self::assertSame(
            [['code' => 'extension', 'key' => 'outra.enabled', 'value' => '1'], ['code' => 'config', 'key' => 'config_name', 'value' => 'Loja']],
            $s->db->rows
        );
    }

    // ── formulario ──────────────────────────────────────────────────────────

    public function testFormIsBuiltFromTheManifest(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);

        $s = $this->screen($folder);
        $s->screen->edit($code);
        $html = $s->response->getOutput();

        self::assertStringContainsString('data-rms-extension-form="' . $code . '"', $html);
        self::assertStringContainsString('action="index.php?route=extension/extension.edit|' . $code . '"', $html);
        self::assertMatchesRegularExpression('~<input [^>]*type="text" name="id" [^>]*value="" maxlength="20"[^>]* required>~', $html);
        self::assertMatchesRegularExpression('~<select [^>]*name="mode"~', $html);
        self::assertStringContainsString('<option value="a" selected>Opcao A</option>', $html, 'padrao do manifesto');
        self::assertMatchesRegularExpression('~<input [^>]*type="checkbox" name="debug" [^>]*value="1">~', $html);
        self::assertMatchesRegularExpression('~<input [^>]*type="password" name="token" [^>]*value=""[^>]*autocomplete="new-password"~', $html);
        self::assertStringNotContainsString('name="token__clear"', $html, 'sem segredo gravado, nada a apagar');
        self::assertStringNotContainsString('<script', $html);
    }

    public function testExtensionWithoutSettingsHasNoForm(): void {
        [$folder, $code] = $this->withExtension();

        $s = $this->screen($folder);
        $s->screen->edit($code);

        self::assertSame('', $s->response->getOutput());
        self::assertSame('This extension has nothing to configure.', $s->session->data['flash']['text']);
        self::assertSame('index.php?route=extension/extension', self::location($s));
    }

    public function testUnknownExtensionHasNoForm(): void {
        [$folder] = $this->withExtension();

        foreach (['nao_existe', '../etc', ''] as $code) {
            $s = $this->screen($folder);
            $s->screen->edit($code);

            self::assertSame('', $s->response->getOutput());
            self::assertSame('error', $s->session->data['flash']['type']);
        }
    }

    public function testValidFormIsSavedAndTheSecretIsSealed(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);
        $box = new SecretBox(self::KEY);

        $s = $this->screen($folder, ['method' => 'POST', 'box' => $box, 'post' => [
            'id' => 'G-ABCD12', 'mode' => 'b', 'debug' => '1', 'token' => 'segredo-da-loja', 'intruso' => 'x',
        ]]);
        $s->screen->edit($code);

        self::assertSame('{"id":"G-ABCD12","mode":"b","debug":true}', $s->db->value($code . '.config'));
        self::assertTrue(SecretBox::isSealed((string)$s->db->value($code . '.secret.token')));
        self::assertSame('segredo-da-loja', $box->open((string)$s->db->value($code . '.secret.token'), SecretBox::extensionContext($code, 'token')));
        self::assertStringNotContainsString('segredo-da-loja', implode("\n", $s->db->sql));
        self::assertNull($s->db->value($code . '.enabled'), 'gravar nao liga');
        self::assertSame('index.php?route=extension/extension', self::location($s));
        self::assertStringContainsString('saved', $s->session->data['flash']['text']);
        self::assertStringNotContainsString('segredo-da-loja', $s->log->all());
    }

    public function testInvalidFormSavesNothingAndPointsAtTheField(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);

        $s = $this->screen($folder, ['method' => 'POST', 'post' => [
            'id' => '"><script>x', 'mode' => 'z', 'token' => 'segredo-digitado',
        ]]);
        $s->db->put($code . '.config', '{"id":"G-OLD1"}');
        $s->screen->edit($code);
        $html = $s->response->getOutput();

        self::assertSame('{"id":"G-OLD1"}', $s->db->value($code . '.config'), 'nada gravado, nem os campos validos');
        self::assertNull($s->db->value($code . '.secret.token'));
        self::assertSame('', self::location($s));
        self::assertStringContainsString('Some fields need attention. Nothing was saved.', $html);
        self::assertStringContainsString('data-rms-field-error="id">This value is not in the format the extension accepts.', $html);
        self::assertStringContainsString('data-rms-field-error="mode">Choose one of the listed options.', $html);
        self::assertStringNotContainsString('<script>x', $html);
        self::assertStringContainsString('value="&quot;&gt;&lt;script&gt;x"', $html, 'o que foi digitado volta, com escape, para ser corrigido');
        self::assertStringNotContainsString('segredo-digitado', $html, 'o segredo digitado nao volta para a tela');
    }

    public function testFormPostWithoutCsrfSavesNothing(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);

        $s = $this->screen($folder, ['method' => 'POST', 'csrf' => false, 'post' => ['id' => 'G-ABCD12']]);
        $s->screen->edit($code);

        self::assertSame([], $s->db->rows);
        self::assertStringContainsString('This form expired or could not be verified', $s->response->getOutput());
    }

    public function testFormPostWithoutModifyPermissionSavesNothing(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);

        $s = $this->screen($folder, ['method' => 'POST', 'modify' => false, 'post' => ['id' => 'G-ABCD12']]);
        $s->screen->edit($code);

        self::assertSame([], $s->db->rows);
        self::assertSame('index.php?route=error/permission', self::location($s));
    }

    public function testReadOnlyUserSeesTheFormDisabledAndWithoutSaveButton(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);

        $s = $this->screen($folder, ['modify' => false]);
        $s->screen->edit($code);
        $html = $s->response->getOutput();

        self::assertStringContainsString('<fieldset disabled>', $html);
        self::assertStringNotContainsString('type="submit"', $html);
    }

    public function testStoredSecretNeverComesBackToTheScreen(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);
        $box    = new SecretBox(self::KEY);
        $sealed = $box->seal('segredo-da-loja', SecretBox::extensionContext($code, 'token'));

        foreach ([$box, new SecretBox('')] as $current) {
            $s = $this->screen($folder, ['box' => $current]);
            $s->db->put($code . '.config', '{"id":"G-ABCD"}');
            $s->db->put($code . '.secret.token', $current === $box ? $sealed : 'segredo-da-loja');
            $s->screen->edit($code);
            $html = $s->response->getOutput();

            self::assertStringNotContainsString('segredo-da-loja', $html);
            self::assertStringNotContainsString($sealed, $html, 'nem o texto cifrado');
            self::assertStringNotContainsString(substr($sealed, 0, 24), $html);
            self::assertStringContainsString('name="token__clear"', $html);
            self::assertMatchesRegularExpression('~type="password" name="token" [^>]*value=""~', $html);

            $s = $this->screen($folder, ['box' => $current]);
            $s->db->put($code . '.secret.token', $current === $box ? $sealed : 'segredo-da-loja');
            $s->screen->index();

            self::assertStringNotContainsString('segredo-da-loja', $s->response->getOutput());
            self::assertStringNotContainsString(substr($sealed, 0, 24), $s->response->getOutput());
        }
    }

    public function testSecretIsHeldBackTwiceByTheControllerAndByTheTemplate(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);

        // 1) O controlador nao entrega o valor gravado ao template, cifrado ou nao.
        $s = $this->screen($folder, ['box' => new SecretBox('')]);
        $s->db->put($code . '.secret.token', 'segredo-da-loja');
        $s->screen->edit($code);

        self::assertStringNotContainsString('segredo-da-loja', (string)json_encode($s->registry->get('language')->view));

        // 2) O template nao imprime valor em campo-segredo, mesmo que receba um.
        $twig = new \Twig\Environment(
            new \Twig\Loader\FilesystemLoader([dirname(__DIR__, 3) . '/dev/admin/view/template/']),
            ['autoescape' => 'html', 'cache' => false]
        );
        $html = $twig->render('extension/extension_form.twig', ['can_modify' => true, 'fields' => [[
            'key' => 'token', 'type' => 'secret', 'label' => 'T', 'help' => '', 'placeholder' => 'entregue-por-engano-p', 'required' => false,
            'max' => 255, 'options' => [], 'value' => 'entregue-por-engano', 'error' => '', 'secret_set' => true, 'secret_locked' => false,
        ]]]);

        self::assertStringContainsString('name="token"', $html);
        self::assertStringNotContainsString('entregue-por-engano', $html);
    }

    public function testEmptySecretKeepsTheStoredOneAndTheBoxClearsIt(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);

        $s = $this->screen($folder, ['method' => 'POST', 'post' => ['id' => 'G-ABCD12', 'mode' => 'a', 'token' => '']]);
        $s->db->put($code . '.secret.token', 'gravado-antes');
        $s->screen->edit($code);

        self::assertSame('gravado-antes', $s->db->value($code . '.secret.token'));

        $s2 = $this->screen($folder, ['method' => 'POST', 'post' => ['id' => 'G-ABCD12', 'mode' => 'a', 'token' => '', 'token__clear' => '1']]);
        $s2->db->rows = $s->db->rows;
        $s2->screen->edit($code);

        self::assertNull($s2->db->value($code . '.secret.token'));
        self::assertNotNull($s2->db->value($code . '.config'));
    }

    public function testSecretThatTheKeyDoesNotOpenIsFlaggedOnBothScreens(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);
        $sealed = (new SecretBox(self::OTHER))->seal('x', SecretBox::extensionContext($code, 'token'));

        $s = $this->screen($folder, ['box' => new SecretBox(self::KEY)]);
        $s->db->put($code . '.secret.token', $sealed);
        $s->screen->index();
        self::assertStringContainsString('data-rms-secret-locked="extension"', $s->response->getOutput());

        $s = $this->screen($folder, ['box' => new SecretBox(self::KEY)]);
        $s->db->put($code . '.secret.token', $sealed);
        $s->screen->edit($code);
        self::assertStringContainsString('data-rms-secret-locked="extension"', $s->response->getOutput());
    }

    public function testFormWarnsWhenThereIsNoKeyToSealWith(): void {
        [$folder, $code] = $this->withExtension(self::FIELDS);

        $s = $this->screen($folder, ['box' => new SecretBox('')]);
        $s->screen->edit($code);
        $withoutKey = $s->response->getOutput();

        $s = $this->screen($folder, ['box' => new SecretBox(self::KEY)]);
        $s->screen->edit($code);
        $withKey = $s->response->getOutput();

        self::assertStringContainsString('bi-key', $withoutKey);
        self::assertStringNotContainsString('bi-key', $withKey);
    }

    // ── textos ──────────────────────────────────────────────────────────────

    public function testEveryLanguageHasTheSameKeysAndPlaceholders(): void {
        $load = static function (string $language): array {
            $_ = [];
            require dirname(__DIR__, 3) . '/dev/admin/language/' . $language . '/extension/extension.php';

            return $_;
        };

        $en = $load('en-us');

        self::assertNotSame([], $en);

        foreach (['pt-br', 'es-eu'] as $language) {
            $other = $load($language);

            self::assertSame(array_keys($en), array_keys($other), $language);

            foreach ($en as $key => $text) {
                preg_match_all('/%[sd]/', $text, $a);
                preg_match_all('/%[sd]/', $other[$key], $b);

                self::assertSame($a[0], $b[0], $language . ' ' . $key);
                self::assertNotSame('', trim($other[$key]), $language . ' ' . $key);
            }
        }
    }

    public function testTemplatesAskOnlyForTextsTheControllerSends(): void {
        $root       = dirname(__DIR__, 3) . '/dev/admin/';
        $controller = (string)file_get_contents($root . 'controller/extension/extension.php');
        $_          = [];
        require $root . 'language/en-us/extension/extension.php';

        foreach (['extension_list', 'extension_form'] as $template) {
            preg_match_all('/\b((?:text|button|column|error)_[a-z_]+)\b/', (string)file_get_contents($root . 'view/template/extension/' . $template . '.twig'), $m);

            foreach (array_unique($m[1]) as $key) {
                if ($key === 'text_flash') {
                    continue;
                }

                self::assertArrayHasKey($key, $_, $template . ' usa ' . $key . ', que o arquivo de idioma nao tem');
                self::assertStringContainsString("'" . $key . "'", $controller, $template . ' usa ' . $key . ', que o controlador nao envia');
            }
        }

        preg_match_all("/language->get\\('([a-z_]+)'\\)/", $controller, $m);

        foreach (array_unique($m[1]) as $key) {
            self::assertArrayHasKey($key, $_, 'o controlador pede ' . $key);
        }
    }
}
