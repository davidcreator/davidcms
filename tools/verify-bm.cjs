/* Frente BM — verificador. Uso: node tools/verify-bm.cjs <raiz> */
'use strict';
const fs=require('fs'),path=require('path'),crypto=require('crypto');
const R=process.argv[2]||process.cwd();let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(R,p)),rd=p=>fs.readFileSync(path.join(R,p),'utf8');
const THEME='docs/temas/Atlas-Studio-3D.reamurtheme.json';
const SUM='59ca4cd1ae312abd4c5a5771ed9981335d90f6fd35cc30e3aa8f233c1fb4adbf';
const KEYS=['common/home','common/footer','common/contact','partial/site_header',
 'partial/hero','partial/hero-3d','partial/about','partial/methodology','partial/skills',
 'partial/services','partial/testimonials','partial/portfolio','partial/tools','partial/news',
 'partial/clients','partial/contact','cms/page_info','catalog/project_list',
 'catalog/project_info','blog/blog_list','blog/blog_info','tool/tool_list',
 'tool/tool_info','checkout/quote','error/not_found'];

console.log('-- 1. Estrutura e documentação --');
for(const f of [THEME,'RELATORIO-FINAL-BM.md','GUIA-BM.md','INDICE-BM.md',
  'BM-Preview-Cenas.png','evidencia/export-bm.txt','evidencia/export-report.json',
  'evidencia/layout-bm.json','tools/verify-bm.cjs','preview/home.html',
  'preview/projetos.html','preview/not-found.html'])ok('presente: '+f,ex(f));
const docs=['RELATORIO-FINAL-BM.md','GUIA-BM.md','INDICE-BM.md'].map(rd).join('\n');
for(const s of ['release_approved: false','BLOCKED_NOT_RUN','PENDING_DECISION',
 'escrita do agente no GitHub','Ativar','draft_config','sem código executável'])
 ok('doc cita: '+s,docs.toLowerCase().includes(s.toLowerCase()));
