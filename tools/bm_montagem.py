#!/usr/bin/env python3
"""Frente BM — prancha de apresentacao das 3 cenas + home + mobile."""
from PIL import Image,ImageDraw,ImageFont
import os
W=1440
def font(sz):
    for p in ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
              '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf']:
        if os.path.exists(p):
            try:return ImageFont.truetype(p,sz)
            except:pass
    return ImageFont.load_default()
F=font(19);FB=font(23);FS=font(14)
files=[('Home · hero-particles','/tmp/bm_shot_home.png',1440,900),
       ('Projetos · portfolio-3d','/tmp/bm_shot_projetos.png',1440,760),
       ('404 · not-found','/tmp/bm_shot_not_found.png',1440,700),
       ('Mobile 390px · hero-particles','/tmp/bm_shot_home_mobile.png',390,844)]
rows=[]
for title,p,cw,ch in files:
    im=Image.open(p).convert('RGB')
    crop=im.crop((0,0,min(cw,im.width),min(ch,im.height)))
    if cw!=W and cw<W: crop=crop.resize((min(W,crop.width*2),min(crop.height*2,1400)))
    rows.append((title,crop))
PAD=26;HEAD=44
heights=[c.height+HEAD for _,c in rows]
total=PAD+sum(heights)+PAD*len(rows)+70
out=Image.new('RGB',(W+2*PAD,total),'#eef1f7');d=ImageDraw.Draw(out)
d.text((PAD,18),'ATLAS STUDIO 3D · tema completo com ThreeJS declarativo (Recorte A)',font=FB,fill='#101828')
y=52
for (title,crop),h in zip(rows,heights):
    d.rectangle((PAD,y,PAD+W,y+HEAD),fill='#0d1526')
    d.text((PAD+16,y+11),title,font=F,fill='#ffffff')
    d.rectangle((PAD+W-210,y+10,PAD+W-12,y+HEAD-8),fill='#4338ca')
    d.text((PAD+W-198,y+13),'dados validados',font=FS,fill='#ffffff')
    y+=HEAD
    cc=crop if crop.width==W else crop.resize((W,int(crop.height*W/crop.width)))
    out.paste(cc,(PAD,y));y+=cc.height+PAD
d.text((PAD,total-28),'Sem JS executavel no tema · slot [data-scene] · cenas allowlistadas por rota · 25 fontes Twig',
       font=FS,fill='#52627a')
path='/home/user/BM-Preview-Cenas.png';out.save(path,optimize=True)
print(path,out.size,os.path.getsize(path),'bytes')
