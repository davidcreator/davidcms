/* Frente BI — verificador do tema de site real (Atlas Studio). Node puro.
   Uso: node tools/verify-site-theme.cjs [raiz] */
'use strict';
const fs=require('fs'),path=require('path'),crypto=require('crypto');
const ROOT=process.argv[2]||process.cwd();
let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(ROOT,p));
const rd=p=>fs.readFileSync(path.join(ROOT,p),'utf8');
const PKG='docs/temas/Atlas-Studio.reamurtheme.json';
ok('pacote presente',ex(PKG));
const raw=rd(PKG);let p;
try{p=JSON.parse(raw);ok('JSON valido',true);}catch(e){ok('JSON valido',false,e.message);console.log('CJS BI: '+C+' OK / '+(F+1)+' FALHAS');process.exit(1);}

/* 1. Envelope + checksum canonico. */
ok('format/version',p.format==='reamurcms-theme'&&p.version===1);
ok('name/slug',p.name==='Atlas Studio'&&/^[a-z0-9_-]{1,48}$/.test(p.slug));
ok('parent vazio / source draft',p.parent===''&&p.meta&&p.meta.source==='draft');
ok('checksum 64 hex',/^[0-9a-f]{64}$/.test(p.checksum||''));
function canon(v){if(Array.isArray(v))return v.map(canon);if(v&&typeof v==='object'){const o={};Object.keys(v).sort().forEach(k=>{o[k]=canon(v[k]);});return o;}return v;}
ok('checksum canonico confere',crypto.createHash('sha256').update(JSON.stringify(canon(p.config)),'utf8').digest('hex')===p.checksum);
ok('<=512 KiB',Buffer.byteLength(raw,'utf8')<=524288,Buffer.byteLength(raw,'utf8')+' bytes');

/* 2. Config allowlistado. */
const KEYS=['common/home','common/footer','common/contact','partial/site_header','partial/hero','partial/hero-3d',
 'partial/about','partial/methodology','partial/skills','partial/services','partial/testimonials','partial/portfolio',
 'partial/tools','partial/news','partial/clients','partial/contact','cms/page_info','catalog/project_list',
 'catalog/project_info','blog/blog_list','blog/blog_info','tool/tool_list','tool/tool_info','checkout/quote','error/not_found'];
