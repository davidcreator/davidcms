<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

require_once __DIR__ . '/ExtensionTestCase.php';

use Reamur\System\Engine\Registry;
use Reamur\System\Library\Extension\Store;
use Reamur\System\Library\Security\SecretBox;

/**
 * EX-01 (ADR 0095): o estado das extensoes em rms_setting (code "extension").
 * Sem tabela nova: o que esta em jogo e nao misturar extensoes entre si, nao
 * tocar nas outras configuracoes do site e gravar segredo cifrado.
 */
final class StoreTest extends ExtensionTestCase {
    private const KEY = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';

    /** @return array{0: Store, 1: object, 2: Registry} */
    private function store(?SecretBox $box = null): array {
        [$registry, $db] = $this->registry('admin', $box);

        return [new Store($registry), $db, $registry];
    }

    public function testNothingStoredMeansNothingKnown(): void {
        [$store] = $this->store();

        self::assertSame([], $store->all());
    }

    public function testStateIsWrittenAndReadBack(): void {
        [$store, $db] = $this->store();

        $store->setEnabled('demo', true);
        $store->saveConfig('demo', ['id' => 'G-1', 'debug' => true, 'nota' => "aspas ' \" e \\ barra"]);

        self::assertSame('1', $db->value('demo.enabled'));
        self::assertSame(
            ['demo' => ['enabled' => true, 'config' => ['id' => 'G-1', 'debug' => true, 'nota' => "aspas ' \" e \\ barra"], 'secrets' => []]],
            $store->all()
        );

        $store->setEnabled('demo', false);

        self::assertSame('0', $db->value('demo.enabled'));
        self::assertFalse($store->all()['demo']['enabled']);
        self::assertCount(2, $db->rows, 'gravar de novo atualiza a linha, nao cria outra');
    }

    public function testOnlyRowsOfTheExtensionCodeAreReadOrTouched(): void {
        [$store, $db] = $this->store();
        $db->put('config_name', 'Loja', 'config');
        $db->put('demo.enabled', '1', 'config');

        self::assertSame([], $store->all());

        $store->setEnabled('demo', true);
        $store->purge('demo');

        self::assertSame(
            [['code' => 'config', 'key' => 'config_name', 'value' => 'Loja'], ['code' => 'config', 'key' => 'demo.enabled', 'value' => '1']],
            $db->rows
        );

        foreach ($db->sql as $sql) {
            self::assertStringContainsString("`code` = 'extension'", $sql);
        }
    }

    public function testOddRowsAreIgnoredInsteadOfBreakingTheSite(): void {
        [$store, $db] = $this->store();
        $db->put('../x.enabled', '1');
        $db->put('Demo.enabled', '1');
        $db->put('demo.enabled.extra', '1');
        $db->put('demo.enabled', 'yes');
        $db->put('demo.config', '{quebrado');
        $db->put('demo.secret.', 'x');
        $db->put('demo.secret.vazio', '');
        $db->put('demo.outra', 'x');
        $db->put('semponto', '1');

        self::assertSame(['demo' => ['enabled' => false, 'config' => [], 'secrets' => []]], array_intersect_key($store->all(), ['demo' => 1]));
        self::assertArrayNotHasKey('Demo', $store->all());
        self::assertArrayNotHasKey('../x', $store->all());
    }

    public function testOnlyTheExactValueOneMeansEnabled(): void {
        [$store, $db] = $this->store();

        foreach (['true', 'on', '01', ' 1', '2'] as $i => $value) {
            $db->put('ext' . $i . 'x.enabled', $value);
        }

        foreach ($store->all() as $row) {
            self::assertFalse($row['enabled']);
        }
    }

    public function testExtensionsComeSortedByCode(): void {
        [$store] = $this->store();
        $store->setEnabled('zeta', true);
        $store->setEnabled('alfa', true);
        $store->setEnabled('meio', false);

        self::assertSame(['alfa', 'meio', 'zeta'], array_keys($store->all()));
    }

