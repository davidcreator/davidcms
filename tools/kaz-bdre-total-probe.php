<?php
/* Frente AP — PROBE instalavel de NAO-VAZAMENTO. Somente leitura.
   AP_ROOT / AP_OUT. Substitui o PROBE da AO (mesmo caminho). */
$ROOT = getenv('AP_ROOT') ?: dirname(__DIR__, 2);
$OUT  = getenv('AP_OUT')  ?: '/tmp/ap_site';
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
$CODE=preg_replace('~/\*.*?\*/~s','',$CSS);
ok('CSS AP presente',strlen($CSS)>8000,strlen($CSS).' bytes');
ok('CSS AN permanece presente',strlen($AN)>15000,strlen($AN).' bytes');
/* 1. RECOLHIMENTO DE ESCOPO — o coracao da correcao image-9.png */
ok('SEM tokens --bs-* (causa da sidebar verde)',!has($CODE,'--bs-'));
ok('SEM tokens --rms-* (skin do Admin)',!has($CODE,'--rms-'));
ok('SEM :root / html como alvo',!has($CODE,':root') && !has($CODE,'html:has') && !has($CODE,'html[data-'));
ok('SEM #menu / .navbar / .page-titlebar',!has($CODE,'#menu') && !has($CODE,'.navbar') && !has($CODE,'.page-titlebar'));
ok('SEM .sidebar / .breadcrumb / .container-fluid do Admin',!has($CODE,'.sidebar {') && !has($CODE,'.breadcrumb') && !has($CODE,'.container-fluid'));
ok('html/body NUNCA sao alvo de regra',preg_match('/(?<![\w-])(html|body)\s*\{/',$CODE)===0);
ok('body:has() SEMPRE com descendente',preg_match('/body:has\([^)]*\)\s*\{/',$CODE)===0);
ok('SEM fallback html[data-kaz-bdre-tema]',!has($CODE,'data-kaz-bdre-tema'));
ok('SEM data-bs-theme=dark (modo sempre escuro revogado)',!has($CODE,'[data-bs-theme="dark"]'));
/* varredura: todo seletor precisa conter o host ou :has(host) */
preg_match_all('~([^{}]+)\{~s',$CODE,$M);
$sel=0;$bad=[];
foreach($M[1] as $s){
  $s=trim(preg_replace('/\s+/',' ',$s)); if($s==='')continue;
  if(str_starts_with($s,'@media')||str_starts_with($s,'@supports'))continue;
  $sel++;
  if(!str_contains($s,'[data-blog-block-editor]')) $bad[]=$s;
}
ok('TODOS os seletores escopados ao BDREditor',count($bad)===0,$sel.' seletores · '.count($bad).' fora do escopo');
if($bad) foreach(array_slice($bad,0,3) as $x) echo "     fora: ".substr($x,0,110)."\n";
ok('catalogo publico fora do escopo',!has($CODE,'dev/catalog') && !has($CODE,'blog-blocks.css'));
/* 2. so o que continua permitido: host + overlays rb-* */
ok('host [data-blog-block-editor] presente',has($CODE,'[data-blog-block-editor]'));
ok('overlays condicionados por :has',substr_count($CODE,'body:has([data-blog-block-editor])')>20,
   substr_count($CODE,'body:has([data-blog-block-editor])').' usos');
