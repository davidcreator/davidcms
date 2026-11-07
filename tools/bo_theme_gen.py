#!/usr/bin/env python3
"""Frente BO — gera o tema David Universe 3D corrigido (cena real).
Parte do config BN ja validado e aplica apenas:
  - allowlist nova: scene/three.scene -> david-universe (home + mobile);
  - HUD com ganchos data-* para a cena atualizar (orbes/fase/distancia/progresso/toast);
  - CSS do backdrop fixo que mantem o voo por scroll sempre visivel;
  - slug/name novos para nao colidir com o pacote BN anterior.
Nenhuma tag de script, |raw, URL externa ou arquivo executavel entra no tema.
"""
import json,os,re,copy

SRC='/home/user/bn_out/bn_config.json'
OUTDIR='/home/user/bo_out'
os.makedirs(OUTDIR,exist_ok=True)
base=json.load(open(SRC,encoding='utf-8'))
cfg=copy.deepcopy(base)
cfg['name']='David Universe 3D — Cena Real'
cfg['slug']='david_universe_3d_bo'
c=cfg['config']

BO_CSS=r'''
/* ── BO: cena real david-universe (backdrop de voo contínuo) ─────────
   O SceneRunner pausa cenas fora da viewport. Mantendo o slot hero fixo
   em tela cheia, o voo por scroll permanece ativo em todas as 9 fases.
   O conteúdo sobe para z-index 1; o canvas fica em 0, sem pointer events. */
html{background:#121516}
body{background:#121516}
body.du{background:#121516}
.du{background:transparent}
.du > :not(.theme-scene):not(.du-hud){position:relative;z-index:1}
.theme-scene.theme-scene--hero[data-scene="david-universe"]{
  position:fixed;inset:0;width:100vw;height:100vh;min-height:0;margin:0;
  border:0;border-radius:0;background:transparent;z-index:0;
  pointer-events:none;isolation:auto}
.theme-scene.theme-scene--hero[data-scene="david-universe"] .theme-scene__fallback{opacity:.16}
.theme-scene.theme-scene--hero[data-scene="david-universe"] canvas{display:block;width:100%;height:100%}
.du-universe-progress{position:fixed;left:0;top:0;height:2px;width:0;
  background:var(--du-cyan);z-index:40;pointer-events:none;transition:width .08s linear}
.du-toast{position:fixed;right:18px;bottom:18px;z-index:40;
  padding:10px 16px;border:1px solid var(--du-line);border-radius:var(--du-radius);
  background:rgba(14,16,17,.94);color:var(--du-cyan);font-size:.70rem;
  letter-spacing:.12em;text-transform:uppercase;opacity:0;transform:translateY(8px);
  transition:opacity .25s,transform .25s;pointer-events:none}
.du-toast.is-on{opacity:1;transform:translateY(0)}
[data-du-orbes],[data-du-distancia],[data-du-fase],[data-du-hint-distancia]{font-variant-numeric:tabular-nums}
@media(max-width:620px){
 .theme-scene.theme-scene--hero[data-scene="david-universe"]{min-height:0}
 .du-toast{right:10px;bottom:10px;font-size:.60rem}
}
@media(prefers-reduced-motion:reduce){.du-universe-progress{transition:none}}
'''

T=c['templates']

# ── HUD: ganchos data-* + barra de progresso + toast ──────────────────
h=T['partial/site_header']
assert h.count('</style>')==1
h=h.replace('</style>',BO_CSS+'\n</style>',1)
assert h.count('ORBES 00/10')==1
h=h.replace('ORBES 00/10','ORBES <span data-du-orbes>00/14</span>',1)
old='<div class="du-status"><span>FASE 00 — DECOLAGEM</span><span>DISTÂNCIA <b>0 KM</b></span></div>\n'
assert h.count(old)==1, 'du-status nao encontrado'
new=('<div class="du-status"><span data-du-fase>FASE 00 — DECOLAGEM</span>'
     '<span>DISTÂNCIA <b data-du-distancia>0 KM</b></span></div>\n'
     '<div class="du-universe-progress" data-du-progresso aria-hidden="true"></div>\n'
     '<div class="du-toast" data-du-toast aria-live="polite"></div>\n')
h=h.replace(old,new,1)
T['partial/site_header']=h

# ── Distância nos demais pontos do HUD ────────────────────────────────
n_hint=0
for k,v in T.items():
    if not isinstance(v,str):continue
    if '<span>DISTÂNCIA 0 KM</span>' in v:
        v=v.replace('<span>DISTÂNCIA 0 KM</span>',
                    '<span>DISTÂNCIA <span data-du-hint-distancia>0 KM</span></span>')
        n_hint+=1
    if 'ROLE PARA VOAR · DISTÂNCIA 0 KM</span>' in v:
        v=v.replace('ROLE PARA VOAR · DISTÂNCIA 0 KM</span>',
                    'ROLE PARA VOAR · DISTÂNCIA <span data-du-hint-distancia>0 KM</span></span>')
        n_hint+=1
    T[k]=v

# ── Allowlist nova: home + mobile usam a cena real ────────────────────
c['scene']='david-universe'
c['three']['scene']='david-universe'
for r in c['conditions']['rules']:
    if r['id']=='du_home_particles':
        r['id']='du_home_universe'
        r['then']['scene']='david-universe'
        r['then']['three']['scene']='david-universe'
    elif r['id']=='du_mobile_lite':
        r['then']['three']['scene']='david-universe'

# ── Sanidade: nenhum codigo executavel entrou no tema ─────────────────
alltext=json.dumps(cfg,ensure_ascii=False)
BAD=['<script','|raw','javascript:','onerror=','onclick=','<iframe','<object','<embed',
     'googleapis','gstatic','cdn.','https://davidalmeida.xyz/catalog/assets']
for b in BAD:
    assert b not in alltext, 'proibido no tema: '+b

OUT=os.path.join(OUTDIR,'bo_config.json')
json.dump(cfg,open(OUT,'w',encoding='utf-8'),ensure_ascii=False,indent=1)
json.dump(cfg,open(os.path.join(OUTDIR,'david_universe_3d_bo.reamurtheme.json'),'w',encoding='utf-8'),
          ensure_ascii=False,indent=1)

# ── HTML de previa independente (usa o CSS + HUD do tema) ─────────────
print('fontes:',len(T),'| camadas kaz_scene:',len(c['kaz_scene']['layers']),
      '| regras:',len(c['conditions']['rules']),'| hints:',n_hint)
print('cenas:',[r['then'].get('scene') or r['then'].get('three',{}).get('scene') for r in c['conditions']['rules']])
print('scene default:',c['scene'],'| three:',c['three']['scene'],'| slug:',cfg['slug'])
print('config bytes:',os.path.getsize(OUT))
print('maior fonte:',max(len(v) for v in T.values()),'bytes')