const CONFIG_KEYS=['primary','surface','scene','templates','conditions','three','tokens','parent','sections','components','schedule','media','sites','kaz_scene'];
const cfg=p.config,tpl=cfg.templates;
ok('somente chaves allowlistadas',Object.keys(cfg).every(k=>CONFIG_KEYS.includes(k)));
ok('primary/surface hex',/^#[0-9a-fA-F]{6}$/.test(cfg.primary)&&/^#[0-9a-fA-F]{6}$/.test(cfg.surface));
ok('25 fontes = TEMPLATE_KEYS',Object.keys(tpl).length===25&&JSON.stringify(Object.keys(tpl).slice().sort())===JSON.stringify(KEYS.slice().sort()));
ok('tokens 22 campos',Object.keys(cfg.tokens).length===22);
ok('three desligado (editorial)',cfg.three.enabled===false&&cfg.three.scene==='');
ok('conditions base limpa',cfg.conditions.version===1&&cfg.conditions.rules.length===0);

/* 3. E um SITE: chrome, CSS e conteudo. */
const css=(tpl['partial/site_header'].match(/<style>([\s\S]*?)<\/style>/)||[])[1]||'';
ok('folha de estilo embutida',css.length>12000,css.length+' bytes');
ok('CSS usa tokens do tema',css.includes('--theme-primary')&&css.includes('--theme-surface'));
ok('CSS responsivo 980/640',css.includes('@media(max-width:980px)')&&css.includes('@media(max-width:640px)'));
ok('CSS foco e reduced-motion',css.includes(':focus-visible')&&css.includes('prefers-reduced-motion'));
ok('CSS sem !important/@import/CDN/--bs-/--rms-',
   !/!important|@import|https?:\/\/|@font-face|--bs-|--rms-/.test(css));
ok('header semantico com nav',/<header/.test(tpl['partial/site_header'])&&/aria-label/.test(tpl['partial/site_header']));
ok('header usa apenas vars do only',!/theme\./.test(tpl['partial/site_header'])&&/text_brand/.test(tpl['partial/site_header']));
ok('footer semantico fecha documento',/<footer/.test(tpl['common/footer'])&&/<\/footer>/.test(tpl['common/footer'])
   &&tpl['common/footer'].includes('</body>')&&tpl['common/footer'].includes('</html>'));
ok('rodape em colunas',(tpl['common/footer'].match(/<h3>/g)||[]).length>=3&&tpl['common/footer'].includes('atlas-footer-grid'));
const all=Object.values(tpl).join('\n');
ok('rotas com header/footer',(all.match(/\{\{ header \}\}/g)||[]).length>=10&&(all.match(/\{\{ footer \}\}/g)||[]).length>=10);
ok('conteudo editorial real',['Ideias que viram produtos digitais','Solicitar proposta','Marina Ribeiro',
   'Aurora Finance','Vera Saude','Norte Log','Tecnica com repertorio'].every(s=>all.includes(s)));
ok('componentes de site',['atlas-header','atlas-hero','atlas-section','atlas-card','atlas-grid',
   'atlas-btn','atlas-cta','atlas-footer','atlas-quote','atlas-checklist'].every(s=>all.includes(s)));
ok('SVG inline sem imagens externas',(all.match(/<svg/g)||[]).length>=8&&!/<img[^>]+https?:/.test(all));
ok('aria-label / aria-hidden',all.includes('aria-label')&&all.includes('aria-hidden'));
ok('textos nunca vazios',Object.values(tpl).every(v=>v.trim().length>80));
ok('nenhuma fonte >131072',Object.values(tpl).every(v=>Buffer.byteLength(v,'utf8')<=131072));

/* 4. Seguranca. */
const BAD=/<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=/i;
ok('nenhuma fonte com conteudo proibido',Object.values(tpl).every(v=>!BAD.test(v)),
   Object.entries(tpl).filter(([,v])=>BAD.test(v)).map(([k])=>k).join(','));
ok('sem |raw / <script / CDN / !important',!all.includes('|raw')&&!/<script/i.test(all)
   &&!/@import|https?:\/\/|fonts\.googleapis|cdn\./i.test(all)&&!/!important/i.test(all));
ok('sem redefinir --bs-* / --rms-*',!all.includes('--bs-')&&!all.includes('--rms-'));
ok('Twig balanceado por fonte',Object.entries(tpl).every(([k,v])=>{
  const a=(v.match(/{%\s*if\b/g)||[]).length,b=(v.match(/{%\s*endif\s*%}/g)||[]).length;
  const c=(v.match(/{%\s*for\b/g)||[]).length,d=(v.match(/{%\s*endfor\s*%}/g)||[]).length;
  if(a!==b||c!==d){console.log('     desbalanceado: '+k);return false;}return true;}));

/* 5. Previa renderizada. */
ok('preview.html presente',ex('docs/temas/preview.html'));
const prev=rd('docs/temas/preview.html');
ok('preview nao contem diretivas Twig',!prev.includes('{{')&&!prev.includes('{%'));
ok('preview usa o MESMO CSS',prev.includes(css.trim()));
ok('preview tem header/footer/CTA',prev.includes('atlas-header')&&prev.includes('<footer')&&prev.includes('atlas-cta'));
ok('preview desktop presente',ex('docs/temas/preview-desktop.png'));
ok('preview mobile presente',ex('docs/temas/preview-mobile.png'));
ok('evidencia de layout presente',ex('evidencia/layout-check.json'));
const L=JSON.parse(rd('evidencia/layout-check.json'));
ok('layout desktop sem overflow',L.some(x=>x.name==='desktop'&&x.innerWidth===1440&&x.scrollWidth===1440&&!x.overflow));
ok('layout mobile sem overflow',L.some(x=>x.name==='mobile'&&x.innerWidth===390&&x.scrollWidth===390&&!x.overflow));
ok('botao primario legivel',L.every(x=>x.primaryBtnColor==='rgb(255, 255, 255)'&&x.primaryBtnBg==='rgb(67, 56, 202)'));
ok('9 secoes / 16 cards',L.every(x=>x.sections>=9&&x.cards>=16));

/* 6. Documentacao e governanca. */
ok('guia presente',ex('docs/temas/GUIA-TEMA-ATLAS.md'));
ok('relatorio presente',ex('RELATORIO-FINAL-BI.md'));ok('indice presente',ex('INDICE-BI.md'));
const docs=rd('docs/temas/GUIA-TEMA-ATLAS.md')+rd('RELATORIO-FINAL-BI.md')+rd('INDICE-BI.md');
ok('explica como importar',/[Ii]mportar tema|bot[aã]o Importar/.test(docs));
ok('explica limite do CSS embutido',/CSS embutido|folha de estilo|<style>/.test(docs));
ok('sem codigo executavel',/sem c[oó]digo execut[aá]vel|dados validados/.test(docs));
ok('sem migration',/[Nn]enhuma migration|sem migration/.test(docs));
ok('nao afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));
ok('release_approved false',/release_approved:\s*false/.test(docs));
ok('F9-27 preservado',/BLOCKED_NOT_RUN/.test(docs));
ok('F9-18 preservado',/release_ready:\s*false/.test(docs));
ok('10 PENDING_DECISION',/10\s*`?PENDING_DECISION`?/.test(docs));
ok('sem escrita no GitHub',/escrita (do agente )?no GitHub/i.test(docs));
console.log('CJS BI: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
