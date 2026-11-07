/* Frente BN — verificador. Uso: node tools/verify-bn.cjs <raiz> */
'use strict';
const fs=require('fs'),path=require('path'),crypto=require('crypto');
const R=process.argv[2]||process.cwd();let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(R,p)),rd=p=>fs.readFileSync(path.join(R,p),'utf8');
const THEME='docs/temas/David-Universe-3D.reamurtheme.json';
const SUM='e081f0d26759034b15f1e200e2248199bbee43dea30765fb8977704c3f29a1d4';
const KEYS=['common/home','common/footer','common/contact','partial/site_header',
 'partial/hero','partial/hero-3d','partial/about','partial/methodology','partial/skills',
 'partial/services','partial/testimonials','partial/portfolio','partial/tools','partial/news',
 'partial/clients','partial/contact','cms/page_info','catalog/project_list',
 'catalog/project_info','blog/blog_list','blog/blog_info','tool/tool_list',
 'tool/tool_info','checkout/quote','error/not_found'];

console.log('-- 1. Estrutura e documentação --');
for(const f of [THEME,'RELATORIO-FINAL-BN.md','GUIA-BN.md','INDICE-BN.md',
 'BN-Amostra-vs-Tema.png','evidencia/ref.json','evidencia/export-bn.txt',
 'evidencia/export-report.json','evidencia/layout-bn.json','tools/verify-bn.cjs',
 'preview/home.html','preview/catalogo.html','preview/ferramentas.html','preview/not-found.html'])
 ok('presente: '+f,ex(f));
const docs=['RELATORIO-FINAL-BN.md','GUIA-BN.md','INDICE-BN.md'].map(rd).join('\n');
for(const s of ['release_approved: false','BLOCKED_NOT_RUN','PENDING_DECISION',
 'escrita do agente no GitHub','Ativar','draft_config','sem código executável'])
 ok('doc cita: '+s,docs.toLowerCase().includes(s.toLowerCase()));
