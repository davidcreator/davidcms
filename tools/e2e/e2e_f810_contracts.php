<?php declare(strict_types=1);
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2026, ReamurCMS
 * @license https://opensource.org/licenses/GPL-3.0
 * F8-10 — PHP/Twig reais; DB/serviços/IO de sessão em fixtures.
 * Nenhuma mensagem real, banco, e-mail ou HTTP da aplicação é acessado.
 */
namespace Reamur\Tests\F810;
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Session;
use Reamur\System\Library\Security\Csrf;
use Reamur\Admin\Controller\Communication\Contact_message as Controller;
use Reamur\Admin\Model\Communication\Contact_message as Model;
$mirror=realpath($argv[1]??dirname(__DIR__,2).'/dev')?:'';
$autoload=getenv('TWIG_AUTOLOAD')?:$mirror.'/system/storage/vendor/autoload.php';
if($mirror===''||!is_file($autoload)){fwrite(STDERR,"MIRROR/Twig ausentes.\n");exit(2);}
require_once $autoload;
if(!class_exists(\Twig\Environment::class)||!extension_loaded('mbstring')){fwrite(STDERR,"Requer Twig3 e mbstring.\n");exit(2);}
if(!defined('DB_PREFIX'))define('DB_PREFIX','f810_');
if(!defined('DIR_SYSTEM'))define('DIR_SYSTEM',$mirror.'/system/');
foreach(['engine/registry.php','engine/controller.php','engine/model.php','library/session.php','library/security/csrf.php','library/pagination.php']as$p)require_once DIR_SYSTEM.$p;
require_once $mirror.'/admin/controller/common/header.php';
require_once $mirror.'/admin/model/communication/contact_message.php';
require_once $mirror.'/admin/controller/communication/contact_message.php';

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
    public array $records=[];public array $calls=[];public array $updates=[];public array $deleted=[];
    private function filtered(?int $status,array $o):array {
        $rows=array_filter(array_values($this->records),static function(array $r)use($status,$o):bool{
            if($status!==null&&$r['status']!==$status)return false;
            $q=$o['search']??'';return $q===''||mb_stripos($r['name'],$q)!==false||mb_stripos($r['email'],$q)!==false||mb_stripos($r['subject'],$q)!==false;
        });usort($rows,static fn(array $a,array $b):int=>[$b['date_added'],$b['contact_message_id']]<=>[$a['date_added'],$a['contact_message_id']]);return array_values($rows);
    }
    public function getMessages(?int $status=null,array $o=[]):array{$this->calls[]=[$status,$o];$rows=$this->filtered($status,$o);return isset($o['limit'])?array_slice($rows,$o['start'],$o['limit']):$rows;}
    public function getTotalMessages(?int $status=null,array $o=[]):int{return count($this->filtered($status,$o));}
    public function getCounts(array $o=[]):array{$c=['all'=>0,'new'=>0,'read'=>0,'replied'=>0];foreach($this->filtered(null,$o)as$r){$c['all']++;$key=match($r['status']){1=>'read',2=>'replied',default=>'new'};$c[$key]++;}return$c;}
    public function getMessage(int $id):array{return$this->records[$id]??[];}
    public function updateStatus(int $id,int $status):bool{if(!in_array($status,Model::STATUSES,true))return false;$this->updates[]=[$id,$status];if(isset($this->records[$id]))$this->records[$id]['status']=$status;return true;}
    public function deleteMessage(int $id):void{$this->deleted[]=$id;unset($this->records[$id]);}
}
final class LoaderFixture {
    public array $data = [];
    private \Twig\Environment $twig;
    public function __construct(private string $mirror) {
        $this->twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader($mirror . '/admin/view/template'), ['cache' => false, 'autoescape' => 'html', 'strict_variables' => true]);
    }
    public function language(string $route): void { if ($route !== 'communication/contact_message') { throw new \RuntimeException('Rota de language inesperada'); } }
    public function model(string $route): void { if ($route !== 'communication/contact_message') { throw new \RuntimeException('Model inesperado'); } }
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
        $this->language = new LanguageFixture($mirror . '/admin/language/' . $locale . '/communication/contact_message.php');
        foreach (['request' => $this->request, 'response' => $this->response, 'load' => $this->loader, 'document' => $this->document, 'language' => $this->language, 'config' => new ConfigFixture(), 'user' => new UserFixture($modify), 'session' => $this->session, 'url' => new UrlFixture(), 'model_communication_contact_message' => $this->store] as $key => $value) { $this->registry->set($key, $value); }
        $this->controller = new Controller($this->registry);
    }
    public function post(array $data): void { $this->request->server['REQUEST_METHOD'] = 'POST'; $this->request->post = $data; }
}
final class DbFixture {
    public array $queries=[];public array $rows=[];public array $row=[];
    public function escape(string $s):string{return addslashes($s);}
    public function query(string $sql):\stdClass{$this->queries[]=$sql;return(object)['rows'=>$this->rows,'row'=>$this->row?:['total'=>7]];}
}
final class Suite {
    private static int $passed=0;private static int $failed=0;private static string $mirror;
    private static function ok(bool $test,string $message):void{if(!$test)throw new \RuntimeException($message);}
    private static function test(string $label,callable $fn):void{try{$fn();self::$passed++;echo'PASS '.$label.PHP_EOL;}catch(\Throwable $e){self::$failed++;fwrite(STDERR,'FAIL '.$label.': '.$e->getMessage().PHP_EOL);}}
    private static function record(int $id=101,int $status=0):array{return['contact_message_id'=>$id,'name'=>'QA Message '.$id,'email'=>'fixture-'.$id.'@example.invalid','telephone'=>'','subject'=>'QA subject '.$id,'message'=>"Linha ação ñ\n<b>Texto literal</b>\nSem dados reais.",'ip'=>'203.0.113.9','status'=>$status,'date_added'=>'2026-10-23 12:00:00'];}
    private static function populated(int $count):StoreFixture{$s=new StoreFixture();for($i=0;$i<$count;$i++)$s->records[101+$i]=self::record(101+$i,$i%3);return$s;}
    private static function harness(?StoreFixture $s=null,?Session $session=null,string $locale='en-us',bool $modify=true):Harness{return new Harness(self::$mirror,$s,$session,$locale,$modify);}
    private static function model():array{$r=new Registry();$db=new DbFixture();$r->set('db',$db);return[new Model($r),$db];}
    private static function valid(Harness $h,array $p=[]):void{$h->post($p+['csrf_token'=>Csrf::token($h->session)]);}
    public static function run(string $mirror):int{
        self::$mirror=$mirror;
        self::test('01 · chamada antiga getMessages completa/mais recentes primeiro',static function():void{[$m,$db]=self::model();$m->getMessages();$q=end($db->queries);self::ok(!str_contains($q,' LIMIT ')&&!str_contains($q,' WHERE ')&&str_ends_with($q,'ORDER BY `date_added` DESC, `contact_message_id` DESC'),'Contrato antigo alterado');});
        self::test('02 · getMessages(?int) preserva filtro antigo inclusive inválido',static function():void{[$m,$db]=self::model();foreach([0,1,2,9]as$s){$m->getMessages($s);self::ok(str_contains((string)end($db->queries),"WHERE `status` = '".$s."'")&&!str_contains((string)end($db->queries),'LIMIT'),'Filtro do model mudou para todas');}});
        self::test('03 · busca escapa e usa só nome/email/assunto com recorte',static function():void{[$m,$db]=self::model();$m->getMessages(0,['search'=>"O'Brien",'start'=>20,'limit'=>20]);$q=end($db->queries);self::ok(str_contains($q,"O\\'Brien")&&str_contains($q,'`name` LIKE')&&str_contains($q,'`email` LIKE')&&str_contains($q,'`subject` LIKE')&&!str_contains($q,'`message` LIKE')&&str_ends_with($q,'LIMIT 20, 20'),'Busca/privacidade/recorte inválidos');});
        self::test('04 · count/list mesmos predicados, limites normalizados',static function():void{[$m,$db]=self::model();$o=['search'=>'x'];self::ok($m->getTotalMessages(1,$o)===7,'Total errado');$count=end($db->queries);$m->getMessages(1,$o);$q=end($db->queries);$q=substr($q,strpos($q,'FROM '));$q=substr($q,0,strpos($q,' ORDER BY'));self::ok(substr($count,strpos($count,'FROM '))===$q,'Predicados divergentes');$m->getMessages(null,['search'=>[],'start'=>-1,'limit'=>999]);self::ok(str_ends_with((string)end($db->queries),'LIMIT 0, 100'),'Limites inválidos');});
        self::test('05 · counts global preservado e busca opcional nas abas',static function():void{[$m,$db]=self::model();$db->rows=[['status'=>0,'total'=>3],['status'=>1,'total'=>2],['status'=>2,'total'=>1],['status'=>9,'total'=>4]];self::ok($m->getCounts()===['all'=>10,'new'=>7,'read'=>2,'replied'=>1]&&!str_contains((string)end($db->queries),'WHERE'),'Counts antigo alterado');$m->getCounts(['search'=>'needle']);self::ok(str_contains((string)end($db->queries),'needle')&&str_ends_with((string)end($db->queries),'GROUP BY `status`'),'Counts não acompanha busca');});
        self::test('06 · model get/update/delete mantém contrato e whitelist',static function():void{[$m,$db]=self::model();$m->getMessage(143);self::ok(str_contains((string)end($db->queries),"`contact_message_id` = '143'"),'Get ID mudou');foreach([0,1,2]as$s)self::ok($m->updateStatus(143,$s),'Status válido rejeitado');$n=count($db->queries);self::ok(!$m->updateStatus(143,9)&&!$m->updateStatus(143,-1)&&count($db->queries)===$n,'Model aceitou status inválido');$m->deleteMessage(143);self::ok(str_contains((string)end($db->queries),"DELETE FROM `f810_contact_message` WHERE `contact_message_id` = '143'"),'Delete ID mudou');});
        self::test('07 · índice vazio honesto/sem add-edit-upload',static function():void{$h=self::harness();$h->controller->index();self::ok($h->loader->data['total_messages']===0&&str_contains($h->response->output,'No messages yet')&&!str_contains($h->response->output,'contact_message.add')&&!method_exists(Controller::class,'add')&&!method_exists(Controller::class,'edit')&&!method_exists(Controller::class,'upload'),'CRUD assimétrico alterado');});
        self::test('08 · 45 mensagens/página dois20/ordem desc',static function():void{$h=self::harness(self::populated(45));$h->request->get['page']='2';$h->controller->index();self::ok(count($h->loader->data['messages'])===20&&$h->loader->data['total_messages']===45&&end($h->store->calls)[1]['start']===20,'Paginação inválida');});
        self::test('09 · recuo de página e query array não quebram',static function():void{$h=self::harness(self::populated(45));$h->request->get['page']=(string)PHP_INT_MAX;$h->controller->index();self::ok(count($h->loader->data['messages'])===5&&end($h->store->calls)[1]['start']===40,'Recuo inválido');$h=self::harness();$h->request->get=['filter_search'=>[],'filter_status'=>[],'page'=>[]];$h->controller->index();self::ok($h->loader->data['filter_search']===''&&$h->loader->data['filter_status']==='all','Query array não normalizada');});
        self::test('10 · filtro canônico0/1/2; x/all/01 não viram Novo',static function():void{foreach(['x','all','01','',[],true,0.2]as$v){$h=self::harness(self::populated(3));$h->request->get['filter_status']=$v;$h->controller->index();self::ok($h->loader->data['filter_status']==='all'&&$h->loader->data['total_messages']===3,'Filtro inválido virou status0');}foreach(['0','1','2']as$v){$h=self::harness(self::populated(3));$h->request->get['filter_status']=$v;$h->controller->index();self::ok($h->loader->data['filter_status']===$v&&$h->loader->data['total_messages']===1,'Status válido perdido');}});
        self::test('11 · busca+status/contadores de abas/hrefs coerentes',static function():void{$s=self::populated(6);foreach([101,102,103]as$id)$s->records[$id]['subject']='needle';$h=self::harness($s);$h->request->get=['filter_search'=>'needle','filter_status'=>'1'];$h->controller->index();self::ok($h->loader->data['total_messages']===1&&array_column($h->loader->data['filters'],'count')===[3,1,1,1],'Count das abas perdeu busca');foreach($h->loader->data['filters']as$f)self::ok(str_contains($f['href'],'filter_search=needle'),'Href perdeu busca');});
        self::test('12 · busca zero conserva reset',static function():void{$h=self::harness();$h->request->get['filter_search']='0';$h->controller->index();self::ok(str_contains($h->response->output,'>Reset</a>'),'Busca0 sem reset');});
        self::test('13 · paginação mantém busca/status sem duplo escape/XSS',static function():void{$s=self::populated(75);foreach($s->records as&$r)$r['subject']="O'Brien & <needle>";unset($r);$h=self::harness($s);$h->request->get=['filter_search'=>"O'Brien & <needle>",'filter_status'=>'0'];$h->controller->index();$html=$h->loader->data['pagination'];self::ok(!str_contains($html,'&amp;amp;')&&!str_contains($h->response->output,'<needle>'),'Escape inválido');preg_match_all('/href="([^"]+)"/',$html,$m);self::ok(count($m[1])>0,'Pager ausente');foreach($m[1]as$a){parse_str((string)parse_url(html_entity_decode($a,ENT_QUOTES|ENT_HTML5,'UTF-8'),PHP_URL_QUERY),$q);self::ok(($q['filter_search']??'')==="O'Brien & <needle>"&&($q['filter_status']??'')==='0','Filtro perdido');}});
        self::test('14 · readonly lista usa GET e não exibe formulários mutáveis',static function():void{$h=self::harness(self::populated(1),null,'en-us',false);$h->controller->index();self::ok(str_contains($h->response->output,'route=communication/contact_message.read|101')&&!str_contains($h->response->output,'data-message-open')&&!str_contains($h->response->output,'data-rms-confirm-delete')&&!str_contains($h->response->output,'method="post"'),'Readonly pode mutar pela UI');});
        self::test('15 · modify lista Ler é POST com CSRF/modal',static function():void{$h=self::harness(self::populated(2));$h->controller->index();self::ok(substr_count($h->response->output,'data-message-open')===2&&substr_count($h->response->output,'name="csrf_token"')===4&&substr_count($h->response->output,'data-rms-confirm-delete=')===2&&$h->store->updates===[],'Ler não protegido ou lista marcou estado');});
        self::test('16 · GET detalhe Novo permanece Novo, sem update',static function():void{$h=self::harness(self::populated(1));$h->controller->read('101');self::ok($h->store->updates===[]&&$h->store->getMessage(101)['status']===0&&$h->loader->data['message']['status']===0&&str_contains($h->response->output,'data-message-body'),'GET ainda muta');});
        self::test('17 · readonly GET não grava e só mostra conteúdo/reply mailto',static function():void{$h=self::harness(self::populated(1),null,'en-us',false);$h->controller->read('101');self::ok($h->store->updates===[]&&!str_contains($h->response->output,'method="post"')&&str_contains($h->response->output,'mailto:fixture-101@example.invalid')&&str_contains($h->response->output,'changing status'),'Readonly leitura indevida');});
        self::test('18 · POST Ler válido Novo→Lido e redireciona GET',static function():void{$h=self::harness(self::populated(1));self::valid($h);$h->controller->read('101');self::ok($h->store->updates===[[101,1]]&&$h->store->getMessage(101)['status']===1&&str_contains($h->response->location,'read|101')&&$h->response->output==='','POST Ler não usa PRG');});
        self::test('19 · POST Ler não regrava Lido/Respondido',static function():void{foreach([1,2]as$status){$s=self::populated(1);$s->records[101]['status']=$status;$h=self::harness($s);self::valid($h);$h->controller->read('101');self::ok($s->updates===[]&&$s->getMessage(101)['status']===$status,'POST Ler sobrescreveu estado');}});
        self::test('20 · Ler sem token/array/errado não atualiza',static function():void{foreach([null,[],'wrong']as$t){$h=self::harness(self::populated(1));$h->post($t===null?[]:['csrf_token'=>$t]);$h->controller->read('101');self::ok($h->store->updates===[]&&$h->store->getMessage(101)['status']===0&&($h->session->data['flash']['text']??'')===$h->language->get('error_csrf'),'CSRF Ler falhou');}});
        self::test('21 · Ler POST readonly mesmo token válido negado',static function():void{$h=self::harness(self::populated(1),null,'en-us',false);self::valid($h);$h->controller->read('101');self::ok($h->store->updates===[]&&str_contains($h->response->location,'error/permission'),'Ler sem modify gravou');});
        self::test('22 · reload GET após POST não repete marcação',static function():void{$s=self::populated(1);$h=self::harness($s);self::valid($h);$h->controller->read('101');$get=self::harness($s,$h->session);$get->controller->read('101');self::ok(count($s->updates)===1&&$get->loader->data['message']['status']===1,'GET após PRG repetiu escrita');});
        self::test('23 · status Respondido/Novo com from=read permanece após GET',static function():void{$s=self::populated(1);foreach([2,0]as$status){$h=self::harness($s);self::valid($h,['status'=>(string)$status,'from'=>'read']);$h->controller->status('101');self::ok(str_contains($h->response->location,'read|101'),'from=read perdido');$get=self::harness($s,$h->session);$get->controller->read('101');self::ok($s->getMessage(101)['status']===$status&&$get->loader->data['message']['status']===$status,'GET desfez status explícito');}});
        self::test('24 · status x/9/01/array/float não coage para Novo',static function():void{foreach(['x','9','01',[],false,null,0.5,-1]as$v){$s=self::populated(1);$s->records[101]['status']=1;$h=self::harness($s);self::valid($h,['status'=>$v,'from'=>'read']);$h->controller->status('101');self::ok($s->updates===[]&&$s->getMessage(101)['status']===1&&($h->session->data['flash']['text']??'')===$h->language->get('error_status'),'Status inválido mutou');}});
        self::test('25 · status string0 é válido',static function():void{$s=self::populated(1);$s->records[101]['status']=1;$h=self::harness($s);self::valid($h,['status'=>'0']);$h->controller->status('101');self::ok($s->updates===[[101,0]]&&$s->getMessage(101)['status']===0,'Status0 rejeitado');});
        self::test('26 · status sem CSRF não atualiza',static function():void{$h=self::harness(self::populated(1));$h->post(['status'=>'2']);$h->controller->status('101');self::ok($h->store->updates===[]&&($h->session->data['flash']['text']??'')===$h->language->get('error_csrf'),'CSRF status falhou');});
        self::test('27 · GET status não muta',static function():void{$h=self::harness(self::populated(1));$h->request->get['status']='2';$h->controller->status('101');self::ok($h->store->updates===[]&&str_contains($h->response->location,'error/permission'),'GET status mutou');});
        self::test('28 · status readonly negado com token',static function():void{$h=self::harness(self::populated(1),null,'en-us',false);self::valid($h,['status'=>'2']);$h->controller->status('101');self::ok($h->store->updates===[]&&str_contains($h->response->location,'error/permission'),'ACL status falhou');});
        self::test('29 · GET delete não remove',static function():void{$h=self::harness(self::populated(1));$h->controller->delete('101');self::ok($h->store->deleted===[]&&str_contains($h->response->location,'error/permission'),'GET delete mutou');});
        self::test('30 · delete sem token/errado não remove',static function():void{foreach([[],['csrf_token'=>'wrong']]as$p){$h=self::harness(self::populated(1));$h->post($p);$h->controller->delete('101');self::ok($h->store->deleted===[]&&($h->session->data['flash']['text']??'')===$h->language->get('error_csrf'),'CSRF delete falhou');}});
        self::test('31 · delete válido só ID próprio',static function():void{$h=self::harness(self::populated(2));self::valid($h);$h->controller->delete('101');self::ok($h->store->deleted===[101]&&!$h->store->getMessage(101)&&$h->store->getMessage(102)!==[],'Delete atingiu outra mensagem');});
        self::test('32 · delete readonly negado',static function():void{$h=self::harness(self::populated(1),null,'en-us',false);self::valid($h);$h->controller->delete('101');self::ok($h->store->deleted===[]&&str_contains($h->response->location,'error/permission'),'ACL delete falhou');});
        self::test('33 · IDs inexistentes mantêm contratos sem mutar',static function():void{$h=self::harness();$h->controller->read('999');self::ok(str_contains($h->response->location,'error/not_found'),'Read 404 perdido');foreach(['status','delete']as$m){$h=self::harness();self::valid($h,['status'=>'2']);$h->controller->{$m}('999');self::ok($h->store->updates===[]&&$h->store->deleted===[]&&str_contains($h->response->location,'communication/contact_message'),'ID inexistente mutou');}});
        self::test('34 · três idiomas com mesmas chaves e Twig strict',static function():void{$keys=null;foreach(['en-us','pt-br','es-eu']as$locale){$h=self::harness(self::populated(1),null,$locale);$h->controller->index();self::ok(str_contains($h->response->output,$h->language->get('heading_title')),'Heading não traduzido');$h->controller->read('101');$k=array_keys($h->language->values);sort($k);if($keys!==null)self::ok($k===$keys,'Chaves divergentes');$keys=$k;}});
        self::test('35 · conteúdo de mensagem/nome/assunto é texto escapado',static function():void{$s=self::populated(1);$s->records[101]['name']='<script>name</script>';$s->records[101]['subject']='<img src=x onerror=alert(1)>';$s->records[101]['message']="<script>body</script>\n<b>literal</b>";$h=self::harness($s);$h->controller->read('101');self::ok(!str_contains($h->response->output,'<script>body')&&!str_contains($h->response->output,'<img src=x')&&str_contains($h->response->output,'&lt;script&gt;body')&&str_contains($h->response->output,'&lt;b&gt;literal'),'Mensagem executada como HTML');});
        self::test('36 · detalhe sem edição/upload/editor/JS inline e estilo local',static function():void{$h=self::harness(self::populated(1));$h->controller->read('101');self::ok(!str_contains($h->response->output,'<textarea')&&!str_contains($h->response->output,'type="file"')&&!str_contains($h->response->output,'<script>')&&!str_contains($h->response->output,'data-rte'),'Inventou edição/upload/editor');$css=file_get_contents(self::$mirror.'/admin/view/css/admin.css');self::ok(str_contains($css,'.contact-message-read .message-body')&&str_contains($css,'white-space: pre-wrap'),'CSS local não preserva texto');});
        self::test('37 · mailto pré-preenchido permanece só link codificado',static function():void{$s=self::populated(1);$s->records[101]['subject']='QA & teste';$h=self::harness($s);$h->controller->read('101');$link=$h->loader->data['reply_href'];self::ok(str_starts_with($link,'mailto:fixture-101@example.invalid?subject=Re%3A%20QA%20%26%20teste')&&str_contains($link,'&body=%0A%0A---%0A'),'Resposta URL/citação alterada');});
        self::test('38 · busca não lê corpo; GET nunca altera dado de mensagem',static function():void{$s=self::populated(1);$s->records[101]['message']='needle-body-only';$h=self::harness($s);$h->request->get['filter_search']='needle-body-only';$h->controller->index();self::ok($h->loader->data['total_messages']===0&&$s->updates===[],'Busca do corpo ou efeito de leitura indevido');});
        self::test('39 · badges temáticos/estado atual sem cor inline ou confirm',static function():void{foreach([0,1,2]as$status){$s=self::populated(1);$s->records[101]['status']=$status;$h=self::harness($s);$h->controller->read('101');self::ok(str_contains($h->response->output,'data-contact-status="'.$status.'"')&&!str_contains($h->response->output,'onsubmit=')&&!preg_match('/#[0-9a-f]{6}/i',$h->response->output),'Badge/inline legado persiste');}});
        self::test('40 · formulários status0/1/2/from/read e CSRF corretos',static function():void{$h=self::harness(self::populated(1));$h->controller->read('101');$html=$h->response->output;self::ok(substr_count($html,'data-message-status-form')===3&&substr_count($html,'name="from" value="read"')===3&&substr_count($html,'name="csrf_token"')===4&&str_contains($html,'data-rms-confirm-delete='),'Formulários de ações incompletos');foreach(['0','1','2']as$v)self::ok(str_contains($html,'name="status" value="'.$v.'"'),'Status action perdido');});
        echo"\nF8-10 contratos: ".self::$passed.' PASS / '.self::$failed.' FAIL — PHP '.PHP_VERSION.', Twig '.\Twig\Environment::VERSION."; DB/serviços/IO sessão em fixtures, sem mensagens/e-mails/HTTP/banco/navegador reais.\n";return self::$failed?1:0;
    }
}
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool{if(!(error_reporting()&$severity))return false;throw new \ErrorException($message,0,$severity,$file,$line);});
exit(Suite::run($mirror));
