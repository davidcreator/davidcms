<?php declare(strict_types=1);
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS
 * @license https://opensource.org/licenses/GPL-3.0
 * F8-07: PHP/Twig reais; DB/serviços/IO de sessão simulados.
 * MediaUpload real só em FS privado não servido; não faz upload HTTP/move.
 */
namespace Reamur\Tests\F807;

use Reamur\System\Engine\Registry;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Session;
use Reamur\Admin\Controller\Catalog\Category as Controller;
use Reamur\Admin\Model\Catalog\Category as Model;

final class FilesFixture {
    private static string $root = '';
    private static array $files = [];
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR4nGOQqgj4D8IMMAYAPqgHhR+OGvoAAAAASUVORK5CYII=';
    public static function init(): string {
        self::$root = sys_get_temp_dir() . '/f807-qa-' . bin2hex(random_bytes(8));
        if (!mkdir(self::$root, 0700)) { throw new \RuntimeException('Não foi possível criar fixture privada.'); }
        foreach (['admin', 'catalog', 'catalog/view', 'catalog/view/image', 'catalog/view/image/category'] as $p) {
            if (!mkdir(self::$root . '/' . $p, 0700)) { throw new \RuntimeException('Falha na fixture privada.'); }
        }
        return self::$root . '/admin/';
    }
    public static function image(string $name): string {
        if (!preg_match('/^[a-z0-9-]+$/', $name)) { throw new \RuntimeException('Nome da fixture inválido'); }
        $relative = 'view/image/category/' . $name . '.png';
        $file = self::absolute($relative); $bytes = (string)base64_decode(self::PNG, true);
        file_put_contents($file, $bytes); chmod($file, 0600); self::$files[$file] = hash('sha256', $bytes);
        return $relative;
    }
    public static function absolute(string $relative): string { return self::$root . '/catalog/' . $relative; }
    public static function cleanup(): void {
        foreach (self::$files as $p => $hash) {
            if (file_exists($p)) {
                if (is_link($p) || hash_file('sha256', $p) !== $hash) { throw new \RuntimeException('Fixture foi alterada; não remover arquivo desconhecido.'); }
                unlink($p);
            }
        }
        foreach (['catalog/view/image/category', 'catalog/view/image', 'catalog/view', 'catalog', 'admin', ''] as $p) {
            $dir = self::$root . ($p === '' ? '' : '/' . $p);
            if (is_dir($dir) && !rmdir($dir)) { throw new \RuntimeException('Diretório de fixture não vazio; não remover amplamente.'); }
        }
    }
}

