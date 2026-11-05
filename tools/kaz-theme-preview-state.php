<?php declare(strict_types=1);
/* ReamurCMS — Frente AG · PROBE de estado efetivo na previa.
 * SOMENTE LEITURA (alem dos HTMLs de evidencia em /tmp/ag_site).
 * Uso: php tools/kaz-theme-preview-state.php
 * Exige Twig 3.x (vendor/autoload.php) e PHP 8.1+.
 * Sem Composer: AG_TWIG_AUTOLOAD=/caminho/autoload.php
 * Raiz do projeto: AG_ROOT (por padrao dois niveis acima de tools/). */
$OK=0;$FAIL=0;
function ok(string $n,bool $c,$x=''):void{
  global $OK,$FAIL; $x=(string)$x;
  if($c){$OK++;echo "OK   $n".($x?" · $x":'')."\n";}
  else{$FAIL++;echo "FAIL $n".($x?" · $x":'')."\n";}
}
function need(string $p):void{ if(!is_file($p)){echo "ERRO arquivo ausente: $p\n";exit(1);} require_once $p; }
$HEAD = getenv('AG_ROOT') ?: dirname(__DIR__,2);
define('DIR_SYSTEM',$HEAD.'/dev/system/');
define('DIR_TEMPLATE',$HEAD.'/dev/catalog/view/template/');
define('DIR_STORAGE','/tmp/ag_storage/');
need(getenv('AG_TWIG_AUTOLOAD') ?: $HEAD.'/vendor/autoload.php');
need($HEAD.'/dev/system/library/cms/page_layout.php');
foreach(['condition_engine','token_registry','component_registry','section_registry','theme_schedule',
         'theme_media','theme_sites','theme_package','theme_scene_css','theme_snapshot',
         'theme_preview_state','theme_compare_view'] as $f)
  need($HEAD.'/dev/system/library/theme/'.$f.'.php');
need($HEAD.'/dev/system/library/template/twig.php');
use Reamur\System\Library\Theme\{ConditionEngine,TokenRegistry,ThemeSnapshot,ThemePreviewState,ThemeSceneCss,ThemeCompareView};
use Reamur\System\Library\Template\Twig as TwigAdapter;

$snapshot=new ThemeSnapshot(new ConditionEngine(),new TokenRegistry());
$tokens=new TokenRegistry();
$ctx=['route'=>'common/home','language'=>'pt-br','device'=>'desktop','variant'=>'','site'=>'primary','hour'=>9];
$base=$tokens->defaults();
$cfgA=['primary'=>'#2563eb','surface'=>'#ffffff',
       'tokens'=>array_replace($base,['radius'=>12,'radius_lg'=>20,'font_size'=>16,
         'color_primary'=>'#2563eb','color_text'=>'#212529','color_surface'=>'#ffffff']),
       'components'=>[],'kaz_scene'=>null];
$cfgB=['primary'=>'#dc2626','surface'=>'#fff7ed',
       'tokens'=>array_replace($base,['radius'=>32,'radius_lg'=>40,'font_size'=>20,
         'color_primary'=>'#dc2626','color_text'=>'#7c2d12','color_surface'=>'#fff7ed']),
       'components'=>['card'=>['enabled'=>true,'variant'=>'elevated','tone'=>'primary']],
       'kaz_scene'=>null];
$A=$snapshot->resolve($cfgA,$ctx,[], '', ['effective'=>'published','reason'=>'disabled'],
                      ['active'=>['id'=>'primary','language'=>'pt-br']]);
$B=$snapshot->resolve($cfgB,$ctx,[], '', ['effective'=>'draft','reason'=>'rollout'],
                      ['active'=>['id'=>'shop','language'=>'pt-br']]);
ok('resolve A 39 chaves',count($A)===39,(string)count($A));
ok('resolve B 39 chaves',count($B)===39,(string)count($B));
ok('radius 12 em A',str_contains($A['token_css'],'12px'));
ok('radius 32 em B',str_contains($B['token_css'],'32px'));
ok('componente habilitado em B',str_contains($B['component_css'],'--rms-cmp-card-variant'));
ok('cor de texto difere A/B',str_contains($A['token_css'],'#212529') && str_contains($B['token_css'],'#7c2d12'));
ok('cor de superficie difere A/B',str_contains($A['token_css'],'#ffffff') && str_contains($B['token_css'],'#fff7ed'));
ok('css A != css B',ThemePreviewState::css($A)!==ThemePreviewState::css($B));
ok('css A contem token',str_contains(ThemePreviewState::css($A),'--rms-radius: 12px'));
ok('css B contem componente',str_contains(ThemePreviewState::css($B),'--rms-cmp-card-variant'));
ok('css B contem schedule',str_contains(ThemePreviewState::css($B),'--rms-sched'));
ok('colorVars A',ThemePreviewState::colorVars($A)==='--theme-primary:#2563eb;--theme-surface:#ffffff;');
ok('colorVars B',ThemePreviewState::colorVars($B)==='--theme-primary:#dc2626;--theme-surface:#fff7ed;');
ok('classes A sem rollout',!str_contains(ThemePreviewState::bodyClasses($A),'rms-theme-rollout'),ThemePreviewState::bodyClasses($A));
ok('classes B COM rollout',str_contains(ThemePreviewState::bodyClasses($B),'rms-theme-rollout'),ThemePreviewState::bodyClasses($B));
ok('classes B site allowlistado',str_contains(ThemePreviewState::bodyClasses($B),'rms-site-shop'));

