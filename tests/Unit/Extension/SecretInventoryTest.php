<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

use PHPUnit\Framework\TestCase;
use Reamur\Admin\Model\Setting\Secret as AdminSecretModel;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Security\SecretBox;

/**
 * EX-01 (ADR 0095): os campos-segredo das extensoes entram no inventario da
 * ADR 0094. "Proteger segredos" os cifra e a troca de chave os regrava, cada
 * um com o contexto da propria extensao e do proprio campo.
 */
final class SecretInventoryTest extends TestCase {
    /**
     * @param list<array{setting_id: int, key: string, value: string}> $extensionRows
     * @return array{0: AdminSecretModel, 1: object}
     */
    private function model(array $extensionRows, SecretBox $box): array {
        $db = new class($extensionRows) {
            /** @var list<string> */
            public array $sql = [];

            /** @param list<array<string, mixed>> $rows */
            public function __construct(private array $rows) {}

            public function escape(string $value): string {
                return addslashes($value);
            }

            public function getLastId(): int {
                return 0;
            }

            public function query(string $sql): \stdClass {
                $this->sql[] = $sql;
                $rows = [];

                if (str_starts_with($sql, 'SHOW TABLES')) {
                    $rows = [['t' => 1]];
                } elseif (str_starts_with(ltrim($sql), 'SELECT') && str_contains($sql, "`code` = 'extension'")) {
                    $rows = $this->rows;
                }

                $res           = new \stdClass();
                $res->rows     = $rows;
                $res->row      = $rows[0] ?? [];
                $res->num_rows = count($rows);

                return $res;
            }
        };

        $registry = new Registry();
        $registry->set('db', $db);
        $registry->set('config', new Config());
        $registry->set('secret_box', $box);
        $registry->set('cache', new class {
            public function get(string $key): mixed { return null; }
            public function set(string $key, mixed $value, int $expire = 0): bool { return true; }
            public function delete(string $key): bool { return true; }
        });

        return [new AdminSecretModel($registry), $db];
    }

    public function testExtensionSecretsAreCountedInTheirOwnGroup(): void {
        $oldKey = SecretBox::generateKey();
        $box    = new SecretBox(SecretBox::generateKey(), $oldKey);
        $old    = new SecretBox($oldKey);
        $alien  = new SecretBox(SecretBox::generateKey());

        [$model] = $this->model([
            ['setting_id' => 11, 'key' => 'demo.secret.token', 'value' => 'legivel-0123456789'],
            ['setting_id' => 12, 'key' => 'demo.secret.outro', 'value' => $box->seal('a-0123456789', SecretBox::extensionContext('demo', 'outro'))],
            ['setting_id' => 13, 'key' => 'tags.secret.token', 'value' => $old->seal('b-0123456789', SecretBox::extensionContext('tags', 'token'))],
            ['setting_id' => 14, 'key' => 'tags.secret.api', 'value' => $alien->seal('c-0123456789', SecretBox::extensionContext('tags', 'api'))],
            // Selado com a chave certa, mas para outro campo: nao abre aqui.
            ['setting_id' => 15, 'key' => 'tags.secret.copiado', 'value' => $box->seal('d-0123456789', SecretBox::extensionContext('demo', 'outro'))],
        ], $box);

        $status = $model->status();

        self::assertSame(['clear' => 1, 'sealed' => 1, 'old' => 1, 'locked' => 2], $status['groups']['extension']);
        self::assertSame(5, $status['total']);

        $flat = (string)json_encode($status);
        self::assertStringNotContainsString('legivel-0123456789', $flat);
        self::assertStringNotContainsString(SecretBox::PREFIX, $flat);
    }

    public function testRowsThatAreNotSecretFieldsStayOut(): void {
        [$model] = $this->model([
            ['setting_id' => 1, 'key' => 'demo.config', 'value' => '{"a":"b.secret.c"}'],
            ['setting_id' => 2, 'key' => 'demo.enabled', 'value' => '1'],
            ['setting_id' => 3, 'key' => 'demo.secret.', 'value' => 'x'],
            ['setting_id' => 4, 'key' => '.secret.token', 'value' => 'x'],
            ['setting_id' => 5, 'key' => 'demo.outra.secret.token', 'value' => 'x'],
        ], new SecretBox(SecretBox::generateKey()));

        $status = $model->status();

        self::assertSame(0, $status['total']);
        self::assertArrayNotHasKey('extension', array_filter($status['groups'], static fn (array $g): bool => array_sum($g) > 0));
    }

    public function testProtectSealsEachSecretWithItsOwnContext(): void {
        $oldKey = SecretBox::generateKey();
        $box    = new SecretBox(SecretBox::generateKey(), $oldKey);
        $old    = new SecretBox($oldKey);
        $done   = $box->seal('pronto-0123456789', SecretBox::extensionContext('demo', 'pronto'));

        [$model, $db] = $this->model([
            ['setting_id' => 11, 'key' => 'demo.secret.token', 'value' => 'legivel-0123456789'],
            ['setting_id' => 12, 'key' => 'demo.secret.pronto', 'value' => $done],
            ['setting_id' => 13, 'key' => 'tags.secret.token', 'value' => $old->seal('antiga-0123456789', SecretBox::extensionContext('tags', 'token'))],
        ], $box);

        $result  = $model->protect(true);
        $updates = array_values(array_filter($db->sql, static fn (string $s): bool => str_starts_with(ltrim($s), 'UPDATE')));

        self::assertSame(2, $result['converted']);
        self::assertCount(2, $updates, 'o que ja estava cifrado com a chave atual nao e regravado');

        $written = [];

        foreach ($updates as $sql) {
            preg_match("/UPDATE `rms_setting`\\s+SET `value` = '([^']+)'\\s+WHERE `setting_id` = '(\\d+)'\\s+AND `value` = '([^']+)'/", $sql, $m);
            self::assertNotEmpty($m, $sql);
            $written[(int)$m[2]] = stripslashes($m[1]);
        }

        self::assertSame('legivel-0123456789', $box->open($written[11], SecretBox::extensionContext('demo', 'token')));
        self::assertSame('antiga-0123456789', $box->open($written[13], SecretBox::extensionContext('tags', 'token')));
        self::assertSame('', $box->open($written[11], SecretBox::extensionContext('tags', 'token')), 'nao abre como segredo de outra extensao');
        self::assertFalse($box->locked($written[13], SecretBox::extensionContext('tags', 'token')));
        self::assertTrue($old->locked($written[13], SecretBox::extensionContext('tags', 'token')), 'regravado com a chave nova: a antiga ja nao abre');
    }
}
