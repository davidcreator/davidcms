<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Model cms/block_pattern — biblioteca pessoal de padrões BDRE (F9-25).
 */
namespace Reamur\Admin\Model\Cms;

class Block_pattern extends \Reamur\System\Engine\Model {
    public const MAX_PATTERNS_PER_USER = 50;

    /** @return array<int, array<string, mixed>> */
    public function getPatterns(int $owner_user_id): array {
        if ($owner_user_id < 1 || !$this->tableExists()) return [];
        $query = $this->db->query(
            "SELECT `pattern_id`, `name`, `source_language`, `root_type`, `date_modified`
               FROM `" . $this->t('cms_block_pattern') . "`
              WHERE `owner_user_id` = '" . $owner_user_id . "'
              ORDER BY `name` ASC, `pattern_id` ASC
              LIMIT " . self::MAX_PATTERNS_PER_USER
        );
        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @return array<string, mixed> */
    public function getPattern(int $pattern_id, int $owner_user_id): array {
        if ($pattern_id < 1 || $owner_user_id < 1 || !$this->tableExists()) return [];
        $query = $this->db->query(
            "SELECT `pattern_id`, `name`, `source_language`, `root_type`, `format_version`, `content_json`
               FROM `" . $this->t('cms_block_pattern') . "`
              WHERE `pattern_id` = '" . $pattern_id . "' AND `owner_user_id` = '" . $owner_user_id . "'
              LIMIT 1"
        );
        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    public function getTotalPatterns(int $owner_user_id): int {
        if ($owner_user_id < 1 || !$this->tableExists()) return 0;
        $query = $this->db->query(
            "SELECT COUNT(*) AS `total` FROM `" . $this->t('cms_block_pattern') . "` WHERE `owner_user_id` = '" . $owner_user_id . "'"
        );
        return ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['total'] : 0;
    }

    public function nameTaken(int $owner_user_id, string $name, int $exclude_pattern_id = 0): bool {
        if ($owner_user_id < 1 || !$this->tableExists()) return false;
        $query = $this->db->query(
            "SELECT `pattern_id` FROM `" . $this->t('cms_block_pattern') . "`
              WHERE `owner_user_id` = '" . $owner_user_id . "'
                AND `name` = '" . $this->db->escape($name) . "'
                AND `pattern_id` <> '" . max(0, $exclude_pattern_id) . "'
              LIMIT 1"
        );
        return $query instanceof \stdClass && !empty($query->row);
    }

    public function addPattern(int $owner_user_id, string $name, string $source_language, string $root_type, int $format_version, string $content_json): int {
        if ($owner_user_id < 1 || !$this->tableExists()) return 0;
        $this->db->query(
            "INSERT INTO `" . $this->t('cms_block_pattern') . "` SET
                `owner_user_id` = '" . $owner_user_id . "',
                `name` = '" . $this->db->escape($name) . "',
                `source_language` = '" . $this->db->escape($source_language) . "',
                `root_type` = '" . $this->db->escape($root_type) . "',
                `format_version` = '" . $format_version . "',
                `content_json` = '" . $this->db->escape($content_json) . "',
                `date_added` = NOW(), `date_modified` = NOW()"
        );
        return (int)$this->db->getLastId();
    }

    public function renamePattern(int $pattern_id, int $owner_user_id, string $name): bool {
        if ($pattern_id < 1 || $owner_user_id < 1 || !$this->tableExists()) return false;
        $this->db->query(
            "UPDATE `" . $this->t('cms_block_pattern') . "` SET
                `name` = '" . $this->db->escape($name) . "', `date_modified` = NOW()
              WHERE `pattern_id` = '" . $pattern_id . "' AND `owner_user_id` = '" . $owner_user_id . "'"
        );
        return true;
    }

    public function deletePattern(int $pattern_id, int $owner_user_id): bool {
        if ($pattern_id < 1 || $owner_user_id < 1 || !$this->tableExists()) return false;
        $this->db->query(
            "DELETE FROM `" . $this->t('cms_block_pattern') . "`
              WHERE `pattern_id` = '" . $pattern_id . "' AND `owner_user_id` = '" . $owner_user_id . "'"
        );
        return true;
    }

    /** Manual cascade required by schema policy (no foreign keys). */
    public function deleteByOwner(int $owner_user_id): void {
        if ($owner_user_id < 1 || !$this->tableExists()) return;
        $this->db->query("DELETE FROM `" . $this->t('cms_block_pattern') . "` WHERE `owner_user_id` = '" . $owner_user_id . "'");
    }

    public function tableExists(): bool {
        static $cache = null;
        if ($cache !== null) return $cache;
        try {
            $table = $this->db->escape($this->t('cms_block_pattern'));
            $query = $this->db->query(
                "SELECT `TABLE_NAME` FROM `information_schema`.`TABLES`
                  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $table . "' LIMIT 1"
            );
            $cache = $query instanceof \stdClass && !empty($query->num_rows);
        } catch (\Throwable $exception) {
            $cache = false;
        }
        return $cache;
    }

    private function t(string $table): string {
        return (defined('DB_PREFIX') ? DB_PREFIX : 'rms_') . $table;
    }
}
