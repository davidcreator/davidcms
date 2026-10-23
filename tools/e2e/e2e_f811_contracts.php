<?php declare(strict_types=1);
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS
 * @license https://opensource.org/licenses/GPL-3.0
 * F8-11 — PHP/Twig reais; DB/serviços/IO de sessão em fixtures.
 * Nenhum idioma/default/diretório real é alterado; DIR_APPLICATION é privado.
 */
namespace Reamur\Tests\F811;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Session;
use Reamur\System\Library\Security\Csrf;
use Reamur\Admin\Controller\Localization\Language as Controller;
use Reamur\Admin\Model\Localization\Language as Model;
final class DirsFixture {
    private static string $root='';
    public static function init():string{self::$root=sys_get_temp_dir().'/f811-qa-'.bin2hex(random_bytes(8));if(!mkdir(self::$root,0700))throw new \RuntimeException('Fixture privada indisponível');foreach(['admin','admin/language','admin/language/en-us','admin/language/pt-br','admin/language/es-eu']as$p)if(!mkdir(self::$root.'/'.$p,0700))throw new \RuntimeException('Fixture privada indisponível');return self::$root.'/admin/';}
    public static function cleanup():void{foreach(['admin/language/es-eu','admin/language/pt-br','admin/language/en-us','admin/language','admin','']as$p){$d=self::$root.($p===''?'':'/'.$p);if(is_dir($d)&&!rmdir($d))throw new \RuntimeException('Fixture não vazia; não remover amplamente');}}
}
$mirror=realpath($argv[1]??dirname(__DIR__,2).'/dev')?:'';$autoload=getenv('TWIG_AUTOLOAD')?:$mirror.'/system/storage/vendor/autoload.php';
if($mirror===''||!is_file($autoload)){fwrite(STDERR,"MIRROR/Twig ausentes.\n");exit(2);}require_once $autoload;
if(!class_exists(\Twig\Environment::class)||!extension_loaded('mbstring')||defined('DIR_APPLICATION')){fwrite(STDERR,"Requer CLI isolado/Twig3/mbstring.\n");exit(2);}
if(!defined('DB_PREFIX'))define('DB_PREFIX','f811_');if(!defined('DIR_SYSTEM'))define('DIR_SYSTEM',$mirror.'/system/');
foreach(['engine/registry.php','engine/controller.php','engine/model.php','library/session.php','library/security/csrf.php','library/pagination.php']as$p)require_once DIR_SYSTEM.$p;
require_once $mirror.'/admin/controller/common/header.php';require_once $mirror.'/admin/model/localization/language.php';require_once $mirror.'/admin/controller/localization/language.php';
define('DIR_APPLICATION',DirsFixture::init());