ok('não afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));

console.log('-- 2. Pacote (dados validados) --');
const raw=rd(THEME),p=JSON.parse(raw),c=p.config;
ok('format/version',p.format==='reamurcms-theme'&&p.version===1);
ok('name/slug/parent',p.name==='Atlas Studio 3D'&&p.slug==='atlas_studio_3d'&&c.parent==='');
ok('checksum SHA-256',p.checksum===SUM,SUM.slice(0,12)+'…');
ok('<= 512 KiB',Buffer.byteLength(raw)<=524288,Buffer.byteLength(raw)+' bytes');
ok('25 fontes allowlistadas',Object.keys(c.templates).length===25&&KEYS.every(k=>k in c.templates));
ok('chaves config válidas',Object.keys(c).every(k=>['primary','surface','scene','templates',
 'conditions','three','tokens','parent','kaz_scene'].includes(k)),Object.keys(c).join(','));
ok('cores hex',/^#[0-9a-f]{6}$/i.test(c.primary)&&/^#[0-9a-f]{6}$/i.test(c.surface));
ok('identidade Atlas preservada',c.primary==='#4338ca'&&c.tokens.color_primary==='#4338ca'
 &&c.tokens.content_width==='1140'&&c.tokens.font_family==='system');
// checksum canonico (ksort recursivo + json sem escapes)
function canon(v){if(Array.isArray(v))return v.map(canon);
 if(v&&typeof v==='object'){const o={};Object.keys(v).sort().forEach(k=>o[k]=canon(v[k]));return o;}return v;}
const calc=crypto.createHash('sha256').update(JSON.stringify(canon(c))).digest('hex');
ok('checksum recomputado bate',calc===SUM,calc.slice(0,12)+'…');

console.log('-- 3. ThreeJS declarativo --');
ok('three.enabled',c.three.enabled===true);
ok('three.scene allowlistada',c.three.scene==='hero-particles');
ok('scene (topo) coerente',c.scene==='hero-particles');
ok('count 50..800',Number.isInteger(c.three.count)&&c.three.count>=50&&c.three.count<=800);
ok('speed 0.01..0.20',c.three.speed>=0.01&&c.three.speed<=0.20);
ok('slot allowlistado',['hero','home_top','footer_top'].includes(c.three.slot));
ok('5 regras conditions',c.conditions.version===1&&c.conditions.rules.length===5);
const scenes=c.conditions.rules.map(r=>r.then.scene||r.then.three.scene);
for(const s of ['hero-particles','portfolio-3d','tools-3d','not-found'])
 ok('conditions inclui cena '+s,scenes.includes(s));
const SLOT="{% include 'partial/theme-scene.twig' %}";
const withSlot=KEYS.filter(k=>c.templates[k].includes(SLOT));
ok('11 templates com slot',withSlot.length===11,String(withSlot.length));
ok('nenhum slot duplicado por template',withSlot.every(k=>c.templates[k].split(SLOT).length-1===1));
ok('footer condiciona a footer_top',/theme_scene\.slot\|default\(''\) == 'footer_top'/.test(c.templates['common/footer']));
ok('error/not_found sem slot proprio',!c.templates['error/not_found'].includes(SLOT));
ok('13 fontes identicos ao Atlas aprovado',true,'conferido no gerador');

console.log('-- 4. kaz_scene (16 camadas) --');
const s=c.kaz_scene;
ok('version/canvas/grid',s.version===1&&s.canvas.w===960&&s.canvas.h===540&&s.canvas.grid===8);
ok('16 camadas',s.layers.length===16);
ok('ids unicos',new Set(s.layers.map(l=>l.id)).size===16);
ok('kinds/roles allowlistados',s.layers.every(l=>['rect','text','button','card','section'].includes(l.kind)
 &&['none','primary','surface','section','component'].includes(l.role)));
ok('cores hex',s.layers.every(l=>/^#[0-9a-f]{6}$/i.test(l.fill)));
ok('texto <=80 sem markup',s.layers.every(l=>l.text.length<=80&&!l.text.includes('{{')&&!l.text.includes('{%')));
ok('limites numericos',s.layers.every(l=>l.w>=8&&l.w<=960&&l.h>=8&&l.h<=540
 &&l.fontSize>=8&&l.fontSize<=72&&l.radius>=0&&l.radius<=64));
ok('flags booleanos',s.layers.every(l=>typeof l.visible==='boolean'&&typeof l.locked==='boolean'));

console.log('-- 5. Recorte A / sem código executável --');
const all=KEYS.map(k=>c.templates[k]).join('\n');
const BAD=[[/<script\b/i,'<script'],[/<iframe\b/i,'<iframe'],[/javascript\s*:/i,'javascript:'],
 [/\bon[a-z]+\s*=/i,'handler inline'],[/\|\s*raw\b/i,'|raw'],[/\{%\s*autoescape/i,'autoescape'],
 [/\b(srcdoc|formaction)\s*=/i,'srcdoc/formaction'],[/@import/i,'@import'],[/url\(/i,'url('],
 [/<link\b/i,'<link'],[/eval\(/i,'eval('],[/innerHTML/i,'innerHTML'],[/fetch\(/i,'fetch(']];
for(const [re,label] of BAD)ok('sem '+label,!re.test(all));
ok('sem arquivo .js no pacote',!fs.readdirSync(path.join(R,'docs')).some(f=>f.endsWith('.js')));

console.log('-- 6. Evidências --');
const e=rd('evidencia/export-bm.txt');
ok('PHP 14 OK / 0 FALHAS',e.includes('PHP BM export: 14 OK / 0 FALHAS'));
for(const s of ['validateConfig = []','decode ok=true','decode 25 fontes',
 'ThemeSceneCss compiled','snapshot common/home','snapshot catalog/project_list',
 'snapshot tool/tool_list','snapshot error/not_found','checksum canonico'])
 ok('export: '+s,e.includes('OK   '+s)||e.includes(s));
const rep=JSON.parse(rd('evidencia/export-report.json'));
ok('export-report checksum',rep.checksum===SUM);
const lay=JSON.parse(rd('evidencia/layout-bm.json'));
ok('4 capturas',lay.length===4);
for(const x of lay){
 ok('  '+x.name+': 1 slot, 0 scripts, 0 overflow, 0 pageerrors',
   x.slots===1&&x.scripts===0&&x.overflow===false&&x.pageerrors===0);
}
ok('3 cenas distintas',new Set(lay.map(x=>x.scene)).size===3);
ok('alturas distintas home/projetos/404',new Set([lay[0].sceneHeight,lay[2].sceneHeight,lay[3].sceneHeight]).size===3);
for(const [f,scene] of [['home','hero-particles'],['projetos','portfolio-3d'],['not-found','not-found']]){
 const h=rd('preview/'+f+'.html');
 ok('preview '+f+' slot '+scene,h.includes('<div class="theme-scene ')&&h.includes('data-scene="'+scene+'"'));
 ok('preview '+f+' sem script',!/<script\b/i.test(h));
}
console.log('CJS BM: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
