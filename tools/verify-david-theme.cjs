/* Frente BJ — verificador do tema David Creator (davidalmeida.xyz). Node puro.
   Uso: node tools/verify-david-theme.cjs [raiz] */
'use strict';
const fs=require('fs'),path=require('path'),crypto=require('crypto');
const ROOT=process.argv[2]||process.cwd();
let C=0,F=0;
function ok(l,c,x=''){c?C++:F++;console.log((c?'OK   ':'FAIL ')+l+(x?' · '+x:''));}
const ex=p=>fs.existsSync(path.join(ROOT,p));
const rd=p=>fs.readFileSync(path.join(ROOT,p),'utf8');
const PKG='docs/temas/David-Creator.reamurtheme.json';
ok('pacote presente',ex(PKG));
const raw=rd(PKG);let p;
try{p=JSON.parse(raw);ok('JSON valido',true);}catch(e){ok('JSON valido',false,e.message);console.log('CJS BJ: '+C+' OK / '+(F+1)+' FALHAS');process.exit(1);}

/* 1. Envelope + checksum canonico. */
ok('format/version',p.format==='reamurcms-theme'&&p.version===1);
ok('name/slug',p.name==='David Creator'&&/^[a-z0-9_-]{1,48}$/.test(p.slug));
ok('parent vazio / source draft',p.parent===''&&p.meta&&p.meta.source==='draft');
ok('checksum 64 hex',/^[0-9a-f]{64}$/.test(p.checksum||''));
function canon(v){if(Array.isArray(v))return v.map(canon);if(v&&typeof v==='object'){const o={};Object.keys(v).sort().forEach(k=>{o[k]=canon(v[k]);});return o;}return v;}
ok('checksum canonico confere',crypto.createHash('sha256').update(JSON.stringify(canon(p.config)),'utf8').digest('hex')===p.checksum);
ok('<=512 KiB',Buffer.byteLength(raw,'utf8')<=524288,Buffer.byteLength(raw,'utf8')+' bytes');

/* 2. Identidade do site real. */
const KEYS=['common/home','common/footer','common/contact','partial/site_header','partial/hero','partial/hero-3d',
 'partial/about','partial/methodology','partial/skills','partial/services','partial/testimonials','partial/portfolio',
 'partial/tools','partial/news','partial/clients','partial/contact','cms/page_info','catalog/project_list',
 'catalog/project_info','blog/blog_list','blog/blog_info','tool/tool_list','tool/tool_info','checkout/quote','error/not_found'];
const CONFIG_KEYS=['primary','surface','scene','templates','conditions','three','tokens','parent','sections','components','schedule','media','sites','kaz_scene'];
const cfg=p.config,tpl=cfg.templates;
ok('somente chaves allowlistadas',Object.keys(cfg).every(k=>CONFIG_KEYS.includes(k)));
ok('25 fontes = TEMPLATE_KEYS',Object.keys(tpl).length===25&&JSON.stringify(Object.keys(tpl).slice().sort())===JSON.stringify(KEYS.slice().sort()));
ok('acento ciano #5cc6d0',cfg.primary==='#5cc6d0');
ok('superficie escura #1b1f24',cfg.surface==='#1b1f24');
ok('scene/three ligados',cfg.scene==='hero-particles'&&cfg.three.enabled===true
   &&cfg.three.scene==='hero-particles'&&cfg.three.slot==='hero'
   &&cfg.three.color==='#5cc6d0'&&cfg.three.count>=50&&cfg.three.count<=800);
ok('tokens 22 campos AA',Object.keys(cfg.tokens).length===22&&cfg.tokens.heading_weight!=='200');
ok('conditions base limpa',cfg.conditions.version===1&&cfg.conditions.rules.length===0);
ok('kaz_scene v1 valido',cfg.kaz_scene.version===1&&cfg.kaz_scene.layers.length>=3);

/* 3. CSS proprio embutido. */
const css=(tpl['partial/site_header'].match(/<style>([\s\S]*?)<\/style>/)||[])[1]||'';
ok('folha de estilo >14 KB',css.length>14000,css.length+' bytes');
ok('CSS usa tokens do tema',css.includes('--theme-primary')&&css.includes('--theme-surface'));
ok('CSS cosmico/escuro',css.includes('radial-gradient')&&css.includes('#1b1f24'));
ok('CSS tipografia display',css.includes('text-transform:uppercase')&&css.includes('letter-spacing'));
ok('CSS responsivo 980/640',css.includes('@media(max-width:980px)')&&css.includes('@media(max-width:640px)'));
ok('CSS foco e reduced-motion',css.includes(':focus-visible')&&css.includes('prefers-reduced-motion'));
ok('CSS sem !important/@import/CDN/--bs-/--rms-',
   !/!important|@import|https?:\/\/|@font-face|--bs-|--rms-/.test(css));
ok('header semantico com nav',/dj-header/.test(tpl['partial/site_header'])&&/aria-label/.test(tpl['partial/site_header']));
ok('header respeita only',!/theme\./.test(tpl['partial/site_header']));
ok('footer semantico fecha documento',/<footer/.test(tpl['common/footer'])&&/<\/footer>/.test(tpl['common/footer'])
   &&tpl['common/footer'].includes('</body>')&&tpl['common/footer'].includes('</html>'));

