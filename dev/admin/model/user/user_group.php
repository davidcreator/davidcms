<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Model user/user_group — grupos e matriz de permissões da ACL (F2-6).
 * O JSON é {"access": [rotas], "modify": [rotas]} sem métodos (granularidade
 * base-route — Adendo F2-6 do ADR 0005).
 */
namespace Reamur\Admin\Model\User;

class User_group extends \Reamur\System\Engine\Model {

    /**
     * @param array<int, string> $access
     * @param array<int, string> $modify
     */
    public function addUserGroup(string $name, array $access, array $modify): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('user_group') . "` SET
                `name`       = '" . $this->db->escape($name) . "',
                `permission` = '" . $this->db->escape($this->encode($access, $modify)) . "'"
        );

        return $this->db->getLastId();
    }

    /**
     * @param array<int, string> $access
     * @param array<int, string> $modify
     */
    public function editUserGroup(int $user_group_id, string $name, array $access, array $modify): void {
        $this->db->query(
            "UPDATE `" . $this->t('user_group') . "` SET
                `name`       = '" . $this->db->escape($name) . "',
                `permission` = '" . $this->db->escape($this->encode($access, $modify)) . "'
             WHERE `user_group_id` = '" . $user_group_id . "'"
        );
    }

    /** @return array<string, mixed> */
    public function getUserGroup(int $user_group_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('user_group') . "` WHERE `user_group_id` = '" . $user_group_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /** @return array<int, array<string, mixed>> grupos com total de usuários vinculados */
    public function getUserGroups(): array {
        $query = $this->db->query(
            "SELECT ug.*,
                    (SELECT COUNT(u.user_id) FROM `" . $this->t('user') . "` AS u
                      WHERE u.user_group_id = ug.user_group_id) AS total_users
             FROM `" . $this->t('user_group') . "` AS ug ORDER BY ug.name"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** permission JSON (nunca inventa formato: access/modify) */
    private function encode(array $access, array $modify): string {
        return (string)json_encode([
            'access' => array_values(array_unique(array_map('strval', $access))),
            'modify' => array_values(array_unique(array_map('strval', $modify))),
        ]);
    }


    /** F4-04 (ADR 0027): total de usuários vinculados (0 = grupo segregável) */
    public function countUsers(int $user_group_id): int {
        $query = $this->db->query(
            "SELECT COUNT(*) AS c FROM `" . $this->t('user') . "` WHERE `user_group_id` = '" . $user_group_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['c'] : 0;
    }

    /**
     * F4-04 (ADR 0027): existe outro grupo (≠ $user_group_id) cuja ACL cobre
     * a rota 'user/user' (administração)? Conservador: LIKE no JSON, não-decoded —
     * heurística super-strings; falso-positivo tripolar simplifica o cache p/ admin.
     * Decisão documentada na ADR 0027 (§6).
     */
    public function hasOtherGroupGrantingUserAdmin(int $user_group_id): bool {
        $query = $this->db->query(
            // Conservador: '%user%user%' casa 'user/user' cru, 'user\\/user' escaped e variantes.
            "SELECT `user_group_id` FROM `" . $this->t('user_group') . "` 
             WHERE `user_group_id` <> '" . $user_group_id . "' 
               AND `permission` LIKE '%user%user%' 
             LIMIT 1"
        );

        return ($query instanceof \stdClass && !empty($query->row));
    }

    /**
     * F4-04 (ADR 0027): existe OUTRO usuário ATIVO (≠ $exclude_user_id) num
     * grupo cuja ACL cobre 'user/user'? Senão a operação tranca a gestão de
     * usuários. Mesmo padrão conservador do hasOtherGroupGrantingUserAdmin (tolerante a JSON com slash escapado — ADR 0027 §5).
     */
    public function hasOtherActiveUserAdmin(int $exclude_user_id): bool {
        $query = $this->db->query(
            "SELECT u.user_id
               FROM `" . $this->t('user') . "` AS u
               JOIN `" . $this->t('user_group') . "` AS g ON g.user_group_id = u.user_group_id
              WHERE u.user_id <> '" . $exclude_user_id . "'
                AND u.status = '1'
                AND g.permission LIKE '%user%user%'
              LIMIT 1"
        );

        return ($query instanceof \stdClass && !empty($query->row));
    }

    /** @return array<int, array<string, mixed>> grupos que concedem 'user/user' (delete-guard) */
    public function deleteUserGroup(int $user_group_id): void {
        $this->db->query(
            "DELETE FROM `" . $this->t('user_group') . "` WHERE `user_group_id` = '" . $user_group_id . "'"
        );
    }

    private function t(string $table): string {
        return $this->prefix() . $table;
    }

    private function prefix(): string {
        return defined('DB_PREFIX') ? DB_PREFIX : 'rms_';
    }
}
