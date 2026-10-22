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

    /** @param array<string, mixed> $filter_data @return array<int, array<string, mixed>> grade (tokens NÃO sobem) */
    public function getSellers(array $filter_data = []): array {
        $where = $this->sellerFilterWhere($filter_data);
        $sql = "SELECT `mp_seller_id`, `mp_user_id`, `nickname`, `email`, `scope`,
                    `token_expires_at`, `status`, `date_added`, `date_modified`
               FROM `" . $this->t('mp_seller') . "`";
        if ($where !== '') { $sql .= ' WHERE ' . $where; }
        $start = max(0, (int)($filter_data['start'] ?? 0));
        $limit = min(100, max(1, (int)($filter_data['limit'] ?? 10)));
        $sql .= " ORDER BY `date_added` DESC, `mp_seller_id` DESC LIMIT " . $start . "," . $limit;
        $query = $this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @param array<string, mixed> $filter_data */
    public function getTotalSellers(array $filter_data = []): int {
        $sql = "SELECT COUNT(*) AS `total` FROM `" . $this->t('mp_seller') . "`";
        $where = $this->sellerFilterWhere($filter_data);
        if ($where !== '') { $sql .= ' WHERE ' . $where; }
        $query = $this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['total'] : 0;
    }

    /** @param array<string, mixed> $filter_data */
    private function sellerFilterWhere(array $filter_data): string {
        $where = [];
        $search = trim((string)($filter_data['search'] ?? ''));
        if ($search !== '') {
            $like = "'%" . $this->db->escape($search) . "%'";
            $where[] = "(`nickname` LIKE " . $like . " OR `email` LIKE " . $like . " OR CAST(`mp_user_id` AS CHAR) LIKE " . $like . ")";
        }
        $status = (string)($filter_data['status'] ?? 'all');
        if (in_array($status, ['0', '1'], true)) { $where[] = "`status` = '" . $status . "'"; }
        return implode(' AND ', $where);
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

    // ── F5-03 (ADR 0035): checkout com split ────────────────────────────────

    /** Vendedor que recebe o líquido: o PRIMEIRO conectado ainda ativo (§5) */
    public function getActiveSeller(): array {
        $query = $this->db->query(
            "SELECT `mp_seller_id`, `mp_user_id`, `nickname`, `email`, `access_token`
               FROM `" . $this->t('mp_seller') . "`
              WHERE `status` = 1
              ORDER BY `mp_seller_id` ASC LIMIT 1"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /** Tentativa vigente do pedido (a mais recente, qualquer status) */
    public function getPaymentByOrder(int $order_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('mp_payment') . "`
              WHERE `order_id` = '" . (int)$order_id . "'
              ORDER BY `mp_payment_id` DESC LIMIT 1"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /** Grava a preference criada (status pending). public_token NUNCA do order_id. */
    public function addPayment(array $p): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('mp_payment') . "` SET
              `order_id`        = '" . (int)$p['order_id'] . "',
              `mp_seller_id`    = '" . (int)$p['mp_seller_id'] . "',
              `preference_id`   = '" . $this->db->escape((string)$p['preference_id']) . "',
              `public_token`    = '" . $this->db->escape((string)$p['public_token']) . "',
              `amount`          = '" . (float)$p['amount'] . "',
              `marketplace_fee` = '" . (float)$p['marketplace_fee'] . "',
              `currency_id`     = '" . $this->db->escape((string)($p['currency_id'] ?? 'BRL')) . "',
              `status`          = 'pending',
              `init_point`      = '" . $this->db->escape((string)$p['init_point']) . "',
              `date_added`      = NOW(),
              `date_modified`   = NOW()"
        );

        return (int)$this->db->getLastId();
    }

    /** Grade paginada: payment + pedido + vendedor (sem tokens). */
    public function getPayments(array $filter_data = []): array {
        $sql = "SELECT p.`mp_payment_id`, p.`order_id`, p.`preference_id`, p.`payment_id`,
                    p.`public_token`, p.`amount`, p.`marketplace_fee`, p.`currency_id`,
                    p.`status`, p.`date_added`,
                    o.`firstname`, o.`lastname`, o.`order_status_id`,
                    s.`nickname` AS `seller_nickname`, s.`mp_user_id` AS `seller_mp_user_id`
               FROM `" . $this->t('mp_payment') . "` p
               LEFT JOIN `" . $this->t('order') . "` o ON o.`order_id` = p.`order_id`
               LEFT JOIN `" . $this->t('mp_seller') . "` s ON s.`mp_seller_id` = p.`mp_seller_id`";
        $where = $this->paymentFilterWhere($filter_data);
        if ($where !== '') { $sql .= ' WHERE ' . $where; }
        $start = max(0, (int)($filter_data['start'] ?? 0));
        $limit = min(100, max(1, (int)($filter_data['limit'] ?? 10)));
        $sql .= " ORDER BY p.`mp_payment_id` DESC LIMIT " . $start . "," . $limit;
        $query = $this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @param array<string, mixed> $filter_data */
    public function getTotalPayments(array $filter_data = []): int {
        $sql = "SELECT COUNT(*) AS `total` FROM `" . $this->t('mp_payment') . "` p
               LEFT JOIN `" . $this->t('order') . "` o ON o.`order_id` = p.`order_id`";
        $where = $this->paymentFilterWhere($filter_data);
        if ($where !== '') { $sql .= ' WHERE ' . $where; }
        $query = $this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['total'] : 0;
    }

    /** @param array<string, mixed> $filter_data */
    private function paymentFilterWhere(array $filter_data): string {
        $where = [];
        $orderId = (int)($filter_data['order_id'] ?? 0);
        if ($orderId > 0) { $where[] = 'p.`order_id` = ' . $orderId; }
        $customer = trim((string)($filter_data['customer'] ?? ''));
        if ($customer !== '') {
            $like = "'%" . $this->db->escape($customer) . "%'";
            $where[] = "(o.`firstname` LIKE " . $like . " OR o.`lastname` LIKE " . $like . " OR o.`email` LIKE " . $like . ")";
        }
        $status = (string)($filter_data['status'] ?? 'all');
        if (in_array($status, ['pending', 'approved', 'rejected', 'cancelled', 'refunded', 'in_process'], true)) {
            $where[] = "p.`status` = '" . $this->db->escape($status) . "'";
        }
        return implode(' AND ', $where);
    }

    /** Tabela com prefixo (mesmo helper dos demais models) */
    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
