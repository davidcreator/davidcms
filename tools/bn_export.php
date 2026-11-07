<?php
/** Frente BN — export/decode/compile REAIS com as classes do HEAD ad7643de. */
$ROOT='/home/user/bn_src/dev/system/library/';
foreach(['cms/page_layout.php','theme/condition_engine.php','theme/token_registry.php',
 'theme/section_registry.php','theme/component_registry.php','theme/theme_schedule.php',
 'theme/theme_media.php','theme/theme_sites.php','theme/theme_scene_css.php',
 'theme/theme_snapshot.php','theme/theme_package.php'] as $f) require_once $ROOT.$f;
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\ThemeSceneCss;
use Reamur\System\Library\Theme\ThemeSnapshot;
use Reamur\System\Library\Theme\ConditionEngine;
use Reamur\System\Library\Theme\TokenRegistry;

$cfg=json_decode(file_get_contents('/home/user/bn_out/bn_config.json'),true);
$P=0;$F=0;
function ok($n,$c,$x=''){global $P,$F;if($c)$P++;else$F++;echo ($c?'OK   ':'FAIL ').$n.($x!==''?' · '.$x:'')."\n";}
$engine=new ConditionEngine();$tokens=new TokenRegistry();
$pkg=new ThemePackage($engine,$tokens);
$config=$cfg['config'];
$errors=$pkg->validateConfig($config);
ok('validateConfig = []',$errors===[],json_encode($errors));
if($errors!==[]){echo "ABORT\n";exit(1);}
ok('tokens dark AA',(new TokenRegistry())->validate($config['tokens'])===[]);
$compiled=(new ThemeSceneCss())->compile($config['kaz_scene']);
ok('ThemeSceneCss 20 camadas',$compiled['enabled']===true&&count($compiled['layers'])===20,
   count($compiled['layers']).' camadas · css '.strlen($compiled['css']).' bytes');
ok('CSS allowlistado',preg_match('/[^A-Za-z0-9#:.;\-]/',$compiled['css'])!==1);

$snap=new ThemeSnapshot($engine,$tokens,$pkg);
foreach(['common/home'=>'hero-particles','catalog/project_list'=>'portfolio-3d',
         'tool/tool_list'=>'tools-3d','error/not_found'=>'not-found'] as $route=>$want){
 $ctx=['route'=>$route,'language'=>'pt-br','device'=>'desktop','mobile'=>false,
  'logged_in'=>false,'user_group'=>'','hour'=>12,'weekday'=>3,'date'=>'2026-11-07',
  'month'=>11,'variant'=>'','https'=>true,'ajax'=>false,'query'=>[],'site'=>'primary','path'=>'/'];
 $s=$snap->resolve($config,$ctx,[],$cfg['slug']);
 ok('snapshot '.$route.' → '.$want,($s['scene']??'')===$want,(string)($s['scene']??'?'));
}
$out=$pkg->export($cfg['name'],$cfg['slug'],$config,['source'=>'draft']);
$json=$pkg->encode($out);
ok('encode <= 512 KiB',strlen($json)<=ThemePackage::MAX_BYTES,strlen($json).' bytes');
$d=$pkg->decode($json);
ok('decode ok=true',$d['ok']===true,json_encode($d['errors']));
ok('decode 25 fontes',count($d['config']['templates'])===25);
ok('decode three.enabled',($d['config']['three']['enabled']??false)===true);
ok('decode kaz_scene 20 camadas',count($d['config']['kaz_scene']['layers'])===20);
ok('checksum canonico',$out['checksum']===$pkg->checksum($config),$out['checksum']);
if(!is_dir('/home/user/bn_out'))mkdir('/home/user/bn_out',0777,true);
file_put_contents('/home/user/bn_out/'.$cfg['slug'].'.reamurtheme.json',$json);
file_put_contents('/home/user/bn_out/export-report.json',json_encode([
 'name'=>$cfg['name'],'slug'=>$cfg['slug'],'checksum'=>$out['checksum'],
 'bytes'=>strlen($json),'templates'=>count($d['config']['templates']),
 'scene_layers'=>count($d['config']['kaz_scene']['layers']),
 'conditions'=>count($d['config']['conditions']['rules']),'errors'=>$errors],
 JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "\nPHP BN export: $P OK / $F FALHAS\n";
exit($F?1:0);
