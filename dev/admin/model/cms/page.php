<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Model cms/page — CRUD de páginas de conteúdo/information (F2-10, ADR 0010).
 * page + page_description (multi-idioma) + seo_url(key=page_id=N).
 * Zero DDL: tabelas já instaladas pelo wizard (Dicionario-de-Dados §cms/).
 */
namespace Reamur\Admin\Model\Cms;

class Page extends \Reamur\System\Engine\Model {

    /**
     * Lista para a grade (título preferindo o idioma ativo, coalesce EN).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPages(): array {
        $lang = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT p.`page_id`, p.`status`, p.`sort_order`, p.`date_modified`,
                    COALESCE(NULLIF(pd.`title`, ''), pd_en.`title`, '') AS `title`
             FROM `" . $this->t('page') . "` p
             LEFT JOIN `" . $this->t('page_description') . "` pd
                 ON pd.`page_id` = p.`page_id` AND pd.`language_id` = '" . $lang . "'
             LEFT JOIN `" . $this->t('page_description') . "` pd_en
                 ON pd_en.`page_id` = p.`page_id` AND pd_en.`language_id` = 1
             ORDER BY p.`sort_order` ASC, p.`page_id` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * @return array<string, mixed> Linha principal (vazio = inexistente)
     */
    public function getPage(int $page_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('page') . "` WHERE `page_id` = '" . $page_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @return array<int, array<string, mixed>> indexado por language_id
     */
    public function getPageDescriptions(int $page_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('page_description') . "` WHERE `page_id` = '" . $page_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $out[(int)$row['language_id']] = (array)$row;
        }

        return $out;
    }

    /**
     * keyword (seo_url.value) por idioma — key = 'page_id=N'.
     *
     * @return array<int, string> indexado por language_id
     */
    public function getPageKeywords(int $page_id): array {
        $query = $this->db->query(
            "SELECT `language_id`, `value` FROM `" . $this->t('seo_url') . "` WHERE `key` = 'page_id=" . $page_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $out[(int)$row['language_id']] = (string)$row['value'];
        }

        return $out;
    }

    /**
     * keyword já em uso naquele idioma por OUTRA entidade (seo_url é global)?
     */
    public function keywordTaken(string $keyword, int $language_id, int $exclude_page_id): bool {
        $query = $this->db->query(
            "SELECT `key` FROM `" . $this->t('seo_url') . "`
             WHERE `value` = '" . $this->db->escape($keyword) . "' AND `language_id` = '" . $language_id . "'
               AND `key` <> 'page_id=" . $exclude_page_id . "' LIMIT 1"
        );

        return $query instanceof \stdClass && !empty($query->row);
    }

    /**
     * Idiomas cadastrados (abas do form). Independe do status do front.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLanguages(): array {
        $query = $this->db->query(
            "SELECT `language_id`, `name`, `code` FROM `" . $this->t('language') . "` ORDER BY `sort_order` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function addPage(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('page') . "` SET
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order` = '" . (int)$data['sort_order'] . "',
                `date_added` = NOW(),
                `date_modified` = NOW()"
        );

        $page_id = $this->db->getLastId();

        $this->syncDescriptions($page_id, (array)$data['descriptions']);

        return $page_id;
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function editPage(int $page_id, array $data): void {
        $this->db->query(
            "UPDATE `" . $this->t('page') . "` SET
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order` = '" . (int)$data['sort_order'] . "',
                `date_modified` = NOW()
             WHERE `page_id` = '" . $page_id . "'"
        );

        $this->db->query("DELETE FROM `" . $this->t('page_description') . "` WHERE `page_id` = '" . $page_id . "'");

        $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'page_id=" . $page_id . "'");

        $this->syncDescriptions($page_id, (array)$data['descriptions']);
    }

    public function deletePage(int $page_id): void {
        $this->db->query("DELETE FROM `" . $this->t('page') . "` WHERE `page_id` = '" . $page_id . "'");

        $this->db->query("DELETE FROM `" . $this->t('page_description') . "` WHERE `page_id` = '" . $page_id . "'");

        $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'page_id=" . $page_id . "'");
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * Descrições (uma por idioma) + keywords SEO — TEXT guard é só o escape.
     *
     * @param array<int, array<string, string>> $descriptions
     */
    private function syncDescriptions(int $page_id, array $descriptions): void {
        foreach ($descriptions as $language_id => $d) {
            $d = (array)$d;

            $this->db->query(
                "INSERT INTO `" . $this->t('page_description') . "` SET
                    `page_id`          = '" . $page_id . "',
                    `language_id`      = '" . (int)$language_id . "',
                    `title`            = '" . $this->db->escape((string)$d['title']) . "',
                    `description`      = '" . $this->db->escape((string)$d['description']) . "',
                    `meta_title`       = '" . $this->db->escape((string)$d['meta_title']) . "',
                    `meta_description` = '" . $this->db->escape((string)$d['meta_description']) . "'"
            );

            $keyword = trim((string)$d['keyword']);

            if ($keyword !== '') {
                $this->db->query(
                    "INSERT INTO `" . $this->t('seo_url') . "` SET
                        `key`         = 'page_id=" . $page_id . "',
                        `value`       = '" . $this->db->escape($keyword) . "',
                        `language_id` = '" . (int)$language_id . "',
                        `sort_order`  = 0"
                );
            }
        }
    }

    private function t(string $table): string {
        return DB_PREFIX . $table;
    }

    /** language_id do idioma ATIVO no admin (language_directory → rms_language) */
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
}
