/* Frente BL — verificador. Uso: node tools/verify-bl.cjs <raiz> */
'use strict';
const fs=require('fs'),path=require('path');
const R=process.argv[2]||process.cwd();let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(R,p)),rd=p=>fs.readFileSync(path.join(R,p),'utf8');
const A='docs/correcao/A-vazamento-failclosed.patch',B='docs/correcao/B-editor-dados-reais.patch';

console.log('-- 1. Diagnóstico e documentação --');
for(const f of ['DIAGNOSTICO-BL.md','GUIA-BL.md','RELATORIO-FINAL-BL.md','INDICE-BL.md',
                A,B,'docs/correcao/patches.json','tools/diagnostico-tema.sql',
                'evidencia/model-harness.txt','evidencia/cadeia-bl.json'])ok('presente: '+f,ex(f));
const docs=['DIAGNOSTICO-BL.md','GUIA-BL.md','RELATORIO-FINAL-BL.md','INDICE-BL.md'].map(rd).join('\n');
for(const s of ['draft_config','published_config','activate()','theme.php:369',
 'template_sources','git apply','Ativar','release_approved: false','BLOCKED_NOT_RUN',
 'PENDING_DECISION','escrita do agente no GitHub'])ok('doc cita: '+s,docs.includes(s));
ok('não afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));

console.log('-- 2. Patch A (fail-closed) --');
const pa=rd(A);
ok('gate theme_scene_preview no partial',/\+.*theme_scene_preview\|default\(false\) and theme_scene_view\.enabled/.test(pa));
ok('flag no twig.php (2 pontos)',(pa.match(/\+\s*'theme_scene_preview'=>true|\+\s*\$context\['theme_scene_preview'\] = true/g)||[]).length===2);
ok('remove include do home.twig',/-\{% include 'partial\/theme-scene-block\.twig' %\}/.test(pa));
ok('remove theme_scene_view do home.php',/\-\s*\$data\['theme_scene_view'\]/.test(pa));
ok('remove set do startup',/\-\s*\$this->config->set\('catalog_theme_scene_view'/.test(pa));
ok('5 arquivos no patch A',(pa.match(/^--- a\//gm)||[]).length===5);
ok('patch A não remove o partial',!/^--- a\/dev\/catalog\/view\/template\/partial\/theme-scene-block\.twig\n\+\+\+ \/dev\/null/m.test(pa));
ok('patch A não toca Admin/contratos',!pa.includes('dev/admin/')&&!pa.includes('CHECKSUMS')&&!pa.includes('schema'));

console.log('-- 3. Patch B (dados reais) --');
const pb=rd(B);
ok('herda templates do publicado',/\+\s*\$published=json_decode\(\(string\)\(\$theme\['published_config'\]/.test(pb));
ok('expoe template_saved_keys',pb.includes("+")&&/\+\s*.*\$data\['template_saved_keys'\]=array_keys/.test(pb));
ok('fonte nativa disabled',/disabled aria-label="Fonte nativa/.test(pb));
ok('fonte nativa SEM name=',/\{% else %}disabled/.test(pb));
ok('2 arquivos no patch B',(pb.match(/^--- a\//gm)||[]).length===2);
ok('patch B não altera buildDraftConfig/save/activate',!/\-\s*(public|private) function (buildDraftConfig|save|activate)/.test(pb));

console.log('-- 4. Prova com model real e SQL --');
const h=rd('evidencia/model-harness.txt');
ok('10 OK / 0 FALHAS',h.includes('PHP BL model harness: 10 OK / 0 FALHAS'));
for(const s of ['apos import(): draft SEM include','published AINDA COM include',
 'salvar no editor REINTRODUZ o include','activate() copia draft -> published/config',
 'gate fail-closed BLOQUEIA no Catalogo','gate PERMITE na previa do Admin'])ok('harness: '+s,h.includes('OK   ')&&h.includes(s));
const sql=rd('tools/diagnostico-tema.sql');
ok('SQL somente SELECT',!/^\s*(INSERT|UPDATE|DELETE|ALTER|DROP|CREATE)\b/im.test(sql.replace(/--.*$/gm,'')));
ok('SQL cobre os 3 payloads',['config','draft_config','published_config'].every(k=>sql.includes(k)));
ok('SQL aponta include no publicado',sql.includes('include_no_publicado'));
const cad=JSON.parse(rd('evidencia/cadeia-bl.json'));
ok('cadeia com 8 elos',cad.elos.length===8,String(cad.elos.length));
ok('HEAD da cadeia',cad.head==='3219ccae80550f81510068c311a8afcc1de8d71a');

console.log('-- 5. Temas corrigidos (inalterados desde a BK) --');
for(const f of ['David-Creator','Atlas-Studio','Reamur-Reference','Reamur-ThreeJS-Reference']){
 const rel='docs/temas/'+f+'.reamurtheme.json';ok('presente: '+rel,ex(rel));
 if(!ex(rel))continue;
 const p=JSON.parse(rd(rel)),all=Object.values(p.config.templates).join('\n');
 ok('  '+f+': 25 fontes / sem include',Object.keys(p.config.templates).length===25&&!all.includes('theme-scene-block.twig'));
 ok('  '+f+': kaz_scene preservado',p.config.kaz_scene.layers.length>=3);
}
console.log('CJS BL: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
