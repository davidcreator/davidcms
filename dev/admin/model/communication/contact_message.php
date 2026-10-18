<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * communication/contact_message — model da caixa de entrada do formulário
 * de contato (F2-15, ADR 0015). Tabela rms_contact_message INSTALADA pelo
 * wizard — zero DDL novo (8º módulo consecutivo). CRUD assimétrico: o admin
 * NÃO cria/edita conteúdo (mensagens nascem no front); opera status
 * (0 novo / 1 lido / 2 respondido — Dicionário-de-Dados §9) e exclusão.
 * Vocabulário DB idêntico aos demais CRUDs F2 (stdClass ->row/->rows).
 */
namespace Reamur\Admin\Model\Communication;

class Contact_message extends \Reamur\System\Engine\Model {

    /** Status canônicos do Dicionário §9 */
    public const STATUS_NEW     = 0;
    public const STATUS_READ    = 1;
    public const STATUS_REPLIED = 2;

    /** Whitelist única — controller e model compartilham daqui */
    public const STATUSES = [self::STATUS_NEW, self::STATUS_READ, self::STATUS_REPLIED];

    /**
     * Caixa de entrada, mais recentes primeiro. Filtro opcional por status
     * (null = todas).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMessages(?int $filter_status = null): array {
        $sql = "SELECT `contact_message_id`, `name`, `email`, `telephone`, `subject`, `status`, `date_added`
                  FROM `" . $this->t('contact_message') . "`";

        if ($filter_status !== null) {
            $sql .= " WHERE `status` = '" . $filter_status . "'";
        }

        $sql .= " ORDER BY `date_added` DESC, `contact_message_id` DESC";

        $query = $this->db->query($sql);

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * @return array<string, mixed> Linha completa (vazio = inexistente)
     */
    public function getMessage(int $contact_message_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('contact_message') . "`
             WHERE `contact_message_id` = '" . $contact_message_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /** Atualiza status respeitando a whitelist (defesa em profundidade). */
    public function updateStatus(int $contact_message_id, int $status): bool {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }

        $this->db->query(
            "UPDATE `" . $this->t('contact_message') . "`
                SET `status` = '" . $status . "'
              WHERE `contact_message_id` = '" . $contact_message_id . "'"
        );

        return true;
    }

    /** Tabela única, sem cascata, sem arquivo físico */
    public function deleteMessage(int $contact_message_id): void {
        $this->db->query(
            "DELETE FROM `" . $this->t('contact_message') . "` WHERE `contact_message_id` = '" . $contact_message_id . "'"
        );
    }

    /**
     * Contadores para as abas de filtro da grade.
     *
     * @return array{all: int, new: int, read: int, replied: int}
     */
    public function getCounts(): array {
        $counts = ['all' => 0, 'new' => 0, 'read' => 0, 'replied' => 0];

        $query = $this->db->query(
            "SELECT `status`, COUNT(*) AS `total` FROM `" . $this->t('contact_message') . "` GROUP BY `status`"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        foreach ($rows as $row) {
            $row  = (array)$row;
            $n    = (int)$row['total'];
            $key  = match ((int)$row['status']) {
                self::STATUS_READ    => 'read',
                self::STATUS_REPLIED => 'replied',
                default              => 'new',
            };

            $counts[$key] += $n;
            $counts['all'] += $n;
        }

        return $counts;
    }

    /** Tabela com prefixo (mesmo helper dos demais models F2) */
    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
