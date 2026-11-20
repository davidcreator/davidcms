<?php
/**
 * JOW-payments — model interno (KZ-35).
 * Cobranças, chaves PIX e geração do BR Code (PIX Copia e Cola, EMV) em PHP puro
 * (TLV + CRC16-CCITT-FALSE), sem rede e sem libs externas.
 */
namespace Reamur\Admin\Model\Payments;

class Jow extends \Reamur\System\Engine\Model {

    // ── BR Code (EMV) / PIX Copia e Cola ────────────────────────────────
    private static function tlv(string $id, string $value): string {
        return $id . str_pad((string)strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    }

    private static function crc16(string $payload): string {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($payload); $i < $n; $i++) {
            $crc ^= (ord($payload[$i]) << 8);
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /** Monta o payload PIX (BR Code) a partir da chave, valor, cidade e descrição. */
    public function buildPixPayload(string $key, float $amount, string $description = ''): string {
        $store = (string)($this->config->get('jow_store_name') ?: 'ReamurCMS');
        $city  = strtoupper((string)($this->config->get('jow_city') ?: 'SAO PAULO'));
        $mcc   = (string)($this->config->get('jow_merchant_category') ?: '0000');

        $gui = self::tlv('00', 'br.gov.bcp.pix');
        $mai = self::tlv('26', self::tlv('00', 'br.gov.bcp.pix') . self::tlv('01', $key));
        $cat = self::tlv('52', $mcc);
        $cur = self::tlv('53', '986');
        $amt = self::tlv('54', number_format($amount, 2, '.', ''));
        $cty = self::tlv('58', 'BR');
        $name = self::tlv('59', strtoupper(mb_substr($store, 0, 25)));
        $cityTlv = self::tlv('60', mb_substr($city, 0, 15));
        $desc = $description !== '' ? self::tlv('05', mb_substr($description, 0, 25)) : '';
        // campo 62 (dados adicionais): txid obrigatório
        $txid = strtoupper(bin2hex(random_bytes(4)));
        $add = self::tlv('62', self::tlv('05', $txid));

        $payload = self::tlv('00', '01') . $gui . $mai . $cat . $cur . $amt . $cty . $name . $cityTlv . $desc . $add . '6304';
        return $payload . self::crc16($payload);
    }

    // ── Cobranças ────────────────────────────────────────────────────────
    public function addCharge(string $description, float $amount, string $method, string $payerEmail = '', ?string $dueDate = null): int {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "jow_charge` (`description`,`amount`,`currency`,`method`,`status`,`payer_email`,`due_date`,`date_added`) VALUES ('"
            . $this->db->escape(mb_substr($description, 0, 128)) . "'," . (float)$amount . ",'BRL','" . $this->db->escape($method)
            . "','pending','" . $this->db->escape($payerEmail) . "'," . ($dueDate ? "'" . $this->db->escape($dueDate) . "'" : "NULL") . ", NOW())");
        return (int)$this->db->getLastId();
    }

    public function attachPix(int $chargeId, string $payload): void {
        $this->db->query("UPDATE `" . DB_PREFIX . "jow_charge` SET `pix_payload` = '" . $this->db->escape($payload) . "' WHERE `charge_id` = " . (int)$chargeId);
    }

    public function getCharge(int $chargeId): array {
        if ($chargeId <= 0) {
            return [];
        }

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "jow_charge` WHERE `charge_id` = " . (int)$chargeId . " LIMIT 1");

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    public function getCharges(int $limit = 50): array {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "jow_charge` ORDER BY `charge_id` DESC LIMIT " . (int)$limit)->rows;
    }

    /** The core migration is optional; legacy PIX/manual JOW must still render without it. */
    public function gatewaySchemaReady(): bool {
        return $this->hasTable('jow_gateway')
            && $this->hasTable('payment_transaction')
            && $this->hasTable('payment_gateway_event');
    }

