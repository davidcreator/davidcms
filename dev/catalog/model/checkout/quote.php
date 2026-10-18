<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * checkout/quote (CATALOG) — formula-de-orçamento público (F2-17): grava em
 * rms_order (customer_id=0, preço SEMPRE 0.0000 — orçamento sem valor,
 * Dicionário §6) + itens + histórico inicial, espelhando as regras contábeis
 * do F2-14 server-side (linha = qty × price; total = Σ; preço do POST é
 * ignorado). O ciclo de vida depois (status, reply) vive no admin.
 */
namespace Reamur\Catalog\Model\Checkout;

class Quote extends \Reamur\System\Engine\Model {

    /**
     * order_status_id inicial: o PRIMEIRO status semeado (en-US, 'Received').
     * Seed do wizard garante a existência; fallback defensivo = 1.
     */
    public function getInitialStatusId(): int {
        $query = $this->db->query(
            "SELECT `order_status_id` FROM `" . $this->t('order_status') . "`
              ORDER BY `order_status_id` ASC LIMIT 1"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['order_status_id'] : 1;
    }

    /**
     * @param array<string, mixed> $order Payload normalizado do controller
     * @param array<int, array{name: string, quantity: int}> $products Linhas já saneadas
     */
    public function addQuote(array $order, array $products): int {
        $total = 0.0;

        foreach ($products as $p) {
            // Preço público = 0.0000 (orçamento): linha = qty × 0
            $total += (int)$p['quantity'] * 0.0;
        }

        $status_id = $this->getInitialStatusId();

        $this->db->query(
            "INSERT INTO `" . $this->t('order') . "` SET
                `customer_id`   = 0,
                `firstname`     = '" . $this->db->escape((string)$order['firstname']) . "',
                `lastname`      = '" . $this->db->escape((string)$order['lastname']) . "',
                `email`         = '" . $this->db->escape((string)$order['email']) . "',
                `telephone`     = '" . $this->db->escape((string)$order['telephone']) . "',
                `total`         = '" . number_format($total, 4, '.', '') . "',
                `order_status_id` = '" . $status_id . "',
                `comment`       = '" . $this->db->escape((string)$order['comment']) . "',
                `ip`            = '" . $this->db->escape((string)$order['ip']) . "',
                `date_added`    = NOW(),
                `date_modified` = NOW()"
        );

        $order_id = (int)$this->db->getLastId();

        if (!$order_id) {
            return 0;
        }

        foreach ($products as $p) {
            $this->db->query(
                "INSERT INTO `" . $this->t('order_product') . "` SET
                    `order_id` = '" . $order_id . "',
                    `name`     = '" . $this->db->escape((string)$p['name']) . "',
                    `quantity` = '" . (int)$p['quantity'] . "',
                    `price`    = '0.0000',
                    `total`    = '0.0000'"
            );
        }

        // Histórico inicial (mesma semântica do F2-14: cada mudança de status vira registro)
        $this->db->query(
            "INSERT INTO `" . $this->t('order_history') . "` SET
                `order_id`        = '" . $order_id . "',
                `order_status_id` = '" . $status_id . "',
                `comment`         = '',
                `date_added`      = NOW()"
        );

        return $order_id;
    }

    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
