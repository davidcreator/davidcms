<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * custom_field/definition — model de DEFINIÇÕES de campos personalizados (F9-05).
 * Usa as tabelas propostas na F9-02. Não grava valores, não renderiza UI e não
 * altera lifecycle de entidades existentes. CMS/page é o único alvo permitido.
 */
namespace Reamur\Admin\Model\Custom_field;

class Definition extends \Reamur\System\Engine\Model {

    /** @var array<int, string> Entidades habilitadas pela decisão F9-01/F9-02 */
    private const ALLOWED_ENTITIES = ['cms/page'];

    /** @var array<int, string> Tipos básicos aprovados */
    private const ALLOWED_TYPES = ['text', 'textarea', 'number', 'boolean', 'url', 'select'];

    /** @var array<int, string> Visibilidades físicas; padrão privado */
    private const ALLOWED_VISIBILITY = ['admin_only', 'public'];

    /**
     * Lista grupos com label por idioma atual ou fallback en-US.
     * @param array{entity_type?: string, key?: string, name?: string, status?: string|int, start?: int, limit?: int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getGroups(array $filters = []): array {
        $sql = "SELECT g.`group_id`, g.`entity_type`, g.`key`, g.`name`, g.`status`, g.`sort_order`, g.`date_added`, g.`date_modified`,
                       COALESCE(gd.`name`, gd_en.`name`, g.`name`) AS `label`
                " . $this->groupListFrom() . $this->groupListWhere($filters) . "
                ORDER BY g.`sort_order` ASC, g.`group_id` ASC";

        if (array_key_exists('limit', $filters)) {
            $start = max(0, (int)($filters['start'] ?? 0));
            $limit = min(100, max(1, (int)$filters['limit']));
            $sql .= " LIMIT " . $start . ", " . $limit;
        }

        $query = $this->db->query($sql);

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @param array{entity_type?: string, key?: string, name?: string, status?: string|int} $filters */
    public function getTotalGroups(array $filters = []): int {
        $query = $this->db->query("SELECT COUNT(*) AS `total` " . $this->groupListFrom() . $this->groupListWhere($filters));

        return ($query instanceof \stdClass && isset($query->row['total'])) ? (int)$query->row['total'] : 0;
    }

