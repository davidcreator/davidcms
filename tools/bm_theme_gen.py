#!/usr/bin/env python3
"""Frente BM — Atlas Studio 3D: tema COMPLETO com ThreeJS declarativo.

Parte dos 25 fontes Twig aprovados na BI ("Agora sim ficou perfeito") e
acrescenta APENAS dados validados: slot [data-scene], cenas allowlistadas por
rota via conditions, three e kaz_scene. Zero JS executavel, zero |raw, zero
upload, zero migration. Parent fica vazio para nao depender de registro pai.
"""
import json,sys,os
BASE='/home/user/Atlas-Studio.reamurtheme.json'
OUT='/tmp/bm_config.json'
p=json.load(open(BASE,encoding='utf-8'))
c=dict(p['config'])
T=dict(c['templates'])                     # copia dos 25 fontes aprovados

THREE_CSS = """
/* ── Atlas Studio 3D · slots declarativos de cena (sem JS neste arquivo) ── */
.theme-scene{position:relative;width:100%;border-radius:var(--at-radius-lg,24px);
  overflow:hidden;background:radial-gradient(circle at 20% 20%,rgba(67,56,202,.18),transparent 60%),
  radial-gradient(circle at 80% 70%,rgba(14,165,233,.16),transparent 62%),var(--at-dark,#0d1526);
  border:1px solid rgba(127,127,127,.20);isolation:isolate}
.theme-scene[data-scene]{min-height:220px}
.theme-scene.theme-scene--hero{min-height:340px;margin:0 0 32px}
.theme-scene.theme-scene--home_top{min-height:240px;margin:0 0 28px}
.theme-scene.theme-scene--footer_top{min-height:180px;margin:0 0 18px}
.theme-scene__fallback{position:absolute;inset:0;
  background-image:radial-gradient(circle,rgba(165,180,252,.62) 1.5px,transparent 2px),
  radial-gradient(circle,rgba(34,211,238,.42) 1px,transparent 1.5px),
  radial-gradient(circle,rgba(255,255,255,.20) 2px,transparent 3px);
  background-size:26px 26px,41px 41px,88px 88px;
  background-position:0 0,13px 19px,44px 30px;opacity:.88}
.theme-scene[data-scene="portfolio-3d"] .theme-scene__fallback{
  background-image:linear-gradient(135deg,transparent 46%,rgba(67,56,202,.30) 47%,
  rgba(67,56,202,.30) 53%,transparent 54%);background-size:64px 64px}
.theme-scene[data-scene="tools-3d"] .theme-scene__fallback{
  background-image:repeating-linear-gradient(60deg,transparent 0 22px,
  rgba(245,158,11,.24) 23px 25px,transparent 26px 48px);background-size:auto}
.theme-scene[data-scene="not-found"] .theme-scene__fallback{opacity:.28}
.atlas-hero .theme-scene{box-shadow:0 24px 60px rgba(13,21,38,.28)}
.atlas-scene-caption{display:flex;align-items:center;gap:10px;flex-wrap:wrap;
  padding:12px 18px;font-size:13px;letter-spacing:.04em;color:var(--at-muted,#52627a)}
.atlas-scene-caption b{color:var(--at-primary,#4338ca);font-weight:750}
.atlas-scene-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;
  margin:18px 0 0;padding:0;list-style:none}
.atlas-scene-grid li{padding:16px;border-radius:var(--at-radius,16px);
  border:1px solid rgba(127,127,127,.20);background:var(--at-surface,#ffffff)}
.atlas-scene-grid strong{display:block;font-size:15px;margin-bottom:4px}
.atlas-scene-grid span{font-size:13px;color:var(--at-muted,#52627a)}
.atlas-scene-lite .theme-scene__fallback{opacity:.32}
.atlas-scene-lite .theme-scene.theme-scene--hero{min-height:220px}
@media(max-width:720px){.theme-scene.theme-scene--hero{min-height:220px}
  .atlas-scene-grid{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){.theme-scene__fallback{opacity:.18}}
"""

SLOT="{% include 'partial/theme-scene.twig' %}"
CAP=('<div class="atlas-scene-caption"><b>Atlas Studio 3D</b>'
     '<span>Cena declarativa · sem codigo executavel no tema</span>'
     '<span>WebGL self-hosted e carregado pelo shell confiavel</span></div>')
GRID=('<ul class="atlas-scene-grid"><li><strong>hero-particles</strong>'
      '<span>Campo de particulas na abertura</span></li>'
      '<li><strong>portfolio-3d</strong><span>Quadros de projeto em profundidade</span></li>'
      '<li><strong>tools-3d</strong><span>Oficina e ferramentas</span></li></ul>')

# 1) CSS novo entra no <style> do cabecalho aprovado (append, nada removido)
h=T['partial/site_header']
assert h.count('</style>')==1
T['partial/site_header']=h.replace('</style>',THREE_CSS+'\n</style>',1)

# 2) home: slot declarativo antes das secoes + legenda + grade de cenas
home=T['common/home']
assert '<main class="atlas">' in home
T['common/home']=home.replace('<main class="atlas">',
  '<main class="atlas">\n'+SLOT+'\n'+CAP+'\n<div class="atlas-wrap">'+GRID+'</div>',1)

# 3) UM slot por PAGINA (partials de secao ficam sem slot: a home ja tem o seu).
#    Assim nenhuma rota instancia dois containers [data-scene].
# error/not_found NAO entra: o footer aprovado ja emite o slot footer_top
# quando a cena e not-found. Incluir aqui duplicaria o container [data-scene].
PAGES=('catalog/project_list','catalog/project_info','tool/tool_list',
       'tool/tool_info','blog/blog_list','blog/blog_info','cms/page_info',
       'common/contact','checkout/quote')
