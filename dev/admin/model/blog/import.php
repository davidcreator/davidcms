<?php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 *
 * blog/import (ADMIN MODEL) — importador WordPress (F7-03, ADR 0044):
 * lê APENAS o endpoint público `/wp-json/wp/v2/posts` (sem credenciais),
 * baixa a imagem destacada e materializa posts na base multilíngue do blog
 * (language_id=1 — coalesce garante exibição nos 3 idiomas, ADR 0001/0042).
 * Idempotente por keyword: post já importado vira "skip", nunca duplica.
 * Slug em colisão cross-entidade ⇒ sufixo -2/-3/... (nunca escreve por cima).
 * Produz ainda o mapa 301 (cache/wp-301-map.txt) — ADR 0044 §301.
 */
namespace Reamur\Admin\Model\Blog;

class Import extends \Reamur\System\Engine\Model {

    /** Limite de segurança do fetch JSON/imagem (timeout duro, sem travar o admin) */
    private const FETCH_TIMEOUT = 20;
    private const FETCH_CONNECT = 5;
    private const MAX_IMAGE_BYTES = 5_000_000; // 5 MB — capa WP média é byte-level standard; sem resize

    /** UA identificável (transparência com o servidor de origem) */
    private const USER_AGENT = 'ReamurCMS-Importer/1.0 (+https://reamurcms.com)';

    /** @var array<int, string> Log humano de erros da leva (preview/run exibem) */
    private array $logLines = [];

    /**
     * Lê uma página do endpoint e normaliza posts (sem escrever nada).
     *
     * @return array{posts: array<int, array<string, string|null>>, error: string}
     */
    public function fetchPage(string $base_url, int $page, int $per_page): array {
        $base_url = trim($base_url);

        if (!preg_match('#^https?://#i', $base_url)) {
            return ['posts' => [], 'error' => 'invalid_base_url'];
        }

        if (in_array(strtolower($base_url), ['null', 'about:blank'], true)) {
            return ['posts' => [], 'error' => 'invalid_base_url'];
        }

        $page     = max(1, $page);
        $per_page = min(100, max(1, $per_page));

        $endpoint = rtrim($base_url, '/')
                  . '/wp-json/wp/v2/posts?status=publish&per_page=' . $per_page
                  . '&page=' . $page . '&_embed=1';

        $out = $this->httpGet($endpoint);

        if ($out['error'] !== '') {
            $this->log($out['error']);

            return ['posts' => [], 'error' => $out['error']];
        }

        $decoded = json_decode($out['body'], true);

        if (!is_array($decoded)) {
            $msg = 'json_invalid';

            $this->log($msg);

            return ['posts' => [], 'error' => $msg];
        }

        $posts = [];

        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $posts[] = $this->normalize($row);
        }

