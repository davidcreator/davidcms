<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * checkout/mp_payment (CATALOG) — leitura/escrita SQL do checkout MP (F5-03,
 * ADR 0035). O controller público checkout/payment só fala HTTP; toda trilha
 * em rms_mp_payment / rms_order / rms_order_history mora aqui.
 */
namespace Reamur\Catalog\Model\Checkout;

class Mp_payment extends \Reamur\System\Engine\Model {

    /**
     * Grava a tentativa de pagamento (status pending) após createPreference.
     * public_token = chave do link público, NUNCA se reconstrói de order_id.
     */
    public function addPayment(array $p): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('mp_payment') . "` SET
              `order_id`       = '" . (int)$p['order_id'] . "',
              `mp_seller_id`   = '" . (int)$p['mp_seller_id'] . "',
              `preference_id`  = '" . $this->db->escape((string)$p['preference_id']) . "',
              `public_token`   = '" . $this->db->escape((string)$p['public_token']) . "',
              `amount`         = '" . (float)$p['amount'] . "',
              `marketplace_fee`= '" . (float)$p['marketplace_fee'] . "',
              `currency_id`    = '" . $this->db->escape((string)($p['currency_id'] ?? 'BRL')) . "',
              `status`         = 'pending',
              `init_point`     = '" . $this->db->escape((string)$p['init_point']) . "',
              `date_added`     = NOW(),
              `date_modified`  = NOW()"
        );

        return (int)$this->db->getLastId();
    }

    public function getPaymentByToken(string $token): array {
        $token = trim($token);

        if ($token === '' || strlen($token) > 48) {
            return [];
        }

        return $this->row(
            "SELECT * FROM `" . $this->t('mp_payment') . "`
              WHERE `public_token` = '" . $this->db->escape($token) . "'"
        );
    }

    /** Tentativa vigente do pedido (qualquer status — a mais recente) */
    public function getPaymentByOrder(int $order_id): array {
        return $this->row(
            "SELECT * FROM `" . $this->t('mp_payment') . "`
              WHERE `order_id` = '" . $order_id . "'
              ORDER BY `mp_payment_id` DESC LIMIT 1"
        );
    }

    /**
     * Reflete o status CONFIRMADO pelo MP (consulta getPayment — a verdade
     * nunca vem do corpo do webhook): atualiza a linha e, quando o status é
     * terminal, o pedido + histórico. Idempotente: mesma assinatura
     * (payment_id + status) já gravada = noop, e a resposta ao MP é 200 de
     * qualquer forma (sem loop de retry).
     *
     * @return bool true quando HOUVE transição efetiva (chama webhooks)
     */
    public function reflectPaymentStatus(int $mp_payment_id, int $payment_id, string $status, string $history_comment): bool {
        $row = $this->row(
            "SELECT * FROM `" . $this->t('mp_payment') . "`
              WHERE `mp_payment_id` = '" . $mp_payment_id . "'"
        );

        if (!$row) {
            return false;
        }

        if ((int)$row['payment_id'] === $payment_id && $row['status'] === $status) {
            return false; // replay: nada muda, e o caller ainda responde 200
        }

        $this->db->query(
            "UPDATE `" . $this->t('mp_payment') . "` SET
              `payment_id`    = '" . $payment_id . "',
              `status`        = '" . $this->db->escape($status) . "',
              `date_modified` = NOW()
              WHERE `mp_payment_id` = '" . $mp_payment_id . "'"
        );

        $new_order_status = match ($status) {
            'approved'            => 4, // Approved
            'rejected', 'cancelled' => 6, // Canceled
            default               => 0, // pending/refunded/...: só registra
        };

        if ($new_order_status > 0 && (int)$row['order_id'] > 0) {
            $order_id = (int)$row['order_id'];

            $this->db->query(
                "UPDATE `" . $this->t('order') . "` SET
                  `order_status_id` = '" . $new_order_status . "',
                  `date_modified`   = NOW()
                  WHERE `order_id` = '" . $order_id . "'"
            );

            $this->db->query(
                "INSERT INTO `" . $this->t('order_history') . "` SET
                  `order_id`        = '" . $order_id . "',
                  `order_status_id` = '" . $new_order_status . "',
                  `comment`         = '" . $this->db->escape($history_comment) . "',
                  `date_added`      = NOW()"
            );
        }

        return true;
    }

    public function getOrder(int $order_id): array {
        return $this->row(
            "SELECT * FROM `" . $this->t('order') . "`
              WHERE `order_id` = '" . (int)$order_id . "'"
        );
    }

    /** Vendedores com conexão utilizável (notify itera quando falta user_id) */
    public function getActiveSellers(): array {
        $query = $this->db->query(
            "SELECT `mp_seller_id`, `mp_user_id`, `nickname`, `access_token`
               FROM `" . $this->t('mp_seller') . "`
              WHERE `status` = 1
              ORDER BY `mp_seller_id` ASC"
        );

        return (array)$query->rows;
    }

    public function getSellerByMpUserId(int $mp_user_id): array {
        return $this->row(
            "SELECT `mp_seller_id`, `mp_user_id`, `nickname`, `access_token`
               FROM `" . $this->t('mp_seller') . "`
              WHERE `mp_user_id` = '" . $mp_user_id . "' AND `status` = 1"
        );
    }

    public function getSellerById(int $mp_seller_id): array {
        return $this->row(
            "SELECT `mp_seller_id`, `mp_user_id`, `nickname`, `access_token`
               FROM `" . $this->t('mp_seller') . "`
              WHERE `mp_seller_id` = '" . (int)$mp_seller_id . "'"
        );
    }

    private function row(string $sql): array {
        $query = $this->db->query($sql);

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
