#!/usr/bin/env python3
"""Frente BO — monta o pacote final, gera evidencias, verifica e empacota.
Nao escreve no GitHub, nao aplica no servidor, nao dispara CI/deploy.
"""
import os,shutil,subprocess,hashlib,json,zipfile
SRC='/home/user'
DST='/home/user/bo_pkg_final'
ZIP='/home/user/entregas/ReamurCMS-Frente-BO-r3-Instalacao-e-Diagnostico.zip'
shutil.rmtree(DST,ignore_errors=True)
os.makedirs(DST,exist_ok=True)
os.makedirs(os.path.dirname(ZIP),exist_ok=True)

def cp(src,dst):
    os.makedirs(os.path.join(DST,os.path.dirname(dst)),exist_ok=True)
    shutil.copyfile(os.path.join(SRC,src),os.path.join(DST,dst))
def put(dst,text):
    os.makedirs(os.path.join(DST,os.path.dirname(dst)),exist_ok=True)
    open(os.path.join(DST,dst),'w',encoding='utf-8').write(text)

# ── docs ──────────────────────────────────────────────────────────────
for f in ['RELATORIO-BO.md','GUIA-BO.md','INDICE-BO.md','DIAGNOSTICO-3D.md']:
    cp('bo_docs/'+f,'docs/'+f)
    cp('bo_docs/'+f,f)
cp('bo_ref/DIAGNOSTICO-BO.md','docs/DIAGNOSTICO-BO.md')
cp('bo_ref/ESPECIFICACAO-CENA.md','docs/ESPECIFICACAO-CENA.md')
cp('bo_ref/especificacao.json','docs/especificacao.json')
cp('bo_docs/console.js','diagnostico/console.js')
cp('bo_docs/console.js','diagnostico-console.js')
cp('bo_docs/verificar-bo.html','diagnostico/verificar-bo.html')
cp('bo_docs/verificar-bo.html','verificar-bo.html')
cp('bo_docs/instalar-bo.py','instalar-bo.py')
cp('bo_docs/instalar-bo.py','diagnostico/instalar-bo.py')
put('LEIA-ME.md',open('/home/user/bo_docs/INDICE-BO.md',encoding='utf-8').read())

# ── tema ──────────────────────────────────────────────────────────────
cp('bo_out/david_universe_3d_bo.reamurtheme.json','tema/david_universe_3d_bo.reamurtheme.json')
cp('bo_out/bo_config.json','tema/bo_config.json')

# ── patch ─────────────────────────────────────────────────────────────
cp('bo_pkg/PATCH-BO.diff','patch/PATCH-BO.diff')
PATCHED=[
 'dev/system/library/three/runtime/three/main.js',
 'dev/system/library/three/asset_manifest.php',
 'dev/system/library/theme/theme_package.php',
 'dev/system/library/theme/condition_engine.php',
 'dev/system/library/theme/theme_snapshot.php',
 'dev/system/library/three/scene_preset_registry.php',
 'dev/catalog/controller/startup/theme.php',
 'dev/catalog/controller/common/home.php',
 'dev/admin/controller/design/theme.php',
 'dev/system/library/three/runtime/three/scenes/david-universe.js',
]
UNTOUCHED=[
 'dev/catalog/view/template/partial/theme-scene.twig',
 'dev/system/library/three/runtime/three/core/scene-runner.js',
 'CHECKSUMS.txt',
 'tools/verify-checksums.sh',
]
for p in PATCHED+UNTOUCHED:
    cp('bo_pkg/'+p,'patch/arquivos/'+p)

# ── previa ────────────────────────────────────────────────────────────
for root,dirs,files in os.walk('/home/user/bo_preview'):
    for f in files:
        rel=os.path.relpath(os.path.join(root,f),'/home/user/bo_preview')
        cp('bo_preview/'+rel,'previa/'+rel)

# ── evidencias ────────────────────────────────────────────────────────
cp('bo_export.log','evidencia/export-bo.txt')
cp('bo_runtime.log','evidencia/runtime-bo.log')
cp('bo_token.log','evidencia/token-parse.log')
cp('bo_ref/prova.log','evidencia/prova-bloqueio.log')
cp('bo_integracao.log','evidencia/integracao-bo.log')
cp('bo_out/shot-bo-diagnostico.png','evidencia/shot-bo-diagnostico.png')
open('/home/user/bo_out/instalar-bo.log','w',encoding='utf-8').write(
    open('/tmp/bo_install.log',encoding='utf-8').read() if os.path.exists('/tmp/bo_install.log')
    else 'BO instalador: executar python3 instalar-bo.py --repo <repo> --apply\n')
