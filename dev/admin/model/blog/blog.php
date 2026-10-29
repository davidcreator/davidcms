<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Model blog/blog — blog interno (F7-01, ADR 0042): posts cronológicos com
 * destaque, multi-idioma (blog_post_description) e keyword no seo_url global
 * (key 'blog_id=N' — mesma unicidade cross-entidade das páginas/projetos).
 * O ordering público (F7-02) é por date_published DESC, NÃO sort_order.
 */
namespace Reamur\Admin\Model\Blog;

class Blog extends \Reamur\System\Engine\Model {

    /**
     * Lista para a grade (título preferindo o idioma ativo, coalesce EN;
     * ordem editorial: publicação desc — novidades primeiro).
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Lista posts com filtros server-side (F8-02, ADR 0046).
     *
     * @param array<string,mixed> $filters title (LIKE), status (''|0|1),
     *                                     start (OFFSET), limit (LIMIT).
     * @return array<int, array<string,mixed>>
     */
    public function getPosts(array $filters = []): array {
        $lang = $this->currentLanguageId();

        $where = '';
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all') {
            $where .= " AND p.`status` = '" . (int)$filters['status'] . "'";
        }
        if (!empty($filters['title'])) {
            $where .= " AND (pd.`title` LIKE '%" . $this->db->escape((string)$filters['title']) . "%'"
                    . " OR pd_en.`title` LIKE '%" . $this->db->escape((string)$filters['title']) . "%')";
        }

        $lim = '';
        if (isset($filters['limit']) && (int)$filters['limit'] > 0) {
            $lim = ' LIMIT ' . (int)($filters['start'] ?? 0) . ', ' . (int)$filters['limit'];
        }

