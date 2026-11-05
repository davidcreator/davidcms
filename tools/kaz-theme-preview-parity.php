<?php declare(strict_types=1);
/* ReamurCMS — Frente AF · PROBE de paridade previa x publicado.
 * SOMENTE LEITURA (alem dos dois HTMLs de evidencia em /tmp/af_site).
 * Uso: php tools/kaz-theme-preview-parity.php
 * Exige Twig 3.x (vendor/autoload.php) e PHP 8.1+.
 * Em ambientes sem Composer: AF_TWIG_AUTOLOAD=/caminho/autoload.php
 */
$OK=0;$FAIL=0;
function ok(string $n,bool $c,$x=''):void{
  $x=(string)$x;
  global $OK,$FAIL;
  if($c){$OK++;echo "OK   $n".($x?" · $x":'')."\n";}
  else{$FAIL++;echo "FAIL $n".($x?" · $x":'')."\n";}
}
function need(string $p):void{ if(!is_file($p)){echo "ERRO arquivo ausente: $p\n";exit(1);} require_once $p; }
/* Instalado: raiz do projeto e vendor/autoload.php do Composer.
   No sandbox de validacao use AF_ROOT / AF_TWIG_AUTOLOAD. */
$HEAD = getenv('AF_ROOT') ?: dirname(__DIR__,2);
define('DIR_SYSTEM',$HEAD.'/dev/system/');
need(getenv('AF_TWIG_AUTOLOAD') ?: $HEAD.'/vendor/autoload.php');
need($HEAD.'/dev/system/library/theme/theme_package.php');
need($HEAD.'/dev/system/library/theme/theme_scene_css.php');
need($HEAD.'/dev/system/library/template/twig.php');
need($HEAD.'/dev/system/library/theme/theme_compare_view.php');
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\ThemeSceneCss;
use Reamur\System\Library\Theme\ThemeCompareView;
use Reamur\System\Library\Template\Twig as TwigAdapter;

/* ---- cena de teste: 6 camadas, mesma forma que o editor serializa ---- */
$scene=['version'=>1,'canvas'=>['w'=>960,'h'=>540,'grid'=>8],
 'layers'=>[
  ['id'=>'a1','kind'=>'section','role'=>'surface','x'=>0,'y'=>0,'w'=>960,'h'=>540,'fill'=>'#ffffff','radius'=>0,'fontSize'=>16,'text'=>'','visible'=>true,'locked'=>false,'bindId'=>''],
  ['id'=>'a2','kind'=>'rect','role'=>'primary','x'=>64,'y'=>64,'w'=>520,'h'=>160,'fill'=>'#2563eb','radius'=>12,'fontSize'=>16,'text'=>'','visible'=>true,'locked'=>false,'bindId'=>''],
  ['id'=>'a3','kind'=>'text','role'=>'none','x'=>88,'y'=>96,'w'=>460,'h'=>64,'fill'=>'#ffffff','radius'=>0,'fontSize'=>32,'text'=>'Tema publicado de verdade','visible'=>true,'locked'=>false,'bindId'=>''],
  ['id'=>'a4','kind'=>'button','role'=>'primary','x'=>88,'y'=>180,'w'=>160,'h'=>48,'fill'=>'#31efb8','radius'=>8,'fontSize'=>16,'text'=>'Começar','visible'=>true,'locked'=>false,'bindId'=>''],
  ['id'=>'a5','kind'=>'card','role'=>'surface','x'=>640,'y'=>64,'w'=>256,'h'=>176,'fill'=>'#f8fafc','radius'=>12,'fontSize'=>16,'text'=>'','visible'=>true,'locked'=>false,'bindId'=>''],
  ['id'=>'a6','kind'=>'text','role'=>'none','x'=>88,'y'=>260,'w'=>420,'h'=>40,'fill'=>'#64748b','radius'=>0,'fontSize'=>16,'text'=>'Desenhado no editor','visible'=>true,'locked'=>false,'bindId'=>''],
 ]];
