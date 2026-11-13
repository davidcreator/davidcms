<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

require_once __DIR__ . '/ExtensionTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;
use Reamur\System\Library\Extension\Manifest;
use Reamur\System\Library\Extension\ManifestException;

/**
 * EX-01 (ADR 0095): o extension.json. A leitura e estrita: o que a tela de
 * extensoes mostra a quem liga tem de ser exatamente o que vai rodar.
 */
final class ManifestTest extends ExtensionTestCase {
    /** @param array<string, mixed> $over */
    private static function parse(array $over = [], string $code = 'demo'): Manifest {
        return Manifest::parse((string)json_encode(self::manifest($code, $over)), $code);
    }

    /**
     * @param array<string, mixed> $over
     * @return list<string>
     */
    private static function problems(array $over, string $code = 'demo'): array {
        try {
            self::parse($over, $code);
        } catch (ManifestException $e) {
            return $e->problems();
        }

        return [];
    }

    // ── codigo e classe ─────────────────────────────────────────────────────

    #[DataProvider('codes')]
    public function testFolderNameRules(string $code, bool $valid): void {
        self::assertSame($valid, Manifest::isCode($code));
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function codes(): array {
        return [
            'simples'                  => ['analytics', true],
            'com sublinhado'           => ['site_verification', true],
            'com digito'               => ['ga4_tags', true],
            'letra solta no fim'       => ['plan_b', true],
            'minimo de 2'              => ['ab', true],
            '32 caracteres'            => [str_repeat('a', 32), true],
            'uma letra'                => ['a', false],
            '33 caracteres'            => [str_repeat('a', 33), false],
            'maiuscula'                => ['Analytics', false],
            'hifen'                    => ['my-ext', false],
            'ponto'                    => ['my.ext', false],
            'sobe um nivel'            => ['..', false],
            'barra'                    => ['a/b', false],
            'barra invertida'          => ['a\\b', false],
            'comeca com digito'        => ['4tags', false],
            'sublinhado no fim'        => ['tags_', false],
            'sublinhado duplo'         => ['a__b', false],
            'vazio'                    => ['', false],
            'quebra de linha no fim'   => ["demo\n", false],
            'nao volta do namespace'   => ['a_b_c', false],
            'segmento comeca digito'   => ['ga_4x', false],
        ];
    }

    public function testEntryClassComesFromTheFolderName(): void {
        self::assertSame('Reamur\\Extension\\SiteVerification\\Extension', Manifest::className('site_verification'));
        self::assertSame('Reamur\\Extension\\Ga4Tags\\Extension', Manifest::className('ga4_tags'));
    }

    // ── o arquivo ───────────────────────────────────────────────────────────

    public function testMinimalManifestGetsSafeDefaults(): void {
        $m = self::parse();

        self::assertSame('demo', $m->code());
        self::assertSame('Extensao demo', $m->name('pt-br'));
        self::assertSame('1.2.3', $m->version());
        self::assertSame('', $m->description());
        self::assertSame('', $m->author());
        self::assertSame('', $m->link());
        self::assertSame(['catalog'], $m->apps(), 'sem "apps", so o site publico');
        self::assertSame([], $m->settings());
        self::assertSame([], $m->events());
        self::assertSame([], $m->csp());
        self::assertSame([], $m->requires());
        self::assertSame([], $m->incompatibilities('1.0.0', '8.2.0'));
    }

    public function testTextsFollowTheLanguageThenEnglishThenTheFirst(): void {
        $m = self::parse(['name' => ['pt-br' => 'Nome', 'en-us' => 'Name'], 'description' => ['es-eu' => 'Solo']]);

        self::assertSame('Nome', $m->name('pt-br'));
        self::assertSame('Nome', $m->name('PT-BR'));
        self::assertSame('Name', $m->name('es-eu'));
        self::assertSame('Solo', $m->description('pt-br'));
    }

    #[DataProvider('fileProblems')]
    public function testUnreadableFilesAreRefused(string $json, string $expected): void {
        try {
            Manifest::parse($json, 'demo');
            self::fail('deveria ter sido recusado');
        } catch (ManifestException $e) {
            self::assertStringContainsString($expected, $e->getMessage());
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function fileProblems(): array {
        return [
            'nao e JSON'       => ['{"schema": 1,', 'not valid JSON'],
            'vazio'            => ['', 'not valid JSON'],
            'lista'            => ['[1, 2]', 'must be a JSON object'],
            'texto'            => ['"oi"', 'must be a JSON object'],
            'grande demais'    => ['{"x": "' . str_repeat('a', Manifest::MAX_BYTES) . '"}', 'larger than'],
            'fundo demais'     => ['{"schema":1,"code":"demo","name":"n","version":"1.0.0","csp":' . str_repeat('[', 20) . str_repeat(']', 20) . '}', 'not valid JSON'],
        ];
    }

    #[DataProvider('badManifests')]
    public function testInvalidPartsRefuseTheWholeManifest(array $over, string $expected): void {
        $problems = self::problems($over);

        self::assertNotSame([], $problems, 'aceitou: ' . json_encode($over));
        self::assertStringContainsString($expected, implode("\n", $problems));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function badManifests(): array {
        $field = static fn (array $f): array => ['settings' => [$f + ['key' => 'k', 'type' => 'text', 'label' => 'L']]];

        return [
            'chave desconhecida'        => [['evnts' => []], 'unknown key "evnts"'],
            'schema errado'             => [['schema' => 2], 'schema: must be 1'],
            'schema como texto'         => [['schema' => '1'], 'schema: must be 1'],
            'code diferente da pasta'   => [['code' => 'outro'], 'code: must be the folder name'],
            'sem nome'                  => [['name' => ''], 'name: required'],
            'nome com quebra de linha'  => [['name' => "a\nb"], 'name: required'],
            'nome longo'                => [['name' => str_repeat('a', 81)], 'name: required'],
            'idioma invalido'           => [['name' => ['portugues' => 'x']], 'name: required'],
            'versao solta'              => [['version' => 'beta'], 'version: required'],
            'versao com 2 partes'       => [['version' => '1.0'], 'version: required'],
            'link http'                 => [['link' => 'http://example.com'], 'link: an https://'],
            'link javascript'           => [['link' => 'javascript:alert(1)'], 'link: an https://'],
            'link com aspas'            => [['link' => 'https://example.com/"onmouseover="x'], 'link: an https://'],
            'autor nao texto'           => [['author' => ['x']], 'author:'],
            'requires lista'            => [['requires' => ['>=1']], 'requires: must be an object'],
            'requires desconhecido'     => [['requires' => ['mysql' => '>=8']], 'requires: unknown key "mysql"'],
            'requires sem sentido'      => [['requires' => ['php' => 'novo']], 'requires.php'],
            'requires com ^'            => [['requires' => ['reamur' => '^1.0']], 'requires.reamur'],
            'apps vazio'                => [['apps' => []], 'apps:'],
            'apps instalador'           => [['apps' => ['install']], 'apps: only'],
            'apps objeto'               => [['apps' => ['a' => 'catalog']], 'apps:'],
            'settings nao lista'        => [['settings' => ['a' => 1]], 'settings: must be a list'],
            'settings demais'           => [['settings' => array_fill(0, Manifest::MAX_SETTINGS + 1, ['key' => 'k', 'type' => 'text', 'label' => 'L'])], 'settings: at most'],
            'campo nao objeto'          => [['settings' => ['x']], 'settings[0]: must be an object'],
            'campo chave desconhecida'  => [$field(['html' => '<b>']), 'settings[0]: unknown key "html"'],
            'campo sem chave'           => [['settings' => [['type' => 'text', 'label' => 'L']]], 'settings[0].key'],
            'campo chave maiuscula'     => [$field(['key' => 'Chave']), 'settings[0].key'],
            'campo chave reservada'     => [$field(['key' => 'token__clear']), 'settings[0].key'],
            'campo chave repetida'      => [['settings' => [['key' => 'k', 'type' => 'text', 'label' => 'L'], ['key' => 'k', 'type' => 'text', 'label' => 'L']]], '"k" is used twice'],
            'campo tipo desconhecido'   => [$field(['type' => 'html']), 'settings[0].type'],
            'campo sem rotulo'          => [['settings' => [['key' => 'k', 'type' => 'text']]], 'settings[0].label'],
            'campo ajuda invalida'      => [$field(['help' => 5]), 'settings[0].help'],
            'campo max zero'            => [$field(['max' => 0]), 'settings[0].max'],
            'campo max enorme'          => [$field(['max' => 4001]), 'settings[0].max'],
            'campo max em checkbox'     => [$field(['type' => 'checkbox', 'max' => 5]), 'settings[0].max'],
            'campo pattern quebrado'    => [$field(['pattern' => '[a-']), 'settings[0].pattern'],
            'campo pattern com til'     => [$field(['pattern' => 'a~b']), 'settings[0].pattern'],
            'campo pattern em select'   => [$field(['type' => 'select', 'options' => [['value' => 'a', 'label' => 'A']], 'pattern' => 'a']), 'settings[0].pattern'],
            'campo required nao bool'   => [$field(['required' => 1]), 'settings[0].required'],
            'checkbox obrigatorio'      => [$field(['type' => 'checkbox', 'required' => true]), 'settings[0].required'],
            'segredo com padrao'        => [$field(['type' => 'secret', 'default' => 'x']), 'settings[0].default: a secret has no default'],
            'checkbox padrao texto'     => [$field(['type' => 'checkbox', 'default' => 'on']), 'settings[0].default'],
            'padrao fora do pattern'    => [$field(['pattern' => '[0-9]+', 'default' => 'abc']), 'settings[0].default: does not pass'],
            'padrao fora das opcoes'    => [$field(['type' => 'select', 'options' => [['value' => 'a', 'label' => 'A']], 'default' => 'z']), 'settings[0].default: does not pass'],
            'select sem opcoes'         => [$field(['type' => 'select']), 'settings[0].options'],
            'opcao sem rotulo'          => [$field(['type' => 'select', 'options' => [['value' => 'a']]]), 'settings[0].options[0]'],
            'opcao valor com aspas'     => [$field(['type' => 'select', 'options' => [['value' => 'a"b', 'label' => 'A']]]), 'settings[0].options[0]'],
            'opcao repetida'            => [$field(['type' => 'select', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'B']]]), 'settings[0].options[1]'],
            'opcoes em texto'           => [$field(['options' => [['value' => 'a', 'label' => 'A']]]), 'settings[0].options: only for type select'],
            'placeholder em segredo'    => [$field(['type' => 'secret', 'placeholder' => 'x']), 'settings[0].placeholder'],
            'events nao lista'          => [['events' => ['a' => 1]], 'events: must be a list'],
            'evento chave a mais'       => [['events' => [['trigger' => 'page/output', 'handler' => 'onX', 'when' => 1]]], 'events[0]'],
            'evento gatilho invalido'   => [['events' => [['trigger' => '../x', 'handler' => 'onX']]], 'events[0].trigger'],
            'evento gatilho com espaco' => [['events' => [['trigger' => 'page output', 'handler' => 'onX']]], 'events[0].trigger'],
            'ouvinte sem prefixo on'    => [['events' => [['trigger' => 'page/output', 'handler' => 'run']]], 'events[0].handler'],
            'ouvinte construtor'        => [['events' => [['trigger' => 'page/output', 'handler' => '__construct']]], 'events[0].handler'],
            'ouvinte on minusculo'      => [['events' => [['trigger' => 'page/output', 'handler' => 'once']]], 'events[0].handler'],
            'prioridade texto'          => [['events' => [['trigger' => 'page/output', 'handler' => 'onX', 'priority' => '1']]], 'events[0].priority'],
            'prioridade fora da faixa'  => [['events' => [['trigger' => 'page/output', 'handler' => 'onX', 'priority' => 1001]]], 'events[0].priority'],
            'evento repetido'           => [['events' => [['trigger' => 'a', 'handler' => 'onX'], ['trigger' => 'a', 'handler' => 'onX']]], 'appear twice'],
            'csp lista'                 => [['csp' => ['https://a.example']], 'csp: must be an object'],
            'csp diretiva desconhecida' => [['csp' => ['default-src' => ['https://a.example']]], 'csp: unknown directive "default-src"'],
            'csp script-src-elem'       => [['csp' => ['script-src-elem' => ['https://a.example']]], 'csp: unknown directive'],
            'csp nao lista'             => [['csp' => ['script-src' => 'https://a.example']], 'csp.script-src: a list'],
            'csp vazia'                 => [['csp' => ['script-src' => []]], 'csp.script-src: a list'],
            'csp unsafe-inline'         => [['csp' => ['script-src' => ["'unsafe-inline'"]]], 'is not accepted'],
            'csp unsafe-eval'           => [['csp' => ['script-src' => ["'unsafe-eval'"]]], 'is not accepted'],
            'csp asterisco'             => [['csp' => ['script-src' => ['*']]], 'is not accepted'],
            'csp esquema solto'         => [['csp' => ['script-src' => ['https:']]], 'is not accepted'],
            'csp data'                  => [['csp' => ['script-src' => ['data:']]], 'is not accepted'],
            'csp http'                  => [['csp' => ['script-src' => ['http://cdn.example.com']]], 'is not accepted'],
            'csp com espaco'            => [['csp' => ['script-src' => ["https://a.example 'unsafe-inline'"]]], 'is not accepted'],
            'csp com ponto e virgula'   => [['csp' => ['script-src' => ['https://a.example; script-src *']]], 'is not accepted'],
            'csp nao texto'             => [['csp' => ['script-src' => [5]]], 'is not accepted'],
            'csp demais'                => [['csp' => ['img-src' => array_map(static fn (int $i): string => 'https://s' . $i . '.example.com', range(1, Manifest::MAX_SOURCES + 1))]], 'csp.img-src: a list'],
        ];
    }

    public function testEveryProblemIsReportedNotJustTheFirst(): void {
        $problems = self::problems(['schema' => 9, 'version' => 'x', 'apps' => ['install'], 'evnts' => 1]);

        self::assertCount(4, $problems);
    }

    public function testProblemMessagesNeverEchoRawFileContent(): void {
        $problems = self::problems(['<img src=x onerror=alert(1)>' => 1, 'csp' => ['script-src' => ['"><script>alert(1)</script>']]]);
        $text     = implode("\n", $problems);

        self::assertStringNotContainsString('<', $text);
        self::assertStringNotContainsString('>', str_replace(['>=', '->'], '', $text));
    }

    // ── o que um manifesto completo declara ─────────────────────────────────

    public function testFullManifestIsReadAsWritten(): void {
        $m = self::parse([
            'author'   => 'Alguem',
            'link'     => 'https://example.com/ext',
            'requires' => ['reamur' => '>=1.0.0 <2.0.0', 'php' => '>=8.2'],
            'apps'     => ['admin', 'catalog', 'admin'],
            'settings' => [
                ['key' => 'id', 'type' => 'text', 'label' => ['pt-br' => 'Identificador', 'en-us' => 'ID'], 'required' => true, 'max' => 20, 'pattern' => 'G-[A-Z0-9]{4,12}', 'placeholder' => 'G-XXXX'],
                ['key' => 'mode', 'type' => 'select', 'label' => 'Modo', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => ['pt-br' => 'Bê']]], 'default' => 'b'],
                ['key' => 'debug', 'type' => 'checkbox', 'label' => 'Depurar', 'default' => true],
                ['key' => 'notes', 'type' => 'textarea', 'label' => 'Notas'],
                ['key' => 'token', 'type' => 'secret', 'label' => 'Token', 'required' => true],
            ],
            'events' => [
                ['trigger' => 'page/output', 'handler' => 'onPageOutput', 'priority' => -5],
                ['trigger' => 'order_add', 'handler' => 'onOrder'],
            ],
            'csp' => [
                'script-src'  => ['https://www.googletagmanager.com', 'https://www.googletagmanager.com'],
                'connect-src' => ['https://*.google-analytics.com'],
            ],
        ]);

        self::assertSame(['admin', 'catalog'], $m->apps());
        self::assertSame(['reamur' => '>=1.0.0 <2.0.0', 'php' => '>=8.2'], $m->requires());
        self::assertSame(['id', 'mode', 'debug', 'notes', 'token'], array_column($m->settings(), 'key'));
        self::assertSame(['token'], $m->secretKeys());
        self::assertSame(['id' => '', 'mode' => 'b', 'debug' => true, 'notes' => ''], $m->defaults());
        self::assertSame(20, $m->settings()[0]['max']);
        self::assertSame(2000, $m->settings()[3]['max'], 'textarea sem max');
        self::assertSame(255, $m->settings()[4]['max'], 'segredo sem max');
        self::assertSame(
            [['trigger' => 'page/output', 'handler' => 'onPageOutput', 'priority' => -5], ['trigger' => 'order_add', 'handler' => 'onOrder', 'priority' => 0]],
            $m->events()
        );
        self::assertSame(
            ['script-src' => ['https://www.googletagmanager.com'], 'connect-src' => ['https://*.google-analytics.com']],
            $m->csp()
        );
    }

