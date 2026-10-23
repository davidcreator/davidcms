<?php declare(strict_types=1);
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS
 * @license https://opensource.org/licenses/GPL-3.0
 * F8-08: PHP/Twig reais; DB/serviços/IO de sessão simulados.
 * MediaUpload real só em FS privado não servido; não faz upload HTTP/move.
 */
namespace Reamur\Tests\F808;

use Reamur\System\Engine\Registry;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Session;
use Reamur\Admin\Controller\Catalog\Project as Controller;
use Reamur\Admin\Model\Catalog\Project as Model;

final class FilesFixture {
    private static string $root = '';
    private static array $files = [];
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR4nGOQqgj4D8IMMAYAPqgHhR+OGvoAAAAASUVORK5CYII=';
    public static function init(): string {
        self::$root = sys_get_temp_dir() . '/f808-qa-' . bin2hex(random_bytes(8));
        if (!mkdir(self::$root, 0700)) { throw new \RuntimeException('Não foi possível criar fixture privada.'); }
        foreach (['admin', 'catalog', 'catalog/view', 'catalog/view/image', 'catalog/view/image/project'] as $p) {
            if (!mkdir(self::$root . '/' . $p, 0700)) { throw new \RuntimeException('Falha na fixture privada.'); }
        }
        return self::$root . '/admin/';
    }
    public static function image(string $name): string {
        if (!preg_match('/^[a-z0-9-]+$/', $name)) { throw new \RuntimeException('Nome da fixture inválido'); }
        $relative = 'view/image/project/' . $name . '.png';
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
        foreach (['catalog/view/image/project', 'catalog/view/image', 'catalog/view', 'catalog', 'admin', ''] as $p) {
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
if (!defined('DB_PREFIX')) { define('DB_PREFIX', 'f808_'); }
if (!defined('DIR_SYSTEM')) { define('DIR_SYSTEM', $mirror . '/system/'); }
foreach (['engine/registry.php', 'engine/controller.php', 'engine/model.php', 'library/session.php', 'library/security/csrf.php', 'library/pagination.php', 'library/media_upload.php'] as $p) { require_once DIR_SYSTEM . $p; }
require_once $mirror . '/admin/controller/common/header.php';
require_once $mirror . '/admin/controller/catalog/project.php';
require_once $mirror . '/admin/model/catalog/project.php';
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
    public array $records=[]; public array $calls=[]; public array $taken=[];
    public array $categoryOptions=[['category_id'=>5,'name'=>'Fixture category']];
    public int $sequence=100;public int $adds=0;public int $edits=0;public int $deletes=0;
    public function getLanguages():array{return [['language_id'=>1,'name'=>'English','code'=>'en-us'],['language_id'=>2,'name'=>'Português','code'=>'pt-br'],['language_id'=>3,'name'=>'Español','code'=>'es-eu']];}
    public function getCategories():array{return $this->categoryOptions;}
    private function filtered(array $f):array {
        $rows=array_filter(array_values($this->records),static function(array $r)use($f):bool{
            if(($f['name']??'')!==''&&mb_stripos($r['name'],$f['name'])===false)return false;
            foreach(['status','featured']as$key)if(in_array($f[$key]??'all',['0','1'],true)&&$r[$key]!== (int)$f[$key])return false;
            return true;
        });
        usort($rows,static fn(array $a,array $b):int=>[$a['sort_order'],-$a['project_id']]<=>[$b['sort_order'],-$b['project_id']]);return array_values($rows);
    }
    public function getTotalProjects(array $f=[]):int{return count($this->filtered($f));}
    public function getProjects(array $f=[]):array{$this->calls[]=$f;$rows=$this->filtered($f);return isset($f['limit'])?array_slice($rows,$f['start'],$f['limit']):$rows;}
    public function getProject(int $id):array{return $this->records[$id]??[];}
    public function getProjectDescriptions(int $id):array{return $this->records[$id]['descriptions']??[];}
    public function getProjectKeywords(int $id):array{return array_map(static fn(array $d):string=>$d['keyword'],$this->getProjectDescriptions($id));}
    public function getProjectCategories(int $id):array{return $this->records[$id]['categories']??[];}
    public function getProjectImages(int $id):array{
        $rows=$this->records[$id]['images']??[];foreach($rows as$i=>&$row)$row['project_image_id']=$i+1;unset($row);
        usort($rows,static fn(array $a,array $b):int=>[$a['sort_order'],$a['project_image_id']]<=>[$b['sort_order'],$b['project_image_id']]);return $rows;
    }
    public function keywordTaken(string $k,int $language,int $exclude):bool{return in_array($k,$this->taken,true);}
    public function addProject(array $data):int{$this->adds++;$id=++$this->sequence;$this->save($id,$data);return $id;}
    public function editProject(int $id,array $data):void{$this->edits++;$this->save($id,$data);}
    private function save(int $id,array $data):void{
        $data['images']=array_values(array_filter($data['images'],static fn(array $r):bool=>trim($r['image'])!==''));
        $data['categories']=array_values(array_filter($data['categories'],static fn(int $id):bool=>$id>0));
        $this->records[$id]=$data+['project_id'=>$id,'date_modified'=>'2026-10-23 12:00:00'];$this->records[$id]['name']=$data['descriptions'][1]['name'];
    }
    public function deleteProject(int $id):void{$this->deletes++;unset($this->records[$id]);}
}
final class LoaderFixture {
    public array $data = [];
    private \Twig\Environment $twig;
    public function __construct(private string $mirror) {
        $this->twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader($mirror . '/admin/view/template'), ['cache' => false, 'autoescape' => 'html', 'strict_variables' => true]);
    }
    public function language(string $route): void { if ($route !== 'catalog/project') { throw new \RuntimeException('Rota de language inesperada'); } }
    public function model(string $route): void { if ($route !== 'catalog/project') { throw new \RuntimeException('Model inesperado'); } }
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
        $this->language = new LanguageFixture($mirror . '/admin/language/' . $locale . '/catalog/project.php');
        foreach (['request' => $this->request, 'response' => $this->response, 'load' => $this->loader, 'document' => $this->document, 'language' => $this->language, 'config' => new ConfigFixture(), 'user' => new UserFixture($modify), 'session' => $this->session, 'url' => new UrlFixture(), 'model_catalog_project' => $this->store] as $key => $value) { $this->registry->set($key, $value); }
        $this->controller = new Controller($this->registry);
    }
    public function post(array $data): void { $this->request->server['REQUEST_METHOD'] = 'POST'; $this->request->post = $data; }
}
final class DbFixture {
    public array $queries=[]; public array $rows=[['project_id'=>77,'image'=>'','client'=>'','featured'=>0,'status'=>0,'sort_order'=>0,'name'=>'Fixture','date_modified'=>'']];
    public function escape(string $value):string{return addslashes($value);}
    public function query(string $sql):\stdClass{$this->queries[]=$sql;if(str_contains($sql,'SELECT `language_id`'))return(object)['row'=>['language_id'=>1]];return(object)['row'=>['total'=>7],'rows'=>$this->rows];}
}
final class Suite {
    private static int $passed=0;private static int $failed=0;private static string $mirror;
    private static function ok(bool $test,string $message):void{if(!$test)throw new \RuntimeException($message);}
    private static function test(string $label,callable $fn):void{try{$fn();self::$passed++;echo 'PASS '.$label.PHP_EOL;}catch(\Throwable $e){self::$failed++;fwrite(STDERR,'FAIL '.$label.': '.$e->getMessage().PHP_EOL);}}
    private static function payload(int $version=1):array{
        $out=['client'=>'Fixture client v'.$version,'url'=>'https://example.invalid/f808/v'.$version,'image'=>'','sort_order'=>7,'project_category'=>[],'project_image'=>[],'project_description'=>[]];
        foreach([1=>'English',2=>'Ação e edição',3=>'Español ñ']as$id=>$text){$out['project_description'][$id]=['name'=>$text.' v'.$version,'summary'=>'Resumo '.$text,'description'=>'<h2>'.$text.'</h2><p>v'.$version.' & texto</p>','tag'=>'one, dois, '.$text,'keyword'=>'f808-lang-'.$id.'-v'.$version,'meta_title'=>'Meta '.$text,'meta_description'=>'Descrição '.$text];}return $out;
    }
    private static function harness(?StoreFixture $store=null,?Session $session=null,string $locale='en-us',bool $modify=true):Harness{return new Harness(self::$mirror,$store,$session,$locale,$modify);}
    private static function populated(int $count):StoreFixture{
        $s=new StoreFixture();for($i=0;$i<$count;$i++){$p=self::payload();$p['status']=$i%2;$p['featured']=((int)floor($i/2))%2;$p['sort_order']=$i;$p['descriptions']=$p['project_description'];$p['images']=[];$p['categories']=[];$p['descriptions'][1]['name']=sprintf('Project %03d',$i+1);unset($p['project_description'],$p['project_image'],$p['project_category']);$s->addProject($p);} $s->adds=0;return $s;
    }
    private static function model():array{$r=new Registry();$db=new DbFixture();$r->set('db',$db);$r->set('config',new ConfigFixture());return[new Model($r),$db];}
    private static function valid(Harness $h,array $p):void{$h->post($p+['csrf_token'=>Csrf::token($h->session)]);}
    private static function json(Harness $h):array{return json_decode($h->response->output,true,512,JSON_THROW_ON_ERROR);}
    private static function withImages(StoreFixture $s,string $stem):array{
        $cover=FilesFixture::image($stem.'-cover');$a=FilesFixture::image($stem.'-a');$b=FilesFixture::image($stem.'-b');
        $s->records[101]['image']=$cover;$s->records[101]['images']=[['image'=>$a,'alt'=>'A','sort_order'=>9],['image'=>$b,'alt'=>'B','sort_order'=>2]];return[$cover,$a,$b];
    }
    public static function run(string $mirror):int{
        self::$mirror=$mirror;
        self::test('01 · model sem argumentos: tudo, sort ASC/id DESC',static function():void{[$m,$db]=self::model();$m->getProjects();$q=end($db->queries);self::ok(str_ends_with($q,'ORDER BY p.`sort_order` ASC, p.`project_id` DESC')&&!str_contains($q,' LIMIT '),'Contrato/ordem alterados');});
        self::test('02 · nome/status/featured escapados com recorte',static function():void{[$m,$db]=self::model();$m->getProjects(['name'=>"O'Brien",'status'=>'0','featured'=>'0','start'=>20,'limit'=>20]);$q=end($db->queries);self::ok(str_contains($q,"O\\'Brien")&&str_contains($q,"p.`status` = '0'")&&str_contains($q,"p.`featured` = '0'")&&str_ends_with($q,'LIMIT 20, 20'),'Filtros SQL incorretos');});
        self::test('03 · count/list iguais e limites/status normalizados',static function():void{[$m,$db]=self::model();$f=['name'=>'x','status'=>'1','featured'=>'0'];self::ok($m->getTotalProjects($f)===7,'Total incorreto');$count=end($db->queries);$m->getProjects($f);$q=end($db->queries);$q=substr($q,strpos($q,'FROM '));$q=substr($q,0,strpos($q,' ORDER BY'));self::ok(substr($count,strpos($count,'FROM '))===$q,'Predicados divergentes');$m->getProjects(['status'=>'1 OR 1=1','featured'=>[],'start'=>-1,'limit'=>999]);$q=end($db->queries);self::ok(!str_contains($q,'1 OR 1=1')&&str_ends_with($q,'LIMIT 0, 100'),'Entrada malformada não normalizada');});
        self::test('04 · model real: categorias completas sem LIMIT da grade',static function():void{[$m,$db]=self::model();$db->rows=[];for($i=1;$i<=45;$i++)$db->rows[]=['category_id'=>$i,'name'=>'C'.$i];$rows=$m->getCategories();self::ok(count($rows)===45&&!str_contains((string)end($db->queries),'LIMIT '),'Checkbox N:N ficou paginado');});
        self::test('05 · galeria do model conserva sort/id ASC',static function():void{[$m,$db]=self::model();$m->getProjectImages(143);self::ok(str_contains((string)end($db->queries),"WHERE `project_id` = '143' ORDER BY `sort_order` ASC, `project_image_id` ASC"),'Ordem da galeria alterada');});
        self::test('06 · model delete limpa somente filhos/relações do projeto',static function():void{[$m,$db]=self::model();$m->deleteProject(143);$sql=implode("\n",$db->queries);self::ok(str_contains($sql,"DELETE FROM `f808_project_image` WHERE `project_id` = '143'")&&str_contains($sql,"DELETE FROM `f808_project_to_category` WHERE `project_id` = '143'")&&!str_contains($sql,'DELETE FROM `f808_category`')&&!str_contains($sql,'DROP'),'Cascata afetou categorias/schema');});
        self::test('07 · índice vazio/CSRF/empty state',static function():void{$h=self::harness();$h->controller->index();self::ok($h->loader->data['total_projects']===0&&str_contains($h->response->output,'empty-state')&&strlen($h->loader->data['csrf_token'])===64,'Índice vazio incorreto');});
        self::test('08 · 45 projetos: página dois vinte registros',static function():void{$h=self::harness(self::populated(45));$h->request->get['page']='2';$h->controller->index();self::ok(count($h->loader->data['projects'])===20&&end($h->store->calls)['start']===20&&$h->loader->data['total_projects']===45,'Recorte incorreto');});
        self::test('09 · página além do fim e query array não quebram',static function():void{$h=self::harness(self::populated(45));$h->request->get['page']=(string)PHP_INT_MAX;$h->controller->index();self::ok(count($h->loader->data['projects'])===5&&end($h->store->calls)['start']===40,'Recuo incorreto');$h=self::harness();$h->request->get=['filter_name'=>[],'filter_status'=>[],'filter_featured'=>[],'page'=>[]];$h->controller->index();self::ok($h->loader->data['filter_name']===''&&$h->loader->data['filter_status']==='all'&&$h->loader->data['filter_featured']==='all','Arrays não normalizados');});
        self::test('10 · filtro nome zero conserva reset',static function():void{$h=self::harness();$h->request->get['filter_name']='0';$h->controller->index();self::ok(str_contains($h->response->output,'>Reset</a>'),'Nome zero perdeu reset');});
        self::test('11 · status zero/featured zero combinados',static function():void{$h=self::harness(self::populated(45));$h->request->get=['filter_status'=>'0','filter_featured'=>'0'];$h->controller->index();self::ok($h->loader->data['total_projects']===12,'Total combinado incorreto');});
        self::test('12 · pager mantém três filtros, sem amp;amp/XSS',static function():void{$s=self::populated(45);foreach($s->records as&$row){$row['name']="O'Brien & <tag>";$row['featured']=0;}unset($row);$h=self::harness($s);$h->request->get=['filter_name'=>"O'Brien & <tag>",'filter_status'=>'0','filter_featured'=>'0'];$h->controller->index();$html=$h->loader->data['pagination'];self::ok(!str_contains($html,'&amp;amp;')&&!str_contains($h->response->output,'<tag>'),'Escape incorreto');preg_match_all('/href="([^"]+)"/',$html,$matches);self::ok(count($matches[1])>0,'Links ausentes');foreach($matches[1]as$attr){parse_str((string)parse_url(html_entity_decode($attr,ENT_QUOTES|ENT_HTML5,'UTF-8'),PHP_URL_QUERY),$query);self::ok(($query['filter_name']??'')==="O'Brien & <tag>"&&($query['filter_status']??'')==='0'&&($query['filter_featured']??'')==='0','Filtro perdido');}});
        self::test('13 · readonly esconde modify/desabilita uploads',static function():void{$h=self::harness(self::populated(1),null,'en-us',false);$h->controller->index();self::ok(!str_contains($h->response->output,'data-rms-confirm-delete')&&!str_contains($h->response->output,'route=catalog/project.add'),'Ações vazaram');$h=self::harness(null,null,'en-us',false);$h->controller->add();self::ok(!str_contains($h->response->output,'type="submit"')&&str_contains($h->response->output,'id="btn-add-image" disabled')&&str_contains($h->response->output,'data-preview="cover-preview" disabled'),'Readonly incorreto');});
        self::test('14 · 21 IDs/names e JSON de upload/labels, sem editor/inline JS',static function():void{$h=self::harness();$h->controller->add();$html=$h->response->output;foreach([1,2,3]as$id)foreach(['name','summary','description','tag','keyword','meta-title','meta-description']as$field){self::ok(substr_count($html,'id="input-'.$field.'-'.$id.'"')===1,'ID perdido/duplicado');self::ok(str_contains($html,'name="project_description['.$id.']['.str_replace('-','_',$field).']"'),'Name incorreto');}self::ok(!str_contains($html,'<script>')&&!str_contains($html,'data-rte')&&str_contains($html,'projectLangTabs'),'Editor/inline indevido');preg_match('/data-project-config="([^"]+)"/',$html,$m);$c=json_decode(html_entity_decode($m[1]??'',ENT_QUOTES|ENT_HTML5,'UTF-8'),true,512,JSON_THROW_ON_ERROR);self::ok(str_contains($c['upload'],'route=catalog/project.upload')&&$c['labels']['alt']===$h->language->get('entry_alt')&&$c['labels']['remove']===$h->language->get('button_remove'),'Config JS não traduzido/compatível');});
        self::test('15 · assets versionados/chrome sem rms-dash',static function():void{$h=self::harness();$h->controller->add();self::ok(str_contains(implode('',$h->document->scripts),'catalog-project.js?v=')&&str_contains(implode('',$h->document->styles),'admin.css?v=')&&!isset($h->loader->data['body_class']),'Assets/chrome incorretos');});
        self::test('16 · en/pt-BR/es com mesmas chaves e Twig strict',static function():void{$keys=null;foreach(['en-us','pt-br','es-eu']as$locale){$h=self::harness(null,null,$locale);$h->controller->add();self::ok(str_contains($h->response->output,$h->language->get('heading_title')),'Heading não traduzido');$k=array_keys($h->language->values);sort($k);if($keys!==null)self::ok($k===$keys,'Chaves i18n divergentes');$keys=$k;$h->controller->index();}});
        self::test('17 · add sem CSRF não muta e mantém dados',static function():void{$h=self::harness();$h->post(self::payload());$h->controller->add();self::ok($h->store->adds===0&&$h->loader->data['error']===$h->language->get('error_csrf')&&$h->loader->data['languages'][1]['fields']['summary']==='Resumo Ação e edição','CSRF add falhou');});
        self::test('18 · token array/incorreto recusados',static function():void{foreach([[],'wrong']as$t){$h=self::harness();$h->post(self::payload()+['csrf_token'=>$t]);$h->controller->add();self::ok($h->store->adds===0,'Token inválido aceito');}});
        self::test('19 · add válido conserva sete campos/cliente/URL/flags',static function():void{$h=self::harness();self::valid($h,self::payload());$h->controller->add();$row=$h->store->getProject(101);self::ok($row['status']===0&&$row['featured']===0&&$row['sort_order']===7&&$row['client']==='Fixture client v1'&&$row['url']==='https://example.invalid/f808/v1'&&$row['descriptions'][3]['tag']==='one, dois, Español ñ','Contrato normalizado alterado');});
        self::test('20 · nome EN obrigatório com CSRF válido',static function():void{$h=self::harness();$p=self::payload();$p['project_description'][1]['name']='';self::valid($h,$p);$h->controller->add();self::ok($h->store->adds===0&&$h->loader->data['error']===$h->language->get('error_name'),'Required EN perdido');});
        self::test('21 · URL inválida rejeitada sem mudar regra existente',static function():void{$h=self::harness();$p=self::payload();$p['url']='not a url';self::valid($h,$p);$h->controller->add();self::ok($h->store->adds===0&&str_contains($h->loader->data['error'],$h->language->get('error_url')),'Validação URL perdida');});
        self::test('22 · formato/unicidade SEO preservados',static function():void{foreach(['BAD keyword!','occupied']as$word){$h=self::harness();$h->store->taken=['occupied'];$p=self::payload();$p['project_description'][1]['keyword']=$word;self::valid($h,$p);$h->controller->add();self::ok($h->store->adds===0&&$h->loader->data['error']!=='','Validação SEO perdida');}});
        self::test('23 · capa fora do módulo/inexistente rejeitada',static function():void{foreach(['../outside.png','view/image/category/wrong.png','view/image/project/missing.png']as$image){$h=self::harness();$p=self::payload();$p['image']=$image;self::valid($h,$p);$h->controller->add();self::ok($h->store->adds===0&&str_contains($h->loader->data['error'],$h->language->get('error_image_path')),'Path de capa aceito indevidamente');}});
        self::test('24 · capa/galeria MediaUpload guard real em FS privado',static function():void{$cover=FilesFixture::image('preview-cover');$a=FilesFixture::image('preview-a');$h=self::harness();$p=self::payload();$p['image']=$cover;$p['project_image']=[['image'=>$a,'alt'=>'Ação & <alt>','sort_order'=>3]];self::valid($h,$p);$h->controller->add();$get=self::harness($h->store,$h->session);$get->controller->edit('101');self::ok($get->loader->data['image_preview']==='http://example.test/catalog/'.$cover&&$get->loader->data['images'][0]['preview']==='http://example.test/catalog/'.$a,'Preview guard incorreto');});
        self::test('25 · paths inválidos da galeria descartados como na base',static function():void{$h=self::harness();$p=self::payload();$p['project_image']=[['image'=>'../wrong.png','alt'=>'wrong','sort_order'=>0]];self::valid($h,$p);$h->controller->add();self::ok($h->store->adds===1&&$h->store->getProjectImages(101)===[],'Mudou a semântica original de galeria inválida');});
        self::test('26 · limites de normalização cliente/summary/tag preservados',static function():void{$h=self::harness();$p=self::payload();$p['client']=str_repeat('ç',200);$p['project_description'][2]['summary']=str_repeat('ã',300);$p['project_description'][2]['tag']=str_repeat('ñ',300);self::valid($h,$p);$h->controller->add();$r=$h->store->getProject(101);self::ok(mb_strlen($r['client'])===128&&mb_strlen($r['descriptions'][2]['summary'])===255&&mb_strlen($r['descriptions'][2]['tag'])===255,'Truncamento alterado');});
        self::test('27 · todas as 45 categorias nos checkboxes',static function():void{$s=new StoreFixture();$s->categoryOptions=[];for($i=1;$i<=45;$i++)$s->categoryOptions[]=['category_id'=>$i,'name'=>'C'.$i];$h=self::harness($s);$h->controller->add();self::ok(count($h->loader->data['categories'])===45&&substr_count($h->response->output,'name="project_category[]"')===45,'Categorias foram limitadas pela grade');});
        self::test('28 · relação N:N selecionada persiste em fixture',static function():void{$s=new StoreFixture();$s->categoryOptions=[['category_id'=>5,'name'=>'A'],['category_id'=>12,'name'=>'B']];$h=self::harness($s);$p=self::payload();$p['project_category']=[5,12];self::valid($h,$p);$h->controller->add();$get=self::harness($s,$h->session);$get->controller->edit('101');self::ok($s->getProjectCategories(101)===[5,12]&&$get->loader->data['categories'][0]['checked']&&$get->loader->data['categories'][1]['checked'],'Vínculo N:N perdido');});
        self::test('29 · edit sem token não altera imagens/dados',static function():void{$s=self::populated(1);[$cover,$a,$b]=self::withImages($s,'edit-csrf');$h=self::harness($s);$h->post(self::payload(2));$h->controller->edit('101');self::ok($s->edits===0&&$s->getProject(101)['image']===$cover&&file_exists(FilesFixture::absolute($a)),'CSRF edit alterou estado');});
        self::test('30 · edit válido flags on/off no mesmo ID',static function():void{$s=self::populated(1);$h=self::harness($s);self::valid($h,self::payload(2)+['status'=>'1','featured'=>'1']);$h->controller->edit('101');self::ok($s->getProject(101)['status']===1&&$s->getProject(101)['featured']===1,'Flags on perdidas');$h=self::harness($s);self::valid($h,self::payload(3));$h->controller->edit('101');self::ok($s->getProject(101)['status']===0&&$s->getProject(101)['featured']===0,'Unchecked não desativou');});
        self::test('31 · galeria preserva alt/ordem e reabre por sort',static function():void{$a=FilesFixture::image('ordered-a');$b=FilesFixture::image('ordered-b');$h=self::harness();$p=self::payload();$p['project_image']=[['image'=>$a,'alt'=>'Ação A','sort_order'=>9],['image'=>$b,'alt'=>'B & texto','sort_order'=>2]];self::valid($h,$p);$h->controller->add();$get=self::harness($h->store,$h->session);$get->controller->edit('101');$rows=$get->loader->data['images'];self::ok($rows[0]['image']===$b&&$rows[0]['alt']==='B & texto'&&$rows[1]['sort_order']==='9','Ordem/alt de galeria perdidos');});
        self::test('32 · remover associação não apaga fisicamente órfão (contrato antigo)',static function():void{$s=self::populated(1);[$cover,$a,$b]=self::withImages($s,'removed-row');$h=self::harness($s);$p=self::payload(2);$p['image']=$cover;$p['project_image']=[['image'=>$b,'alt'=>'B','sort_order'=>0]];self::valid($h,$p);$h->controller->edit('101');self::ok(count($s->getProjectImages(101))===1&&file_exists(FilesFixture::absolute($a)),'Foi inventada limpeza física na edição');});
        self::test('33 · GET delete não remove projeto/capa/galeria',static function():void{$s=self::populated(1);[$cover,$a,$b]=self::withImages($s,'get-delete');$h=self::harness($s);$h->controller->delete('101');self::ok($s->deletes===0&&file_exists(FilesFixture::absolute($cover))&&file_exists(FilesFixture::absolute($b))&&str_contains($h->response->location,'error/permission'),'GET delete mutou');});
        self::test('34 · delete sem CSRF não chega ao unlink',static function():void{$s=self::populated(1);[$cover,$a,$b]=self::withImages($s,'bad-delete');$h=self::harness($s);$h->post([]);$h->controller->delete('101');self::ok($s->deletes===0&&file_exists(FilesFixture::absolute($cover))&&file_exists(FilesFixture::absolute($a))&&($h->session->data['flash']['type']??'')==='error','CSRF delete falhou');});
        self::test('35 · delete válido só arquivos/registro próprios, categorias ficam',static function():void{$s=self::populated(2);[$cover,$a,$b]=self::withImages($s,'own-delete');$keep=FilesFixture::image('keep');$s->records[102]['image']=$keep;$h=self::harness($s);self::valid($h,[]);$h->controller->delete('101');self::ok(!$s->getProject(101)&&$s->getProject(102)!==[]&&!file_exists(FilesFixture::absolute($cover))&&!file_exists(FilesFixture::absolute($a))&&!file_exists(FilesFixture::absolute($b))&&file_exists(FilesFixture::absolute($keep))&&count($s->categoryOptions)===1,'Delete afetou dados/arquivos alheios');});
        self::test('36 · ACL modify negada nos quatro métodos',static function():void{foreach(['add','edit','delete','upload']as$m){$h=self::harness(self::populated(1),null,'en-us',false);self::valid($h,self::payload(2));if($m==='add'||$m==='upload')$h->controller->{$m}();else $h->controller->{$m}('101');self::ok($h->store->adds+$h->store->edits+$h->store->deletes===0,'ACL mutou');if($m==='upload')self::ok(self::json($h)['error']===$h->language->get('error_permission'),'ACL upload mudou');else self::ok(str_contains($h->response->location,'error/permission'),'Redirect ACL mudou');}});
        self::test('37 · upload sem token/array/errado recusa por CSRF',static function():void{foreach([null,[],'wrong']as$t){$h=self::harness();$h->post($t===null?[]:['csrf_token'=>$t]);$h->controller->upload();$j=self::json($h);self::ok($j['success']===false&&$j['path']===''&&$j['error']===$h->language->get('error_csrf'),'CSRF upload não conferido');}});
        self::test('38 · upload GET/no-file mantém JSON original',static function():void{$h=self::harness();$h->controller->upload();self::ok(self::json($h)['error']===$h->language->get('error_upload_method'),'GET upload mudou');$h=self::harness();self::valid($h,[]);$h->controller->upload();$j=self::json($h);self::ok($j['success']===false&&$j['error']===$h->language->get('error_upload')&&str_contains(implode('',$h->response->headers),'application/json'),'No-file/JSON mudou');});
        self::test('39 · edit inexistente not-found',static function():void{$h=self::harness();$h->controller->edit('999');self::ok(str_contains($h->response->location,'error/not_found')&&$h->store->edits===0,'404 mudou');});
        self::test('40 · roundtrip sete campos por idioma escapados',static function():void{$h=self::harness();self::valid($h,self::payload());$h->controller->add();$get=self::harness($h->store,$h->session);$get->controller->edit('101');foreach($get->loader->data['languages']as$lang)foreach(self::payload()['project_description'][$lang['language_id']]as$key=>$value)self::ok(($lang['fields'][$key]??null)===$value,'Campo perdido: '.$key);self::ok(!str_contains($get->response->output,'<h2>Español'),'HTML executado no textarea');});
        self::test('41 · nome/alt/keywords maliciosos não viram HTML',static function():void{$s=self::populated(1);$image=FilesFixture::image('escape');$s->records[101]['images']=[['image'=>$image,'alt'=>'<script>alt</script>','sort_order'=>0]];$h=self::harness($s);$h->controller->edit('101');self::ok(!str_contains($h->response->output,'<script>alt')&&str_contains($h->response->output,'&lt;script&gt;alt'),'Alt não escapado');$h=self::harness();$p=self::payload();$p['project_description'][1]['name']='<script>name</script>';$p['project_description'][1]['keyword']='BAD word';self::valid($h,$p);$h->controller->add();self::ok(!str_contains($h->response->output,'<script>name')&&str_contains($h->response->output,'&lt;script&gt;name'),'Nome não escapado');});
        self::test('42 · nomes legados da galeria/categorias/controles preservados',static function():void{$s=self::populated(1);self::withImages($s,'legacy');$s->records[101]['categories']=[5];$h=self::harness($s);$h->controller->edit('101');$html=$h->response->output;foreach(['name="project_image[0][image]"','name="project_image[1][alt]"','name="project_image[1][sort_order]"','name="project_category[]"','id="gallery"','id="input-gallery-file"','id="btn-add-image"','data-upload','id="input-client"','id="input-url"']as$needle)self::ok(str_contains($html,$needle),'Controle perdido: '.$needle);});
        self::test('43 · filtro featured zero sozinho conserva reset',static function():void{$h=self::harness(self::populated(1));$h->request->get['filter_featured']='0';$h->controller->index();self::ok($h->loader->data['filter_featured']==='0'&&str_contains($h->response->output,'>Reset</a>'),'Destaque zero perdeu reset');});
        self::test('44 · token forjado sem sessão anterior não é aceito',static function():void{$h=self::harness();$h->post(self::payload()+['csrf_token'=>str_repeat('0',64)]);$h->controller->add();self::ok($h->store->adds===0&&$h->loader->data['error']===$h->language->get('error_csrf'),'Token arbitrário aceito');});
        self::test('45 · delete por modal/data com hidden CSRF, sem confirm inline',static function():void{$h=self::harness(self::populated(1));$h->controller->index();self::ok(str_contains($h->response->output,'data-rms-confirm-delete=')&&!str_contains($h->response->output,'onsubmit=')&&substr_count($h->response->output,'name="csrf_token"')===1,'Modal/CSRF inválido');});
        echo "\nF8-08 contratos: ".self::$passed.' PASS / '.self::$failed.' FAIL — PHP '.PHP_VERSION.', Twig '.\Twig\Environment::VERSION."; DB/serviços/IO sessão em fixtures; MediaUpload/FS privados não servidos, sem HTTP/upload/banco/navegador reais.\n";return self::$failed?1:0;
    }
}
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool{if(!(error_reporting()&$severity))return false;throw new \ErrorException($message,0,$severity,$file,$line);});
try{$code=Suite::run($mirror);}finally{FilesFixture::cleanup();}
exit($code);
