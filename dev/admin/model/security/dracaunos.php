<?php
/**
 * Dracaunos-Security — model interno (KZ-34).
 * Senhas (politica), log de logins, 2FA TOTP (RFC 6238), recuperacao de conta, regras de IP.
 * Sem rede, sem libs externas: TOTP puro em hash_hmac/base32.
 */
namespace Reamur\Admin\Model\Security;

class Dracaunos extends \Reamur\System\Engine\Model {

    // ── TOTP (RFC 6238, SHA1, 30s, 6 dígitos) ────────────────────────────
    private static function base32Encode(string $raw): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = ''; $bits = 0; $val = 0;
        for ($i = 0, $n = strlen($raw); $i < $n; $i++) {
            $val = ($val << 8) | ord($raw[$i]); $bits += 8;
            while ($bits >= 5) { $out .= $alphabet[($val >> ($bits - 5)) & 31]; $bits -= 5; }
        }
        if ($bits > 0) $out .= $alphabet[($val << (5 - $bits)) & 31];
        return $out;
    }

    public static function base32Decode(string $b32): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        $out = ''; $bits = 0; $val = 0;
        for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
            $val = ($val << 5) | strpos($alphabet, $b32[$i]); $bits += 5;
            if ($bits >= 8) { $out .= chr(($val >> ($bits - 8)) & 255); $bits -= 8; }
        }
        return $out;
    }

    public function generateSecret(int $bytes = 20): string {
        return self::base32Encode(random_bytes($bytes));
    }

    public function totp(string $secret, ?int $time = null): string {
        $time = $time ?? time();
        $counter = (int)floor($time / 30);
        $bin = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $bin, self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $code = ((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset+1]) & 255) << 16)
              | ((ord($hash[$offset+2]) & 255) << 8) | (ord($hash[$offset+3]) & 255);
        return str_pad((string)($code % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** aceita janela de ±1 período (30s) para relógio do celular levemente atrasado */
    public function verifyTotp(string $secret, string $code, ?int $time = null): bool {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) return false;
        $time = $time ?? time();
        for ($w = -1; $w <= 1; $w++) {
            if (hash_equals($this->totp($secret, $time + $w * 30), $code)) return true;
        }
        return false;
    }

    public function otpauthUri(string $secret, string $account, string $issuer = 'ReamurCMS'): string {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret
             . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    // ── 2FA ──────────────────────────────────────────────────────────────
    public function get2fa(int $userId): array {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dracaunos_2fa` WHERE `user_id` = " . (int)$userId);
        return $q->row;
    }
    public function save2fa(int $userId, string $secret, bool $enabled): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "dracaunos_2fa` (`user_id`,`secret`,`enabled`,`date_added`) VALUES ("
            . (int)$userId . ",'" . $this->db->escape($secret) . "'," . ($enabled ? 1 : 0) . ", NOW())"
            . " ON DUPLICATE KEY UPDATE `secret` = VALUES(`secret`), `enabled` = VALUES(`enabled`)");
    }

    // ── Log de logins ────────────────────────────────────────────────────
    public function addLoginLog(int $userId, string $username, string $ip, string $ua, bool $success): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "dracaunos_login_log` (`user_id`,`username`,`ip`,`user_agent`,`success`,`date_added`) VALUES ("
            . (int)$userId . ",'" . $this->db->escape($username) . "','" . $this->db->escape($ip) . "','"
            . $this->db->escape(mb_substr($ua, 0, 255)) . "'," . ($success ? 1 : 0) . ", NOW())");
    }
    public function getLoginLogs(int $limit = 50): array {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "dracaunos_login_log` ORDER BY `log_id` DESC LIMIT " . (int)$limit)->rows;
    }

    // ── Recuperação / verificação de conta ───────────────────────────────
    public function addRecovery(int $userId, string $email, string $type, string $ip): string {
        $token = bin2hex(random_bytes(16));
        $this->db->query("INSERT INTO `" . DB_PREFIX . "dracaunos_recovery` (`user_id`,`email`,`type`,`token`,`ip`,`status`,`date_added`) VALUES ("
            . (int)$userId . ",'" . $this->db->escape($email) . "','" . $this->db->escape($type) . "','" . $token . "','"
            . $this->db->escape($ip) . "','pending', NOW())");
        return $token;
    }
    public function getRecovery(string $status = 'pending', int $limit = 50): array {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "dracaunos_recovery` WHERE `status` = '" . $this->db->escape($status)
            . "' ORDER BY `recovery_id` DESC LIMIT " . (int)$limit)->rows;
    }
    public function setRecoveryStatus(int $id, string $status): void {
        $this->db->query("UPDATE `" . DB_PREFIX . "dracaunos_recovery` SET `status` = '" . $this->db->escape($status)
            . "', `date_handled` = NOW() WHERE `recovery_id` = " . (int)$id);
    }

    // ── Regras de IP ─────────────────────────────────────────────────────
    public function getIpRules(): array {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "dracaunos_ip_rule` ORDER BY `rule_id` DESC")->rows;
    }
    public function addIpRule(string $ip, string $action, string $scope, string $note): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "dracaunos_ip_rule` (`ip`,`action`,`scope`,`note`,`status`,`date_added`) VALUES ('"
            . $this->db->escape($ip) . "','" . $this->db->escape($action) . "','" . $this->db->escape($scope) . "','"
            . $this->db->escape(mb_substr($note, 0, 128)) . "',1, NOW())");
    }
    public function deleteIpRule(int $id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dracaunos_ip_rule` WHERE `rule_id` = " . (int)$id);
    }
    /** verifica IP contra as regras (deny vence; depois allow; padrão: permite) */
    public function ipAllowed(string $ip, string $scope = 'admin'): bool {
        $rows = $this->getIpRules();
        $deny = false; $allow = false;
        foreach ($rows as $r) {
            if ((int)$r['status'] !== 1 || $r['scope'] !== $scope) continue;
            if ($this->ipMatches($ip, $r['ip'])) {
                if ($r['action'] === 'deny') return false;
                $allow = true;
            }
        }
        return $allow || !$deny;
    }
    private function ipMatches(string $ip, string $rule): bool {
        if ($rule === $ip) return true;
        if (strpos($rule, '/') !== false) { // CIDR (IPv4)
            list($sub, $mask) = explode('/', $rule);
            return (ip2long($ip) & ~((1 << (32 - (int)$mask)) - 1)) === (ip2long($sub) & ~((1 << (32 - (int)$mask)) - 1));
        }
        return false;
    }

    // ── Política de senhas ───────────────────────────────────────────────
    public function getPasswordPolicy(): array {
        $v = $this->config->get('dracaunos_password_policy');
        $p = is_array($v) ? $v : (json_decode((string)$v, true) ?: []);
        return array_merge(['min_length'=>10,'require_upper'=>1,'require_digit'=>1,'require_symbol'=>1,'max_age_days'=>90], $p);
    }
    public function passwordMeetsPolicy(string $password): bool {
        $p = $this->getPasswordPolicy();
        if (strlen($password) < (int)$p['min_length']) return false;
        if (!empty($p['require_upper']) && !preg_match('/[A-Z]/', $password)) return false;
        if (!empty($p['require_digit']) && !preg_match('/[0-9]/', $password)) return false;
        if (!empty($p['require_symbol']) && !preg_match('/[^A-Za-z0-9]/', $password)) return false;
        return true;
    }
    public function savePasswordPolicy(array $policy): void {
        $clean = [
            'min_length'     => max(8, min(64, (int)($policy['min_length'] ?? 10))),
            'require_upper'  => !empty($policy['require_upper']) ? 1 : 0,
            'require_digit'  => !empty($policy['require_digit']) ? 1 : 0,
            'require_symbol' => !empty($policy['require_symbol']) ? 1 : 0,
            'max_age_days'   => max(0, min(365, (int)($policy['max_age_days'] ?? 90))),
        ];
        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (`code`,`key`,`value`,`serialized`) VALUES ('dracaunos','dracaunos_password_policy','"
            . $this->db->escape(json_encode($clean)) . "',1) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    }

    // ── Rota do admin ────────────────────────────────────────────────────
    public function getAdminRoute(): string {
        $r = (string)$this->config->get('dracaunos_admin_route');
        return preg_match('/^[a-z0-9][a-z0-9-]{0,31}$/', $r) ? $r : 'admin';
    }
    public function saveAdminRoute(string $route): bool {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,31}$/', $route)) return false;
        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (`code`,`key`,`value`,`serialized`) VALUES ('dracaunos','dracaunos_admin_route','"
            . $this->db->escape($route) . "',0) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        return true;
    }

    /** usuário por username (para o fluxo de login com 2FA) */
    public function getUserByUsername(string $username): array {
        $q = $this->db->query("SELECT `user_id`,`username`,`password`,`status` FROM `" . DB_PREFIX . "user` WHERE `username` = '" . $this->db->escape($username) . "' LIMIT 1");
        return (array)($q->row ?? []);
    }

    // ── Usuários (gestão de login / perfil) ──────────────────────────────
    public function getUsers(int $limit = 50): array {
        return $this->db->query("SELECT `user_id`,`username`,`email`,`firstname`,`lastname`,`status` FROM `" . DB_PREFIX . "user` ORDER BY `user_id` LIMIT " . (int)$limit)->rows;
    }

    // ── Expiração de senha (KZ-39, Fase B) ───────────────────────────────
    /** true se a política define max_age_days > 0 e a senha do usuário venceu (NULL = venceu: fail-closed). */
    public function passwordExpired(int $userId): bool {
        $p = $this->getPasswordPolicy();
        $maxAge = (int)($p['max_age_days'] ?? 0);
        if ($maxAge <= 0) return false;

        $q = $this->db->query("SELECT `password_changed_at` FROM `" . DB_PREFIX . "user` WHERE `user_id` = " . (int)$userId . " LIMIT 1");
        $changedAt = (string)($q->row['password_changed_at'] ?? '');
        if ($changedAt === '') return true; // sem carimbo = venceu (fail-closed)

        $limit = date('Y-m-d H:i:s', time() - $maxAge * 86400);
        return $changedAt < $limit;
    }
    // ── Auditoria do admin (KZ-39, Fase B) ────────────────────────────────
    public function addAudit(int $userId, string $route, string $method, string $ip, string $ua): void {
        $u = $this->db->query("SELECT `username` FROM `" . DB_PREFIX . "user` WHERE `user_id` = " . (int)$userId . " LIMIT 1");
        $username = $this->db->escape((string)($u->row['username'] ?? ''));
        $this->db->query("INSERT INTO `" . DB_PREFIX . "dracaunos_audit` (`user_id`,`username`,`route`,`method`,`ip`,`user_agent`,`date_added`) VALUES ("
            . (int)$userId . ",'" . $username . "','" . $this->db->escape(mb_substr($route, 0, 128)) . "','"
            . $this->db->escape(mb_substr($method, 0, 10)) . "','" . $this->db->escape($ip) . "','"
            . $this->db->escape(mb_substr($ua, 0, 255)) . "', NOW())");
        // retenção: poda registros mais antigos que dracaunos_audit_days
        $days = max(1, (int)($this->config->get('dracaunos_audit_days') ?: 30));
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dracaunos_audit` WHERE `date_added` < NOW() - INTERVAL " . $days . " DAY");
    }
    public function getAuditLogs(int $limit = 50): array {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "dracaunos_audit` ORDER BY `audit_id` DESC LIMIT " . (int)$limit)->rows;
    }
}
