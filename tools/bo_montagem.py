#!/usr/bin/env python3
"""Frente BO — prancha comparativa: amostra / BN (sem nave) / BO (cena real)."""
from PIL import Image, ImageDraw, ImageFont
import os
W=1480
CELL=700
GAP=24
PAD=24
TOP=112
ROW_H=470
BG=(12,15,16); FG=(223,231,232); CYAN=(92,198,208); MUTED=(167,179,181)
def font(sz,bold=False):
    for p in ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf' if bold
              else '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
              '/home/user/libs/DejaVuSans.ttf']:
        if os.path.exists(p):
            try:return ImageFont.truetype(p,sz)
            except Exception:pass
    return ImageFont.load_default()

def load(path):
    im=Image.open(path).convert('RGB')
    return im

def fit(im,w,h,crop_top=0):
    if crop_top:
        im=im.crop((0,crop_top,im.width,min(im.height,crop_top+int(im.width*0.62))))
    r=min(w/im.width,h/im.height)
    im=im.resize((max(1,int(im.width*r)),max(1,int(im.height*r))),Image.LANCZOS)
    return im

CELLS=[
 ('Amostra online · threejs/?lang=pt','/home/user/bn_pkg/evidencia/ref-hero.png',0,
  'Nave + planeta + HUD da build Vite/React (Three r186)'),
 ('BN entregue antes · fallback declarativo','/home/user/bn_pkg/evidencia/shot-home.png',1,
  'So CSS + data-scene: sem nave, sem orbes, sem interacoes'),
 ('BO · decolagem (cena real no CMS)','/home/user/bo_out/shot-bo-hero.png',0,
  'Nave, 5 planetas, 46 asteroides, 8 gates, 14 orbes, trilha'),
 ('BO · voo por scroll','/home/user/bo_out/shot-bo-voo.png',0,
  'FASE 07 · distancia em km · ORBES nn/14 · toast de coleta'),
 ('BO · coleta e fim do voo','/home/user/bo_out/shot-bo-coleta.png',0,
  'coleta ativa (nn/14) · camera z≈-312 · ~384.400 KM · barra 100%'),
 ('BO · mobile 390px','/home/user/bo_out/shot-bo-mobile.png',0,
  'WebGL ativo, autopiloto coarse, 0 overflow, 0 pageerrors'),
]
rows=(len(CELLS)+1)//2
H=TOP+rows*(ROW_H+GAP)+PAD
out=Image.new('RGB',(W,H),BG)
d=ImageDraw.Draw(out)
d.text((PAD,18),'BO — A nave e as interacoes ThreeJS, agora no ReamurCMS',font=font(30,True),fill=CYAN)
d.text((PAD,58),'Recorte B autorizado · SceneRunner self-hosted + Three.js r186 · Chromium 141 / WebGL 2.0 · 49 OK / 0 FALHAS',
       font=font(15),fill=MUTED)
d.line((PAD,92,W-PAD,92),fill=(50,58,61),width=1)

for i,(title,path,crop,note) in enumerate(CELLS):
    col=i%2; row=i//2
    x=PAD+col*(CELL+GAP); y=TOP+row*(ROW_H+GAP)
    d.rectangle((x,y,x+CELL,y+ROW_H),fill=(18,22,24),outline=(50,58,61),width=1)
    d.text((x+12,y+10),title,font=font(17,True),fill=FG)
    img=fit(load(path),CELL-24,ROW_H-84,crop)
    px=x+(CELL-img.width)//2; py=y+38
    out.paste(img,(px,py))
    d.text((x+12,y+ROW_H-38),note,font=font(12),fill=MUTED)

path='/home/user/BO-Nave-e-Interacoes.png'
out.save(path,optimize=True)
print(path,out.size,os.path.getsize(path),'bytes')
