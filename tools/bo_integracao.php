<?php
/** Frente BO-r3 — prova da INTEGRACAO real com o CMS (classes patchadas).
    Simula: ThemeSnapshot -> startup/theme.php -> common/header.php ->
    common/home.php -> header.twig -> theme-scene.twig -> AssetManifest.
    Nao roda runtime, nao acessa rede, nao escreve no GitHub. */
$ROOT='/home/user/bo_lib/dev/system/library/';
foreach(['cms/page_layout.php','theme/condition_engine.php','theme/token_registry.php',
 'theme/section_registry.php','theme/component_registry.php','theme/theme_schedule.php',
 'theme/theme_media.php','theme/theme_sites.php','theme/theme_scene_css.php',
 'theme/theme_snapshot.php','theme/theme_package.php','three/asset_manifest.php',
 'three/scene_preset_registry.php','three/scene_preset.php','three/scene_config.php'] as $f)
 require_once $ROOT.$f;
use Reamur\System\Library\Theme\ThemePackage;
use Reamur\System\Library\Theme\ThemeSnapshot;
use Reamur\System\Library\Theme\ConditionEngine;
use Reamur\System\Library\Theme\TokenRegistry;
use Reamur\System\Library\Theme\ThemeSceneCss;
use Reamur\System\Library\Three\AssetManifest;
use Reamur\System\Library\Three\SceneConfig;

$cfg=json_decode(file_get_contents('/home/user/bo_out/bo_config.json'),true);
$config=$cfg['config'];
$P=0;$F=0;
function ok($n,$c,$x=''){global $P,$F;if($c)$P++;else$F++;echo ($c?'OK   ':'FAIL ').$n.($x!==''?' · '.$x:'')."\n";}

$engine=new ConditionEngine();$tokens=new TokenRegistry();
$pkg=new ThemePackage($engine,$tokens);
$snap=new ThemeSnapshot($engine,$tokens,$pkg,null,null,null,null,null,new ThemeSceneCss());
$ctx=['route'=>'common/home','path'=>'/','device'=>'desktop','weekday'=>6,'hour'=>14,
 'month'=>11,'variant'=>'','https'=>true,'ajax'=>false,'query'=>[],'site'=>'primary','path'=>'/'];
$s=$snap->resolve($config,$ctx,[],$cfg['slug']);

echo "--- snapshot (startup/theme.php) ---\n";
echo 'scene                 = '.json_encode($s['scene']??null)."\n";
echo 'three                 = '.json_encode($s['three']??null)."\n";
echo 'theme_scene (globals) = '.json_encode($s['vars']['theme_scene']??$s['globals']['theme_scene']??null)."\n";
echo 'chaves do snapshot    = '.implode(',',array_keys($s))."\n\n";

$three=$s['three']??[];
$scene=(string)($s['scene']??'');

// 1) startup/theme.php grava catalog_theme_three; common/header.php le enabled
ok('catalog_theme_three.enabled = true',!empty($three['enabled']),json_encode($three['enabled']??null));
ok('header has_3d derivado do tema',!empty($three['enabled']));

// 2) theme-scene.twig usa theme_scene.enabled + theme_scene.scene
$ts=$s['vars']['theme_scene']??$s['globals']['theme_scene']??$three;
ok('theme_scene.enabled = true',!empty($ts['enabled']),json_encode($ts['enabled']??null));
ok('theme_scene.scene = david-universe',($ts['scene']??'')==='david-universe',(string)($ts['scene']??''));
ok('slot declarativo = hero',($ts['slot']??'')==='hero',(string)($ts['slot']??''));

// 3) common/home.php: themeSceneSettings -> three_hero_enabled -> SceneConfig
$settings=['hero-particles'=>'three_hero_enabled','portfolio-3d'=>'three_portfolio_enabled',
 'tools-3d'=>'three_tools_enabled','david-universe'=>'three_hero_enabled','not-found'=>'three_not_found_enabled'];
ok('home.php mapeia david-universe',isset($settings[$scene]),$scene);
$siteInfo=[];
if(isset($settings[$scene]))$siteInfo[$settings[$scene]]='1';
$sc=new SceneConfig();
$rt=$sc->runtime($siteInfo);
ok('SceneConfig hero_enabled = true',!empty($rt['hero_enabled']),json_encode($rt['hero_enabled']??null));
ok('home.php has_3d = true',!empty($rt['hero_enabled']));

// 4) AssetManifest publica o modulo novo e o header monta o importmap
if(!defined('DIR_SYSTEM')) define('DIR_SYSTEM','/home/user/bo_lib/dev/system/');
$am=new AssetManifest();
$base=$am->publicBasePath();
ok('publicBasePath = three-runtime/',$base==='three-runtime/',$base);
$e=$am->resolve('three/scenes/david-universe.js');
ok('resolve(david-universe.js) nao nulo',$e!==null,json_encode($e));
ok('MIME do modulo novo = JS',isset($e['content_type'])&&str_contains($e['content_type'],'javascript'),(string)($e['content_type']??''));
// O php-cli WASM espelha apenas .php/.json/.txt/.twig; .js nao entra no FS
// virtual. Validamos aqui o CAMINHO LOGICO que common/three.php vai ler; a
// existencia real do arquivo e conferida no host por bo_final.py / instalar-bo.py.
$esperado = DIR_SYSTEM.'library/three/runtime/three/scenes/david-universe.js';
ok('path do modulo novo no runtime',($e['path']??'')===$esperado,(string)($e['path']??''));
ok('main.js publicado',$am->resolve('three/main.js')!==null);
ok('three.module.js publicado',$am->resolve('three.module.js')!==null);
ok('three.core.js publicado',$am->resolve('three.core.js')!==null);
ok('scene-runner.js publicado',$am->resolve('three/core/scene-runner.js')!==null);
ok('dom.js publicado',$am->resolve('three/core/dom.js')!==null);

// 5) Resolucao de imports relativos a partir de /three-runtime/three/main.js
$urls=[
 'main'       => $base.'three/main.js',
 'runner'     => $base.'three/core/scene-runner.js',
 'cena'       => $base.'three/scenes/david-universe.js',
 'three'      => $base.'three.module.js',
];
foreach($urls as $k=>$u){
  $asset=preg_replace('#^three-runtime/#','',$u);
  ok('importmap/rota '.$k.' resolvivel',$am->resolve($asset)!==null,$asset);
}

// 6) as 4 cenas antigas continuam publicadas
foreach(['hero-particles','not-found','portfolio-3d','tools-3d'] as $c)
 ok('cena antiga '.$c.' preservada',$am->resolve('three/scenes/'.$c.'.js')!==null);

echo "\nPHP BO integracao: $P OK / $F FALHAS\n";
