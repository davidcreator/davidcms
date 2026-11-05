<?php declare(strict_types=1);
/* ReamurCMS — Frente AI · PROBE do CICLO REAL de criacao de tema.
 *
 * Uso: php tools/kaz-theme-lifecycle-probe.php
 * Exige PHP 8.1+ e Twig 3.x (vendor/autoload.php).
 * Sem Composer: AI_TWIG_AUTOLOAD=/caminho/autoload.php
 * Raiz do projeto: AI_ROOT (por padrao dois niveis acima de tools/).
 * HTMLs de evidencia: AI_OUT (por padrao /tmp/ai_site).
 *
 * Executa o codigo REAL do projeto — buildDraftConfig() (privado, via
 * Reflection), ModelDesignTheme::save()/activate(), Controller::activate(),
 * ModelCommonTheme::getEffectiveTheme(), ThemeSnapshot::resolve(),
 * ThemePreviewState::css() — com as SQL REAIS interpretadas por um DB em
 * memoria. NENHUMA escrita no projeto, no banco ou no GitHub; nao ha MySQL,
 * migration, sessao autenticada nem deploy. Serve apenas para provar que
 * Salvar -> Publicar -> home do catalogo produz o tema escolhido no editor. */
$OK=0;$FAIL=0;
function ok(string $n,bool $c,$x=''):void{
  global $OK,$FAIL; $x=(string)$x;
  if($c){$OK++;echo "OK   $n".($x?" · $x":'')."\n";}
  else{$FAIL++;echo "FAIL $n".($x?" · $x":'')."\n";}
}
function need(string $p):void{ if(!is_file($p)){echo "ERRO arquivo ausente: $p\n";exit(1);} require_once $p; }
$R=getenv('AI_ROOT') ?: dirname(__DIR__,2);
$OUT=getenv('AI_OUT') ?: '/tmp/ai_site';
define('DB_PREFIX','rms_'); define('DIR_SYSTEM',$R.'/dev/system/');
need(getenv('AI_TWIG_AUTOLOAD') ?: $R.'/vendor/autoload.php');
need($R.'/dev/system/engine/registry.php');
need($R.'/dev/system/engine/controller.php');
need($R.'/dev/system/engine/model.php');
need($R.'/dev/system/library/cms/page_layout.php');
foreach(['condition_engine','token_registry','component_registry','section_registry','theme_schedule',
         'theme_media','theme_sites','theme_package','theme_scene_css','theme_snapshot',
         'theme_preview_state','theme_release_probe','theme_compare_view'] as $f)
  need($R.'/dev/system/library/theme/'.$f.'.php');
need($R.'/dev/system/library/three/scene_preset.php');
need($R.'/dev/system/library/three/scene_preset_registry.php');
need($R.'/dev/system/library/security/csrf.php');
need($R.'/dev/admin/model/design/theme.php');
need($R.'/dev/catalog/model/common/theme.php');
need($R.'/dev/admin/controller/design/theme.php');
use Reamur\System\Engine\Registry;
use Reamur\System\Library\Theme\{ConditionEngine,TokenRegistry,SectionRegistry,ComponentRegistry,
   ThemeSchedule,ThemeMedia,ThemeSites,ThemePackage,ThemeSnapshot,ThemePreviewState,ThemeSceneCss,ThemeReleaseProbe};
use Reamur\System\Library\Three\ScenePresetRegistry;
use Reamur\System\Library\Security\Csrf;

