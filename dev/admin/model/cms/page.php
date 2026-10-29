<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * Model cms/page — CRUD de páginas de conteúdo/information (F2-10, ADR 0010).
 * page + page_description (multi-idioma) + seo_url(key=page_id=N).
 * Zero DDL: tabelas já instaladas pelo wizard (Dicionario-de-Dados §cms/).
 */
namespace Reamur\Admin\Model\Cms;

class Page extends \Reamur\System\Engine\Model {

    /**
     * Lista a grade; sem argumentos mantém a listagem completa e a ordem antiga.
     * @param array{title?: string, status?: string|int, start?: int, limit?: int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getPages(array $filters = []): array {
        $systemKeySelect = $this->hasSystemKeyColumn() ? "p.`system_key`" : "'' AS `system_key`";
        $sql = "SELECT p.`page_id`, p.`status`, p.`sort_order`, p.`date_modified`, " . $systemKeySelect . ",
                       COALESCE(NULLIF(pd.`title`, ''), pd_en.`title`, '') AS `title` "
            . $this->pageListFrom() . $this->pageListWhere($filters)
            . " ORDER BY p.`sort_order` ASC, p.`page_id` ASC";

        if (array_key_exists('limit', $filters)) {
            $start = max(0, (int)($filters['start'] ?? 0));
            $limit = min(100, max(1, (int)$filters['limit']));
            $sql .= " LIMIT " . $start . ", " . $limit;
        }

        $query = $this->db->query($sql);
        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /** @param array{title?: string, status?: string|int} $filters */
    public function getTotalPages(array $filters = []): int {
        $query = $this->db->query("SELECT COUNT(*) AS `total` " . $this->pageListFrom() . $this->pageListWhere($filters));
        return ($query instanceof \stdClass && isset($query->row['total'])) ? (int)$query->row['total'] : 0;
    }

    /** Joins idênticos na lista e contagem; título ativo com fallback EN. */
    private function pageListFrom(): string {
        $lang = $this->currentLanguageId();
        return "FROM `" . $this->t('page') . "` p
                LEFT JOIN `" . $this->t('page_description') . "` pd
                  ON pd.`page_id` = p.`page_id` AND pd.`language_id` = '" . $lang . "'
                LEFT JOIN `" . $this->t('page_description') . "` pd_en
                  ON pd_en.`page_id` = p.`page_id` AND pd_en.`language_id` = 1";
    }

    /** @param array{title?: string, status?: string|int} $filters */
    private function pageListWhere(array $filters): string {
        $where = [];
        $title = $filters['title'] ?? '';
        if (is_string($title) && trim($title) !== '') {
            $where[] = "COALESCE(NULLIF(pd.`title`, ''), pd_en.`title`, '') LIKE '%" . $this->db->escape(trim($title)) . "%'";
        }
        $status = $filters['status'] ?? 'all';
        if (in_array($status, ['0', '1', 0, 1], true)) {
            $where[] = "p.`status` = '" . (int)$status . "'";
        }
        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    /** Read-only legal footer links used by the private public-theme preview. */
    public function getPublicLegalPages(int $language_id): array {
        $language_id = max(1, $language_id);
        $query = $this->db->query(
            "SELECT DISTINCT p.`page_id`, s.`value` AS `keyword`, COALESCE(NULLIF(pd.`title`, ''), pd_en.`title`, '') AS `title`, FIELD(s.`value`,'cookies','politicas-internas') AS `sort_order`
               FROM `" . $this->t('page') . "` p
               INNER JOIN `" . $this->t('seo_url') . "` s ON s.`key` = CONCAT('page_id=', p.`page_id`)
               LEFT JOIN `" . $this->t('page_description') . "` pd ON pd.`page_id`=p.`page_id` AND pd.`language_id`='" . $language_id . "'
               LEFT JOIN `" . $this->t('page_description') . "` pd_en ON pd_en.`page_id`=p.`page_id` AND pd_en.`language_id`=1
              WHERE p.`status`='1' AND s.`value` IN ('cookies','politicas-internas')
              ORDER BY `sort_order`, p.`page_id`"
        );
        return ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];
    }

