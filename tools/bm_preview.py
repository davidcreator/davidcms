#!/usr/bin/env python3
"""Frente BM — previas estaticas FIEEIS ao tema Atlas Studio 3D.

O slot theme-scene.twig (partial do sistema, fora da allowlist de 25 fontes)
e renderizado aqui exatamente como o Twig do Catalogo emitiria: apenas
atributos data-* e o fallback. Nenhum JavaScript e executado na previa.
"""
import json,re,os
SRC='/tmp/bm_out/atlas_studio_3d.reamurtheme.json'
OUT='/tmp/bm_previews';os.makedirs(OUT,exist_ok=True)
P=json.load(open(SRC,encoding='utf-8'))
T=P['config']['templates']
THREE=P['config']['three']

def strip_controls(s):
    s=re.sub(r'\{#.*?#\}','',s,flags=re.S)
    s=re.sub(r'\{%\s*(for|if)\b.*?%\}.*?\{%\s*end\1\s*%\}','',s,flags=re.S)
    s=re.sub(r'\{%.*?%\}','',s,flags=re.S)
    return s
def resolve(s):
    s=re.sub(r"\{\{\s*[^}]*?\|\s*default\('([^']*)'\)\s*\}\}",lambda m:m.group(1),s,flags=re.S)
    s=re.sub(r"\{\{.*?\}\}",'',s,flags=re.S)
    return s
def clean(s):return resolve(strip_controls(s))

def slot(scene,slot_name,count,color,speed):
    return ('<div class="theme-scene theme-scene--%s" data-scene="%s" data-count="%s" '
            'data-color="%s" data-speed="%s" aria-hidden="true">'
            '<div class="theme-scene__fallback" data-fallback></div></div>'
            %(slot_name,scene,count,color,speed))

CSS=re.search(r'<style>(.*?)</style>',T['partial/site_header'],flags=re.S).group(1)
header=clean(re.sub(r'<style>.*?</style>','',T['partial/site_header'],flags=re.S))
footer=clean(T['common/footer']).replace('</body>','').replace('</html>','')
ORDER=['partial/hero','partial/clients','partial/services','partial/about',
       'partial/methodology','partial/portfolio','partial/testimonials',
       'partial/news','partial/contact']
home_body=''.join(clean(T[k]) for k in ORDER)
cta=re.search(r'(<div class="atlas-wrap" style="padding-top:18px.*?</div>\s*</div>)',T['common/home'],flags=re.S)
cta_html=clean(cta.group(1)) if cta else ''
# legenda + grade (do common/home)
cap=re.search(r'(<div class="atlas-scene-caption".*?</div>)',T['common/home'],flags=re.S)
grid=re.search(r'(<ul class="atlas-scene-grid">.*?</ul>)',T['common/home'],flags=re.S)
extras=(clean(cap.group(1)) if cap else '')+'<div class="atlas-wrap">'+(clean(grid.group(1)) if grid else '')+'</div>'

def page(title,main,scene,slot_name,count,color,speed,cls=''):
    return ("<!DOCTYPE html>\n<html lang=\"pt-BR\">\n<head>\n<meta charset=\"UTF-8\">\n"
      "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
      "<title>"+title+"</title>\n<style>\n"+CSS+"\n</style>\n</head>\n"
      "<body class=\""+cls+"\" style=\"--theme-primary:#4338ca;--theme-surface:#ffffff;margin:0\">\n"
      +header+"\n<main class=\"atlas\">\n"+slot(scene,slot_name,count,color,speed)
      +"\n"+main+"\n</main>\n"+footer+"\n</body>\n</html>\n")

pages={
 'home':page('Atlas Studio 3D — Início',
   extras+home_body+cta_html,'hero-particles','hero',520,'#4338ca','0.06'),
 'projetos':page('Atlas Studio 3D — Projetos',
   clean(T['catalog/project_list']),'portfolio-3d','home_top',500,'#22d3ee','0.06'),
 'not-found':page('Atlas Studio 3D — Não encontrado',
   clean(T['error/not_found']),'not-found','footer_top',200,'#94a3b8','0.04'),
}
for k,v in pages.items():
    f=os.path.join(OUT,k+'.html');open(f,'w',encoding='utf-8').write(v)
    print(k,f,len(v.encode()),'bytes | CSS identico:',CSS in v,
          '| slot:',re.search(r'<div class="theme-scene [^"]*" data-scene="([^"]+)"',v).group(1))