final class LanguageFixture {
    public array $values;
    public function __construct(string $file) { $this->values = (static function (string $file): array { $_ = []; require $file; return $_; })($file); }
    public function get(string $key): string { if (!array_key_exists($key, $this->values)) { throw new \RuntimeException('Chave i18n ausente: ' . $key); } return (string)$this->values[$key]; }
}
final class ConfigFixture {
    public array $values=['site_url'=>'http://example.test/admin/','language_directory'=>'en-us','config_language_catalog'=>'en-us','config_language_admin'=>'en-us'];
    public function get(string $key,mixed $default=null):mixed{return$this->values[$key]??$default;}
}
final class UserFixture {
    public function __construct(public bool $modify,public bool $access=true){}
    public function hasPermission(string $kind,string $route):bool{return$kind==='access'?$this->access:$this->modify;}
}
final class SettingsFixture {
    public array $writes=[];public array $values=[];
    public function editSettings(array $data):void{$this->writes[]=$data;$this->values=array_replace($this->values,$data);}
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
final class StoreFixture {
    public array $records=[];public array $calls=[];public array $edits=[];public array $gets=[];
    public function __construct(){foreach([[1,'English','en-us','en_US.UTF-8',1,1],[2,'Português (BR)','pt-br','pt_BR.UTF-8',2,1],[3,'Español (ES)','es-eu','es_ES.UTF-8',3,1]]as$r)$this->records[$r[0]]=['language_id'=>$r[0],'name'=>$r[1],'code'=>$r[2],'locale'=>$r[3],'sort_order'=>$r[4],'status'=>$r[5]];}
    private function filtered(array $o):array{$rows=array_filter(array_values($this->records),static function(array $r)use($o):bool{$q=$o['search']??'';if($q!==''&&mb_stripos($r['name'],$q)===false&&mb_stripos($r['code'],$q)===false&&mb_stripos($r['locale'],$q)===false)return false;return!in_array($o['status']??'all',['0','1'],true)||$r['status']===(int)$o['status'];});usort($rows,static fn(array $a,array $b):int=>[$a['sort_order'],$a['language_id']]<=>[$b['sort_order'],$b['language_id']]);return array_values($rows);}
    public function getLanguages(array $o=[]):array{$this->calls[]=$o;$rows=$this->filtered($o);return isset($o['limit'])?array_slice($rows,$o['start'],$o['limit']):$rows;}
    public function getTotalLanguages(array $o=[]):int{return count($this->filtered($o));}
    public function getLanguage(int $id):array{$this->gets[]=$id;return$this->records[$id]??[];}
    public function editLanguage(int $id,array $data):void{$this->edits[]=[$id,$data];$this->records[$id]=array_replace($this->records[$id],$data);}
}
final class LoaderFixture {
    public array $data=[];private \Twig\Environment $twig;
    public function __construct(string $mirror){$this->twig=new \Twig\Environment(new \Twig\Loader\FilesystemLoader($mirror.'/admin/view/template'),['cache'=>false,'autoescape'=>'html','strict_variables'=>true]);}
    public function language(string $route):void{if($route!=='localization/language')throw new \RuntimeException('Language inesperada');}
    public function model(string $route):void{if(!in_array($route,['localization/language','setting/setting'],true))throw new \RuntimeException('Model inesperado');}
    public function controller(string $route,array $data=[]):string{return'<!-- shell fixture -->';}
    public function view(string $route,array $data):string{$this->data=$data;return$this->twig->render($route.'.twig',$data);}
}
final class Harness {
    public Registry $registry;public Controller $controller;public StoreFixture $store;public SettingsFixture $settings;public ConfigFixture $config;public Session $session;public \stdClass $request;public ResponseFixture $response;public LoaderFixture $loader;public DocumentFixture $document;public LanguageFixture $language;
    public function __construct(string $mirror,?StoreFixture $store=null,?Session $session=null,string $locale='en-us',bool $modify=true,bool $access=true,?SettingsFixture $settings=null){$this->registry=new Registry();$this->store=$store??new StoreFixture();$this->settings=$settings??new SettingsFixture();$this->config=new ConfigFixture();$this->config->values=array_replace($this->config->values,$this->settings->values);$this->session=$session??(new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();$this->request=(object)['server'=>['REQUEST_METHOD'=>'GET'],'get'=>[],'post'=>[]];$this->response=new ResponseFixture();$this->loader=new LoaderFixture($mirror);$this->document=new DocumentFixture();$this->language=new LanguageFixture($mirror.'/admin/language/'.$locale.'/localization/language.php');foreach(['request'=>$this->request,'response'=>$this->response,'load'=>$this->loader,'document'=>$this->document,'language'=>$this->language,'config'=>$this->config,'user'=>new UserFixture($modify,$access),'session'=>$this->session,'url'=>new UrlFixture(),'model_localization_language'=>$this->store,'model_setting_setting'=>$this->settings]as$key=>$value)$this->registry->set($key,$value);$this->controller=new Controller($this->registry);}
    public function post(array $data):void{$this->request->server['REQUEST_METHOD']='POST';$this->request->post=$data;}
}
final class DbFixture {
    public array $queries=[];public array $rows=[];public array $row=[];
    public function escape(string $s):string{return addslashes($s);}
    public function query(string $sql):\stdClass{$this->queries[]=$sql;return(object)['rows'=>$this->rows,'row'=>$this->row?:['total'=>7]];}
}
final class Suite {
    private static int $passed=0;private static int $failed=0;private static string $mirror;
    private static function ok(bool $t,string $m):void{if(!$t)throw new \RuntimeException($m);}
    private static function test(string $label,callable $fn):void{try{$fn();self::$passed++;echo'PASS '.$label.PHP_EOL;}catch(\Throwable $e){self::$failed++;fwrite(STDERR,'FAIL '.$label.': '.$e->getMessage().PHP_EOL);}}
    private static function harness(?StoreFixture $s=null,?Session $session=null,string $locale='en-us',bool $modify=true,bool $access=true,?SettingsFixture $settings=null):Harness{return new Harness(self::$mirror,$s,$session,$locale,$modify,$access,$settings);}
    private static function populated(int $n):StoreFixture{$s=new StoreFixture();for($i=4;$i<=$n;$i++)$s->records[$i]=['language_id'=>$i,'name'=>sprintf('QA %03d',$i),'code'=>sprintf('qa-%03d',$i),'locale'=>sprintf('qa_%03d.UTF-8',$i),'sort_order'=>$i,'status'=>$i%2];return$s;}
    private static function model():array{$r=new Registry();$db=new DbFixture();$r->set('db',$db);return[new Model($r),$db];}
    private static function valid(Harness $h,array $p):void{$h->post($p+['csrf_token'=>Csrf::token($h->session)]);}
    private static function editPayload(StoreFixture $s,int $id=2):array{$row=$s->records[$id];return['name'=>$row['name'],'locale'=>$row['locale'],'sort_order'=>(string)$row['sort_order'],'status'=>(string)$row['status']];}
    public static function run(string $mirror):int{
        self::$mirror=$mirror;
        self::test('01 · model sem argumentos completo/ordem original',static function():void{[$m,$db]=self::model();$m->getLanguages();$q=end($db->queries);self::ok(!str_contains($q,' WHERE ')&&!str_contains($q,' LIMIT ')&&str_ends_with($q,'ORDER BY `sort_order` ASC, `language_id` ASC'),'Contrato antigo alterado');});
        self::test('02 · busca/status/limites escapados',static function():void{[$m,$db]=self::model();$m->getLanguages(['search'=>"O'Brien",'status'=>'0','start'=>-1,'limit'=>999]);$q=end($db->queries);self::ok(str_contains($q,"O\\'Brien")&&str_contains($q,'`name` LIKE')&&str_contains($q,'`code` LIKE')&&str_contains($q,'`locale` LIKE')&&str_contains($q,"`status` = '0'")&&str_ends_with($q,'LIMIT 0, 100'),'SQL filtros incorreto');});
        self::test('03 · total usa predicados da lista',static function():void{[$m,$db]=self::model();$f=['search'=>'qa','status'=>'1'];self::ok($m->getTotalLanguages($f)===7,'Total incorreto');$count=end($db->queries);$m->getLanguages($f);$q=end($db->queries);$q=substr($q,strpos($q,'FROM '));$q=substr($q,0,strpos($q,' ORDER BY'));self::ok(substr($count,strpos($count,'FROM '))===$q,'Predicados divergentes');});
        self::test('04 · getLanguage mantém ID/limit1',static function():void{[$m,$db]=self::model();$db->row=['language_id'=>12,'code'=>'pt-br'];self::ok($m->getLanguage(12)['code']==='pt-br'&&str_contains((string)end($db->queries),"WHERE `language_id` = '12' LIMIT 1"),'Get mudou');});
        self::test('05 · editLanguage não atualiza code/schema',static function():void{[$m,$db]=self::model();$m->editLanguage(12,['name'=>"O'Brien",'locale'=>'pt_BR.UTF-8','sort_order'=>7,'status'=>1]);$q=end($db->queries);self::ok(str_contains($q,"O\\'Brien")&&str_contains($q,"`sort_order` = '7'")&&!str_contains($q,'`code` =')&&!preg_match('/ALTER|DROP|CREATE/',$q),'Código/schema alterados');});
        self::test('06 · getEnabledCodes real respeita diretório em FS privado',static function():void{[$m,$db]=self::model();$db->rows=[['code'=>'en-us'],['code'=>'qa-missing']];self::ok($m->getEnabledCodes()===['en-us']&&str_contains((string)end($db->queries),'WHERE `status` = 1'),'Gate FS/status alterado');});
        self::test('07 · GET lista não altera idiomas/padrões',static function():void{$h=self::harness();$before=$h->store->records;$h->controller->index();self::ok($h->store->records===$before&&$h->store->edits===[]&&$h->settings->writes===[]&&$h->loader->data['default_admin']==='en-us'&&$h->loader->data['default_catalog']==='en-us','GET mudou padrões');});
        self::test('08 · grade45/página2 tem20 e opções completas45',static function():void{$h=self::harness(self::populated(45));$h->request->get['page']='2';$h->controller->index();self::ok(count($h->loader->data['languages'])===20&&count($h->loader->data['language_options'])===45&&$h->loader->data['total_languages']===45,'Seletores foram paginados');});
        self::test('09 · recuo e queries arrays normalizados',static function():void{$h=self::harness(self::populated(45));$h->request->get['page']=(string)PHP_INT_MAX;$h->controller->index();self::ok(count($h->loader->data['languages'])===5,'Recuo inválido');$h=self::harness();$h->request->get=['filter_search'=>[],'filter_status'=>[],'page'=>[]];$h->controller->index();self::ok($h->loader->data['filter_search']===''&&$h->loader->data['filter_status']==='all','Query array não normalizada');});
        self::test('10 · busca/status zero conservam reset',static function():void{foreach(['filter_search','filter_status']as$key){$h=self::harness();$h->request->get[$key]='0';$h->controller->index();self::ok(str_contains($h->response->output,'>Reset</a>'),'Filtro0 sem reset');}});
        self::test('11 · busca nome/código/locale e status combinam',static function():void{foreach(['Português','pt-br','pt_BR']as$q){$h=self::harness();$h->request->get=['filter_search'=>$q,'filter_status'=>'1'];$h->controller->index();self::ok($h->loader->data['total_languages']===1,'Busca não localizou nome/code/locale');}});
        self::test('12 · pager mantém busca/status sem escape duplo',static function():void{$s=self::populated(45);foreach($s->records as&$r)$r['name']="O'Brien & <tag>";unset($r);$h=self::harness($s);$h->request->get=['filter_search'=>"O'Brien & <tag>",'filter_status'=>'1'];$h->controller->index();$html=$h->loader->data['pagination'];self::ok(!str_contains($html,'&amp;amp;')&&!str_contains($h->response->output,'<tag>'),'Escape inválido');preg_match_all('/href="([^"]+)"/',$html,$m);self::ok(count($m[1])>0,'Pager ausente');foreach($m[1]as$a){parse_str((string)parse_url(html_entity_decode($a,ENT_QUOTES|ENT_HTML5,'UTF-8'),PHP_URL_QUERY),$q);self::ok(($q['filter_search']??'')==="O'Brien & <tag>"&&($q['filter_status']??'')==='1','Query perdida');}});
        self::test('13 · readonly lista sem salvar, fieldset disabled',static function():void{$h=self::harness(null,null,'en-us',false);$h->controller->index();self::ok(str_contains($h->response->output,'<fieldset disabled>')&&str_contains($h->response->output,$h->language->get('text_readonly_notice')),'Readonly não sinalizado');self::ok(!str_contains($h->response->output,'class="btn btn-primary">Save</button>'),'Save readonly visível');});
        self::test('14 · access negado para lista não chega à view/escrita',static function():void{$h=self::harness(null,null,'en-us',true,false);$h->controller->index();self::ok(str_contains($h->response->location,'error/permission')&&$h->response->output===''&&$h->settings->writes===[],'Access não interrompeu');});
        self::test('15 · defaults sem CSRF não persistem',static function():void{$h=self::harness();$h->post(['config_language_catalog'=>'pt-br','config_language_admin'=>'es-eu']);$h->controller->index();self::ok($h->settings->writes===[]&&($h->session->data['flash']['text']??'')===$h->language->get('error_csrf'),'CSRF defaults falhou');});
        self::test('16 · defaults válidos só POST explícito, duas chaves/PRG',static function():void{$h=self::harness();self::valid($h,['config_language_catalog'=>'pt-br','config_language_admin'=>'es-eu']);$h->controller->index();self::ok($h->settings->writes===[['config_language_catalog'=>'pt-br','config_language_admin'=>'es-eu']]&&$h->store->edits===[]&&$h->response->output===''&&($h->session->data['flash']['type']??'')==='success','Defaults/retorno incorretos');self::ok($h->config->get('config_language_admin')==='en-us','Mudou config do mesmo request');});
        self::test('17 · defaults ausentes/desabilitados/desconhecidos recusados',static function():void{foreach([['','en-us'],['qa-missing','en-us'],['pt-br','en-us']]as[$catalog,$admin]){$s=new StoreFixture();$s->records[2]['status']=0;$h=self::harness($s);self::valid($h,['config_language_catalog'=>$catalog,'config_language_admin'=>$admin]);$h->controller->index();self::ok($h->settings->writes===[]&&($h->session->data['flash']['type']??'')==='error','Default inválido persistiu');}});
        self::test('18 · defaults readonly negados com token válido',static function():void{$h=self::harness(null,null,'en-us',false);self::valid($h,['config_language_catalog'=>'pt-br','config_language_admin'=>'es-eu']);$h->controller->index();self::ok($h->settings->writes===[]&&str_contains($h->response->location,'error/permission'),'ACL defaults falhou');});
        self::test('19 · token array/incorreto recusado nos defaults',static function():void{foreach([[],'wrong']as$t){$h=self::harness();$h->post(['config_language_catalog'=>'pt-br','config_language_admin'=>'es-eu','csrf_token'=>$t]);$h->controller->index();self::ok($h->settings->writes===[],'Token inválido aceito');}});
        self::test('20 · filtrar grade vazia não apaga opções/padrões',static function():void{$h=self::harness(self::populated(45));$h->request->get['filter_search']='not-found';$h->controller->index();self::ok($h->loader->data['languages']===[]&&count($h->loader->data['language_options'])===45&&str_contains($h->response->output,'value="qa-045"'),'Opções herdaram filtro da grade');});
        self::test('21 · GET edit inicializa error/IDs/limits/code readonly',static function():void{$h=self::harness();$h->controller->edit('2');$html=$h->response->output;foreach(['name','code','locale','sort_order']as$id)self::ok(str_contains($html,'id="'.$id.'"'),'ID perdido');self::ok($h->loader->data['error']===''&&str_contains($html,'id="code" value="pt-br" disabled')&&!str_contains($html,'name="code"')&&str_contains($html,'maxlength="32"'),'GET error/código incorreto');});
        self::test('22 · edit ID inexistente interrompe após redirect',static function():void{$h=self::harness();$h->controller->edit('999');self::ok($h->response->output===''&&str_contains($h->response->location,'localization/language')&&($h->session->data['flash']['text']??'')===$h->language->get('error_not_found'),'ID ausente continuou');});
        self::test('23 · edit sem access não lê registro/view',static function():void{$h=self::harness(null,null,'en-us',true,false);$h->controller->edit('2');self::ok($h->store->gets===[]&&$h->response->output===''&&str_contains($h->response->location,'error/permission'),'Access edit não interrompeu');});
        self::test('24 · edit sem CSRF não grava e traduz erro',static function():void{foreach([null,[],'wrong']as$t){$h=self::harness();$p=self::editPayload($h->store);if($t!==null)$p['csrf_token']=$t;$h->post($p);$h->controller->edit('2');self::ok($h->store->edits===[]&&$h->loader->data['error']===$h->language->get('error_csrf'),'CSRF edit falhou');}});
        self::test('25 · edit readonly negado com token válido',static function():void{$h=self::harness(null,null,'en-us',false);self::valid($h,self::editPayload($h->store));$h->controller->edit('2');self::ok($h->store->edits===[]&&$h->response->output===''&&str_contains($h->response->location,'error/permission'),'ACL edit não interrompeu');});
        self::test('26 · edit válido mantém código e altera só metadados',static function():void{$h=self::harness();self::valid($h,['name'=>'Português QA','locale'=>'pt_BR.UTF-8','sort_order'=>'7','status'=>'1']);$h->controller->edit('2');$r=$h->store->records[2];self::ok($r['name']==='Português QA'&&$r['sort_order']===7&&$r['code']==='pt-br'&&$h->settings->writes===[],'Metadados/código/padrões misturados');});
        self::test('27 · não desativa default de qualquer aplicação',static function():void{foreach([1,2]as$id){$h=self::harness();$h->config->values['config_language_catalog']='pt-br';$p=self::editPayload($h->store,$id);unset($p['status']);self::valid($h,$p);$h->controller->edit((string)$id);self::ok($h->store->edits===[]&&str_contains($h->loader->data['error'],$h->language->get('error_disable_default')),'Default desativado');}});
        self::test('28 · idioma não padrão pode desativar em fixture sem trocar defaults',static function():void{$h=self::harness();$p=self::editPayload($h->store,3);unset($p['status']);self::valid($h,$p);$h->controller->edit('3');self::ok($h->store->records[3]['status']===0&&$h->settings->writes===[]&&$h->config->get('config_language_admin')==='en-us','Status/padrões mudaram indevidamente');});
        self::test('29 · erro repovoa POST e unchecked sem static-this fatal',static function():void{$h=self::harness();self::valid($h,['name'=>'','locale'=>'pt_QA.UTF-8','sort_order'=>'9']);$h->controller->edit('2');self::ok($h->store->edits===[]&&$h->loader->data['entry']['name']===''&&$h->loader->data['entry']['locale']==='pt_QA.UTF-8'&&$h->loader->data['entry']['sort_order']===9&&$h->loader->data['entry']['status']===0&&$h->loader->data['error']===$h->language->get('error_name'),'Repovo/callback de erro inválidos');});
        self::test('30 · POST code forjado é ignorado',static function():void{$h=self::harness();$p=self::editPayload($h->store);$p['code']='evil';self::valid($h,$p);$h->controller->edit('2');self::ok($h->store->records[2]['code']==='pt-br','Código trocado');});
        self::test('31 · limites32 de name/locale preservados',static function():void{foreach(['name','locale']as$key){$h=self::harness();$p=self::editPayload($h->store);$p[$key]=str_repeat('ã',33);self::valid($h,$p);$h->controller->edit('2');self::ok($h->store->edits===[]&&str_contains($h->loader->data['error'],$h->language->get('error_'.$key)),'Teto32 perdido');}});
        self::test('32 · default-lock tem checkbox disabled e status hidden1',static function():void{$h=self::harness();$h->controller->edit('1');$html=$h->response->output;self::ok(str_contains($html,'id="language-status" value="1" checked disabled')&&str_contains($html,'type="hidden" name="status" value="1"')&&str_contains($html,$h->language->get('text_default_locked')),'UI permite apagar default');});
        self::test('33 · readonly edit disabled sem Save e código visível',static function():void{$h=self::harness(null,null,'en-us',false);$h->controller->edit('2');self::ok(str_contains($h->response->output,'<fieldset disabled>')&&!str_contains($h->response->output,'type="submit"')&&str_contains($h->response->output,'value="pt-br"'),'Readonly edit inválido');});
        self::test('34 · i18n3 Twig strict e dados POST escapados',static function():void{$keys=null;foreach(['en-us','pt-br','es-eu']as$locale){$h=self::harness(null,null,$locale);$h->controller->index();self::ok(str_contains($h->response->output,$h->language->get('heading_title')),'Heading não traduzido');$h->controller->edit('2');$k=array_keys($h->language->values);sort($k);if($keys!==null)self::ok($keys===$k,'Chaves divergentes');$keys=$k;}$h=self::harness();self::valid($h,['name'=>'<script>x</script>','locale'=>'','sort_order'=>'2','status'=>'1']);$h->controller->edit('2');self::ok(!str_contains($h->response->output,'<script>x')&&str_contains($h->response->output,'&lt;script&gt;'),'POST não escapado');});
        self::test('35 · falta de diretório sinaliza sem criar/alterar nada',static function():void{$h=self::harness(self::populated(4));$h->request->get['filter_search']='qa-004';$h->controller->index();self::ok(!$h->loader->data['languages'][0]['dir_exists']&&str_contains($h->response->output,$h->language->get('text_dir_missing'))&&$h->store->edits===[]&&!is_dir(DIR_APPLICATION.'language/qa-004'),'Diretório foi criado ou aviso perdido');});
        self::test('36 · CSRF estável da lista para edit na mesma sessão',static function():void{$h=self::harness();$h->controller->index();$token=$h->loader->data['csrf_token'];$get=self::harness($h->store,$h->session);$get->controller->edit('2');self::ok(strlen($token)===64&&$get->loader->data['csrf_token']===$token,'Token de sessão mudou');});
        self::test('37 · defaults salvos aplicam no GET seguinte da fixture',static function():void{$h=self::harness();self::valid($h,['config_language_catalog'=>'pt-br','config_language_admin'=>'es-eu']);$h->controller->index();$get=self::harness($h->store,$h->session,'en-us',true,true,$h->settings);$get->controller->index();self::ok($get->loader->data['default_catalog']==='pt-br'&&$get->loader->data['default_admin']==='es-eu','Roundtrip defaults em memória falhou');});
        self::test('38 · sem add/delete/upload, opções continuam instaladas',static function():void{$h=self::harness();$h->controller->index();self::ok(!method_exists(Controller::class,'add')&&!method_exists(Controller::class,'delete')&&!method_exists(Controller::class,'upload')&&count($h->store->records)===3&&!str_contains($h->response->output,'language.add'),'Inventou idioma/CRUD novo');});
        self::test('39 · assets locais versionados e sem JS runtime/inline novo',static function():void{$h=self::harness();$h->controller->index();self::ok(str_contains(implode('',$h->document->styles),'admin.css?v=')&&$h->document->scripts===[]&&!str_contains($h->response->output,'<script>'),'Assets incorretos');});
        self::test('40 · sucesso edit interrompe PRG e deixa flash sucesso',static function():void{$h=self::harness();self::valid($h,self::editPayload($h->store));$h->controller->edit('2');self::ok(count($h->store->edits)===1&&$h->response->output===''&&($h->session->data['flash']['text']??'')===$h->language->get('text_success_edit'),'Fluxo continuou após redirect');});
        echo"\nF8-11 contratos: ".self::$passed.' PASS / '.self::$failed.' FAIL — PHP '.PHP_VERSION.', Twig '.\Twig\Environment::VERSION."; DB/serviços/IO sessão em fixtures, diretórios privados; sem idioma/default/banco/HTTP/browser reais alterados.\n";return self::$failed?1:0;
    }
}
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool{if(!(error_reporting()&$severity))return false;throw new \ErrorException($message,0,$severity,$file,$line);});
try{$code=Suite::run($mirror);}finally{DirsFixture::cleanup();}
exit($code);
