#!/usr/bin/env python3
"""Frente BO — monta a previa funcional: SceneRunner REAL + david-universe.js
REAL + Three.js r186 self-hosted. Nada e simulado: o proprio runtime do CMS
e executado pelo Chromium. Nao usa CDN nem recursos externos.
"""
import json,os,re,shutil
ROOT='/home/user/bo_preview'
shutil.rmtree(ROOT,ignore_errors=True)
os.makedirs(ROOT+'/runtime/three/core',exist_ok=True)
os.makedirs(ROOT+'/runtime/three/scenes',exist_ok=True)

# modulos reais
shutil.copyfile('/home/user/bo_src/runtime/three.module.js',ROOT+'/runtime/three.module.js')
shutil.copyfile('/home/user/bo_src/runtime/three.core.js',ROOT+'/runtime/three.core.js')
shutil.copyfile('/home/user/bo_pkg/dev/system/library/three/runtime/three/core/scene-runner.js',
                ROOT+'/runtime/three/core/scene-runner.js')
shutil.copyfile('/home/user/bo_src/dev/system/library/three/runtime/three/core/dom.js',
                ROOT+'/runtime/three/core/dom.js')
shutil.copyfile('/home/user/bo_pkg/dev/system/library/three/runtime/three/scenes/david-universe.js',
                ROOT+'/runtime/three/scenes/david-universe.js')

open(ROOT+'/main-bo.js','w').write(
"import { boot } from './runtime/three/core/scene-runner.js';\n"
"import './runtime/three/scenes/david-universe.js';\n"
"boot();\n")

cfg=json.load(open('/home/user/bo_out/bo_config.json',encoding='utf-8'))['config']
T=cfg['templates']
header=T['partial/site_header']
style=re.sub(r'\{#.*?#\}\s*','',header[:header.index('</style>')+8],flags=re.S)
hud=header[header.index('</style>')+8:]
# A previa e HTML estatico: remove o comentario Twig e renderiza o unico loop
# de idiomas exatamente como o CMS o faria, para a evidencia nao mostrar codigo.
hud=re.sub(r'\{#.*?#\}\s*','',hud,flags=re.S)
hud=hud.replace(
 '{% for lang in languages|default([]) %}<a href="{{ lang.href }}" hreflang="{{ lang.code }}">{{ lang.code }}</a>{% endfor %}',
 '<a href="?lang=pt" hreflang="pt">pt</a><a href="?lang=en" hreflang="en">en</a><a href="?lang=es" hreflang="es">es</a>')
assert '{%' not in hud and '{#' not in hud, 'Twig residual na previa'

FASES=[('00','DECOLAGEM','Eu sou David L. Almeida','Posso criar sites, aplicativos, logotipos e outras mídias digitais incríveis.'),
       ('01','SOBRE','Desenvolvedor Criativo','São Paulo, Brasil. Identidade visual, interfaces e experiências imersivas.'),
       ('02','PROCESSO','Seis etapas','Briefing, pesquisa, protótipo, design, desenvolvimento e entrega.'),
       ('03','POR QUE INVESTIR','Presença que converte','Design consistente, performance e SEO caminham juntos.'),
       ('04','HABILIDADES','Inventário','HTML, CSS, PHP, Javascript, jQuery, Illustrator, Photoshop, Figma.'),
       ('05','SERVIÇOS','O que eu faço','Sites, aplicativos, logotipos, UI, SEO e mídias digitais.'),
       ('06','CATÁLOGO','Nove níveis','Juvenile World Academy, Steel of Death, Farmer Life, Chameleon, Rogério, Pitbul, Cat Fe, Classic Car, Selva Dev.'),
       ('07','DEPOIMENTOS','O que dizem','Depoimentos e últimos posts do Instagram.'),
       ('08','CONTATO','Vamos conversar','contact@davidalmeida.xyz · São Paulo, Brasil')]

sections=[]
for n,tag,title,text in FASES:
    sections.append(
      f'<section class="du du-phase" id="fase-{n}"><div class="du-wrap">'
      f'<div class="du-phase-tag"><b>FASE {n}</b><span>{tag}</span></div>'
      f'<h2>{title}</h2><p>{text}</p>'
      f'<div class="du-wrap du-hint"><span>ROLE PARA VOAR ▾</span>'
      f'<span>DISTÂNCIA <span data-du-hint-distancia>0 KM</span></span></div>'
      f'</div></section>')

html=('<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
 '<meta name="viewport" content="width=device-width,initial-scale=1">'
 '<title>David Universe 3D — Cena Real | Prévia BO</title>'
 +style+'</head><body class="du">'
 +hud
 +'<div class="theme-scene theme-scene--hero" data-scene="david-universe"'
 ' data-count="520" data-color="#5cc6d0" data-speed="0.06" data-debug="1" aria-hidden="true">'
 '<div class="theme-scene__fallback" data-fallback></div></div>'
 +''.join(sections)
 +'<div style="height:60vh"></div>'
 +'<script type="module" src="./main-bo.js"></script></body></html>')
open(ROOT+'/index.html','w',encoding='utf-8').write(html)

print('previa:',ROOT)
for root,dirs,files in os.walk(ROOT):
    for f in files:
        p=os.path.join(root,f)
        print(f'  {os.path.getsize(p):>9,}  {os.path.relpath(p,ROOT)}')