        $query = $this->db->query(
            "SELECT p.`post_id`, p.`image`, p.`status`, p.`date_published`, p.`date_modified`,
                    COALESCE(NULLIF(pd.`title`, ''), pd_en.`title`, '') AS `title`
             FROM `" . $this->t('blog_post') . "` p
             LEFT JOIN `" . $this->t('blog_post_description') . "` pd
                 ON pd.`post_id` = p.`post_id` AND pd.`language_id` = '" . $lang . "'
             LEFT JOIN `" . $this->t('blog_post_description') . "` pd_en
                 ON pd_en.`post_id` = p.`post_id` AND pd_en.`language_id` = 1
             WHERE 1=1" . $where . "
             ORDER BY p.`date_published` DESC, p.`post_id` DESC" . $lim
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * Total de posts sob os mesmos filtros (F8-02) — alimenta badge + paginação.
     */
    public function getTotalPosts(array $filters = []): int {
        $lang = $this->currentLanguageId();

        $where = '';
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all') {
            $where .= " AND p.`status` = '" . (int)$filters['status'] . "'";
        }
        if (!empty($filters['title'])) {
            $where .= " AND (pd.`title` LIKE '%" . $this->db->escape((string)$filters['title']) . "%'"
                    . " OR pd_en.`title` LIKE '%" . $this->db->escape((string)$filters['title']) . "%')";
        }

        $query = $this->db->query(
            "SELECT COUNT(*) AS `total`
             FROM `" . $this->t('blog_post') . "` p
             LEFT JOIN `" . $this->t('blog_post_description') . "` pd
                 ON pd.`post_id` = p.`post_id` AND pd.`language_id` = '" . $lang . "'
             LEFT JOIN `" . $this->t('blog_post_description') . "` pd_en
                 ON pd_en.`post_id` = p.`post_id` AND pd_en.`language_id` = 1
             WHERE 1=1" . $where
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (int)$query->row['total'] : 0;
    }

    /**
     * @return array<string, mixed> Linha principal (vazio = inexistente)
     */
    public function getPost(int $post_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('blog_post') . "` WHERE `post_id` = '" . $post_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @return array<int, array<string, mixed>> indexado por language_id
     */
    public function getPostDescriptions(int $post_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('blog_post_description') . "` WHERE `post_id` = '" . $post_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $out[(int)$row['language_id']] = (array)$row;
        }

        return $out;
    }

    /**
     * Detecta a tabela aditiva F9-12 sem consultar a tabela quando a migration está ausente.
     */
    public function blogBlockContentTableExists(): bool {
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

    /** @return array<int, array<string, mixed>> rows sidecar indexed by language_id */
    public function getPostBlockContents(int $post_id): array {
        if ($post_id < 1 || !$this->blogBlockContentTableExists()) return [];
        try {
            $query = $this->db->query(
                "SELECT `language_id`, `format_version`, `content_json`, `source_hash`, `legacy_html_backup`
                   FROM `" . $this->t('blog_post_content') . "` WHERE `post_id` = '" . $post_id . "'"
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException('blog_block_read_failed', 0, $e);
        }
        if (!$query instanceof \stdClass) throw new \RuntimeException('blog_block_read_failed');
        $out = [];
        if (!empty($query->rows)) {
            foreach ((array)$query->rows as $row) $out[(int)$row['language_id']] = (array)$row;
        }
        return $out;
    }

    /**
     * Persiste post/descrições/SEO e documentos sidecar numa única transação.
     * `blocks` contém somente JSON já validado pelo Controller. A fonte HTML da
     * primeira conversão vem do banco (não do cliente) e nunca é sobrescrita no UPSERT.
     *
     * @param array<int, string> $blocks JSON por language_id
     */
    public function savePostWithBlocks(int $post_id, array $data, array $blocks): int {
        if (!$this->blogBlockContentTableExists()) throw new \RuntimeException('blog_block_schema_unavailable');
        if ($this->db->query('START TRANSACTION') !== true) throw new \RuntimeException('blog_save_transaction_start_failed');
        try {
            $oldDescriptions = $post_id > 0 ? $this->getPostDescriptions($post_id) : [];
            if ($post_id > 0) {
                $currentBlocks = $this->getPostBlockContents($post_id);
                $blockChanged = false;
                foreach ($blocks as $languageId => $json) if ((string)($currentBlocks[(int)$languageId]['content_json'] ?? '') !== (string)$json) { $blockChanged = true; break; }
                if ($blockChanged || $this->postContentChanged($post_id, $data)) $this->captureBdreRevisions($post_id);
                $this->editPost($post_id, $data, false);
            } else {
                $post_id = $this->addPost($data);
                if ($post_id < 1) throw new \RuntimeException('blog_post_insert_failed');
            }

            $validator = new \Reamur\System\Library\Blog\BlockDocument();
            foreach ($blocks as $language_id => $json) {
                $language_id = (int)$language_id;
                if ($language_id < 1 || !is_string($json)) throw new \RuntimeException('blog_block_payload_invalid');
                $decoded = $validator->deserializeDocument($json);
                if (!$decoded['valid']) throw new \RuntimeException('blog_block_document_invalid');
                $version = (int)$decoded['document']['format_version'];
                $legacy = (string)($oldDescriptions[$language_id]['description'] ?? '');
                $backupSql = $legacy === '' ? 'NULL' : "'" . $this->db->escape($legacy) . "'";
                $sourceHash = $legacy === '' ? '' : hash('sha256', $legacy);
                $this->db->query(
                    "INSERT INTO `" . $this->t('blog_post_content') . "` SET
                        `post_id` = '" . $post_id . "',
                        `language_id` = '" . $language_id . "',
                        `format_version` = '" . $version . "',
                        `content_json` = '" . $this->db->escape($json) . "',
                        `source_hash` = '" . $this->db->escape($sourceHash) . "',
                        `legacy_html_backup` = " . $backupSql . ",
                        `date_added` = NOW(), `date_modified` = NOW()
                     ON DUPLICATE KEY UPDATE
                        `format_version` = '" . $version . "',
                        `content_json` = '" . $this->db->escape($json) . "',
                        `date_modified` = NOW()"
                );
            }
            if ($this->db->query('COMMIT') !== true) throw new \RuntimeException('blog_save_transaction_commit_failed');
            return $post_id;
        } catch (\Throwable $e) {
            try { $this->db->query('ROLLBACK'); } catch (\Throwable $rollbackError) { /* preserve original exception */ }
            throw $e;
        }
    }

    /**
     * keyword (seo_url.value) por idioma — key = 'blog_id=N'.
     *
     * @return array<int, string> indexado por language_id
     */
    public function getPostKeywords(int $post_id): array {
        $query = $this->db->query(
            "SELECT `language_id`, `value` FROM `" . $this->t('seo_url') . "` WHERE `key` = 'blog_id=" . $post_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $out[(int)$row['language_id']] = (string)$row['value'];
        }

        return $out;
    }

    /**
     * keyword já em uso naquele idioma por QUALQUER outra entidade
     * (seo_url é global: blog x página x projeto x ferramenta).
     */
    public function keywordTaken(string $keyword, int $language_id, int $exclude_post_id): bool {
        $query = $this->db->query(
            "SELECT `key` FROM `" . $this->t('seo_url') . "`
             WHERE `value` = '" . $this->db->escape($keyword) . "' AND `language_id` = '" . $language_id . "'
               AND `key` <> 'blog_id=" . $exclude_post_id . "' LIMIT 1"
        );

        return $query instanceof \stdClass && !empty($query->row);
    }

    /**
     * Idiomas cadastrados (abas do form).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLanguages(): array {
        $query = $this->db->query(
            "SELECT `language_id`, `name`, `code` FROM `" . $this->t('language') . "` ORDER BY `sort_order` ASC"
        );

        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function addPost(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('blog_post') . "` SET
                `image`          = '" . $this->db->escape((string)$data['image']) . "',
                `status`         = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `date_published` = '" . $this->db->escape((string)$data['date_published']) . "',
                `date_added`     = NOW(),
                `date_modified`  = NOW()"
        );

        $post_id = (int)$this->db->getLastId();

        $this->syncDescriptions($post_id, (array)$data['descriptions']);

        return $post_id;
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function editPost(int $post_id, array $data, bool $captureRevisions = true): void {
        if ($captureRevisions && $this->postContentChanged($post_id, $data)) $this->captureBdreRevisions($post_id);
        $this->db->query(
            "UPDATE `" . $this->t('blog_post') . "` SET
                `image`          = '" . $this->db->escape((string)$data['image']) . "',
                `status`         = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `date_published` = '" . $this->db->escape((string)$data['date_published']) . "',
                `date_modified`  = NOW()
             WHERE `post_id` = '" . $post_id . "'"
        );

        $this->db->query("DELETE FROM `" . $this->t('blog_post_description') . "` WHERE `post_id` = '" . $post_id . "'");

        $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'blog_id=" . $post_id . "'");

        $this->syncDescriptions($post_id, (array)$data['descriptions']);
    }

    public function deletePost(int $post_id): void {
        $hasBlockContent = $this->blogBlockContentTableExists();
        if ($this->db->query('START TRANSACTION') !== true) throw new \RuntimeException('blog_delete_transaction_start_failed');
        try {
            if ($hasBlockContent) {
                $this->db->query("DELETE FROM `" . $this->t('blog_post_content') . "` WHERE `post_id` = '" . $post_id . "'");
            }
            $this->db->query("DELETE FROM `" . $this->t('blog_post') . "` WHERE `post_id` = '" . $post_id . "'");
            $this->db->query("DELETE FROM `" . $this->t('blog_post_description') . "` WHERE `post_id` = '" . $post_id . "'");
            $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'blog_id=" . $post_id . "'");
            if ($this->db->query('COMMIT') !== true) throw new \RuntimeException('blog_delete_transaction_commit_failed');
        } catch (\Throwable $e) {
            try { $this->db->query('ROLLBACK'); } catch (\Throwable $rollbackError) { /* preserve original exception */ }
            throw $e;
        }
        if ($this->bdreRevisionTableExists()) $this->db->query("DELETE FROM `" . $this->t('bdre_revision') . "` WHERE `entity_type`='blog' AND `entity_id`='" . $post_id . "'");
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * Descrições (uma por idioma) + keywords SEO.
     *
     * @param array<int, array<string, string>> $descriptions
     */
    private function postContentChanged(int $postId, array $data): bool {
        $post=$this->getPost($postId);
        if (!$post || (int)($post['status'] ?? 0)!==(int)($data['status'] ?? 0) || (string)($post['image'] ?? '')!==(string)($data['image'] ?? '') || (string)($post['date_published'] ?? '')!==(string)($data['date_published'] ?? '')) return true;
        $current=$this->getPostDescriptions($postId); $keywords=$this->getPostKeywords($postId);
        foreach ((array)($data['descriptions'] ?? []) as $languageId=>$next) {
            $old=(array)($current[(int)$languageId] ?? []); $next=(array)$next;
            foreach (['title','description','meta_title','meta_description'] as $field) if ((string)($old[$field] ?? '')!==(string)($next[$field] ?? '')) return true;
            if ((string)($keywords[(int)$languageId] ?? '')!==(string)($next['keyword'] ?? '')) return true;
        }
        return false;
    }

    /** Snapshot each persisted language before the administrator explicitly saves an edit. */
    private function captureBdreRevisions(int $post_id): void {
        if (!$this->bdreRevisionTableExists()) return;
        $post = $this->getPost($post_id);
        if (!$post) return;
        $descriptions = $this->getPostDescriptions($post_id);
        $keywords = $this->getPostKeywords($post_id);
        $blocks = $this->getPostBlockContents($post_id);
        foreach ($descriptions as $languageId => $description) {
            $description['keyword'] = $keywords[$languageId] ?? '';
            $snapshot = ['entity' => ['status' => (int)($post['status'] ?? 0), 'image' => (string)($post['image'] ?? ''), 'date_published' => (string)($post['date_published'] ?? '')], 'description' => $description];
            if (isset($blocks[$languageId])) $snapshot['block_content'] = $blocks[$languageId];
            elseif ((string)($description['description'] ?? '') !== '') { $converted=(new \Reamur\System\Library\Blog\BlockDocument())->convertLegacy((string)$description['description']); if (($converted['status'] ?? '') === 'converted' && is_array($converted['document'] ?? null)) $snapshot['block_document']=$converted['document']; }
            $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($json) || strlen($json) > 4194304) continue;
            $hash = hash('sha256', $json); $table = $this->t('bdre_revision');
            $latest = $this->db->query("SELECT `content_hash` FROM `" . $table . "` WHERE `entity_type`='blog' AND `entity_id`='" . $post_id . "' AND `language_id`='" . (int)$languageId . "' ORDER BY `revision_id` DESC LIMIT 1");
            if ($latest instanceof \stdClass && !empty($latest->row) && hash_equals((string)$latest->row['content_hash'], $hash)) continue;
            $this->db->query("INSERT INTO `" . $table . "` SET `entity_type`='blog', `entity_id`='" . $post_id . "', `language_id`='" . (int)$languageId . "', `content_hash`='" . $hash . "', `snapshot_json`='" . $this->db->escape($json) . "', `date_added`=NOW()");
            $this->db->query("DELETE FROM `" . $table . "` WHERE `entity_type`='blog' AND `entity_id`='" . $post_id . "' AND `language_id`='" . (int)$languageId . "' AND `revision_id` NOT IN (SELECT `revision_id` FROM (SELECT `revision_id` FROM `" . $table . "` WHERE `entity_type`='blog' AND `entity_id`='" . $post_id . "' AND `language_id`='" . (int)$languageId . "' ORDER BY `revision_id` DESC LIMIT 20) AS retained)");
        }
    }

