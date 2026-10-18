<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * catalog/page (CATALOG MODEL) — leitura pública de páginas CMS (F2-17):
 * apenas ATIVAS (status=1), coalesce idioma ativo → en-US.
 */
namespace Reamur\Catalog\Model\Catalog;

class Page extends \Reamur\System\Engine\Model {

    /**
     * @return array<string, mixed> Página ativa (vazio = inexistente/inativa)
     */
    public function getPage(int $page_id): array {
        $lang  = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT p.`page_id`,
                    COALESCE(NULLIF(pd.`title`, ''), pd_en.`title`, '')                 AS `title`,
                    COALESCE(NULLIF(pd.`description`, ''), pd_en.`description`, '')     AS `description`,
                    COALESCE(NULLIF(pd.`meta_title`, ''), pd_en.`meta_title`, '')       AS `meta_title`,
                    COALESCE(NULLIF(pd.`meta_description`, ''), pd_en.`meta_description`, '') AS `meta_description`
               FROM `" . $this->t('page') . "` p
          LEFT JOIN `" . $this->t('page_description') . "` pd
                 ON pd.`page_id` = p.`page_id` AND pd.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('page_description') . "` pd_en
                 ON pd_en.`page_id` = p.`page_id` AND pd_en.`language_id` = 1
              WHERE p.`page_id` = '" . $page_id . "' AND p.`status` = '1'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
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