$scene2=$scene; $scene2['layers'][2]['text']='CENA DO RASCUNHO';
$vPub=(new ThemeSceneCss())->compile($scene);
$vDraft=(new ThemeSceneCss())->compile($scene2);
ok('compile publicado 6 camadas',$vPub['enabled']===true && count($vPub['layers'])===6,count($vPub['layers']).' camadas');
ok('compile rascunho 6 camadas',$vDraft['enabled']===true && count($vDraft['layers'])===6);
ok('css allowlistado',preg_match('/[^A-Za-z0-9#:.;\-]/',$vPub['css'])===1?false:true,substr($vPub['css'],0,60));

/* ---- 1) normalizacao fail-closed ---- */
$n=TwigAdapter::previewSceneView($vPub);
ok('previewSceneView preserva enabled',$n['enabled']===true);
ok('previewSceneView preserva canvas',$n['canvas']['w']===960 && $n['canvas']['h']===540);
ok('previewSceneView preserva 6 camadas',count($n['layers'])===6);
ok('previewSceneView preserva texto',$n['layers'][2]['text']==='Tema publicado de verdade',$n['layers'][2]['text']);
ok('previewSceneView preserva fill',$n['layers'][1]['fill']==='#2563eb');
$bad=TwigAdapter::previewSceneView(['enabled'=>true,'canvas'=>['w'=>1,'h'=>1],'layers'=>$vPub['layers']]);
ok('previewSceneView fail-closed canvas invalido',$bad['enabled']===false && $bad['layers']===[]);
$bad2=TwigAdapter::previewSceneView(['enabled'=>true,'canvas'=>['w'=>960,'h'=>540],
  'layers'=>[['kind'=>'rect','left'=>999,'top'=>-50,'width'=>1e9,'fill'=>'javascript:alert(1)','radius'=>9999,'font'=>9999,'text'=>'<b>x</b>']]]);
ok('previewSceneView clamp %',$bad2['layers'][0]['left']===100.0 && $bad2['layers'][0]['top']===0.0,json_encode([$bad2['layers'][0]['left'],$bad2['layers'][0]['top']]));
ok('previewSceneView sanitiza fill',$bad2['layers'][0]['fill']==='#ffffff');
ok('previewSceneView clamp radius/font',$bad2['layers'][0]['radius']===64 && $bad2['layers'][0]['font']===72);
ok('previewSceneView texto nao vira tag',strpos($bad2['layers'][0]['text'],'<b>')!==false,'texto mantido; autoescape no Twig');
$empty=TwigAdapter::previewSceneView([]);
ok('previewSceneView vazio = desabilitado',$empty['enabled']===false);

/* ---- 2) previaCss estatico e seguro ---- */
$css=ThemeSceneCss::previewCss();
ok('previewCss contem stage',str_contains($css,'.rms-scene__stage'));
ok('previewCss contem layer',str_contains($css,'.rms-scene__layer'));
ok('previewCss sem tag/url/import',!preg_match('~<\s*(script|/style)|url\s*\(|@import|expression\s*\(|javascript\s*:~i',$css));
ok('previewCss sem dados do usuario',!str_contains($css,'Tema publicado'));
ok('previewCss container-type (anti-corte)',str_contains($css,'container-type:inline-size'));
ok('previewCss @container responsivo',str_contains($css,'@container(max-width:1200px)') && str_contains($css,'2.4cqw'));
ok('previewCss max-width do stage',str_contains($css,'max-width:var(--rms-content-width,1200px)'));