    /**
     * @return array<string, mixed> Linha principal (vazio = inexistente)
     */
    public function getPage(int $page_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('page') . "` WHERE `page_id` = '" . $page_id . "'"
        );

        return ($query instanceof \stdClass && !empty($query->row)) ? (array)$query->row : [];
    }

    /**
     * @return array<int, array<string, mixed>> indexado por language_id
     */
    public function getPageDescriptions(int $page_id): array {
        $query = $this->db->query(
            "SELECT * FROM `" . $this->t('page_description') . "` WHERE `page_id` = '" . $page_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $out[(int)$row['language_id']] = (array)$row;
        }

        return $out;
    }

    /**
     * keyword (seo_url.value) por idioma — key = 'page_id=N'.
     *
     * @return array<int, string> indexado por language_id
     */
    public function getPageKeywords(int $page_id): array {
        $query = $this->db->query(
            "SELECT `language_id`, `value` FROM `" . $this->t('seo_url') . "` WHERE `key` = 'page_id=" . $page_id . "'"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $out = [];

        foreach ($rows as $row) {
            $out[(int)$row['language_id']] = (string)$row['value'];
        }

        return $out;
    }

    /**
     * keyword já em uso naquele idioma por OUTRA entidade (seo_url é global)?
     */
    public function keywordTaken(string $keyword, int $language_id, int $exclude_page_id): bool {
        $query = $this->db->query(
            "SELECT `key` FROM `" . $this->t('seo_url') . "`
             WHERE `value` = '" . $this->db->escape($keyword) . "' AND `language_id` = '" . $language_id . "'
               AND `key` <> 'page_id=" . $exclude_page_id . "' LIMIT 1"
        );

        return $query instanceof \stdClass && !empty($query->row);
    }

    /**
     * Idiomas cadastrados (abas do form). Independe do status do front.
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
    public function addPage(array $data): int {
        $this->db->query(
            "INSERT INTO `" . $this->t('page') . "` SET
                `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                `sort_order` = '" . (int)$data['sort_order'] . "',
                `date_added` = NOW(),
                `date_modified` = NOW()"
        );

        $page_id = $this->db->getLastId();

        $this->syncDescriptions($page_id, (array)$data['descriptions']);

        return $page_id;
    }

    /**
     * @param array<string, mixed> $data Payload normalizado do controller
     */
    public function editPage(int $page_id, array $data, ?string $expectedFingerprint = null): void {
        $transaction = $expectedFingerprint !== null;
        if ($transaction && $this->db->query('START TRANSACTION') !== true) throw new \RuntimeException('page_edit_transaction_start_failed');
        try {
            if ($transaction) {
                $lock = $this->db->query("SELECT `page_id` FROM `" . $this->t('page') . "` WHERE `page_id`='" . $page_id . "' FOR UPDATE");
                if (!($lock instanceof \stdClass) || empty($lock->row) || !hash_equals($expectedFingerprint, $this->getPageEditFingerprint($page_id))) throw new \RuntimeException('edit_conflict');
            }
            if ($this->pageContentChanged($page_id, $data)) $this->captureBdreRevisions('page', $page_id);
            $this->db->query(
                "UPDATE `" . $this->t('page') . "` SET
                    `status`     = '" . (int)(!empty($data['status']) ? 1 : 0) . "',
                    `sort_order` = '" . (int)$data['sort_order'] . "',
                    `date_modified` = NOW()
                 WHERE `page_id` = '" . $page_id . "'"
            );
            $this->db->query("DELETE FROM `" . $this->t('page_description') . "` WHERE `page_id` = '" . $page_id . "'");
            $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'page_id=" . $page_id . "'");
            $this->syncDescriptions($page_id, (array)$data['descriptions']);
            if ($transaction && $this->db->query('COMMIT') !== true) throw new \RuntimeException('page_edit_transaction_commit_failed');
        } catch (\Throwable $e) {
            if ($transaction) { try { $this->db->query('ROLLBACK'); } catch (\Throwable $rollbackError) { /* preserve original */ } }
            throw $e;
        }
    }

    public function isSystemPage(int $page_id): bool {
        if (!$this->hasSystemKeyColumn()) {
            return false;
        }

        $query = $this->db->query(
            "SELECT `system_key` FROM `" . $this->t('page') . "` WHERE `page_id` = '" . (int)$page_id . "' LIMIT 1"
        );

        return $query instanceof \stdClass && !empty($query->row['system_key']);
    }

    public function deletePage(int $page_id): void {
        if ($this->isSystemPage($page_id)) {
            return;
        }

        $this->db->query("DELETE FROM `" . $this->t('page') . "` WHERE `page_id` = '" . $page_id . "'");

        $this->db->query("DELETE FROM `" . $this->t('page_description') . "` WHERE `page_id` = '" . $page_id . "'");

        $this->db->query("DELETE FROM `" . $this->t('seo_url') . "` WHERE `key` = 'page_id=" . $page_id . "'");
        if ($this->bdreRevisionTableExists()) $this->db->query("DELETE FROM `" . $this->t('bdre_revision') . "` WHERE `entity_type`='page' AND `entity_id`='" . $page_id . "'");
    }

    // ─── internos ────────────────────────────────────────────────────────

    /**
     * Descrições (uma por idioma) + keywords SEO — TEXT guard é só o escape.
     *
     * @param array<int, array<string, string>> $descriptions
     */
    private function pageContentChanged(int $pageId, array $data): bool {
        $page = $this->getPage($pageId);
        if (!$page || (int)($page['status'] ?? 0) !== (int)($data['status'] ?? 0) || (int)($page['sort_order'] ?? 0) !== (int)($data['sort_order'] ?? 0)) return true;
        $current = $this->getPageDescriptions($pageId); $keywords = $this->getPageKeywords($pageId);
        foreach ((array)($data['descriptions'] ?? []) as $languageId => $next) {
            $old = (array)($current[(int)$languageId] ?? []); $next = (array)$next;
            foreach (['title','description','meta_title','meta_description'] as $field) if ((string)($old[$field] ?? '') !== (string)($next[$field] ?? '')) return true;
            if ((string)($keywords[(int)$languageId] ?? '') !== (string)($next['keyword'] ?? '')) return true;
        }
        return false;
    }

    /** Capture persisted per-language content before an explicit edit; duplicates are skipped. */
    private function captureBdreRevisions(string $type, int $entityId): void {
        if (!$this->bdreRevisionTableExists()) return;
        $page = $this->getPage($entityId);
        if (!$page) return;
        $descriptions = $this->getPageDescriptions($entityId);
        $keywords = $this->getPageKeywords($entityId);
        foreach ($descriptions as $languageId => &$description) $description['keyword'] = $keywords[$languageId] ?? '';
        unset($description);
        foreach ($descriptions as $languageId => $description) {
            $snapshot = ['entity' => ['status' => (int)($page['status'] ?? 0), 'sort_order' => (int)($page['sort_order'] ?? 0)], 'description' => $description];
            $raw = (string)($description['description'] ?? '');
            $layout = (new \Reamur\System\Library\Cms\PageLayout())->decode($raw, (string)($page['system_key'] ?? ''));
            if ($layout !== null) { $snapshot['page_layout_sections'] = $layout['sections']; $snapshot['block_document'] = $layout['content']; }
            elseif (str_starts_with($raw, 'REAMURCMS_BDRE_JSON_V2:')) { $decoded = (new \Reamur\System\Library\Blog\BlockDocument(true))->deserializeDocument(substr($raw, strlen('REAMURCMS_BDRE_JSON_V2:'))); if (!empty($decoded['valid'])) $snapshot['block_document'] = $decoded['document']; }
            elseif ($raw !== '') { $converted=(new \Reamur\System\Library\Blog\BlockDocument(true))->convertLegacy($raw); if (($converted['status'] ?? '') === 'converted' && is_array($converted['document'] ?? null)) $snapshot['block_document']=$converted['document']; }
            $this->storeBdreRevision($type, $entityId, (int)$languageId, $snapshot);
        }
    }

    /** Stable server fingerprint for optimistic edit conflict detection (custom fields are added by the controller). */
    public function getPageEditFingerprint(int $pageId): string {
        $page=$this->getPage($pageId); if (!$page) return '';
        $descriptions=$this->getPageDescriptions($pageId); $keywords=$this->getPageKeywords($pageId); $safe=[];
        foreach ($descriptions as $languageId=>$row) { $row=(array)$row; $safe[(int)$languageId]=['title'=>(string)($row['title']??''),'description'=>(string)($row['description']??''),'meta_title'=>(string)($row['meta_title']??''),'meta_description'=>(string)($row['meta_description']??''),'keyword'=>(string)($keywords[$languageId]??'')]; }
        ksort($safe);
        $json=json_encode(['status'=>(int)($page['status']??0),'sort_order'=>(int)($page['sort_order']??0),'descriptions'=>$safe],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return is_string($json) ? hash('sha256',$json) : '';
    }

    /** @return array<int, array<string, mixed>> newest first; snapshot is decoded server-side */
    public function getPageRevisions(int $entityId, int $languageId): array {
        if ($entityId < 1 || $languageId < 1 || !$this->bdreRevisionTableExists()) return [];
        $q = $this->db->query("SELECT `revision_id`, `snapshot_json`, `date_added` FROM `" . $this->t('bdre_revision') . "` WHERE `entity_type`='page' AND `entity_id`='" . $entityId . "' AND `language_id`='" . $languageId . "' ORDER BY `revision_id` DESC LIMIT 20");
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

    private function storeBdreRevision(string $type, int $entityId, int $languageId, array $snapshot): void {
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || strlen($json) > 4194304) return;
        $hash = hash('sha256', $json);
        $table = $this->t('bdre_revision');
        $latest = $this->db->query("SELECT `content_hash` FROM `" . $table . "` WHERE `entity_type`='" . $this->db->escape($type) . "' AND `entity_id`='" . $entityId . "' AND `language_id`='" . $languageId . "' ORDER BY `revision_id` DESC LIMIT 1");
        if ($latest instanceof \stdClass && !empty($latest->row) && hash_equals((string)$latest->row['content_hash'], $hash)) return;
        $this->db->query("INSERT INTO `" . $table . "` SET `entity_type`='" . $this->db->escape($type) . "', `entity_id`='" . $entityId . "', `language_id`='" . $languageId . "', `content_hash`='" . $hash . "', `snapshot_json`='" . $this->db->escape($json) . "', `date_added`=NOW()");
        $this->db->query("DELETE FROM `" . $table . "` WHERE `entity_type`='" . $this->db->escape($type) . "' AND `entity_id`='" . $entityId . "' AND `language_id`='" . $languageId . "' AND `revision_id` NOT IN (SELECT `revision_id` FROM (SELECT `revision_id` FROM `" . $table . "` WHERE `entity_type`='" . $this->db->escape($type) . "' AND `entity_id`='" . $entityId . "' AND `language_id`='" . $languageId . "' ORDER BY `revision_id` DESC LIMIT 20) AS retained)");
    }

    private function syncDescriptions(int $page_id, array $descriptions): void {
        foreach ($descriptions as $language_id => $d) {
            $d = (array)$d;

            $this->db->query(
                "INSERT INTO `" . $this->t('page_description') . "` SET
                    `page_id`          = '" . $page_id . "',
                    `language_id`      = '" . (int)$language_id . "',
                    `title`            = '" . $this->db->escape((string)$d['title']) . "',
                    `description`      = '" . $this->db->escape((string)$d['description']) . "',
                    `meta_title`       = '" . $this->db->escape((string)$d['meta_title']) . "',
                    `meta_description` = '" . $this->db->escape((string)$d['meta_description']) . "'"
            );

            $keyword = trim((string)$d['keyword']);

            if ($keyword !== '') {
                $this->db->query(
                    "INSERT INTO `" . $this->t('seo_url') . "` SET
                        `key`         = 'page_id=" . $page_id . "',
                        `value`       = '" . $this->db->escape($keyword) . "',
                        `language_id` = '" . (int)$language_id . "',
                        `sort_order`  = 0"
                );
            }
        }
    }

    private function hasSystemKeyColumn(): bool {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        $query = $this->db->query("SHOW COLUMNS FROM `" . $this->t('page') . "` LIKE 'system_key'");
        $available = $query instanceof \stdClass && !empty($query->row);

        return $available;
    }

    private function t(string $table): string {
        return DB_PREFIX . $table;
    }

    /** language_id do idioma ATIVO no admin (language_directory → rms_language) */
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
}
