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

    public function getCharges(int $limit = 50): array {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "jow_charge` ORDER BY `charge_id` DESC LIMIT " . (int)$limit)->rows;
    }

    public function setChargeStatus(int $chargeId, string $status): void {
        $allowed = ['pending', 'paid', 'expired', 'refunded', 'canceled'];
        if (!in_array($status, $allowed, true)) return;
        $paidAt = $status === 'paid' ? ", `paid_at` = NOW()" : "";
        $this->db->query("UPDATE `" . DB_PREFIX . "jow_charge` SET `status` = '" . $status . "'" . $paidAt . " WHERE `charge_id` = " . (int)$chargeId);
    }

    /** Relatório de recebimentos por status/método. */
    public function report(): array {
        $q = $this->db->query("SELECT `status`, `method`, COUNT(*) AS n, SUM(`amount`) AS total FROM `" . DB_PREFIX . "jow_charge` GROUP BY `status`, `method` ORDER BY `status`, `method)");
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