for k in PAGES:
    s=T[k]
    if s.startswith('{#'):
        first,rest=s.split('\n',1); T[k]=first+'\n'+SLOT+'\n'+rest
    else:
        T[k]=SLOT+'\n'+s
# parciais de secao e rodape permanecem IDENTICOS ao tema aprovado
UNTOUCHED=('partial/hero','partial/hero-3d','partial/portfolio','partial/tools',
           'partial/news','partial/clients','partial/services','partial/about',
           'partial/methodology','partial/skills','partial/testimonials',
           'partial/contact','common/footer')

# ── three base + cena allowlistada ────────────────────────────────────
THREE=lambda scene,slot,count,color,speed:{
 'enabled':True,'scene':scene,'slot':slot,'count':count,'color':color,'speed':speed}
c['three']=THREE('hero-particles','hero',520,'#4338ca',0.06)
c['scene']='hero-particles'
c['primary']='#4338ca'; c['surface']='#ffffff'; c['parent']=''

# ── conditions: cenas diferentes por rota (declarativo, allowlistado) ──
def rule(rid,when,three,scene,classes=None):
    then={'scene':scene,'three':three}
    if classes:then['classes']=classes
    return {'id':rid,'when':when,'then':then}
c['conditions']={'version':1,'rules':[
 rule('atlas_home_particles',{'field':'route','op':'eq','value':'common/home'},
      THREE('hero-particles','hero',520,'#4338ca',0.06),'hero-particles'),
 rule('atlas_portfolio_3d',{'field':'route','op':'in','value':['catalog/project_list','catalog/project_info']},
      THREE('portfolio-3d','home_top',500,'#22d3ee',0.06),'portfolio-3d'),
 rule('atlas_tools_3d',{'field':'route','op':'in','value':['tool/tool_list','tool/tool_info']},
      THREE('tools-3d','home_top',350,'#f59e0b',0.08),'tools-3d'),
 rule('atlas_not_found',{'field':'route','op':'eq','value':'error/not_found'},
      THREE('not-found','footer_top',200,'#94a3b8',0.04),'not-found'),
 {'id':'atlas_mobile_lite','when':{'field':'mobile','op':'eq','value':True},
  'then':{'classes':['atlas-scene-lite'],
          'three':THREE('hero-particles','hero',180,'#4338ca',0.04)}},
]}

# ── kaz_scene: 16 camadas declarativas da composicao 3D (dados de edicao) ──
P='#4338ca'; S='#ffffff'; INK='#101828'; BLUE='#0ea5e9'; CYAN='#22d3ee'
AMBER='#f59e0b'; PALE='#f5f7fc'; DARK='#0d1526'; SLATE='#94a3b8'
L=[]
def layer(i,kind,role,x,y,w,h,fill,text='',font=16,radius=16,visible=True):
    L.append({'id':'atlas3d-%02d'%i,'kind':kind,'role':role,'x':x,'y':y,'w':w,'h':h,
              'fill':fill,'radius':radius,'text':text,'fontSize':font,
              'visible':visible,'locked':False,'bindId':''})
layer(1,'rect','primary',  40, 40,420,200,P,   radius=20)
layer(2,'text','none',     60, 62,380, 46,'#ffffff','Atlas Studio 3D',32,0)
layer(3,'text','none',     60,118,380, 34,'#ffffff','Cena declarativa por rota',18,0)
layer(4,'button','component',60,178,220,44,BLUE,'Ver projetos',16,11)
layer(5,'card','surface', 480, 40,440,200,PALE,radius=20)
layer(6,'rect','none',    500, 60,180,120,CYAN,radius=12)
layer(7,'rect','none',    694, 60,206,120,P,   radius=12)
layer(8,'rect','none',    500,194,400, 28,S,   radius=8)
layer(9,'section','section',40,264,280,220,DARK,radius=20)
layer(10,'text','none',    58,284,244, 40,'#ffffff','hero-particles',20,0)
layer(11,'text','none',    58,332,244, 34,'#dee2e6','520 particulas · 0.06',14,0)
layer(12,'card','surface',340,264,280,220,PALE,radius=20)
layer(13,'text','none',   358,284,244, 40,INK,'portfolio-3d',20,0)
layer(14,'text','none',   358,332,244, 34,'#52627a','Quadros em profundidade',14,0)
layer(15,'card','surface',640,264,280,220,PALE,radius=20)
layer(16,'button','component',660,420,240,48,AMBER,'Solicitar proposta',16,11)
c['kaz_scene']={'version':1,'canvas':{'w':960,'h':540,'grid':8},'layers':L}

# ── chaves finais ─────────────────────────────────────────────────────
c['templates']=T
for k in ('sections','components','schedule','media','sites'):c.pop(k,None)
cfg={'name':'Atlas Studio 3D','slug':'atlas_studio_3d','config':c}
json.dump(cfg,open(OUT,'w',encoding='utf-8'),ensure_ascii=False,indent=1)
print('fontes:',len(T),'| kaz_scene:',len(L),'camadas | conditions:',len(c['conditions']['rules']),'regras')
print('three:',c['three'])
print('bytes config:',os.path.getsize(OUT))
slots=[k for k,v in T.items() if SLOT in v]
print('templates com slot:',len(slots),'| sem slot:',len(T)-len(slots))
print('slot em partials/footer:',[k for k in slots if k in UNTOUCHED])
print('inalterados:',len([k for k in UNTOUCHED if T[k]==json.load(open(BASE,encoding='utf-8'))['config']['templates'][k]]),'/',len(UNTOUCHED))
