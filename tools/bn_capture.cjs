/* Frente BN — captura REAL da amostra online davidalmeida.xyz/threejs/?lang=pt */
const {chromium}=require('/home/user/browser/node_modules/playwright');
const fs=require('fs');
const EXE='/home/user/.pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';
const URL='https://davidalmeida.xyz/threejs/?lang=pt';
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:EXE,
   args:['--no-sandbox','--disable-dev-shm-usage','--use-gl=swiftshader','--enable-webgl']});
 const out=[];
 for(const t of [{n:'desktop',w:1440,h:900,full:true},{n:'mobile',w:390,h:844,full:false}]){
  const ctx=await browser.newContext({viewport:{width:t.w,height:t.h},deviceScaleFactor:1,
    userAgent:'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'});
  const page=await ctx.newPage();const errs=[];
  page.on('pageerror',e=>errs.push(String(e).slice(0,180)));
  await page.goto(URL,{waitUntil:'domcontentloaded',timeout:60000});
  await page.waitForTimeout(11000);
  // rolar para disparar as fases/scroll-driven
  for(const y of [800,2200,4200,6500,9000,12000]){await page.evaluate(v=>window.scrollTo(0,v),y);await page.waitForTimeout(500);}
  await page.evaluate(()=>window.scrollTo(0,0));await page.waitForTimeout(1200);
  const m=await page.evaluate(()=>{
   const cs=n=>getComputedStyle(document.querySelector(n)||document.body);
   const txt=document.body.innerText;
   return {
    title:document.title,lang:document.documentElement.lang,
    bodyBg:cs('body').backgroundColor,bodyColor:cs('body').color,
    bodyFont:cs('body').fontFamily,headingFont:cs('h1')?getComputedStyle(document.querySelector('h1')).fontFamily:null,
    themeColor:(document.querySelector('meta[name="theme-color"]')||{}).content||null,
    scrollHeight:document.documentElement.scrollHeight,
    sections:document.querySelectorAll('section').length,
    h2:[...document.querySelectorAll('h2')].map(e=>e.innerText.trim()).slice(0,20),
    canvas:document.querySelectorAll('canvas').length,
    imgs:document.querySelectorAll('img').length,
    hasWebGL:!!document.querySelector('canvas'),
    phrases:['Carregando universo','Mova o mouse','Distância','Fase 08','Vamos conversar','Escolha o seu nível']
      .filter(s=>txt.includes(s)),
    links:[...document.querySelectorAll('a[href^="#"]')].length,
   };
  });
  m.name=t.n;m.innerWidth=t.w;m.pageerrors=errs.length;m.errSample=errs.slice(0,3);
  const shot='/tmp/bn_ref_'+t.n+'.png';
  await page.screenshot({path:shot,fullPage:!!t.full});
  m.bytes=fs.statSync(shot).size;m.shot=shot;
  out.push(m);await ctx.close();
 }
 // captura do topo (hero) separada, sem fullPage
 const ctx=await browser.newContext({viewport:{width:1440,height:900},deviceScaleFactor:1});
 const p=await ctx.newPage();await p.goto(URL,{waitUntil:'domcontentloaded',timeout:60000});
 await p.waitForTimeout(11000);await p.screenshot({path:'/tmp/bn_ref_hero.png'});
 await ctx.close();await browser.close();
 fs.writeFileSync('/tmp/bn_ref.json',JSON.stringify(out,null,2)+'\n');
 console.log(JSON.stringify(out,null,2));
})().catch(e=>{console.error(e);process.exit(1)});
