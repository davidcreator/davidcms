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

    /** Teto do log exibido (idempotência/auditoria recente; sem paginação ainda) */
    private const LOG_LIMIT = 200;

    /**
     * Todos os endpoints, agrupados por trigger.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWebhooks(): array {
        $query = $this->db->query(
            "SELECT w.`webhook_id`, w.`trigger`, w.`url`, w.`secret`, w.`description`, w.`status`, w.`date_added`,
                    (SELECT COUNT(*) FROM `" . $this->t('webhook_log') . "` l WHERE l.`webhook_id` = w.`webhook_id`) AS `log_count`
               FROM `" . $this->t('webhook') . "` w
           ORDER BY w.`trigger` ASC, w.`webhook_id` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
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
     * Entregas recentes — todas, ou filtradas por endpoint.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLogs(?int $webhook_id = null): array {
        $sql = "SELECT l.`webhook_log_id`, l.`webhook_id`, l.`delivery`, l.`http_code`, l.`duration`, l.`attempt`, l.`date_added`,
                       w.`trigger`, w.`url`
                  FROM `" . $this->t('webhook_log') . "` l
             LEFT JOIN `" . $this->t('webhook') . "` w ON w.`webhook_id` = l.`webhook_id`";

        if ($webhook_id !== null && $webhook_id > 0) {
            $sql .= " WHERE l.`webhook_id` = '" . $webhook_id . "'";
        }

        $sql .= " ORDER BY l.`date_added` DESC, l.`webhook_log_id` DESC LIMIT " . self::LOG_LIMIT;

        $query = $this->db->query($sql);

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
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
}
