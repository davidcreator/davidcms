<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * sale/order — model do CRUD de pedidos/orçamentos (F2-14, ADR 0014).
 * Tabelas rms_order (+ rms_order_product, rms_order_history, rms_order_status)
 * INSTALADAS e semeadas pelo wizard — 7º módulo consecutivo sem DDL novo.
 * Semânticas do Dicionário-de-Dados: customer_id=0 visitante (orçamento sem
 * cadastro); total 0.0000 = orçamento sem preço; cada mudança de status vira
 * registro em rms_order_history. Totais SEMPRE recalculados no servidor
 * (nunca confiados ao POST) — qty*price por linha, Σ no pedido.
 */
namespace Reamur\Admin\Model\Sale;

class Order extends \Reamur\System\Engine\Model {

    /**
     * Grade paginável: totais + nome do status com coalesce idioma ativo → en-US.
     * Sem limite, mantém o comportamento histórico de retornar todas as linhas.
     *
     * @param array<string, mixed> $filters Filtros normalizados pelo controller
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(array $filters = [], int $start = 0, int $limit = 0): array {
        $lang = $this->currentLanguageId();
        $limitSql = $limit > 0 ? ' LIMIT ' . max(0, $start) . ', ' . max(1, $limit) : '';

        $query = $this->db->query(
            "SELECT o.`order_id`, o.`firstname`, o.`lastname`, o.`email`, o.`total`,
                    o.`order_status_id`, o.`date_added`, o.`date_modified`,
                    COALESCE(sn.`name`, se.`name`, '') AS `status_name`
               FROM `" . $this->t('order') . "` o
          LEFT JOIN `" . $this->t('order_status') . "` sn
                 ON sn.`order_status_id` = o.`order_status_id` AND sn.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('order_status') . "` se
                 ON se.`order_status_id` = o.`order_status_id` AND se.`language_id` = 1"
            . $this->orderFilterSql($filters) . "
           ORDER BY o.`date_added` DESC, o.`order_id` DESC"
            . $limitSql
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** Total para paginação, usando os mesmos filtros da grade. */
    public function getTotalOrders(array $filters = []): int {
        $query = $this->db->query(
            "SELECT COUNT(*) AS `total` FROM `" . $this->t('order') . "` o"
            . $this->orderFilterSql($filters)
        );

        return ($query instanceof \stdClass && isset($query->row['total'])) ? (int)$query->row['total'] : 0;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function orderFilterSql(array $filters): string {
        $where = [];
        $order_id = max(0, (int)($filters['order_id'] ?? 0));

        if ($order_id > 0) {
            $where[] = "o.`order_id` = '" . $order_id . "'";
        }

        $customer = is_scalar($filters['customer'] ?? null) ? trim((string)$filters['customer']) : '';

        if ($customer !== '') {
            $customer = $this->db->escape($customer);
            $where[] = "(CONCAT_WS(' ', o.`firstname`, o.`lastname`) LIKE '%" . $customer . "%'
                        OR o.`firstname` LIKE '%" . $customer . "%'
                        OR o.`lastname` LIKE '%" . $customer . "%')";
        }

        $email = is_scalar($filters['email'] ?? null) ? trim((string)$filters['email']) : '';

        if ($email !== '') {
            $where[] = "o.`email` LIKE '%" . $this->db->escape($email) . "%'";
        }

        $status_id = max(0, (int)($filters['status_id'] ?? 0));

        if ($status_id > 0) {
            $where[] = "o.`order_status_id` = '" . $status_id . "'";
        }

        return $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    /**
     * @return array<string, mixed> Linha principal (vazio = inexistente)
     */
    public function getOrder(int $order_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('order') . "` WHERE `order_id` = '" . $order_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @return array<int, array<string, mixed>> Itens do pedido (linhas order_product)
     */
    public function getOrderProducts(int $order_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('order_product') . "` WHERE `order_id` = '" . $order_id . "' ORDER BY `order_product_id` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * @return array<int, array<string, mixed>> Histórico (mais novo primeiro)
     */
    public function getOrderHistory(int $order_id): array {
        $lang = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT h.`order_history_id`, h.`order_status_id`, h.`comment`, h.`date_added`,
                    COALESCE(sn.`name`, se.`name`, '') AS `status_name`
               FROM `" . $this->t('order_history') . "` h
          LEFT JOIN `" . $this->t('order_status') . "` sn
                 ON sn.`order_status_id` = h.`order_status_id` AND sn.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('order_status') . "` se
                 ON se.`order_status_id` = h.`order_status_id` AND se.`language_id` = 1
              WHERE h.`order_id` = '" . $order_id . "'
           ORDER BY h.`order_history_id` DESC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * @return array<int, array<string, mixed>> Opções de status (coalesce idioma ativo → en-US)
     */
    public function getOrderStatusOptions(): array {
        $lang = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT s.`order_status_id`, COALESCE(sn.`name`, se.`name`, '') AS `name`
               FROM (SELECT DISTINCT `order_status_id` FROM `" . $this->t('order_status') . "`) s
          LEFT JOIN `" . $this->t('order_status') . "` sn
                 ON sn.`order_status_id` = s.`order_status_id` AND sn.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('order_status') . "` se
                 ON se.`order_status_id` = s.`order_status_id` AND se.`language_id` = 1
           ORDER BY s.`order_status_id` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * Cria pedido + itens + registro inicial de histórico.
     *
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function addOrder(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('order') . "` SET
                `customer_id`     = '" . (int)($data['customer_id'] ?? 0) . "',
                `firstname`       = '" . $this->db->escape((string)($data['firstname'] ?? '')) . "',
                `lastname`        = '" . $this->db->escape((string)($data['lastname'] ?? '')) . "',
                `email`           = '" . $this->db->escape((string)($data['email'] ?? '')) . "',
                `telephone`       = '" . $this->db->escape((string)($data['telephone'] ?? '')) . "',
                `total`           = '" . $this->db->escape((string)($data['total'] ?? '0.0000')) . "',
                `order_status_id` = '" . (int)($data['order_status_id'] ?? 0) . "',
                `comment`         = '" . $this->db->escape((string)($data['comment'] ?? '')) . "',
                `ip`              = '" . $this->db->escape((string)($data['ip'] ?? '')) . "',
                `date_added`      = NOW(),
                `date_modified`   = NOW()"
        );

        $order_id = (int)$this->db->getLastId();

        if (!$order_id) {
            return 0;
        }

        $this->syncProducts($order_id, (array)($data['products'] ?? []));
        $this->addHistory($order_id, (int)($data['order_status_id'] ?? 0), '');

        return $order_id;
    }

    /**
     * Edita pedido (date_modified=NOW), ressincroniza itens e registra
     * histórico QUANDO o status muda (Dicionário: cada mudança vira registro).
     *
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function editOrder(int $order_id, array $data): void {
        $this->db->query(
            "UPDATE `" . $this->t('order') . "` SET
                `customer_id`     = '" . (int)($data['customer_id'] ?? 0) . "',
                `firstname`       = '" . $this->db->escape((string)($data['firstname'] ?? '')) . "',
                `lastname`        = '" . $this->db->escape((string)($data['lastname'] ?? '')) . "',
                `email`           = '" . $this->db->escape((string)($data['email'] ?? '')) . "',
                `telephone`       = '" . $this->db->escape((string)($data['telephone'] ?? '')) . "',
                `total`           = '" . $this->db->escape((string)($data['total'] ?? '0.0000')) . "',
                `order_status_id` = '" . (int)($data['order_status_id'] ?? 0) . "',
                `comment`         = '" . $this->db->escape((string)($data['comment'] ?? '')) . "',
                `date_modified`   = NOW()
             WHERE `order_id` = '" . $order_id . "'"
        );

        $this->syncProducts($order_id, (array)($data['products'] ?? []));

        if ((int)($data['status_changed'] ?? 0)) {
            $this->addHistory($order_id, (int)($data['order_status_id'] ?? 0), '');
        }
    }

    /** Cascata manual (sem FK): pedido + itens + histórico */
    public function deleteOrder(int $order_id): void {
        $this->db->query("DELETE FROM `" . $this->t('order_product') . "` WHERE `order_id` = '" . $order_id . "'");
        $this->db->query("DELETE FROM `" . $this->t('order_history') . "` WHERE `order_id` = '" . $order_id . "'");
        $this->db->query("DELETE FROM `" . $this->t('order') . "` WHERE `order_id` = '" . $order_id . "'");
    }

    /**
     * Ressincroniza itens (delete-ALL + reinsert — o controller já validou e
     * recalculou cada linha: qtd ≥1, preço ≥0, total = qty*price).
     *
     * @param array<int, array<string, mixed>> $products
     */
    private function syncProducts(int $order_id, array $products): void {
        $this->db->query("DELETE FROM `" . $this->t('order_product') . "` WHERE `order_id` = '" . $order_id . "'");

        foreach ($products as $p) {
            $p = (array)$p;

            $this->db->query(
                "INSERT INTO `" . $this->t('order_product') . "` SET
                    `order_id` = '" . $order_id . "',
                    `name`     = '" . $this->db->escape((string)($p['name'] ?? '')) . "',
                    `quantity` = '" . max(1, (int)($p['quantity'] ?? 1)) . "',
                    `price`    = '" . $this->db->escape((string)($p['price'] ?? '0.0000')) . "',
                    `total`    = '" . $this->db->escape((string)($p['total'] ?? '0.0000')) . "'"
            );
        }
    }

    private function addHistory(int $order_id, int $order_status_id, string $comment): void {
        $this->db->query(
            "INSERT INTO `" . $this->t('order_history') . "` SET
                `order_id`        = '" . $order_id . "',
                `order_status_id` = '" . $order_status_id . "',
                `comment`         = '" . $this->db->escape($comment) . "',
                `date_added`      = NOW()"
        );
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

    /** Tabela com prefixo (mesmo helper dos demais models F2) */
    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
