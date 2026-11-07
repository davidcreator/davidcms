#!/usr/bin/env python3
"""BN — prancha: amostra online (esquerda) x tema entregue (direita) + cenas."""
from PIL import Image,ImageDraw,ImageFont
import os
def font(sz):
    for p in ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
              '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf']:
        if os.path.exists(p):
            try:return ImageFont.truetype(p,sz)
            except:pass
    return ImageFont.load_default()
F=font(17);FB=font(22);FS=font(13)
W=1440;PAD=22;HEAD=38
def crop(p,w,h):return Image.open(p).convert('RGB').crop((0,0,w,h))
def fit(im,w):return im if im.width==w else im.resize((w,round(im.height*w/im.width)))

rows=[]
# 1) comparacao direta dos heroes (lado a lado, 700px cada)
ref=Image.open('/tmp/bn_ref_hero.png').convert('RGB').crop((0,0,1440,760)).resize((700,369))
# pula o bloco de cena declarativa para alinhar o HUD/hero com a referencia
new=Image.open('/home/user/bn_previews/shot-home.png').convert('RGB').crop((0,430,1440,1190)).resize((700,369))
cmp=Image.new('RGB',(1400,369),'#0e1011');cmp.paste(ref,(0,0));cmp.paste(new,(700,0))
d=ImageDraw.Draw(cmp);d.rectangle((698,0,702,369),fill='#5cc6d0')
d.rectangle((6,6,210,30),fill='#121516');d.text((14,9),'AMOSTRA ONLINE',font=F,fill='#5cc6d0')
d.rectangle((706,6,910,30),fill='#121516');d.text((714,9),'TEMA BN ENTREGUE',font=F,fill='#5cc6d0')
rows.append(('Hero: referência davidalmeida.xyz/threejs/?lang=pt  ×  David Universe 3D',cmp))
# 2) cenas
for title,p,h in [
 ('Cena 01 · hero-particles (home)','/home/user/bn_previews/shot-home.png',900),
 ('Cena 02 · portfolio-3d (catálogo)','/home/user/bn_previews/shot-catalogo.png',640),
 ('Cena 03 · tools-3d (ferramentas)','/home/user/bn_previews/shot-ferramentas.png',640),
 ('Cena 04 · not-found (404)','/home/user/bn_previews/shot-not-found.png',640)]:
    im=Image.open(p).convert('RGB');rows.append((title,fit(im.crop((0,0,im.width,min(h or im.height,im.height))),W)))
# 3) mobile
mob=Image.open('/home/user/bn_previews/shot-home-mob.png').convert('RGB')
rows.append(('Mobile 390px · HUD compacto, 0 overflow',fit(mob,W//2)))

heights=[c.height+HEAD for _,c in rows]
total=PAD+sum(heights)+PAD*len(rows)+74
out=Image.new('RGB',(W+2*PAD,total),'#e9eef0');d=ImageDraw.Draw(out)
d.text((PAD,16),'DAVID UNIVERSE 3D · tema completo ThreeJS declarativo · Recorte A',font=FB,fill='#101828')
y=50
for (title,c),h in zip(rows,heights):
    d.rectangle((PAD,y,PAD+W,y+HEAD),fill='#121516')
    d.text((PAD+14,y+9),title,font=F,fill='#dfdfdf')
    y+=HEAD
    cc=fit(c,W);out.paste(cc,(PAD,y));y+=cc.height+PAD
d.text((PAD,total-26),'Sem JS executável no tema · slot [data-scene] · 4 cenas por rota · 25 fontes Twig · 9 fases · 20 camadas kaz_scene',
       font=FS,fill='#52627a')
path='/home/user/BN-Amostra-vs-Tema.png';out.save(path,optimize=True)
print(path,out.size,os.path.getsize(path),'bytes')
