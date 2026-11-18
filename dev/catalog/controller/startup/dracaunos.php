<?php declare(strict_types=1);
/**
 * Pré-ação startup/dracaunos do CATÁLOGO (KZ-58): defesa da face pública.
 *   1) Regras de IP escopo 'catalog' valem em todas as rotas públicas.
 *   2) DDoS suspeito, sonda em path padrão e UA de scanner viram incidente
 *      em dracaunos_incident (acompanhados no painel Dracaunos-Security).
 */
namespace Reamur\Catalog\Controller\Startup;

class Dracaunos extends \Reamur\System\Engine\Controller {

    public function index(): void {
        $defense = new \Reamur\System\Library\Security\Defense($this->db, $this->config);

        $ip    = (string)$this->request->ip();
        $ua    = (string)($this->request->server['HTTP_USER_AGENT'] ?? '');
        $route = (string)$this->config->get('_route', '');
        $uri   = (string)($this->request->server['REQUEST_URI'] ?? '');

        // defesa antes de qualquer coisa
        $defense->detectDdos($ip, $route);
        $defense->detectProbe($uri, $ip, $route);
        $defense->detectScannerUa($ua, $ip, $route);

        // regras de IP do catálogo (bloqueio público)
        $rules = $this->db->query("SELECT `ip`,`action` FROM `" . DB_PREFIX . "dracaunos_ip_rule` WHERE `status`=1 AND `scope`='catalog' ORDER BY `rule_id` DESC");
        foreach ($rules->rows as $r) {
            if ($this->ipMatch($ip, (string)$r['ip'])) {
                if ($r['action'] === 'deny') {
                    $defense->logIncident('blocked_ip', $ip, $route, 'bloqueado por regra de IP (catalog)', 'high');
                    $this->response->addHeader('HTTP/1.1 403 Forbidden');
                    $this->response->setOutput('Forbidden');
                    return;
                }
            }
        }
    }

    /** mesmo matcher CIDR (IPv4/IPv6) do Dracaunos */
    private function ipMatch(string $ip, string $rule): bool {
        if ($rule === $ip) return true;
        if (strpos($rule, '/') !== false) {
            [$sub, $mask] = explode('/', $rule, 2);
            $a = @inet_pton($ip); $b = @inet_pton($sub);
            if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
            $bits = strlen($a) * 8;
            $mask = max(0, min($bits, (int)$mask));
            $full = intdiv($mask, 8); $rem = $mask % 8;
            if ($full && substr($a, 0, $full) !== substr($b, 0, $full)) return false;
            if ($rem) {
                $m = chr((0xFF << (8 - $rem)) & 0xFF);
                return (ord($a[$full]) & ord($m)) === (ord($b[$full]) & ord($m));
            }
            return true;
        }
        return false;
    }
}
