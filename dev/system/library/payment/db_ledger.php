<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Cobranças no banco (ADR 0097, EX-03).
 *
 * Hoje a tabela é `mp_payment`, a mesma de antes do contrato: nenhuma mudança
 * de banco nesta etapa. As colunas com nome do Mercado Pago guardam o dado
 * geral (preference_id = identificador da cobrança, init_point = endereço de
 * pagamento, mp_seller_id = conta que recebe, marketplace_fee = parte da
 * plataforma). `payment_id` é numérico. Quando entrar um segundo meio, a
 * tabela geral entra aqui e só esta classe muda.
 */
namespace Reamur\System\Library\Payment;

final class DbLedger implements Ledger {
    /**
     * @param object $db     serviço de banco do CMS (query, escape)
     * @param string $prefix prefixo das tabelas (DB_PREFIX)
     */
    public function __construct(
        private object $db,
        private string $prefix,
    ) {}

    public function latestForOrder(int $orderId): array {
        return $this->row(
            "SELECT * FROM `" . $this->prefix . "mp_payment`
              WHERE `order_id` = '" . $orderId . "'
              ORDER BY `mp_payment_id` DESC LIMIT 1"
        );
    }

    public function byToken(string $token): array {
        $token = trim($token);

        if ($token === '' || strlen($token) > 48) {
            return [];
        }

        return $this->row(
            "SELECT * FROM `" . $this->prefix . "mp_payment`
              WHERE `public_token` = '" . $this->escape($token) . "'"
        );
    }

    public function add(int $orderId, string $token, float $amount, Charge $charge): void {
        $this->query(
            "INSERT INTO `" . $this->prefix . "mp_payment` SET
              `order_id`        = '" . $orderId . "',
              `mp_seller_id`    = '" . $charge->account . "',
              `preference_id`   = '" . $this->escape($charge->reference) . "',
              `public_token`    = '" . $this->escape($token) . "',
              `amount`          = '" . $amount . "',
              `marketplace_fee` = '" . $charge->fee . "',
              `currency_id`     = '" . $this->escape($charge->currency) . "',
              `status`          = 'pending',
              `init_point`      = '" . $this->escape($charge->url) . "',
              `date_added`      = NOW(),
              `date_modified`   = NOW()"
        );
    }

    public function record(int $id, string $paymentId, string $status): void {
        // A coluna é numérica: gravar um identificador em texto viraria 0 e
        // o aviso repetido deixaria de ser reconhecido.
        if (!ctype_digit($paymentId)) {
            throw new \InvalidArgumentException('payment ledger: identificador de pagamento não numérico');
        }

        $this->query(
            "UPDATE `" . $this->prefix . "mp_payment` SET
              `payment_id`    = '" . $paymentId . "',
              `status`        = '" . $this->escape($status) . "',
              `date_modified` = NOW()
              WHERE `mp_payment_id` = '" . $id . "'"
        );
    }

    public function moveOrder(int $orderId, int $orderStatusId, string $comment): void {
        $this->query(
            "UPDATE `" . $this->prefix . "order` SET
              `order_status_id` = '" . $orderStatusId . "',
              `date_modified`   = NOW()
              WHERE `order_id` = '" . $orderId . "'"
        );

        $this->query(
            "INSERT INTO `" . $this->prefix . "order_history` SET
              `order_id`        = '" . $orderId . "',
              `order_status_id` = '" . $orderStatusId . "',
              `comment`         = '" . $this->escape($comment) . "',
              `date_added`      = NOW()"
        );
    }

    /**
     * Linha do banco com as chaves do contrato (ver Ledger).
     *
     * @return array<string, mixed>
     */
    private function row(string $sql): array {
        $query = $this->query($sql);
        $row   = ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];

        if ($row === []) {
            return [];
        }

        return [
            'id'         => (int)($row['mp_payment_id'] ?? 0),
            'order_id'   => (int)($row['order_id'] ?? 0),
            'token'      => (string)($row['public_token'] ?? ''),
            'status'     => (string)($row['status'] ?? ''),
            'url'        => (string)($row['init_point'] ?? ''),
            'payment_id' => (string)($row['payment_id'] ?? ''),
        ];
    }

    private function query(string $sql): mixed {
        return $this->db->query($sql);
    }

    private function escape(string $value): string {
        return (string)$this->db->escape($value);
    }
}
