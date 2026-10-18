<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * tool/tool (CATALOG) — ferramentas conectadas ATIVAS públicas (F2-17):
 * ícone + nome/descrita coalesce idioma ativo → en-US + URL externa.
 */
namespace Reamur\Catalog\Model\Tool;

class Tool extends \Reamur\System\Engine\Model {

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTools(): array {
        $lang  = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT t.`tool_id`, t.`image`, t.`vendor`, t.`url`,
                    COALESCE(NULLIF(td.`name`, ''), td_en.`name`, '')             AS `name`,
                    COALESCE(NULLIF(td.`description`, ''), td_en.`description`, '') AS `description`
               FROM `" . $this->t('tool') . "` t
          LEFT JOIN `" . $this->t('tool_description') . "` td
                 ON td.`tool_id` = t.`tool_id` AND td.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('tool_description') . "` td_en
                 ON td_en.`tool_id` = t.`tool_id` AND td_en.`language_id` = 1
              WHERE t.`status` = '1'
              ORDER BY t.`sort_order` ASC, t.`tool_id` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    private function currentLanguageId(): int {
        static $cache = 0;

        if ($cache > 0) {
            return $cache;
        }

        $query = $this->db->query(
            "SELECT `language_id` FROM `" . $this->t('language') . "`
             WHERE `code` = '" . $this->db->escape((string)$this->config->get('language_directory', 'en-us')) . "' LIMIT 1"
        );

        $cache = ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['language_id'] : 1;

        return $cache;
    }

    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
