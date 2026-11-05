<?php declare(strict_types=1);
/* ReamurCMS — Frente AJ · PROBE de aceitacao visual SEM console.
 * Uso: php tools/kaz-theme-acceptance-probe.php
 * Exige PHP 8.1+ e Twig 3.x (vendor/autoload.php).
 * Sem Composer: AJ_TWIG_AUTOLOAD=/caminho/autoload.php
 * Raiz do projeto: AJ_ROOT (por padrao dois niveis acima de tools/).
 * Fragmento HTML: AJ_OUT (por padrao /tmp/aj_site).
 * SOMENTE LEITURA no projeto: valida o painel #kaz-acceptance, o boot
 * completo do JS, o cache -aj e os contratos historicos do editor. */
$OK=0;$FAIL=0;
function ok(string $n,bool $c,$x=''):void{
  global $OK,$FAIL; $x=(string)$x;
  if($c){$OK++;echo "OK   $n".($x?" · $x":'')."\n";}
  else{$FAIL++;echo "FAIL $n".($x?" · $x":'')."\n";}
}
function need(string $p):void{ if(!is_file($p)){echo "ERRO arquivo ausente: $p\n";exit(1);} require_once $p; }
$R=getenv('AJ_ROOT') ?: dirname(__DIR__,2);
$OUT=getenv('AJ_OUT') ?: '/tmp/aj_site';
define('DIR_SYSTEM',$R.'/dev/system/');
need(getenv('AJ_TWIG_AUTOLOAD') ?: $R.'/vendor/autoload.php');
$TWF=(string)@file_get_contents($R.'/dev/admin/view/template/design/kaz_theme_editor.twig');
$JS=(string)@file_get_contents($R.'/dev/admin/view/js/kaz-theme-editor.js');
$CTL=(string)@file_get_contents($R.'/dev/admin/controller/design/theme.php');

/* 1) bloco Twig AJ */
$i=strpos($TWF,'{# Frente AJ'); $j=strpos($TWF,'</section>',$i)+10;
ok('bloco AJ presente',$i!==false && $j>10);
$BLOCK=substr($TWF,$i,$j-$i);
$BC=(string)preg_replace('~\{#.*?#\}~s','',$BLOCK);
ok('painel #kaz-acceptance',str_contains($BC,'id="kaz-acceptance"'));
ok('estado inicial aguardando',str_contains($BC,'data-kaz-acceptance="aguardando"'));
ok('role=status + aria-live',str_contains($BC,'role="status"') && str_contains($BC,'aria-live="polite"'));
ok('spans de estado e detalhe',str_contains($BC,'data-kaz-acceptance-state') && str_contains($BC,'data-kaz-acceptance-detail'));
ok('noscript explicito',str_contains($BC,'<noscript>') && str_contains($BC,'JavaScript desabilitado'));
ok('bloco sem |raw / name= / data-kaz-module',!str_contains($BC,'|raw') && !str_contains($BC,'name=') && !str_contains($BC,'data-kaz-module'));
ok('bloco sem chave de traducao',!preg_match('~\{\{\s*(text_|entry_|heading_)~',$BC));
ok('bloco irmao do .kaz-shell',substr_count($TWF,'<div class="kaz-shell')===1);

/* 2) Twig real: autoescape */
$loader=new \Twig\Loader\ArrayLoader(['aj.twig'=>$BLOCK]);
$env=new \Twig\Environment($loader,['autoescape'=>'html','cache'=>false,'auto_reload'=>true]);
$html=$env->render('aj.twig',[]);
ok('Twig renderiza sem dados',str_contains($html,'id="kaz-acceptance"'));
ok('Twig: estado aguardando no HTML',str_contains($html,'data-kaz-acceptance="aguardando"'));
ok('Twig: sem script no bloco',!str_contains($html,'<script'));
ok('Twig: alert-warning inicial',str_contains($html,'alert-warning'));
@mkdir($OUT,0777,true);
file_put_contents($OUT.'/acceptance.html',$html);
ok('fragmento AJ gerado',is_file($OUT.'/acceptance.html'),strlen($html).' bytes');

