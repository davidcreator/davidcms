#!/usr/bin/env python3
"""Frente BO — gera os patches do runtime sobre o HEAD ad7643de6ca2.
Le arquivos originais de bo_src/ e escreve versoes patchadas em bo_pkg/.
Nao aplica nada no servidor, nao escreve no GitHub.
"""
import os, re, shutil, subprocess
SRC='/home/user/bo_src'
DST='/home/user/bo_pkg'
NEW='david-universe'

FILES=[
 'dev/system/library/three/runtime/three/main.js',
 'dev/system/library/three/asset_manifest.php',
 'dev/system/library/theme/theme_package.php',
 'dev/system/library/theme/condition_engine.php',
 'dev/system/library/theme/theme_snapshot.php',
 'dev/system/library/three/scene_preset_registry.php',
 'dev/catalog/controller/startup/theme.php',
 'dev/catalog/controller/common/home.php',
 'dev/admin/controller/design/theme.php',
 # sem alteracao (documentado no guia):
 'dev/catalog/view/template/partial/theme-scene.twig',
 'dev/system/library/three/runtime/three/core/scene-runner.js',
 'CHECKSUMS.txt',
 'tools/verify-checksums.sh',
]
UNCHANGED=set(FILES[9:])

def rw(rel, fn):
    src=os.path.join(SRC,rel); dst=os.path.join(DST,rel)
    os.makedirs(os.path.dirname(dst),exist_ok=True)
    s=open(src,encoding='utf-8').read()
    t=fn(s)
    open(dst,'w',encoding='utf-8').write(t)
    return s,t

def sub1(s,old,new,label):
    assert s.count(old)==1, f'{label}: esperado 1, achou {s.count(old)}'
    return s.replace(old,new,1)

# 1) main.js — registra a cena nova
def p_main(s):
    old="import './scenes/tools-3d.js';       // Cena 5: ícones flutuantes das ferramentas\n"
    new=old+"import './scenes/david-universe.js'; // BO: nave, orbes e voo por scroll (g3 portada)\n"
    return sub1(s,old,new,'main.js')

# 2) asset_manifest.php — publica o modulo pela rota allowlistada
def p_manifest(s):
    old="        'three/scenes/tools-3d.js' => 'runtime/three/scenes/tools-3d.js',\n"
    new=old+"        'three/scenes/david-universe.js' => 'runtime/three/scenes/david-universe.js',\n"
    return sub1(s,old,new,'asset_manifest.php')

# 3) theme_package.php — allowlist de cenas do pacote
def p_package(s):
    old="private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found'];"
    new="private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found', 'david-universe'];"
    return sub1(s,old,new,'theme_package.php')

# 4) condition_engine.php — allowlist de cenas das condicoes
def p_engine(s):
    old="private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found'];"
    new="private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found', 'david-universe'];"
    return sub1(s,old,new,'condition_engine.php')

# 5) scene_preset_registry.php — allowlist + preset + miniatura SVG estatica
def p_preset(s):
    s=sub1(s,
      "private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found'];",
      "private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found', 'david-universe'];",
      'preset SCENES')
    old="        ['id'=>'off',         'scene'=>'',              'slot'=>'hero',       'count'=>400, 'color'=>'#8a4fff', 'speed'=>0.05, 'enabled'=>false, 'label'=>'scene_preset_off',         'help'=>'scene_preset_off_help'],\n"
    new=("        ['id'=>'universe',    'scene'=>'david-universe','slot'=>'hero',       'count'=>520, 'color'=>'#5cc6d0', 'speed'=>0.06, 'enabled'=>true,  'label'=>'scene_preset_universe',    'help'=>'scene_preset_universe_help'],\n"
         +old)
    s=sub1(s,old,new,'preset universe')
    old="""        } elseif ($c['scene']==='not-found') {
"""
    new="""        } elseif ($c['scene']==='david-universe') {
            // Miniatura estatica: estrelas, nave e orbes. Sem script, sem url(.
            for ($i=0;$i<34;$i++) {
                $x=12+(($i*71)%296); $y=14+(($i*43)%136);
                $r=sprintf('%.1f',0.7+(($i*5)%3)*0.5);
                $o.='<circle cx="'.$x.'" cy="'.$y.'" r="'.$r.'" fill="#ffffff" opacity="'.sprintf('%.2f',0.25+(($i*3)%5)*0.11).'"/>';
            }
            $o.='<circle cx="252" cy="44" r="22" fill="'.$color.'" opacity="0.20"/>'
               .'<circle cx="252" cy="44" r="14" fill="none" stroke="'.$color.'" stroke-width="1" opacity="0.55"/>'
               .'<polygon points="160,58 172,104 160,96 148,104" fill="'.$color.'" opacity="0.92"/>'
               .'<polygon points="160,70 168,102 160,96 152,102" fill="#121516"/>'
               .'<line x1="148" y1="98" x2="132" y2="112" stroke="'.$color.'" stroke-width="3" opacity="0.75"/>'
               .'<line x1="172" y1="98" x2="188" y2="112" stroke="'.$color.'" stroke-width="3" opacity="0.75"/>'
               .'<ellipse cx="160" cy="110" rx="7" ry="16" fill="'.$color.'" opacity="0.35"/>';
            for ($i=0;$i<4;$i++) {
                $x=42+$i*74; $y=140+(($i%2===0)?-8:8);
                $o.='<circle cx="'.$x.'" cy="'.$y.'" r="5" fill="none" stroke="'.$color.'" stroke-width="2" opacity="'.sprintf('%.2f',0.45+$i*0.12).'"/>';
            }
        } elseif ($c['scene']==='not-found') {
"""
    return sub1(s,old,new,'preset svg')

