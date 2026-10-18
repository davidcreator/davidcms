<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * marketing/testimonial (CATALOG) — depoimentos ATIVOS públicos (F2-17).
 * Coalesce idioma ativo → en-US (author/company/text).
 */
namespace Reamur\Catalog\Model\Marketing;

class Testimonial extends \Reamur\System\Engine\Model {

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTestimonials(): array {
        $lang  = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT t.`testimonial_id`, t.`image`, t.`sort_order`,
                    COALESCE(NULLIF(td.`author`, ''), td_en.`author`, '')     AS `author`,
                    COALESCE(NULLIF(td.`company`, ''), td_en.`company`, '')   AS `company`,
                    COALESCE(NULLIF(td.`text`, ''), td_en.`text`, '')         AS `text`
               FROM `" . $this->t('testimonial') . "` t
          LEFT JOIN `" . $this->t('testimonial_description') . "` td
                 ON td.`testimonial_id` = t.`testimonial_id` AND td.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('testimonial_description') . "` td_en
                 ON td_en.`testimonial_id` = t.`testimonial_id` AND td_en.`language_id` = 1
              WHERE t.`status` = '1'
              ORDER BY t.`sort_order` ASC, t.`testimonial_id` ASC"
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
