<?php declare(strict_types=1);
/**
 * Defense — camada de defesa/incidentes do Dracaunos-Security (KZ-58).
 * Biblioteca compartilhada (system/library): usada pelo model admin
 * (security/dracaunos) e pela startup do catálogo (startup/dracaunos).
 *
 * Tipos de incidente: brute_force, ddos_suspect, probe, scanner_ua,
 * blocked_ip, unauthorized.
 */
namespace Reamur\System\Library\Security;

final class Defense {

    private const PROBE_PATHS = ['/.env', '/.git', '/wp-login', '/wp-admin', '/phpmyadmin', '/pma', '/admin.php', '/.aws', '/.ssh', '/config.php', '/setup', '/shell', '/c99', '/r57', '/vendor/phpunit', '/actuator'];
    private const SCANNER_UA  = ['sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab', 'dirbuster', 'gobuster', 'wpscan', 'acunetix'];

    private static bool $tableReady = false;

    public function __construct(private object $db, private object $config) {}

    private function ensureTable(): void {
        if (self::$tableReady) return;
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dracaunos_incident` (
            `incident_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `type`        VARCHAR(32)  NOT NULL,
            `severity`    VARCHAR(16)  NOT NULL DEFAULT 'medium',
            `ip`          VARCHAR(45)  NOT NULL DEFAULT '',
            `route`       VARCHAR(191) NOT NULL DEFAULT '',
            `detail`      VARCHAR(255) NOT NULL DEFAULT '',
            `status`      VARCHAR(16)  NOT NULL DEFAULT 'open',
            `date_added`  DATETIME     NOT NULL,
            PRIMARY KEY (`incident_id`),
            KEY `type` (`type`), KEY `ip` (`ip`), KEY `status` (`status`), KEY `date_added` (`date_added`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$tableReady = true;
    }

    public function logIncident(string $type, string $ip, string $route, string $detail, string $severity = 'medium'): void {
        $this->ensureTable();
        $dup = $this->db->query("SELECT `incident_id` FROM `" . DB_PREFIX . "dracaunos_incident` WHERE `type`='" . $this->db->escape($type) . "' AND `ip`='" . $this->db->escape($ip) . "' AND `status`='open' AND `date_added` > NOW() - INTERVAL 10 MINUTE LIMIT 1");
        if (!empty($dup->row)) return;
        $this->db->query("INSERT INTO `" . DB_PREFIX . "dracaunos_incident` (`type`,`severity`,`ip`,`route`,`detail`,`status`,`date_added`) VALUES ('"
            . $this->db->escape($type) . "','" . $this->db->escape($severity) . "','" . $this->db->escape(mb_substr($ip, 0, 45)) . "','"
            . $this->db->escape(mb_substr($route, 0, 191)) . "','" . $this->db->escape(mb_substr($detail, 0, 255)) . "','open', NOW())");
    }

    public function getIncidents(int $limit = 50): array {
        $this->ensureTable();
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "dracaunos_incident` ORDER BY `incident_id` DESC LIMIT " . (int)$limit)->rows;
    }

