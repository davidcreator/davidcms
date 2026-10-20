<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/marketplace (MODEL admin) — persistência dos vendedores Mercado
 * Pago conectados por OAuth (F5-02, ADR 0034). SQL vive EXCLUSIVAMENTE aqui
 * (regra 01 §1); o controller orquestra state/troca de código via Library.
 */
namespace Reamur\Admin\Model\Setting;

class Marketplace extends \Reamur\System\Engine\Model {

    /** @return array<int, array<string, mixed>> grade (tokens NÃO sobem) */
    public function getSellers(): array {
        $query = $this->db->query(
            "SELECT `mp_seller_id`, `mp_user_id`, `nickname`, `email`, `scope`,
                    `token_expires_at`, `status`, `date_added`, `date_modified`
               FROM `" . $this->t('mp_seller') . "`
              ORDER BY `date_added` DESC, `mp_seller_id` DESC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @return array<string, mixed> vendedor completo (com tokens — uso interno) */
    public function getSeller(int $mp_seller_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('mp_seller') . "` WHERE `mp_seller_id` = '" . $mp_seller_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * Reconexão do MESMO mp_user_id = UPDATE (dedupe natural do OAuth:
     * vendedor que clica "conectar" de novo atualiza tokens, não duplica).
     */
    public function upsertSeller(array $data): void {
        $this->db->query(
            "INSERT INTO `" . $this->t('mp_seller') . "` SET
               `mp_user_id`       = '" . (int)$data['mp_user_id'] . "',
               `nickname`         = '" . $this->db->escape((string)($data['nickname'] ?? '')) . "',
               `email`            = '" . $this->db->escape((string)($data['email'] ?? '')) . "',
               `access_token`     = '" . $this->db->escape((string)$data['access_token']) . "',
               `refresh_token`    = '" . $this->db->escape((string)($data['refresh_token'] ?? '')) . "',
               `scope`            = '" . $this->db->escape((string)($data['scope'] ?? '')) . "',
               `token_expires_at` = '" . $this->db->escape((string)$data['token_expires_at']) . "',
               `status`           = '1',
               `date_added`       = NOW(),
               `date_modified`    = NOW()
             ON DUPLICATE KEY UPDATE
               `nickname`         = VALUES(`nickname`),
               `email`            = VALUES(`email`),
               `access_token`     = VALUES(`access_token`),
               `refresh_token`    = VALUES(`refresh_token`),
               `scope`            = VALUES(`scope`),
               `token_expires_at` = VALUES(`token_expires_at`),
               `status`           = '1',
               `date_modified`    = NOW()"
        );
    }

    /** Rotação pós-refresh: apenas tokens + expiração + marca de modificação */
    public function rotateTokens(int $mp_seller_id, string $access_token, string $refresh_token, string $token_expires_at): void {
        $this->db->query(
            "UPDATE `" . $this->t('mp_seller') . "` SET
               `access_token`     = '" . $this->db->escape($access_token) . "',
               `refresh_token`    = '" . $this->db->escape($refresh_token) . "',
               `token_expires_at` = '" . $this->db->escape($token_expires_at) . "',
               `date_modified`    = NOW()
             WHERE `mp_seller_id` = '" . $mp_seller_id . "'"
        );
    }

    /**
     * Desconectar: apaga LOCALMENTE. Revogação no MP não é chamada de API —
     * o vendedor desautoriza o app no próprio painel Mercado Pago (texto na
     * tela deixa isso explícito — ADR 0034 §5).
     */
    public function deleteSeller(int $mp_seller_id): void {
        $this->db->query("DELETE FROM `" . $this->t('mp_seller') . "` WHERE `mp_seller_id` = '" . $mp_seller_id . "'");
    }

    /** Tabela com prefixo (mesmo helper dos demais models) */
    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