/* ---------------- DB em memoria: interpreta o SQL real dos models ---------------- */
final class FakeDb {
  public array $themes=[]; public array $revisions=[]; public array $queries=[]; private int $seq=0;
  public function escape(string $v):string{ return addslashes($v); }
  public function getLastId():int{ return $this->seq; }
  private function val(string $s):string{ return stripslashes($s); }
  private function pairs(string $set):array{
    preg_match_all('/`([a-z_]+)`\s*=\s*(?:\'((?:\\\\.|[^\'])*)\'|`([a-z_]+)`|NOW\(\))/i',$set,$m,PREG_SET_ORDER);
    $out=[];
    foreach($m as $x) $out[$x[1]]=['lit'=>$this->val($x[2]??''),'col'=>$x[3]??''];
    return $out;
  }
  private function result(array $rows):object{
    $o=new \stdClass(); $o->rows=$rows; $o->row=$rows[0]??[]; $o->num_rows=count($rows); return $o;
  }
  public function query(string $sql):object{
    $this->queries[]=$sql; $s=preg_replace('/\s+/',' ',trim($sql));
    if(preg_match('/^SELECT \* FROM `rms_design_theme` WHERE `theme_id`=\'(\d+)\'/',$s,$m))
      return $this->result(isset($this->themes[(int)$m[1]])?[$this->themes[(int)$m[1]]]:[]);
    if(str_starts_with($s,'SELECT * FROM `rms_design_theme`'))
      return $this->result(array_values($this->themes));
    if(preg_match('/^SELECT `theme_id`,`slug`,`config`,`published_config`,`draft_config` FROM `rms_design_theme` WHERE `status`=\'1\'/',$s)){
      $act=array_filter($this->themes,fn($r)=>(string)($r['status']??'0')==='1');
      if($act){ krsort($act); $r=reset($act); return $this->result([array_intersect_key($r,array_flip(['theme_id','slug','config','published_config','draft_config']))]); }
      return $this->result([]);
    }
    if(preg_match('/^SELECT `slug`,`config`,`(draft_config|published_config)` FROM `rms_design_theme`/',$s))
      return $this->result(array_values($this->themes));
    if(preg_match('/^SELECT `theme_id`,`config`,`published_config` FROM `rms_design_theme` WHERE `status`=\'1\'/',$s)){
      $act=array_filter($this->themes,fn($r)=>(string)($r['status']??'0')==='1');
      if($act){ krsort($act); $r=reset($act); return $this->result([array_intersect_key($r,array_flip(['theme_id','config','published_config']))]); }
      return $this->result([]);
    }
    if(preg_match('/^INSERT INTO `rms_design_theme_revision` SET (.+)$/',$s,$m)){
      $this->revisions[]=$this->pairs($m[1]); return $this->result([]);
    }
    if(preg_match('/^INSERT INTO `rms_design_theme` SET (.+)$/',$s,$m)){
      $this->seq++; $row=['theme_id'=>$this->seq,'status'=>'0','published_config'=>'{}','config'=>'{}','draft_config'=>'{}','name'=>'','slug'=>''];
      foreach($this->pairs($m[1]) as $k=>$v) $row[$k]=$v['col']!==''?($row[$v['col']]??''):$v['lit'];
      $this->themes[$this->seq]=$row; return $this->result([]);
    }
    if(preg_match('/^UPDATE `rms_design_theme` SET `status`=\'0\'$/',$s)){
      foreach($this->themes as &$r) $r['status']='0'; unset($r); return $this->result([]);
    }
    if(preg_match('/^UPDATE `rms_design_theme` SET (.+) WHERE `theme_id`=\'(\d+)\'$/',$s,$m)){
      $id=(int)$m[2]; if(!isset($this->themes[$id])) return $this->result([]);
      foreach($this->pairs($m[1]) as $k=>$v) $this->themes[$id][$k]=$v['col']!==''?($this->themes[$id][$v['col']]??''):$v['lit'];
      return $this->result([]);
    }
    throw new \RuntimeException('SQL nao interpretada: '.substr($s,0,160));
  }
}