/* ---- paridade com a concatenacao de common/header.php ---- */
$manualA='';
foreach(['token_css','component_css','schedule_css','media_css','site_css','scene_css'] as $k)
  if(is_string($A[$k]??null) && $A[$k]!=='') $manualA.=rtrim(trim($A[$k]),';').';';
ok('PARIDADE header.php (6 partes, mesma ordem)',ThemePreviewState::css($A)===$manualA,
   strlen($manualA).' bytes');
$partes=['token_css'=>'-a: 1','component_css'=>'-b: 2','schedule_css'=>'-c: 3',
         'media_css'=>'-d: 4','site_css'=>'-e: 5','scene_css'=>'-f: 6'];
ok('ordem das 6 partes',ThemePreviewState::css($partes)==='-a: 1;-b: 2;-c: 3;-d: 4;-e: 5;-f: 6;',
   ThemePreviewState::css($partes));

/* ---- fail-closed ---- */
$bad=$partes; $bad['token_css']='--x:red}</style><script>alert(1)</script>';
ok('fail-closed descarta parte com tag script',!str_contains(ThemePreviewState::css($bad),'alert('));
ok('fail-closed mantem as outras partes',str_contains(ThemePreviewState::css($bad),'-f: 6'));
$bad2=$partes; $bad2['media_css']='--m: url(javascript:alert(1))';
ok('fail-closed descarta javascript:',!str_contains(ThemePreviewState::css($bad2),'javascript'));
$bad3=$partes; $bad3['site_css']=str_repeat('x',9000);
ok('fail-closed descarta parte enorme',!str_contains(ThemePreviewState::css($bad3),str_repeat('x',100)));
ok('css de estado vazio = vazio',ThemePreviewState::css([])==='');
ok('colorVars fallback',ThemePreviewState::colorVars(['primary'=>'javascript:','surface'=>null])
   ==='--theme-primary:#2563eb;--theme-surface:#ffffff;');

/* ---- globais seguros ---- */
$G=ThemePreviewState::globals($B);
ok('globais sem header/footer',!isset($G['header']) && !isset($G['footer']));
ok('globais theme.primary = rascunho',$G['theme']['primary']==='#dc2626');
ok('globais theme_primary',$G['theme_primary']==='#dc2626');
ok('globais theme_classes',$G['theme_classes']===ThemePreviewState::bodyClasses($B));
ok('globais so theme/theme_*',count(array_filter(array_keys($G),
   fn($k)=>$k!=='theme' && strncmp($k,'theme_',6)!==0))===0,implode(',',array_keys($G)));
ok('globais nao incluem theme_scene_view',!isset($G['theme_scene_view']));
$evil=ThemePreviewState::globals(['globals'=>['header'=>'<b>x</b>','footer'=>'EVIL',
   'theme_x'=>str_repeat('z',300),'theme_ok'=>'fine']]);
ok('globais descartam valor longo',!isset($evil['theme_x']));
ok('globais mantem valor curto',($evil['theme_ok']??'')==='fine');
ok('catalogCss le theme.css real',str_contains(ThemePreviewState::catalogCss(),'--theme-primary'));
ok('catalogCss sem tag script',!preg_match('~<\s*/?\s*(script|style)\b|@import~i',ThemePreviewState::catalogCss()));
ok('catalogCss consome --rms-*',str_contains(ThemePreviewState::catalogCss(),'--rms-'));

/* ---- Twig sandbox: theme.primary realmente muda ---- */
$adapter=new TwigAdapter('',false);
$source='<div class="ag-probe" data-primary="{{ theme.primary }}" data-radius="{{ theme.tokens.radius|default(0) }}">{{ theme.primary }}</div>';
$htmlA=$adapter->renderSandboxPreview('common/home',$source,[],$A);
$htmlB=$adapter->renderSandboxPreview('common/home',$source,[],$B);
$legacy=$adapter->renderSandboxPreview('common/home',$source);
ok('Twig A mostra primaria do rascunho',str_contains($htmlA,'#2563eb'),substr($htmlA,0,120));
ok('Twig B mostra primaria alterada',str_contains($htmlB,'#dc2626'),substr($htmlB,0,120));
ok('Twig legado mantem #2563eb',str_contains($legacy,'#2563eb'));
ok('Twig B radius do token',str_contains($htmlB,'data-radius="32"'),substr($htmlB,0,160));
ok('Twig nao vaza header/footer',!str_contains($htmlB,'EVIL'));

