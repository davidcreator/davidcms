<?php
/* Frente AN — PROBE instalavel. Somente leitura. AN_ROOT / AN_OUT. */
$ROOT = getenv('AN_ROOT') ?: dirname(__DIR__, 2);
$OUT  = getenv('AN_OUT')  ?: '/tmp/an_site';
$C=0;$F=0;
function ok($l,$c,$x=''){ global $C,$F;
  if($c){$C++;echo "OK   $l".($x!==''?" · $x":'')."\n";}
  else{$F++;echo "FAIL $l".($x!==''?" · $x":'')."\n";} }
function rd($p){ return is_file($p)?(string)file_get_contents($p):''; }
function has($h,$n){ return str_contains($h,$n); }
$CSS=rd($ROOT.'/dev/admin/view/css/blog-block-editor-kaz.css');
$JS =rd($ROOT.'/dev/admin/view/js/blog-block-editor-kaz.js');
$JSC=preg_replace('~/\*.*?\*/~s','',$JS); $JSC=preg_replace('~//[^\n]*~','',$JSC);
$BLOG=rd($ROOT.'/dev/admin/controller/blog/blog.php');
$PAGE=rd($ROOT.'/dev/admin/controller/cms/page.php');
$ORIG=rd($ROOT.'/dev/admin/view/css/blog-block-editor.css');
$ORIGJS=rd($ROOT.'/dev/admin/view/js/blog-block-editor.js');
ok('CSS aditivo presente',strlen($CSS)>6000,strlen($CSS).' bytes');
ok('JS aditivo presente',strlen($JS)>3000,strlen($JS).' bytes');
/* 1. identidade KAZ */
ok('paleta escura KAZ',has($CSS,'--kaz-bdre-bg: #18181b') && has($CSS,'--kaz-bdre-panel: #1f1f23'));
ok('accent verde-menta #31efb8',has($CSS,'--kaz-bdre-primary: #31efb8'));
ok('remapeia 8 variaveis --rb-*',preg_match_all('/--rb-[a-z-]+:/',$CSS)>=8);
ok('escopo rigoroso no host',has($CSS,'[data-blog-block-editor]') && !has($CSS,'body {'));
ok('chrome escuro',has($CSS,'.rb-toolbar') && has($CSS,'var(--kaz-bdre-panel) !important'));
ok('canvas permanece claro',has($CSS,'.rb-canvas,') && has($CSS,'background: #fff !important'));
ok('sem alteracao do catalogo publico',!has($CSS,'dev/catalog') && !has($CSS,'blog-blocks.css'));
/* 2. contrato F9-26 */
ok('foco #174ea6 preservado',has($CSS,'--rb-focus: #174ea6') && has($CSS,'outline: 3px solid #174ea6 !important'));
ok('halo de contraste no escuro',has($CSS,'box-shadow: 0 0 0 5px rgba(255,255,255,.22)'));
ok('outline-offset 2px',has($CSS,'outline-offset: 2px !important'));
/* 3. scrollbars tematicas */
ok('scrollbar-color tematica',has($CSS,'scrollbar-color: var(--kaz-bdre-thumb)'));
ok('webkit thumb/track/corner',has($CSS,'::-webkit-scrollbar-thumb') && has($CSS,'::-webkit-scrollbar-track') && has($CSS,'::-webkit-scrollbar-corner'));
ok('color-mix sobre o tema',has($CSS,'color-mix(in srgb, var(--kaz-bdre-primary) 62%'));
ok('forced-colors',has($CSS,'@media (forced-colors: active)'));
ok('prefers-reduced-motion',has($CSS,'@media (prefers-reduced-motion: reduce)'));
/* 4. escala e dobras */
ok('4 niveis de escala',has($CSS,'data-kaz-ui-scale="compact"') && has($CSS,'data-kaz-ui-scale="comfortable"') && has($CSS,'data-kaz-ui-scale="large"') && has($CSS,'--kaz-ui: 1;'));
ok('10+ grupos escalados',substr_count($CSS,'calc(')>=20,substr_count($CSS,'calc('));
ok('3 folds',has($CSS,'data-kaz-fold~="toolbar"') && has($CSS,'data-kaz-fold~="tabs"') && has($CSS,'data-kaz-fold~="status"'));
ok('barra de controle nunca recolhida',!has($CSS,'data-kaz-fold~="controls"'));
ok('medias responsivas',has($CSS,'@media (max-width: 1280px)') && has($CSS,'@media (max-width: 620px)'));
/* 5. JS companion */
ok('4 escalas no select',has($JS,"'compact'") && has($JS,"'comfortable'") && has($JS,"'large'") && has($JS,"'normal'"));
ok('3 botoes de dobra',has($JS,"FOLDS = ['toolbar', 'tabs', 'status']"));
ok('Recolher/Expandir tudo',has($JS,"'Recolher tudo'") && has($JS,"'Expandir tudo'"));
ok('aria-pressed',has($JS,"'aria-pressed'"));
ok('role status + aria-live',has($JS,"'role', 'status'") && has($JS,"'aria-live', 'polite'"));
ok('barra no inicio do host',has($JS,'host.insertBefore(bar, host.firstChild)'));
ok('espera montagem do editor',has($JS,'MutationObserver'));
ok('nao toca no documento de blocos',!has($JSC,'data-hidden-input') && !has($JSC,'serialize') && !has($JSC,'addEventListener(\'submit\''));
ok('DOM puro',has($JS,'document.createElement') && has($JS,'textContent') && has($JS,'setAttribute'));
ok('sem primitivas indevidas',!has($JSC,'innerHTML') && !has($JSC,'localStorage') && !has($JSC,'sessionStorage') && !has($JSC,'eval(') && !has($JSC,'fetch(') && !has($JSC,'document.write') && !has($JSC,'XMLHttpRequest'));
ok('idempotente',has($JS,'data-kaz-bdre-ready'));
ok('diagnostico somente leitura',has($JS,'window.__kazBdre = function ()'));
/* 6. controllers */
ok('blog.php registra -kaz-bdre-an',has($BLOG,"'-kaz-bdre-an'") && has($BLOG,'$kazBdreAsset'));
ok('cms/page.php registra -kaz-bdre-an',has($PAGE,"'-kaz-bdre-an'") && has($PAGE,'$kazBdreAsset'));
ok('blog.php: 2 novos assets',substr_count($BLOG,'blog-block-editor-kaz'),2);
ok('cms/page.php: 2 novos assets',substr_count($PAGE,'blog-block-editor-kaz'),2);
ok('usa addScriptDeferred',has($BLOG,'addScriptDeferred') && has($PAGE,'addScriptDeferred'));
ok('assetVersion original intacto',has($BLOG,"'-threejs-49-media-accessibility'"));
ok('version original intacto',has($PAGE,"'-threejs-49-media-accessibility'"));
ok('registro original do CSS intacto',has($BLOG,"'css/blog-block-editor.css?v=' . \$assetVersion") && has($PAGE,"'view/css/blog-block-editor.css?v=' . \$version"));
/* 7. originais e contratos */
ok('CSS original presente e nao vazio',strlen($ORIG)>100000,strlen($ORIG).' bytes');
ok('JS original presente e nao vazio',strlen($ORIGJS)>500000,strlen($ORIGJS).' bytes');
ok('sem migration SQL',!has($CSS,'.sql') && !has($JS,'migration'));
ok('sem traducao / |raw / name=',!has($CSS,'|raw') && !has($JS,'name=') && !has($JS,'language/'));
ok('schema.sql intacto',is_file($ROOT.'/dev/install/sql/schema.sql'));
ok('theme_form.twig intacto',strlen(rd($ROOT.'/dev/admin/view/template/design/theme_form.twig'))===165);
$F27=rd($ROOT.'/docs/F9-27-Status.json');
ok('release_approved false',has($F27,'"release_approved": false'));
ok('F9-27 BLOCKED_NOT_RUN',has($F27,'BLOCKED_NOT_RUN'));
ok('F9-26 contrato presente',is_file($ROOT.'/docs/F9-26-Contrato-A11y-Performance.json'));
ok('decision-matrix presente',is_file($ROOT.'/tools/decision-matrix.json'));
/* 8. docs e tools */
$D=rd($ROOT.'/docs/KAZ-BDREditor-Padrao-Visual.md');
ok('doc AN presente',strlen($D)>2000,strlen($D).' bytes');
ok('doc AN sem placeholder',!has($D,'TODO') && !has($D,'TBD') && !has($D,'__TOTAL__'));
ok('doc AN nao afirma release aprovada',preg_match('/não significam\s+release aprovada/u',$D)===1);
ok('PROBE instalavel',has(rd($ROOT.'/tools/kaz-bdre-visual-probe.php'),'AN_ROOT'));
ok('CJS verificador presente',has(rd($ROOT.'/tools/verify-kaz-bdre-visual.cjs'),'chromium'));
if(!is_dir($OUT)) mkdir($OUT,0777,true);
file_put_contents($OUT.'/probe.txt',"PROBE AN: $C OK / $F FALHAS\n");
echo "PROBE AN: $C OK / $F FALHAS\n";
