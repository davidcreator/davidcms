<?php
/* Frente AO — PROBE instalavel. Somente leitura. AO_ROOT / AO_OUT. */
$ROOT = getenv('AO_ROOT') ?: dirname(__DIR__, 2);
$OUT  = getenv('AO_OUT')  ?: '/tmp/ao_site';
$C=0;$F=0;
function ok($l,$c,$x=''){ global $C,$F;
  if($c){$C++;echo "OK   $l".($x!==''?" · $x":'')."\n";}
  else{$F++;echo "FAIL $l".($x!==''?" · $x":'')."\n";} }
function rd($p){ return is_file($p)?(string)file_get_contents($p):''; }
function has($h,$n){ return str_contains($h,$n); }
$CSS=rd($ROOT.'/dev/admin/view/css/blog-block-editor-kaz-total.css');
$AN =rd($ROOT.'/dev/admin/view/css/blog-block-editor-kaz.css');
$ANJS=rd($ROOT.'/dev/admin/view/js/blog-block-editor-kaz.js');
$BLOG=rd($ROOT.'/dev/admin/controller/blog/blog.php');
$PAGE=rd($ROOT.'/dev/admin/controller/cms/page.php');
$ORIG=rd($ROOT.'/dev/admin/view/css/blog-block-editor.css');
$ORIGJS=rd($ROOT.'/dev/admin/view/js/blog-block-editor.js');
ok('CSS AO presente',strlen($CSS)>18000,strlen($CSS).' bytes');
ok('CSS AN permanece presente',strlen($AN)>15000,strlen($AN).' bytes');
/* 1. escopo e modo */
ok('escopo :has nas telas do BDREditor',has($CSS,'body:has([data-blog-block-editor])'));
ok('fallback sem :has',has($CSS,'html[data-kaz-bdre-tema="1"]'));
ok('html tambem escurece',has($CSS,'html:has([data-blog-block-editor])'));
ok('SEM condicao data-bs-theme=dark (sempre escuro)',!has($CSS,'[data-bs-theme="dark"]'));
ok('catalogo publico fora do escopo',!has($CSS,'dev/catalog') && !has($CSS,'blog-blocks.css'));
/* 2. tokens */
ok('tokens Bootstrap sobrescritos',has($CSS,'--bs-body-bg: #18181b') && has($CSS,'--bs-body-color: #e9e9ee') && has($CSS,'--bs-primary: #31efb8'));
ok('tokens de superficie e borda',has($CSS,'--bs-card-bg') && has($CSS,'--bs-border-color') && has($CSS,'--bs-secondary-bg'));
ok('accent verde-menta',has($CSS,'--kaz-bdre-primary: #31efb8'));
ok('paleta KAZ no body',has($CSS,'--kaz-bdre-bg: #18181b') && has($CSS,'--kaz-bdre-panel: #1f1f23'));
/* 3. superficies do Admin */
ok('card / form-control / nav-tabs',has($CSS,'.card') && has($CSS,'.form-control') && has($CSS,'.nav-tabs'));
ok('titlebar / breadcrumb / alert / badge',has($CSS,'.page-titlebar') && has($CSS,'.breadcrumb') && has($CSS,'.alert') && has($CSS,'.badge'));
ok('modal / dropdown / table / sidebar',has($CSS,'.modal-content') && has($CSS,'.dropdown-menu') && has($CSS,'.table') && has($CSS,'.sidebar'));
ok('botao primario verde',has($CSS,'.btn-primary') && has($CSS,'var(--kaz-bdre-primary) !important'));
ok('textos secundarios legiveis',has($CSS,'.text-muted') && has($CSS,'var(--kaz-bdre-muted)'));
/* 4. estados sob demanda */
ok('dialogs e overlays em body',has($CSS,'rb-command-overlay') && has($CSS,'rb-command-dialog') && has($CSS,'rb-group-confirm-dialog'));
ok('biblioteca de padroes',has($CSS,'rb-pattern-library'));
ok('inserter / previews / tabelas',has($CSS,'rb-inserter') && has($CSS,'rb-preview-') && has($CSS,'rb-table-'));
ok('fullscreen / arraste / selecao',has($CSS,'is-fullscreen') && has($CSS,'rb-drag') && has($CSS,'is-selected'));
ok('sidebar / rail / inspector do editor',has($CSS,'rb-builder-sidebar') && has($CSS,'rb-workspace-rail') && has($CSS,'rb-builder-inspector'));
ok('mais de 150 seletores escopados',substr_count($CSS,':is(body:has')>=150,substr_count($CSS,':is(body:has'));
/* 5. canvas e foco */
ok('canvas permanece claro',has($CSS,'#fff !important') && has($CSS,'.rb-canvas'));
ok('foco #174ea6 / 3px / 2px',has($CSS,'3px solid #174ea6') && has($CSS,'outline-offset: 2px'));
ok('sem troca do foco para verde',!has($CSS,'outline: 3px solid #31efb8'));
/* 6. scrollbars e acessibilidade */
ok('scrollbar-color no body e html',has($CSS,'scrollbar-color: var(--kaz-bdre-thumb)'));
ok('webkit thumb/track',has($CSS,'::-webkit-scrollbar-thumb') && has($CSS,'::-webkit-scrollbar-track'));
ok('forced-colors',has($CSS,'@media (forced-colors: active)'));
ok('prefers-reduced-motion',has($CSS,'@media (prefers-reduced-motion: reduce)'));
ok('responsivo 760px',has($CSS,'@media (max-width: 760px)'));
/* 7. controllers */
ok('blog.php registra -kaz-bdre-ao',has($BLOG,"'-kaz-bdre-ao'") && has($BLOG,'$kazBdreTotalAsset'));
ok('cms/page.php registra -kaz-bdre-ao',has($PAGE,"'-kaz-bdre-ao'") && has($PAGE,'$kazBdreTotalAsset'));
ok('blog.php AO depois da AN',strpos($BLOG,'blog-block-editor-kaz.css')<strpos($BLOG,'blog-block-editor-kaz-total.css'));
ok('cms/page.php AO depois da AN',strpos($PAGE,'blog-block-editor-kaz.css')<strpos($PAGE,'blog-block-editor-kaz-total.css'));
ok('marcador AN intacto',has($BLOG,"'-kaz-bdre-an'") && has($PAGE,"'-kaz-bdre-an'"));
ok('versao original intacta',has($BLOG,"'-threejs-49-media-accessibility'") && has($PAGE,"'-threejs-49-media-accessibility'"));
ok('JS AN intacto',strlen($ANJS)>5000 && has($ANJS,'window.__kazBdre'));
/* 8. intocaveis e contratos */
ok('CSS original presente',strlen($ORIG)>100000,strlen($ORIG).' bytes');
ok('JS original presente',strlen($ORIGJS)>500000,strlen($ORIGJS).' bytes');
ok('sem JS novo / DOM alterado',!is_file($ROOT.'/dev/admin/view/js/blog-block-editor-kaz-total.js'));
$CSSCODE = preg_replace('~/\*.*?\*/~s','',$CSS);
ok('sem primitivas indevidas',!has($CSSCODE,'innerHTML') && !has($CSSCODE,'localStorage') && !has($CSSCODE,'eval('));
ok('sem |raw / name= / migration / traducao',!has($CSSCODE,'|raw') && !has($CSSCODE,'name=') && !has($CSSCODE,'migration') && !has($CSSCODE,'language/'));
ok('schema.sql intacto',is_file($ROOT.'/dev/install/sql/schema.sql'));
$F27=rd($ROOT.'/docs/F9-27-Status.json');
ok('release_approved false',has($F27,'"release_approved": false'));
ok('F9-27 BLOCKED_NOT_RUN',has($F27,'BLOCKED_NOT_RUN'));
ok('F9-26 contrato presente',is_file($ROOT.'/docs/F9-26-Contrato-A11y-Performance.json'));
ok('decision-matrix presente',is_file($ROOT.'/tools/decision-matrix.json'));
/* 9. docs e tools */
$D=rd($ROOT.'/docs/KAZ-BDREditor-Tema-Total.md');
ok('doc AO presente',strlen($D)>2000,strlen($D).' bytes');
ok('doc AO sem placeholder',!has($D,'TODO') && !has($D,'TBD') && !has($D,'__TOTAL__'));
ok('doc AO nao afirma release',preg_match('/não significam\s+release aprovada/u',$D)===1);
ok('PROBE instalavel',has(rd($ROOT.'/tools/kaz-bdre-total-probe.php'),'AO_ROOT'));
ok('CJS verificador presente',has(rd($ROOT.'/tools/verify-kaz-bdre-total.cjs'),'chromium'));
if(!is_dir($OUT)) mkdir($OUT,0777,true);
file_put_contents($OUT.'/probe.txt',"PROBE AO: $C OK / $F FALHAS\n");
echo "PROBE AO: $C OK / $F FALHAS\n";
