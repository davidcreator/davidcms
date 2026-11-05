<?php declare(strict_types=1);
/* ReamurCMS — Frente AH · PROBE do elo Publicar → catalogo.
 * SOMENTE LEITURA (alem dos HTMLs de evidencia em /tmp/ah_site).
 * Uso: php tools/kaz-theme-release-probe.php
 * Exige Twig 3.x (vendor/autoload.php) e PHP 8.1+.
 * Sem Composer: AH_TWIG_AUTOLOAD=/caminho/autoload.php
 * Raiz do projeto: AH_ROOT (por padrao dois niveis acima de tools/). */
$OK=0;$FAIL=0;
function ok(string $n,bool $c,$x=''):void{
  global $OK,$FAIL; $x=(string)$x;
  if($c){$OK++;echo "OK   $n".($x?" · $x":'')."\n";}
  else{$FAIL++;echo "FAIL $n".($x?" · $x":'')."\n";}
}
function need(string $p):void{ if(!is_file($p)){echo "ERRO arquivo ausente: $p\n";exit(1);} require_once $p; }
$HEAD = getenv('AH_ROOT') ?: dirname(__DIR__,2);
define('DIR_SYSTEM',$HEAD.'/dev/system/');
define('DIR_TEMPLATE',$HEAD.'/dev/catalog/view/template/');
define('DIR_STORAGE','/tmp/ah_storage/');
need(getenv('AH_TWIG_AUTOLOAD') ?: $HEAD.'/vendor/autoload.php');
need($HEAD.'/dev/system/library/cms/page_layout.php');
foreach(['condition_engine','token_registry','component_registry','section_registry','theme_schedule',
         'theme_media','theme_sites','theme_package','theme_scene_css','theme_snapshot',
         'theme_preview_state','theme_release_probe'] as $f)
  need($HEAD.'/dev/system/library/theme/'.$f.'.php');
use Reamur\System\Library\Theme\{ConditionEngine,TokenRegistry,ThemeSnapshot,ThemeSceneCss,
    ThemePreviewState,ThemeReleaseProbe};

$snapshot=new ThemeSnapshot(new ConditionEngine(),new TokenRegistry());
$ctx=['route'=>'common/home','language'=>'pt-br','device'=>'desktop','site'=>'primary','hour'=>9];
$base=(new TokenRegistry())->defaults();
$A=['primary'=>'#2563eb','surface'=>'#ffffff',
    'tokens'=>array_replace($base,['radius'=>12,'font_size'=>16,'color_primary'=>'#2563eb']),
    'components'=>[]];
$B=['primary'=>'#dc2626','surface'=>'#fff7ed',
    'tokens'=>array_replace($base,['radius'=>32,'font_size'=>20,'color_primary'=>'#dc2626']),
    'components'=>['card'=>['enabled'=>true,'variant'=>'elevated','tone'=>'primary']]];
$ASSET='1.0-kaz-theme-editor-ah';
$scene=(new ThemeSceneCss())->compile(null);
$stateB=$snapshot->resolve($B,$ctx);
$stateA=$snapshot->resolve($A,$ctx);
ok('resolve B 39 chaves',count($stateB)===39,(string)count($stateB));
ok('css B nao vazio',ThemePreviewState::css($stateB)!=='');
ok('css A != css B',ThemePreviewState::css($stateA)!==ThemePreviewState::css($stateB));

/* ---- 1) resumo ANTES de publicar ---- */
$P=ThemeReleaseProbe::summarize($B,$A,$stateB,$scene,true,$ASSET);
ok('veredito pendente',$P['verdict']==='RASCUNHO_PENDENTE',$P['verdict']);
ok('pending=1',$P['pending']===1,(string)$P['pending']);
ok('pending_count>=3',(int)$P['pending_count']>=3,(string)$P['pending_count']);
ok('pendente lista primary',$P['pending_keys']!=='' && str_contains($P['pending_keys'],'primary'),$P['pending_keys']);
ok('css_parts entre 1 e 6',(int)$P['css_parts']>=1 && (int)$P['css_parts']<=6,(string)$P['css_parts']);
ok('css_bytes > 0',(int)$P['css_bytes']>0,(string)$P['css_bytes']);
ok('asset_ok=1',$P['asset_ok']===1,(string)$P['asset_version']);
ok('active=1',$P['active']===1);
ok('rotulo allowlistado',in_array($P['verdict_label'],['Publicado','Rascunho pendente','Nenhum tema ativo'],true));
ok('resumo so escalares',count(array_filter($P,fn($v)=>!is_scalar($v)))===0);
ok('resumo sem HTML',!preg_match('~[<>{}]~',implode('|',array_map('strval',$P))));
ok('hint orienta a publicar',str_contains($P['hint'],'Publicar'),$P['hint']);

/* ---- 2) activate(): published = draft ---- */
$after=$B; // exatamente o contrato da SQL: published_config = draft_config
$Q=ThemeReleaseProbe::summarize($B,$after,$stateB,$scene,true,$ASSET);
ok('veredito publicado',$Q['verdict']==='PUBLICADO',$Q['verdict']);
ok('pending=0',$Q['pending']===0,(string)$Q['pending']);
ok('pending_count=0',$Q['pending_count']===0);
ok('pending_keys vazio',$Q['pending_keys']==='');
ok('sem tema ativo',ThemeReleaseProbe::summarize($B,$B,$stateB,$scene,false,$ASSET)['verdict']==='SEM_TEMA_ATIVO');
ok('asset errado e detectado',ThemeReleaseProbe::summarize($B,$B,$stateB,$scene,true,'1.0-old')['asset_ok']===0);
ok('canon ignora ordem das chaves',
   ThemeReleaseProbe::summarize(['a'=>1,'b'=>2],['b'=>2,'a'=>1],[], [],true,$ASSET)['pending']===0);