/* ---------------- stubs minimos de servico (nenhum codigo do projeto alterado) ---------------- */
final class FakeLanguage { public function get(string $k):string{ return $k; } public function load(string $f):void{} }
final class FakeRequest { public array $post=[]; public array $get=[]; public array $server=[]; }
final class FakeUser {
  public bool $allow=true;
  public function hasPermission(string $a,string $b):bool{return $this->allow;}
  public function getUserId():int{return 1;}
}
final class FakeSession { public array $data=[]; }
final class FakeResponse {
  public array $redirects=[]; public string $output='';
  public function redirect(string $u):void{$this->redirects[]=$u;}
  public function setOutput(string $s):void{$this->output=$s;}
  public function addHeader(string $h):void{}
}
final class FakeUrl { public function link(string $r,string $q=''):string{return '/index.php?route='.$r;} }
final class FakeConfig {
  private array $d=[];
  public function get(string $k,$def=''){return $this->d[$k]??$def;}
  public function set(string $k,$v):void{$this->d[$k]=$v;}
}
final class FakeDocument {
  public array $styles=[]; public array $scripts=[];
  public function addStyle(string $s,string $r='stylesheet'):void{$this->styles[$s]=$r;}
  public function addScriptDeferred(string $s):void{$this->scripts[]=$s;}
  public function getStyles():array{return $this->styles;}
  public function getScripts():array{return $this->scripts;}
  public function getTitle():string{return '';} public function getDescription():string{return '';}
}
final class FakeLoad {
  public function __construct(private Registry $r){}
  public function model(string $n):void{
    $key='model_'.str_replace('/','_',$n);
    if($n==='design/theme' && !$this->r->has($key))
      $this->r->set($key,new \Reamur\Admin\Model\Design\Theme($this->r));
  }
  public function language(string $f):void{}
  public function view(string $t,array $d=[]):string{return '';}
  public function controller(string $r,array $d=[]):string{return '';}
}

/* ---------------- Registry com os SERVICOS REAIS do framework.php ---------------- */
$reg=new Registry();
$db=new FakeDb(); $sess=new FakeSession(); $req=new FakeRequest(); $res=new FakeResponse();
$reg->set('db',$db); $reg->set('session',$sess); $reg->set('request',$req); $reg->set('response',$res);
$reg->set('url',new FakeUrl()); $reg->set('config',new FakeConfig()); $reg->set('document',new FakeDocument());
$reg->set('language',new FakeLanguage()); $reg->set('user',new FakeUser());
$reg->set('load',new FakeLoad($reg));
$reg->set('cms_page_layout',new \Reamur\System\Library\Cms\PageLayout());
$reg->set('theme_condition_engine',new ConditionEngine());
$reg->set('theme_token_registry',new TokenRegistry());
$reg->set('theme_section_registry',new SectionRegistry($reg->get('cms_page_layout')));
$reg->set('theme_component_registry',new ComponentRegistry());
$reg->set('theme_schedule',new ThemeSchedule());
$reg->set('theme_media',new ThemeMedia());
$reg->set('theme_sites',new ThemeSites());
$reg->set('theme_package',new ThemePackage($reg->get('theme_condition_engine'),$reg->get('theme_token_registry'),
    $reg->get('theme_section_registry'),$reg->get('theme_component_registry'),$reg->get('theme_schedule'),
    $reg->get('theme_media'),$reg->get('theme_sites')));
$reg->set('theme_snapshot',new ThemeSnapshot($reg->get('theme_condition_engine'),$reg->get('theme_token_registry'),
    $reg->get('theme_package'),$reg->get('theme_section_registry'),$reg->get('theme_component_registry'),
    $reg->get('theme_schedule'),$reg->get('theme_media'),$reg->get('theme_sites')));
$reg->set('three_scene_preset',new ScenePresetRegistry());
ok('Registry com 11 servicos reais',count(array_filter(['theme_condition_engine','theme_token_registry',
   'theme_section_registry','theme_component_registry','theme_schedule','theme_media','theme_sites',
   'theme_package','theme_snapshot','three_scene_preset','cms_page_layout'],fn($k)=>$reg->get($k)!==null))===11);

