<?php declare(strict_types=1);

namespace Reamur\Tests\Unit\Extension;

use PHPUnit\Framework\TestCase;
use Reamur\System\Engine\Config;
use Reamur\System\Engine\Event;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Extension\Manager;
use Reamur\System\Library\Response;
use Reamur\System\Library\Security\Csp;
use Reamur\System\Library\Security\SecretBox;

/**
 * EX-01 (ADR 0095): apoio dos testes de extensao. Cria pastas de extensao de
 * verdade num diretorio temporario (o Manager le arquivos, nao simulacoes) e
 * um banco de mentira que guarda as linhas de rms_setting em memoria,
 * entendendo exatamente os comandos que o Store emite.
 */
abstract class ExtensionTestCase extends TestCase {
    private static int $serial = 0;

    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void {
        foreach ($this->temp as $dir) {
            self::remove($dir);
        }

        $this->temp = [];
    }

    private static function remove(string $path): void {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $name) {
                if ($name !== '.' && $name !== '..') {
                    self::remove($path . '/' . $name);
                }
            }

            rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }

    /** Pasta de extensoes vazia. */
    protected function folder(): string {
        $dir = sys_get_temp_dir() . '/rms-ex01-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        $this->temp[] = $dir;

        return $dir . '/';
    }

    /** Codigo de extensao nunca usado nesta execucao (a classe PHP de cada um so pode ser declarada uma vez). */
    protected static function code(): string {
        return 'tx' . (++self::$serial);
    }

    protected static function classOf(string $code): string {
        return \Reamur\System\Library\Extension\Manifest::className($code);
    }

    /**
     * Manifesto minimo valido, com o que o teste quiser trocar.
     *
     * @param array<string, mixed> $over
     * @return array<string, mixed>
     */
    protected static function manifest(string $code, array $over = []): array {
        return $over + [
            'schema'  => 1,
            'code'    => $code,
            'name'    => 'Extensao ' . $code,
            'version' => '1.2.3',
        ];
    }

    /**
     * Grava uma extensao na pasta: extension.json e, se dado, extension.php.
     *
     * @param array<string, mixed>|string $manifest array (vira JSON) ou o texto cru do arquivo
     * @param string|null                 $body     corpo da classe (metodos); null = sem extension.php
     */
    protected function install(string $folder, string $code, array|string $manifest, ?string $body = ''): void {
        if (!is_dir($folder . $code)) {
            mkdir($folder . $code, 0777, true);
        }

        file_put_contents(
            $folder . $code . '/extension.json',
            is_array($manifest) ? json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $manifest
        );

        if ($body !== null) {
            file_put_contents($folder . $code . '/extension.php', self::source($code, $body));
        }
    }

    protected static function source(string $code, string $body): string {
        return "<?php declare(strict_types=1);\nnamespace " . substr(self::classOf($code), 0, -strlen('\\Extension')) . ";\n\n"
            . "final class Extension extends \\Reamur\\System\\Library\\Extension\\Extension {\n"
            . "    /** @var list<mixed> */\n    public static array \$seen = [];\n"
            . "    public function peek(string \$key): string|bool { return \$this->setting(\$key); }\n"
            . "    public function peekLocked(string \$key): bool { return \$this->secretLocked(\$key); }\n"
            . "    public function peekApp(): string { return \$this->app(); }\n"
            . "    public function say(string \$m): void { \$this->log(\$m); }\n"
            . $body . "\n}\n";
    }

    /** Banco de mentira: linhas de rms_setting em memoria + o SQL que passou. */
    protected function db(): object {
        return new class {
            /** @var list<array{code:string, key:string, value:string}> */
            public array $rows = [];
            /** @var list<string> */
            public array $sql = [];
            public ?\Throwable $fail = null;

            public function escape(string $value): string {
                return addslashes($value);
            }

            public function query(string $sql): \stdClass {
                $this->sql[] = $sql;

                if ($this->fail !== null) {
                    throw $this->fail;
                }

                $res = new \stdClass();
                $res->rows = [];
                $res->row = [];
                $res->num_rows = 0;

                if (preg_match("/^SELECT `key`, `value` FROM `rms_setting` WHERE `code` = '([a-z]+)'$/", trim($sql), $m)) {
                    foreach ($this->rows as $row) {
                        if ($row['code'] === $m[1]) {
                            $res->rows[] = ['key' => $row['key'], 'value' => $row['value']];
                        }
                    }
                } elseif (preg_match("/^UPDATE `rms_setting` SET `value` = '(.*)', `serialized` = '0'\\s+WHERE `code` = '([a-z]+)' AND `key` = '(.*)'$/s", trim($sql), $m)) {
                    foreach ($this->rows as $i => $row) {
                        if ($row['code'] === $m[2] && $row['key'] === stripslashes($m[3])) {
                            $this->rows[$i]['value'] = stripslashes($m[1]);
                        }
                    }
                } elseif (preg_match("/^INSERT INTO `rms_setting` \\(`code`, `key`, `value`, `serialized`\\)\\s+SELECT '([a-z]+)', '(.*?)', '(.*)', '0' FROM DUAL\\s+WHERE NOT EXISTS/s", trim($sql), $m)) {
                    $exists = false;

                    foreach ($this->rows as $row) {
                        $exists = $exists || ($row['code'] === $m[1] && $row['key'] === stripslashes($m[2]));
                    }

                    if (!$exists) {
                        $this->rows[] = ['code' => $m[1], 'key' => stripslashes($m[2]), 'value' => stripslashes($m[3])];
                    }
                } elseif (preg_match("/^DELETE FROM `rms_setting`\\s+WHERE `code` = '([a-z]+)' AND `key` = '(.*)'$/s", trim($sql), $m)) {
                    $this->rows = array_values(array_filter(
                        $this->rows,
                        static fn (array $row): bool => !($row['code'] === $m[1] && $row['key'] === stripslashes($m[2]))
                    ));
                } elseif (preg_match("/^DELETE FROM `rms_setting`\\s+WHERE `code` = '([a-z]+)' AND `key` LIKE '(.*)'$/s", trim($sql), $m)) {
                    // LIKE do MySQL: \_ e \% literais, _ um caractere, % qualquer coisa.
                    $like  = stripslashes($m[2]);
                    $regex = '';

                    for ($i = 0, $n = strlen($like); $i < $n; $i++) {
                        $c = $like[$i];

                        if ($c === '\\' && $i + 1 < $n) {
                            $regex .= preg_quote($like[++$i], '/');
                        } elseif ($c === '%') {
                            $regex .= '.*';
                        } elseif ($c === '_') {
                            $regex .= '.';
                        } else {
                            $regex .= preg_quote($c, '/');
                        }
                    }

                    $this->rows = array_values(array_filter(
                        $this->rows,
                        static fn (array $row): bool => !($row['code'] === $m[1] && preg_match('/^' . $regex . '$/s', $row['key']) === 1)
                    ));
                } else {
                    throw new \LogicException('SQL que o banco de mentira nao conhece: ' . $sql);
                }

                $res->num_rows = count($res->rows);
                $res->row      = $res->rows[0] ?? [];

                return $res;
            }

            /** Valor gravado de uma chave do code extension (null = nao existe). */
            public function value(string $key): ?string {
                foreach ($this->rows as $row) {
                    if ($row['code'] === 'extension' && $row['key'] === $key) {
                        return $row['value'];
                    }
                }

                return null;
            }

            public function put(string $key, string $value, string $code = 'extension'): void {
                $this->rows[] = ['code' => $code, 'key' => $key, 'value' => $value];
            }
        };
    }

    protected function log(): object {
        return new class {
            /** @var list<string> */
            public array $lines = [];

            public function write(string $message): void {
                $this->lines[] = $message;
            }

            public function all(): string {
                return implode("\n", $this->lines);
            }
        };
    }

    /**
     * Registry com o que o Manager usa. Devolve tambem as pecas, para o teste olhar.
     *
     * @return array{0: Registry, 1: object, 2: object}  registry, banco, log
     */
    protected function registry(string $app = 'catalog', ?SecretBox $box = null, mixed $cspSources = []): array {
        $db       = $this->db();
        $log      = $this->log();
        $registry = new Registry();

        $registry->set('db', $db);
        $registry->set('log', $log);
        $registry->set('config', new Config());
        $registry->set('event', new Event());
        $registry->set('response', new Response());
        $registry->set('csp', new Csp($app, 'enforce', $cspSources));

        if ($box !== null) {
            $registry->set('secret_box', $box);
        }

        return [$registry, $db, $log];
    }

    protected function manager(Registry $registry, string $folder, string $cms = '1.0.0', string $php = '8.2.0'): Manager {
        $manager = new Manager($registry, $folder, $cms, $php);
        $registry->set('extensions', $manager);

        return $manager;
    }
}