/* 4. Conteudo transcrito de davidalmeida.xyz. */
const all=Object.values(tpl).join('\n');
const NEEDLES=["I'm",'L. Almeida','Creative Developer','As a passionate designer and developer',
 'Knowing the Client and the Company','Delivery','Graphic Design','Web Design','SEO Audit',
 'Silvio Robson B. Lima','Bruno Reis','Hiperion Gamer','Steel of Death','Selva Dev',
 'Fluxa','Quotia','Vertex','Solis','BDRProPlayer','Horror na Veia',
 'contato@davidalmeida.xyz','São Paulo','RedBubble','© 2025 All rights reserved',
 'What it looks like?','Connected tools','Beloved clients','Latest news','Get in touch'];
ok('textos reais do site ('+NEEDLES.length+')',NEEDLES.every(s=>all.includes(s)),
   NEEDLES.filter(s=>!all.includes(s)).join(','));
ok('rotas com header/footer',(all.match(/\{\{ header \}\}/g)||[]).length>=10&&(all.match(/\{\{ footer \}\}/g)||[]).length>=10);
ok('componentes do site',['dj-header','dj-hero','dj-section','dj-card','dj-grid','dj-btn',
 'dj-cta','dj-footer','dj-quote','dj-work','dj-tool','dj-step'].every(s=>all.includes(s)));
ok('SVG inline',(all.match(/<svg/g)||[]).length>=8,(String((all.match(/<svg/g)||[]).length)));
ok('textos nunca vazios',Object.values(tpl).every(v=>v.trim().length>80));
ok('nenhuma fonte >131072',Object.values(tpl).every(v=>Buffer.byteLength(v,'utf8')<=131072));

/* 5. Seguranca. */
const BAD=/<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=|\|\s*raw\b|\{%\s*autoescape\b|\b(srcdoc|formaction)\s*=/i;
ok('nenhuma fonte com conteudo proibido',Object.values(tpl).every(v=>!BAD.test(v)),
   Object.entries(tpl).filter(([,v])=>BAD.test(v)).map(([k])=>k).join(','));
ok('sem |raw / <script / @import / !important',!all.includes('|raw')&&!/<script/i.test(all)
   &&!all.includes('@import')&&!/!important/i.test(all));
ok('sem redefinir --bs-* / --rms-*',!all.includes('--bs-')&&!all.includes('--rms-'));
ok('Twig balanceado por fonte',Object.entries(tpl).every(([k,v])=>{
  const a=(v.match(/{%\s*if\b/g)||[]).length,b=(v.match(/{%\s*endif\s*%}/g)||[]).length;
  const c=(v.match(/{%\s*for\b/g)||[]).length,d=(v.match(/{%\s*endfor\s*%}/g)||[]).length;
  if(a!==b||c!==d){console.log('     desbalanceado: '+k);return false;}return true;}));

/* 6. Previa renderizada. */
ok('preview.html presente',ex('docs/temas/preview.html'));
const prev=rd('docs/temas/preview.html');
ok('preview sem diretivas Twig',!prev.includes('{{')&&!prev.includes('{%'));
ok('preview usa o MESMO CSS',prev.includes(css.trim()));
ok('preview tem header/footer/CTA/hero',prev.includes('dj-header')&&prev.includes('<footer')
   &&prev.includes('dj-cta')&&prev.includes('dj-hero-name'));
ok('preview desktop presente',ex('docs/temas/preview-desktop.png'));
ok('preview mobile presente',ex('docs/temas/preview-mobile.png'));
ok('evidencia de layout presente',ex('evidencia/layout-check.json'));
const L=JSON.parse(rd('evidencia/layout-check.json'));
ok('desktop 1440 sem overflow',L.some(x=>x.name==='desktop'&&x.innerWidth===1440&&x.scrollWidth===1440&&!x.overflow));
ok('mobile 390 sem overflow',L.some(x=>x.name==='mobile'&&x.innerWidth===390&&x.scrollWidth===390&&!x.overflow));
ok('fundo escuro e acento ciano',L.every(x=>x.bodyBg==='rgb(27, 31, 36)'&&x.accent==='rgb(92, 198, 208)'));
ok('hero DAVID / L. ALMEIDA',L.every(x=>x.heroName==='DAVID'&&x.heroLast==='L. ALMEIDA'));
ok('11 secoes / >=30 cards',L.every(x=>x.sections>=11&&x.cards>=30));

/* 7. Documentacao e governanca. */
ok('guia presente',ex('docs/temas/GUIA-TEMA-DAVID.md'));
ok('relatorio presente',ex('RELATORIO-FINAL-BJ.md'));ok('indice presente',ex('INDICE-BJ.md'));
const docs=rd('docs/temas/GUIA-TEMA-DAVID.md')+rd('RELATORIO-FINAL-BJ.md')+rd('INDICE-BJ.md');
ok('explica como importar',/[Ii]mportar tema|bot[aã]o Importar/.test(docs));
ok('cita a fonte davidalmeida.xyz',/davidalmeida\.xyz/.test(docs));
ok('explica limite do CSS embutido',/CSS embutido|<style>/.test(docs));
ok('sem codigo executavel',/sem c[oó]digo execut[aá]vel|dados validados/.test(docs));
ok('sem migration',/[Nn]enhuma migration|sem migration/.test(docs));
ok('nao afirma release',/n[aã]o\s+significam?\s+release\s+aprovada/i.test(docs));
ok('release_approved false',/release_approved:\s*false/.test(docs));
ok('F9-27 preservado',/BLOCKED_NOT_RUN/.test(docs));
ok('F9-18 preservado',/release_ready:\s*false/.test(docs));
ok('10 PENDING_DECISION',/10\s*`?PENDING_DECISION`?/.test(docs));
ok('sem escrita no GitHub',/escrita (do agente )?no GitHub/i.test(docs));
console.log('CJS BJ: '+C+' OK / '+F+' FALHAS');
process.exit(F?1:0);