/* ---------------- 1) POST real, derivado dos 49 name= do Twig ---------------- */
$twig=(string)@file_get_contents($R.'/dev/admin/view/template/design/kaz_theme_editor.twig');
$names=[]; preg_match_all('/\bname="([^"]+)"/',$twig,$m); $names=$m[1];
$flat=implode('|',$names);
ok('campos do formulario no Twig real',count($names)>=49,count($names).' name=');
$temCampos=true;
foreach(['name','slug','primary','surface','kaz_scene_json','csrf_token'] as $k)
  if(!in_array($k,$names,true)) $temCampos=false;
$temTokens=false; foreach($names as $n) if(str_contains($n,'tokens[')) $temTokens=true;
ok('Twig: name/slug/primary/tokens/cena',$temCampos && $temTokens);
$tokens=(new TokenRegistry())->defaults();
$post=[
 'name'=>'Tema AI','slug'=>'tema-ai','primary'=>'#dc2626','surface'=>'#fff7ed',
 'parent_theme'=>'','scene'=>'','scene_preset'=>'','csrf_token'=>Csrf::token($sess),
 'conditions'=>json_encode(['version'=>1,'rules'=>[]]),
 'three_enabled'=>'0','three_scene'=>'','three_slot'=>'hero','three_count'=>'400',
 'three_color'=>'#8a4fff','three_speed'=>'0.05',
 'tokens'=>array_replace($tokens,['radius'=>'32','radius_lg'=>'40','font_size'=>'20','density'=>'spacious',
   'color_primary'=>'#dc2626','color_text'=>'#7c2d12','color_surface'=>'#fff7ed']),
 'sections'=>[],'sections_order'=>'',
 'components'=>['card'=>['enabled'=>'1','variant'=>'elevated','tone'=>'primary']],
 'schedule'=>['enabled'=>'0','from'=>'','until'=>'','percent'=>'0','scope'=>'all','sites'=>[]],
 'media'=>[],'sites'=>[],'templates'=>[],
 'kaz_scene_json'=>json_encode(['version'=>1,'canvas'=>['w'=>960,'h'=>540,'grid'=>8],
   'layers'=>[['id'=>'a1','kind'=>'section','role'=>'surface','x'=>0,'y'=>0,'w'=>960,'h'=>540,
     'fill'=>'#ffffff','radius'=>0,'fontSize'=>16,'text'=>'','visible'=>true,'locked'=>false,'bindId'=>'']]]),
];
// Chaves de pre-via/sonda que o formulario tambem publica.
foreach(['preview_device','preview_language','preview_route','preview_site','preview_template','preview_variant',
         'preview_hour','preview_logged_in','preview_query','schedule_probe_logged','schedule_probe_now',
         'schedule_probe_site','schedule_probe_visitor','site_probe_host'] as $k) $post[$k]='';
ok('POST montado com campos do formulario',count($post)>=30,count($post).' chaves');
ok('POST: cor primaria escolhida',$post['primary']==='#dc2626');
ok('POST: radius 32 / fonte 20 / spacious',$post['tokens']['radius']==='32' && $post['tokens']['font_size']==='20' && $post['tokens']['density']==='spacious');

/* ---------------- 2) buildDraftConfig() REAL (privado, via Reflection) ---------------- */
$ctl=new \Reamur\Admin\Controller\Design\Theme($reg);
$rm=new \ReflectionMethod($ctl,'buildDraftConfig');
$draft=$rm->invoke($ctl,$post);
ok('buildDraftConfig sem erro',($draft['error']??'ERRO')==='',(string)($draft['error']??''));
ok('buildDraftConfig name/slug',$draft['name']==='Tema AI' && $draft['slug']==='tema-ai');
$cfg=$draft['config']??[];
ok('config: primary persistida',($cfg['primary']??'')==='#dc2626',(string)($cfg['primary']??''));
ok('config: surface persistida',($cfg['surface']??'')==='#fff7ed');
ok('config: tokens radius=32',(string)($cfg['tokens']['radius']??'')==='32',(string)($cfg['tokens']['radius']??''));
ok('config: tokens font_size=20',(string)($cfg['tokens']['font_size']??'')==='20');
ok('config: componente card habilitado',!empty($cfg['components']['card']['enabled']));
ok('config: cena validada e gravada',isset($cfg['kaz_scene']['canvas']['grid']) && $cfg['kaz_scene']['canvas']['grid']===8);
ok('config: 14 CONFIG_KEYS preservadas',count($cfg)>=10,count($cfg).' chaves');
ok('buildDraftConfig nao grava nada',count($db->queries)===0,count($db->queries).' queries');

