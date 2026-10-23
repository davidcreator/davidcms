<?php declare(strict_types=1);
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS
 * @license https://opensource.org/licenses/GPL-3.0
 * F8-09: PHP/Twig reais; DB/serviços/IO de sessão simulados.
 * MediaUpload real só em FS privado não servido; não faz upload HTTP/move.
 */
namespace Reamur\Tests\F809;

use Reamur\System\Engine\Registry;
use Reamur\System\Library\Security\Csrf;
use Reamur\System\Library\Session;
use Reamur\Admin\Controller\Tool\Tool as Controller;
use Reamur\Admin\Model\Tool\Tool as Model;

final class FilesFixture {
    private static string $root = '';
    private static array $files = [];
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR4nGOQqgj4D8IMMAYAPqgHhR+OGvoAAAAASUVORK5CYII=';
    public static function init(): string {
        self::$root = sys_get_temp_dir() . '/f809-qa-' . bin2hex(random_bytes(8));
        if (!mkdir(self::$root, 0700)) { throw new \RuntimeException('Não foi possível criar fixture privada.'); }
        foreach (['admin', 'catalog', 'catalog/view', 'catalog/view/image', 'catalog/view/image/tool'] as $p) {
            if (!mkdir(self::$root . '/' . $p, 0700)) { throw new \RuntimeException('Falha na fixture privada.'); }
        }
        return self::$root . '/admin/';
    }
    public static function image(string $name): string {
        if (!preg_match('/^[a-z0-9-]+$/', $name)) { throw new \RuntimeException('Nome da fixture inválido'); }
        $relative = 'view/image/tool/' . $name . '.png';
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
        foreach (['catalog/view/image/tool', 'catalog/view/image', 'catalog/view', 'catalog', 'admin', ''] as $p) {
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
if (!defined('DB_PREFIX')) { define('DB_PREFIX', 'f809_'); }
if (!defined('DIR_SYSTEM')) { define('DIR_SYSTEM', $mirror . '/system/'); }
foreach (['engine/registry.php', 'engine/controller.php', 'engine/model.php', 'library/session.php', 'library/security/csrf.php', 'library/pagination.php', 'library/media_upload.php'] as $p) { require_once DIR_SYSTEM . $p; }
require_once $mirror . '/admin/controller/common/header.php';
require_once $mirror . '/admin/controller/tool/tool.php';
require_once $mirror . '/admin/model/tool/tool.php';
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
    public array $records=[];public array $calls=[];public int $sequence=100;public int $adds=0;public int $edits=0;public int $deletes=0;
    public function getLanguages():array{return [['language_id'=>1,'name'=>'English','code'=>'en-us'],['language_id'=>2,'name'=>'Português','code'=>'pt-br'],['language_id'=>3,'name'=>'Español','code'=>'es-eu']];}
    private function filtered(array $f):array{
        $rows=array_filter(array_values($this->records),static function(array $r)use($f):bool{
            foreach(['name','vendor']as$key)if(($f[$key]??'')!==''&&mb_stripos($r[$key],$f[$key])===false)return false;
            return !in_array($f['status']??'all',['0','1'],true)||$r['status']===(int)$f['status'];
        });usort($rows,static fn(array $a,array $b):int=>[$a['sort_order'],$a['tool_id']]<=>[$b['sort_order'],$b['tool_id']]);return array_values($rows);
    }
    public function getTotalTools(array $f=[]):int{return count($this->filtered($f));}
    public function getTools(array $f=[]):array{$this->calls[]=$f;$rows=$this->filtered($f);return isset($f['limit'])?array_slice($rows,$f['start'],$f['limit']):$rows;}
    public function getTool(int $id):array{return $this->records[$id]??[];}
    public function getToolDescriptions(int $id):array{return $this->records[$id]['descriptions']??[];}
    public function addTool(array $data):int{$this->adds++;$id=++$this->sequence;$this->save($id,$data);return $id;}
    public function editTool(int $id,array $data):void{$this->edits++;$this->save($id,$data);}
    private function save(int $id,array $data):void{$this->records[$id]=$data+['tool_id'=>$id,'date_added'=>'2026-10-23 14:05:00'];$this->records[$id]['name']=$data['descriptions'][1]['name'];}
    public function deleteTool(int $id):void{$this->deletes++;unset($this->records[$id]);}
}
final class LoaderFixture {
    public array $data = [];
    private \Twig\Environment $twig;
    public function __construct(private string $mirror) {
        $this->twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader($mirror . '/admin/view/template'), ['cache' => false, 'autoescape' => 'html', 'strict_variables' => true]);
    }
    public function language(string $route): void { if ($route !== 'tool/tool') { throw new \RuntimeException('Rota de language inesperada'); } }
    public function model(string $route): void { if ($route !== 'tool/tool') { throw new \RuntimeException('Model inesperado'); } }
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
        $this->language = new LanguageFixture($mirror . '/admin/language/' . $locale . '/tool/tool.php');
        foreach (['request' => $this->request, 'response' => $this->response, 'load' => $this->loader, 'document' => $this->document, 'language' => $this->language, 'config' => new ConfigFixture(), 'user' => new UserFixture($modify), 'session' => $this->session, 'url' => new UrlFixture(), 'model_tool_tool' => $this->store] as $key => $value) { $this->registry->set($key, $value); }
        $this->controller = new Controller($this->registry);
    }
    public function post(array $data): void { $this->request->server['REQUEST_METHOD'] = 'POST'; $this->request->post = $data; }
}
final class DbFixture {
    public array $queries=[];public array $rows=[['tool_id'=>77,'name'=>'Fixture','description'=>'Fixture','image'=>'','vendor'=>'','url'=>'','status'=>0,'sort_order'=>0,'date_added'=>'']];
    public function escape(string $value):string{return addslashes($value);}
    public function query(string $sql):\stdClass{$this->queries[]=$sql;if(str_contains($sql,'SELECT `language_id`'))return(object)['row'=>['language_id'=>1]];return(object)['row'=>['total'=>7],'rows'=>$this->rows];}
}
final class Suite {
    private static int $passed=0;private static int $failed=0;private static string $mirror;
    private static function ok(bool $test,string $message):void{if(!$test)throw new \RuntimeException($message);}
    private static function test(string $label,callable $fn):void{try{$fn();self::$passed++;echo 'PASS '.$label.PHP_EOL;}catch(\Throwable $e){self::$failed++;fwrite(STDERR,'FAIL '.$label.': '.$e->getMessage().PHP_EOL);}}
    private static function payload(int $version=1):array{
        $p=['vendor'=>'Vendor v'.$version,'url'=>$version===1?'':'https://example.invalid/f809/v'.$version,'image'=>'','sort_order'=>7,'tool_description'=>[]];
        foreach([1=>'English tool',2=>'Ferramenta ação çã',3=>'Herramienta ñ']as$id=>$name)$p['tool_description'][$id]=['name'=>$name.' v'.$version,'description'=>'Descrição & texto '.$name.' v'.$version];return$p;
    }
    private static function harness(?StoreFixture $s=null,?Session $session=null,string $locale='en-us',bool $modify=true):Harness{return new Harness(self::$mirror,$s,$session,$locale,$modify);}
    private static function populated(int $count):StoreFixture{$s=new StoreFixture();for($i=0;$i<$count;$i++){$p=self::payload();$p['vendor']='Vendor '.($i%2);$p['status']=$i%2;$p['sort_order']=$i;$p['descriptions']=$p['tool_description'];$p['descriptions'][1]['name']=sprintf('Tool %03d',$i+1);unset($p['tool_description']);$s->addTool($p);}$s->adds=0;return$s;}
    private static function model():array{$r=new Registry();$db=new DbFixture();$r->set('db',$db);$r->set('config',new ConfigFixture());return[new Model($r),$db];}
    private static function valid(Harness $h,array $p):void{$h->post($p+['csrf_token'=>Csrf::token($h->session)]);}
    private static function json(Harness $h):array{return json_decode($h->response->output,true,512,JSON_THROW_ON_ERROR);}
    public static function run(string $mirror):int{
        self::$mirror=$mirror;
        self::test('01 · model sem argumentos mantém COALESCE/ordem/lista completa',static function():void{[$m,$db]=self::model();$m->getTools();$q=end($db->queries);self::ok(str_contains($q,"COALESCE(td.`name`, td_en.`name`, '')")&&!str_contains($q,'NULLIF')&&!str_contains($q,' LIMIT ')&&str_ends_with($q,'ORDER BY t.`sort_order` ASC, t.`tool_id` ASC'),'Contrato/fallback alterado');});
        self::test('02 · filtros nome/fornecedor/status0 escapados e recorte',static function():void{[$m,$db]=self::model();$m->getTools(['name'=>"O'Brien",'vendor'=>"V'endor",'status'=>'0','start'=>20,'limit'=>20]);$q=end($db->queries);self::ok(str_contains($q,"O\\'Brien")&&str_contains($q,"V\\'endor")&&str_contains($q,"t.`status` = '0'")&&str_ends_with($q,'LIMIT 20, 20'),'SQL filtros inválido');});
        self::test('03 · count e lista usam os mesmos joins/predicados',static function():void{[$m,$db]=self::model();$f=['name'=>'x','vendor'=>'v','status'=>'1'];self::ok($m->getTotalTools($f)===7,'Total errado');$count=end($db->queries);$m->getTools($f);$q=end($db->queries);$q=substr($q,strpos($q,'FROM '));$q=substr($q,0,strpos($q,' ORDER BY'));self::ok(substr($count,strpos($count,'FROM '))===$q,'Predicados divergentes');});
        self::test('04 · status adulterado/arrays ignorados e limites normalizados',static function():void{[$m,$db]=self::model();$m->getTools(['name'=>[],'vendor'=>[],'status'=>'1 OR 1=1','start'=>-1,'limit'=>999]);$q=end($db->queries);self::ok(!str_contains($q,'1 OR 1=1')&&!str_contains($q,' WHERE ')&&str_ends_with($q,'LIMIT 0, 100'),'Normalização inválida');});
        self::test('05 · delete do model só ferramenta/descrições, sem SEO/DDL',static function():void{[$m,$db]=self::model();$m->deleteTool(143);$q=implode("\n",$db->queries);self::ok(str_contains($q,"DELETE FROM `f809_tool` WHERE `tool_id` = '143'")&&str_contains($q,"DELETE FROM `f809_tool_description` WHERE `tool_id` = '143'")&&!preg_match('/seo_url|DROP|ALTER|CREATE/',$q),'Cascata/schema alterados');});
        self::test('06 · descrições reais têm só name/description por language_id',static function():void{[$m,$db]=self::model();$db->rows=[['language_id'=>12,'name'=>'Ação','description'=>'Texto']];self::ok($m->getToolDescriptions(143)===[12=>['name'=>'Ação','description'=>'Texto']],'Shape multilíngue mudou');});
        self::test('07 · índice vazio/CSRF/estado honesto',static function():void{$h=self::harness();$h->controller->index();self::ok($h->loader->data['total_tools']===0&&str_contains($h->response->output,'No tools yet')&&strlen($h->loader->data['csrf_token'])===64,'Índice vazio inválido');});
        self::test('08 · 45 ferramentas: página dois contém vinte',static function():void{$h=self::harness(self::populated(45));$h->request->get['page']='2';$h->controller->index();self::ok(count($h->loader->data['tools'])===20&&end($h->store->calls)['start']===20&&$h->loader->data['total_tools']===45,'Paginação incorreta');});
        self::test('09 · recuo além do fim e query array sem warnings',static function():void{$h=self::harness(self::populated(45));$h->request->get['page']=(string)PHP_INT_MAX;$h->controller->index();self::ok(count($h->loader->data['tools'])===5&&end($h->store->calls)['start']===40,'Recuo inválido');$h=self::harness();$h->request->get=['filter_name'=>[],'filter_vendor'=>[],'filter_status'=>[],'page'=>[]];$h->controller->index();self::ok($h->loader->data['filter_name']===''&&$h->loader->data['filter_vendor']===''&&$h->loader->data['filter_status']==='all','Query array não normalizada');});
        self::test('10 · nome/fornecedor zero conservam reset',static function():void{foreach(['filter_name','filter_vendor']as$key){$h=self::harness();$h->request->get[$key]='0';$h->controller->index();self::ok(str_contains($h->response->output,'>Reset</a>'),'Filtro zero perdeu reset');}});
        self::test('11 · nome/fornecedor/status combinados e contagem',static function():void{$h=self::harness(self::populated(45));$h->request->get=['filter_name'=>'Tool','filter_vendor'=>'Vendor 0','filter_status'=>'0'];$h->controller->index();self::ok($h->loader->data['total_tools']===23,'Total combinado errado');});
        self::test('12 · pager preserva três filtros sem escape duplo/XSS',static function():void{$s=self::populated(45);foreach($s->records as&$row){$row['name']="O'Brien & <tag>";$row['vendor']="V'endor & <vendor>";}unset($row);$h=self::harness($s);$h->request->get=['filter_name'=>"O'Brien & <tag>",'filter_vendor'=>"V'endor & <vendor>",'filter_status'=>'0'];$h->controller->index();$html=$h->loader->data['pagination'];self::ok(!str_contains($html,'&amp;amp;')&&!str_contains($h->response->output,'<vendor>')&&!str_contains($h->response->output,'<tag>'),'Escape incorreto');preg_match_all('/href="([^"]+)"/',$html,$matches);self::ok(count($matches[1])>0,'Links ausentes');foreach($matches[1]as$attr){parse_str((string)parse_url(html_entity_decode($attr,ENT_QUOTES|ENT_HTML5,'UTF-8'),PHP_URL_QUERY),$q);self::ok(($q['filter_name']??'')==="O'Brien & <tag>"&&($q['filter_vendor']??'')==="V'endor & <vendor>"&&($q['filter_status']??'')==='0','Filtro perdido');}});
        self::test('13 · readonly sem ações modify/upload desabilitado',static function():void{$h=self::harness(self::populated(1),null,'en-us',false);$h->controller->index();self::ok(!str_contains($h->response->output,'data-rms-confirm-delete')&&!str_contains($h->response->output,'route=tool/tool.add'),'Ações modify visíveis');$h=self::harness(null,null,'en-us',false);$h->controller->add();self::ok(!str_contains($h->response->output,'type="submit"')&&str_contains($h->response->output,'id="btn-upload-icon" disabled'),'Readonly incorreto');});
        self::test('14 · seis IDs/names e inputs128/255, JSON JS, sem SEO/editor',static function():void{$h=self::harness();$h->controller->add();$html=$h->response->output;foreach([1,2,3]as$id)foreach(['name','description']as$key){self::ok(substr_count($html,'id="input-'.$key.'-'.$id.'"')===1&&str_contains($html,'name="tool_description['.$id.']['.$key.']"'),'ID/name inválido');}self::ok(str_contains($html,'maxlength="128"')&&str_contains($html,'maxlength="255"')&&!str_contains($html,'<textarea')&&!str_contains($html,'<script>')&&!str_contains($html,'keyword')&&!str_contains($html,'data-rte'),'Contrato de conteúdo/editor alterado');preg_match('/data-tool-config="([^"]+)"/',$html,$m);$c=json_decode(html_entity_decode($m[1]??'',ENT_QUOTES|ENT_HTML5,'UTF-8'),true,512,JSON_THROW_ON_ERROR);self::ok(str_contains($c['upload'],'route=tool/tool.upload')&&$c['catalog']==='http://example.test/catalog/'&&$c['wait']===$h->language->get('text_upload_wait'),'Config JS incorreta');});
        self::test('15 · assets versionados/chrome sem rms-dash',static function():void{$h=self::harness();$h->controller->add();self::ok(str_contains(implode('',$h->document->scripts),'connected-tools.js?v=')&&str_contains(implode('',$h->document->styles),'admin.css?v=')&&!isset($h->loader->data['body_class']),'Assets/chrome inválidos');});
        self::test('16 · três idiomas com mesmas chaves e Twig strict',static function():void{$keys=null;foreach(['en-us','pt-br','es-eu']as$locale){$h=self::harness(null,null,$locale);$h->controller->add();self::ok(str_contains($h->response->output,$h->language->get('heading_title')),'Heading não traduzido');$k=array_keys($h->language->values);sort($k);if($keys!==null)self::ok($k===$keys,'Chaves divergentes');$keys=$k;$h->controller->index();}});
        self::test('17 · add sem token mantém campos e não muta',static function():void{$h=self::harness();$h->post(self::payload());$h->controller->add();self::ok($h->store->adds===0&&$h->loader->data['error']===$h->language->get('error_csrf')&&$h->loader->data['languages'][1]['fields']['name']==='Ferramenta ação çã v1','CSRF add falhou');});
        self::test('18 · token array/incorreto/forjado sem sessão recusados',static function():void{foreach([[],'wrong',str_repeat('0',64)]as$t){$h=self::harness();$h->post(self::payload()+['csrf_token'=>$t]);$h->controller->add();self::ok($h->store->adds===0,'Token inválido aceito');}});
        self::test('19 · add válido mantém Unicode/vendor/URL vazia/flags',static function():void{$h=self::harness();self::valid($h,self::payload());$h->controller->add();$r=$h->store->getTool(101);self::ok($r['vendor']==='Vendor v1'&&$r['url']===''&&$r['status']===0&&$r['sort_order']===7&&$r['descriptions'][3]['description']==='Descrição & texto Herramienta ñ v1','Payload normalizado alterado');});
        self::test('20 · nome EN obrigatório e teto128 preservados',static function():void{foreach(['',str_repeat('ã',129)]as$name){$h=self::harness();$p=self::payload();$p['tool_description'][1]['name']=$name;self::valid($h,$p);$h->controller->add();self::ok($h->store->adds===0&&$h->loader->data['error']===$h->language->get('error_name'),'Validação do nome alterada');}});
        self::test('21 · regra URL antiga: vazio/http(s), sem endurecimento silencioso',static function():void{foreach(['','https://example.invalid','HTTP://example.invalid','http://']as$url){$h=self::harness();$p=self::payload();$p['url']=$url;self::valid($h,$p);$h->controller->add();self::ok($h->store->adds===1,'Prefixo URL aceito na base foi rejeitado');}foreach(['ftp://example.invalid','javascript:alert(1)']as$url){$h=self::harness();$p=self::payload();$p['url']=$url;self::valid($h,$p);$h->controller->add();self::ok($h->store->adds===0&&str_contains($h->loader->data['error'],$h->language->get('error_url')),'Esquema indevido aceito');}});
        self::test('22 · limites vendor64/descrição255/URL255 preservados',static function():void{$h=self::harness();$p=self::payload();$p['vendor']=str_repeat('ç',80);$p['url']='https://example.invalid/'.str_repeat('a',300);$p['tool_description'][2]['description']=str_repeat('ã',300);self::valid($h,$p);$h->controller->add();$r=$h->store->getTool(101);self::ok(mb_strlen($r['vendor'])===64&&mb_strlen($r['url'])===255&&mb_strlen($r['descriptions'][2]['description'])===255,'Truncamento alterado');});
        self::test('23 · ícone externo/inexistente recusado',static function():void{foreach(['../outside.png','view/image/project/x.png','view/image/tool/missing.png']as$icon){$h=self::harness();$p=self::payload();$p['image']=$icon;self::valid($h,$p);$h->controller->add();self::ok($h->store->adds===0&&str_contains($h->loader->data['error'],$h->language->get('error_image_path')),'Imagem indevida aceita');}});
        self::test('24 · guard/preview real em FS privado, ícone sem recorte',static function():void{$icon=FilesFixture::image('preview');$h=self::harness();$p=self::payload();$p['image']=$icon;self::valid($h,$p);$h->controller->add();$get=self::harness($h->store,$h->session);$get->controller->edit('101');self::ok($get->loader->data['image_preview']==='http://example.test/catalog/'.$icon&&str_contains($get->response->output,'object-fit-contain'),'Preview incorreto');});
        self::test('25 · edit sem token não altera imagem/vendor',static function():void{$s=self::populated(1);$icon=FilesFixture::image('edit-csrf');$s->records[101]['image']=$icon;$h=self::harness($s);$h->post(self::payload(2));$h->controller->edit('101');self::ok($s->edits===0&&$s->getTool(101)['image']===$icon&&file_exists(FilesFixture::absolute($icon)),'CSRF edit mutou');});
        self::test('26 · edit mesmo ID, URL/status on/off, date_added intacta',static function():void{$s=self::populated(1);$date=$s->getTool(101)['date_added'];$h=self::harness($s);self::valid($h,self::payload(2)+['status'=>'1']);$h->controller->edit('101');self::ok($s->getTool(101)['status']===1&&$s->getTool(101)['url']==='https://example.invalid/f809/v2'&&$s->getTool(101)['date_added']===$date,'Edit on/data alterados');$h=self::harness($s);self::valid($h,self::payload(3));$h->controller->edit('101');self::ok($s->getTool(101)['status']===0,'Unchecked não desativou');});
        self::test('27 · GET delete não remove registro/ícone',static function():void{$s=self::populated(1);$icon=FilesFixture::image('get-delete');$s->records[101]['image']=$icon;$h=self::harness($s);$h->controller->delete('101');self::ok($s->deletes===0&&file_exists(FilesFixture::absolute($icon))&&str_contains($h->response->location,'error/permission'),'GET mutou');});
        self::test('28 · delete sem token não chega ao unlink',static function():void{$s=self::populated(1);$icon=FilesFixture::image('csrf-delete');$s->records[101]['image']=$icon;$h=self::harness($s);$h->post([]);$h->controller->delete('101');self::ok($s->deletes===0&&file_exists(FilesFixture::absolute($icon))&&($h->session->data['flash']['type']??'')==='error','CSRF delete falhou');});
        self::test('29 · delete válido só ferramenta/arquivo próprios',static function():void{$s=self::populated(2);$icon=FilesFixture::image('own-delete');$keep=FilesFixture::image('keep');$s->records[101]['image']=$icon;$s->records[102]['image']=$keep;$h=self::harness($s);self::valid($h,[]);$h->controller->delete('101');self::ok(!$s->getTool(101)&&$s->getTool(102)!==[]&&!file_exists(FilesFixture::absolute($icon))&&file_exists(FilesFixture::absolute($keep)),'Delete afetou outro conteúdo');});
        self::test('30 · ACL modify negada em add/edit/delete/upload',static function():void{foreach(['add','edit','delete','upload']as$m){$h=self::harness(self::populated(1),null,'en-us',false);self::valid($h,self::payload(2));if($m==='add'||$m==='upload')$h->controller->{$m}();else$h->controller->{$m}('101');self::ok($h->store->adds+$h->store->edits+$h->store->deletes===0,'ACL mutou');if($m==='upload')self::ok(self::json($h)['error']===$h->language->get('error_permission'),'ACL upload mudou');else self::ok(str_contains($h->response->location,'error/permission'),'Redirect ACL mudou');}});
        self::test('31 · upload token ausente/array/errado recusa antes do componente',static function():void{foreach([null,[],'wrong']as$t){$h=self::harness();$h->post($t===null?[]:['csrf_token'=>$t]);$h->controller->upload();$j=self::json($h);self::ok($j['success']===false&&$j['path']===''&&$j['error']===$h->language->get('error_csrf'),'CSRF upload ausente');}});
        self::test('32 · upload GET/no-file mantém JSON normalizado',static function():void{$h=self::harness();$h->controller->upload();self::ok(self::json($h)['error']===$h->language->get('error_upload_method'),'GET upload mudou');$h=self::harness();self::valid($h,[]);$h->controller->upload();$j=self::json($h);self::ok($j['success']===false&&$j['path']===''&&$j['error']===$h->language->get('error_upload')&&str_contains(implode('',$h->response->headers),'application/json'),'JSON/no-file mudou');});
        self::test('33 · edit inexistente mantém not-found',static function():void{$h=self::harness();$h->controller->edit('999');self::ok(str_contains($h->response->location,'error/not_found')&&$h->store->edits===0,'404 mudou');});
        self::test('34 · roundtrip seis campos/Unicode, descrição continua input',static function():void{$h=self::harness();self::valid($h,self::payload());$h->controller->add();$get=self::harness($h->store,$h->session);$get->controller->edit('101');foreach($get->loader->data['languages']as$lang)foreach(self::payload()['tool_description'][$lang['language_id']]as$key=>$value)self::ok(($lang['fields'][$key]??null)===$value,'Campo multilíngue perdido');self::ok(!str_contains($get->response->output,'<textarea'),'Descrição virou editor');});
        self::test('35 · nome/vendor/descrição escapados, URL externa não requisitada',static function():void{$h=self::harness();$p=self::payload();$p['tool_description'][1]['name']='<script>name</script>';$p['tool_description'][2]['description']='<b>texto</b>';$p['vendor']='<script>vendor</script>';self::valid($h,$p);$h->controller->add();$get=self::harness($h->store,$h->session);$get->controller->edit('101');self::ok(!str_contains($get->response->output,'<script>name')&&!str_contains($get->response->output,'<b>texto</b>')&&str_contains($get->response->output,'&lt;script&gt;vendor'),'HTML não escapado');});
        self::test('36 · modal CSRF/sem inline e link mantém noopener',static function():void{$s=self::populated(1);$s->records[101]['url']='https://example.invalid/';$h=self::harness($s);$h->controller->index();self::ok(str_contains($h->response->output,'data-rms-confirm-delete=')&&!str_contains($h->response->output,'onsubmit=')&&substr_count($h->response->output,'name="csrf_token"')===1&&str_contains($h->response->output,'rel="noopener"'),'Modal/link alterado');});
        echo "\nF8-09 contratos: ".self::$passed.' PASS / '.self::$failed.' FAIL — PHP '.PHP_VERSION.', Twig '.\Twig\Environment::VERSION."; DB/serviços/IO sessão simulados, MediaUpload/FS privados não servidos; sem HTTP/banco/navegador/upload reais.\n";return self::$failed?1:0;
    }
}
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool{if(!(error_reporting()&$severity))return false;throw new \ErrorException($message,0,$severity,$file,$line);});
try{$code=Suite::run($mirror);}finally{FilesFixture::cleanup();}
exit($code);
