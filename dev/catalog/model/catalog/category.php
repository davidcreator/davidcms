<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/category (CATALOG) — categorias ATIVAS com projeto para o filtro
 * público do portfólio (F2-17). Coalesce idioma ativo → en-US.
 */
namespace Reamur\Catalog\Model\Catalog;

class Category extends \Reamur\System\Engine\Model {

    /**
     * @return array<int, array<string, mixed>> Categorias ativas que têm projeto ativo
     */
    public function getCategoriesWithProjects(): array {
        $lang  = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT c.`category_id`,
                    COALESCE(NULLIF(cd.`name`, ''), cd_en.`name`, '') AS `name`
               FROM `" . $this->t('category') . "` c
          LEFT JOIN `" . $this->t('category_description') . "` cd
                 ON cd.`category_id` = c.`category_id` AND cd.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('category_description') . "` cd_en
                 ON cd_en.`category_id` = c.`category_id` AND cd_en.`language_id` = 1
              WHERE c.`status` = '1'
                AND EXISTS (
                      SELECT 1
                        FROM `" . $this->t('project_to_category') . "` p2c
                        JOIN `" . $this->t('project') . "` p ON p.`project_id` = p2c.`project_id` AND p.`status` = '1'
                       WHERE p2c.`category_id` = c.`category_id`
                )
              ORDER BY c.`sort_order` ASC, c.`category_id` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

}