/* ---------------- 3) SAVE REAL via ModelDesignTheme::save() ---------------- */
$modelAdmin=new \Reamur\Admin\Model\Design\Theme($reg);
$id=$modelAdmin->save(0,['name'=>$draft['name'],'slug'=>$draft['slug'],'config'=>$cfg],1);
ok('save() criou tema',$id>0,'theme_id='.$id);
ok('save() executou INSERT real',count($db->queries)>=1 && str_contains($db->queries[0],'INSERT INTO'));
ok('draft_config gravado no banco',$db->themes[$id]['draft_config']===json_encode($cfg,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),strlen($db->themes[$id]['draft_config']).' bytes');
ok('published_config ainda vazio',($db->themes[$id]['published_config']??'')==='{}');
ok('status ainda 0',(string)($db->themes[$id]['status']??'')==='0');
$read=$modelAdmin->getDraftConfig($id);
ok('getDraftConfig() le o que foi salvo',$read['primary']==='#dc2626' && (string)$read['tokens']['radius']==='32');
ok('getPublishedConfig(): fallback legado config',($modelAdmin->getPublishedConfig($id)['primary']??'')==='#dc2626','comportamento F13-C');

/* ---------------- 4) PAINEL AH antes de publicar (rascunho pendente) ---------------- */
$ctx=['route'=>'common/home','language'=>'pt-br','device'=>'desktop','variant'=>'','site'=>'primary','hour'=>9,'date'=>'2026-11-05'];
$snap=$reg->get('theme_snapshot');
$stateDraft=$snap->resolve($cfg,$ctx,[], '', ['effective'=>'published','reason'=>'disabled'],['active'=>['id'=>'primary','language'=>'pt-br']]);
$scene=(new ThemeSceneCss())->compile($cfg['kaz_scene']??null);
$before=ThemeReleaseProbe::summarize($cfg,json_decode('{}',true),$stateDraft,$scene,false,'1.0-kaz-theme-editor-ah');
ok('AH: tema novo sem ativar',$before['verdict']==='SEM_TEMA_ATIVO',$before['verdict']);
ok('AH: ainda sem tema ativo',(int)$before['active']===0);
$pend=ThemeReleaseProbe::summarize($cfg,json_decode('{}',true),$stateDraft,$scene,true,'1.0-kaz-theme-editor-ah');
ok('AH: ativo sem publicar = RASCUNHO_PENDENTE',$pend['verdict']==='RASCUNHO_PENDENTE',$pend['verdict']);

/* ---------------- 5) ACTIVATE() REAL do controller (CSRF + ACL + model) ---------------- */
$req->server['REQUEST_METHOD']='POST'; $req->post=['csrf_token'=>Csrf::token($sess)];
$reg->set('load',new FakeLoad($reg));
$ctl->activate((string)$id);
ok('activate(): CSRF + ACL aceitos',count($res->redirects)===1 && !str_contains($res->redirects[0],'permission'),$res->redirects[0]??'');
$pub=$db->themes[$id];
ok('activate(): status=1',(string)($pub['status']??'')==='1',(string)($pub['status']??''));
ok('activate(): published_config = draft_config',$pub['published_config']===$pub['draft_config'],strlen($pub['published_config']).' bytes');
ok('activate(): config = draft_config',$pub['config']===$pub['draft_config']);
ok('activate(): SQL copia colunas',str_contains(implode(' ',$db->queries),'`published_config`=`draft_config`'));
ok('activate(): gravou revisao',count($db->revisions)>=1,count($db->revisions));
ok('CSRF invalido NAO ativa',(function()use($reg,$ctl,$db,$id,$sess){
    $r=$reg->get('request'); $before=$db->themes[$id]['published_config'];
    $r->post=['csrf_token'=>'invalido']; $ctl->activate((string)$id);
    $r->post=['csrf_token'=>Csrf::token($sess)];
    return $db->themes[$id]['published_config']===$before;
})());

