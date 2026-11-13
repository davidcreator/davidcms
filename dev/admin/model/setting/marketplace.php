<?php declare(strict_types=1);
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
                    `token_expires_at`, `status`, `date_added`, `date_modified`,
                    `access_token`, `refresh_token`
               FROM `" . $this->t('mp_seller') . "`";
        if ($where !== '') { $sql .= ' WHERE ' . $where; }
        $start = max(0, (int)($filter_data['start'] ?? 0));
        $limit = min(100, max(1, (int)($filter_data['limit'] ?? 10)));
        $sql .= " ORDER BY `date_added` DESC, `mp_seller_id` DESC LIMIT " . $start . "," . $limit;
        $query = $this->db->query($sql);
        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        // ADR 0094 (S9): a grade só recebe a informação "os tokens abrem?".
        return array_map(function ($row): array {
            $row = $this->openSeller((array)$row);
            unset($row['access_token'], $row['refresh_token']);

            return $row;
        }, $rows);
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

        return $this->openSeller(($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : []);
    }

    /**
     * ADR 0094 (S9): tokens saem do banco cifrados e chegam aqui prontos para
     * uso. `token_locked` = há token gravado que a chave do config.php não
     * abre (chave trocada ou perdida): o token vem vazio e o vendedor precisa
     * reconectar. O texto cifrado nunca é devolvido como se fosse o token.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function openSeller(array $row): array {
        if ($row === []) {
            return [];
        }

        $box    = $this->secrets();
        $locked = false;

        foreach ([
            'access_token'  => \Reamur\System\Library\Security\SecretBox::CTX_MP_ACCESS,
            'refresh_token' => \Reamur\System\Library\Security\SecretBox::CTX_MP_REFRESH,
        ] as $column => $context) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            $stored       = (string)$row[$column];
            $locked       = $locked || $box->locked($stored, $context);
            $row[$column] = $box->open($stored, $context);
        }

        $row['token_locked'] = $locked;

        return $row;
    }

    /**
     * Reconexão do MESMO mp_user_id = UPDATE (dedupe natural do OAuth:
     * vendedor que clica "conectar" de novo atualiza tokens, não duplica).
     */
    public function upsertSeller(array $data): void {
        // ADR 0094 (S9): os tokens vão cifrados para o banco.
        $data['access_token']  = $this->secrets()->seal((string)$data['access_token'], \Reamur\System\Library\Security\SecretBox::CTX_MP_ACCESS);
        $data['refresh_token'] = $this->secrets()->seal((string)($data['refresh_token'] ?? ''), \Reamur\System\Library\Security\SecretBox::CTX_MP_REFRESH);

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
        $access_token  = $this->secrets()->seal($access_token, \Reamur\System\Library\Security\SecretBox::CTX_MP_ACCESS);
        $refresh_token = $this->secrets()->seal($refresh_token, \Reamur\System\Library\Security\SecretBox::CTX_MP_REFRESH);

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
    // As cobranças (rms_mp_payment) são gravadas e lidas pelo núcleo de
    // pagamento, system/library/payment/db_ledger.php (ADR 0097). Aqui ficam
    // os vendedores e a grade da tela.

    /**
     * Vendedores ativos, por ordem de cadastro, com o token aberto. O
     * PRIMEIRO é quem recebe as cobranças novas (ADR 0035 §5).
     *
     * @return list<array<string, mixed>>
     */
    public function getActiveSellers(): array {
        $query = $this->db->query(
            "SELECT `mp_seller_id`, `mp_user_id`, `nickname`, `email`, `access_token`
               FROM `" . $this->t('mp_seller') . "`
              WHERE `status` = 1
              ORDER BY `mp_seller_id` ASC"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        return array_values(array_map(fn ($row): array => $this->openSeller((array)$row), $rows));
    }

    /**
     * Vendedor ativo pelo usuário do Mercado Pago; [] se não há.
     *
     * @return array<string, mixed>
     */
    public function getSellerByMpUserId(int $mp_user_id): array {
        $query = $this->db->query(
            "SELECT `mp_seller_id`, `mp_user_id`, `nickname`, `email`, `access_token`
               FROM `" . $this->t('mp_seller') . "`
              WHERE `mp_user_id` = '" . $mp_user_id . "' AND `status` = 1"
        );

        return $this->openSeller(($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : []);
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

}
