<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * checkout/mp_payment (CATALOG) — vendedores do Mercado Pago para o aviso de
 * pagamento (F5-03, ADR 0035). As cobranças (rms_mp_payment), a situação do
 * pedido e o histórico são gravados pelo núcleo de pagamento,
 * system/library/payment/db_ledger.php (ADR 0097).
 */
namespace Reamur\Catalog\Model\Checkout;

class Mp_payment extends \Reamur\System\Engine\Model {

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

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        return array_map(fn ($row): array => $this->openSeller((array)$row), $rows);
    }

    public function getSellerByMpUserId(int $mp_user_id): array {
        return $this->openSeller($this->row(
            "SELECT `mp_seller_id`, `mp_user_id`, `nickname`, `access_token`
               FROM `" . $this->t('mp_seller') . "`
              WHERE `mp_user_id` = '" . (int)$mp_user_id . "' AND `status` = 1"
        ));
    }

    public function getSellerById(int $mp_seller_id): array {
        return $this->openSeller($this->row(
            "SELECT `mp_seller_id`, `mp_user_id`, `nickname`, `access_token`
               FROM `" . $this->t('mp_seller') . "`
              WHERE `mp_seller_id` = '" . (int)$mp_seller_id . "'"
        ));
    }

    /**
     * ADR 0094 (S9): o access_token sai do banco cifrado e chega aqui pronto
     * para uso. `token_locked` = há token gravado que a chave do config.php
     * do catálogo não abre; o token vem VAZIO (nunca o texto cifrado).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function openSeller(array $row): array {
        if ($row === []) {
            return [];
        }

        $stored = (string)($row['access_token'] ?? '');
        $box    = $this->secrets();

        $row['token_locked'] = $box->locked($stored, \Reamur\System\Library\Security\SecretBox::CTX_MP_ACCESS);
        $row['access_token'] = $box->open($stored, \Reamur\System\Library\Security\SecretBox::CTX_MP_ACCESS);

        return $row;
    }

    private function row(string $sql): array {
        $query = $this->db->query($sql);

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

}
