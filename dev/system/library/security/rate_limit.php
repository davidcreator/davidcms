<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * RateLimit (ADR 0018): janela deslizante simples por (scope, ip) persistida
 * em `rms_rate_limit`. Usado nos POSTs públicos (contato/orçamento) e no
 * login do admin. fail-open NÃO é opção: sem tabela, sem DB, exceção sobe.
 *
 * Convenção de uso no controller:
 *   $limiter = new RateLimit($this->db);
 *   if (!$limiter->allow('login', $ip, 20, 900)) { ... 429/erro ... }
 */
namespace Reamur\System\Library\Security;

final class RateLimit {

    public function __construct(private object $db) {}

    /**
     * Registra uma tentativa e responde se ela ainda cabe na janela.
     * Janela expirada zera o contador (window_start = NOW()).
     */
    public function allow(string $scope, string $ip, int $maxAttempts, int $windowSeconds): bool {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "rate_limit` (`scope`, `ip`, `attempts`, `window_start`) VALUES ("
            . "'" . $this->db->escape($scope) . "',"
            . "'" . $this->db->escape($ip) . "',"
            . "1, NOW())"
            . " ON DUPLICATE KEY UPDATE"
            . " `attempts` = IF(TIMESTAMPDIFF(SECOND, `window_start`, NOW()) >= " . (int)$windowSeconds . ", 1, `attempts` + 1),"
            . " `window_start` = IF(TIMESTAMPDIFF(SECOND, `window_start`, NOW()) >= " . (int)$windowSeconds . ", NOW(), `window_start`)"
        );

        $row = $this->db->query(
            "SELECT `attempts` FROM `" . DB_PREFIX . "rate_limit` WHERE"
            . " `scope` = '" . $this->db->escape($scope) . "' AND `ip` = '" . $this->db->escape($ip) . "'"
        );

        $attempts = (int)($row->row['attempts'] ?? 0);

        // Limpeza oportunista de janelas mortas (1% das chamadas — sem cron)
        if (random_int(1, 100) === 1) {
            $this->db->query(
                "DELETE FROM `" . DB_PREFIX . "rate_limit` WHERE `window_start` < NOW() - INTERVAL 1 DAY"
            );
        }

        return $attempts <= $maxAttempts;
    }
}
