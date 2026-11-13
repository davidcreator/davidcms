<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * EX-01 (ADR 0095): onde fica o estado das extensões — ligada ou não, valores
 * de configuração e campos-segredo. Usa a tabela rms_setting que já existe,
 * com `code = 'extension'` (nenhuma alteração de banco):
 *
 *   <código>.enabled          '1' | '0'
 *   <código>.config           JSON com os campos que não são segredo
 *   <código>.secret.<campo>   valor cifrado pelo SecretBox (ADR 0094)
 *
 * Lida pelos dois lados (site público e admin) com UM SELECT por requisição e
 * sem cache: o que o admin grava vale na requisição seguinte do site.
 */
namespace Reamur\System\Library\Extension;

use Reamur\System\Engine\Registry;
use Reamur\System\Library\Security\SecretBox;

final class Store {
    public const CODE = 'extension';

    public function __construct(private Registry $registry) {}

    /**
     * Tudo o que está gravado, por extensão. Os segredos vêm como estão no
     * banco (cifrados); quem abre é o Manager, na hora de ligar a extensão.
     *
     * @return array<string, array{enabled: bool, config: array<string, mixed>, secrets: array<string, string>}>
     */
    public function all(): array {
        $db = $this->db();

        if ($db === null) {
            return [];
        }

        $query = $db->query(
            "SELECT `key`, `value` FROM `" . $this->table() . "` WHERE `code` = '" . self::CODE . "'"
        );

        $out = [];

        if (!$query instanceof \stdClass || empty($query->rows)) {
            return $out;
        }

        foreach ((array)$query->rows as $row) {
            $row   = (array)$row;
            $parts = explode('.', (string)($row['key'] ?? ''), 3);
            $code  = $parts[0];
            $what  = $parts[1] ?? '';
            $value = (string)($row['value'] ?? '');

            if (!Manifest::isCode($code)) {
                continue;
            }

            $out[$code] ??= ['enabled' => false, 'config' => [], 'secrets' => []];

            if ($what === 'enabled' && !isset($parts[2])) {
                $out[$code]['enabled'] = $value === '1';
            } elseif ($what === 'config' && !isset($parts[2])) {
                $decoded = json_decode($value, true, 8);

                $out[$code]['config'] = is_array($decoded) ? $decoded : [];
            } elseif ($what === 'secret' && isset($parts[2]) && $parts[2] !== '' && $value !== '') {
                $out[$code]['secrets'][$parts[2]] = $value;
            }
        }

        ksort($out, SORT_STRING);

        return $out;
    }

    public function setEnabled(string $code, bool $enabled): void {
        if (Manifest::isCode($code)) {
            $this->write($code . '.enabled', $enabled ? '1' : '0');
        }
    }

    /** @param array<string, string|bool> $values campos que não são segredo */
    public function saveConfig(string $code, array $values): void {
        if (!Manifest::isCode($code)) {
            return;
        }

        $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $this->write($code . '.config', $json === false ? '{}' : $json);
    }

    /**
     * Grava um campo-segredo cifrado. Sem chave no config.php o SecretBox
     * devolve o texto como veio (comportamento da ADR 0094): o valor fica
     * legível até "Proteger segredos" ser usado, e o painel avisa.
     */
    public function saveSecret(string $code, string $field, #[\SensitiveParameter] string $plain): void {
        if (!Manifest::isCode($code) || !self::isField($field)) {
            return;
        }

        $this->write(
            $code . '.secret.' . $field,
            $this->box()->seal($plain, SecretBox::extensionContext($code, $field))
        );
    }

    public function deleteSecret(string $code, string $field): void {
        $db = $this->db();

        if ($db === null || !Manifest::isCode($code) || !self::isField($field)) {
            return;
        }

        $db->query(
            "DELETE FROM `" . $this->table() . "`
              WHERE `code` = '" . self::CODE . "' AND `key` = '" . $db->escape($code . '.secret.' . $field) . "'"
        );
    }

    /** Apaga tudo o que a extensão gravou (estado, configuração e segredos). */
    public function purge(string $code): void {
        $db = $this->db();

        if ($db === null || !Manifest::isCode($code)) {
            return;
        }

        // `_` é curinga do LIKE e faz parte dos códigos: sem o escape,
        // apagar "a_b" levaria junto os dados de "axb".
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $code) . '.%';

        $db->query(
            "DELETE FROM `" . $this->table() . "`
              WHERE `code` = '" . self::CODE . "' AND `key` LIKE '" . $db->escape($like) . "'"
        );
    }

    /**
     * Chave de campo, na mesma forma que o manifesto aceita. Código e campo
     * são conferidos em cada entrada pública: nem um nem outro pode trazer
     * ponto, que é o que separa as partes da chave gravada.
     */
    private static function isField(string $field): bool {
        return preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $field) === 1;
    }

    private function write(string $key, string $value): void {
        $db = $this->db();

        if ($db === null || strlen($key) > 128) {
            return;
        }

        $k = $db->escape($key);
        $v = $db->escape($value);

        // Mesmo par de comandos de setting/setting: não depende do índice único,
        // que instalações antigas podem não ter.
        $db->query(
            "UPDATE `" . $this->table() . "` SET `value` = '" . $v . "', `serialized` = '0'
              WHERE `code` = '" . self::CODE . "' AND `key` = '" . $k . "'"
        );

        $db->query(
            "INSERT INTO `" . $this->table() . "` (`code`, `key`, `value`, `serialized`)
             SELECT '" . self::CODE . "', '" . $k . "', '" . $v . "', '0' FROM DUAL
              WHERE NOT EXISTS (
                    SELECT 1 FROM `" . $this->table() . "` WHERE `code` = '" . self::CODE . "' AND `key` = '" . $k . "'
              )"
        );
    }

    private function table(): string {
        return (defined('DB_PREFIX') ? (string)DB_PREFIX : 'rms_') . 'setting';
    }

    /** O serviço db do Registry (ou um substituto de teste com query() e escape()). */
    private function db(): mixed {
        $db = $this->registry->get('db');

        return (is_object($db) && method_exists($db, 'query') && method_exists($db, 'escape')) ? $db : null;
    }

    private function box(): SecretBox {
        $box = $this->registry->get('secret_box');

        return $box instanceof SecretBox ? $box : new SecretBox();
    }
}