/* ---- 3) Twig real: home.twig COM e SEM a cena ---- */
function renderHome(array $ctx):string{
  $loader=new \Twig\Loader\ChainLoader([
    new \Twig\Loader\ArrayLoader([]),
    new \Twig\Loader\FilesystemLoader([DIR_SYSTEM.'../catalog/view/template/'])
  ]);
  $env=new \Twig\Environment($loader,['autoescape'=>'html','cache'=>false,'auto_reload'=>true]);
  $policy=new \Twig\Sandbox\SecurityPolicy(
    ['if','for','set','block','extends','include'],
    ['escape','e','default','join','length','upper','lower','title','trim','date','json_encode'],
    [],[],['range','cycle','parent']);
  $env->addExtension(new \Twig\Extension\SandboxExtension($policy,true));
  $base=['header'=>'','footer'=>'','cms_home_sections'=>[],
    'theme_scene'=>['enabled'=>false,'scene'=>'','slot'=>'hero','count'=>400,'color'=>'#8a4fff','speed'=>0.05]];
  return $env->render('common/home.twig',array_merge($base,$ctx));
}
$comCena=renderHome(['theme_scene_view'=>$n]);
$semCena=renderHome([]);
ok('home.twig SEM cena nao mostra rms-scene',!str_contains($semCena,'rms-scene'),'(lacuna da AE)');
ok('home.twig COM cena mostra section',str_contains($comCena,'class="rms-scene"'));
ok('home.twig COM cena tem 6 camadas',substr_count($comCena,'rms-scene__layer rms-scene__layer--')===6,(string)substr_count($comCena,'rms-scene__layer rms-scene__layer--'));
ok('home.twig texto da cena presente',str_contains($comCena,'Tema publicado de verdade'));
ok('home.twig aspect-ratio do canvas',str_contains($comCena,'aspect-ratio: 960 / 540'));
ok('home.twig escapa kind/role',str_contains($comCena,'rms-scene__layer--section'));

/* ---- 4) ThemeCompareView: CSS, scene_css e cenas distintas ---- */
function state(array $view,string $label):array{
  return [
   'token_css'=>'--rms-color-primary:#2563eb','scene_css'=>$view['css'],'scene_view'=>$view,
   'primary'=>'#2563eb','surface'=>'#ffffff','matched'=>[],'flags'=>[],
   'tokens'=>['font_size'=>16,'radius'=>12,'density'=>'normal'],
   'sections'=>[],'components'=>[],'component_css'=>'','schedule'=>[],'media'=>[],'sites'=>[],
   'three'=>['enabled'=>false,'scene'=>'','slot'=>'hero','count'=>400,'color'=>'#8a4fff','speed'=>0.05],
   'vars'=>[],'classes'=>[],'label'=>$label,
  ];
}
$labels=['title'=>'Comparação','context'=>'Contexto','identical'=>'Idêntico','changes'=>'mudanças',
 'diff_title'=>'Diferenças','empty'=>'vazio','primary'=>'Primária','surface'=>'Superfície',
 'matched'=>'Correspondências','flags'=>'Flags','tokens'=>'Tokens','sample_title'=>'Amostra',
 'sample_body'=>'Corpo','sample_button'=>'Botão','preview_note'=>'Prévia isolada',
 'scene_thumb'=>'Miniatura','scene_label'=>'Composição visual do tema',
 'sections'=>'Seções','components'=>'Componentes','schedule'=>'Agenda','media'=>'Mídia','sites'=>'Sites',
 'published'=>'Publicado','draft'=>'Rascunho','changed'=>'Alterado','added'=>'Adicionado',
 'removed'=>'Removido','diff_help'=>'Diferenças entre publicado e rascunho','truncated'=>'Lista truncada',
 'variant'=>'Variante','site'=>'Site','invalid'=>'Inválido','nothing_saved'=>'Nada foi gravado',
 'no_sections'=>'Sem seções','no_components'=>'Sem componentes','no_schedule'=>'Sem agenda',
 'no_media'=>'Sem mídia','no_sites'=>'Sem sites','active'=>'Ativo','inactive'=>'Inativo'];
$ctx=['route'=>'common/home','language'=>'pt-br','device'=>'desktop','variant'=>'','site'=>''];
$diff=['added'=>[['key'=>'kaz_scene.layers.2.text','before'=>'','after'=>'CENA DO RASCUNHO']],
       'removed'=>[],'changed'=>[],'total'=>1,'truncated'=>false];