    /**
     * Pedidos recentes que ainda podem receber um link de pagamento (ADR 0119).
     * Só o suficiente para a lista da tela; nenhum dado de pagamento aqui.
     *
     * @return list<array<string, mixed>>
     */
    public function getRecentOrdersForGatewayLink(int $limit = 10): array {
        $limit = max(1, min(50, $limit));

        $query = $this->db->query(
            "SELECT o.`order_id`, o.`email`, o.`total`, o.`date_added`, os.`name` AS `status_name`"
            . " FROM `" . DB_PREFIX . "order` o"
            . " LEFT JOIN `" . DB_PREFIX . "order_status` os ON os.`order_status_id` = o.`order_status_id`"
            . " WHERE o.`total` > 0 ORDER BY o.`order_id` DESC LIMIT " . $limit
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        return array_map(static fn($row): array => (array)$row, $rows);
    }

    /**
     * O mesmo "schema pronto" para o link de PEDIDO (ADR 0119). A checagem é a
     * das três tabelas do núcleo novo: sem elas o link falha com mensagem
     * clara em vez de gravar pela metade.
     */
    public function orderGatewaySchemaReady(): bool {
        return $this->gatewaySchemaReady();
    }

    /**
     * Dados do pedido que o link de pagamento precisa (ADR 0119): total, e-mail
     * do cliente e um título legível. O título é montado a partir dos itens —
     * um pedido sem descrição apareceria como "Pedido #12" genérico no extrato
     * do cliente.
     *
     * @return array<string, mixed>
     */
    public function getOrderForGatewayLink(int $orderId): array {
        if ($orderId <= 0) {
            return [];
        }

        $query = $this->db->query(
            "SELECT `order_id`, `email`, `total` FROM `" . DB_PREFIX . "order`"
            . " WHERE `order_id` = " . (int)$orderId . " LIMIT 1"
        );

        if (!($query instanceof \stdClass) || empty($query->row)) {
            return [];
        }

        $order = (array)$query->row;

        $items = $this->db->query(
            "SELECT `name` FROM `" . DB_PREFIX . "order_product`"
            . " WHERE `order_id` = " . (int)$orderId . " ORDER BY `order_product_id` ASC LIMIT 3"
        );

        $names = [];

        if ($items instanceof \stdClass && !empty($items->rows)) {
            foreach ((array)$items->rows as $row) {
                $name = trim((string)(((array)$row)['name'] ?? ''));

                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        $order['title'] = $names === []
            ? ('Pedido #' . (int)$orderId)
            : ('Pedido #' . (int)$orderId . ' — ' . implode(', ', $names));

        return $order;
    }

    /** Locate the single JOW platform-owned account for a provider, if configured. */
    public function getPlatformGatewayId(string $providerCode): int {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $providerCode) !== 1) {
            return 0;
        }

        $query = $this->db->query(
            "SELECT `gateway_id` FROM `" . DB_PREFIX . "jow_gateway`"
            . " WHERE `provider_code` = '" . $this->db->escape($providerCode) . "' AND `account_label` = 'JOW platform'"
            . " ORDER BY `gateway_id` ASC LIMIT 1"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['gateway_id'] : 0;
    }

    public function setChargeStatus(int $chargeId, string $status): void {
        $allowed = ['pending', 'paid', 'expired', 'refunded', 'canceled', 'partial', 'disputed'];
        if (!in_array($status, $allowed, true)) return;
        $paidAt = $status === 'paid' ? ", `paid_at` = NOW()" : "";
        $this->db->query("UPDATE `" . DB_PREFIX . "jow_charge` SET `status` = '" . $status . "'" . $paidAt . " WHERE `charge_id` = " . (int)$chargeId);
    }

    /** Relatório de recebimentos por status/método. */
    public function report(): array {
        $q = $this->db->query("SELECT `status`, `method`, COUNT(*) AS n, SUM(`amount`) AS total FROM `" . DB_PREFIX . "jow_charge` GROUP BY `status`, `method` ORDER BY `status`, `method`");
        return $q->rows;
    }

    // ── Chaves PIX ───────────────────────────────────────────────────────
    public function getPixKeys(): array {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "jow_pix_key` ORDER BY `key_id`")->rows;
    }

    public function addPixKey(string $type, string $key, string $holder): bool {
        if (!in_array($type, ['email', 'phone', 'cpf', 'cnpj', 'random'], true)) return false;
        if (trim($key) === '') return false;
        $this->db->query("INSERT INTO `" . DB_PREFIX . "jow_pix_key` (`type`,`key`,`holder`,`status`,`date_added`) VALUES ('"
            . $this->db->escape($type) . "','" . $this->db->escape(mb_substr(trim($key), 0, 128)) . "','"
            . $this->db->escape(mb_substr($holder, 0, 128)) . "',1, NOW())");
        return true;
    }

    public function deletePixKey(int $keyId): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "jow_pix_key` WHERE `key_id` = " . (int)$keyId);
    }

    public function defaultPixKey(): string {
        $q = $this->db->query("SELECT `key` FROM `" . DB_PREFIX . "jow_pix_key` WHERE `status` = 1 ORDER BY `key_id` LIMIT 1");
        return (string)($q->row['key'] ?? '');
    }

    // ── Config da loja (usada no BR Code) ────────────────────────────────
    public function saveStore(array $post): void {
        foreach (['jow_store_name', 'jow_city', 'jow_merchant_category'] as $k) {
            $v = trim((string)($post[$k] ?? ''));
            if ($v === '') continue;
            $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (`code`,`key`,`value`,`serialized`) VALUES ('jow','" . $k . "','"
                . $this->db->escape(mb_substr($v, 0, 64)) . "',0) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        }
    }
}