/* ---- 3) PROVA CENTRAL: previa == catalogo apos Publicar ---- */
$stateAfter=$snapshot->resolve($after,$ctx);
$cssPreview=ThemePreviewState::css($stateB).ThemePreviewState::colorVars($stateB);
$cssCatalog=ThemePreviewState::css($stateAfter).ThemePreviewState::colorVars($stateAfter);
ok('PUBLICAR: CSS previa === CSS catalogo',$cssPreview===$cssCatalog,strlen($cssPreview).' bytes');
ok('PUBLICAR: classes iguais',ThemePreviewState::bodyClasses($stateB)===ThemePreviewState::bodyClasses($stateAfter));
ok('PUBLICAR: globais iguais',ThemePreviewState::globals($stateB)['theme']===ThemePreviewState::globals($stateAfter)['theme']);
ok('sem publicar: CSS diferente',ThemePreviewState::css($stateA)!==ThemePreviewState::css($stateB));

/* ---- 4) contrato estatico dos models (sem executar SQL) ---- */
$modelAdmin=(string)@file_get_contents($HEAD.'/dev/admin/model/design/theme.php');
$modelCat=(string)@file_get_contents($HEAD.'/dev/catalog/model/common/theme.php');
ok('activate copia published_config',str_contains($modelAdmin,'`published_config`=`draft_config`'));
ok('activate copia config',str_contains($modelAdmin,'`config`=`draft_config`'));
ok('activate zera status dos outros',str_contains($modelAdmin,"SET `status`='0'"));
ok('activate marca status=1',str_contains($modelAdmin,"`status`='1'"));
ok('getEffectiveTheme le published_config',str_contains($modelCat,'`published_config`'));
ok('getEffectiveTheme filtra status=1',str_contains($modelCat,"WHERE `status`='1'"));

/* ---- 5) Twig REAL do painel, com autoescape ---- */
$editor=(string)@file_get_contents($HEAD.'/dev/admin/view/template/design/kaz_theme_editor.twig');
$i=strpos($editor,'{# Frente AH'); $j=strpos($editor,'</section>',$i);
ok('bloco AH presente no Twig',$i!==false && $j!==false);
$block=substr($editor,$i,$j-$i+10);
$blockCode=(string)preg_replace('~\{#.*?#\}~s','',$block);
ok('bloco sem |raw',strpos($blockCode,'|raw')===false);
ok('bloco sem name=',strpos($blockCode,'name=')===false);
ok('bloco sem data-kaz-module',strpos($blockCode,'data-kaz-module')===false);
ok('bloco role=status',str_contains($block,'role="status"'));
$loader=new \Twig\Loader\ArrayLoader(['release.twig'=>$block]);
$env=new \Twig\Environment($loader,['autoescape'=>'html','cache'=>false,'auto_reload'=>true]);
$htmlP=$env->render('release.twig',['kaz_release'=>$P]);
$htmlQ=$env->render('release.twig',['kaz_release'=>$Q]);
$htmlNoData=$env->render('release.twig',[]);
ok('Twig pendente mostra veredito',str_contains($htmlP,'RASCUNHO_PENDENTE') && str_contains($htmlP,'Rascunho pendente'));
ok('Twig publicado mostra veredito',str_contains($htmlQ,'PUBLICADO') && str_contains($htmlQ,'Publicado'));
ok('Twig sem dados nao quebra',str_contains($htmlNoData,'SEM_TEMA_ATIVO'));
ok('Twig autoescapa texto',str_contains($htmlP,'alert-warning') && !str_contains($htmlP,'<script'));
$evil=$P; $evil['pending_keys']='<img src=x onerror=alert(1)>'; $evil['hint']='<script>x</script>';
$htmlEvil=$env->render('release.twig',['kaz_release'=>$evil]);
ok('Twig escapa valor hostil',!str_contains($htmlEvil,'<img') && !str_contains($htmlEvil,'<script'));
ok('Twig escapa atributo hostil',str_contains($htmlEvil,'&lt;') || str_contains($htmlEvil,'&#'));

/* ---- 6) HTMLs para o Chromium ---- */
@mkdir('/tmp/ah_site',0777,true);
$CSS='body{font:14px system-ui;margin:0;padding:24px;background:#f8fafc}'
   . '.alert{padding:10px 14px;border-radius:8px;border:1px solid #cbd5e1}'
   . '.alert-warning{background:#fef3c7;border-color:#fcd34d;color:#92400e}'
   . '.alert-success{background:#dcfce7;border-color:#86efac;color:#166534}'
   . '.badge{display:inline-block;padding:1px 7px;border-radius:999px;background:#e2e8f0;font-size:12px}'
   . '.small{font-size:12px}.text-muted{color:#64748b}.ms-2{margin-left:8px}.mt-1{margin-top:6px}'
   . 'code{font-family:monospace}strong{font-size:15px}';
$mk=static function(string $body) use ($CSS):string{
  return '<!doctype html><html><head><meta charset="utf-8"><style>'.$CSS.'</style></head>'
       .'<body><main style="max-width:960px;margin:0 auto">'.$body.'</main></body></html>';
};
file_put_contents('/tmp/ah_site/pending.html',$mk($htmlP));
file_put_contents('/tmp/ah_site/published.html',$mk($htmlQ));
ok('HTMLs de evidencia gerados',is_file('/tmp/ah_site/pending.html') && is_file('/tmp/ah_site/published.html'));

echo "\nPROBE AH: $OK OK / $FAIL FALHAS\n";
