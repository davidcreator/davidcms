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
                    COALESCE(NULLIF(pd.`meta_description`, ''), pd_en.`meta_description`, '') AS `meta_description`,
                    '" . $lang . "' AS `active_language_id`,
                    CASE WHEN NULLIF(pd.`description`, '') IS NOT NULL THEN pd.`language_id` ELSE pd_en.`language_id` END AS `content_language_id`
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
     * Documento estruturado do idioma já escolhido pelo SELECT do post.
     * A ausência da migration/tabela/linha preserva exatamente o caminho legado.
     * @return array{table_available:bool,row_found:bool,query_error:bool,format_version:int,content_json:string}
     */
    public function getPostBlockContent(int $post_id, int $language_id = 0): array {
        $empty = ['table_available' => false, 'row_found' => false, 'query_error' => false, 'format_version' => 0, 'content_json' => ''];
        if ($post_id < 1) return $empty;
        try {
            if (!$this->blockContentTableExists()) return $empty;
        } catch (\Throwable $e) {
            // A metadata error is not proof of absence; the public caller must fail closed.
            return ['table_available' => true, 'row_found' => false, 'query_error' => true, 'format_version' => 0, 'content_json' => ''];
        }
        $language_id = $language_id > 0 ? $language_id : $this->currentLanguageId();
        try {
            $query = $this->db->query(
                "SELECT `format_version`, `content_json`
                   FROM `" . $this->t('blog_post_content') . "`
                  WHERE `post_id` = '" . $post_id . "' AND `language_id` = '" . $language_id . "'
                  LIMIT 1"
            );
        } catch (\Throwable $e) {
            return ['table_available' => true, 'row_found' => false, 'query_error' => true, 'format_version' => 0, 'content_json' => ''];
        }
        if (!$query instanceof \stdClass) {
            return ['table_available' => true, 'row_found' => false, 'query_error' => true, 'format_version' => 0, 'content_json' => ''];
        }
        if (!empty($query->row)) {
            return ['table_available' => true, 'row_found' => true, 'query_error' => false, 'format_version' => (int)($query->row['format_version'] ?? 0), 'content_json' => (string)($query->row['content_json'] ?? '')];
        }
        return ['table_available' => true, 'row_found' => false, 'query_error' => false, 'format_version' => 0, 'content_json' => ''];
    }

    /** Detect the additive F9-12 sidecar; a successful empty result alone means absent. */
    private function blockContentTableExists(): bool {
        static $cache = null;
        if ($cache !== null) return $cache;
        try {
            $table = $this->db->escape($this->t('blog_post_content'));
            $query = $this->db->query(
                "SELECT `TABLE_NAME` FROM `information_schema`.`TABLES`
                 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $table . "' LIMIT 1"
            );
            if (!$query instanceof \stdClass) throw new \RuntimeException('blog_block_schema_detection_failed');
            $cache = !empty($query->num_rows);
        } catch (\Throwable $e) {
            throw new \RuntimeException('blog_block_schema_detection_failed', 0, $e);
        }
        return $cache;
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
