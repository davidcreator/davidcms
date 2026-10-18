<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Model catalog/project — CRUD do portfólio (F2-8, ADR 0008).
 * Entidade rica do Dicionario-de-Dados §catalog/: project + description
 * (multi-idioma) + image (galeria) + project_to_category + seo_url.
 * Sempre escape do driver; parâmetro por referência é PROIBIDO em model
 * (Proxy::__call) — retorna array/estado. Sem FKs no schema: cascata manual.
 */
namespace Reamur\Admin\Model\Catalog;

class Project extends \Reamur\System\Engine\Model {

    /**
     * Lista para a grade (título preferindo o idioma padrão, coalesce).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProjects(): array {
        $query = $this->db->query(
            "SELECT p.`project_id`, p.`image`, p.`client`, p.`featured`, p.`status`, p.`sort_order`, p.`date_modified`,
                    COALESCE(NULLIF(pd.`name`, ''), pd_en.`name`, '') AS `name`
             FROM `" . $this->t('project') . "` p
             LEFT JOIN `" . $this->t('project_description') . "` pd
                 ON pd.`project_id` = p.`project_id` AND pd.`language_id` = '" . $this->currentLanguageId() . "'
             LEFT JOIN `" . $this->t('project_description') . "` pd_en
                 ON pd_en.`project_id` = p.`project_id` AND pd_en.`language_id` = 1
             ORDER BY p.`sort_order` ASC, p.`project_id` DESC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * @return array<string, mixed> Linha principal (vazio = inexistente)
     */
    public function getProject(int $project_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('project') . "` WHERE `project_id` = '" . $project_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @return array<int, array<string, mixed>> indexado por language_id
     */
    public function getProjectDescriptions(int $project_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('project_description') . "` WHERE `project_id` = '" . $project_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $out[(int)$row['language_id']] = (array)$row;
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProjectImages(int $project_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('project_image') . "` WHERE `project_id` = '" . $project_id . "' ORDER BY `sort_order` ASC, `project_image_id` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * keyword (seo_url.value) por idioma — key = 'project_id=N'.
     *
     * @return array<int, string> indexado por language_id
     */
    public function getProjectKeywords(int $project_id): array {
        $query = $this->db->query(
            "SELECT `language_id`, `value` FROM `" . $this->t('seo_url') . "` WHERE `key` = 'project_id=" . $project_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $out[(int)$row['language_id']] = (string)$row['value'];
        }

        return $out;
    }

    /**
     * keyword já em uso por OUTRO projeto no mesmo idioma?
     */
    public function keywordTaken(string $keyword, int $language_id, int $exclude_project_id): bool {
        $query = $this->db->query(
            "SELECT `key` FROM `" . $this->t('seo_url') . "`
             WHERE `value` = '" . $this->db->escape($keyword) . "' AND `language_id` = '" . $language_id . "'
               AND `key` <> 'project_id=" . $exclude_project_id . "' LIMIT 1"
        );

        return $query instanceof \stdClass && !empty($query->row);
    }

    /**
     * @return array<int, int> ids
     */
    public function getProjectCategories(int $project_id): array {
        $query = $this->db->query(
            "SELECT `category_id` FROM `" . $this->t('project_to_category') . "` WHERE `project_id` = '" . $project_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        return array_map('intval', array_column($rows, 'category_id'));
    }

    /**
     * Categorias para o checkbox do form (nome no idioma padrão, coalesce EN).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(): array {
        $lang = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT c.`category_id`, COALESCE(NULLIF(cd.`name`, ''), cd_en.`name`, '') AS `name`
             FROM `" . $this->t('category') . "` c
             LEFT JOIN `" . $this->t('category_description') . "` cd
                 ON cd.`category_id` = c.`category_id` AND cd.`language_id` = '" . $lang . "'
             LEFT JOIN `" . $this->t('category_description') . "` cd_en
                 ON cd_en.`category_id` = c.`category_id` AND cd_en.`language_id` = 1
             ORDER BY c.`sort_order` ASC, c.`category_id` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * Idiomas cadastrados (abas do form). Independe do status do front —
     * conteúdo pode ser redigido antes do idioma ir ao ar.
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
    public function addProject(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('project') . "` SET
                `image`      = '" . $this->db->escape((string)$data['image']) . "',
                `client`     = '" . $this->db->escape((string)$data['client']) . "',
                `url`        = '" . $this->db->escape((string)$data['url']) . "',
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `featured`   = '" . (int)(!empty($data['featured']) ? 1 : 0) . "',
                `sort_order` = '" . (int)$data['sort_order'] . "',
                `date_added` = NOW(),
                `date_modified` = NOW()"
        );

        $project_id = $this->db->getLastId();

        $this->syncChildren($project_id, $data);

        return $project_id;
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function editProject(int $project_id, array $data): void {
        $this->db->query(
            "UPDATE `" . $this->t('project') . "` SET
                `image`      = '" . $this->db->escape((string)$data['image']) . "',
                `client`     = '" . $this->db->escape((string)$data['client']) . "',
                `url`        = '" . $this->db->escape((string)$data['url']) . "',
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `featured`   = '" . (int)(!empty($data['featured']) ? 1 : 0) . "',
                `sort_order` = '" . (int)$data['sort_order'] . "',
                `date_modified` = NOW()
             WHERE `project_id` = '" . $project_id . "'"
        );

        // Filhos são reescritos do zero (estado declarativo do formulário)
        foreach (['project_description', 'project_image', 'project_to_category'] as $child) {
            $this->db->query("DELETE FROM `" . $this->t($child) . "` WHERE `project_id` = '" . $project_id . "'");
        }

        $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'project_id=" . $project_id . "'");

        $this->syncChildren($project_id, $data);
    }

    public function deleteProject(int $project_id): void {
        foreach (['project', 'project_description', 'project_image', 'project_to_category'] as $table) {
            $this->db->query("DELETE FROM `" . $this->t($table) . "` WHERE `project_id` = '" . $project_id . "'");
        }

        $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'project_id=" . $project_id . "'");
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * Insere descrições (uma por idioma), galeria, relação N:N e keywords SEO.
     *
     * @param array<string, mixed> $data
     */
    private function syncChildren(int $project_id, array $data): void {
        foreach ((array)$data['descriptions'] as $language_id => $d) {
            $d = (array)$d;

            $this->db->query(
                "INSERT INTO `" . $this->t('project_description') . "` SET
                    `project_id`       = '" . $project_id . "',
                    `language_id`      = '" . (int)$language_id . "',
                    `name`             = '" . $this->db->escape((string)$d['name']) . "',
                    `summary`          = '" . $this->db->escape((string)$d['summary']) . "',
                    `description`      = '" . $this->db->escape((string)$d['description']) . "',
                    `tag`              = '" . $this->db->escape((string)$d['tag']) . "',
                    `meta_title`       = '" . $this->db->escape((string)$d['meta_title']) . "',
                    `meta_description` = '" . $this->db->escape((string)$d['meta_description']) . "'"
            );

            $keyword = trim((string)$d['keyword']);

            if ($keyword !== '') {
                $this->db->query(
                    "INSERT INTO `" . $this->t('seo_url') . "` SET
                        `key`         = 'project_id=" . $project_id . "',
                        `value`       = '" . $this->db->escape($keyword) . "',
                        `language_id` = '" . (int)$language_id . "',
                        `sort_order`  = 0"
                );
            }
        }

        foreach ((array)$data['images'] as $img) {
            $img = (array)$img;

            if (trim((string)$img['image']) === '') {
                continue;
            }

            $this->db->query(
                "INSERT INTO `" . $this->t('project_image') . "` SET
                    `project_id` = '" . $project_id . "',
                    `image`      = '" . $this->db->escape((string)$img['image']) . "',
                    `alt`        = '" . $this->db->escape((string)$img['alt']) . "',
                    `sort_order` = '" . (int)$img['sort_order'] . "'"
            );
        }

        foreach ((array)$data['categories'] as $category_id) {
            $category_id = (int)$category_id;

            if ($category_id > 0) {
                $this->db->query(
                    "INSERT INTO `" . $this->t('project_to_category') . "` SET
                        `project_id`  = '" . $project_id . "',
                        `category_id` = '" . $category_id . "'"
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
