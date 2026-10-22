<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * setting/webhook — model do CRUD de endpoints de webhook + log de entregas
 * (F2-16, ADR 0016). Tabelas rms_webhook + rms_webhook_log INSTALADAS pelo
 * wizard — zero DDL novo (9º módulo consecutivo). Grupo setting/ (decisão:
 * Dicionário-de-Dados §2 oferecia marketing/ ou setting/ — webhook é
 * configuração técnica, não peça de marketing). Sem multi-idioma, sem SEO,
 * sem upload. Vocabulário DB idêntico aos demais CRUDs F2.
 */
namespace Reamur\Admin\Model\Setting;

class Webhook extends \Reamur\System\Engine\Model {


    /**
     * Endpoints filtrados e paginados, agrupados por trigger.
     * @param array<string, mixed> $filter_data
     * @return array<int, array<string, mixed>>
     */
    public function getWebhooks(array $filter_data = []): array {
        $where = $this->webhookFilterWhere($filter_data);
        $sql = "SELECT w.`webhook_id`, w.`trigger`, w.`url`, CASE WHEN w.`secret` <> '' THEN 1 ELSE 0 END AS `secret_is_set`, w.`description`, w.`status`, w.`date_added`,
                    (SELECT COUNT(*) FROM `" . $this->t('webhook_log') . "` l WHERE l.`webhook_id` = w.`webhook_id`) AS `log_count`
               FROM `" . $this->t('webhook') . "` w";
        if ($where !== '') { $sql .= ' WHERE ' . $where; }
        $start = max(0, (int)($filter_data['start'] ?? 0));
        $limit = min(100, max(1, (int)($filter_data['limit'] ?? 20)));
        $sql .= " ORDER BY w.`trigger` ASC, w.`webhook_id` ASC LIMIT " . $start . "," . $limit;
        $query = $this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @param array<string, mixed> $filter_data */
    public function getTotalWebhooks(array $filter_data = []): int {
        $sql = "SELECT COUNT(*) AS `total` FROM `" . $this->t('webhook') . "` w";
        $where = $this->webhookFilterWhere($filter_data);
        if ($where !== '') { $sql .= ' WHERE ' . $where; }
        $query = $this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['total'] : 0;
    }

    /** @param array<string, mixed> $filter_data */
    private function webhookFilterWhere(array $filter_data): string {
        $where = [];
        foreach (['trigger' => 'w.`trigger`', 'url' => 'w.`url`'] as $key => $column) {
            $value = trim((string)($filter_data[$key] ?? ''));
            if ($value !== '') { $where[] = $column . " LIKE '%" . $this->db->escape($value) . "%'"; }
        }
        $status = (string)($filter_data['status'] ?? 'all');
        if (in_array($status, ['0', '1'], true)) { $where[] = "w.`status` = '" . $status . "'"; }
        return implode(' AND ', $where);
    }

    /**
     * @return array<string, mixed> Linha principal (vazio = inexistente)
     */
    public function getWebhook(int $webhook_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('webhook') . "` WHERE `webhook_id` = '" . $webhook_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /** UNIQUE(trigger,url) do schema — checar ANTES do INSERT/UPDATE */
    public function existsTriggerUrl(string $trigger, string $url, int $except_webhook_id = 0): bool {
        $query = $this->db->query(
            "SELECT 1 FROM `" . $this->t('webhook') . "`
              WHERE `trigger` = '" . $this->db->escape($trigger) . "'
                AND `url`     = '" . $this->db->escape($url) . "'
                AND `webhook_id` <> '" . $except_webhook_id . "'
              LIMIT 1"
        );

        return $query instanceof \stdClass && !empty($query->row);
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function addWebhook(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('webhook') . "` SET
                `trigger`     = '" . $this->db->escape((string)$data['trigger']) . "',
                `url`         = '" . $this->db->escape((string)$data['url']) . "',
                `secret`      = '" . $this->db->escape((string)$data['secret']) . "',
                `description` = '" . $this->db->escape((string)$data['description']) . "',
                `status`      = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `date_added`  = NOW()"
        );

        return (int)$this->db->getLastId();
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function editWebhook(int $webhook_id, array $data): void {
        $this->db->query(
            "UPDATE `" . $this->t('webhook') . "` SET
                `trigger`     = '" . $this->db->escape((string)$data['trigger']) . "',
                `url`         = '" . $this->db->escape((string)$data['url']) . "',
                `secret`      = '" . $this->db->escape((string)$data['secret']) . "',
                `description` = '" . $this->db->escape((string)$data['description']) . "',
                `status`      = '" . (int)(!empty($data['status']) ? 1 : 0) . "'
             WHERE `webhook_id` = '" . $webhook_id . "'"
        );
    }

    /** Cascata manual (sem FK): endpoint + TODAS as suas entregas */
    public function deleteWebhook(int $webhook_id): void {
        $this->db->query("DELETE FROM `" . $this->t('webhook_log') . "` WHERE `webhook_id` = '" . $webhook_id . "'");
        $this->db->query("DELETE FROM `" . $this->t('webhook') . "` WHERE `webhook_id` = '" . $webhook_id . "'");
    }

    /**
     * Entregas paginadas — todas ou filtradas por endpoint, delivery, trigger e HTTP.
     * @param array<string, mixed> $filter_data
     * @return array<int, array<string, mixed>>
     */
    public function getLogs(?int $webhook_id = null, int $start = 0, int $limit = 20, array $filter_data = []): array {
        $sql = "SELECT l.`webhook_log_id`, l.`webhook_id`, l.`delivery`, l.`http_code`, l.`duration`, l.`attempt`, l.`date_added`,
                       w.`trigger`, w.`url`
                  FROM `" . $this->t('webhook_log') . "` l
             LEFT JOIN `" . $this->t('webhook') . "` w ON w.`webhook_id` = l.`webhook_id`";
        $where = $this->logFilterWhere($webhook_id, $filter_data);
        if ($where !== '') { $sql .= ' WHERE ' . $where; }
        $start = max(0, $start);
        $limit = min(100, max(1, $limit));
        $sql .= " ORDER BY l.`date_added` DESC, l.`webhook_log_id` DESC LIMIT " . $start . "," . $limit;
        $query = $this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @param array<string, mixed> $filter_data */
    public function getTotalLogs(?int $webhook_id = null, array $filter_data = []): int {
        $sql = "SELECT COUNT(*) AS `total`
                  FROM `" . $this->t('webhook_log') . "` l
             LEFT JOIN `" . $this->t('webhook') . "` w ON w.`webhook_id` = l.`webhook_id`";
        $where = $this->logFilterWhere($webhook_id, $filter_data);
        if ($where !== '') { $sql .= ' WHERE ' . $where; }
        $query = $this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['total'] : 0;
    }

    /** @param array<string, mixed> $filter_data */
    private function logFilterWhere(?int $webhook_id, array $filter_data): string {
        $where = [];
        if ($webhook_id !== null && $webhook_id > 0) { $where[] = 'l.`webhook_id` = ' . $webhook_id; }
        foreach (['delivery' => 'l.`delivery`', 'trigger' => 'w.`trigger`'] as $key => $column) {
            $value = trim((string)($filter_data[$key] ?? ''));
            if ($value !== '') { $where[] = $column . " LIKE '%" . $this->db->escape($value) . "%'"; }
        }
        $httpCode = (int)($filter_data['http_code'] ?? 0);
        if ($httpCode > 0) { $where[] = 'l.`http_code` = ' . $httpCode; }
        return implode(' AND ', $where);
    }

    /**
     * @return array<string, mixed> Entrega completa (com payload MEDIIUMTEXT)
     */
    public function getLog(int $webhook_log_id): array {
        $query = $this->db->query(
            "SELECT l.*, w.`trigger`, w.`url`
               FROM `" . $this->t('webhook_log') . "` l
          LEFT JOIN `" . $this->t('webhook') . "` w ON w.`webhook_id` = l.`webhook_id`
              WHERE l.`webhook_log_id` = '" . $webhook_log_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /** Tabela com prefixo (mesmo helper dos demais models F2) */
    private function t(string $table): string {
        return DB_PREFIX . $table;
    }

    // ── F5-01 (ADR 0033) — despacho de eventos do lado admin ──────────────

    /**
     * Endpoints ativos cujo trigger casa o evento, wildcard-aware
     * (`order_*` casa `order_edit`; `*`→`%`, `?`→`_`). Espelho do
     * método homônimo do catalog/setting/webhook.
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

    /** 1 linha por tentativa de entrega (delivery estável nos retries) */
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
}
