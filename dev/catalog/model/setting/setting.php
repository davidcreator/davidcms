<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/setting (CATALOG) — reader rápido de rms_setting code='config'
 * (F4-01, ADR 0024): alimenta o pre-action startup/setting que sobrescreve
 * os defaults na request corrente. Leitura única por request (sem cache aqui:
 * a velocidade vem do SELECT único em chave PK indexada).
 */
namespace Reamur\Catalog\Model\Setting;

class Setting extends \Reamur\System\Engine\Model {

    private const CODE = 'config';

    /**
     * Todas as chaves do code 'config' (mapa key=>value).
     *
     * @return array<string, string>
     */
    public function getSettings(): array {
        $rows = $this->db->query(
            "SELECT `key`, `value`, `serialized` FROM `" . DB_PREFIX . "setting` WHERE `code` = '" . self::CODE . "'"
        );

        $map = [];

        if ($rows instanceof \stdClass && !empty($rows->rows)) {
            foreach ($rows->rows as $row) {
                $row = (array)$row;

                $map[(string)$row['key']] = $this->decodeSettingValue((string)$row['value'], (int)$row['serialized']);
            }
        }

        return $map;
    }

    private function decodeSettingValue(string $value, int $serialized): string {
        if ($serialized === 0) {
            return $value;
        }

        if ($value === 'b:0;') {
            return '0';
        }

        if ($value === '' || preg_match('/^(?:[abisdOCrR]:|N;)/', $value) !== 1) {
            return '';
        }

        $decoded = @unserialize($value, ['allowed_classes' => false]);
        if ($decoded === false) {
            return '';
        }

        if (is_bool($decoded)) {
            return $decoded ? '1' : '0';
        }

        if (is_scalar($decoded)) {
            return (string)$decoded;
        }

        if (is_array($decoded)) {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return '';
    }
}