ok('dialogs / padroes / inserter do editor',has($CODE,'rb-command-dialog') && has($CODE,'rb-pattern-library') && has($CODE,'rb-inserter'));
ok('fullscreen / arraste / selecao',has($CODE,'is-fullscreen') && has($CODE,'rb-drag') && has($CODE,'is-selected'));
ok('sidebar / rail / inspector DO EDITOR',has($CODE,'rb-builder-sidebar') && has($CODE,'rb-workspace-rail') && has($CODE,'rb-builder-inspector'));
ok('tokens locais --kaz-ap-* (nao globais)',has($CODE,'--kaz-ap-panel') && has($CODE,'--kaz-ap-primary'));
ok('accent verde apenas no editor',has($CODE,'--kaz-ap-primary: #31efb8'));
/* 3. canvas e foco preservados */
ok('canvas permanece claro',has($CODE,'#fff !important') && has($CODE,'.rb-canvas'));
ok('foco #174ea6 / 3px / 2px',has($CODE,'3px solid #174ea6') && has($CODE,'outline-offset: 2px'));
ok('sem troca do foco para verde',!has($CODE,'outline: 3px solid #31efb8'));
/* 4. scrollbars, acessibilidade, responsivo — dentro do host */
ok('scrollbar-color sem tocar no body',has($CODE,'scrollbar-color: #31efb8 #1f1f23') && !has($CODE,'html {') );
ok('webkit thumb/track',has($CODE,'::-webkit-scrollbar-thumb') && has($CODE,'::-webkit-scrollbar-track'));
ok('forced-colors',has($CODE,'@media (forced-colors: active)'));
ok('prefers-reduced-motion',has($CODE,'@media (prefers-reduced-motion: reduce)'));
ok('responsivo 760px',has($CODE,'@media (max-width: 760px)'));
/* 5. controllers: mesmo caminho, marcador novo */
ok('blog.php registra -kaz-bdre-ap',has($BLOG,"'-kaz-bdre-ap'") && has($BLOG,'$kazBdreTotalAsset'));
ok('cms/page.php registra -kaz-bdre-ap',has($PAGE,"'-kaz-bdre-ap'") && has($PAGE,'$kazBdreTotalAsset'));
ok('marcador da AO revogado',!has($BLOG,"'-kaz-bdre-ao'") && !has($PAGE,"'-kaz-bdre-ao'"));
ok('blog.php AP depois da AN',strpos($BLOG,'blog-block-editor-kaz.css')<strpos($BLOG,'blog-block-editor-kaz-total.css'));
ok('cms/page.php AP depois da AN',strpos($PAGE,'blog-block-editor-kaz.css')<strpos($PAGE,'blog-block-editor-kaz-total.css'));
ok('marcador AN intacto',has($BLOG,"'-kaz-bdre-an'") && has($PAGE,"'-kaz-bdre-an'"));
ok('versao original intacta',has($BLOG,"'-threejs-49-media-accessibility'") && has($PAGE,"'-threejs-49-media-accessibility'"));
ok('JS AN intacto',strlen($ANJS)>5000 && has($ANJS,'window.__kazBdre'));
/* 6. intocaveis e contratos */
ok('CSS original presente',strlen($ORIG)>100000,strlen($ORIG).' bytes');
ok('JS original presente',strlen($ORIGJS)>500000,strlen($ORIGJS).' bytes');
ok('sem JS novo / DOM alterado',!is_file($ROOT.'/dev/admin/view/js/blog-block-editor-kaz-total.js'));
ok('sem primitivas indevidas',!has($CODE,'innerHTML') && !has($CODE,'localStorage') && !has($CODE,'eval('));
ok('sem |raw / name= / migration / traducao',!has($CODE,'|raw') && !has($CODE,'name=') && !has($CODE,'migration') && !has($CODE,'language/'));
ok('schema.sql intacto',is_file($ROOT.'/dev/install/sql/schema.sql'));
$F27=rd($ROOT.'/docs/F9-27-Status.json');
ok('release_approved false',has($F27,'"release_approved": false'));
ok('F9-27 BLOCKED_NOT_RUN',has($F27,'BLOCKED_NOT_RUN'));
ok('F9-26 contrato presente',is_file($ROOT.'/docs/F9-26-Contrato-A11y-Performance.json'));
ok('decision-matrix presente',is_file($ROOT.'/tools/decision-matrix.json'));
/* 7. docs e tools */
$D=rd($ROOT.'/docs/KAZ-BDREditor-Tema-Total.md');
ok('doc corrigido presente',strlen($D)>2000,strlen($D).' bytes');
ok('doc sem placeholder',!has($D,'TODO') && !has($D,'TBD') && !has($D,'__TOTAL__'));
ok('doc nao afirma release',preg_match('/não significam\s+release aprovada/u',$D)===1);
ok('doc registra a reprovacao image-9',has($D,'image-9.png') && has($D,'image-10.png'));
$RG=rd($ROOT.'/docs/Registro-Visual-AP.md');
ok('registro visual presente',strlen($RG)>1200 && has($RG,'image-9.png'));
ok('PROBE instalavel',has(rd($ROOT.'/tools/kaz-bdre-total-probe.php'),'AP_ROOT'));
ok('CJS verificador presente',has(rd($ROOT.'/tools/verify-kaz-bdre-total.cjs'),'chromium'));
if(!is_dir($OUT)) mkdir($OUT,0777,true);
file_put_contents($OUT.'/probe.txt',"PROBE AP: $C OK / $F FALHAS\n");
echo "PROBE AP: $C OK / $F FALHAS\n";
