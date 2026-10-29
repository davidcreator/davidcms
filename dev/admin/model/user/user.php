<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Model user/user — CRUD de usuários do painel (F2-6).
 * Sempre escape/prepare do driver; password_hash somente aqui e no wizard.
 * Parâmetro por referência é PROIBIDO em model (Proxy::__call) — Retorna array/estado.
 */
namespace Reamur\Admin\Model\User;

class User extends \Reamur\System\Engine\Model {

    /**
     * @param array<string, mixed> $data user_group_id, username, password (limpa), email, firstname, lastname, status
     */
    public function addUser(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('user') . "` SET
                `user_group_id` = '" . (int)$data['user_group_id'] . "',
                `username`      = '" . $this->db->escape((string)$data['username']) . "',
                `password`      = '" . $this->db->escape(password_hash((string)$data['password'], PASSWORD_DEFAULT)) . "',
                `email`         = '" . $this->db->escape((string)$data['email']) . "',
                `firstname`     = '" . $this->db->escape((string)$data['firstname']) . "',
                `lastname`      = '" . $this->db->escape((string)$data['lastname']) . "',
                `image`         = '',
                `status`        = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `date_added`    = NOW()"
        );

        return $this->db->getLastId();
    }

    /**
     * Senha vazia em $data['password'] → preserva a atual (regra do formulário).
     *
     * @param array<string, mixed> $data
     */
    public function editUser(int $user_id, array $data): void {
        $sql = "UPDATE `" . $this->t('user') . "` SET
                `user_group_id` = '" . (int)$data['user_group_id'] . "',
                `username`      = '" . $this->db->escape((string)$data['username']) . "',
                `email`         = '" . $this->db->escape((string)$data['email']) . "',
                `firstname`     = '" . $this->db->escape((string)$data['firstname']) . "',
                `lastname`      = '" . $this->db->escape((string)$data['lastname']) . "',
                `status`        = '" . (int)(!empty($data['status']) ? 1 : 0) . "'";

        if (!empty($data['password'])) {
            $sql .= ", `password` = '" . $this->db->escape(password_hash((string)$data['password'], PASSWORD_DEFAULT)) . "'";
        }

        $sql .= " WHERE `user_id` = '" . $user_id . "'";

        $this->db->query($sql);
    }

    /**
     * Auto-perfil (F4-04, ADR 0027): SOMENTE campos de identidade — nunca
     * username/grupo/status (troca de grupo fica em user/user, com ACL explícita).
     * Password entra só quando não vazia (bcrypt, PASSWORD_DEFAULT).
     *
     * @param array<string, string> $data email, firstname, lastname, password_new
     */
    public function editProfile(int $user_id, array $data): void {
        $sql = "UPDATE `" . $this->t('user') . "` SET
                    `email`     = '" . $this->db->escape((string)$data['email']) . "',
                    `firstname` = '" . $this->db->escape((string)$data['firstname']) . "',
                    `lastname`  = '" . $this->db->escape((string)$data['lastname']) . "'";

        if ((string)$data['password_new'] !== '') {
            $sql .= ", `password` = '" . $this->db->escape(password_hash((string)$data['password_new'], PASSWORD_DEFAULT)) . "'";
        }

        $sql .= " WHERE `user_id` = '" . $user_id . "'";

        $this->db->query($sql);
    }

    public function deleteUser(int $user_id): void {
        // F9-25: manual cascade (the schema intentionally has no foreign keys).
        // Older installations may not have run the patterns migration yet.
        if ($user_id > 0) {
            $patternTable = $this->t('cms_block_pattern');
            $exists = null;
            try {
                $exists = $this->db->query(
                    "SELECT `TABLE_NAME` FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $this->db->escape($patternTable) . "' LIMIT 1"
                );
            } catch (\Throwable $exception) {
                // Older DB users may not be able to inspect information_schema.
            }
            if ($exists instanceof \stdClass && !empty($exists->num_rows)) {
                // If this delete fails, let the error propagate; do not remove the owner row first.
                $this->db->query("DELETE FROM `" . $patternTable . "` WHERE `owner_user_id` = '" . $user_id . "'");
            }
        }
        $this->db->query("DELETE FROM `" . $this->t('user') . "` WHERE `user_id` = '" . $user_id . "'");
    }

    /** @return array<string, mixed> */
    public function getUser(int $user_id): array {
        $query = $this->db->query("SELECT * FROM `" . $this->t('user') . "` WHERE `user_id` = '" . $user_id . "'");

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /** @return array<string, mixed> */
    public function getUserByUsername(string $username): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('user') . "` WHERE `username` = '" . $this->db->escape($username) . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /** @param array<string, mixed> $filter_data search, group_id, status, start, limit
     *  @return array<int, array<string, mixed>> usuários com nome do grupo
     */
    public function getUsers(array $filter_data = []): array {
        $sql = "SELECT u.*, ug.name AS group_name FROM `" . $this->t('user') . "` AS u
                LEFT JOIN `" . $this->t('user_group') . "` AS ug ON (u.user_group_id = ug.user_group_id)";
        $where = $this->userFilterWhere($filter_data);
        if ($where !== '') { $sql .= ' WHERE ' . $where; }
        $start = max(0, (int)($filter_data['start'] ?? 0));
        $limit = min(100, max(1, (int)($filter_data['limit'] ?? 20)));
        $sql .= " ORDER BY u.username ASC, u.user_id ASC LIMIT " . $start . "," . $limit;

        $query = $this->db->query($sql);

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @param array<string, mixed> $filter_data */
    public function getTotalUsers(array $filter_data = []): int {
        $sql = "SELECT COUNT(*) AS `total` FROM `" . $this->t('user') . "` AS u
                LEFT JOIN `" . $this->t('user_group') . "` AS ug ON (u.user_group_id = ug.user_group_id)";
        $where = $this->userFilterWhere($filter_data);
        if ($where !== '') { $sql .= ' WHERE ' . $where; }
        $query = $this->db->query($sql);

        return ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['total'] : 0;
    }

    /** @return array<int, array<string, mixed>> */
    public function getUserGroups(): array {
        $query = $this->db->query("SELECT * FROM `" . $this->t('user_group') . "` ORDER BY `name`");

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @param array<string, mixed> $filter_data */
    private function userFilterWhere(array $filter_data): string {
        $where = [];
        foreach (['username' => 'u.username', 'email' => 'u.email'] as $key => $column) {
            $value = trim((string)($filter_data[$key] ?? ''));
            if ($value !== '') { $where[] = $column . " LIKE '%" . $this->db->escape($value) . "%'"; }
        }
        $groupId = (int)($filter_data['user_group_id'] ?? 0);
        if ($groupId > 0) { $where[] = 'u.user_group_id = ' . $groupId; }
        $status = (string)($filter_data['status'] ?? 'all');
        if (in_array($status, ['0', '1'], true)) { $where[] = "u.status = '" . $status . "'"; }
        return implode(' AND ', $where);
    }

    private function t(string $table): string {
        return $this->prefix() . $table;
    }

    private function prefix(): string {
        return defined('DB_PREFIX') ? DB_PREFIX : 'rms_';
    }
}
