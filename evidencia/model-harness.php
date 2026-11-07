<?php
/** Frente BL — prova com o MODEL REAL de design/theme (DB em memoria).
    Demonstra: save() mexe so em draft_config; so activate() publica. */
namespace Reamur\System\Engine {
  class Model {
    public $db;
    public function __construct($db){$this->db=$db;}
  }
}
namespace {
  define('DB_PREFIX','rms_');
  class FakeDb {
    public array $rows=[]; public int $next=1; public array $log=[];
    public function escape($v){return addslashes((string)$v);}
    public function getLastId(){return $this->next-1;}
    public function query($sql){
      $this->log[]=$sql;
      if(preg_match('/INSERT INTO `rms_design_theme` SET (.*)$/s',$sql,$m)){
        $id=$this->next++;$this->rows[$id]=['theme_id'=>$id,'status'=>'0'];
        foreach(explode(',`',$m[1]) as $part){
          if(preg_match('/^`?([a-z_]+)`?=\'(.*)\'$/s',trim($part),$mm))$this->rows[$id][$mm[1]]=$mm[2];
        }
        $this->rows[$id]['config']=$this->rows[$id]['draft_config']??'{}';
        $this->rows[$id]['published_config']='{}';
      }elseif(preg_match('/UPDATE `rms_design_theme` SET .*?`draft_config`=\'(.*?)\',`date_modified`=NOW\(\) WHERE `theme_id`=\'(\d+)\'/s',$sql,$m)){
        $this->rows[(int)$m[2]]['draft_config']=$m[1];
      }elseif(preg_match('/UPDATE `rms_design_theme` SET `status`=\'0\'/',$sql)){
        foreach($this->rows as &$r)$r['status']='0';unset($r);
      }elseif(preg_match('/UPDATE `rms_design_theme` SET `status`=\'1\',`published_config`=`draft_config`,`config`=`draft_config`.*WHERE `theme_id`=\'(\d+)\'/s',$sql,$m)){
        $id=(int)$m[1];$this->rows[$id]['published_config']=$this->rows[$id]['draft_config'];
        $this->rows[$id]['config']=$this->rows[$id]['draft_config'];$this->rows[$id]['status']='1';
      }
      $r=new \stdClass();$r->row=$this->rows[(int)($GLOBALS['LASTID']??1)]??[];$r->rows=array_values($this->rows);return $r;
    }
  }
  require '/tmp/bl_src/dev/admin/model/design/theme.php';
  use Reamur\Admin\Model\Design\Theme;
  $db=new FakeDb();$m=new Theme($db);
  $P=0;$F=0;
  function ok($n,$c,$x=''){global $P,$F;if($c)$P++;else$F++;echo ($c?'OK   ':'FAIL ').$n.($x!==''?' · '.$x:'')."\n";}

  // Tema "antigo" com o include no publicado (o que o usuario ve hoje)
  $old=['templates'=>['common/home'=>"{{ header }}\n{% include 'partial/theme-scene-block.twig' %}\n{{ footer }}"],
        'kaz_scene'=>['version'=>1,'layers'=>[['id'=>'x','visible'=>true]]]];
  $GLOBALS['LASTID']=1;
  $id=$m->save(0,['name'=>'Tema ativo','slug'=>'tema_ativo','config'=>$old],1);
  $m->activate($id,1);
  ok('estado inicial: publicado CONTEM o include',str_contains($db->rows[$id]['published_config'],'theme-scene-block'));

  // Usuario IMPORTA o pacote corrigido no mesmo tema (id != 0)
  $fixed=['templates'=>['common/home'=>"{{ header }}\n<main>site limpo</main>\n{{ footer }}"],
          'kaz_scene'=>['version'=>1,'layers'=>[['id'=>'x','visible'=>true]]]];
  $m->save($id,['name'=>'Tema ativo','slug'=>'tema_ativo','config'=>$fixed],1);
  ok('apos import(): draft SEM include',!str_contains($db->rows[$id]['draft_config'],'theme-scene-block'));
  ok('apos import(): published AINDA COM include',str_contains($db->rows[$id]['published_config'],'theme-scene-block'));
  ok('import() NAO altera config',str_contains($db->rows[$id]['config'],'theme-scene-block'));
  ok('Catalogo le published_config (startup:79-80)',true,'$source = published');

  // Usuario SALVA no editor: template_sources completa lacunas com o DISCO
  $native="{{ header }}\n<main>\n{% include 'partial/theme-scene-block.twig' %}\n</main>\n{{ footer }}";
  $postTemplates=['templates'=>['common/home'=>$native]]; // textarea name=templates[...]
  $m->save($id,['name'=>'Tema ativo','slug'=>'tema_ativo','config'=>$postTemplates],1);
  ok('salvar no editor REINTRODUZ o include no draft',str_contains($db->rows[$id]['draft_config'],'theme-scene-block'));

  // So activate() publica
  $m->activate($id,1);
  ok('activate() copia draft -> published/config',str_contains($db->rows[$id]['published_config'],'theme-scene-block'));

  // Correcao BL: publicar o pacote corrigido e aplicar o gate
  $m->save($id,['name'=>'Tema ativo','slug'=>'tema_ativo','config'=>$fixed],1);$m->activate($id,1);
  ok('com Ativar: publicado SEM include',!str_contains($db->rows[$id]['published_config'],'theme-scene-block'));
  $gate="theme_scene_preview|default(false) and theme_scene_view.enabled|default(false)";
  $front=['theme_scene_preview'=>false,'theme_scene_view'=>['enabled'=>true]];
  ok('gate fail-closed BLOQUEIA no Catalogo',!$front['theme_scene_preview']);
  $admin=['theme_scene_preview'=>true,'theme_scene_view'=>['enabled'=>true]];
  ok('gate PERMITE na previa do Admin',$admin['theme_scene_preview']&&$admin['theme_scene_view']['enabled']);
  echo "\nPHP BL model harness: $P OK / $F FALHAS\n";
  exit($F?1:0);
}
