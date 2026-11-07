/* Frente BM — capturas reais (Chromium 141 via LD_LIBRARY_PATH). */
const {chromium}=require('/home/user/browser/node_modules/playwright');
const fs=require('fs');
const EXE='/home/user/.pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:EXE,
   args:['--no-sandbox','--disable-dev-shm-usage']});
 const targets=[
  {name:'home',      w:1440,h:1000,full:true, out:'/tmp/bm_shot_home.png'},
  {name:'home-mob',  w:390, h:844, full:false,out:'/tmp/bm_shot_home_mobile.png',page:'home'},
  {name:'projetos',  w:1440,h:1000,full:false,out:'/tmp/bm_shot_projetos.png'},
  {name:'not-found', w:1440,h:1000,full:false,out:'/tmp/bm_shot_not_found.png'},
 ];
 const report=[];
 for(const t of targets){
  const ctx=await browser.newContext({viewport:{width:t.w,height:t.h},deviceScaleFactor:1});
  const page=await ctx.newPage();const errs=[];
  page.on('pageerror',e=>errs.push(String(e)));
  await page.goto('file:///tmp/bm_previews/'+(t.page||t.name)+'.html',{waitUntil:'load'});
  const m=await page.evaluate(()=>{
    const q=s=>document.querySelector(s);
    const slot=q('.theme-scene[data-scene]');
    return {
      innerWidth:window.innerWidth,
      scrollWidth:document.documentElement.scrollWidth,
      scrollHeight:document.documentElement.scrollHeight,
      sections:document.querySelectorAll('section').length,
      atlasSections:document.querySelectorAll('.atlas-section').length,
      cards:document.querySelectorAll('.atlas-card').length,
      header:!!q('.atlas-header'),footer:!!q('.atlas-footer'),cta:!!q('.atlas-cta'),
      scene:slot?slot.getAttribute('data-scene'):null,
      slotClass:slot?slot.className:null,
      count:slot?slot.getAttribute('data-count'):null,
      color:slot?slot.getAttribute('data-color'):null,
      slots:document.querySelectorAll('.theme-scene[data-scene]').length,
      sceneHeight:slot?Math.round(slot.getBoundingClientRect().height):0,
      fallbackVisible:!!q('.theme-scene__fallback'),
      primaryBtnBg:q('.atlas-btn-primary')?getComputedStyle(q('.atlas-btn-primary')).backgroundColor:null,
      primaryBtnColor:q('.atlas-btn-primary')?getComputedStyle(q('.atlas-btn-primary')).color:null,
      scripts:document.querySelectorAll('script').length,
      inlineStyles:(document.documentElement.outerHTML.match(/\son[a-z]+\s*=/gi)||[]).length,
    };
  });
  m.overflow=m.scrollWidth>m.innerWidth;m.pageerrors=errs.length;
  await page.screenshot({path:t.out,fullPage:!!t.full});
  m.bytes=fs.statSync(t.out).size;m.name=t.name;
  report.push(m);await ctx.close();
 }
 await browser.close();
 fs.writeFileSync('/tmp/bm_layout_check.json',JSON.stringify(report,null,2)+'\n');
 console.log(JSON.stringify(report,null,2));
 const bad=report.some(r=>r.overflow||!r.header||!r.footer||!r.scene||r.slots!==1||r.pageerrors||r.scripts);
 if(bad)process.exit(1);
})().catch(e=>{console.error(e);process.exit(1)});
