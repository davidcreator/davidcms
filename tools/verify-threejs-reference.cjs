/* Frente BH — verificador do tema de referencia ThreeJS. Node puro.
   Uso: node tools/verify-threejs-reference.cjs [raiz] */
'use strict';
const fs=require('fs'),path=require('path'),crypto=require('crypto');
const ROOT=process.argv[2]||process.cwd();
let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(ROOT,p));
const rd=p=>fs.readFileSync(path.join(ROOT,p),'utf8');
const PKG='docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json';
ok('pacote presente',ex(PKG));
const raw=rd(PKG);let p;
try{p=JSON.parse(raw);ok('JSON valido',true);}catch(e){ok('JSON valido',false,e.message);console.log('CJS BH: '+C+' OK / '+(F+1)+' FALHAS');process.exit(1);}

/* 1. Envelope + checksum canonico independente. */
ok('format/version',p.format==='reamurcms-theme'&&p.version===1);
ok('name/slug',p.name==='Reamur ThreeJS Reference'&&/^[a-z0-9_-]{1,48}$/.test(p.slug));
ok('parent vazio',p.parent==='');
ok('meta.source=draft',p.meta&&p.meta.source==='draft');
ok('checksum 64 hex',/^[0-9a-f]{64}$/.test(p.checksum||''));
function canon(v){if(Array.isArray(v))return v.map(canon);if(v&&typeof v==='object'){const o={};Object.keys(v).sort().forEach(k=>{o[k]=canon(v[k]);});return o;}return v;}
ok('checksum canonico confere',crypto.createHash('sha256').update(JSON.stringify(canon(p.config)),'utf8').digest('hex')===p.checksum);
ok('<=512 KiB',Buffer.byteLength(raw,'utf8')<=524288,Buffer.byteLength(raw,'utf8')+' bytes');

/* 2. Allowlists do config. */
const KEYS=['common/home','common/footer','common/contact','partial/site_header','partial/hero','partial/hero-3d',
 'partial/about','partial/methodology','partial/skills','partial/services','partial/testimonials','partial/portfolio',
 'partial/tools','partial/news','partial/clients','partial/contact','cms/page_info','catalog/project_list',
 'catalog/project_info','blog/blog_list','blog/blog_info','tool/tool_list','tool/tool_info','checkout/quote','error/not_found'];
