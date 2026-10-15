<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 */
namespace Reamur\{{APP}}\Model\{{GROUP}};

/**
 * TEMPLATE: Model padrão.
 * Placeholders: {{APP}} Admin|Catalog|Install · {{GROUP}} ex.: Cms · {{ENTITY}} ex.: Project · {{entity}} ex.: project
 * REGRA: colunas SEMPRE conforme docs/Dicionario-de-Dados.md — nunca inventar nomes.
 * REGRA: multi-idioma segue o padrão *_description (gravar um registro por language_id).
 */
class {{ENTITY}} extends \Reamur\System\Engine\Model {

    /** Um registro (com dado neutro + descrição do idioma atual) */
    public function get{{ENTITY}}(int ${{entity}}_id): array {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "{{entity}}` p
            LEFT JOIN `" . DB_PREFIX . "{{entity}}_description` pd
                ON (p.`{{entity}}_id` = pd.`{{entity}}_id`)
            WHERE p.`{{entity}}_id` = '" . (int)${{entity}}_id . "'
              AND pd.`language_id` = '" . (int)$this->config->get('config_language_id') . "'");

        return $query->row;
    }

    /** Lista paginada */
    public function get{{ENTITY}}List(array $data = []): array {
        $sql = "SELECT * FROM `" . DB_PREFIX . "{{entity}}` p
            LEFT JOIN `" . DB_PREFIX . "{{entity}}_description` pd
                ON (p.`{{entity}}_id` = pd.`{{entity}}_id` AND pd.`language_id` = '" . (int)$this->config->get('config_language_id') . "')
            ORDER BY p.`sort_order` ASC";

        if (isset($data['start'], $data['limit'])) {
            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        return $this->db->query($sql)->rows;
    }

    /** Total (para pagination) */
    public function getTotal{{ENTITY}}(): int {
        $query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "{{entity}}`");

        return (int)$query->row['total'];
    }

    /** Cria — retorna o ID gerado */
    public function add{{ENTITY}}(array $data): int {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "{{entity}}`
            SET `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
                `status`     = '" . (int)($data['status'] ?? 0) . "',
                `date_added` = NOW(),
                `date_modified` = NOW()");

        ${{entity}}_id = (int)$this->db->getLastId();

        // Descrições por idioma
        foreach (($data['{{entity}}_description'] ?? []) as $language_id => $value) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "{{entity}}_description`
                SET `{{entity}}_id` = '" . ${{entity}}_id . "',
                    `language_id`  = '" . (int)$language_id . "',
                    `name`         = '" . $this->db->escape((string)$value['name']) . "',
                    `description`  = '" . $this->db->escape((string)$value['description']) . "'");
        }

        return ${{entity}}_id;
    }

    /** Edita — remove e recria descrições (padrão simples e idempotente) */
    public function edit{{ENTITY}}(int ${{entity}}_id, array $data): void {
        $this->db->query("UPDATE `" . DB_PREFIX . "{{entity}}`
            SET `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
                `status`     = '" . (int)($data['status'] ?? 0) . "',
                `date_modified` = NOW()
            WHERE `{{entity}}_id` = '" . (int)${{entity}}_id . "'");

        $this->db->query("DELETE FROM `" . DB_PREFIX . "{{entity}}_description` WHERE `{{entity}}_id` = '" . (int)${{entity}}_id . "'");

        foreach (($data['{{entity}}_description'] ?? []) as $language_id => $value) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "{{entity}}_description`
                SET `{{entity}}_id` = '" . ${{entity}}_id . "',
                    `language_id`  = '" . (int)$language_id . "',
                    `name`         = '" . $this->db->escape((string)$value['name']) . "',
                    `description`  = '" . $this->db->escape((string)$value['description']) . "'");
        }
    }

    /** Exclui (cascata manual nas filhas) */
    public function delete{{ENTITY}}(int ${{entity}}_id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "{{entity}}` WHERE `{{entity}}_id` = '" . (int)${{entity}}_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "{{entity}}_description` WHERE `{{entity}}_id` = '" . (int)${{entity}}_id . "'");
    }
}
