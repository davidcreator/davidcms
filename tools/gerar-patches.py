#!/usr/bin/env python3
"""Frente BL — patches de runtime (PROPOSTAS, nao aplicadas).
A: fecha o vazamento de forma FAIL-CLOSED, independente do config no banco.
B: faz o KAZEditor-Theme carregar os dados reais e nao reintroduzir o include.
"""
import os,difflib,json
SHA=open('/tmp/bl_sha.txt').read().strip().split('=')[1]
SRC={
 'dev/catalog/view/template/partial/theme-scene-block.twig':'/tmp/bl_src/dev/catalog/view/template/partial/theme-scene-block.twig',
 'dev/system/library/template/twig.php':'/tmp/bl_src/dev/system/library/template/twig.php',
 'dev/catalog/view/template/common/home.twig':'/tmp/bl_src/dev/catalog/view/template/common/home.twig',
 'dev/catalog/controller/common/home.php':'/tmp/bl_src/dev/catalog/controller/common/home.php',
 'dev/catalog/controller/startup/theme.php':'/tmp/bl_src/dev/catalog/controller/startup/theme.php',
 'dev/admin/controller/design/theme.php':'/tmp/bl_src/dev/admin/controller/design/theme.php',
 'dev/admin/view/template/design/kaz_theme_editor.twig':'/tmp/bl_kaz.twig',
}
OUT='/tmp/bl_patch';os.makedirs(OUT,exist_ok=True)
orig={k:open(v,encoding='utf-8').read() for k,v in SRC.items()}

# ── PATCH A: vazamento ────────────────────────────────────────────────
A=[]
def ch(lista,rel,old,new,count=1):
    assert orig[rel].count(old)==count,(rel,'needle',orig[rel].count(old))
    lista.append((rel,old,new))
# 1) gate fail-closed no partial: so renderiza na previa do Admin
ch(A,'dev/catalog/view/template/partial/theme-scene-block.twig',
   "{% if theme_scene_view.enabled|default(false) %}",
   "{% if theme_scene_preview|default(false) and theme_scene_view.enabled|default(false) %}")
# 2) twig.php: sinaliza explicitamente o contexto de previa (2 pontos)
ch(A,'dev/system/library/template/twig.php',
   "'theme_scene_view'=>self::previewSceneView($sceneView),",
   "'theme_scene_view'=>self::previewSceneView($sceneView),\n            'theme_scene_preview'=>true,")
ch(A,'dev/system/library/template/twig.php',
   "$context['theme_scene_view'] = self::previewSceneView($sceneView);",
   "$context['theme_scene_view'] = self::previewSceneView($sceneView);\n            $context['theme_scene_preview'] = true;")
# 3-5) defesa em profundidade: as 3 remocoes da BK
ch(A,'dev/catalog/view/template/common/home.twig',
   "{% include 'partial/theme-scene-block.twig' %}\n",'')
ch(A,'dev/catalog/controller/common/home.php',
   "        // Frente AB: blocos da cena desenhada no editor (escalares allowlistados).\n"
   "        $data['theme_scene_view'] = (array)$this->config->get('catalog_theme_scene_view', []);\n",'')
ch(A,'dev/catalog/controller/startup/theme.php',
   "        $this->config->set('catalog_theme_scene_view', $snapshot['scene_view']);\n",'')

# ── PATCH B: editor carregar dados reais ──────────────────────────────
B=[]
# B1) edit(): se o rascunho nao tem templates, usa o payload PUBLICADO (dado real)
old="        $config=json_decode((string)($theme['draft_config']??$theme['config']??'{}'),true); if (!is_array($config)) $config=[];"
new=("        $config=json_decode((string)($theme['draft_config']??$theme['config']??'{}'),true); if (!is_array($config)) $config=[];\n"
     "        // BL: quando o rascunho nao traz templates, o dado real do tema esta\n"
     "        // no payload publicado. Sem isto o editor caia nos .twig do disco.\n"
     "        if (!isset($config['templates']) || !is_array($config['templates']) || $config['templates']===[]) {\n"
     "            $published=json_decode((string)($theme['published_config']??$theme['config']??'{}'),true);\n"
     "            if (is_array($published) && isset($published['templates']) && is_array($published['templates']))\n"
     "                $config['templates']=$published['templates'];\n"
     "        }")
ch(B,'dev/admin/controller/design/theme.php',old,new)
# B2) controller: expoe quais chaves realmente pertencem ao tema
old2="$data['template_sources']=$data['templates'];"
new2=("$data['template_saved_keys']=array_keys((array)$data['templates']);\n"
      "        $data['template_sources']=$data['templates'];")
ch(B,'dev/admin/controller/design/theme.php',old2,new2)
# B3) twig: fontes nativas visiveis, porem SEM name= (nao submetidas)
old3='<textarea class="form-control font-monospace mt-2" name="templates[{{ key }}]" rows="14" maxlength="131072" spellcheck="false">{{ template_sources[key] ?? \'\' }}</textarea>'
assert old3 in orig['dev/admin/view/template/design/kaz_theme_editor.twig'],'textarea needle'
new3=('<textarea class="form-control font-monospace mt-2" {% if key in template_saved_keys|default([]) %}name="templates[{{ key }}]"{% else %}disabled aria-label="Fonte nativa (nao faz parte do tema)"{% endif %} rows="14" maxlength="131072" spellcheck="false">{{ template_sources[key] ?? \'\' }}</textarea>'
       "{% if key not in template_saved_keys|default([]) %}<p class=\"form-text\">Fonte nativa do disco — não pertence a este tema e não é salva.</p>{% endif %}")
ch(B,'dev/admin/view/template/design/kaz_theme_editor.twig',old3,new3)

def build(grupo,nome):
    state=dict(orig);patches=[]
    for rel,old,new in grupo:
        state[rel]=state[rel].replace(old,new,1)
    for rel in sorted({r for r,_,_ in grupo}):
        d=''.join(difflib.unified_diff(orig[rel].splitlines(True),state[rel].splitlines(True),
                  fromfile='a/'+rel,tofile='b/'+rel,n=3))
        patches.append(d)
        os.makedirs(os.path.join(OUT,os.path.dirname(rel)),exist_ok=True)
        open(os.path.join(OUT,rel.replace('/','__')),'w',encoding='utf-8').write(state[rel])
    txt=''.join(patches)
    open(os.path.join(OUT,nome),'w',encoding='utf-8').write(txt)
    print(nome,len(txt),'bytes |',len(grupo),'edicoes em',len({r for r,_,_ in grupo}),'arquivos')
    return txt
pa=build(A,'A-vazamento-failclosed.patch')
pb=build(B,'B-editor-dados-reais.patch')
json.dump({'head':SHA,
 'A':{'arquivos':sorted({r for r,_,_ in A}),'edicoes':len(A),'bytes':len(pa)},
 'B':{'arquivos':sorted({r for r,_,_ in B}),'edicoes':len(B),'bytes':len(pb)}},
 open(os.path.join(OUT,'patches.json'),'w'),indent=1,ensure_ascii=False)