# 6) theme_snapshot.php — zera scene se a allowlist nao conhecer a cena
def p_snapshot(s):
    old="private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found'];"
    new="private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found', 'david-universe'];"
    return sub1(s,old,new,'theme_snapshot.php')

# 7) startup/theme.php — allowlist espelhada (constante declarada, hoje sem uso)
def p_startup(s):
    old="private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found'];"
    new="private const SCENES = ['', 'hero-particles', 'portfolio-3d', 'tools-3d', 'not-found', 'david-universe'];"
    return sub1(s,old,new,'startup/theme.php')

# 8) common/home.php — has_3d continua ligado para a cena nova
def p_home(s):
    old="            'tools-3d' => 'three_tools_enabled',\n"
    new=old+"            'david-universe' => 'three_hero_enabled',\n"
    return sub1(s,old,new,'common/home.php')

# 9) admin/controller/design/theme.php — dropdown + normalizacao do POST
def p_admin(s):
    old="['id'=>'not-found','name'=>'Not found']];"
    new="['id'=>'not-found','name'=>'Not found'],['id'=>'david-universe','name'=>'David Universe (nave + orbes)']];"
    s=sub1(s,old,new,'admin dropdown')
    old="['','hero-particles','portfolio-3d','tools-3d','not-found'],true)"
    new="['','hero-particles','portfolio-3d','tools-3d','not-found','david-universe'],true)"
    return sub1(s,old,new,'admin normalize')

PATCHERS={
 'dev/system/library/three/runtime/three/main.js':p_main,
 'dev/system/library/three/asset_manifest.php':p_manifest,
 'dev/system/library/theme/theme_package.php':p_package,
 'dev/system/library/theme/condition_engine.php':p_engine,
 'dev/system/library/theme/theme_snapshot.php':p_snapshot,
 'dev/system/library/three/scene_preset_registry.php':p_preset,
 'dev/catalog/controller/startup/theme.php':p_startup,
 'dev/catalog/controller/common/home.php':p_home,
 'dev/admin/controller/design/theme.php':p_admin,
}

print('== patches ==')
for rel,fn in PATCHERS.items():
    a,b=rw(rel,fn)
    print(f'  {rel}: {len(a)} -> {len(b)} bytes (+{len(b)-len(a)})')
for rel in UNCHANGED:
    dst=os.path.join(DST,rel)
    os.makedirs(os.path.dirname(dst),exist_ok=True)
    shutil.copyfile(os.path.join(SRC,rel),dst)
    print(f'  {rel}: inalterado (copia para conferencia)')

# diff unificado apenas dos arquivos alterados (+ o novo modulo)
diff=['# PATCH-BO — aplicar com: git apply PATCH-BO.diff',
      '# Base: ad7643de6ca2 (HEAD). Nao toca em dev/ SQL, migration, deploy ou CI.','','']
for rel in list(PATCHERS)+['dev/system/library/three/runtime/three/scenes/david-universe.js']:
    a=os.path.join(SRC,rel); b=os.path.join(DST,rel)
    if not os.path.exists(a):
        # arquivo novo: diff contra /dev/null
        r=subprocess.run(['git','diff','--no-index','--','/dev/null',b],capture_output=True,text=True)
    else:
        r=subprocess.run(['git','diff','--no-index','--',a,b],capture_output=True,text=True)
    d=r.stdout
    d=d.replace('a'+a,'a/'+rel).replace('b'+b,'b/'+rel)
    d=d.replace('--- /dev/null','--- /dev/null').replace('+++ /dev/null','+++ /dev/null')
    diff.append(d.rstrip())
    diff.append('')
open('/home/user/bo_pkg/PATCH-BO.diff','w',encoding='utf-8').write('\n'.join(diff))
print('\nPATCH-BO.diff:',os.path.getsize('/home/user/bo_pkg/PATCH-BO.diff'),'bytes')
