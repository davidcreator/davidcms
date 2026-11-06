/* Frente BG — verificador do tema de referencia. Node puro, somente leitura.
   Uso: node tools/verify-reference-theme.cjs [raiz] */
'use strict';
const fs=require('fs'),path=require('path'),crypto=require('crypto');
const ROOT=process.argv[2]||process.cwd();
let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(ROOT,p));
const rd=p=>fs.readFileSync(path.join(ROOT,p),'utf8');
const PKG='docs/temas/Reamur-Reference.reamurtheme.json';
ok('pacote presente',ex(PKG));
const raw=rd(PKG);let p;
try{p=JSON.parse(raw);ok('JSON valido',true);}catch(e){ok('JSON valido',false,e.message);console.log('CJS BG: '+C+' OK / '+(F+1)+' FALHAS');process.exit(1);}

/* 1. Identidade e envelope do pacote (ThemePackage::FORMAT/VERSION). */
ok('format = reamurcms-theme',p.format==='reamurcms-theme',p.format);
ok('version = 1',p.version===1,String(p.version));
ok('name / slug',p.name==='Reamur Reference'&&p.slug==='reamur_reference');
ok('slug no padrao',/^[a-z0-9_-]{1,48}$/.test(p.slug));
ok('parent vazio (tema base)',p.parent==='');
ok('meta.source = draft',p.meta&&p.meta.source==='draft');
ok('checksum 64 hex',typeof p.checksum==='string'&&/^[0-9a-f]{64}$/.test(p.checksum));

/* 2. Checksum canonico recalculado de forma independente. */
function canon(v){
  if(Array.isArray(v))return v.map(canon);
  if(v&&typeof v==='object'){const o={};Object.keys(v).sort().forEach(k=>{o[k]=canon(v[k]);});return o;}
  return v;
}
const sum=crypto.createHash('sha256').update(JSON.stringify(canon(p.config)),'utf8').digest('hex');
ok('checksum SHA-256 canonico confere',sum===p.checksum,sum.slice(0,16));
ok('tamanho <= 512 KiB',Buffer.byteLength(raw,'utf8')<=524288,Buffer.byteLength(raw,'utf8')+' bytes');

/* 3. Config allowlistado. */
const KEYS=['common/home','common/footer','common/contact','partial/site_header','partial/hero','partial/hero-3d',
 'partial/about','partial/methodology','partial/skills','partial/services','partial/testimonials','partial/portfolio',
 'partial/tools','partial/news','partial/clients','partial/contact','cms/page_info','catalog/project_list',
 'catalog/project_info','blog/blog_list','blog/blog_info','tool/tool_list','tool/tool_info','checkout/quote','error/not_found'];
