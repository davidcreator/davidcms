<?php
/** Frente BO — export/decode/allowlists REAIS com as classes PATCHADAS. */
$ROOT='/home/user/bo_lib/dev/system/library/';
foreach(['cms/page_layout.php','theme/condition_engine.php','theme/token_registry.php',
 'theme/section_registry.php','theme/component_registry.php','theme/theme_schedule.php',
 'theme/theme_media.php','theme/theme_sites.php','theme/theme_scene_css.php',
 'theme/theme_snapshot.php','theme/theme_package.php','three/asset_manifest.php',
 'three/scene_preset_registry.php','three/scene_preset.php','three/scene_config.php'] as $f)
 require_once $ROOT.$f;
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\ThemeSceneCss;
use Reamur\System\Library\Theme\ThemeSnapshot;
use Reamur\System\Library\Theme\ConditionEngine;
use Reamur\System\Library\Theme\TokenRegistry;
use Reamur\System\Library\Three\AssetManifest;
use Reamur\System\Library\Three\ScenePresetRegistry;

$cfg=json_decode(file_get_contents('/home/user/bo_out/bo_config.json'),true);
$P=0;$F=0;
function ok($n,$c,$x=''){global $P,$F;if($c)$P++;else$F++;echo ($c?'OK   ':'FAIL ').$n.($x!==''?' · '.$x:'')."\n";}
$engine=new ConditionEngine();$tokens=new TokenRegistry();
$pkg=new ThemePackage($engine,$tokens);
$config=$cfg['config'];

// ── allowlists patchadas ──────────────────────────────────────────────
ok('ConditionEngine aceita david-universe',in_array('david-universe',$engine->scenes(),true),json_encode($engine->scenes()));
$rp=new ReflectionClass('Reamur\System\Library\Theme\ThemePackage');
$rc=$rp->getReflectionConstant('SCENES');
ok('ThemePackage aceita david-universe',in_array('david-universe',$rc->getValue(),true),json_encode($rc->getValue()));
$presets=new ScenePresetRegistry();
ok('ScenePresetRegistry aceita david-universe',in_array('david-universe',$presets->scenes(),true),json_encode($presets->scenes()));
$u=$presets->find('universe');
ok('preset universe existe',is_array($u)&&$u['scene']==='david-universe',json_encode($u));
$svg=$presets->svg(['scene'=>'david-universe','slot'=>'hero','count'=>520,'color'=>'#5cc6d0','speed'=>0.06,'enabled'=>true]);
ok('miniatura desenha a nave',strpos($svg,'polygon')!==false&&strpos($svg,'david-universe')!==false);
ok('miniatura sem codigo',strpos($svg,'script')===false&&strpos($svg,'url(')===false);
if(!defined('DIR_SYSTEM')) define('DIR_SYSTEM','/home/user/bo_lib/dev/system/');
$manifest=new AssetManifest();
$entry=$manifest->resolve('three/scenes/david-universe.js');
ok('asset_manifest publica o modulo',is_array($entry)&&strpos($entry['path'],'david-universe.js')!==false,json_encode($entry));
ok('asset_manifest MIME js',is_array($entry)&&$entry['content_type']==='application/javascript; charset=utf-8');
ok('asset_manifest continua negando desconhecido',$manifest->resolve('three/scenes/evil.js')===null);
ok('slots inalterados',$engine->slots()===['hero','home_top','footer_top'],json_encode($engine->slots()));
ok('4 cenas originais preservadas',count(array_intersect(['hero-particles','portfolio-3d','tools-3d','not-found'],$engine->scenes()))===4);

// ── tema ──────────────────────────────────────────────────────────────
$errors=$pkg->validateConfig($config);
ok('validateConfig = []',$errors===[],json_encode($errors));
if($errors!==[]){echo "ABORT\n";exit(1);}
ok('tokens dark AA',(new TokenRegistry())->validate($config['tokens'])===[]);
$compiled=(new ThemeSceneCss())->compile($config['kaz_scene']);
ok('ThemeSceneCss 20 camadas',$compiled['enabled']===true&&count($compiled['layers'])===20,
   count($compiled['layers']).' camadas · css '.strlen($compiled['css']).' bytes');
ok('CSS allowlistado',preg_match('/[^A-Za-z0-9#:.;\-]/',$compiled['css'])!==1);

$snap=new ThemeSnapshot($engine,$tokens,$pkg);
foreach(['common/home'=>'david-universe','catalog/project_list'=>'portfolio-3d',
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
ok('decode three.scene=david-universe',($d['config']['three']['scene']??'')==='david-universe');
ok('decode kaz_scene 20 camadas',count($d['config']['kaz_scene']['layers'])===20);
ok('decode HUD data-du-orbes',strpos($d['config']['templates']['partial/site_header'],'data-du-orbes')!==false);
ok('checksum canonico',$out['checksum']===$pkg->checksum($config),$out['checksum']);
file_put_contents('/home/user/bo_out/'.$cfg['slug'].'.reamurtheme.json',$json);
file_put_contents('/home/user/bo_out/export-report.json',json_encode([
 'name'=>$cfg['name'],'slug'=>$cfg['slug'],'checksum'=>$out['checksum'],
 'bytes'=>strlen($json),'templates'=>count($d['config']['templates']),
 'scene_layers'=>count($d['config']['kaz_scene']['layers']),
 'conditions'=>count($d['config']['conditions']['rules']),'errors'=>$errors,
 'scenes'=>$engine->scenes(),'asset'=>$entry],
 JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo "\nPHP BO export: $P OK / $F FALHAS\n";
exit($F?1:0);
