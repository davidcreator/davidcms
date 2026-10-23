<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Model admin de idiomas (F4-02, ADR 0025): leitura/edição das linhas de
 * rms_language. Sem add/delete: a existência do IDIOMA depende do diretório
 * ({app}/language/{code}/) — o filesystem é a fonte da verdade (Adendo F2-3
 * do ADR 0001). A tela governa metadados (name/locale/sort_order), status e
 * o idioma-padrão por aplicação (persistido em rms_setting via F4-01).
 */
namespace Reamur\Admin\Model\Localization;

class Language extends \Reamur\System\Engine\Model {

    /**
     * Grade filtrável F8-11. Sem argumentos continua completa, na ordem antiga.
     * @param array{search?: string, status?: string|int, start?: int, limit?: int} $options
     * @return array<int, array<string, mixed>>
     */
    public function getLanguages(array $options = []): array {
        $sql = "SELECT * FROM `" . $this->t('language') . "`" . $this->languageListWhere($options)
            . " ORDER BY `sort_order` ASC, `language_id` ASC";
        if (array_key_exists('limit', $options)) {
            $start = max(0, (int)($options['start'] ?? 0));
            $limit = min(100, max(1, (int)$options['limit']));
            $sql .= " LIMIT " . $start . ", " . $limit;
        }
        $query = $this->db->query($sql);
        return $query->rows;
    }

    /** @param array{search?: string, status?: string|int} $options */
    public function getTotalLanguages(array $options = []): int {
        $query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . $this->t('language') . "`" . $this->languageListWhere($options));
        return isset($query->row['total']) ? (int)$query->row['total'] : 0;
    }

    /** @param array{search?: string, status?: string|int} $options */
    private function languageListWhere(array $options): string {
        $where = [];
        $search = $options['search'] ?? '';
        if (is_string($search) && trim($search) !== '') {
            $value = $this->db->escape(trim($search));
            $where[] = "(`name` LIKE '%" . $value . "%' OR `code` LIKE '%" . $value . "%' OR `locale` LIKE '%" . $value . "%')";
        }
        $status = $options['status'] ?? 'all';
        if (in_array($status, ['0', '1', 0, 1], true)) { $where[] = "`status` = '" . (int)$status . "'"; }
        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getLanguage(int $language_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('language') . "` WHERE `language_id` = '" . (int)$language_id . "' LIMIT 1"
        );

        return $query->row ?: [];
    }

    /**
     * Persiste metadados + status. `code` é imutável — a chave é o identificador
     * do diretório de idioma no filesystem; trocá-la via tela quebraria o site.
     *
     * @param array{name: string, locale: string, sort_order: int, status: int} $data
     */
    public function editLanguage(int $language_id, array $data): void {
        $this->db->query(
            "UPDATE `" . $this->t('language') . "` SET
                `name`       = '" . $this->db->escape($data['name']) . "',
                `locale`     = '" . $this->db->escape($data['locale']) . "',
                `sort_order` = '" . (int)$data['sort_order'] . "',
                `status`     = '" . (int)$data['status'] . "'
             WHERE `language_id` = '" . (int)$language_id . "'"
        );
    }

    /**
     * Códigos habilitados nesta aplicação (admin): status=1 E diretório
     * presente em {app}/language/. Usado pelo gate do startup/language.
     *
     * @return array<int, string>
     */
    public function getEnabledCodes(): array {
        $codes = [];

        $query = $this->db->query(
            "SELECT `code` FROM `" . $this->t('language') . "`
             WHERE `status` = 1
             ORDER BY `sort_order` ASC, `language_id` ASC"
        );

        foreach ($query->rows as $row) {
            $code = (string)$row['code'];

            if (is_dir(DIR_APPLICATION . 'language/' . $code)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /** Helper local (ADR 0024 §1): tabela com prefixo, sem mágica de base */
    private function t(string $table): string {
        return DB_PREFIX . $table;
    }
}
