<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Model catalog/category — CRUD de categorias do portfólio (F2-9, ADR 0009).
 * category + category_description (multi-idioma) + seo_url(key=category_id=N).
 * Hierarquia simples (parent_id auto-referente). Sem FKs no schema: cascata
 * manual — filhos viram raiz e project_to_category é limpo no delete.
 */
namespace Reamur\Admin\Model\Catalog;

class Category extends \Reamur\System\Engine\Model {

    /**
     * Lista filtrável (F8-07). Sem argumentos continua completa e alfabética.
     * @param array{name?: string, status?: string|int, start?: int, limit?: int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(array $filters = []): array {
        $lang = $this->currentLanguageId();
        $coalesce = $this->categoryNameSql();
        $sql = "SELECT c.`category_id`, c.`parent_id`, c.`image`, c.`status`, c.`sort_order`, c.`date_modified`,
                       " . $coalesce . " AS `name`,
                       (SELECT " . str_replace('cd', 'pd', $coalesce) . "
                        FROM `" . $this->t('category_description') . "` pd
                        LEFT JOIN `" . $this->t('category_description') . "` pd_en
                          ON pd_en.`category_id` = pd.`category_id` AND pd_en.`language_id` = 1
                        WHERE pd.`category_id` = c.`parent_id` AND pd.`language_id` = '" . $lang . "'
                        LIMIT 1) AS `parent_name` "
            . $this->categoryListFrom() . $this->categoryListWhere($filters)
            . " ORDER BY " . $coalesce . " ASC";

        if (array_key_exists('limit', $filters)) {
            $start = max(0, (int)($filters['start'] ?? 0));
            $limit = min(100, max(1, (int)$filters['limit']));
            $sql .= ", c.`category_id` ASC LIMIT " . $start . ", " . $limit;
        }
        $query = $this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @param array{name?: string, status?: string|int} $filters */
    public function getTotalCategories(array $filters = []): int {
        $query = $this->db->query("SELECT COUNT(*) AS `total` " . $this->categoryListFrom() . $this->categoryListWhere($filters));
        return ($query instanceof \stdClass && isset($query->row['total'])) ? (int)$query->row['total'] : 0;
    }

    /** Nome exibido, compartilhado pela lista/contagem e pelo filtro. */
    private function categoryNameSql(): string {
        return "COALESCE(NULLIF(cd.`name`, ''), cd_en.`name`, '')";
    }

    private function categoryListFrom(): string {
        $lang = $this->currentLanguageId();
        return "FROM `" . $this->t('category') . "` c
                LEFT JOIN `" . $this->t('category_description') . "` cd
                  ON cd.`category_id` = c.`category_id` AND cd.`language_id` = '" . $lang . "'
                LEFT JOIN `" . $this->t('category_description') . "` cd_en
                  ON cd_en.`category_id` = c.`category_id` AND cd_en.`language_id` = 1";
    }

