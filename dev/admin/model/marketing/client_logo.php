<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * marketing/client_logo — model do CRUD de logos de clientes (F2-12, ADR 0012).
 * Tabela ÚNICA rms_client_logo (sem descrições/multi-idioma, sem SEO, sem
 * datas — exatamente como o schema instalado pelo wizard: zero DDL novo).
 * Vocabulário DB idêntico ao cms/page (stdClass ->row/->rows, helper t()).
 */
namespace Reamur\Admin\Model\Marketing;

class Client_logo extends \Reamur\System\Engine\Model {

    /**
     * Grade paginável. Sem limite, mantém o comportamento histórico de retornar todas as linhas.
     *
     * @param array<string, mixed> $filters Filtros normalizados pelo controller
     * @return array<int, array<string, mixed>>
     */
    public function getClientLogos(array $filters = [], int $start = 0, int $limit = 0): array {
        $where = $this->clientLogoFilterSql($filters);
        $limitSql = $limit > 0 ? ' LIMIT ' . max(0, $start) . ', ' . max(1, $limit) : '';

        $query = $this->db->query(
            "SELECT `client_logo_id`, `name`, `image`, `url`, `status`, `sort_order`
               FROM `" . $this->t('client_logo') . "`"
            . $where . "
           ORDER BY `sort_order` ASC, `name` ASC"
            . $limitSql
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** Total para paginação, usando os mesmos filtros da grade. */
    public function getTotalClientLogos(array $filters = []): int {
        $query = $this->db->query(
            "SELECT COUNT(*) AS `total` FROM `" . $this->t('client_logo') . "`"
            . $this->clientLogoFilterSql($filters)
        );

        return ($query instanceof \stdClass && isset($query->row['total'])) ? (int)$query->row['total'] : 0;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function clientLogoFilterSql(array $filters): string {
        $where = [];
        $name = is_scalar($filters['name'] ?? null) ? trim((string)$filters['name']) : '';

        if ($name !== '') {
            $where[] = "`name` LIKE '%" . $this->db->escape($name) . "%'";
        }

        $status = is_scalar($filters['status'] ?? null) ? (string)$filters['status'] : 'all';

        if (in_array($status, ['0', '1'], true)) {
            $where[] = "`status` = '" . $status . "'";
        }

        return $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    /**
     * @return array<string, mixed> Linha (vazio = inexistente)
     */
    public function getClientLogo(int $client_logo_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('client_logo') . "` WHERE `client_logo_id` = '" . $client_logo_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function addClientLogo(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('client_logo') . "` SET
                `name`       = '" . $this->db->escape((string)($data['name'] ?? '')) . "',
                `image`      = '" . $this->db->escape((string)($data['image'] ?? '')) . "',
                `url`        = '" . $this->db->escape((string)($data['url'] ?? '')) . "',
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "'"
        );

        return (int)$this->db->getLastId();
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function editClientLogo(int $client_logo_id, array $data): void {
        $this->db->query(
            "UPDATE `" . $this->t('client_logo') . "` SET
                `name`       = '" . $this->db->escape((string)($data['name'] ?? '')) . "',
                `image`      = '" . $this->db->escape((string)($data['image'] ?? '')) . "',
                `url`        = '" . $this->db->escape((string)($data['url'] ?? '')) . "',
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "'
             WHERE `client_logo_id` = '" . $client_logo_id . "'"
        );
    }

    /** Uma tabela só; o arquivo físico o controller remove */
    public function deleteClientLogo(int $client_logo_id): void {
        $this->db->query("DELETE FROM `" . $this->t('client_logo') . "` WHERE `client_logo_id` = '" . $client_logo_id . "'");
    }

    /** Tabela com prefixo (mesmo helper dos demais models F2) */
    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
