<?php
/* Frente AM — PROBE instalavel. Somente leitura. AM_ROOT / AM_OUT. */
$ROOT = getenv('AM_ROOT') ?: dirname(__DIR__, 2);
$OUT  = getenv('AM_OUT')  ?: '/tmp/am_site';
$C=0;$F=0;
function ok($l,$c,$x=''){ global $C,$F;
  if($c){$C++;echo "OK   $l".($x!==''?" · $x":'')."\n";}
  else{$F++;echo "FAIL $l".($x!==''?" · $x":'')."\n";} }
function rd($p){ return is_file($p)?(string)file_get_contents($p):''; }
function has($h,$n){ return str_contains($h,$n); }
$CSS=rd($ROOT.'/dev/admin/view/css/kaz-theme-editor-ajuste.css');
$JS =rd($ROOT.'/dev/admin/view/js/kaz-theme-editor-ajuste.js');
$CTL=rd($ROOT.'/dev/admin/controller/design/theme.php');
$TWG=rd($ROOT.'/dev/admin/view/template/design/kaz_theme_editor.twig');
$EDT=rd($ROOT.'/dev/admin/view/js/kaz-theme-editor.js');
$ALJ=rd($ROOT.'/dev/admin/view/js/kaz-theme-editor-ergonomia.js');
$TFM=rd($ROOT.'/dev/admin/view/template/design/theme_form.twig');
ok('CSS aditivo presente',strlen($CSS)>1500,strlen($CSS).' bytes');
ok('JS aditivo presente',strlen($JS)>4000,strlen($JS).' bytes');
/* 1. auto-ajuste universal */
ok('chama botao publico de ajuste',has($JS,"click('[data-kaz-action=\"screen-fit\"]')"));
ok('reduz zoom ate caber',has($JS,"click('[data-kaz-zoom=\"out\"]')") && has($JS,'over() > 4'));
ok('duas passagens no boot',has($JS,'scheduleFit()') && has($JS,'setTimeout(fitAll, 120)'));
ok('reajuste em resize',has($JS,"addEventListener('resize'"));
ok('reajuste apos colunas',has($JS,'scheduleFit();') && has($JS,'function up()'));
ok('nao depende de media query mobile',!has($JS,'matchMedia'));
ok('registra overflow',has($JS,"'data-kaz-fit-overflow'"));
/* 2. screenbar */
ok('nowrap em desktop',has($CSS,'@media (min-width: 981px)') && has($CSS,'flex-wrap: nowrap'));
ok('altura escalada',has($CSS,'max-height: calc(38px * var(--kaz-ui, 1))'));
ok('filhos nao encolhem',has($CSS,'.kaz-screenbar--outer > * { flex: none; }'));
/* 3. divisorias */
ok('workspace posicionado',has($CSS,'#kaz-theme-editor .kaz-workspace { position: relative; }'));
ok('colunas so apos arraste',has($CSS,'#kaz-theme-editor[data-kaz-cols] .kaz-workspace'));
ok('colapso left preservado',has($CSS,'[data-kaz-cols][data-kaz-collapsed~="left"]'));
ok('colapso right preservado',has($CSS,'[data-kaz-cols][data-kaz-collapsed~="right"]'));
ok('colapso ambos preservado',has($CSS,'[data-kaz-collapsed~="left"][data-kaz-collapsed~="right"]'));
ok('alcas escondidas no mobile',has($CSS,'.kaz-grip { display: none; }'));
ok('alca visivel no desktop',has($CSS,'cursor: col-resize') && has($CSS,'touch-action: none'));
ok('cor da alca vem do tema',has($CSS,'var(--kaz-al-primary'));
ok('2 alcas criadas',has($JS,"['left', 'right'].forEach"));
ok('role separator',has($JS,"'role', 'separator'"));
ok('aria-valuenow',has($JS,"'aria-valuenow'"));
ok('teclado setas',has($JS,"'ArrowLeft'") && has($JS,"'ArrowRight'"));
ok('duplo clique restaura',has($JS,"addEventListener('dblclick'") && has($JS,"removeAttribute('data-kaz-cols')"));
ok('limites 160-420',has($JS,'MIN = 160, MAX = 420'));
ok('pointer events',has($JS,"addEventListener('pointerdown'") && has($JS,"'pointermove'"));
ok('setPointerCapture',has($JS,'setPointerCapture'));
/* 4. inspector ao vivo */
ok('MutationObserver no SVG',has($JS,'MutationObserver') && has($JS,'observer.observe(canvas'));
ok('4 campos acompanhados',has($JS,"['x', 'y', 'w', 'h'].forEach"));
ok('le retangulo selecionado',has($JS,"'.kaz-g--selected'"));
ok('escreve value sem eventos',has($JS,'f.value = String(v)') && !has($JS,'dispatchEvent'));
ok('throttle por rAF',has($JS,'requestAnimationFrame'));
/* 5. seguranca e contratos */
$b=['inner'.'HTML','loc'.'alStorage','session'.'Storage','ev'.'al(','fet'.'ch(','document.w'.'rite'];
foreach($b as $i=>$v) ok('sem primitiva indevida #'.($i+1),!has($JS,$v));
ok('DOM puro',has($JS,'document.createElement') && has($JS,'setAttribute'));
ok('sem SQL',!has($JS,'SELECT ') && !has($JS,'INSERT INTO'));
ok('sem escrita em arquivo',!has($JS,'file_put_contents'));
ok('idempotente',has($JS,'data-kaz-ajuste-ready'));
ok('diagnostico somente leitura',has($JS,'window.__kazAjuste = function ()'));
ok('controller mantem 4 usos de -aj',substr_count($CTL,'$kazAssetAJ')===4,substr_count($CTL,'$kazAssetAJ'));
ok('controller mantem -al',has($CTL,"'-kaz-theme-editor-aj-al'"));
ok('controller registra -aj-am',has($CTL,"'-kaz-theme-editor-aj-am'") && has($CTL,'$kazAssetAM'));
ok('controller: 2 assets AM',substr_count($CTL,'ajuste.css')===1 && substr_count($CTL,'ajuste.js')===1);
ok('controller: sem SQL novo',!has($CTL,'ALTER TABLE') && !has($CTL,'CREATE TABLE'));
ok('JS da AJ intacto',has($EDT,'bootComplete') && !has($EDT,'__kazAjuste'));
ok('JS da AL intacto',has($ALJ,'__kazErgo') && !has($ALJ,'__kazAjuste'));
ok('Twig intacto',has($TWG,'data-kaz-owner="1"') && has($TWG,'id="kaz-acceptance"'));
ok('3 forms no Twig',substr_count($TWG,'<form')===3);
ok('name= nao aumentou',substr_count($TWG,'name=')>=49 && substr_count($TWG,'name=')<=60);
ok('theme_form 165 bytes / 2 raw',strlen($TFM)===165 && substr_count($TFM,'|raw')===2);
/* 6. congelados e docs */
ok('schema.sql presente',is_file($ROOT.'/dev/install/sql/schema.sql'));
$st=rd($ROOT.'/docs/F9-27-Status.json');
ok('release_approved nao true',!has($st,'"release_approved": true'));
ok('F9-27 BLOCKED_NOT_RUN',has($st,'BLOCKED_NOT_RUN'));
$dm=json_decode(rd($ROOT.'/tools/decision-matrix.json'),true);
ok('10 PENDING_DECISION',(is_array($dm)?(int)($dm['status_counts']['PENDING_DECISION']??0):0)===10);
ok('11 hashes',is_array($dm)&&count($dm['frozen_artifacts_sha256']??[])===11);
ok('14 manifestos',count(glob($ROOT.'/CHECKSUMS*.txt'))===14);
$dd=glob($ROOT.'/docs/KAZTheme-Editor*.md');
ok('18 documentos',count($dd)===18,count($dd));
$n=0;foreach($dd as $f) if(has(rd($f),'Frente AM')) $n++;
ok('18 docs com secao AM',$n===18,$n);
$doc=rd($ROOT.'/docs/KAZTheme-Editor-Ajuste-Direto-Na-Tela.md');
ok('doc AM sem placeholder',!has($doc,'TODO')&&!has($doc,'TBD')&&!has($doc,'__TOTAL__'));
ok('doc AM cita a causa medida',has($doc,'100%') && has($doc,'980px'));
ok('doc AM nao afirma release aprovada',has($doc,'não significam') && has($doc,'release aprovada'));
ok('PROBE tool presente',is_file($ROOT.'/tools/kaz-theme-ajuste-probe.php'));
ok('CJS tool presente',is_file($ROOT.'/tools/verify-kaz-ajuste.cjs'));
if(!is_dir($OUT)) mkdir($OUT,0777,true);
file_put_contents($OUT.'/probe.txt',"PROBE AM: $C OK / $F FALHAS\n");
echo "PROBE AM: $C OK / $F FALHAS\n";
exit($F?1:0);