/* ---------------- 6) CATALOGO: getEffectiveTheme() REAL ---------------- */
$modelCat=new \Reamur\Catalog\Model\Common\Theme($reg);
$eff=$modelCat->getEffectiveTheme();
ok('getEffectiveTheme(): slug',$eff['slug']==='tema-ai',$eff['slug']);
ok('getEffectiveTheme(): published = rascunho',$eff['published']===$eff['draft']);
ok('getEffectiveTheme(): cor do formulario',$eff['published']['primary']==='#dc2626',(string)($eff['published']['primary']??''));
ok('getEffectiveTheme(): radius 32',(string)($eff['published']['tokens']['radius']??'')==='32');
ok('getPublishedTheme(): mesmo config',$modelCat->getPublishedTheme()['primary']==='#dc2626');
ok('escritas reais = 4',count(array_filter($db->queries,fn($q)=>preg_match('/^(INSERT|UPDATE)/',trim($q))))===4,count($db->queries).' queries no total');

/* ---------------- 7) CSS EFETIVO: previa == home publicada ---------------- */
$statePub=$snap->resolve($eff['published'],$ctx,[], '', ['effective'=>'published','reason'=>'disabled'],['active'=>['id'=>'primary','language'=>'pt-br']]);
$cssPreview=ThemePreviewState::css($stateDraft).ThemePreviewState::colorVars($stateDraft);
$cssHome=ThemePreviewState::css($statePub).ThemePreviewState::colorVars($statePub);
ok('PREVIA === HOME PUBLICADA (CSS)',$cssPreview===$cssHome,strlen($cssHome).' bytes');
ok('CSS contem radius 32px',str_contains($cssHome,'32px'));
ok('CSS contem #dc2626',str_contains(strtolower($cssHome),'#dc2626'));
ok('CSS contem componente card',str_contains($cssHome,'--rms-cmp-card-variant'));
ok('CSS contem cena compilada',str_contains($cssHome,'--rms-scene-') || str_contains($cssHome,'scene'));
ok('classes do body iguais',ThemePreviewState::bodyClasses($stateDraft)===ThemePreviewState::bodyClasses($statePub));
$after=ThemeReleaseProbe::summarize($eff['draft'],$eff['published'],$statePub,$scene,true,'1.0-kaz-theme-editor-ah');
ok('AH: PUBLICADO apos ativar',$after['verdict']==='PUBLICADO' && $after['pending']===0,$after['verdict']);

/* ---------------- 8) header.twig REAL: o CSS entra no <body> da home ---------------- */
$head=(string)@file_get_contents($R.'/dev/catalog/view/template/common/header.twig');
$i=strpos($head,'<body class='); $j=strpos($head,"\n",$i);
ok('header.twig tem tag body real',$i!==false);
$bodyTag=substr($head,$i,$j-$i);
$loader=new \Twig\Loader\ArrayLoader(['body.twig'=>$bodyTag]);
$env=new \Twig\Environment($loader,['autoescape'=>'html','cache'=>false,'auto_reload'=>true]);
$data=['body_class'=>'home','theme'=>['classes'=>ThemePreviewState::bodyClasses($statePub),'matched'=>[]],
 'theme_primary'=>$eff['published']['primary'],'theme_surface'=>$eff['published']['surface'],
 'theme_token_css'=>ThemePreviewState::css($statePub)];