    /** @return array<int, array<string, mixed>> newest first; snapshot is decoded server-side */
    public function getPostRevisions(int $entityId, int $languageId): array {
        if ($entityId < 1 || $languageId < 1 || !$this->bdreRevisionTableExists()) return [];
        $q = $this->db->query("SELECT `revision_id`, `snapshot_json`, `date_added` FROM `" . $this->t('bdre_revision') . "` WHERE `entity_type`='blog' AND `entity_id`='" . $entityId . "' AND `language_id`='" . $languageId . "' ORDER BY `revision_id` DESC LIMIT 20");
        $out = [];
        if ($q instanceof \stdClass && !empty($q->rows)) foreach ((array)$q->rows as $row) {
            $snapshot = json_decode((string)$row['snapshot_json'], true);
            if (!is_array($snapshot)) continue;
            $out[] = ['revision_id' => (int)$row['revision_id'], 'date_added' => (string)$row['date_added'], 'snapshot' => $snapshot];
        }
        return $out;
    }

    private function bdreRevisionTableExists(): bool {
        static $exists = null;
        if ($exists !== null) return $exists;
        try { $q = $this->db->query("SELECT 1 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '" . $this->db->escape($this->t('bdre_revision')) . "' LIMIT 1"); $exists = $q instanceof \stdClass && !empty($q->row); }
        catch (\Throwable $e) { $exists = false; }
        return $exists;
    }