    /** @param array{name?: string, status?: string|int} $filters */
    private function categoryListWhere(array $filters): string {
        $where = [];
        $name = $filters['name'] ?? '';
        if (is_string($name) && trim($name) !== '') {
            $where[] = $this->categoryNameSql() . " LIKE '%" . $this->db->escape(trim($name)) . "%'";
        }
        $status = $filters['status'] ?? 'all';
        if (in_array($status, ['0', '1', 0, 1], true)) {
            $where[] = "c.`status` = '" . (int)$status . "'";
        }
        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    /**
     * @return array<string, mixed> Linha principal (vazio = inexistente)
     */
    public function getCategory(int $category_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('category') . "` WHERE `category_id` = '" . $category_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @return array<int, array<string, mixed>> indexado por language_id
     */
    public function getCategoryDescriptions(int $category_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('category_description') . "` WHERE `category_id` = '" . $category_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $out[(int)$row['language_id']] = (array)$row;
        }

        return $out;
    }

    /**
     * keyword (seo_url.value) por idioma — key = 'category_id=N'.
     *
     * @return array<int, string> indexado por language_id
     */
    public function getCategoryKeywords(int $category_id): array {
        $query = $this->db->query(
            "SELECT `language_id`, `value` FROM `" . $this->t('seo_url') . "` WHERE `key` = 'category_id=" . $category_id . "'"
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
    public function keywordTaken(string $keyword, int $language_id, int $exclude_category_id): bool {
        $query = $this->db->query(
            "SELECT `key` FROM `" . $this->t('seo_url') . "`
             WHERE `value` = '" . $this->db->escape($keyword) . "' AND `language_id` = '" . $language_id . "'
               AND `key` <> 'category_id=" . $exclude_category_id . "' LIMIT 1"
        );

        return $query instanceof \stdClass && !empty($query->row);
    }

    /**
     * Opções de pai para o form: tudo menos a própria categoria e seus
     * descendentes (guarda anti-ciclo — árvore pequena, loop tolerável).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getParentOptions(int $exclude_category_id = 0): array {
        $all = $this->getCategories();

        if ($exclude_category_id <= 0) {
            return $all;
        }

        // fronteira expande enquanto acha novos descendentes
        $banned = [$exclude_category_id => true];

        $found = true;

        while ($found) {
            $found = false;

            foreach ($all as $row) {
                $cid    = (int)$row['category_id'];
                $parent = (int)$row['parent_id'];

                if (!isset($banned[$cid]) && isset($banned[$parent])) {
                    $banned[$cid] = true;
                    $found        = true;
                }
            }
        }

        return array_values(array_filter($all, fn(array $row): bool => !isset($banned[(int)$row['category_id']])));
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
    public function addCategory(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('category') . "` SET
                `parent_id`  = '" . (int)$data['parent_id'] . "',
                `image`      = '" . $this->db->escape((string)$data['image']) . "',
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order` = '" . (int)$data['sort_order'] . "',
                `date_added` = NOW(),
                `date_modified` = NOW()"
        );

        $category_id = $this->db->getLastId();

        $this->syncDescriptions($category_id, (array)$data['descriptions']);

        return $category_id;
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function editCategory(int $category_id, array $data): void {
        $this->db->query(
            "UPDATE `" . $this->t('category') . "` SET
                `parent_id`  = '" . (int)$data['parent_id'] . "',
                `image`      = '" . $this->db->escape((string)$data['image']) . "',
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order` = '" . (int)$data['sort_order'] . "',
                `date_modified` = NOW()
             WHERE `category_id` = '" . $category_id . "'"
        );

        $this->db->query("DELETE FROM `" . $this->t('category_description') . "` WHERE `category_id` = '" . $category_id . "'");

        $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'category_id=" . $category_id . "'");

        $this->syncDescriptions($category_id, (array)$data['descriptions']);
    }

    public function deleteCategory(int $category_id): void {
        // Filhos sobem um nível (viram raiz) — nunca órfãos de árvore
        $this->db->query("UPDATE `" . $this->t('category') . "` SET `parent_id` = '0' WHERE `parent_id` = '" . $category_id . "'");

        foreach (['category', 'category_description'] as $table) {
            $this->db->query("DELETE FROM `" . $this->t($table) . "` WHERE `category_id` = '" . $category_id . "'");
        }

        $this->db->query("DELETE FROM `" . $this->t('project_to_category') . "` WHERE `category_id` = '" . $category_id . "'");

        $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'category_id=" . $category_id . "'");
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * @param array<int, array<string, string>> $descriptions
     */
    private function syncDescriptions(int $category_id, array $descriptions): void {
        foreach ($descriptions as $language_id => $d) {
            $d = (array)$d;

            $this->db->query(
                "INSERT INTO `" . $this->t('category_description') . "` SET
                    `category_id`      = '" . $category_id . "',
                    `language_id`      = '" . (int)$language_id . "',
                    `name`             = '" . $this->db->escape((string)$d['name']) . "',
                    `description`      = '" . $this->db->escape((string)$d['description']) . "',
                    `meta_title`       = '" . $this->db->escape((string)$d['meta_title']) . "',
                    `meta_description` = '" . $this->db->escape((string)$d['meta_description']) . "'"
            );

            $keyword = trim((string)$d['keyword']);

            if ($keyword !== '') {
                $this->db->query(
                    "INSERT INTO `" . $this->t('seo_url') . "` SET
                        `key`         = 'category_id=" . $category_id . "',
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
