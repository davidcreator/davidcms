<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * design/seo_url — leitura da tabela rms_seo_url para o CATALOG (F2-17,
 * publicação, ADR 0017). Dois sentidos: resolver keyword da URL → query real
 * ('project_id=7'/'page_id=3') e produzir keyword amigável a partir da query.
 * Idioma da requisição vem do startup/language (config language_directory);
 * coalesce: idioma ativo → en-US (language_id=1).
 */
namespace Reamur\Catalog\Model\Design;

class Seo_url extends \Reamur\System\Engine\Model {

    /**
     * keyword → query interna (ex.: 'jwa' → 'project_id=7'), ativo→en-US.
     */
    public function getQueryByKeyword(string $keyword): ?string {
        if ($keyword === '' || mb_strlen($keyword) > 255) {
            return null;
        }

        $lang = $this->currentLanguageId();
        $o    = $this->escape($keyword);

        foreach ([$lang, 1] as $language_id) {
            $query = $this->db->query(
                "SELECT `key` FROM `" . DB_PREFIX . "seo_url`
                  WHERE `value` = '" . $o . "' AND `language_id` = '" . $language_id . "'
                  ORDER BY `sort_order` ASC, `seo_url_id` ASC LIMIT 1"
            );

            if ($query instanceof \stdClass && !empty($query->row['key'])) {
                return (string)$query->row['key'];
            }
        }

        return null;
    }

    /**
     * query interna → keyword amigável no idioma ativo → en-US
     * (ex.: 'project_id=7' → 'jwa').
     */
    public function getKeywordByQuery(string $query_key): ?string {
        if ($query_key === '' || mb_strlen($query_key) > 64) {
            return null;
        }

        $lang = $this->currentLanguageId();
        $o    = $this->escape($query_key);

        foreach ([$lang, 1] as $language_id) {
            $query = $this->db->query(
                "SELECT `value` FROM `" . DB_PREFIX . "seo_url`
                  WHERE `key` = '" . $o . "' AND `language_id` = '" . $language_id . "'
                  ORDER BY `sort_order` ASC, `seo_url_id` ASC LIMIT 1"
            );

            if ($query instanceof \stdClass && !empty($query->row['value'])) {
                return (string)$query->row['value'];
            }
        }

        return null;
    }

    /**
     * F4-07 (ADR 0031): entradas cruas para o sitemap — key (query alvo, ex.:
     * 'project_id=7'), value (keyword amigável) e language_id de TODAS as
     * linhas; o controller deduplica por alvo preferindo language_id=1 (en-us,
     * ADR 0001) e valida que a entidade segue ATIVA via models de conteúdo.
     *
     * @return array<int, array{key: string, value: string, language_id: int}>
     */
    public function getEntriesForSitemap(): array {
        $query = $this->db->query(
            "SELECT `key`, `value`, `language_id` FROM `" . DB_PREFIX . "seo_url`
             ORDER BY `seo_url_id` ASC"
        );

        $out = [];

        if ($query instanceof \stdClass && !empty($query->rows)) {
            foreach ((array)$query->rows as $row) {
                $row = (array)$row;

                $out[] = [
                    'key'         => (string)($row['key'] ?? ''),
                    'value'       => (string)($row['value'] ?? ''),
                    'language_id' => (int)($row['language_id'] ?? 0),
                ];
            }
        }

        return $out;
    }

    private function currentLanguageId(): int {
        static $cache = 0;

        if ($cache > 0) {
            return $cache;
        }

        $query = $this->db->query(
            "SELECT `language_id` FROM `" . DB_PREFIX . "language`
             WHERE `code` = '" . $this->escape((string)$this->config->get('language_directory', 'en-us')) . "' LIMIT 1"
        );

        $cache = ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['language_id'] : 1;

        return $cache;
    }

    private function escape(string $value): string {
        return $this->db->escape($value);
    }
}
