/* BN — capturas reais do tema + medicoes (Chromium 141 via LD_LIBRARY_PATH) */
const {chromium}=require('/home/user/browser/node_modules/playwright');const fs=require('fs');
const EXE='/home/user/.pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';
(async()=>{const b=await chromium.launch({headless:true,executablePath:EXE,args:['--no-sandbox','--disable-dev-shm-usage']});
const rep=[];
for(const t of [{n:'home',w:1440,h:900,full:true},{n:'home-mob',w:390,h:844,full:false,p:'home'},
 {n:'catalogo',w:1440,h:900,full:false},{n:'ferramentas',w:1440,h:900,full:false},{n:'not-found',w:1440,h:900,full:false}]){
 const ctx=await b.newContext({viewport:{width:t.w,height:t.h},deviceScaleFactor:1});const p=await ctx.newPage();const errs=[];
 p.on('pageerror',e=>errs.push(String(e)));
 await p.goto('file:///home/user/bn_previews/'+(t.p||t.n)+'.html',{waitUntil:'load'});
 const m=await p.evaluate(()=>{const q=s=>document.querySelector(s);const sl=q('.theme-scene[data-scene]');return{
 innerWidth,scrollWidth:document.documentElement.scrollWidth,scrollHeight:document.documentElement.scrollHeight,
 phases:document.querySelectorAll('.du-phase').length,cards:document.querySelectorAll('.du-card').length,
 levels:document.querySelectorAll('.du-level-card').length,seals:document.querySelectorAll('svg').length,
 hud:!!q('.du-hud'),footer:!!q('.du-footer'),hero:!!q('.du-hero'),form:!!q('.du-form'),
 scene:sl?sl.getAttribute('data-scene'):null,slots:document.querySelectorAll('.theme-scene[data-scene]').length,
 sceneHeight:sl?Math.round(sl.getBoundingClientRect().height):0,
 bodyBg:getComputedStyle(document.body).backgroundColor,titleColor:q('.du-overline')?getComputedStyle(q('.du-overline')).color:null,
 btnBg:q('.du-btn')?getComputedStyle(q('.du-btn')).backgroundColor:null,
 scripts:document.querySelectorAll('script').length,inline:(document.documentElement.outerHTML.match(/\son[a-z]+\s*=/gi)||[]).length};});
 m.overflow=m.scrollWidth>m.innerWidth;m.pageerrors=errs.length;
 const f='/home/user/bn_previews/shot-'+t.n+'.png';await p.screenshot({path:f,fullPage:!!t.full});
 m.name=t.n;m.bytes=fs.statSync(f).size;rep.push(m);await ctx.close();}
await b.close();fs.writeFileSync('/home/user/bn_previews/layout-bn.json',JSON.stringify(rep,null,2)+'\n');
console.log(JSON.stringify(rep.map(x=>({name:x.name,scene:x.scene,h:x.sceneHeight,phases:x.phases,cards:x.cards,levels:x.levels,bodyBg:x.bodyBg,btnBg:x.btnBg,overflow:x.overflow,scripts:x.scripts,pageerrors:x.pageerrors})),null,2));
if(rep.some(r=>r.overflow||r.pageerrors||r.scripts||r.slots!==1||!r.hud||!r.footer))process.exit(1);
})().catch(e=>{console.error(e);process.exit(1)});
