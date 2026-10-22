<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * marketing/testimonial — model do CRUD de depoimentos (F2-11, ADR 0011).
 * Tabelas rms_testimonial + rms_testimonial_description (INSTALADAS pelo
 * wizard — zero DDL novo). Depoimento NÃO tem SEO/keyword: é conteúdo da home
 * (Dicionário-de-Dados §"MARKETING / HOME"), não página própria.
 * Vocabulário DB idêntico ao cms/page (ADR 0010): wrapper retorna stdClass
 * com ->row/->rows já em arrays associativos.
 */
namespace Reamur\Admin\Model\Marketing;

class Testimonial extends \Reamur\System\Engine\Model {

    /**
     * Grade paginável: coalesce autor/empresa do idioma ativo → en-US.
     * Sem limite, mantém o comportamento histórico de retornar todas as linhas.
     *
     * @param array<string, mixed> $filters Filtros normalizados pelo controller
     * @return array<int, array<string, mixed>>
     */
    public function getTestimonials(array $filters = [], int $start = 0, int $limit = 0): array {
        $lang = $this->currentLanguageId();
        $limitSql = $limit > 0 ? ' LIMIT ' . max(0, $start) . ', ' . max(1, $limit) : '';

        $query = $this->db->query(
            "SELECT t.`testimonial_id`, t.`image`, t.`status`, t.`sort_order`, t.`date_added`,
                    COALESCE(td.`author`, td_en.`author`, '')     AS `author`,
                    COALESCE(td.`company`, td_en.`company`, '')   AS `company`
               FROM `" . $this->t('testimonial') . "` t
          LEFT JOIN `" . $this->t('testimonial_description') . "` td
                 ON td.`testimonial_id` = t.`testimonial_id` AND td.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('testimonial_description') . "` td_en
                 ON td_en.`testimonial_id` = t.`testimonial_id` AND td_en.`language_id` = 1"
            . $this->testimonialFilterSql($filters) . "
           ORDER BY t.`sort_order` ASC, t.`testimonial_id` ASC"
            . $limitSql
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** Total para paginação, com os mesmos filtros da grade. */
    public function getTotalTestimonials(array $filters = []): int {
        $lang = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT COUNT(DISTINCT t.`testimonial_id`) AS `total`
               FROM `" . $this->t('testimonial') . "` t
          LEFT JOIN `" . $this->t('testimonial_description') . "` td
                 ON td.`testimonial_id` = t.`testimonial_id` AND td.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('testimonial_description') . "` td_en
                 ON td_en.`testimonial_id` = t.`testimonial_id` AND td_en.`language_id` = 1"
            . $this->testimonialFilterSql($filters)
        );

        return ($query instanceof \stdClass && isset($query->row['total'])) ? (int)$query->row['total'] : 0;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function testimonialFilterSql(array $filters): string {
        $where = [];
        $author = is_scalar($filters['author'] ?? null) ? trim((string)$filters['author']) : '';

        if ($author !== '') {
            $author = $this->db->escape($author);
            $where[] = "(COALESCE(td.`author`, td_en.`author`, '') LIKE '%" . $author . "%'
                        OR COALESCE(td.`company`, td_en.`company`, '') LIKE '%" . $author . "%')";
        }

        $status = is_scalar($filters['status'] ?? null) ? (string)$filters['status'] : 'all';

        if (in_array($status, ['0', '1'], true)) {
            $where[] = "t.`status` = '" . $status . "'";
        }

        return $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    /**
     * @return array<string, mixed> Linha principal (vazio = inexistente)
     */
    public function getTestimonial(int $testimonial_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('testimonial') . "` WHERE `testimonial_id` = '" . $testimonial_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @return array<int, array<string, mixed>> Descrições indexadas por language_id
     */
    public function getTestimonialDescriptions(int $testimonial_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('testimonial_description') . "` WHERE `testimonial_id` = '" . $testimonial_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $row = (array)$row;

            $out[(int)$row['language_id']] = [
                'author'  => (string)$row['author'],
                'company' => (string)$row['company'],
                'text'    => (string)$row['text'],
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>> Idiomas em sort_order (en-US primeiro por convenção)
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
    public function addTestimonial(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('testimonial') . "` SET
                `image`      = '" . $this->db->escape((string)($data['image'] ?? '')) . "',
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
                `date_added` = NOW()"
        );

        $testimonial_id = (int)$this->db->getLastId();

        if (!$testimonial_id) {
            return 0;
        }

        $this->syncDescriptions($testimonial_id, (array)($data['descriptions'] ?? []));

        return $testimonial_id;
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function editTestimonial(int $testimonial_id, array $data): void {
        $this->db->query(
            "UPDATE `" . $this->t('testimonial') . "` SET
                `image`      = '" . $this->db->escape((string)($data['image'] ?? '')) . "',
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "'
             WHERE `testimonial_id` = '" . $testimonial_id . "'"
        );

        $this->syncDescriptions($testimonial_id, (array)($data['descriptions'] ?? []));
    }

    /** Cascata manual (sem FK): depoimento + descrições (a foto física o controller remove) */
    public function deleteTestimonial(int $testimonial_id): void {
        $this->db->query("DELETE FROM `" . $this->t('testimonial') . "` WHERE `testimonial_id` = '" . $testimonial_id . "'");
        $this->db->query("DELETE FROM `" . $this->t('testimonial_description') . "` WHERE `testimonial_id` = '" . $testimonial_id . "'");
    }

    /**
     * @param array<int, array<string, mixed>> $descriptions
     */
    private function syncDescriptions(int $testimonial_id, array $descriptions): void {
        $this->db->query("DELETE FROM `" . $this->t('testimonial_description') . "` WHERE `testimonial_id` = '" . $testimonial_id . "'");

        foreach ($descriptions as $language_id => $d) {
            $d = (array)$d;

            $this->db->query(
                "INSERT INTO `" . $this->t('testimonial_description') . "` SET
                    `testimonial_id` = '" . $testimonial_id . "',
                    `language_id`    = '" . (int)$language_id . "',
                    `author`         = '" . $this->db->escape((string)($d['author'] ?? '')) . "',
                    `company`        = '" . $this->db->escape((string)($d['company'] ?? '')) . "',
                    `text`           = '" . $this->db->escape((string)($d['text'] ?? '')) . "'"
            );
        }
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

    /** Tabela com prefixo (mesmo helper dos demais models F2) */
    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
