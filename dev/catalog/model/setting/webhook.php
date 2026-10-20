<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/webhook (CATALOG) — leitura de endpoints ativos para despacho de
 * eventos do lado público (F5-01, ADR 0033) + gravação das tentativas de
 * entrega em rms_webhook_log. SQL vive EXCLUSIVAMENTE aqui (regra 01 §1):
 * o controller despacha via Library\Webhook\Dispatcher (transporte puro) e
 * persiste os resultados por addLog().
 *
 * Match de trigger: padrões armazenados no estilo wildcard (`order_add`,
 * `order_*`, `*_add` — validados no CRUD F2-16/ADR 0016) casam o evento
 * concreto em SQL, com LIKE: `*`→`%` e `?`→`_`. Um endpoint NUNCA casa
 * evento de outro nome sem wildcard explícito.
 */
namespace Reamur\Catalog\Model\Setting;

class Webhook extends \Reamur\System\Engine\Model {

    /**
     * Endpoints ativos cujo trigger casa o evento (wildcard-aware).
     *
     * @return array<int, array{webhook_id:int,url:string,secret:string}>
     */
    public function getHooksForEvent(string $event): array {
        $query = $this->db->query(
            "SELECT `webhook_id`, `url`, `secret`
               FROM `" . $this->t('webhook') . "`
              WHERE `status` = '1'
                AND '" . $this->db->escape($event) . "' LIKE
                    REPLACE(REPLACE(`trigger`, '*', '%'), '?', '_')
              ORDER BY `webhook_id` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * 1 linha por tentativa de entrega (delivery idêntico nos retries do
     * mesmo evento×endpoint — KEY (webhook_id, delivery) permite ao
     * receptor e ao admin correlacionar).
     */
    public function addLog(int $webhook_id, string $delivery, string $payload, int $http_code, float $duration, int $attempt): void {
        $this->db->query(
            "INSERT INTO `" . $this->t('webhook_log') . "` SET
               `webhook_id` = '" . $webhook_id . "',
               `delivery`   = '" . $this->db->escape($delivery) . "',
               `payload`    = '" . $this->db->escape($payload) . "',
               `http_code`  = '" . $http_code . "',
               `duration`   = '" . $duration . "',
               `attempt`    = '" . $attempt . "',
               `date_added` = NOW()"
        );
    }

    /** Tabela com prefixo (mesmo helper dos demais models) */
    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