    public function testInvalidCodesAreNeverWritten(): void {
        [$store, $db] = $this->store();

        foreach (['', '..', 'Demo', "demo' OR '1'='1", 'a/b', 'demo.config'] as $code) {
            $store->setEnabled($code, true);
            $store->saveConfig($code, ['a' => 'b']);
            $store->saveSecret($code, 'token', 'x');
            $store->deleteSecret($code, 'token');
            $store->purge($code);
        }

        self::assertSame([], $db->rows);
        self::assertSame([], $db->sql, 'nem chega ao banco');
    }

    public function testInvalidFieldNamesAreNeverWritten(): void {
        [$store, $db] = $this->store();
        $store->saveSecret('demo', 'ok', 'fica');

        foreach (['', 'a.b', 'Token', "t' OR '1'='1", '%', str_repeat('k', 41), "token\n", '1a'] as $field) {
            $store->saveSecret('demo', $field, 'x');
            $store->deleteSecret('demo', $field);
        }

        self::assertSame([['code' => 'extension', 'key' => 'demo.secret.ok', 'value' => 'fica']], $db->rows);
        self::assertCount(2, $db->sql, 'so os dois comandos da gravacao valida');
    }

    public function testSecretIsSealedWithItsOwnContext(): void {
        $box = new SecretBox(self::KEY);
        [$store, $db] = $this->store($box);

        $store->saveSecret('demo', 'token', 'valor-muito-secreto');

        $stored = (string)$db->value('demo.secret.token');

        self::assertTrue(SecretBox::isSealed($stored));
        self::assertStringNotContainsString('valor-muito-secreto', implode("\n", $db->sql), 'o texto puro nao passa pelo SQL');
        self::assertSame('valor-muito-secreto', $box->open($stored, SecretBox::extensionContext('demo', 'token')));
        self::assertSame('', $box->open($stored, SecretBox::extensionContext('demo', 'outro')), 'nao abre como outro campo');
        self::assertSame('', $box->open($stored, SecretBox::extensionContext('outra', 'token')), 'nao abre como outra extensao');
        self::assertSame(['token' => $stored], $store->all()['demo']['secrets']);
    }

    public function testContextCannotCollideBetweenExtensionAndField(): void {
        // Codigo nao tem ponto e chave de campo nao tem ponto: "a.b_c" so tem uma leitura.
        self::assertSame('extension.demo.token', SecretBox::extensionContext('demo', 'token'));
        self::assertNotSame(SecretBox::extensionContext('ab', 'c'), SecretBox::extensionContext('a', 'bc'));
    }

    public function testWithoutKeyTheSecretStaysReadableAsInAdr0094(): void {
        [$store, $db] = $this->store(new SecretBox(''));

        $store->saveSecret('demo', 'token', 'sem-chave');

        self::assertSame('sem-chave', $db->value('demo.secret.token'));
    }

    public function testDeleteSecretRemovesOnlyThatField(): void {
        [$store, $db] = $this->store();
        $store->saveSecret('demo', 'token', 'a');
        $store->saveSecret('demo', 'token2', 'b');
        $store->saveSecret('outra', 'token', 'c');

        $store->deleteSecret('demo', 'token');

        self::assertNull($db->value('demo.secret.token'));
        self::assertSame('b', $db->value('demo.secret.token2'));
        self::assertSame('c', $db->value('outra.secret.token'));
    }

    public function testPurgeRemovesEverythingOfOneExtensionOnly(): void {
        [$store, $db] = $this->store();

        foreach (['a_bc', 'axbc', 'a_bcd'] as $code) {
            self::assertTrue(\Reamur\System\Library\Extension\Manifest::isCode($code), $code);
            $store->setEnabled($code, true);
            $store->saveConfig($code, ['k' => 'v']);
            $store->saveSecret($code, 'token', 's');
        }

        $store->purge('a_bc');

        // "_" e curinga do LIKE: sem escape, "a_bc" levaria "axbc" junto.
        self::assertSame(['a_bcd', 'axbc'], array_keys($store->all()));
        self::assertCount(6, $db->rows);
    }

    public function testWithoutDatabaseNothingHappens(): void {
        $store = new Store(new Registry());

        $store->setEnabled('demo', true);
        $store->purge('demo');

        self::assertSame([], $store->all());
    }
}
