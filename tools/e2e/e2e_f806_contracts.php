<?php declare(strict_types=1);
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS
 * @license   https://opensource.org/licenses/GPL-3.0
 *
 * Fase 8 / Tarefa 06 — PHP e Twig reais, serviços/DB/IO de sessão em fixtures.
 * Não carrega config.php/framework, não conecta banco, não faz HTTP ou deploy.
 * Usa classes reais Registry/Controller/Model/Session/Csrf/Pagination do projeto.
 * O Session é criado sem construtor (dados somente em memória).
 */
namespace Reamur\Tests\F806;

$mirror = $argv[1] ?? dirname(__DIR__, 2) . '/dev';
$mirror = realpath($mirror) ?: '';
if ($mirror === '' || !is_dir($mirror)) { fwrite(STDERR, "MIRROR inválido.\n"); exit(2); }
$autoload = getenv('TWIG_AUTOLOAD') ?: $mirror . '/system/storage/vendor/autoload.php';
if (!is_file($autoload)) { fwrite(STDERR, "Twig ausente: use o Composer do projeto ou TWIG_AUTOLOAD explícito.\n"); exit(2); }
require_once $autoload;
if (!class_exists(\Twig\Environment::class) || !extension_loaded('mbstring')) { fwrite(STDERR, "Requer Twig 3 e mbstring.\n"); exit(2); }
if (!defined('DB_PREFIX')) { define('DB_PREFIX', 'f806_'); }
if (!defined('DIR_SYSTEM')) { define('DIR_SYSTEM', $mirror . '/system/'); }
foreach (['engine/registry.php', 'engine/controller.php', 'engine/model.php', 'library/session.php', 'library/security/csrf.php', 'library/pagination.php'] as $file) {
    require_once DIR_SYSTEM . $file;
}
require_once $mirror . '/admin/controller/common/header.php';
require_once $mirror . '/admin/controller/cms/page.php';
require_once $mirror . '/admin/model/cms/page.php';

use Reamur\System\Engine\Registry;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Session;
use Reamur\Admin\Controller\Cms\Page as Controller;
use Reamur\Admin\Model\Cms\Page as Model;