cp('bo_out/instalar-bo.log','evidencia/instalar-bo.log')
cp('bo_out/runtime-bo.json','evidencia/runtime-bo.json')
cp('bo_out/export-report.json','evidencia/export-report.json')
cp('BO-Nave-e-Interacoes.png','evidencia/BO-Nave-e-Interacoes.png')
for f in ['shot-bo-hero.png','shot-bo-voo.png','shot-bo-coleta.png','shot-bo-fim.png','shot-bo-mobile.png']:
    cp('bo_out/'+f,'evidencia/'+f)

# node --check
r=subprocess.run(['node','--check','/tmp/bo_check/du.mjs'],capture_output=True,text=True)
r2=subprocess.run(['node','--check','/tmp/bo_check/main.mjs'],capture_output=True,text=True)
put('evidencia/node-check.log',
    'node --check david-universe.mjs: '+('OK' if r.returncode==0 else r.stderr)+'\n'
   +'node --check main.mjs: '+('OK' if r2.returncode==0 else r2.stderr)+'\n')

# git apply --check em replica limpa
rep='/tmp/bo_apply_final'
shutil.rmtree(rep,ignore_errors=True);os.makedirs(rep)
for root,dirs,files in os.walk('/home/user/bo_src'):
    for f in files:
        p=os.path.join(root,f);rel=os.path.relpath(p,'/home/user/bo_src')
        d=os.path.join(rep,rel);os.makedirs(os.path.dirname(d),exist_ok=True)
        shutil.copyfile(p,d)
def git(*a,cwd=rep):return subprocess.run(['git']+list(a),cwd=cwd,capture_output=True,text=True)
git('init','-q','.');git('add','-A')
git('-c','user.email=bo@local','-c','user.name=bo','commit','-qm','base')
c=git('apply','--check','/home/user/bo_pkg/PATCH-BO.diff')
a=git('apply','/home/user/bo_pkg/PATCH-BO.diff')
put('evidencia/git-apply.log',
    'git apply --check: '+('OK' if c.returncode==0 else 'FALHOU '+c.stderr)+'\n'
   +'git apply: '+('OK' if a.returncode==0 else 'FALHOU '+a.stderr)+'\n'
   +'git status --short:\n'+git('status','--short').stdout)

# hashes dos arquivos deliberadamente inalterados
for p,h in [
 ('dev/catalog/view/template/partial/theme-scene.twig','original-theme-scene.sha256'),
 ('dev/system/library/three/runtime/three/core/scene-runner.js','original-scene-runner.sha256'),
 ('CHECKSUMS.txt','original-CHECKSUMS.sha256')]:
    data=open(os.path.join(DST,'patch/arquivos/'+p),'rb').read()
    put('evidencia/'+h,hashlib.sha256(data).hexdigest()+'\n')

# ── tools ─────────────────────────────────────────────────────────────
for f in ['bo_spec.py','bo_patch.py','bo_theme_gen.py','bo_preview.py','bo_export.php',
          'bo_runtime.cjs','bo_montagem.py','verify-bo.cjs','bo_final.py','bn_theme_gen.py',
          'bo_integracao.php']:
    cp('validacao/'+f,'tools/'+f)

# ── verificador ───────────────────────────────────────────────────────
v=subprocess.run(['node','tools/verify-bo.cjs','.'],cwd=DST,capture_output=True,text=True)
put('evidencia/verify-bo.log',v.stdout+v.stderr)
print(v.stdout[-2500:])
print('verify rc =',v.returncode)
assert v.returncode==0,'verify-bo falhou'

# ── ZIP ───────────────────────────────────────────────────────────────
for old in [ZIP,ZIP+'.sha256']:
    if os.path.exists(old):os.remove(old)
entries=[]
with zipfile.ZipFile(ZIP,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
    for root,dirs,files in os.walk(DST):
        for f in sorted(files):
            p=os.path.join(root,f);rel=os.path.relpath(p,DST)
            z.write(p,rel);entries.append(rel)
h=hashlib.sha256(open(ZIP,'rb').read()).hexdigest()
open(ZIP+'.sha256','w').write(h+'  '+os.path.basename(ZIP)+'\n')
print('\nZIP:',ZIP)
print('bytes:',os.path.getsize(ZIP),'| entradas:',len(entries),'| SHA:',h)
print('entradas raiz:',sorted(set(e.split('/')[0] for e in entries)))
json.dump({'zip':os.path.basename(ZIP),'bytes':os.path.getsize(ZIP),
           'entries':len(entries),'sha256':h,'verify_rc':v.returncode},
          open('/home/user/bo_final.json','w'),indent=1)