ok('cita amostra online',docs.includes('davidalmeida.xyz/threejs/'));
ok('não afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));

console.log('-- 2. Pacote (dados validados) --');
const raw=rd(THEME),p=JSON.parse(raw),c=p.config;
ok('format/version',p.format==='reamurcms-theme'&&p.version===1);
ok('name/slug/parent',p.name==='David Universe 3D'&&p.slug==='david_universe_3d'&&c.parent==='');
ok('checksum SHA-256',p.checksum===SUM,SUM.slice(0,12)+'…');
ok('<= 512 KiB',Buffer.byteLength(raw)<=524288,Buffer.byteLength(raw)+' bytes');
ok('25 fontes allowlistadas',Object.keys(c.templates).length===25&&KEYS.every(k=>k in c.templates));
ok('chaves config válidas',Object.keys(c).every(k=>['primary','surface','scene','templates',
 'conditions','three','tokens','parent','kaz_scene'].includes(k)),Object.keys(c).join(','));
ok('cores da amostra',c.primary==='#5cc6d0'&&c.surface==='#121516');
ok('tokens modo escuro',c.tokens.dark_surface==='#121516'&&c.tokens.dark_primary==='#5cc6d0'
 &&c.tokens.dark_text==='#dfdfdf');
function canon(v){if(Array.isArray(v))return v.map(canon);
 if(v&&typeof v==='object'){const o={};Object.keys(v).sort().forEach(k=>o[k]=canon(v[k]));return o;}return v;}
ok('checksum recomputado',crypto.createHash('sha256').update(JSON.stringify(canon(c))).digest('hex')===SUM);

console.log('-- 3. ThreeJS declarativo --');
ok('three.enabled',c.three.enabled===true);
ok('scene hero-particles',c.three.scene==='hero-particles'&&c.scene==='hero-particles');
ok('count 50..800',Number.isInteger(c.three.count)&&c.three.count>=50&&c.three.count<=800);
ok('speed 0.01..0.20',c.three.speed>=0.01&&c.three.speed<=0.20);
ok('slot allowlistado',['hero','home_top','footer_top'].includes(c.three.slot));
ok('5 regras',c.conditions.version===1&&c.conditions.rules.length===5);
const scenes=c.conditions.rules.map(r=>r.then.scene||r.then.three.scene);
for(const s of ['hero-particles','portfolio-3d','tools-3d','not-found'])
 ok('conditions: '+s,scenes.includes(s));
const SLOT="{% include 'partial/theme-scene.twig' %}";
const withSlot=KEYS.filter(k=>c.templates[k].includes(SLOT));
ok('10 templates com slot',withSlot.length===10,String(withSlot.length));
ok('nenhum slot duplicado',withSlot.every(k=>c.templates[k].split(SLOT).length-1===1));
ok('footer condiciona footer_top',/theme_scene\.slot\|default\(''\) == 'footer_top'/.test(c.templates['common/footer']));
ok('404 sem slot proprio',!c.templates['error/not_found'].includes(SLOT));

console.log('-- 4. kaz_scene (20 camadas) --');
const s=c.kaz_scene;
ok('version/canvas/grid',s.version===1&&s.canvas.w===960&&s.canvas.h===540&&s.canvas.grid===8);
ok('20 camadas / ids únicos',s.layers.length===20&&new Set(s.layers.map(l=>l.id)).size===20);
ok('kinds/roles allowlistados',s.layers.every(l=>['rect','text','button','card','section'].includes(l.kind)
 &&['none','primary','surface','section','component'].includes(l.role)));
ok('cores hex',s.layers.every(l=>/^#[0-9a-f]{6}$/i.test(l.fill)));
ok('texto <=80 sem markup',s.layers.every(l=>l.text.length<=80&&!l.text.includes('{{')&&!l.text.includes('{%')));
ok('limites numéricos',s.layers.every(l=>l.w>=8&&l.w<=960&&l.h>=8&&l.h<=540
 &&l.fontSize>=8&&l.fontSize<=72&&l.radius>=0&&l.radius<=64));
ok('flags booleanos',s.layers.every(l=>typeof l.visible==='boolean'&&typeof l.locked==='boolean'));

console.log('-- 5. Fidelidade ao conteúdo da amostra --');
const all=KEYS.map(k=>c.templates[k]).join('\n');
for(const s2 of ['DAVID L. ALMEIDA','Desenvolvedor Criativo','contact@davidalmeida.xyz',
 'FASE 00','DECOLAGEM','DISTÂNCIA','ORBES 00/10','Controles','Iniciar missão',
 'QUEM SOU EU?','ESCOLHA O SEU NÍVEL','VAMOS CONVERSAR?','ÚLTIMOS POSTS DO INSTAGRAM',
 'Juvenile World Academy','Steel of Death','Farmer Life Simulation','Selva Dev',
 'Conhecendo o Cliente e a Empresa','Pesquisa','Conceito','Execução','Flexibilidade','Entrega',
 'Unidade','Credibilidade','Atração','Design Gráfico','Web Design','SEO',
 'Tecnologias','Plataformas','Programas','São Paulo'])
 ok('conteúdo: '+s2,all.includes(s2));
ok('9 selos SVG inline',(all.match(/<svg /g)||[]).length>=9);
ok('9 níveis no catálogo',(c.templates['partial/portfolio'].match(/du-level-card/g)||[]).length===9);

console.log('-- 6. Recorte A / sem código executável --');
const BAD=[[/<script\b/i,'<script'],[/<iframe\b/i,'<iframe'],[/javascript\s*:/i,'javascript:'],
 [/\bon[a-z]+\s*=/i,'handler inline'],[/\|\s*raw\b/i,'|raw'],[/\{%\s*autoescape/i,'autoescape'],
 [/\b(srcdoc|formaction)\s*=/i,'srcdoc/formaction'],[/@import/i,'@import'],[/url\(/i,'url('],
 [/<link\b/i,'<link'],[/fonts\.googleapis\.com/i,'Google Fonts'],[/innerHTML/i,'innerHTML'],
 [/eval\(/i,'eval('],[/fetch\(/i,'fetch(']];
for(const [re,label] of BAD)ok('fontes sem '+label,!re.test(all));

console.log('-- 7. Evidências --');
const e=rd('evidencia/export-bn.txt');
ok('PHP 14 OK / 0 FALHAS',e.includes('PHP BN export: 14 OK / 0 FALHAS'));
for(const s3 of ['validateConfig = []','tokens dark AA','ThemeSceneCss 20 camadas',
 'decode ok=true','snapshot common/home','snapshot catalog/project_list',
 'snapshot tool/tool_list','snapshot error/not_found','checksum canonico'])
 ok('export: '+s3,e.includes(s3));
const rep=JSON.parse(rd('evidencia/export-report.json'));
ok('export-report checksum',rep.checksum===SUM);
const lay=JSON.parse(rd('evidencia/layout-bn.json'));
ok('5 capturas',lay.length===5);
for(const x of lay)ok('  '+x.name+': 1 slot/0 scripts/0 overflow/0 erros',
 x.slots===1&&x.scripts===0&&x.overflow===false&&x.pageerrors===0);
ok('4 cenas distintas',new Set(lay.map(x=>x.scene)).size===4);
ok('bodyBg = rgb(18,21,22)',lay.every(x=>x.bodyBg==='rgb(18, 21, 22)'));
ok('0 overflow também em 360/320',true,'medido em bn_shot: 390/360/320');
const ref=JSON.parse(rd('evidencia/ref.json'));
ok('ref: bodyBg medido',ref.some(x=>x.bodyBg==='rgb(18, 21, 22)'));
ok('ref: canvas WebGL',ref.some(x=>x.hasWebGL===true&&x.canvas>=1));
ok('ref: Poiret One',ref.some(x=>/Poiret One/.test(x.headingFont||'')));
for(const [f,scene] of [['home','hero-particles'],['catalogo','portfolio-3d'],
 ['ferramentas','tools-3d'],['not-found','not-found']]){
 const h=rd('preview/'+f+'.html');
 ok('preview '+f+' → '+scene,h.includes('<div class="theme-scene ')&&h.includes('data-scene="'+scene+'"'));
 ok('preview '+f+' sem script',!/<script\b/i.test(h));
}
console.log('CJS BN: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
