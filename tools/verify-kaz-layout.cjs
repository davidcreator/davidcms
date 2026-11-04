#!/usr/bin/env node
/* Frente AC — verificacao de layout em Chromium REAL (Playwright).
   Uso: node tools/verify-kaz-layout.cjs <url-admin> <url-catalog>
   Exige Playwright + Chromium; nao faz parte do runtime do CMS.
   Falha (exit 1) se houver overflow horizontal, canvas achatado, paineis
   empilhados no boot, sidebars sobrepostas ou scrolls aninhados demais. */
const {chromium}=require('playwright');
const ADMIN=process.argv[2],CATALOG=process.argv[3];
const VIEWS=[['wide',1920,1080],['desktop',1440,900],['laptop',1280,800],
             ['small',1024,768],['tablet',768,1024],['mobile',390,844]];
let P=0,F=0;
const ok=(n,c,x)=>{if(c)P++;else{F++;console.log('FAIL '+n+' '+String(x??''));}};
const workW=m=>m.center?m.center.w:9999;
const MEASURE=()=>{
  const b=s=>{const e=document.querySelector(s);if(!e)return null;const r=e.getBoundingClientRect();
    return {x:r.x,y:r.y,w:r.width,h:r.height,right:r.right,bottom:r.bottom,hidden:e.hidden,
      sh:e.scrollHeight,ch:e.clientHeight};};
  const nested=Array.from(document.querySelectorAll('*')).filter(e=>{
    const c=getComputedStyle(e);
    return (c.overflowY==='auto'||c.overflowY==='scroll')&&e.scrollHeight>e.clientHeight+4&&e.clientHeight>40;});
  return {iw:innerWidth,ih:innerHeight,docW:document.documentElement.scrollWidth,
    shell:b('#kaz-theme-editor'),work:b('[data-kaz-workspace]'),center:b('.kaz-center'),
    stage:b('[data-kaz-stage]'),screen:b('[data-kaz-screen-frame]'),svg:b('svg[data-kaz-canvas]'),
    left:b('.kaz-sidebar--left'),right:b('.kaz-sidebar--right'),
    sb:b('[data-kaz-screenbar]'),tabs:b('.kaz-module-tabs'),
    panVis:document.querySelectorAll('[data-kaz-module-panel]:not([hidden])').length,
    advOpen:document.querySelectorAll('details.kaz-advanced[open]').length,
    ready:document.getElementById('kaz-theme-editor').getAttribute('data-kaz-ready'),
    grid:getComputedStyle(document.querySelector('[data-kaz-workspace]')).gridTemplateColumns,
    nested:nested.length};
};
(async()=>{
  const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
  const page=await browser.newPage(); const errs=[];
  page.on('pageerror',e=>errs.push(e.message));
  for(const [name,w,h] of VIEWS){
    await page.setViewportSize({width:w,height:h}); errs.length=0;
    await page.goto(ADMIN,{waitUntil:'networkidle'});
    await page.waitForFunction(()=>document.getElementById('kaz-theme-editor')?.getAttribute('data-kaz-ready')==='1',{timeout:8000}).catch(()=>{});
    const m=await page.evaluate(MEASURE),t=name+' ';
    ok(t+'sem pageerror',errs.length===0,errs[0]);
    ok(t+'sem overflow horizontal',m.docW<=m.iw+2,m.docW+'/'+m.iw);
    ok(t+'shell cabe na viewport',m.shell.h<=m.ih+2,m.shell.h+'/'+m.ih);
    const minH=m.iw>980?200:100;
    ok(t+'canvas nao achatado',m.screen.h>=minH&&m.svg.h>=minH,m.screen.h+'/'+m.svg.h);
    ok(t+'aspect-ratio preservado',Math.abs(m.screen.w/m.screen.h-960/540)<0.06,
       (m.screen.w/m.screen.h).toFixed(3));
    if(m.iw<=980) ok(t+'canvas ajustado ao viewport',m.screen.w<=workW(m)+2,'');
    ok(t+'stage com altura util',m.stage.h>=250,m.stage.h);
    ok(t+'paineis escondidos no boot',m.panVis===0,m.panVis);
    ok(t+'avancado fechado',m.advOpen===0,m.advOpen);
    ok(t+'abas visiveis',m.tabs.h>=20,m.tabs.h);
    ok(t+'scrolls aninhados <=3',m.nested<=3,m.nested);
    ok(t+'screenbar fora do centro',m.sb.bottom<=m.center.y+2,m.sb.bottom+'/'+m.center.y);
    if(w>980) ok(t+'3 colunas sem sobreposicao',
      m.left.right<=m.center.x+2&&m.center.right<=m.right.x+2,
      m.left.right+'/'+m.center.x+'/'+m.center.right+'/'+m.right.x);
  }
  if(CATALOG) for(const [name,w,h] of VIEWS){
    await page.setViewportSize({width:w,height:h});
    await page.goto(CATALOG,{waitUntil:'networkidle'});
    const m=await page.evaluate(()=>{const s=document.querySelector('.rms-scene__stage');
      const r=s?.getBoundingClientRect();
      return {iw:innerWidth,docW:document.documentElement.scrollWidth,
        ratio:r?r.width/r.height:0,layers:document.querySelectorAll('.rms-scene__layer').length,
        vis:Array.from(document.querySelectorAll('.rms-scene__layer')).every(e=>{const b=e.getBoundingClientRect();return b.width>0&&b.height>0;})};});
    ok('catalogo/'+name+' sem overflow',m.docW<=m.iw+2,m.docW+'/'+m.iw);
    ok('catalogo/'+name+' aspect 960/540',Math.abs(m.ratio-960/540)<0.02,m.ratio);
    ok('catalogo/'+name+' camadas visiveis',m.layers>0&&m.vis,m.layers);
  }
  await browser.close();
  console.log('verify-kaz-layout: '+P+' OK / '+F+' FALHAS');
  process.exit(F?1:0);
})().catch(e=>{console.error(e);process.exit(2);});
