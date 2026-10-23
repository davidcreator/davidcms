<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * tool/tool — model do CRUD de ferramentas conectadas (F2-13, ADR 0013).
 * Tabelas rms_tool + rms_tool_description (INSTALADAS pelo wizard — zero DDL
 * novo, 6º módulo consecutivo). Sem SEO/keyword: ferramenta é item da página
 * catalog 'tool/', não página própria (Dicionário-de-Dados §Ferramentas).
 * Vocabulário DB idêntico ao cms/page (stdClass ->row/->rows, helper t()).
 */
namespace Reamur\Admin\Model\Tool;

class Tool extends \Reamur\System\Engine\Model {

    /**
     * Lista filtrável; sem argumentos mantém todos, COALESCE e ordem antigos.
     * @param array{name?: string, vendor?: string, status?: string|int, start?: int, limit?: int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getTools(array $filters = []): array {
        $sql = "SELECT t.`tool_id`, t.`image`, t.`vendor`, t.`url`, t.`status`, t.`sort_order`, t.`date_added`,
                       COALESCE(td.`name`, td_en.`name`, '') AS `name`,
                       COALESCE(td.`description`, td_en.`description`, '') AS `description` "
            . $this->toolListFrom() . $this->toolListWhere($filters)
            . " ORDER BY t.`sort_order` ASC, t.`tool_id` ASC";
        if (array_key_exists('limit', $filters)) {
            $start = max(0, (int)($filters['start'] ?? 0));
            $limit = min(100, max(1, (int)$filters['limit']));
            $sql .= " LIMIT " . $start . ", " . $limit;
        }
        $query = $this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @param array{name?: string, vendor?: string, status?: string|int} $filters */
    public function getTotalTools(array $filters = []): int {
        $query = $this->db->query("SELECT COUNT(*) AS `total` " . $this->toolListFrom() . $this->toolListWhere($filters));
        return ($query instanceof \stdClass && isset($query->row['total'])) ? (int)$query->row['total'] : 0;
    }

    private function toolListFrom(): string {
        return "FROM `" . $this->t('tool') . "` t
                LEFT JOIN `" . $this->t('tool_description') . "` td
                  ON td.`tool_id` = t.`tool_id` AND td.`language_id` = '" . $this->currentLanguageId() . "'
                LEFT JOIN `" . $this->t('tool_description') . "` td_en
                  ON td_en.`tool_id` = t.`tool_id` AND td_en.`language_id` = 1";
    }

    /** @param array{name?: string, vendor?: string, status?: string|int} $filters */
    private function toolListWhere(array $filters): string {
        $where = [];
        $name = $filters['name'] ?? '';
        if (is_string($name) && trim($name) !== '') {
            $where[] = "COALESCE(td.`name`, td_en.`name`, '') LIKE '%" . $this->db->escape(trim($name)) . "%'";
        }
        $vendor = $filters['vendor'] ?? '';
        if (is_string($vendor) && trim($vendor) !== '') {
            $where[] = "t.`vendor` LIKE '%" . $this->db->escape(trim($vendor)) . "%'";
        }
        $status = $filters['status'] ?? 'all';
        if (in_array($status, ['0', '1', 0, 1], true)) {
            $where[] = "t.`status` = '" . (int)$status . "'";
        }
        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    /**
     * @return array<string, mixed> Linha principal (vazio = inexistente)
     */
    public function getTool(int $tool_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('tool') . "` WHERE `tool_id` = '" . $tool_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @return array<int, array<string, mixed>> Descrições indexadas por language_id
     */
    public function getToolDescriptions(int $tool_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('tool_description') . "` WHERE `tool_id` = '" . $tool_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $row = (array)$row;

            $out[(int)$row['language_id']] = [
                'name'        => (string)$row['name'],
                'description' => (string)$row['description'],
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
    public function addTool(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('tool') . "` SET
                `image`      = '" . $this->db->escape((string)($data['image'] ?? '')) . "',
                `vendor`     = '" . $this->db->escape((string)($data['vendor'] ?? '')) . "',
                `url`        = '" . $this->db->escape((string)($data['url'] ?? '')) . "',
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
                `date_added` = NOW()"
        );

        $tool_id = (int)$this->db->getLastId();

        if (!$tool_id) {
            return 0;
        }

        $this->syncDescriptions($tool_id, (array)($data['descriptions'] ?? []));

        return $tool_id;
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function editTool(int $tool_id, array $data): void {
        $this->db->query(
            "UPDATE `" . $this->t('tool') . "` SET
                `image`      = '" . $this->db->escape((string)($data['image'] ?? '')) . "',
                `vendor`     = '" . $this->db->escape((string)($data['vendor'] ?? '')) . "',
                `url`        = '" . $this->db->escape((string)($data['url'] ?? '')) . "',
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "'
             WHERE `tool_id` = '" . $tool_id . "'"
        );

        $this->syncDescriptions($tool_id, (array)($data['descriptions'] ?? []));
    }

    /** Cascata manual (sem FK): ferramenta + descrições (o ícone físico o controller remove) */
    public function deleteTool(int $tool_id): void {
        $this->db->query("DELETE FROM `" . $this->t('tool') . "` WHERE `tool_id` = '" . $tool_id . "'");
        $this->db->query("DELETE FROM `" . $this->t('tool_description') . "` WHERE `tool_id` = '" . $tool_id . "'");
    }

    /**
     * @param array<int, array<string, mixed>> $descriptions
     */
    private function syncDescriptions(int $tool_id, array $descriptions): void {
        $this->db->query("DELETE FROM `" . $this->t('tool_description') . "` WHERE `tool_id` = '" . $tool_id . "'");

        foreach ($descriptions as $language_id => $d) {
            $d = (array)$d;

            $this->db->query(
                "INSERT INTO `" . $this->t('tool_description') . "` SET
                    `tool_id`     = '" . $tool_id . "',
                    `language_id` = '" . (int)$language_id . "',
                    `name`        = '" . $this->db->escape((string)($d['name'] ?? '')) . "',
                    `description` = '" . $this->db->escape((string)($d['description'] ?? '')) . "'"
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