const CONFIG_KEYS=['primary','surface','scene','templates','conditions','three','tokens','parent','sections','components','schedule','media','sites','kaz_scene'];
const cfg=p.config;
ok('config somente chaves allowlistadas',Object.keys(cfg).every(k=>CONFIG_KEYS.includes(k)));
ok('primary/surface hex',/^#[0-9a-fA-F]{6}$/.test(cfg.primary)&&/^#[0-9a-fA-F]{6}$/.test(cfg.surface));
const SCENES=['','hero-particles','portfolio-3d','tools-3d','not-found'],SLOTS=['hero','home_top','footer_top'];
ok('scene allowlistada',SCENES.includes(cfg.scene));

/* 3. Os 6 presets reais. */
const PRESETS={
 aurora:    {enabled:true, scene:'hero-particles',slot:'hero',      count:400,color:'#8a4fff',speed:0.05},
 nebula:    {enabled:true, scene:'hero-particles',slot:'hero',      count:800,color:'#6ea8fe',speed:0.12},
 geometric: {enabled:true, scene:'portfolio-3d',  slot:'home_top',  count:500,color:'#22d3ee',speed:0.06},
 workshop:  {enabled:true, scene:'tools-3d',      slot:'home_top',  count:350,color:'#f59e0b',speed:0.08},
 lost:      {enabled:true, scene:'not-found',     slot:'footer_top',count:200,color:'#94a3b8',speed:0.04},
 off:       {enabled:false,scene:'',              slot:'hero',      count:400,color:'#8a4fff',speed:0.05}};
function same(a,b){return JSON.stringify(canon(a))===JSON.stringify(canon(b));}
ok('three base = preset aurora',same(cfg.three,PRESETS.aurora));
ok('three base allowlistado',SCENES.includes(cfg.three.scene)&&SLOTS.includes(cfg.three.slot)
   &&cfg.three.count>=50&&cfg.three.count<=800&&cfg.three.speed>=0.01&&cfg.three.speed<=0.20);
const MAP={'home-aurora':'aurora','portfolio-geometric':'geometric','tools-workshop':'workshop',
           'notfound-lost':'lost','variant-nebula':'nebula','mobile-off':'off'};
ok('6 regras condicionais',cfg.conditions.version===1&&cfg.conditions.rules.length===6,String(cfg.conditions.rules.length));
ok('ids das 6 regras',JSON.stringify(cfg.conditions.rules.map(r=>r.id).sort())===JSON.stringify(Object.keys(MAP).sort()));
for(const r of cfg.conditions.rules){
  ok('regra '+r.id+' -> preset '+MAP[r.id],same(r.then.three,PRESETS[MAP[r.id]]));
  ok('regra '+r.id+' three allowlistado',SCENES.includes(r.then.three.scene)&&SLOTS.includes(r.then.three.slot));
}
ok('todos os 6 presets cobertos',new Set(cfg.conditions.rules.map(r=>MAP[r.id])).size===6);
ok('3 slots demonstrados',new Set([cfg.three.slot,...cfg.conditions.rules.map(r=>r.then.three.slot)]).size===3);
ok('4 cenas allowlistadas demonstradas',new Set([cfg.three.scene,...cfg.conditions.rules.map(r=>r.then.three.scene)].filter(Boolean)).size===4);
ok('regras usam vars/flags de cena',cfg.conditions.rules.every(r=>r.then.vars&&r.then.vars.scene_preset&&Array.isArray(r.then.flags)));

/* 4. kaz_scene completo. */
const ks=cfg.kaz_scene;
ok('kaz_scene version=1',ks.version===1);
ok('canvas 960x540 grid 8',ks.canvas.w===960&&ks.canvas.h===540&&ks.canvas.grid===8);
ok('24 camadas (<=48)',ks.layers.length===24&&ks.layers.length<=48,String(ks.layers.length));
const KINDS=['rect','text','button','card','section'],ROLES=['none','primary','surface','section','component'];
ok('5 kinds cobertos',new Set(ks.layers.map(l=>l.kind)).size===5&&ks.layers.every(l=>KINDS.includes(l.kind)));
ok('5 roles cobertos',new Set(ks.layers.map(l=>l.role)).size===5&&ks.layers.every(l=>ROLES.includes(l.role)));
ok('ids unicos e validos',new Set(ks.layers.map(l=>l.id)).size===ks.layers.length&&ks.layers.every(l=>/^[a-z0-9_-]{1,48}$/.test(l.id)));
ok('cores hex',ks.layers.every(l=>/^#[0-9a-fA-F]{6}$/.test(l.fill)));
ok('numeros nos limites',ks.layers.every(l=>l.x>=-2000&&l.x<=2000&&l.y>=-2000&&l.y<=2000&&l.w>=8&&l.w<=960
   &&l.h>=8&&l.h<=540&&l.radius>=0&&l.radius<=64&&l.fontSize>=8&&l.fontSize<=72));
ok('textos seguros',ks.layers.every(l=>typeof l.text==='string'&&l.text.length<=80&&!/(\{\{|\{%)|javascript\s*:|<script/i.test(l.text)));
ok('visible/locked booleanos',ks.layers.every(l=>typeof l.visible==='boolean'&&typeof l.locked==='boolean'));
ok('bindIds demonstrados',ks.layers.filter(l=>l.bindId!=='').length>=4);
ok('kaz_scene <=64 KiB',Buffer.byteLength(JSON.stringify(ks),'utf8')<=65536);

/* 5. 25 fontes Twig + integracao com os dois mecanismos 3D. */
const tpl=cfg.templates;
ok('25 fontes Twig',Object.keys(tpl).length===25);
ok('chaves = TEMPLATE_KEYS',JSON.stringify(Object.keys(tpl).slice().sort())===JSON.stringify(KEYS.slice().sort()));
const BAD=/<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=/i;
ok('nenhuma fonte com conteudo proibido',Object.values(tpl).every(v=>!BAD.test(v)),
   Object.entries(tpl).filter(([,v])=>BAD.test(v)).map(([k])=>k).join(','));
ok('nenhuma fonte vazia',Object.values(tpl).every(v=>v.trim().length>80));
ok('nenhuma fonte >131072 bytes',Object.values(tpl).every(v=>Buffer.byteLength(v,'utf8')<=131072));
ok('sem |raw / <script / CDN / !important / --bs-*',
   Object.values(tpl).every(v=>!v.includes('|raw')&&!/<script/i.test(v)&&!/@import|https?:\/\/|cdn\./i.test(v)
     &&!/!important/i.test(v)&&!/--bs-|--rms-/.test(v)));
ok('usa |default defensivamente',Object.values(tpl).every(v=>v.includes('|default')));
ok('templates de rota com {{ header }}',['common/home','common/contact','cms/page_info','blog/blog_list','error/not_found']
   .every(k=>tpl[k].includes('{{ header }}')));
ok('footer fecha o documento',tpl['common/footer'].includes('</body>')&&tpl['common/footer'].includes('</html>'));
ok('runtime theme_scene incluido',Object.values(tpl).filter(v=>v.includes("partial/theme-scene.twig")).length>=6,
   String(Object.values(tpl).filter(v=>v.includes("partial/theme-scene.twig")).length));
ok('composicao theme-scene-block incluida',tpl['common/home'].includes('partial/theme-scene-block.twig'));
ok('globais theme_scene / theme.vars / theme.flags usados',
   Object.values(tpl).some(v=>v.includes('theme_scene'))&&Object.values(tpl).some(v=>v.includes('theme.vars'))
   &&Object.values(tpl).some(v=>v.includes('theme.flags')));
ok('3 slots referenciados nos templates',['hero','home_top','footer_top'].every(s=>Object.values(tpl).some(v=>v.includes("'"+s+"'"))));
ok('classes proprias thr-*',Object.values(tpl).every(v=>v.includes('thr-')));
ok('Twig balanceado por fonte',Object.entries(tpl).every(([k,v])=>{
  const a=(v.match(/{%\s*if\b/g)||[]).length,b=(v.match(/{%\s*endif\s*%}/g)||[]).length;
  const c=(v.match(/{%\s*for\b/g)||[]).length,d=(v.match(/{%\s*endfor\s*%}/g)||[]).length;
  if(a!==b||c!==d){console.log('     desbalanceado: '+k);return false;}return true;
}));
ok('tokens 22 campos',Object.keys(cfg.tokens).length===22);

/* 6. Documentacao e governanca. */
ok('guia presente',ex('docs/temas/GUIA-TEMA-THREEJS-REFERENCIA.md'));
ok('relatorio presente',ex('RELATORIO-FINAL-BH.md'));ok('indice presente',ex('INDICE-BH.md'));
const docs=rd('docs/temas/GUIA-TEMA-THREEJS-REFERENCIA.md')+rd('RELATORIO-FINAL-BH.md')+rd('INDICE-BH.md');
ok('explica como importar',/[Ii]mportar tema|bot[aã]o Importar/.test(docs));
ok('registra decode() real',/decode\(\)/.test(docs));
ok('sem codigo executavel',/sem c[oó]digo execut[aá]vel|dados validados/.test(docs));
ok('sem migration',/[Nn]enhuma migration|sem migration/.test(docs));
ok('nao afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));
ok('release_approved false',/release_approved:\s*false/.test(docs));
ok('F9-27 preservado',/BLOCKED_NOT_RUN/.test(docs));
ok('F9-18 preservado',/release_ready:\s*false/.test(docs));
ok('10 PENDING_DECISION',/10\s*`?PENDING_DECISION`?/.test(docs));
ok('sem escrita no GitHub',/escrita (do agente )?no GitHub/i.test(docs));
console.log('CJS BH: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