const CONFIG_KEYS=['primary','surface','scene','templates','conditions','three','tokens','parent','sections','components','schedule','media','sites','kaz_scene'];
const cfg=p.config;
ok('config e objeto',cfg&&typeof cfg==='object');
ok('nenhuma chave fora da allowlist',Object.keys(cfg).every(k=>CONFIG_KEYS.includes(k)));
ok('primary / surface hex',/^#[0-9a-fA-F]{6}$/.test(cfg.primary)&&/^#[0-9a-fA-F]{6}$/.test(cfg.surface));
ok('scene allowlistada',['','hero-particles','portfolio-3d','tools-3d','not-found'].includes(cfg.scene));
ok('conditions version=1 sem regras',cfg.conditions&&cfg.conditions.version===1&&Array.isArray(cfg.conditions.rules)&&cfg.conditions.rules.length===0);
ok('three allowlistado',cfg.three&&cfg.three.enabled===true&&cfg.three.scene==='hero-particles'
   &&['hero','home_top','footer_top'].includes(cfg.three.slot)&&cfg.three.count>=50&&cfg.three.count<=800
   &&cfg.three.speed>=0.01&&cfg.three.speed<=0.20&&/^#[0-9a-fA-F]{6}$/.test(cfg.three.color));

/* 4. 25 fontes Twig — completas, seguras e nao vazias. */
const tpl=cfg.templates;
ok('25 fontes Twig',Object.keys(tpl).length===25,String(Object.keys(tpl).length));
ok('chaves exatamente TEMPLATE_KEYS',JSON.stringify(Object.keys(tpl).slice().sort())===JSON.stringify(KEYS.slice().sort()));
const BAD=/<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=/i;
ok('nenhuma fonte com conteudo proibido',Object.values(tpl).every(v=>!BAD.test(v)),
   Object.entries(tpl).filter(([,v])=>BAD.test(v)).map(([k])=>k).join(','));
ok('nenhuma fonte vazia',Object.values(tpl).every(v=>typeof v==='string'&&v.trim().length>80));
ok('nenhuma fonte > 131072 bytes',Object.values(tpl).every(v=>Buffer.byteLength(v,'utf8')<=131072));
ok('nenhum |raw',!Object.values(tpl).some(v=>v.includes('|raw')));
ok('nenhum <script',!Object.values(tpl).some(v=>/<script/i.test(v)));
ok('nenhuma fonte externa / CDN',!Object.values(tpl).some(v=>/@import|https?:\/\/|fonts\.googleapis|cdn\./i.test(v)));
ok('sem !important',!Object.values(tpl).some(v=>/!important/i.test(v)));
ok('sem redefinicao --bs-* / --rms-*',!Object.values(tpl).some(v=>/--bs-|--rms-/.test(v)));
ok('usa |default defensivamente',Object.values(tpl).every(v=>v.includes('|default')));
ok('templates de rota incluem header',['common/home','common/contact','cms/page_info','blog/blog_list','error/not_found']
   .every(k=>tpl[k].includes('{{ header }}')));
ok('footer fecha o documento',tpl['common/footer'].includes('</body>')&&tpl['common/footer'].includes('</html>'));
ok('preserva ThreeJS via includes',tpl['common/home'].includes('partial/theme-scene')
   &&tpl['common/footer'].includes('partial/theme-scene'));
ok('classes proprias ref-*',Object.values(tpl).every(v=>v.includes('ref-')));
ok('Twig if/for balanceados por fonte',Object.entries(tpl).every(([k,v])=>{
  const a=(v.match(/{%\s*if\b/g)||[]).length,b=(v.match(/{%\s*endif\s*%}/g)||[]).length;
  const c=(v.match(/{%\s*for\b/g)||[]).length,d=(v.match(/{%\s*endfor\s*%}/g)||[]).length;
  if(a!==b||c!==d){console.log('     desbalanceado: '+k);return false;}return true;
}));

/* 5. kaz_scene allowlistado. */
const ks=cfg.kaz_scene;
ok('kaz_scene version=1',ks&&ks.version===1);
ok('canvas allowlistado',ks.canvas&&ks.canvas.w>=320&&ks.canvas.w<=3840&&ks.canvas.h>=240&&ks.canvas.h<=2160&&ks.canvas.grid===8);
ok('layers <= 48',Array.isArray(ks.layers)&&ks.layers.length<=48,String(ks.layers.length));
const KINDS=['rect','text','button','card','section'],ROLES=['none','primary','surface','section','component'];
ok('kinds/roles allowlistados',ks.layers.every(l=>KINDS.includes(l.kind)&&ROLES.includes(l.role)));
ok('ids unicos e validos',new Set(ks.layers.map(l=>l.id)).size===ks.layers.length
   &&ks.layers.every(l=>/^[a-z0-9_-]{1,48}$/.test(l.id)));
ok('textos sem codigo executavel',ks.layers.every(l=>!/(\{\{|\{%)|javascript\s*:|<script/i.test(l.text)));
ok('cores hex nas layers',ks.layers.every(l=>/^#[0-9a-fA-F]{6}$/.test(l.fill)));

/* 6. Documentacao e governanca. */
ok('guia presente',ex('docs/temas/GUIA-TEMA-REFERENCIA.md'));
ok('relatorio presente',ex('RELATORIO-FINAL-BG.md'));ok('indice presente',ex('INDICE-BG.md'));
const docs=rd('docs/temas/GUIA-TEMA-REFERENCIA.md')+rd('RELATORIO-FINAL-BG.md')+rd('INDICE-BG.md');
ok('explica como importar',/[Ii]mportar tema|bot[aã]o Importar/.test(docs));
ok('registra decode() real',/decode\(\)/.test(docs));
ok('sem migration',/[Nn]enhuma migration|sem migration/.test(docs));
ok('sem codigo executavel',/sem c[oó]digo execut[aá]vel|dados validados/.test(docs));
ok('nao afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));
ok('release_approved false',/release_approved:\s*false/.test(docs));
ok('F9-27 preservado',/BLOCKED_NOT_RUN/.test(docs));
ok('F9-18 preservado',/release_ready:\s*false/.test(docs));
ok('10 PENDING_DECISION',/10\s*`?PENDING_DECISION`?/.test(docs));
ok('sem escrita no GitHub',/escrita (do agente )?no GitHub/i.test(docs));

console.log('CJS BG: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