    public function getIncidentStats(): array {
        $this->ensureTable();
        $rows = $this->db->query("SELECT `severity`, COUNT(*) AS `n` FROM `" . DB_PREFIX . "dracaunos_incident` WHERE `status`='open' GROUP BY `severity`")->rows;
        $out = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0];
        foreach ($rows as $r) { $out[(string)$r['severity']] = (int)$r['n']; $out['total'] += (int)$r['n']; }
        return $out;
    }

    public function setIncidentStatus(int $id, string $status): void {
        $this->ensureTable();
        $this->db->query("UPDATE `" . DB_PREFIX . "dracaunos_incident` SET `status`='" . $this->db->escape($status) . "' WHERE `incident_id`=" . (int)$id);
    }

    public function getDefense(): array {
        $v = $this->config->get('dracaunos_defense');
        $d = is_array($v) ? $v : (json_decode((string)$v, true) ?: []);
        return array_merge([
            'auto_block_bruteforce' => 1, 'bruteforce_max' => 5, 'bruteforce_window_min' => 5,
            'ddos_max_req_min'      => 120, 'probe_detect' => 1, 'scanner_ua_detect' => 1,
        ], $d);
    }

    public function saveDefense(array $d): void {
        $clean = [
            'auto_block_bruteforce' => !empty($d['auto_block_bruteforce']) ? 1 : 0,
            'bruteforce_max'        => max(3, min(50, (int)($d['bruteforce_max'] ?? 5))),
            'bruteforce_window_min' => max(1, min(60, (int)($d['bruteforce_window_min'] ?? 5))),
            'ddos_max_req_min'      => max(30, min(2000, (int)($d['ddos_max_req_min'] ?? 120))),
            'probe_detect'          => !empty($d['probe_detect']) ? 1 : 0,
            'scanner_ua_detect'     => !empty($d['scanner_ua_detect']) ? 1 : 0,
        ];
        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (`code`,`key`,`value`,`serialized`) VALUES ('dracaunos','dracaunos_defense','"
            . $this->db->escape(json_encode($clean)) . "',1) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    }

    /** força bruta: N falhas de login na janela → incidente + auto-bloqueio opcional */
    public function detectBruteForce(string $ip, string $route = 'common/login'): bool {
        $d = $this->getDefense();
        $fails = $this->db->query("SELECT COUNT(*) AS `n` FROM `" . DB_PREFIX . "dracaunos_login_log` WHERE `ip`='" . $this->db->escape($ip) . "' AND `success`=0 AND `date_added` > NOW() - INTERVAL " . (int)$d['bruteforce_window_min'] . " MINUTE");
        $n = (int)($fails->row['n'] ?? 0);
        if ($n < (int)$d['bruteforce_max']) return false;
        $this->logIncident('brute_force', $ip, $route, $n . ' falhas de login em ' . (int)$d['bruteforce_window_min'] . ' min', 'high');
        if (!empty($d['auto_block_bruteforce'])) {
            foreach (['admin', 'catalog'] as $scope) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "dracaunos_ip_rule` (`ip`,`action`,`scope`,`note`,`status`,`date_added`) VALUES ('"
                    . $this->db->escape($ip) . "','deny','" . $scope . "','auto: brute force (" . $n . " falhas)',1, NOW())");
            }
            $this->logIncident('blocked_ip', $ip, $route, 'IP bloqueado automaticamente (brute force)', 'critical');
        }
        return true;
    }

    /** DDoS suspeito: mais de X requisições do mesmo IP em 60s (janela rate_limit) */
    public function detectDdos(string $ip, string $route): bool {
        $d = $this->getDefense();
        $limiter = new RateLimit($this->db);
        $limiter->allow('ddos', $ip, PHP_INT_MAX, 60);
        $row = $this->db->query("SELECT `attempts` FROM `" . DB_PREFIX . "rate_limit` WHERE `scope`='ddos' AND `ip`='" . $this->db->escape($ip) . "' LIMIT 1");
        $n = (int)($row->row['attempts'] ?? 0);
        if ($n >= (int)$d['ddos_max_req_min']) {
            $this->logIncident('ddos_suspect', $ip, $route, $n . ' req/min (limite ' . (int)$d['ddos_max_req_min'] . ')', 'critical');
            return true;
        }
        return false;
    }

    /** sonda em paths/serviços padrão */
    public function detectProbe(string $uri, string $ip, string $route): bool {
        $d = $this->getDefense();
        if (empty($d['probe_detect'])) return false;
        $path = strtolower((string)(parse_url($uri, PHP_URL_PATH) ?: ''));
        foreach (self::PROBE_PATHS as $p) {
            if (str_starts_with($path, $p)) {
                $this->logIncident('probe', $ip, $route, 'sonda em ' . $p, 'medium');
                return true;
            }
        }
        return false;
    }

    /** user-agent de scanner conhecido */
    public function detectScannerUa(string $ua, string $ip, string $route): bool {
        $d = $this->getDefense();
        if (empty($d['scanner_ua_detect'])) return false;
        $uaL = strtolower($ua);
        foreach (self::SCANNER_UA as $s) {
            if (str_contains($uaL, $s)) {
                $this->logIncident('scanner_ua', $ip, $route, 'UA de scanner: ' . $s, 'high');
                return true;
            }
        }
        return false;
    }

    /** varredura estática de arquivos PHP atrás de scripts maliciosos */
    public function scanFiles(string $baseDir): array {
        $patterns = [
            'eval+base64'    => '/eval\s*\(\s*(base64_decode|gzinflate|str_rot13)/i',
            'shell exec'     => '/\b(shell_exec|passthru|system|proc_open|popen)\s*\(/i',
            'preg /e'        => '/preg_replace\s*\(\s*[^,]*["\'][^"\']*e["\']/i',
            'webshell var'   => '/\$_(GET|POST|REQUEST|COOKIE)\s*\[[^\]]*\]\s*;?\s*(eval|assert|system|shell_exec)/i',
            'include remoto' => '/(include|require)(_once)?\s*\(?\s*\$_(GET|POST|REQUEST)/i',
        ];
        $skipDirs = ['/storage/', '/three/', '/vendor/'];
        $findings = [];
        if (!is_dir($baseDir)) return $findings;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->getExtension() !== 'php') continue;
            $p = $f->getPathname();
            foreach ($skipDirs as $sd) { if (str_contains($p, $sd)) continue 2; }
            $c = @file_get_contents($p);
            if ($c === false) continue;
            foreach ($patterns as $label => $rx) {
                if (preg_match($rx, $c, $m, PREG_OFFSET_CAPTURE)) {
                    $line = substr_count(substr($c, 0, $m[0][1]), "\n") + 1;
                    $findings[] = ['file' => $p, 'line' => $line, 'pattern' => $label];
                }
            }
        }
        return $findings;
    }
}