final class LanguageFixture {
    public array $values;
    public function __construct(string $file) { $this->values = (static function (string $file): array { $_ = []; require $file; return $_; })($file); }
    public function get(string $key): string { if (!array_key_exists($key, $this->values)) { throw new \RuntimeException('Chave i18n ausente: ' . $key); } return (string)$this->values[$key]; }
}
final class ConfigFixture {
    public function get(string $key, mixed $default = null): mixed { return ['site_url' => 'http://example.test/admin/', 'language_directory' => 'en-us'][$key] ?? $default; }
}
final class DocumentFixture {
    public array $styles = [];
    public array $scripts = [];
    public string $title = '';
    public function setTitle(string $title): void { $this->title = $title; }
    public function addStyle(string $url): void { $this->styles[] = $url; }
    public function addScript(string $url): void { $this->scripts[] = $url; }
}
final class ResponseFixture {
    public string $output = '';
    public string $location = '';
    public function setOutput(string $output): void { $this->output = $output; }
    public function redirect(string $url): void { $this->location = $url; }
}
final class UrlFixture {
    public function link(string $route, string|array $args = '', bool $secure = false): string {
        return 'http://example.test/admin/index.php?route=' . $route . ($args ? '&' . (is_array($args) ? http_build_query($args) : $args) : '');
    }
}
final class UserFixture {
    public function __construct(public bool $modify) {}
    public function hasPermission(string $kind, string $route): bool { return $kind === 'access' || $this->modify; }
}
final class StoreFixture {
    public array $records = [];
    public array $calls = [];
    public int $sequence = 100;
    public int $adds = 0;
    public int $edits = 0;
    public int $deletes = 0;
    public array $taken = [];
    public function getLanguages(): array { return [['language_id' => 1, 'name' => 'English', 'code' => 'en-us'], ['language_id' => 2, 'name' => 'Português', 'code' => 'pt-br'], ['language_id' => 3, 'name' => 'Español', 'code' => 'es-eu']]; }
    private function filtered(array $filters): array {
        $rows = array_values($this->records);
        $rows = array_filter($rows, static function (array $row) use ($filters): bool {
            return (($filters['title'] ?? '') === '' || mb_stripos($row['title'], $filters['title']) !== false)
                && (!in_array($filters['status'] ?? 'all', ['0', '1'], true) || $row['status'] === (int)$filters['status']);
        });
        usort($rows, static fn(array $a, array $b): int => [$a['sort_order'], $a['page_id']] <=> [$b['sort_order'], $b['page_id']]);
        return array_values($rows);
    }
    public function getTotalPages(array $filters = []): int { return count($this->filtered($filters)); }
    public function getPages(array $filters = []): array {
        $this->calls[] = $filters;
        $rows = $this->filtered($filters);
        return isset($filters['limit']) ? array_slice($rows, $filters['start'], $filters['limit']) : $rows;
    }
    public function getPage(int $id): array { return $this->records[$id] ?? []; }
    public function getPageDescriptions(int $id): array { return $this->records[$id]['descriptions'] ?? []; }
    public function getPageKeywords(int $id): array { return array_map(static fn(array $d): string => $d['keyword'], $this->getPageDescriptions($id)); }
    public function keywordTaken(string $value, int $language, int $excluded): bool { return in_array($value, $this->taken, true); }
    public function addPage(array $data): int { $this->adds++; $id = ++$this->sequence; $this->save($id, $data); return $id; }
    public function editPage(int $id, array $data): void { $this->edits++; $this->save($id, $data); }
    private function save(int $id, array $data): void {
        $this->records[$id] = $data + ['page_id' => $id, 'date_modified' => '2026-10-23 12:00:00'];
        $this->records[$id]['title'] = $data['descriptions'][1]['title'];
    }
    public function deletePage(int $id): void { $this->deletes++; unset($this->records[$id]); }
}
final class LoaderFixture {
    public array $data = [];
    private \Twig\Environment $twig;
    public function __construct(private string $mirror) {
        $this->twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader($mirror . '/admin/view/template'), ['cache' => false, 'autoescape' => 'html', 'strict_variables' => true]);
    }
    public function language(string $route): void { if ($route !== 'cms/page') { throw new \RuntimeException('Rota de language inesperada'); } }
    public function model(string $route): void { if ($route !== 'cms/page') { throw new \RuntimeException('Model inesperado'); } }
    public function controller(string $route, array $data = []): string { return '<!-- shell fixture: ' . $route . ' -->'; }
    public function view(string $route, array $data): string { $this->data = $data; return $this->twig->render($route . '.twig', $data); }
}
final class Harness {
    public Registry $registry;
    public Controller $controller;
    public StoreFixture $store;
    public Session $session;
    public \stdClass $request;
    public ResponseFixture $response;
    public LoaderFixture $loader;
    public DocumentFixture $document;
    public LanguageFixture $language;
    public function __construct(string $mirror, ?StoreFixture $store = null, ?Session $session = null, string $locale = 'en-us', bool $modify = true) {
        $this->registry = new Registry();
        $this->store = $store ?? new StoreFixture();
        $this->session = $session ?? (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        $this->request = (object)['server' => ['REQUEST_METHOD' => 'GET'], 'get' => [], 'post' => []];
        $this->response = new ResponseFixture(); $this->loader = new LoaderFixture($mirror); $this->document = new DocumentFixture();
        $this->language = new LanguageFixture($mirror . '/admin/language/' . $locale . '/cms/page.php');
        foreach (['request' => $this->request, 'response' => $this->response, 'load' => $this->loader, 'document' => $this->document, 'language' => $this->language, 'config' => new ConfigFixture(), 'user' => new UserFixture($modify), 'session' => $this->session, 'url' => new UrlFixture(), 'model_cms_page' => $this->store] as $key => $value) { $this->registry->set($key, $value); }
        $this->controller = new Controller($this->registry);
    }
    public function post(array $data): void { $this->request->server['REQUEST_METHOD'] = 'POST'; $this->request->post = $data; }
}
final class DbFixture {
    public array $queries = [];
    public function escape(string $value): string { return addslashes($value); }
    public function query(string $sql): \stdClass {
        $this->queries[] = $sql;
        if (str_contains($sql, 'SELECT `language_id`')) { return (object)['row' => ['language_id' => 1]]; }
        return (object)['row' => ['total' => 7], 'rows' => [['page_id' => 77, 'title' => 'Fixture']]];
    }
}
final class Suite {
    private static int $passed = 0;
    private static int $failed = 0;
    private static string $mirror;
    private static function ok(bool $condition, string $message): void { if (!$condition) { throw new \RuntimeException($message); } }
    private static function test(string $label, callable $fn): void {
        try { $fn(); self::$passed++; echo 'PASS ' . $label . PHP_EOL; }
        catch (\Throwable $e) { self::$failed++; fwrite(STDERR, 'FAIL ' . $label . ': ' . $e->getMessage() . PHP_EOL); }
    }
    private static function payload(int $version = 1): array {
        $out = ['sort_order' => 7, 'page_description' => []];
        foreach ([1 => 'English', 2 => 'Ação e edição', 3 => 'Español ñ'] as $id => $text) {
            $out['page_description'][$id] = ['title' => $text . ' v' . $version, 'description' => '<h2>' . $text . '</h2><p>v' . $version . ' & texto</p>', 'keyword' => 'f806-lang-' . $id . '-v' . $version, 'meta_title' => 'Meta ' . $text, 'meta_description' => 'Descrição ' . $text];
        }
        return $out;
    }
    private static function harness(?StoreFixture $store = null, ?Session $session = null, string $locale = 'en-us', bool $modify = true): Harness { return new Harness(self::$mirror, $store, $session, $locale, $modify); }
    private static function populated(int $count): StoreFixture {
        $s = new StoreFixture(); for ($i = 0; $i < $count; $i++) { $p = self::payload(); $p['status'] = $i % 2; $p['sort_order'] = $i; $p['descriptions'] = $p['page_description']; unset($p['page_description']); $s->addPage($p); } $s->adds = 0; return $s;
    }
    private static function model(): array { $r = new Registry(); $db = new DbFixture(); $r->set('db', $db); $r->set('config', new ConfigFixture()); return [new Model($r), $db]; }
    public static function run(string $mirror): int {
        self::$mirror = $mirror;
        self::test('01 · model sem argumentos mantém lista completa/ordenação', static function (): void { [$m, $db] = self::model(); $m->getPages(); $q = end($db->queries); self::ok(!str_contains($q, ' LIMIT ') && str_contains($q, 'ORDER BY p.`sort_order` ASC, p.`page_id` ASC'), 'Contrato antigo de getPages mudou'); });
        self::test('02 · model aplica título/status/limite com escape', static function (): void { [$m, $db] = self::model(); $m->getPages(['title' => "O'Brien", 'status' => '0', 'start' => 20, 'limit' => 20]); $q = end($db->queries); self::ok(str_contains($q, "O\\'Brien") && str_contains($q, "p.`status` = '0'") && str_contains($q, 'LIMIT 20, 20'), 'SQL de filtros inválido'); });
        self::test('03 · contagem/lista compartilham os mesmos joins/filtros', static function (): void { [$m, $db] = self::model(); $f = ['title' => 'x', 'status' => '1']; self::ok($m->getTotalPages($f) === 7, 'Total inválido'); $count = end($db->queries); $m->getPages($f); $list = end($db->queries); $from = substr($list, strpos($list, 'FROM ')); $from = substr($from, 0, strpos($from, ' ORDER BY')); self::ok(substr($count, strpos($count, 'FROM ')) === $from, 'Predicados divergentes'); });
        self::test('04 · SQL usa fallback EN e prefixo, sem DDL', static function (): void { [$m, $db] = self::model(); $m->getTotalPages(['title' => 'x']); $q = end($db->queries); self::ok(str_contains($q, "COALESCE(NULLIF(pd.`title`, ''), pd_en.`title`, '')") && str_contains($q, '`f806_page`') && !preg_match('/DROP|ALTER|CREATE/i', $q), 'Fallback/prefixo incorretos'); });
        self::test('05 · status adulterado ignorado e limites normalizados', static function (): void { [$m, $db] = self::model(); $m->getPages(['status' => "1 OR 1=1", 'start' => -20, 'limit' => 999]); $q = end($db->queries); self::ok(!str_contains($q, '1 OR 1=1') && str_contains($q, 'LIMIT 0, 100'), 'Normalização inválida'); });
        self::test('06 · índice vazio renderiza estado e CSRF sem warnings', static function (): void { $h = self::harness(); $h->controller->index(); self::ok($h->loader->data['total_pages'] === 0 && str_contains($h->response->output, 'empty-state') && strlen($h->loader->data['csrf_token']) === 64, 'Índice vazio inválido'); });
        self::test('07 · 45 registros: página 2 contém 20 e mantém total', static function (): void { $h = self::harness(self::populated(45)); $h->request->get['page'] = '2'; $h->controller->index(); self::ok(count($h->loader->data['pages']) === 20 && $h->loader->data['total_pages'] === 45 && end($h->store->calls)['start'] === 20, 'Paginação incorreta'); });
        self::test('08 · página além do fim recua para a última', static function (): void { $h = self::harness(self::populated(45)); $h->request->get['page'] = (string)PHP_INT_MAX; $h->controller->index(); self::ok(end($h->store->calls)['start'] === 40 && count($h->loader->data['pages']) === 5, 'Recorte além do fim incorreto'); });
        self::test('09 · filtros malformados não produzem warnings/overflow', static function (): void { $h = self::harness(); $h->request->get = ['filter_title' => [], 'filter_status' => [], 'page' => []]; $h->controller->index(); self::ok($h->loader->data['filter_title'] === '' && $h->loader->data['filter_status'] === 'all' && end($h->store->calls)['start'] === 0, 'Query array não normalizada'); $numeric = self::harness(); $numeric->request->get['filter_title'] = '0'; $numeric->controller->index(); self::ok(str_contains($numeric->response->output, '>Reset</a>'), 'Título zero perdeu o link de reset'); });
        self::test('10 · filtro desabilitado selecionado e total correspondente', static function (): void { $h = self::harness(self::populated(45)); $h->request->get['filter_status'] = '0'; $h->controller->index(); self::ok($h->loader->data['total_pages'] === 23 && str_contains($h->response->output, 'value="0" selected'), 'Filtro de status incorreto'); });
        self::test('11 · paginação preserva filtros sem amp;amp nem XSS', static function (): void { $s = self::populated(45); foreach ($s->records as &$r) { $r['title'] = 'O\'Brien & <tag>'; } unset($r); $h = self::harness($s); $h->request->get = ['filter_title' => 'O\'Brien & <tag>', 'filter_status' => '0']; $h->controller->index(); $html = $h->loader->data['pagination']; self::ok(!str_contains($html, '&amp;amp;') && !str_contains($h->response->output, '<tag>'), 'Escape de paginação/filtro inválido'); preg_match_all('/href="([^"]+)"/', $html, $matches); self::ok(count($matches[1]) > 0, 'Links ausentes'); foreach ($matches[1] as $attr) { parse_str((string)parse_url(html_entity_decode($attr, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_QUERY), $query); self::ok(($query['filter_title'] ?? '') === 'O\'Brien & <tag>' && ($query['filter_status'] ?? '') === '0', 'Filtros perdidos no href'); } });
        self::test('12 · readonly: sem add/delete/save na UI', static function (): void { $h = self::harness(self::populated(1), null, 'en-us', false); $h->controller->index(); self::ok(!str_contains($h->response->output, 'data-rms-confirm-delete') && !str_contains($h->response->output, 'route=cms/page.add'), 'Ações modify vazaram'); $h = self::harness(null, null, 'en-us', false); $h->controller->add(); self::ok(!str_contains($h->response->output, 'type="submit"'), 'Save readonly visível'); });
        self::test('13 · form: 15 ids/names, tabs e textarea sem editor/upload', static function (): void { $h = self::harness(); $h->controller->add(); $html = $h->response->output; foreach ([1,2,3] as $id) foreach (['title', 'keyword', 'description', 'meta-title', 'meta-description'] as $field) { self::ok(substr_count($html, 'id="input-' . $field . '-' . $id . '"') === 1, 'ID perdido/duplicado'); } self::ok(str_contains($html, 'page_description[3][description]') && str_contains($html, 'cmsPageLangTabs') && !str_contains($html, 'data-rte') && !str_contains($html, 'name="image"'), 'Contrato de form/editor mudou'); });
        self::test('14 · script específico e CSS versionados sem rms-dash', static function (): void { $h = self::harness(); $h->controller->add(); self::ok(str_contains(implode('', $h->document->scripts), 'cms-page.js?v=') && str_contains(implode('', $h->document->styles), 'admin.css?v=') && !isset($h->loader->data['body_class']), 'Assets/chrome incorretos'); });
        self::test('15 · i18n en/pt-BR/es renderiza com Twig strict', static function (): void { $keys = null; foreach (['en-us', 'pt-br', 'es-eu'] as $locale) { $h = self::harness(null, null, $locale); $h->controller->add(); self::ok(str_contains($h->response->output, $h->language->get('heading_title')), 'Heading não traduzido'); $k = array_keys($h->language->values); sort($k); if ($keys !== null) self::ok($keys === $k, 'Chaves divergentes'); $keys = $k; $h->controller->index(); } });
        self::test('16 · add sem CSRF não muta e mantém valores/erro', static function (): void { $h = self::harness(); $h->post(self::payload()); $h->controller->add(); self::ok($h->store->adds === 0 && $h->loader->data['error'] === $h->language->get('error_csrf') && $h->loader->data['languages'][1]['fields']['title'] === 'Ação e edição v1', 'CSRF add sem token falhou'); });
        self::test('17 · token array/incorreto é recusado', static function (): void { foreach ([[], 'wrong'] as $token) { $h = self::harness(); $h->post(self::payload() + ['csrf_token' => $token]); $h->controller->add(); self::ok($h->store->adds === 0, 'Token inválido aceito'); } });
        self::test('18 · add válido normaliza contrato e preserva HTML/Unicode', static function (): void { $h = self::harness(); $h->post(self::payload() + ['csrf_token' => Csrf::token($h->session)]); $h->controller->add(); $r = $h->store->getPage(101); self::ok($h->store->adds === 1 && $r['status'] === 0 && $r['sort_order'] === 7 && $r['descriptions'][2]['description'] === self::payload()['page_description'][2]['description'] && str_contains($h->response->location, 'route=cms/page'), 'Add normalizado incorreto'); });
        self::test('19 · título EN obrigatório mesmo com CSRF válido', static function (): void { $h = self::harness(); $p = self::payload(); $p['page_description'][1]['title'] = ''; $h->post($p + ['csrf_token' => Csrf::token($h->session)]); $h->controller->add(); self::ok($h->store->adds === 0 && $h->loader->data['error'] === $h->language->get('error_title'), 'Required EN perdido'); });
        self::test('20 · SEO formato e unicidade global preservados', static function (): void { foreach (['BAD keyword!', 'occupied'] as $keyword) { $h = self::harness(); $h->store->taken = ['occupied']; $p = self::payload(); $p['page_description'][1]['keyword'] = $keyword; $h->post($p + ['csrf_token' => Csrf::token($h->session)]); $h->controller->add(); self::ok($h->store->adds === 0 && $h->loader->data['error'] !== '', 'Validação SEO perdida'); } });
        self::test('21 · edit inválido não muta e GET conserva o salvo', static function (): void { $store = self::populated(1); $h = self::harness($store); $h->post(self::payload(2)); $h->controller->edit('101'); self::ok($store->edits === 0 && $store->getPage(101)['descriptions'][1]['title'] === 'English v1', 'CSRF edit falhou'); });
        self::test('22 · edit válido preserva ID e troca conteúdo/keyword/status', static function (): void { $h = self::harness(self::populated(1)); $h->post(self::payload(2) + ['status' => '1', 'csrf_token' => Csrf::token($h->session)]); $h->controller->edit('101'); $r = $h->store->getPage(101); self::ok($h->store->edits === 1 && $r['status'] === 1 && $r['descriptions'][3]['keyword'] === 'f806-lang-3-v2', 'Edit inválido'); });
        self::test('23 · GET delete nunca exclui', static function (): void { $h = self::harness(self::populated(1)); $h->controller->delete('101'); self::ok($h->store->deletes === 0 && str_contains($h->response->location, 'error/permission'), 'GET delete mutou'); });
        self::test('24 · POST delete sem CSRF recusa e gera flash de erro', static function (): void { $h = self::harness(self::populated(1)); $h->post([]); $h->controller->delete('101'); self::ok($h->store->deletes === 0 && ($h->session->data['flash']['type'] ?? '') === 'error', 'CSRF delete falhou'); });
        self::test('25 · POST delete válido exclui somente ID informado', static function (): void { $h = self::harness(self::populated(2)); $h->post(['csrf_token' => Csrf::token($h->session)]); $h->controller->delete('101'); self::ok($h->store->deletes === 1 && !$h->store->getPage(101) && $h->store->getPage(102) !== [], 'Delete atingiu outra página'); });
        self::test('26 · ACL modify negada bloqueia add/edit/delete com token', static function (): void { foreach (['add', 'edit', 'delete'] as $method) { $h = self::harness(self::populated(1), null, 'en-us', false); $h->post(self::payload(2) + ['csrf_token' => Csrf::token($h->session)]); $h->controller->{$method}('101'); self::ok($h->store->adds + $h->store->edits + $h->store->deletes === 0 && str_contains($h->response->location, 'error/permission'), 'ACL modify falhou'); } });
        self::test('27 · página inexistente mantém not-found sem mutar', static function (): void { $h = self::harness(); $h->controller->edit('999'); self::ok(str_contains($h->response->location, 'error/not_found') && $h->store->edits === 0, '404 mudou'); });
        self::test('28 · roundtrip controller em memória, não banco real', static function (): void { $h = self::harness(); $h->post(self::payload() + ['csrf_token' => Csrf::token($h->session)]); $h->controller->add(); $get = self::harness($h->store, $h->session); $get->controller->edit('101'); self::ok($get->loader->data['languages'][2]['fields']['description'] === self::payload()['page_description'][3]['description'], 'Roundtrip da fixture perdeu corpo'); self::ok(!str_contains($get->response->output, '<h2>Español'), 'HTML foi renderizado dentro do textarea em vez de escapado'); });
        self::test('29 · título/keyword maliciosos escapados no HTML', static function (): void { $h = self::harness(); $p = self::payload(); $p['page_description'][1]['title'] = '<script>alert(1)</script>'; $p['page_description'][1]['keyword'] = 'bad quote"'; $h->post($p + ['csrf_token' => Csrf::token($h->session)]); $h->controller->add(); self::ok(!str_contains($h->response->output, '<script>alert') && str_contains($h->response->output, '&lt;script&gt;'), 'Escape de HTML perdido'); });
        self::test('30 · modal por data attribute, sem confirm inline/injeção', static function (): void { $h = self::harness(self::populated(1)); $h->controller->index(); self::ok(str_contains($h->response->output, 'data-rms-confirm-delete=') && !str_contains($h->response->output, 'onsubmit=') && substr_count($h->response->output, 'name="csrf_token"') === 1, 'Contrato de delete/modal inválido'); });
        echo "\nF8-06 contratos: " . self::$passed . ' PASS / ' . self::$failed . ' FAIL — PHP ' . PHP_VERSION . ', Twig ' . \Twig\Environment::VERSION . "; DB/serviços/IO de sessão em fixtures, sem navegador/HTTP.\n";
        return self::$failed > 0 ? 1 : 0;
    }
}
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool { if (!(error_reporting() & $severity)) { return false; } throw new \ErrorException($message, 0, $severity, $file, $line); });
exit(Suite::run($mirror));
