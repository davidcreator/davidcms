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

    /** @return array<int, array<string, mixed>> usuários com nome do grupo */
    public function getUsers(): array {
        $query = $this->db->query(
            "SELECT u.*, ug.name AS group_name FROM `" . $this->t('user') . "` AS u
             LEFT JOIN `" . $this->t('user_group') . "` AS ug ON (u.user_group_id = ug.user_group_id)
             ORDER BY u.username"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getUserGroups(): array {
        $query = $this->db->query("SELECT * FROM `" . $this->t('user_group') . "` ORDER BY `name`");

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    private function t(string $table): string {
        return $this->prefix() . $table;
    }

    private function prefix(): string {
        return defined('DB_PREFIX') ? DB_PREFIX : 'rms_';
    }
}