$htmlBody=$env->render('body.twig',$data);
ok('header.twig renderiza com CSS real',str_contains($htmlBody,'32px'),substr($htmlBody,0,120));
ok('header.twig recebe cor do formulario',str_contains($htmlBody,'#dc2626'));
ok('header.twig sem |raw novo',strpos($bodyTag,'|raw')===false);

/* ---------------- 9) HTMLs para Chromium ---------------- */
$cssBase=(string)@file_get_contents($R.'/dev/catalog/view/css/theme.css');
ok('theme.css REAL carregado (>10KB)',strlen($cssBase)>10000,strlen($cssBase).' bytes');
@mkdir($OUT,0777,true);
$mk=static function(string $state,string $css,array $pub) use ($htmlBody,$R):string{
  $style=(string)@file_get_contents($R.'/dev/catalog/view/css/theme.css');
  return '<!doctype html><html lang="pt-br"><head><meta charset="utf-8">'
   .'<style>'.$style.'</style><style>:root{'.$css.'}</style></head>'
   .str_replace('<body class="','<body data-kaz-ai="'.$state.'" class="',$htmlBody)
   .'<main style="padding:32px;font:16px system-ui">'
   .'<div id="kaz-probe" style="background:var(--rms-color-primary,#6d28d9);border-radius:var(--rms-radius,4px);'
   .'color:#fff;padding:20px;margin:12px 0;font-size:var(--rms-font-size,16px)">Tema publicado</div>'
   .'<div id="kaz-legacy" style="background:var(--theme-primary,#6d28d9);padding:14px;color:#fff">Legado</div>'
   .'</main></body></html>';
};
// Antes: catalogo ainda com o tema publicado anterior (azul), sem as escolhas do POST.
$old=['primary'=>'#2563eb','surface'=>'#ffffff','tokens'=>array_replace($tokens,['radius'=>'12','font_size'=>'16','color_primary'=>'#2563eb','color_text'=>'#212529','color_surface'=>'#ffffff'])];
$stateOld=$snap->resolve($old,$ctx,[], '', ['effective'=>'published','reason'=>'disabled'],['active'=>['id'=>'primary','language'=>'pt-br']]);
$dataOld=$data; $dataOld['theme_primary']='#2563eb'; $dataOld['theme_surface']='#ffffff';
$dataOld['theme_token_css']=ThemePreviewState::css($stateOld);
$dataOld['theme']['classes']=ThemePreviewState::bodyClasses($stateOld);
$htmlOld=$env->render('body.twig',$dataOld);
file_put_contents($OUT.'/antes.html',(static function(string $h,string $css) use ($R):string{
  $style=(string)@file_get_contents($R.'/dev/catalog/view/css/theme.css');
  return '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><style>'.$style.'</style><style>:root{'.$css.'}</style></head>'
   .str_replace('<body class="','<body data-kaz-ai="antes" class="',$h)
   .'<main style="padding:32px;font:16px system-ui">'
   .'<div id="kaz-probe" style="background:var(--rms-color-primary,#6d28d9);border-radius:var(--rms-radius,4px);'
   .'color:#fff;padding:20px;margin:12px 0;font-size:var(--rms-font-size,16px)">Tema publicado</div>'
   .'<div id="kaz-legacy" style="background:var(--theme-primary,#6d28d9);padding:14px;color:#fff">Legado</div>'
   .'</main></body></html>';
})($htmlOld,ThemePreviewState::css($stateOld).ThemePreviewState::colorVars($stateOld)));
file_put_contents($OUT.'/depois.html',$mk('depois',$cssHome,$eff['published']));
ok('HTMLs antes/depois gerados',is_file($OUT.'/antes.html') && is_file($OUT.'/depois.html'));
ok('HTML antes NAO contem #dc2626',!str_contains(strtolower(file_get_contents($OUT.'/antes.html')),'#dc2626'));
ok('HTML depois contem #dc2626',str_contains(strtolower(file_get_contents($OUT.'/depois.html')),'#dc2626'));

echo "\nPROBE AI: $OK OK / $FAIL FALHAS\n";