$mirror = realpath($argv[1] ?? dirname(__DIR__, 2) . '/dev') ?: '';
$autoload = getenv('TWIG_AUTOLOAD') ?: $mirror . '/system/storage/vendor/autoload.php';
if ($mirror === '' || !is_file($autoload)) { fwrite(STDERR, "MIRROR/Twig ausentes. Use o Composer do projeto ou TWIG_AUTOLOAD explícito.\n"); exit(2); }
require_once $autoload;
if (!class_exists(\Twig\Environment::class) || !extension_loaded('mbstring') || defined('DIR_APPLICATION')) { fwrite(STDERR, "Requer CLI isolado, mbstring e Twig3; não bootar a aplicação.\n"); exit(2); }
if (!defined('DB_PREFIX')) { define('DB_PREFIX', 'f807_'); }
if (!defined('DIR_SYSTEM')) { define('DIR_SYSTEM', $mirror . '/system/'); }
foreach (['engine/registry.php', 'engine/controller.php', 'engine/model.php', 'library/session.php', 'library/security/csrf.php', 'library/pagination.php', 'library/media_upload.php'] as $p) { require_once DIR_SYSTEM . $p; }
require_once $mirror . '/admin/controller/common/header.php';
require_once $mirror . '/admin/controller/catalog/category.php';
require_once $mirror . '/admin/model/catalog/category.php';
define('DIR_APPLICATION', FilesFixture::init());

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
    public array $headers = [];
    public function addHeader(string $header): void { $this->headers[] = $header; }
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
    public array $taken = [];
    public int $sequence = 100;
    public int $adds = 0;
    public int $edits = 0;
    public int $deletes = 0;
    public function getLanguages(): array { return [['language_id'=>1,'name'=>'English','code'=>'en-us'],['language_id'=>2,'name'=>'Português','code'=>'pt-br'],['language_id'=>3,'name'=>'Español','code'=>'es-eu']]; }
    private function filtered(array $f): array {
        $rows = array_filter(array_values($this->records), static fn(array $r): bool => (($f['name']??'')==='' || mb_stripos($r['name'],$f['name'])!==false) && (!in_array($f['status']??'all',['0','1'],true) || $r['status']===(int)$f['status']));
        usort($rows, static fn(array $a,array $b): int => [$a['name'],$a['category_id']]<=>[$b['name'],$b['category_id']]); return array_values($rows);
    }
    public function getTotalCategories(array $f=[]): int { return count($this->filtered($f)); }
    public function getCategories(array $f=[]): array { $this->calls[]=$f; $rows=$this->filtered($f); return isset($f['limit'])?array_slice($rows,$f['start'],$f['limit']):$rows; }
    public function getCategory(int $id): array { return $this->records[$id]??[]; }
    public function getCategoryDescriptions(int $id): array { return $this->records[$id]['descriptions']??[]; }
    public function getCategoryKeywords(int $id): array { return array_map(static fn(array $d):string=>$d['keyword'],$this->getCategoryDescriptions($id)); }
    public function keywordTaken(string $k,int $language,int $exclude): bool { return in_array($k,$this->taken,true); }
    public function getParentOptions(int $id=0): array {
        $all=$this->getCategories(); if($id<=0)return $all; $banned=[$id=>true]; $found=true;
        while($found){$found=false;foreach($all as $r){if(!isset($banned[$r['category_id']])&&isset($banned[$r['parent_id']])){$banned[$r['category_id']]=true;$found=true;}}}
        return array_values(array_filter($all,static fn(array $r):bool=>!isset($banned[$r['category_id']])));
    }
    public function addCategory(array $data): int { $this->adds++;$id=++$this->sequence;$this->save($id,$data);return $id; }
    public function editCategory(int $id,array $data): void { $this->edits++;$this->save($id,$data); }
    private function save(int $id,array $data): void { $this->records[$id]=$data+['category_id'=>$id,'date_modified'=>'2026-10-23 12:00:00','parent_name'=>''];$this->records[$id]['name']=$data['descriptions'][1]['name']; }
    public function deleteCategory(int $id): void { $this->deletes++;foreach($this->records as &$r){if($r['parent_id']===$id)$r['parent_id']=0;}unset($r);unset($this->records[$id]); }
}
final class LoaderFixture {
    public array $data = [];
    private \Twig\Environment $twig;
    public function __construct(private string $mirror) {
        $this->twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader($mirror . '/admin/view/template'), ['cache' => false, 'autoescape' => 'html', 'strict_variables' => true]);
    }
    public function language(string $route): void { if ($route !== 'catalog/category') { throw new \RuntimeException('Rota de language inesperada'); } }
    public function model(string $route): void { if ($route !== 'catalog/category') { throw new \RuntimeException('Model inesperado'); } }
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
        $this->request = (object)['server' => ['REQUEST_METHOD' => 'GET'], 'get' => [], 'post' => [], 'files' => []];
        $this->response = new ResponseFixture(); $this->loader = new LoaderFixture($mirror); $this->document = new DocumentFixture();
        $this->language = new LanguageFixture($mirror . '/admin/language/' . $locale . '/catalog/category.php');
        foreach (['request' => $this->request, 'response' => $this->response, 'load' => $this->loader, 'document' => $this->document, 'language' => $this->language, 'config' => new ConfigFixture(), 'user' => new UserFixture($modify), 'session' => $this->session, 'url' => new UrlFixture(), 'model_catalog_category' => $this->store] as $key => $value) { $this->registry->set($key, $value); }
        $this->controller = new Controller($this->registry);
    }
    public function post(array $data): void { $this->request->server['REQUEST_METHOD'] = 'POST'; $this->request->post = $data; }
}
final class DbFixture {
    public array $queries=[];
    public array $rows=[['category_id'=>77,'parent_id'=>0,'name'=>'Fixture','image'=>'','status'=>0,'sort_order'=>0,'date_modified'=>'']];
    public function escape(string $value):string{return addslashes($value);}
    public function query(string $sql):\stdClass { $this->queries[]=$sql;if(str_contains($sql,'SELECT `language_id`'))return(object)['row'=>['language_id'=>1]];return(object)['row'=>['total'=>7],'rows'=>$this->rows]; }
}
final class Suite {
    private static int $passed=0;
    private static int $failed=0;
    private static string $mirror;
    private static function ok(bool $condition,string $message):void { if(!$condition)throw new \RuntimeException($message); }
    private static function test(string $label,callable $fn):void { try{$fn();self::$passed++;echo 'PASS '.$label.PHP_EOL;}catch(\Throwable $e){self::$failed++;fwrite(STDERR,'FAIL '.$label.': '.$e->getMessage().PHP_EOL);} }
    private static function payload(int $version=1):array {
        $out=['parent_id'=>0,'image'=>'','sort_order'=>7,'category_description'=>[]];
        foreach([1=>'English',2=>'Ação e edição',3=>'Español ñ'] as $id=>$text){$out['category_description'][$id]=['name'=>$text.' v'.$version,'description'=>'<h2>'.$text.'</h2><p>v'.$version.' & texto</p>','keyword'=>'f807-lang-'.$id.'-v'.$version,'meta_title'=>'Meta '.$text,'meta_description'=>'Descrição '.$text];}return $out;
    }
    private static function harness(?StoreFixture $store=null,?Session $session=null,string $locale='en-us',bool $modify=true):Harness{return new Harness(self::$mirror,$store,$session,$locale,$modify);}
    private static function populated(int $count):StoreFixture {
        $s=new StoreFixture();for($i=0;$i<$count;$i++){$p=self::payload();$p['status']=$i%2;$p['sort_order']=$count-$i;$p['descriptions']=$p['category_description'];$p['descriptions'][1]['name']=sprintf('Category %03d',$i+1);unset($p['category_description']);$s->addCategory($p);} $s->adds=0;return $s;
    }
    private static function model():array {$r=new Registry();$db=new DbFixture();$r->set('db',$db);$r->set('config',new ConfigFixture());return[new Model($r),$db];}
    private static function validPost(Harness $h,array $p):void {$h->post($p+['csrf_token'=>Csrf::token($h->session)]);}
    private static function json(Harness $h):array {return json_decode($h->response->output,true,512,JSON_THROW_ON_ERROR);}
    public static function run(string $mirror):int {
        self::$mirror=$mirror;
        self::test('01 · model sem argumentos: lista completa/alfabética',static function():void{[$m,$db]=self::model();$m->getCategories();$q=end($db->queries);self::ok(!preg_match('/LIMIT\s+\d+,\s*\d+\s*$/',$q)&&str_contains($q,"ORDER BY COALESCE(NULLIF(cd.`name`, ''), cd_en.`name`, '') ASC"),'Mudou o contrato sem LIMIT externo');});
        self::test('02 · SQL nome escapado/status zero/limite/tie-break',static function():void{[$m,$db]=self::model();$m->getCategories(['name'=>"O'Brien",'status'=>'0','start'=>20,'limit'=>20]);$q=end($db->queries);self::ok(str_contains($q,"O\\'Brien")&&str_contains($q,"c.`status` = '0'")&&str_ends_with($q,'c.`category_id` ASC LIMIT 20, 20'),'SQL de filtros incorreto');});
        self::test('03 · contagem/lista compartilham joins e predicados',static function():void{[$m,$db]=self::model();$f=['name'=>'x','status'=>'1'];self::ok($m->getTotalCategories($f)===7,'Total inválido');$count=end($db->queries);$m->getCategories($f);$list=end($db->queries);$marker='FROM `f807_category` c';$list=substr($list,strpos($list,$marker));$list=substr($list,0,strpos($list,' ORDER BY'));self::ok(substr($count,strpos($count,$marker))===$list,'Lista/contagem divergentes');});
        self::test('04 · status adulterado/limites normalizados/sem DDL',static function():void{[$m,$db]=self::model();$m->getCategories(['status'=>'1 OR 1=1','start'=>-20,'limit'=>999]);$q=end($db->queries);self::ok(!str_contains($q,'1 OR 1=1')&&str_ends_with($q,'LIMIT 0, 100')&&!preg_match('/DROP|ALTER|CREATE/i',$q),'Filtro inseguro');});
        self::test('05 · model real: descendentes fora da primeira página excluídos dos pais',static function():void{[$m,$db]=self::model();$db->rows=[];for($i=1;$i<=45;$i++)$db->rows[]=['category_id'=>$i,'parent_id'=>$i===36?1:($i===37?36:0),'name'=>'C'.$i];$options=$m->getParentOptions(1);$ids=array_column($options,'category_id');self::ok(!in_array(1,$ids,true)&&!in_array(36,$ids,true)&&!in_array(37,$ids,true)&&in_array(45,$ids,true),'Anti-ciclo foi limitado pela paginação');self::ok(!preg_match('/LIMIT\s+\d+,\s*\d+\s*$/',(string)end($db->queries)),'Dropdown paginado');});
        self::test('06 · delete do model mantém filhos raiz e limpa relações só do ID',static function():void{[$m,$db]=self::model();$m->deleteCategory(143);$sql=implode("\n",$db->queries);self::ok(str_contains($sql,"SET `parent_id` = '0' WHERE `parent_id` = '143'")&&str_contains($sql,"DELETE FROM `f807_project_to_category` WHERE `category_id` = '143'")&&!str_contains($sql,'DROP'),'Cascata/associações mudaram');});
        self::test('07 · índice vazio/CSRF/empty state',static function():void{$h=self::harness();$h->controller->index();self::ok($h->loader->data['total_categories']===0&&str_contains($h->response->output,'empty-state')&&strlen($h->loader->data['csrf_token'])===64,'Índice vazio inválido');});
        self::test('08 · 45 categorias: página dois contém vinte',static function():void{$h=self::harness(self::populated(45));$h->request->get['page']='2';$h->controller->index();self::ok(count($h->loader->data['categories'])===20&&$h->loader->data['total_categories']===45&&end($h->store->calls)['start']===20,'Paginação incorreta');});
        self::test('09 · página além do fim recua para a última',static function():void{$h=self::harness(self::populated(45));$h->request->get['page']=(string)PHP_INT_MAX;$h->controller->index();self::ok(count($h->loader->data['categories'])===5&&end($h->store->calls)['start']===40,'Recuo incorreto');});
        self::test('10 · query array/nome zero tratados sem warnings',static function():void{$h=self::harness();$h->request->get=['filter_name'=>[],'filter_status'=>[],'page'=>[]];$h->controller->index();self::ok($h->loader->data['filter_name']===''&&$h->loader->data['filter_status']==='all'&&end($h->store->calls)['start']===0,'Query não normalizada');$h=self::harness();$h->request->get['filter_name']='0';$h->controller->index();self::ok(str_contains($h->response->output,'>Reset</a>'),'Nome zero perdeu reset');});
        self::test('11 · filtro desabilitado e total correspondente',static function():void{$h=self::harness(self::populated(45));$h->request->get['filter_status']='0';$h->controller->index();self::ok($h->loader->data['total_categories']===23&&str_contains($h->response->output,'value="0" selected'),'Status zero perdido');});
        self::test('12 · paginação preserva nome/status sem escape duplo/XSS',static function():void{$s=self::populated(45);foreach($s->records as &$row)$row['name']="O'Brien & <tag>";unset($row);$h=self::harness($s);$h->request->get=['filter_name'=>"O'Brien & <tag>",'filter_status'=>'0'];$h->controller->index();$html=$h->loader->data['pagination'];self::ok(!str_contains($html,'&amp;amp;')&&!str_contains($h->response->output,'<tag>'),'Escape incorreto');preg_match_all('/href="([^"]+)"/',$html,$matches);self::ok(count($matches[1])>0,'Links ausentes');foreach($matches[1] as $attr){parse_str((string)parse_url(html_entity_decode($attr,ENT_QUOTES|ENT_HTML5,'UTF-8'),PHP_URL_QUERY),$query);self::ok(($query['filter_name']??'')==="O'Brien & <tag>"&&($query['filter_status']??'')==='0','Filtros perdidos');}});
        self::test('13 · readonly: sem ações modify e upload desabilitado',static function():void{$h=self::harness(self::populated(1),null,'en-us',false);$h->controller->index();self::ok(!str_contains($h->response->output,'data-rms-confirm-delete')&&!str_contains($h->response->output,'route=catalog/category.add'),'Ações vazaram');$h=self::harness(null,null,'en-us',false);$h->controller->add();self::ok(!str_contains($h->response->output,'type="submit"')&&str_contains($h->response->output,'id="btn-upload-cover" disabled'),'Readonly form incorreto');});
        self::test('14 · quinze IDs/names, parent/image/upload preservados, sem editor',static function():void{$h=self::harness();$h->controller->add();$html=$h->response->output;foreach([1,2,3] as $id)foreach(['name','keyword','description','meta-title','meta-description'] as $field){self::ok(substr_count($html,'id="input-'.$field.'-'.$id.'"')===1,'ID ausente/duplicado');$key=str_replace('-','_',$field);self::ok(str_contains($html,'name="category_description['.$id.']['.$key.']"'),'Name trocado pelo de outro CRUD');}self::ok(str_contains($html,'name="parent_id"')&&str_contains($html,'name="image"')&&str_contains($html,'categoryLangTabs')&&!str_contains($html,'data-rte')&&!str_contains($html,'<script>'),'Form/JS inline/editor mudou');preg_match('/data-category-config="([^"]+)"/',$html,$configMatch);$config=json_decode(html_entity_decode($configMatch[1]??'',ENT_QUOTES|ENT_HTML5,'UTF-8'),true,512,JSON_THROW_ON_ERROR);self::ok(str_contains($config['upload'],'route=catalog/category.upload')&&$config['catalog']==='http://example.test/catalog/'&&$config['wait']===$h->language->get('text_upload_wait'),'Config Twig/JS incompatível');});
        self::test('15 · assets versionados/chrome sem rms-dash',static function():void{$h=self::harness();$h->controller->add();self::ok(str_contains(implode('',$h->document->scripts),'catalog-category.js?v=')&&str_contains(implode('',$h->document->styles),'admin.css?v=')&&!isset($h->loader->data['body_class']),'Assets/chrome inválidos');});
        self::test('16 · i18n en/pt-BR/es com Twig strict',static function():void{$keys=null;foreach(['en-us','pt-br','es-eu'] as $locale){$h=self::harness(null,null,$locale);$h->controller->add();self::ok(str_contains($h->response->output,$h->language->get('heading_title')),'Heading não traduzido');$current=array_keys($h->language->values);sort($current);if($keys!==null)self::ok($current===$keys,'Chaves divergentes');$keys=$current;$h->controller->index();}});
        self::test('17 · add sem CSRF mantém valores e não muta',static function():void{$h=self::harness();$h->post(self::payload());$h->controller->add();self::ok($h->store->adds===0&&$h->loader->data['error']===$h->language->get('error_csrf')&&$h->loader->data['languages'][1]['fields']['name']==='Ação e edição v1','CSRF add inválido');});
        self::test('18 · token array/incorreto recusados',static function():void{foreach([[],'wrong'] as $token){$h=self::harness();$h->post(self::payload()+['csrf_token'=>$token]);$h->controller->add();self::ok($h->store->adds===0,'Token inválido aceito');}});
        self::test('19 · add válido conserva HTML/Unicode/contrato',static function():void{$h=self::harness();self::validPost($h,self::payload());$h->controller->add();$row=$h->store->getCategory(101);self::ok($h->store->adds===1&&$row['status']===0&&$row['parent_id']===0&&$row['sort_order']===7&&$row['descriptions'][3]['description']===self::payload()['category_description'][3]['description'],'Normalização perdida');});
        self::test('20 · nome EN obrigatório com token válido',static function():void{$h=self::harness();$p=self::payload();$p['category_description'][1]['name']='';self::validPost($h,$p);$h->controller->add();self::ok($h->store->adds===0&&$h->loader->data['error']===$h->language->get('error_name'),'Required EN perdido');});
        self::test('21 · SEO formato/unicidade preservados',static function():void{foreach(['BAD keyword!','occupied'] as $word){$h=self::harness();$h->store->taken=['occupied'];$p=self::payload();$p['category_description'][1]['keyword']=$word;self::validPost($h,$p);$h->controller->add();self::ok($h->store->adds===0&&$h->loader->data['error']!=='','Validação SEO perdida');}});
        self::test('22 · imagem fora do módulo recusada',static function():void{foreach(['../outside.png','view/image/other/f807.png','view/image/category/missing.png'] as $image){$h=self::harness();$p=self::payload();$p['image']=$image;self::validPost($h,$p);$h->controller->add();self::ok($h->store->adds===0&&str_contains($h->loader->data['error'],$h->language->get('error_image_path')),'Path externo/inexistente aceito');}});
        self::test('23 · MediaUpload guard real/preview em FS privado, não upload',static function():void{$image=FilesFixture::image('guard');$dimensions=getimagesize(FilesFixture::absolute($image));self::ok($dimensions[0]===2&&$dimensions[1]===2,'PNG fixture inválido');$h=self::harness();$p=self::payload();$p['image']=$image;self::validPost($h,$p);$h->controller->add();self::ok($h->store->getCategory(101)['image']===$image,'MediaUpload guard descartou PNG local');$get=self::harness($h->store,$h->session);$get->controller->edit('101');self::ok($get->loader->data['image_preview']==='http://example.test/catalog/'.$image,'Preview incorreto');});
        self::test('24 · pai válido preservado ao criar filho',static function():void{$h=self::harness(self::populated(1));$p=self::payload();$p['parent_id']=101;self::validPost($h,$p);$h->controller->add();self::ok($h->store->getCategory(102)['parent_id']===101,'Pai não persistiu na fixture');});
        self::test('25 · POST pai igual à própria categoria rejeitado',static function():void{$h=self::harness(self::populated(1));$p=self::payload(2);$p['parent_id']=101;self::validPost($h,$p);$h->controller->edit('101');self::ok($h->store->edits===0&&str_contains($h->loader->data['error'],$h->language->get('error_parent_cycle')),'Self-cycle aceito');});
        self::test('26 · POST pai descendente rejeitado',static function():void{$s=self::populated(3);$s->records[102]['parent_id']=101;$s->records[103]['parent_id']=102;$h=self::harness($s);$p=self::payload(2);$p['parent_id']=103;self::validPost($h,$p);$h->controller->edit('101');self::ok($s->edits===0&&$s->getCategory(101)['parent_id']===0,'Ciclo com neto aceito');});
        self::test('27 · pai inexistente rejeitado',static function():void{$h=self::harness();$p=self::payload();$p['parent_id']=99999;self::validPost($h,$p);$h->controller->add();self::ok($h->store->adds===0&&str_contains($h->loader->data['error'],$h->language->get('error_parent_cycle')),'Pai inexistente aceito');});
        self::test('28 · dropdown de criação inclui todas as 45 categorias',static function():void{$h=self::harness(self::populated(45));$h->controller->add();self::ok(count($h->loader->data['parents'])===45&&str_contains($h->response->output,'value="145"'),'Dropdown limitado aos 20 itens da grade');});
        self::test('29 · edit sem CSRF não muta',static function():void{$h=self::harness(self::populated(1));$h->post(self::payload(2));$h->controller->edit('101');self::ok($h->store->edits===0&&$h->store->getCategory(101)['descriptions'][1]['name']==='Category 001','CSRF edit falhou');});
        self::test('30 · edit válido mesmo ID troca versão/SEO/status',static function():void{$h=self::harness(self::populated(1));self::validPost($h,self::payload(2)+['status'=>'1']);$h->controller->edit('101');$row=$h->store->getCategory(101);self::ok($h->store->edits===1&&$row['status']===1&&$row['descriptions'][3]['keyword']==='f807-lang-3-v2','Edit inválido');});
        self::test('31 · GET delete não apaga registro/arquivo da fixture',static function():void{$s=self::populated(1);$image=FilesFixture::image('get-delete');$s->records[101]['image']=$image;$h=self::harness($s);$h->controller->delete('101');self::ok($s->deletes===0&&file_exists(FilesFixture::absolute($image))&&str_contains($h->response->location,'error/permission'),'GET delete mutou');});
        self::test('32 · delete sem CSRF não apaga imagem nem registro',static function():void{$s=self::populated(1);$image=FilesFixture::image('csrf-delete');$s->records[101]['image']=$image;$h=self::harness($s);$h->post([]);$h->controller->delete('101');self::ok($s->deletes===0&&file_exists(FilesFixture::absolute($image))&&($h->session->data['flash']['type']??'')==='error','CSRF delete permitiu unlink');});
        self::test('33 · delete válido: imagem própria e filhos raiz em FS/memória fixtures',static function():void{$s=self::populated(3);$image=FilesFixture::image('own-delete');$keep=FilesFixture::image('keep');$s->records[101]['image']=$image;$s->records[102]['parent_id']=101;$s->records[103]['image']=$keep;$h=self::harness($s);self::validPost($h,[]);$h->controller->delete('101');self::ok($s->deletes===1&&!$s->getCategory(101)&&$s->getCategory(102)['parent_id']===0&&!file_exists(FilesFixture::absolute($image))&&file_exists(FilesFixture::absolute($keep)),'Delete/cascata afetou outro arquivo/registro');});
        self::test('34 · modify negado bloqueia add/edit/delete/upload',static function():void{foreach(['add','edit','delete','upload'] as $method){$h=self::harness(self::populated(1),null,'en-us',false);self::validPost($h,self::payload(2));if($method==='add'||$method==='upload')$h->controller->{$method}();else $h->controller->{$method}('101');self::ok($h->store->adds+$h->store->edits+$h->store->deletes===0,'ACL mutou');if($method==='upload')self::ok(self::json($h)['error']===$h->language->get('error_permission'),'ACL upload não foi verificada');else self::ok(str_contains($h->response->location,'error/permission'),'Redirect ACL mudou');}});
        self::test('35 · upload sem CSRF/array/incorreto recusa antes de MediaUpload',static function():void{foreach([null,[],'wrong'] as $token){$h=self::harness();$h->post($token===null?[]:['csrf_token'=>$token]);$h->controller->upload();$j=self::json($h);self::ok($j['success']===false&&$j['path']===''&&$j['error']===$h->language->get('error_csrf'),'CSRF de upload ausente');}});
        self::test('36 · upload GET/no-file válido mantém JSON/MediaUpload originais',static function():void{$h=self::harness();$h->controller->upload();self::ok(self::json($h)['error']===$h->language->get('error_upload_method'),'GET upload mudou');$h=self::harness();self::validPost($h,[]);$h->controller->upload();$j=self::json($h);self::ok($j['success']===false&&$j['path']===''&&$j['error']===$h->language->get('error_upload')&&str_contains(implode('',$h->response->headers),'application/json'),'Contrato JSON/no-file mudou');});
        self::test('37 · edit inexistente redireciona not-found',static function():void{$h=self::harness();$h->controller->edit('999');self::ok(str_contains($h->response->location,'error/not_found')&&$h->store->edits===0,'404 mudou');});
        self::test('38 · roundtrip em memória mantém cinco campos por idioma',static function():void{$h=self::harness();self::validPost($h,self::payload());$h->controller->add();$get=self::harness($h->store,$h->session);$get->controller->edit('101');foreach($get->loader->data['languages'] as $lang){$expected=self::payload()['category_description'][$lang['language_id']];foreach($expected as $key=>$value)self::ok(($lang['fields'][$key]??null)===$value,'Dados multilíngues perdidos: '.$key);}self::ok(!str_contains($get->response->output,'<h2>Español'),'HTML renderizado dentro do textarea');});
        self::test('39 · nome/keyword maliciosos escapados no form',static function():void{$h=self::harness();$p=self::payload();$p['category_description'][1]['name']='<script>alert(1)</script>';$p['category_description'][1]['keyword']='BAD quote"';self::validPost($h,$p);$h->controller->add();self::ok(!str_contains($h->response->output,'<script>alert')&&str_contains($h->response->output,'&lt;script&gt;'),'Escape perdido');});
        self::test('40 · confirmação via modal/data, sem JS inline',static function():void{$h=self::harness(self::populated(1));$h->controller->index();self::ok(str_contains($h->response->output,'data-rms-confirm-delete=')&&!str_contains($h->response->output,'onsubmit=')&&substr_count($h->response->output,'name="csrf_token"')===1,'Contrato modal/CSRF inválido');});
        echo "\nF8-07 contratos: ".self::$passed.' PASS / '.self::$failed.' FAIL — PHP '.PHP_VERSION.', Twig '.\Twig\Environment::VERSION."; DB/serviços/IO sessão em fixtures, MediaUpload/FS privados não servidos; sem upload/HTTP/navegador reais.\n";
        return self::$failed?1:0;
    }
}
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool{if(!(error_reporting()&$severity))return false;throw new \ErrorException($message,0,$severity,$file,$line);});
try{$code=Suite::run($mirror);}finally{FilesFixture::cleanup();}
exit($code);
