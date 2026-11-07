#!/usr/bin/env python3
"""Frente BN — previas estaticas FIEEIS ao tema David Universe 3D.
O slot theme-scene.twig e renderizado exatamente como o Twig do Catalogo
emitiria: apenas atributos data-* e o fallback. Nenhum JS na previa.
"""
import json,re,os
SRC='/home/user/bn_out/david_universe_3d.reamurtheme.json'
OUT='/home/user/bn_previews';os.makedirs(OUT,exist_ok=True)
P=json.load(open(SRC,encoding='utf-8'));T=P['config']['templates']

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
def slot(scene,sl,count,color,speed):
    return ('<div class="theme-scene theme-scene--%s" data-scene="%s" data-count="%s" '
            'data-color="%s" data-speed="%s" aria-hidden="true">'
            '<div class="theme-scene__fallback" data-fallback></div></div>'
            %(sl,scene,count,color,speed))
CSS=re.search(r'<style>(.*?)</style>',T['partial/site_header'],flags=re.S).group(1)
header=clean(re.sub(r'<style>.*?</style>','',T['partial/site_header'],flags=re.S))
footer=clean(T['common/footer']).replace('</body>','').replace('</html>','')
MAIN_PHASES=['partial/hero','partial/about','partial/methodology','partial/clients',
 'partial/services','partial/skills','partial/portfolio','partial/tools',
 'partial/testimonials','partial/news','partial/contact']
home_main=''.join(clean(T[k]) for k in MAIN_PHASES)
cap='<div class="du-wrap"><div class="du-scene-caption"><b>UNIVERSO 3D</b><span>Cena declarativa · sem código executável no tema</span></div></div>'

def page(title,main,scene,sl,count,color,speed,cls=''):
    return ("<!DOCTYPE html>\n<html lang=\"pt-BR\">\n<head>\n<meta charset=\"UTF-8\">\n"
      "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
      "<meta name=\"theme-color\" content=\"#121516\">\n<title>"+title+"</title>\n"
      "<style>\n"+CSS+"\n</style>\n</head>\n"
      "<body class=\""+cls+"\" style=\"--theme-primary:#5cc6d0;--theme-surface:#121516;margin:0;background:#121516\">\n"
      +header+"\n<main class=\"du\">\n"+slot(scene,sl,count,color,speed)+"\n"+cap
      +"\n"+main+"\n</main>\n"+footer+"\n</body>\n</html>\n")

pages={
 'home':page('David Universe 3D — Início',home_main,'hero-particles','hero',520,'#5cc6d0','0.06'),
 'catalogo':page('David Universe 3D — Catálogo',clean(T['catalog/project_list']),
   'portfolio-3d','home_top',500,'#eb767e','0.07'),
 'ferramentas':page('David Universe 3D — Ferramentas',clean(T['tool/tool_list']),
   'tools-3d','home_top',350,'#ffdf00','0.08'),
 'not-found':page('David Universe 3D — Não encontrado',clean(T['error/not_found']),
   'not-found','footer_top',200,'#a7b3b5','0.04'),
}
for k,v in pages.items():
    f=os.path.join(OUT,k+'.html');open(f,'w',encoding='utf-8').write(v)
    print(k,f,len(v.encode()),'bytes | slot:',
      re.search(r'<div class="theme-scene [^"]*" data-scene="([^"]+)"',v).group(1))
