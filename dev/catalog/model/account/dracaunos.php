<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @link      https://reamurcms.com
 *
 * account/dracaunos (CATALOG, KZ-38) — modelo de conta do cliente com as
 * tabelas do Dracaunos-Security: login log, verificação de IP (escopo catalog),
 * recuperação de senha/conta e verificação de e-mail via dracaunos_recovery.
 */
namespace Reamur\Catalog\Model\Account;

class Dracaunos extends \Reamur\System\Engine\Model {

    public function getCustomerByEmail(string $email): array {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer` WHERE `email` = '" . $this->db->escape($email) . "' LIMIT 1");
        return (array)($q->row ?? []);
    }

    public function getCustomer(int $id): array {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = " . (int)$id . " LIMIT 1");
        return (array)($q->row ?? []);
    }

    public function createCustomer(string $firstname, string $lastname, string $email, string $telephone, string $password): int {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "customer` (`customer_group_id`,`firstname`,`lastname`,`email`,`telephone`,`password`,`status`,`date_added`) VALUES (1,'"
            . $this->db->escape(mb_substr($firstname, 0, 32)) . "','" . $this->db->escape(mb_substr($lastname, 0, 32)) . "','"
            . $this->db->escape($email) . "','" . $this->db->escape(mb_substr($telephone, 0, 32)) . "','"
            . $this->db->escape(password_hash($password, PASSWORD_DEFAULT)) . "',0, NOW())");
        return (int)$this->db->getLastId();
    }

    public function updateCustomer(int $id, array $fields): void {
        $set = [];
        foreach (['firstname', 'lastname', 'telephone'] as $k) {
            if (isset($fields[$k])) $set[] = "`" . $k . "` = '" . $this->db->escape(mb_substr((string)$fields[$k], 0, 32)) . "'";
        }
        if ($set) $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET " . implode(',', $set) . " WHERE `customer_id` = " . (int)$id);
    }

    public function setPassword(int $id, string $password): void {
        $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `password` = '" . $this->db->escape(password_hash($password, PASSWORD_DEFAULT)) . "' WHERE `customer_id` = " . (int)$id);
    }

    public function verifyCustomerLogin(string $email, string $password): array {
        $c = $this->getCustomerByEmail($email);
        if (!$c || (int)$c['status'] !== 1 || !password_verify($password, (string)$c['password'])) return [];
        return $c;
    }

    // ── Dracaunos: IP, log, tokens ───────────────────────────────────────
    public function ipAllowed(string $ip): bool {
        $rows = $this->db->query("SELECT `ip`,`action` FROM `" . DB_PREFIX . "dracaunos_ip_rule` WHERE `status` = 1 AND `scope` = 'catalog'")->rows;
        $allow = false;
        foreach ($rows as $r) {
            if ($this->ipMatches($ip, (string)$r['ip'])) {
                if ($r['action'] === 'deny') return false;
                $allow = true;
            }
        }
        return true;
    }

    private function ipMatches(string $ip, string $rule): bool {
        if ($rule === $ip) return true;
        if (strpos($rule, '/') !== false) {
            list($sub, $mask) = explode('/', $rule);
            $m = ~((1 << (32 - (int)$mask)) - 1);
            return (ip2long($ip) & $m) === (ip2long($sub) & $m);
        }
        return false;
    }

    public function addLoginLog(int $customerId, string $email, string $ip, string $ua, bool $success): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "dracaunos_login_log` (`user_id`,`username`,`ip`,`user_agent`,`success`,`date_added`) VALUES ("
            . (int)$customerId . ",'" . $this->db->escape(mb_substr($email, 0, 32)) . "','" . $this->db->escape($ip) . "','"
            . $this->db->escape(mb_substr($ua, 0, 255)) . "'," . ($success ? 1 : 0) . ", NOW())");
    }

    public function addRecovery(int $customerId, string $email, string $type, string $ip): string {
        $token = bin2hex(random_bytes(16));
        $this->db->query("INSERT INTO `" . DB_PREFIX . "dracaunos_recovery` (`user_id`,`email`,`type`,`token`,`ip`,`status`,`date_added`) VALUES ("
            . (int)$customerId . ",'" . $this->db->escape($email) . "','" . $this->db->escape($type) . "','" . $token . "','"
            . $this->db->escape($ip) . "','pending', NOW())");
        return $token;
    }

    public function getRecoveryByToken(string $token): array {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dracaunos_recovery` WHERE `token` = '" . $this->db->escape($token) . "' AND `status` = 'pending' LIMIT 1");
        return (array)($q->row ?? []);
    }

    public function consumeToken(int $id, string $status): void {
        $this->db->query("UPDATE `" . DB_PREFIX . "dracaunos_recovery` SET `status` = '" . $this->db->escape($status) . "', `date_handled` = NOW() WHERE `recovery_id` = " . (int)$id);
    }

    public function activateCustomer(int $id): void {
        $this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `status` = 1 WHERE `customer_id` = " . (int)$id);
    }
}
