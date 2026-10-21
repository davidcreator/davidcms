<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Model blog/blog — blog interno (F7-01, ADR 0042): posts cronológicos com
 * destaque, multi-idioma (blog_post_description) e keyword no seo_url global
 * (key 'blog_id=N' — mesma unicidade cross-entidade das páginas/projetos).
 * O ordering público (F7-02) é por date_published DESC, NÃO sort_order.
 */
namespace Reamur\Admin\Model\Blog;

class Blog extends \Reamur\System\Engine\Model {

    /**
     * Lista para a grade (título preferindo o idioma ativo, coalesce EN;
     * ordem editorial: publicação desc — novidades primeiro).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPosts(): array {
        $lang = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT p.`post_id`, p.`status`, p.`date_published`, p.`date_modified`,
                    COALESCE(NULLIF(pd.`title`, ''), pd_en.`title`, '') AS `title`
             FROM `" . $this->t('blog_post') . "` p
             LEFT JOIN `" . $this->t('blog_post_description') . "` pd
                 ON pd.`post_id` = p.`post_id` AND pd.`language_id` = '" . $lang . "'
             LEFT JOIN `" . $this->t('blog_post_description') . "` pd_en
                 ON pd_en.`post_id` = p.`post_id` AND pd_en.`language_id` = 1
             ORDER BY p.`date_published` DESC, p.`post_id` DESC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * @return array<string, mixed> Linha principal (vazio = inexistente)
     */
    public function getPost(int $post_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('blog_post') . "` WHERE `post_id` = '" . $post_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @return array<int, array<string, mixed>> indexado por language_id
     */
    public function getPostDescriptions(int $post_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('blog_post_description') . "` WHERE `post_id` = '" . $post_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $out[(int)$row['language_id']] = (array)$row;
        }

        return $out;
    }

    /**
     * keyword (seo_url.value) por idioma — key = 'blog_id=N'.
     *
     * @return array<int, string> indexado por language_id
     */
    public function getPostKeywords(int $post_id): array {
        $query = $this->db->query(
            "SELECT `language_id`, `value` FROM `" . $this->t('seo_url') . "` WHERE `key` = 'blog_id=" . $post_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $out[(int)$row['language_id']] = (string)$row['value'];
        }

        return $out;
    }

    /**
     * keyword já em uso naquele idioma por QUALQUER outra entidade
     * (seo_url é global: blog x página x projeto x ferramenta).
     */
    public function keywordTaken(string $keyword, int $language_id, int $exclude_post_id): bool {
        $query = $this->db->query(
            "SELECT `key` FROM `" . $this->t('seo_url') . "`
             WHERE `value` = '" . $this->db->escape($keyword) . "' AND `language_id` = '" . $language_id . "'
               AND `key` <> 'blog_id=" . $exclude_post_id . "' LIMIT 1"
        );

        return $query instanceof \stdClass && !empty($query->row);
    }

    /**
     * Idiomas cadastrados (abas do form).
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
    public function addPost(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('blog_post') . "` SET
                `image`          = '" . $this->db->escape((string)$data['image']) . "',
                `status`         = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `date_published` = '" . $this->db->escape((string)$data['date_published']) . "',
                `date_added`     = NOW(),
                `date_modified`  = NOW()"
        );

        $post_id = (int)$this->db->getLastId();

        $this->syncDescriptions($post_id, (array)$data['descriptions']);

        return $post_id;
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function editPost(int $post_id, array $data): void {
        $this->db->query(
            "UPDATE `" . $this->t('blog_post') . "` SET
                `image`          = '" . $this->db->escape((string)$data['image']) . "',
                `status`         = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `date_published` = '" . $this->db->escape((string)$data['date_published']) . "',
                `date_modified`  = NOW()
             WHERE `post_id` = '" . $post_id . "'"
        );

        $this->db->query("DELETE FROM `" . $this->t('blog_post_description') . "` WHERE `post_id` = '" . $post_id . "'");

        $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'blog_id=" . $post_id . "'");

        $this->syncDescriptions($post_id, (array)$data['descriptions']);
    }

    public function deletePost(int $post_id): void {
        $this->db->query("DELETE FROM `" . $this->t('blog_post') . "` WHERE `post_id` = '" . $post_id . "'");

        $this->db->query("DELETE FROM `" . $this->t('blog_post_description') . "` WHERE `post_id` = '" . $post_id . "'");

        $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'blog_id=" . $post_id . "'");
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * Descrições (uma por idioma) + keywords SEO.
     *
     * @param array<int, array<string, string>> $descriptions
     */
    private function syncDescriptions(int $post_id, array $descriptions): void {
        foreach ($descriptions as $language_id => $d) {
            $d = (array)$d;

            $this->db->query(
                "INSERT INTO `" . $this->t('blog_post_description') . "` SET
                    `post_id`          = '" . $post_id . "',
                    `language_id`      = '" . (int)$language_id . "',
                    `title`            = '" . $this->db->escape((string)$d['title']) . "',
                    `description`      = '" . $this->db->escape((string)$d['description']) . "',
                    `meta_title`       = '" . $this->db->escape((string)$d['meta_title']) . "',
                    `meta_description` = '" . $this->db->escape((string)$d['meta_description']) . "'"
            );

            $keyword = trim((string)($d['keyword'] ?? ''));

            if ($keyword !== '') {
                $this->db->query(
                    "INSERT INTO `" . $this->t('seo_url') . "` SET
                        `key`         = 'blog_id=" . $post_id . "',
                        `value`       = '" . $this->db->escape($keyword) . "',
                        `language_id` = '" . (int)$language_id . "',
                        `sort_order`  = 0"
                );
            }
        }
    }

    /** Idioma ativo do admin (language_directory → rms_language) — padrão dos models */
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

    /** Nome real da tabela com o prefixo do banco (padrão Model base) */
    private function t(string $base): string {
        return DB_PREFIX . $base;
    }
}
