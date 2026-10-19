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

                $map[(string)$row['key']] = ((int)$row['serialized'])
                    ? (string)unserialize((string)$row['value'], ['allowed_classes' => false])
                    : (string)$row['value'];
            }
        }

        return $map;
    }
}