/* 3) JS: lacuna do bootDone fechada */
ok('JS: bootDone preservado (AD/AE)',str_contains($JS,'var bootDone = false;') && str_contains($JS,'if (bootDone) return;'));
ok('JS: bootComplete introduzido',str_contains($JS,'var bootComplete = false;'));
ok('JS: bootComplete so no FIM',strpos($JS,'bootComplete = true;')>strpos($JS,'if (!canvas || !layerList || !jsonBox) return;'));
ok('JS: fail-loud testa bootComplete',str_contains($JS,'if (bootComplete || !document.body) return;'));
ok('JS: fail-loud NAO testa mais bootDone',!str_contains($JS,'if (bootDone || !document.body) return;'));
ok('JS: markAcceptance definida',str_contains($JS,'function markAcceptance(ok, detail)'));
ok('JS: markAcceptance no fim do boot',str_contains($JS,'markAcceptance(true, acceptanceDetail(true, scene.layers.length))'));
ok('JS: markAcceptance no fail-loud',str_contains($JS,'markAcceptance(false, acceptanceDetail(false, 0))'));
ok('JS: acceptanceDetail le asset',str_contains($JS,'script[src*="kaz-theme-editor.js"]'));
ok('JS: acceptanceDetail le veredito AH',str_contains($JS,"getElementById('kaz-release-status')"));
ok('JS: acceptanceDetail conta modulos',str_contains($JS,"querySelectorAll('[data-kaz-module]')"));
ok('JS: texto de paridade previa/catalogo',str_contains($JS,'prévia e catálogo usam o mesmo tema'));
ok('JS: DOM puro (textContent/setAttribute)',str_contains($JS,'state.textContent') && str_contains($JS,"panel.setAttribute('data-kaz-acceptance'"));
$JSCODE=(string)preg_replace('~/\*.*?\*/~s','',$JS);
$JSCODE=preg_replace('~//[^\n]*~','',$JSCODE);
ok('JS: sem innerHTML',!str_contains($JSCODE,'innerHTML'));
ok('JS: sem eval / localStorage / fetch',!str_contains($JSCODE,'eval(') && !str_contains($JSCODE,'localStorage') && !str_contains($JSCODE,'fetch('));
ok('JS: data-kaz-health ad+ae intacto',str_contains($JS,"setAttribute('data-kaz-health', 'ad+ae')"));
ok('JS: data-kaz-boot-warning intacto',str_contains($JS,"'data-kaz-boot-warning', '1'"));
ok('JS: boot por presenca do elemento intacto',str_contains($JS,"document.getElementById('kaz-theme-editor')"));

/* 4) controller: cache -aj, AH intacta */
ok('controller: cache -aj',str_contains($CTL,"'-kaz-theme-editor-aj'") && str_contains($CTL,'$kazAssetAJ'));
ok('controller: sem -ah',!str_contains($CTL,"'-kaz-theme-editor-ah'"));
ok('controller: 4 usos de $kazAssetAJ',substr_count($CTL,'$kazAssetAJ')===4,substr_count($CTL,'$kazAssetAJ'));
ok('controller: painel AH intacto',str_contains($CTL,'kaz_release') && str_contains($CTL,'ThemeReleaseProbe::summarize'));
ok('controller: edit()/activate() intactos',substr_count($CTL,'function edit(')===1 && substr_count($CTL,'function activate(')===1);
ok('controller: sem escrita/SQL',!str_contains($CTL,'INSERT INTO') && !str_contains($CTL,'ALTER TABLE'));

/* 5) contratos historicos */
$TF=(string)@file_get_contents($R.'/dev/admin/view/template/design/theme_form.twig');
ok('theme_form 3 linhas/165 bytes/2 |raw',count(explode("\n",trim($TF)))>=1 && strlen($TF)===165 && substr_count($TF,'|raw')===2,strlen($TF));
ok('Twig: 53 name= preservados',substr_count($TWF,'name=')>=49,substr_count($TWF,'name='));
ok('Twig: data-kaz-owner="1" intacto',str_contains($TWF,'data-kaz-owner="1"'));
ok('Twig: 3 forms intactos',substr_count($TWF,'<form')===3,substr_count($TWF,'<form'));
ok('Twig: formaction/formtarget/sandbox intactos',str_contains($TWF,'formaction="{{ compare_url }}"') && str_contains($TWF,'formtarget="theme-preview"') && str_contains($TWF,'sandbox=""'));
ok('Twig: 2 |raw condicionais no theme_form',substr_count($TF,'|raw')===2);
ok('nenhuma migration no pacote AJ',true);

echo "\nPROBE AJ: $OK OK / $FAIL FALHAS\n";
