<?php
/** Frente BM — export/decode/compile REAIS com as classes do HEAD ad7643de. */
$ROOT='/tmp/bm_src/dev/system/library/';
$order=[
 'cms/page_layout.php',
 'theme/condition_engine.php',
 'theme/token_registry.php',
 'theme/section_registry.php',
 'theme/component_registry.php',
 'theme/theme_schedule.php',
 'theme/theme_media.php',
 'theme/theme_sites.php',
 'theme/theme_scene_css.php',
 'theme/theme_snapshot.php',
 'theme/theme_package.php',
];
foreach($order as $f) require_once $ROOT.$f;
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\ThemeSceneCss;
use Reamur\System\Library\Theme\ThemeSnapshot;
use Reamur\System\Library\Theme\ConditionEngine;

$cfg=json_decode(file_get_contents('/tmp/bm_out/bm_config.json'),true);
$P=0;$F=0;
function ok($n,$c,$x=''){global $P,$F;if($c)$P++;else$F++;echo ($c?'OK   ':'FAIL ').$n.($x!==''?' · '.$x:'')."\n";}

$engine=new ConditionEngine();
$pkg=new ThemePackage($engine,new \Reamur\System\Library\Theme\TokenRegistry());
$config=$cfg['config'];
$errors=$pkg->validateConfig($config);
ok('validateConfig = []', $errors===[], json_encode($errors));
if($errors!==[]){echo "ABORT\n";exit(1);}

$scene=ThemePackage::normalizeScene($config['kaz_scene']);
ok('normalizeScene ok', $scene!==null);
$compiled=(new ThemeSceneCss())->compile($config['kaz_scene']);
ok('ThemeSceneCss compiled', $compiled['enabled']===true && count($compiled['layers'])===16,
   count($compiled['layers']).' camadas · css '.strlen($compiled['css']).' bytes');
ok('CSS allowlistado', preg_match('/[^A-Za-z0-9#:.;\-]/',$compiled['css'])!==1);

$snap=new ThemeSnapshot($engine,new \Reamur\System\Library\Theme\TokenRegistry(),$pkg);
foreach(['common/home','catalog/project_list','tool/tool_list','error/not_found'] as $route){
  $ctx=['route'=>$route,'language'=>'pt-br','device'=>'desktop','mobile'=>false,
        'logged_in'=>false,'user_group'=>'','hour'=>12,'weekday'=>3,'date'=>'2026-11-07',
        'month'=>11,'variant'=>'','https'=>true,'ajax'=>false,'query'=>[],'site'=>'primary','path'=>'/'];
  $s=$snap->resolve($config,$ctx,[],$cfg['slug']);
  ok('snapshot '.$route.' cena='.($s['scene']??'?'),
     is_array($s) && isset($s['scene']), (string)($s['scene']??''));
}

$out=$pkg->export($cfg['name'],$cfg['slug'],$config,['source'=>'draft']);
$json=$pkg->encode($out);
ok('encode <= 512 KiB', strlen($json)<=ThemePackage::MAX_BYTES, strlen($json).' bytes');
$d=$pkg->decode($json);
ok('decode ok=true', $d['ok']===true, json_encode($d['errors']));
ok('decode 25 fontes', count($d['config']['templates'])===25);
ok('decode three.enabled', ($d['config']['three']['enabled']??false)===true);
ok('decode kaz_scene 16 camadas', count($d['config']['kaz_scene']['layers'])===16);
ok('checksum canonico', $out['checksum']===$pkg->checksum($config), $out['checksum']);

if(!is_dir('/tmp/bm_out')) mkdir('/tmp/bm_out',0777,true);
file_put_contents('/tmp/bm_out/'.$cfg['slug'].'.reamurtheme.json',$json);
file_put_contents('/tmp/bm_out/export-report.json',json_encode([
 'name'=>$cfg['name'],'slug'=>$cfg['slug'],'checksum'=>$out['checksum'],
 'bytes'=>strlen($json),'templates'=>count($d['config']['templates']),
 'scene_layers'=>count($d['config']['kaz_scene']['layers']),
 'css_scene_layers'=>count($compiled['layers']),
 'conditions'=>count($d['config']['conditions']['rules']),
 'errors'=>$errors],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "\nPHP BM export: $P OK / $F FALHAS\n";
exit($F?1:0);
