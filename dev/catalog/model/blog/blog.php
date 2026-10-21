<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * blog/blog (CATALOG MODEL) — leitura pública do blog interno (F7-02,
 * ADR 0043): apenas PUBLICADOS (status=1), cronológico (date_published
 * DESC — coberto pelo índice status_published), coalesce idioma ativo →
 * en-US, multilíngue exatamente como catalog/page.
 */
namespace Reamur\Catalog\Model\Blog;

class Blog extends \Reamur\System\Engine\Model {

    /** Limite padrão da lista pública (paginação: decisão acompanhada na ADR 0043) */
    private const DEFAULT_LIMIT = 50;

    /**
     * @return array<int, array<string, mixed>> Posts publicados, mais novos primeiro
     */
    public function getPosts(int $limit = 0): array {
        if (!$this->tablesExist()) {
            return []; // degradação silenciosa (ADR 0023/0043): módulo ainda não instalado neste banco
        }

        $limit = $limit > 0 ? min($limit, 100) : self::DEFAULT_LIMIT;
        $lang  = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT p.`post_id`, p.`image`, p.`date_published`,
                    COALESCE(NULLIF(pd.`title`, ''), pd_en.`title`, '')           AS `title`,
                    COALESCE(NULLIF(pd.`description`, ''), pd_en.`description`, '') AS `description`
               FROM `" . $this->t('blog_post') . "` p
          LEFT JOIN `" . $this->t('blog_post_description') . "` pd
                 ON pd.`post_id` = p.`post_id` AND pd.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('blog_post_description') . "` pd_en
                 ON pd_en.`post_id` = p.`post_id` AND pd_en.`language_id` = 1
              WHERE p.`status` = '1' AND p.`date_published` <= NOW()
           ORDER BY p.`date_published` DESC, p.`post_id` DESC
              LIMIT " . $limit
        );

        $out = [];

        if ($query instanceof \stdClass && !empty($query->rows)) {
            foreach ((array)$query->rows as $row) {
                $out[] = (array)$row;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed> Post publicado completo (vazio = inexistente/inativo)
     */
    public function getPost(int $post_id): array {
        if (!$this->tablesExist()) {
            return []; // idem lista: tabela ausente = blog não instalado → 404 fora (controller)
        }

        $lang  = $this->currentLanguageId();

        $query = $this->db->query(
            "SELECT p.`post_id`, p.`image`, p.`date_published`, p.`date_modified`,
                    COALESCE(NULLIF(pd.`title`, ''), pd_en.`title`, '')                 AS `title`,
                    COALESCE(NULLIF(pd.`description`, ''), pd_en.`description`, '')     AS `description`,
                    COALESCE(NULLIF(pd.`meta_title`, ''), pd_en.`meta_title`, '')       AS `meta_title`,
                    COALESCE(NULLIF(pd.`meta_description`, ''), pd_en.`meta_description`, '') AS `meta_description`
               FROM `" . $this->t('blog_post') . "` p
          LEFT JOIN `" . $this->t('blog_post_description') . "` pd
                 ON pd.`post_id` = p.`post_id` AND pd.`language_id` = '" . $lang . "'
          LEFT JOIN `" . $this->t('blog_post_description') . "` pd_en
                 ON pd_en.`post_id` = p.`post_id` AND pd_en.`language_id` = 1
              WHERE p.`post_id` = '" . $post_id . "' AND p.`status` = '1' AND p.`date_published` <= NOW()"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * Tabelas do blog presentes? Cacheada por request.
     * Leituras públicas (home inclusa) NÃO podem quebrar quando o upgrade
     * do módulo ainda não correu — degradação silenciosa (ADR 0023/0043).
     */
    private function tablesExist(): bool {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($this->t('blog_post')) . "'");

        $cache = ($query instanceof \stdClass && !empty($query->num_rows)) ? (int)$query->num_rows === 1 : false;

        return $cache;
    }

    /** language_id do idioma ATIVO no catálogo (startup/language resolve antes) */
    private function currentLanguageId(): int {        static $cache = 0;

        if ($cache > 0) {
            return $cache;
        }

        $query = $this->db->query(
            "SELECT `language_id` FROM `" . $this->t('language') . "`
             WHERE `code` = '" . $this->db->escape((string)$this->config->get('language_directory', 'en-us')) . "' LIMIT 1"
        );

        $cache = ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['language_id'] : 1;

        return $cache;
    }

    /** Nome real da tabela com o prefixo do banco */
    private function t(string $base): string {
        return DB_PREFIX . $base;
    }
}
