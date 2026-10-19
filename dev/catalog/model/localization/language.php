<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Reader do catálogo (F4-02, ADR 0025): idiomas ATIVOS (status=1) e com
 * diretório real. Usado pelo startup/language (gate + default) e pelo
 * switcher do header — antes disto, a coluna status era ignorada e o
 * switcher refletia o filesystem direto.
 */
namespace Reamur\Catalog\Model\Localization;

class Language extends \Reamur\System\Engine\Model {

    /**
     * Códigos dos idiomas habilitados publicamente:
     * status = 1 (rms_language) E diretório presente.
     *
     * @return array<int, string> ex.: ['en-us','pt-br']
     */
    public function getEnabledCodes(): array {
        $codes = [];

        $query = $this->db->query(
            "SELECT `code` FROM `" . DB_PREFIX . "language`
             WHERE `status` = 1
             ORDER BY `sort_order` ASC, `language_id` ASC"
        );

        foreach ($query->rows as $row) {
            $code = (string)$row['code'];

            if (is_dir(DIR_APPLICATION . 'language/' . $code)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