    /** @return array<string, mixed> */
    public function getGroup(int $group_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('custom_field_group') . "` WHERE `group_id` = '" . $group_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * Labels de grupo indexadas por language_id.
     * @return array<int, array{name: string}>
     */
    public function getGroupDescriptions(int $group_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('custom_field_group_description') . "` WHERE `group_id` = '" . $group_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
        $out = [];

        foreach ($rows as $row) {
            $row = (array)$row;
            $out[(int)$row['language_id']] = ['name' => (string)$row['name']];
        }

        return $out;
    }

    public function groupExists(string $entity_type, string $key, int $exclude_group_id = 0): bool {
        if (!$this->isAllowedEntity($entity_type) || !$this->isValidKey($key)) {
            return false;
        }

        $query = $this->db->query(
            "SELECT `group_id` FROM `" . $this->t('custom_field_group') . "`
             WHERE `entity_type` = '" . $this->db->escape($entity_type) . "'
               AND `key` = '" . $this->db->escape($key) . "'
               AND `group_id` <> '" . $exclude_group_id . "' LIMIT 1"
        );

        return ($query instanceof \stdClass && !empty($query->row));
    }

    /** Cria apenas definição de grupo; nenhum valor de conteúdo é criado. */
    public function addGroup(array $data): int {
        if (!$this->validGroupPayload($data)) {
            return 0;
        }

        $entity_type = (string)$data['entity_type'];
        $key = (string)$data['key'];

        $this->db->query(
            "INSERT INTO `" . $this->t('custom_field_group') . "` SET
                `entity_type`   = '" . $this->db->escape($entity_type) . "',
                `key`           = '" . $this->db->escape($key) . "',
                `name`          = '" . $this->db->escape((string)($data['name'] ?? '')) . "',
                `status`        = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order`    = '" . (int)($data['sort_order'] ?? 0) . "',
                `date_added`    = NOW(),
                `date_modified` = NOW()"
        );

        $group_id = (int)$this->db->getLastId();

        if (!$group_id) {
            return 0;
        }

        $this->syncGroupDescriptions($group_id, (array)($data['descriptions'] ?? []));

        return $group_id;
    }

    /** entity_type e key são imutáveis nesta etapa; alteração exige migração própria. */
    public function editGroup(int $group_id, array $data): void {
        $this->db->query(
            "UPDATE `" . $this->t('custom_field_group') . "` SET
                `name`          = '" . $this->db->escape((string)($data['name'] ?? '')) . "',
                `status`        = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order`    = '" . (int)($data['sort_order'] ?? 0) . "',
                `date_modified` = NOW()
             WHERE `group_id` = '" . $group_id . "'"
        );

        $this->syncGroupDescriptions($group_id, (array)($data['descriptions'] ?? []));
    }

    /** Só remove grupo vazio; não toca em campos nem valores. */
    public function deleteGroup(int $group_id): bool {
        if ($this->countGroupFields($group_id) > 0) {
            return false;
        }

        $this->db->query("DELETE FROM `" . $this->t('custom_field_group_description') . "` WHERE `group_id` = '" . $group_id . "'");
        $this->db->query("DELETE FROM `" . $this->t('custom_field_group') . "` WHERE `group_id` = '" . $group_id . "'");

        return true;
    }

    /**
     * Lista campos de definição com fallback de label.
     * @param array{entity_type?: string, group_id?: int|string, key?: string, type?: string, status?: string, visibility?: string, start?: int, limit?: int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getFields(array $filters = []): array {
        $sql = "SELECT f.`field_id`, f.`entity_type`, f.`group_id`, f.`key`, f.`type`, f.`localized`, f.`required`, f.`visibility`,
                       f.`options`, f.`sort_order`, f.`date_added`, f.`date_modified`,
                       g.`key` AS `group_key`, g.`name` AS `group_name`, g.`status` AS `group_status`,
                       COALESCE(fd.`label`, fd_en.`label`, f.`key`) AS `label`
                FROM `" . $this->t('custom_field') . "` f
                LEFT JOIN `" . $this->t('custom_field_group') . "` g ON g.`group_id` = f.`group_id`
                LEFT JOIN `" . $this->t('custom_field_description') . "` fd
                  ON fd.`field_id` = f.`field_id` AND fd.`language_id` = '" . $this->currentLanguageId() . "'
                LEFT JOIN `" . $this->t('custom_field_description') . "` fd_en
                  ON fd_en.`field_id` = f.`field_id` AND fd_en.`language_id` = 1"
            . $this->fieldListWhere($filters) . "
                ORDER BY f.`sort_order` ASC, f.`field_id` ASC";

        if (array_key_exists('limit', $filters)) {
            $start = max(0, (int)($filters['start'] ?? 0));
            $limit = min(100, max(1, (int)$filters['limit']));
            $sql .= " LIMIT " . $start . ", " . $limit;
        }

        $query = $this->db->query($sql);

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @param array{entity_type?: string, group_id?: int|string, key?: string, type?: string, status?: string, visibility?: string} $filters */
    public function getTotalFields(array $filters = []): int {
        $query = $this->db->query(
            "SELECT COUNT(*) AS `total` FROM `" . $this->t('custom_field') . "` f
             LEFT JOIN `" . $this->t('custom_field_description') . "` fd
               ON fd.`field_id` = f.`field_id` AND fd.`language_id` = '" . $this->currentLanguageId() . "'
             LEFT JOIN `" . $this->t('custom_field_description') . "` fd_en
               ON fd_en.`field_id` = f.`field_id` AND fd_en.`language_id` = 1"
            . $this->fieldListWhere($filters)
        );

        return ($query instanceof \stdClass && isset($query->row['total'])) ? (int)$query->row['total'] : 0;
    }

    /** @return array<string, mixed> */
    public function getField(int $field_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('custom_field') . "` WHERE `field_id` = '" . $field_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @return array<int, array{label: string, help: string}>
     */
    public function getFieldDescriptions(int $field_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('custom_field_description') . "` WHERE `field_id` = '" . $field_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
        $out = [];

        foreach ($rows as $row) {
            $row = (array)$row;
            $out[(int)$row['language_id']] = [
                'label' => (string)$row['label'],
                'help'  => (string)$row['help'],
            ];
        }

        return $out;
    }

    public function fieldExists(string $entity_type, string $key, int $exclude_field_id = 0): bool {
        if (!$this->isAllowedEntity($entity_type) || !$this->isValidKey($key)) {
            return false;
        }

        $query = $this->db->query(
            "SELECT `field_id` FROM `" . $this->t('custom_field') . "`
             WHERE `entity_type` = '" . $this->db->escape($entity_type) . "'
               AND `key` = '" . $this->db->escape($key) . "'
               AND `field_id` <> '" . $exclude_field_id . "' LIMIT 1"
        );

        return ($query instanceof \stdClass && !empty($query->row));
    }

    /**
     * Cria definição de campo. Não grava nenhum `custom_field_value`.
     * Options aceitas como array de opções e serializadas para JSON estrito.
     */
    public function addField(array $data): int {
        if (!$this->validFieldPayload($data)) {
            return 0;
        }

        $this->db->query(
            "INSERT INTO `" . $this->t('custom_field') . "` SET
                `entity_type`   = '" . $this->db->escape((string)$data['entity_type']) . "',
                `group_id`      = '" . (int)$data['group_id'] . "',
                `key`           = '" . $this->db->escape((string)$data['key']) . "',
                `type`          = '" . $this->db->escape((string)$data['type']) . "',
                `localized`     = '" . (int)(!empty($data['localized']) ? 1 : 0) . "',
                `required`      = '" . (int)(!empty($data['required']) ? 1 : 0) . "',
                `visibility`    = '" . $this->db->escape((string)$data['visibility']) . "',
                `options`       = '" . $this->db->escape($this->optionsToJson((string)$data['type'], $data['options'] ?? [])) . "',
                `sort_order`    = '" . (int)($data['sort_order'] ?? 0) . "',
                `date_added`    = NOW(),
                `date_modified` = NOW()"
        );

        $field_id = (int)$this->db->getLastId();

        if (!$field_id) {
            return 0;
        }

        $this->syncFieldDescriptions($field_id, (array)($data['descriptions'] ?? []));

        return $field_id;
    }

    /** key/type/entity_type/localized são imutáveis; opções obrigatórias voltam a [] quando o tipo muda via migração futura. */
    public function editField(int $field_id, array $data): bool {
        $field = $this->getField($field_id);

        if (!$field) {
            return false;
        }

        $group_id = (int)($data['group_id'] ?? $field['group_id']);

        if (!$this->groupBelongsToEntity($group_id, (string)$field['entity_type'])) {
            return false;
        }

        $visibility = (string)($data['visibility'] ?? $field['visibility']);

        if (!$this->isAllowedVisibility($visibility)) {
            return false;
        }

        $type = (string)$field['type'];

        $this->db->query(
            "UPDATE `" . $this->t('custom_field') . "` SET
                `group_id`      = '" . $group_id . "',
                `required`      = '" . (int)(!empty($data['required']) ? 1 : 0) . "',
                `visibility`    = '" . $this->db->escape($visibility) . "',
                `options`       = '" . $this->db->escape($this->optionsToJson($type, $data['options'] ?? [])) . "',
                `sort_order`    = '" . (int)($data['sort_order'] ?? $field['sort_order']) . "',
                `date_modified` = NOW()
             WHERE `field_id` = '" . $field_id . "'"
        );

        $this->syncFieldDescriptions($field_id, (array)($data['descriptions'] ?? []));

        return true;
    }

    /** Lifecycle conservador: campo com valor NÃO é hard-deleted; desative/atualize primeiro. */
    public function deleteField(int $field_id): bool {
        if ($this->countFieldValues($field_id) > 0) {
            return false;
        }

        $this->db->query("DELETE FROM `" . $this->t('custom_field_description') . "` WHERE `field_id` = '" . $field_id . "'");
        $this->db->query("DELETE FROM `" . $this->t('custom_field') . "` WHERE `field_id` = '" . $field_id . "'");

        return true;
    }

    /** @return array<int, string> */
    public function getAllowedEntities(): array {
        return self::ALLOWED_ENTITIES;
    }

    /** @return array<int, string> */
    public function getAllowedTypes(): array {
        return self::ALLOWED_TYPES;
    }

    /** @return array<int, string> */
    public function getAllowedVisibility(): array {
        return self::ALLOWED_VISIBILITY;
    }

    /** @return array<int, array<string, mixed>> */
    public function getLanguages(): array {
        $query = $this->db->query(
            "SELECT `language_id`, `name`, `code`, `status` FROM `" . $this->t('language') . "` ORDER BY `sort_order` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    public function countGroupFields(int $group_id): int {
        $query = $this->db->query(
            "SELECT COUNT(*) AS `total` FROM `" . $this->t('custom_field') . "` WHERE `group_id` = '" . $group_id . "'"
        );

        return ($query instanceof \stdClass && isset($query->row['total'])) ? (int)$query->row['total'] : 0;
    }

    public function countFieldValues(int $field_id): int {
        $query = $this->db->query(
            "SELECT COUNT(*) AS `total` FROM `" . $this->t('custom_field_value') . "` WHERE `field_id` = '" . $field_id . "'"
        );

        return ($query instanceof \stdClass && isset($query->row['total'])) ? (int)$query->row['total'] : 0;
    }

    private function groupBelongsToEntity(int $group_id, string $entity_type): bool {
        $query = $this->db->query(
            "SELECT `group_id` FROM `" . $this->t('custom_field_group') . "`
             WHERE `group_id` = '" . $group_id . "'
               AND `entity_type` = '" . $this->db->escape($entity_type) . "' LIMIT 1"
        );

        return ($query instanceof \stdClass && !empty($query->row));
    }

    private function validGroupPayload(array $data): bool {
        return $this->isAllowedEntity((string)($data['entity_type'] ?? ''))
            && $this->isValidKey((string)($data['key'] ?? ''))
            && trim((string)($data['name'] ?? '')) !== '';
    }

    private function validFieldPayload(array $data): bool {
        $entity_type = (string)($data['entity_type'] ?? '');
        $type = (string)($data['type'] ?? '');
        $visibility = (string)($data['visibility'] ?? 'admin_only');

        return $this->isAllowedEntity($entity_type)
            && $this->isAllowedType($type)
            && $this->isAllowedVisibility($visibility)
            && $this->isValidKey((string)($data['key'] ?? ''))
            && $this->groupBelongsToEntity((int)($data['group_id'] ?? 0), $entity_type);
    }

    private function isAllowedEntity(string $entity_type): bool {
        return in_array($entity_type, self::ALLOWED_ENTITIES, true);
    }

    private function isAllowedType(string $type): bool {
        return in_array($type, self::ALLOWED_TYPES, true);
    }

    private function isAllowedVisibility(string $visibility): bool {
        return in_array($visibility, self::ALLOWED_VISIBILITY, true);
    }

    private function isValidKey(string $key): bool {
        return (bool)preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key);
    }

    /**
     * @param mixed $options Array seguro; select exige value/label não vazios.
     */
    private function optionsToJson(string $type, mixed $options): string {
        if ($type !== 'select') {
            return '[]';
        }

        if (!is_array($options)) {
            return '[]';
        }

        $out = [];
        $seen = [];

        foreach ($options as $option) {
            $option = (array)$option;
            $value = trim((string)($option['value'] ?? ''));
            $label = trim((string)($option['label'] ?? ''));

            if ($value === '' || mb_strlen($value) > 64 || mb_strlen($label) > 128 || isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $out[] = ['value' => $value, 'label' => $label];
        }

        return (string)json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<int, array<string, mixed>> $descriptions */
    private function syncGroupDescriptions(int $group_id, array $descriptions): void {
        $this->db->query("DELETE FROM `" . $this->t('custom_field_group_description') . "` WHERE `group_id` = '" . $group_id . "'");

        foreach ($descriptions as $language_id => $description) {
            $description = (array)$description;
            $name = trim((string)($description['name'] ?? ''));

            if ((int)$language_id <= 0 || $name === '') {
                continue;
            }

            $this->db->query(
                "INSERT INTO `" . $this->t('custom_field_group_description') . "` SET
                    `group_id`    = '" . $group_id . "',
                    `language_id` = '" . (int)$language_id . "',
                    `name`        = '" . $this->db->escape($name) . "'"
            );
        }
    }

    /** @param array<int, array<string, mixed>> $descriptions */
    private function syncFieldDescriptions(int $field_id, array $descriptions): void {
        $this->db->query("DELETE FROM `" . $this->t('custom_field_description') . "` WHERE `field_id` = '" . $field_id . "'");

        foreach ($descriptions as $language_id => $description) {
            $description = (array)$description;
            $label = trim((string)($description['label'] ?? ''));

            if ((int)$language_id <= 0 || $label === '') {
                continue;
            }

            $this->db->query(
                "INSERT INTO `" . $this->t('custom_field_description') . "` SET
                    `field_id`    = '" . $field_id . "',
                    `language_id` = '" . (int)$language_id . "',
                    `label`       = '" . $this->db->escape($label) . "',
                    `help`        = '" . $this->db->escape((string)($description['help'] ?? '')) . "'"
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

    private function groupListFrom(): string {
        return "FROM `" . $this->t('custom_field_group') . "` g
                LEFT JOIN `" . $this->t('custom_field_group_description') . "` gd
                  ON gd.`group_id` = g.`group_id` AND gd.`language_id` = '" . $this->currentLanguageId() . "'
                LEFT JOIN `" . $this->t('custom_field_group_description') . "` gd_en
                  ON gd_en.`group_id` = g.`group_id` AND gd_en.`language_id` = 1";
    }

    /** @param array<string, mixed> $filters */
    private function groupListWhere(array $filters): string {
        $where = [];

        if (!empty($filters['entity_type']) && is_string($filters['entity_type'])) {
            $where[] = "g.`entity_type` = '" . $this->db->escape($filters['entity_type']) . "'";
        }

        if (!empty($filters['key']) && is_string($filters['key'])) {
            $where[] = "g.`key` LIKE '%" . $this->db->escape(trim($filters['key'])) . "%'";
        }

        if (!empty($filters['name']) && is_string($filters['name'])) {
            $where[] = "COALESCE(gd.`name`, gd_en.`name`, g.`name`) LIKE '%" . $this->db->escape(trim($filters['name'])) . "%'";
        }

        $status = $filters['status'] ?? 'all';
        if (in_array($status, ['0', '1', 0, 1], true)) {
            $where[] = "g.`status` = '" . (int)$status . "'";
        }

        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    /** @param array<string, mixed> $filters */
    private function fieldListWhere(array $filters): string {
        $where = [];

        if (!empty($filters['entity_type']) && is_string($filters['entity_type'])) {
            $where[] = "f.`entity_type` = '" . $this->db->escape($filters['entity_type']) . "'";
        }

        if (!empty($filters['group_id']) && is_scalar($filters['group_id'])) {
            $where[] = "f.`group_id` = '" . (int)$filters['group_id'] . "'";
        }

        if (!empty($filters['key']) && is_string($filters['key'])) {
            $where[] = "f.`key` LIKE '%" . $this->db->escape(trim($filters['key'])) . "%'";
        }

        if (!empty($filters['type']) && is_string($filters['type']) && $this->isAllowedType($filters['type'])) {
            $where[] = "f.`type` = '" . $this->db->escape($filters['type']) . "'";
        }

        if (!empty($filters['visibility']) && is_string($filters['visibility']) && $this->isAllowedVisibility($filters['visibility'])) {
            $where[] = "f.`visibility` = '" . $this->db->escape($filters['visibility']) . "'";
        }

        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