/* ---- ThemeCompareView: dois paineis com estados diferentes ---- */
$labels=['title'=>'Comparação','context'=>'Contexto','identical'=>'Idêntico','changes'=>'mudanças',
 'diff_title'=>'Diferenças','empty'=>'vazio','primary'=>'Primária','surface'=>'Superfície',
 'matched'=>'Correspondências','flags'=>'Flags','tokens'=>'Tokens','sample_title'=>'Amostra',
 'sample_body'=>'Corpo','sample_button'=>'Botão','preview_note'=>'Prévia isolada',
 'scene_thumb'=>'Miniatura','scene_label'=>'Composição visual do tema',
 'sections'=>'Seções','components'=>'Componentes','schedule'=>'Agenda','media'=>'Mídia','sites'=>'Sites',
 'published'=>'Publicado','draft'=>'Rascunho','changed'=>'Alterado','added'=>'Adicionado',
 'removed'=>'Removido','diff_help'=>'Diferenças','truncated'=>'Truncado','variant'=>'Variante',
 'site'=>'Site','invalid'=>'Inválido','nothing_saved'=>'Nada gravado'];
$diff=['total'=>1,'changed'=>[],'added'=>[['key'=>'tokens.radius','before'=>'12','after'=>'32']],
       'removed'=>[],'truncated'=>false];
$wrap=static function(array $s,array $config):array{
  return array_replace($s,['labels'=>[],'matched'=>[],'flags'=>[],'sections'=>[],'components'=>[],
    'schedule'=>[],'media'=>[],'sites'=>[],'vars'=>[],'classes'=>[]]);
};
$view=(new ThemeCompareView())->render([
 'lang'=>'pt-br','labels'=>$labels,'context'=>$ctx,'template_key'=>'common/home','error'=>'',
 'published'=>$A,'draft'=>$B,
 'published_html'=>$htmlA,'draft_html'=>$htmlB,
 'published_thumb'=>'','draft_thumb'=>'','diff'=>$diff,
]);
ok('compare 2 paineis',substr_count($view,'<section class=')===2,(string)substr_count($view,'<section class='));
ok('compare radius 12 (publicado)',str_contains($view,'--rms-radius: 12px'));
ok('compare radius 32 (rascunho)',str_contains($view,'--rms-radius: 32px'));
ok('compare componente so no rascunho',substr_count($view,'--rms-cmp-card-variant')===1);
ok('compare cor publicada',str_contains($view,'--theme-primary:#2563eb'));
ok('compare cor do rascunho',str_contains($view,'--theme-primary:#dc2626'));
ok('compare classes rollout no rascunho',str_contains($view,'rms-theme-rollout'));
ok('compare CSP intacta',str_contains($view,"default-src 'none'") && str_contains($view,"style-src 'unsafe-inline'"));
ok('compare sem tag script',!str_contains($view,'<script'));
ok('compare painel consome --rms-radius',str_contains($view,'border-radius:var(--rms-radius,8px)'));

/* ---- HTMLs para o Chromium ---- */
@mkdir('/tmp/ag_site',0777,true);
$mk=static function(array $st,string $prim) use ($source,$adapter):string{
  $css=ThemePreviewState::catalogCss().ThemeSceneCss::previewCss();
  $body=ThemePreviewState::css($st).ThemePreviewState::colorVars($st);
  $frag=$adapter->renderSandboxPreview('common/home',$source,[],$st);
  return '<!doctype html><html><head><meta charset="utf-8"><style>'.$css.'</style></head>'
       . '<body class="'.htmlspecialchars(ThemePreviewState::bodyClasses($st),ENT_QUOTES,'UTF-8')
       . '" style="'.htmlspecialchars($body,ENT_QUOTES,'UTF-8').'"><main style="padding:24px">'
       . '<div class="ag-card" style="padding:16px;border:1px solid #ccc;border-radius:var(--rms-radius,8px);'
       . 'background:var(--rms-light-surface,#fff);color:var(--rms-light-text,#212529);'
       . 'font-size:var(--rms-font-size,16px)">Amostra do tema</div>'.$frag.'</main></body></html>';
};
file_put_contents('/tmp/ag_site/state_a.html',$mk($A,'#2563eb'));
file_put_contents('/tmp/ag_site/state_b.html',$mk($B,'#dc2626'));
ok('HTMLs de evidencia gerados',is_file('/tmp/ag_site/state_a.html') && is_file('/tmp/ag_site/state_b.html'));

echo "\nPROBE AG: $OK OK / $FAIL FALHAS\n";