    // ── compatibilidade ─────────────────────────────────────────────────────

    #[DataProvider('constraints')]
    public function testVersionConstraints(string $actual, string $constraint, bool $ok): void {
        self::assertSame($ok, Manifest::satisfies($actual, $constraint));
    }

    /** @return array<string, array{0: string, 1: string, 2: bool}> */
    public static function constraints(): array {
        return [
            'igual ao minimo'       => ['1.0.0', '>=1.0.0', true],
            'abaixo do minimo'      => ['1.0.0', '>=1.0.1', false],
            'sem operador = minimo' => ['1.2.0', '1.1', true],
            'sem operador, abaixo'  => ['1.0.0', '1.1', false],
            'faixa, dentro'         => ['1.5.0', '>=1.0.0 <2.0.0', true],
            'faixa, no teto'        => ['2.0.0', '>=1.0.0 <2.0.0', false],
            'igualdade'             => ['1.0.0', '=1.0.0', true],
            'igualdade falsa'       => ['1.0.1', '=1.0.0', false],
            'php com sufixo'        => ['8.2.32-1+deb12', '>=8.2', true],
            'versao de teste'       => ['1.0.0-test', '>=1.0.0', true],
            'maior que'             => ['8.3.0', '>8.3', false],
            'maior que, vale'       => ['8.3.1', '>8.3', true],
            'ate, com 2 partes'     => ['8.3.0', '<=8.3', true],
            'igual, com 2 partes'   => ['1.0.0', '=1.0', true],
            'so o numero maior'     => ['2.4.1', '2', true],
            'texto sem sentido'     => ['1.0.0', 'qualquer', false],
        ];
    }