$compare=(new ThemeCompareView())->render([
 'lang'=>'pt-br','labels'=>$labels,'context'=>$ctx,'template_key'=>'common/home','error'=>'',
 'published'=>state($vPub,'pub'),'draft'=>state($vDraft,'draft'),
 'published_html'=>renderHome(['theme_scene_view'=>TwigAdapter::previewSceneView($vPub)]),
 'draft_html'=>renderHome(['theme_scene_view'=>TwigAdapter::previewSceneView($vDraft)]),
 'published_thumb'=>'','draft_thumb'=>'','diff'=>$diff,
]);
ok('compare tem style .rms-scene__stage',str_contains($compare,'<style>') && str_contains($compare,'.rms-scene__stage'));
ok('compare tem scene_css no painel',str_contains($compare,'--rms-scene-w:960'));
ok('compare tem 2 secoes de cena',substr_count($compare,'class="rms-scene"')===2,(string)substr_count($compare,'class="rms-scene"'));
ok('compare mostra cena publicada',str_contains($compare,'Tema publicado de verdade'));
ok('compare mostra cena do rascunho',str_contains($compare,'CENA DO RASCUNHO'));
ok('compare nao duplica fragmento',substr_count($compare,'<main>')<=2,(string)substr_count($compare,'<main>'));
ok('compare CSP intacta',str_contains($compare,"default-src 'none'") && str_contains($compare,"style-src 'unsafe-inline'"));
ok('compare sem script',!str_contains($compare,'<script'));
ok('compare sem iframe/form',!str_contains($compare,'<iframe') && !str_contains($compare,'<form'));
ok('compare diff acusa a cena',str_contains($compare,'kaz_scene.layers.2.text'));
/* fallback: template customizado SEM o partial */
$compareFallback=(new ThemeCompareView())->render([
 'lang'=>'pt-br','labels'=>$labels,'context'=>$ctx,'template_key'=>'common/home','error'=>'',
 'published'=>state($vPub,'pub'),'draft'=>state($vDraft,'draft'),
 'published_html'=>'<p>template custom</p>','draft_html'=>'<p>template custom</p>',
 'published_thumb'=>'','draft_thumb'=>'','diff'=>$diff,
]);
ok('fallback renderiza cena sem partial',substr_count($compareFallback,'class="rms-scene"')===2,(string)substr_count($compareFallback,'class="rms-scene"'));
ok('fallback nao duplica quando fragmento ja tem cena',substr_count($compare,'class="rms-scene"')===2);

/* ---- 5) paridade de camadas previa x publicado ---- */
function layers(string $html):array{
  preg_match_all('~class="rms-scene__layer[^"]*"[^>]*data-rms-layer="(\d+)"[^>]*style="([^"]*)"[^>]*>(.*?)</div>~s',$html,$m,PREG_SET_ORDER);
  $out=[];
  foreach($m as $r) $out[]=['n'=>$r[1],'style'=>$r[2],'text'=>trim(strip_tags($r[3]))];
  return $out;
}
$Lpub=layers($comCena); $Lprev=layers($compare);
ok('paridade 12 camadas (6 publicadas + 6 rascunho)',count($Lprev)===12,(string)count($Lprev));
ok('paridade: primeiras 6 iguais as publicadas',array_slice($Lprev,0,6)===$Lpub,
   count($Lpub).' vs '.count(array_slice($Lprev,0,6)));
ok('paridade: texto do rascunho diferente',$Lprev[8]['text']==='CENA DO RASCUNHO',$Lprev[8]['text']??'-');

/* ---- 6) HTML de evidencia para o Chromium ---- */
$cssCat=is_file($HEAD.'/dev/catalog/view/css/theme.css')?file_get_contents($HEAD.'/dev/catalog/view/css/theme.css'):'';
/* header.php publicado injeta as variaveis do tema no body; reproduzir aqui
   para que a cor --role--primary seja a MESMA no painel e no catalogo. */
$pubVars='--theme-primary:#2563eb;--theme-surface:#ffffff;--rms-color-text:#212529;'.$vPub['css'];
$pub='<!doctype html><html><head><meta charset="utf-8"><style>'.$cssCat.'</style></head>'
   .'<body style="'.$pubVars.'"><main>'.$comCena.'</main></body></html>';
@mkdir('/tmp/af_site',0777,true);
file_put_contents('/tmp/af_site/preview.html',$compare);
file_put_contents('/tmp/af_site/published.html',$pub);
ok('HTMLs de evidencia gerados',is_file('/tmp/af_site/preview.html') && is_file('/tmp/af_site/published.html'));

echo "\nPROBE AF: $OK OK / $FAIL FALHAS\n";