    private function syncDescriptions(int $post_id, array $descriptions): void {
        foreach ($descriptions as $language_id => $d) {
            $d = (array)$d;

            $this->db->query(
                "INSERT INTO `" . $this->t('blog_post_description') . "` SET
                    `post_id`          = '" . $post_id . "',
                    `language_id`      = '" . (int)$language_id . "',
                    `title`            = '" . $this->db->escape((string)$d['title']) . "',
                    `description`      = '" . $this->db->escape((string)$d['description']) . "',
                    `meta_title`       = '" . $this->db->escape((string)$d['meta_title']) . "',
                    `meta_description` = '" . $this->db->escape((string)$d['meta_description']) . "'"
            );

            $keyword = trim((string)($d['keyword'] ?? ''));

            if ($keyword !== '') {
                $this->db->query(
                    "INSERT INTO `" . $this->t('seo_url') . "` SET
                        `key`         = 'blog_id=" . $post_id . "',
                        `value`       = '" . $this->db->escape($keyword) . "',
                        `language_id` = '" . (int)$language_id . "',
                        `sort_order`  = 0"
                );
            }
        }
    }

    /** Idioma ativo do admin (language_directory → rms_language) — padrão dos models */
    private function currentLanguageId(): int {
        static $cache = 0;

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

    /** Nome real da tabela com o prefixo do banco (padrão Model base) */
    private function t(string $base): string {
        return DB_PREFIX . $base;
    }
}