    public function testIncompatibilitiesNameWhatIsMissing(): void {
        $m = self::parse(['requires' => ['reamur' => '>=2.0.0', 'php' => '>=9.0']]);

        self::assertSame(['reamur >=2.0.0 (this site: 1.0.0)', 'php >=9.0 (this site: 8.2.32)'], $m->incompatibilities('1.0.0', '8.2.32'));
        self::assertSame([], $m->incompatibilities('2.1.0', '9.0.0'));
    }

    // ── valores de configuracao ─────────────────────────────────────────────

    private static function withFields(): Manifest {
        return self::parse(['settings' => [
            ['key' => 'id', 'type' => 'text', 'label' => 'ID', 'required' => true, 'max' => 12, 'pattern' => 'G-[A-Z0-9]{4,10}'],
            ['key' => 'mode', 'type' => 'select', 'label' => 'Modo', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']], 'default' => 'a'],
            ['key' => 'debug', 'type' => 'checkbox', 'label' => 'Depurar'],
            ['key' => 'notes', 'type' => 'textarea', 'label' => 'Notas', 'max' => 30],
            ['key' => 'token', 'type' => 'secret', 'label' => 'Token', 'required' => true, 'pattern' => '[a-z0-9]{8,}'],
            ['key' => 'spare', 'type' => 'secret', 'label' => 'Reserva'],
        ]]);
    }

    public function testValidFormIsAcceptedAndSecretsNeverComeBackAsValues(): void {
        $r = self::withFields()->validate([
            'id' => '  G-ABCD12  ', 'mode' => 'b', 'debug' => '1', 'notes' => "linha 1\r\nlinha 2", 'token' => 'abcdefgh1', 'extra' => 'ignorado',
        ]);

        self::assertSame([], $r['errors']);
        self::assertSame(['id' => 'G-ABCD12', 'mode' => 'b', 'debug' => true, 'notes' => "linha 1\nlinha 2"], $r['values']);
        self::assertSame(['token' => 'abcdefgh1'], $r['secrets']);
    }

    #[DataProvider('badInputs')]
    public function testInvalidInputIsNamedByField(array $input, string $field, string $problem): void {
        $r = self::withFields()->validate($input + ['id' => 'G-ABCD12', 'token' => 'abcdefgh1']);

        self::assertSame([$field => $problem], $r['errors']);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string, 2: string}> */
    public static function badInputs(): array {
        return [
            'obrigatorio vazio'         => [['id' => '   '], 'id', 'required'],
            'obrigatorio como lista'    => [['id' => ['x']], 'id', 'required'],
            'fora do formato'           => [['id' => 'UA-1234'], 'id', 'pattern'],
            'injeta html'               => [['id' => '"><script>'], 'id', 'pattern'],
            'formato certo + sobra'     => [['id' => "G-ABCD12\n<script>"], 'id', 'too_long'],
            'longo demais'              => [['id' => 'G-ABCDEFGHIJK'], 'id', 'too_long'],
            'opcao inexistente'         => [['mode' => 'z'], 'mode', 'option'],
            'textarea longa'            => [['notes' => str_repeat('a', 31)], 'notes', 'too_long'],
            'textarea com controle'     => [['notes' => "a\x00b"], 'notes', 'pattern'],
            'segredo fora do formato'   => [['token' => 'CURTO'], 'token', 'pattern'],
        ];
    }

    public function testNewlineInASingleLineFieldBecomesASpace(): void {
        $m = self::parse(['settings' => [['key' => 'k', 'type' => 'text', 'label' => 'K']]]);

        self::assertSame(['k' => 'a b'], $m->validate(['k' => "a\nb"])['values']);
    }

    public function testSecretKeepReplaceAndClear(): void {
        $m = self::withFields();

        // vazio + ja gravado = manter (nem aparece em secrets)
        $keep = $m->validate(['id' => 'G-ABCD12', 'token' => ''], ['token']);
        self::assertSame([], $keep['errors']);
        self::assertSame([], $keep['secrets']);

        // vazio + nada gravado + obrigatorio = erro
        self::assertSame(['token' => 'required'], $m->validate(['id' => 'G-ABCD12'], [])['errors']);

        // apagar um obrigatorio = erro; apagar um opcional = null
        self::assertSame(['token' => 'required'], $m->validate(['id' => 'G-ABCD12', 'token__clear' => '1'], ['token'])['errors']);
        $clear = $m->validate(['id' => 'G-ABCD12', 'spare__clear' => '1'], ['token', 'spare']);
        self::assertSame([], $clear['errors']);
        self::assertSame(['spare' => null], $clear['secrets']);

        // valor novo vence a caixa "apagar"
        $both = $m->validate(['id' => 'G-ABCD12', 'spare' => 'novo', 'spare__clear' => '1'], ['token', 'spare']);
        self::assertSame(['spare' => 'novo'], $both['secrets']);
    }

    public function testResolveKeepsOnlyWhatStillPassesTheManifest(): void {
        $m = self::withFields();

        self::assertSame(
            ['id' => 'G-ABCD12', 'mode' => 'b', 'debug' => true, 'notes' => 'ok'],
            $m->resolve(['id' => 'G-ABCD12', 'mode' => 'b', 'debug' => true, 'notes' => 'ok', 'token' => 'nunca', 'outra' => 'x'])
        );

        // gravado por um manifesto antigo, ou alterado direto no banco: volta ao padrao
        self::assertSame(
            ['id' => '', 'mode' => 'a', 'debug' => false, 'notes' => ''],
            $m->resolve(['id' => '<script>alert(1)</script>', 'mode' => 'z', 'debug' => 'yes', 'notes' => ['x']])
        );
    }

    public function testMissingListsRequiredFieldsWithoutValue(): void {
        $m = self::withFields();

        self::assertSame(['id', 'token'], $m->missing([], []));
        self::assertSame(['token'], $m->missing(['id' => 'G-ABCD12'], ['spare']));
        self::assertSame([], $m->missing(['id' => 'G-ABCD12'], ['token']));
        self::assertSame(['id'], $m->missing(['id' => 'fora-do-formato'], ['token']), 'valor que nao passa conta como ausente');
    }

    // ── leitura do disco ────────────────────────────────────────────────────

    public function testReadFromFolder(): void {
        $folder = $this->folder();
        $this->install($folder, 'demo', self::manifest('demo'), null);

        self::assertSame('demo', Manifest::read($folder, 'demo')->code());
        self::assertSame('demo', Manifest::read(rtrim($folder, '/'), 'demo')->code());
    }

    public function testReadRefusesBadFolderNamesBeforeTouchingTheDisk(): void {
        $folder = $this->folder();
        mkdir($folder . 'sub');
        $this->install($folder . 'sub/', 'demo', self::manifest('demo'), null);

        foreach (['../' . basename(rtrim($folder, '/')) . '/sub/demo', 'sub/demo', '..', '', 'Demo'] as $code) {
            try {
                Manifest::read($folder, $code);
                self::fail('aceitou a pasta "' . $code . '"');
            } catch (ManifestException $e) {
                self::assertStringContainsString('folder name', $e->getMessage());
            }
        }
    }

    public function testReadReportsMissingAndOversizedFiles(): void {
        $folder = $this->folder();
        mkdir($folder . 'vazia');
        $this->install($folder, 'gorda', '{"x":"' . str_repeat('a', Manifest::MAX_BYTES) . '"}', null);

        foreach (['vazia' => 'file not found', 'ausente' => 'file not found', 'gorda' => 'larger than'] as $code => $expected) {
            try {
                Manifest::read($folder, $code);
                self::fail($code);
            } catch (ManifestException $e) {
                self::assertStringContainsString($expected, $e->getMessage());
            }
        }
    }

    public function testFolderNameAndCodeFieldMustAgree(): void {
        $folder = $this->folder();
        $this->install($folder, 'copia', self::manifest('original'), null);

        $this->expectException(ManifestException::class);
        $this->expectExceptionMessage('code: must be the folder name ("copia")');
        Manifest::read($folder, 'copia');
    }
}
