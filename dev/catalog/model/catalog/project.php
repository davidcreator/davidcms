<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/project (CATALOG) — leitura pública do portfólio (F2-17): apenas
 * projetos ATIVOS (status=1), coalesce idioma ativo → en-US, galeria e
 * categorias por projeto. Nenhuma escrita aqui — o CRUD fica no admin.
 */
namespace Reamur\Catalog\Model\Catalog;

class Project extends \Reamur\System\Engine\Model {

    /**
     * Grade pública ordenada (destaques primeiro, depois sort_order).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProjects(?int $category_id = null): array {
        $lang = $this->currentLanguageId();

        $sql = "SELECT DISTINCT p.`project_id`, p.`image`, p.`client`, p.`url`, p.`featured`, p.`sort_order`,
                       COALESCE(NULLIF(pd.`name`, ''), pd_en.`name`, '')       AS `name`,
                       COALESCE(NULLIF(pd.`summary`, ''), pd_en.`summary`, '') AS `summary`
                  FROM `" . $this->t('project') . "` p
             LEFT JOIN `" . $this->t('project_description') . "` pd
                    ON pd.`project_id` = p.`project_id` AND pd.`language_id` = '" . $lang . "'
             LEFT JOIN `" . $this->t('project_description') . "` pd_en
                    ON pd_en.`project_id` = p.`project_id` AND pd_en.`language_id` = 1";

        if ($category_id !== null && $category_id > 0) {
            $sql .= " JOIN `" . $this->t('project_to_category') . "` p2c
                         ON p2c.`project_id` = p.`project_id` AND p2c.`category_id` = '" . $category_id . "'";
        }

        $sql .= " WHERE p.`status` = '1'
               ORDER BY p.`featured` DESC, p.`sort_order` ASC, p.`project_id` ASC";

        $query = $this->db->query($sql);

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * Projeto ATIVO com descrição coalesce (vazio = inexistente/inativo).
     *
     * @return array<string, mixed>
     */
    public function getProject(int $project_id): array {
        $lang  = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT p.*,
                    COALESCE(NULLIF(pd.`name`, ''), pd_en.`name`, '')                 AS `name`,
                    COALESCE(NULLIF(pd.`summary`, ''), pd_en.`summary`, '')           AS `summary`,
                    COALESCE(NULLIF(pd.`description`, ''), pd_en.`description`, '')   AS `description`,
                    COALESCE(NULLIF(pd.`tag`, ''), pd_en.`tag`, '')                   AS `tag`,
                    COALESCE(NULLIF(pd.`meta_title`, ''), pd_en.`meta_title`, pd.`name`, '')     AS `meta_title`,
                    COALESCE(NULLIF(pd.`meta_description`, ''), pd_en.`meta_description`, '')    AS `meta_description`
               FROM `" . $this->t('project') . "` p
          LEFT JOIN `" . $this->t('project_description') . "` pd
                 ON pd.`project_id` = p.`project_id` AND pd.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('project_description') . "` pd_en
                 ON pd_en.`project_id` = p.`project_id` AND pd_en.`language_id` = 1
              WHERE p.`project_id` = '" . $project_id . "' AND p.`status` = '1'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @return array<int, array<string, mixed>> Galeria ordenada
     */
    public function getProjectImages(int $project_id): array {
        $query = $this->db->query(
            "SELECT `image`, `alt` FROM `" . $this->t('project_image') . "`
              WHERE `project_id` = '" . $project_id . "'
              ORDER BY `sort_order` ASC, `project_image_id` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * Categorias do projeto (para chips na página dedicada).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProjectCategories(int $project_id): array {
        $lang  = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT c.`category_id`,
                    COALESCE(NULLIF(cd.`name`, ''), cd_en.`name`, '') AS `name`
               FROM `" . $this->t('project_to_category') . "` p2c
               JOIN `" . $this->t('category') . "` c
                 ON c.`category_id` = p2c.`category_id` AND c.`status` = '1'
          LEFT JOIN `" . $this->t('category_description') . "` cd
                 ON cd.`category_id` = c.`category_id` AND cd.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('category_description') . "` cd_en
                 ON cd_en.`category_id` = c.`category_id` AND cd_en.`language_id` = 1
              WHERE p2c.`project_id` = '" . $project_id . "'
              ORDER BY c.`sort_order` ASC, c.`category_id` ASC"
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