        return ['posts' => $posts, 'error' => ''];
    }

    /**
     * Importa UM post normalizado (transação "manual" sequencial por post —
     * falha em uma leva não suja as demais, ADR 0044).
     *
     * @param array<string, string|null> $post
     * @param bool $commit false = apenas simula (dry-run de decisões, sem SQL/escrita)
     * @return array{action: string, reason: string, post_id: int, keyword: string, image: string}
     */
    public function importOne(array $post, bool $commit): array {
        // Gate mínimo de conteúdo: slug + título
        $slug = (string)($post['slug'] ?? '');

        if ($slug === '' || (string)($post['title'] ?? '') === '') {
            return ['action' => 'skip', 'reason' => 'missing_fields', 'post_id' => 0, 'keyword' => '', 'image' => ''];
        }

        $keyword   = $this->resolveKeyword($slug);
        $statusNow = $this->statusOf($keyword, $slug);

        if ($statusNow === 'exists' || $this->alreadyImportedStem($keyword)) {
            // já importado (exato OU com sufixo -N) — idempotência da família do slug
            return ['action' => 'skip', 'reason' => 'already_imported', 'post_id' => 0, 'keyword' => $keyword, 'image' => ''];
        }

        if (!$commit) {
            return ['action' => $statusNow === 'collision' ? 'import_with_suffix' : 'import', 'reason' => $statusNow, 'post_id' => 0, 'keyword' => $keyword, 'image' => (string)$post['image_url']];
        }

        // Featured media → arquivo local (catalog/view/image/blog/<slug>.<ext>)
        $image_field = '';

        if (!empty($post['image_url'])) {
            $download = $this->downloadImage((string)$post['image_url'], $slug);

            if ($download['ok']) {
                $image_field = $download['path'];
            } else {
                $this->log('image_failed: ' . $slug . ' — ' . $download['error']);
            }
        }

        $date = trim((string)($post['date'] ?? ''));
        $ts   = $date !== '' ? strtotime($date) : false;

        $date_published = $ts !== false ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
        $now            = date('Y-m-d H:i:s');

        // 1) linha main
        $this->db->query(
            "INSERT INTO `" . $this->t('blog_post') . "` SET
                `image`          = '" . $this->db->escape($image_field) . "',
                `status`         = '1',
                `date_published` = '" . $this->db->escape($date_published) . "',
                `date_added`     = '" . $this->db->escape($now) . "',
                `date_modified`  = '" . $this->db->escape($now) . "'"
        );

        $post_id = (int)$this->db->getLastId();

        if ($post_id <= 0) {
            $this->log('main_insert_failed: ' . $slug);

            return ['action' => 'error', 'reason' => 'main_insert', 'post_id' => 0, 'keyword' => $keyword, 'image' => ''];
        }

        // 2) descrição multilíngue na âncora (language_id=1)
        $this->db->query(
            "INSERT INTO `" . $this->t('blog_post_description') . "` SET
                `post_id`          = '" . $post_id . "',
                `language_id`      = '1',
                `title`            = '" . $this->db->escape($this->clean((string)$post['title'], 255)) . "',
                `description`      = '" . $this->db->escape((string)($post['content'] ?? '')) . "',
                `meta_title`       = '" . $this->db->escape($this->clean((string)($post['meta_title'] ?? $post['title']), 255)) . "',
                `meta_description` = '" . $this->db->escape($this->clean((string)($post['excerpt'] ?? ''), 255)) . "'"
        );

        // 3) keyword seo_url (sufixo -N em colisão cross-entidade — relatório conta)
        $resolved_stem = $keyword;
        $keyword       = $this->ensureUniqueKeyword($keyword);
        $suffixed      = ($keyword !== '' && $keyword !== $resolved_stem);

        if ($keyword === '') {
            $this->log('keyword_sold: ' . $slug . ' — slug exaurido em colisões');

            return ['action' => 'error', 'reason' => 'keyword_collision', 'post_id' => $post_id, 'keyword' => '', 'image' => $image_field];
        }

        $this->db->query(
            "INSERT INTO `" . $this->t('seo_url') . "` SET
                `key`         = 'blog_id=" . $post_id . "',
                `value`       = '" . $this->db->escape($keyword) . "',
                `language_id` = '1',
                `sort_order`  = 0"
        );

        return [
            'action'  => $suffixed ? 'import_with_suffix' : 'imported',
            'reason'  => $suffixed ? 'collision' : '',
            'post_id' => $post_id,
            'keyword' => $keyword,
            'image'   => $image_field,
        ];
    }

    /**
     * Stem de keyword já pertence ao blog (exato OU com sufixo -N)?
     * Garante idempotência incluso quando o 1º import caiu com sufixo por
     * colisão cross-entidade — sem flag/wp_id na tabela (ADR 0044 §schema).
     */
    private function alreadyImportedStem(string $stem): bool {
        if ($stem === '') {
            return false;
        }

        $stem = $this->db->escape($stem);

        $query = $this->db->query(
            "SELECT `value` FROM `" . $this->t('seo_url') . "`
              WHERE `key` LIKE 'blog_id=%'
                AND (`value` = '" . $stem . "' OR `value` REGEXP '^" . $stem . "-[0-9]+$')
              LIMIT 1"
        );

        return $query instanceof \stdClass && !empty($query->rows);
    }

    /**
     * Grava/atualiza o mapa 301 (anexo de entrega, cenário ADR 0044 §301) —
     * linhas `<slug-antigo> <url-nova-absoluta>` por keyword sob nossa chave.
     */
    public function writeRedirectMap(string $site_base): int {
        $query = $this->db->query(
            "SELECT `value` FROM `" . $this->t('seo_url') . "`
              WHERE `key` LIKE 'blog_id=%' AND `language_id` = '1' ORDER BY `value`"
        );

        $rows = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows : [];

        $site_base = rtrim($site_base, '/');

        $out  = "# F7-03 — mapa 301 WP legado → blog interno (gerado " . date('Y-m-d H:i:s') . ")\n";
        $out .= "# cenário A: mesmo domínio — cole o par abaixo no .htaccess do app novo;\n";
        $out .= "# cenário B: domínios distintos — use as URLs absolutas no servidor do WP antigo.\n\n";

        foreach ($rows as $row) {
            $kw = trim((string)(is_array($row) ? ($row['value'] ?? '') : $row->value));

            if ($kw !== '') {
                $out .= $kw . ' ' . $site_base . '/' . $kw . "\n";
            }
        }

        $dir = defined('DIR_STORAGE') ? DIR_STORAGE . 'cache/' : '';
        $ok  = 0;

        if ($dir !== '') {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $ok = (int)@file_put_contents($dir . 'wp-301-map.txt', $out);
        }

        return $ok;
    }

    /**
     * URL pública do catálogo derivada do HTTP_SERVER do admin
     * (`.../admin/` → `.../catalog/`). Topologias exóticas: o form aceita
     * override explícito (entry_public_url, ADR 0044 §301).
     */
    public function defaultPublicUrl(): string {
        $http = defined('HTTP_SERVER') ? HTTP_SERVER : (string)$this->config->get('site_url', '');

        $derived = preg_replace('#/admin/?$#', '', (string)$http);

        return rtrim((string)$derived, '/') . '/catalog/';
    }

    /** @return array<int, string> */
    public function getLog(): array {
        return $this->logLines;
    }

    /* ──────────────── internals ──────────────── */

    /** Normaliza um item CRU do endpoint para a nossa forma enxuta */
    private function normalize(array $row): array {
        $title      = (string)($row['title']['rendered'] ?? '');
        $content    = trim((string)($row['content']['rendered'] ?? ''));
        $excerpt    = trim((string)($row['excerpt']['rendered'] ?? ''));
        $slug       = (string)($row['slug'] ?? '');
        $date       = (string)($row['date'] ?? '');
        $link       = (string)($row['link'] ?? '');

        // featured media via _embed
        $image_url = '';

        $embed      = $row['_embedded']['wp:featuredmedia'] ?? null;

        if (is_array($embed) && isset($embed[0]) && is_array($embed[0])) {
            $candidate =
                (string)($embed[0]['media_details']['sizes']['large']['source_url'] ?? '')
                ?: (string)($embed[0]['media_details']['sizes']['full']['source_url'] ?? '')
                ?: (string)($embed[0]['source_url'] ?? '');

            if (preg_match('#^https?://#i', $candidate)) {
                $image_url = $candidate;
            }
        }

        return [
            'wp_id'     => (string)(int)($row['id'] ?? 0),
            'slug'      => $slug,
            'title'     => $this->clean($title, 255),
            'content'   => $content,
            'excerpt'   => $this->clean($excerpt, 255),
            'meta_title'=> $this->clean($title, 255),
            'date'      => $date,
            'link'      => $link,
            'image_url' => $image_url,
        ];
    }

    /** fetch binário/HTML leve (cURL direto — sem wrapper externo no admin) */
    private function httpGet(string $url, bool $headOnly = false): array {
        $ch = curl_init($url);

        if ($ch === false) {
            return ['body' => '', 'error' => 'curl_init_failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => self::FETCH_CONNECT,
            CURLOPT_TIMEOUT        => self::FETCH_TIMEOUT,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_NOBODY         => $headOnly,
            CURLOPT_HTTPHEADER     => ['Accept: application/json,*/*;q=0.8'],
        ]);

        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = (string)curl_error($ch);

        curl_close($ch);

        if ($body === false || $err !== '') {
            return ['body' => '', 'error' => 'fetch_failed:' . ($err !== '' ? $err : "http $code")];
        }

        if ($code < 200 || $code >= 300) {
            return ['body' => '', 'error' => 'http_' . $code];
        }

        return ['body' => (string)$body, 'error' => ''];
    }

    /**
     * featured media → arquivo em catalog/view/image/blog/<slug>.<ext>
     * (diretório criado na 1ª importação; cabeçalho/canary por mimetype).
     *
     * @return array{ok: bool, path: string, error: string}
     */
    private function downloadImage(string $url, string $slug): array {
        $name = $this->safeName($slug, parse_url((string)$url, PHP_URL_PATH) ?? '');

        if ($name === '') {
            return ['ok' => false, 'path' => '', 'error' => 'bad_name'];
        }

        $fetch = $this->httpGet($url);

        if ($fetch['error'] !== '') {
            return ['ok' => false, 'path' => '', 'error' => $fetch['error']];
        }

        if (strlen($fetch['body']) > self::MAX_IMAGE_BYTES) {
            return ['ok' => false, 'path' => '', 'error' => 'too_big'];
        }

        $info = getimagesizefromstring($fetch['body']);

        if ($info === false) {
            return ['ok' => false, 'path' => '', 'error' => 'not_image'];
        }

        // cria <catalog>/view/image/blog/ — raiz muda entre dev tree e build
        // prod (cPanel): tenta prod (catalog/ ao lado do admin) 1º, dev depois.
        $baseDir = '';

        foreach ($this->catalogCandidates() as $root) {
            if ($root !== '' && is_dir($root . 'view/image')) {
                $baseDir = $root . 'view/image/blog/';

                break;
            }
        }

        if ($baseDir === '') {
            // última tentativa: 1º candidato mesmo sem view/image (será mkdir'd)
            $cands    = $this->catalogCandidates();
            $baseDir  = ($cands[0] ?? '') !== '' ? $cands[0] . 'view/image/blog/' : '';
        }

        if ($baseDir === '') {
            return ['ok' => false, 'path' => '', 'error' => 'no_dir'];
        }

        if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true)) {
            return ['ok' => false, 'path' => '', 'error' => 'mkdir_failed'];
        }

        if (@file_put_contents($baseDir . $name, $fetch['body']) === false) {
            return ['ok' => false, 'path' => '', 'error' => 'write_failed'];
        }

        // guard anti-listing
        if (!file_exists($baseDir . 'index.html')) {
            @file_put_contents($baseDir . 'index.html', '');
        }

        return ['ok' => true, 'path' => 'view/image/blog/' . $name, 'error' => ''];
    }

    /** slug do WP (já SEO-friendly) → keyword validada pelo regex do módulo */
    private function resolveKeyword(string $slug): string {
        $slug = strtolower(trim($slug));

        if ($slug === '') {
            return '';
        }

        // saneamento extra: tira tudo que não for [a-z0-9-] e saneia duplicatas
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim(preg_replace('/-+/', '-', $slug) ?? '', '-');

        if ($slug === '') {
            return '';
        }

        return mb_substr($slug, 0, 64);
    }

    /**
     * Keyword garantidamente única na seo_url global (cross-entidade).
     * Já importado (mesma keyword com key blog_id=*) ⇒ prefixa _imported_
     * fora (statusOf falou 'exists'); aqui só resolvemos COLISÕES novas.
     */
    private function ensureUniqueKeyword(string $keyword): string {
        for ($i = 0; $i <= 9; $i++) {
            $candidate = $i === 0 ? $keyword : $keyword . '-' . ($i + 1);

            $query = $this->db->query(
                "SELECT `key` FROM `" . $this->t('seo_url') . "`
                  WHERE `value` = '" . $this->db->escape($candidate) . "' LIMIT 1"
            );

            $taken = $query instanceof \stdClass && !empty($query->rows);

            if (!$taken) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Status de uma keyword antes de decidir: 'exists' (já do blog = idempotência),
     * 'collision' (taken por page/project/tool = sufixo), 'free'.
     */
    private function statusOf(string $keyword, string $slug): string {
        if ($keyword === '') {
            return 'free';
        }

        $query = $this->db->query(
            "SELECT `key` FROM `" . $this->t('seo_url') . "`
              WHERE `value` = '" . $this->db->escape($keyword) . "' LIMIT 1"
        );

        $row = ($query instanceof \stdClass && !empty($query->rows)) ? (array)$query->rows[0] : null;

        if (!$row) {
            return 'free';
        }

        $owner = (string)$row['key'];

        if (strpos($owner, 'blog_id=') === 0) {
            return 'exists'; // já importado numa leva anterior — pular, é idempotente
        }

        return 'collision';   // keyword ocupada por outra entidade — sufixo
    }

    /** Texto WP → limpo (decodifica entidades, tira tags, normaliza espaço, cap) */
    private function clean(string $html, int $cap): string {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');

        return mb_strlen($plain) > $cap ? mb_strimwidth($plain, 0, $cap, '', 'UTF-8') : $plain;
    }

    /** nome seguro do arquivo da imagem: <slug>.<ext-mime-ext> */
    private function safeName(string $slug, string $url_path): string {
        $base = preg_replace('/[^a-z0-9]+/i', '', $slug) ?: 'imagem';
        $ext  = strtolower(pathinfo((string)$url_path, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        return mb_substr($base, 0, 64) . '.' . $ext;
    }

    private function log(string $line): void {
        $this->logLines[] = $line;
    }

    /**
     * Raiz do catalog em disco: build prod = <app>/catalog/ (vizinha do admin,
     * conforme os config.php.EXEMPLO da F6-04); dev tree = <repo>/dev/catalog/.
     *
     * @return array<int, string>
     */
    private function catalogCandidates(): array {
        $out = [];

        if (defined('DIR_REAMUR')) {
            $root = rtrim((string)constant('DIR_REAMUR'), '/');

            $out[] = $root . '/catalog/';      // build prod (cPanel)
            $out[] = $root . '/dev/catalog/';  // dev tree (dev/install, lab)
        }

        return array_values(array_filter($out));
    }

    private function t(string $base): string {
        return DB_PREFIX . $base;
    }
}
