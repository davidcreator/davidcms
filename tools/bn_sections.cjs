/* BN — captura de fases em posicoes de scroll (conteudo e scroll-driven) */
const {chromium}=require('/home/user/browser/node_modules/playwright');
const fs=require('fs');
const EXE='/home/user/.pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';
(async()=>{
 const b=await chromium.launch({headless:true,executablePath:EXE,
  args:['--no-sandbox','--disable-dev-shm-usage','--use-gl=swiftshader','--enable-webgl']});
 const ctx=await b.newContext({viewport:{width:1440,height:900},deviceScaleFactor:1});
 const p=await ctx.newPage();
 await p.goto('https://davidalmeida.xyz/threejs/?lang=pt',{waitUntil:'domcontentloaded',timeout:60000});
 await p.waitForTimeout(10000);
 const ys=[1600,3300,5600,8200,10800,12800];const out=[];
 for(let i=0;i<ys.length;i++){
  await p.evaluate(y=>window.scrollTo({top:y,behavior:'instant'}),ys[i]);
  await p.waitForTimeout(1600);
  const f='/tmp/bn_sec_'+String(i+1).padStart(2,'0')+'.png';
  await p.screenshot({path:f});
  const info=await p.evaluate(()=>({
   y:Math.round(window.scrollY),
   visibleH2:[...document.querySelectorAll('h2')].filter(e=>{const r=e.getBoundingClientRect();return r.top<900&&r.bottom>0;}).map(e=>e.innerText.trim()).slice(0,3),
   visibleH3:[...document.querySelectorAll('h3')].filter(e=>{const r=e.getBoundingClientRect();return r.top<900&&r.bottom>0;}).map(e=>e.innerText.trim()).slice(0,6),
  }));
  out.push({...info,file:f,bytes:fs.statSync(f).size});
 }
 await b.close();fs.writeFileSync('/tmp/bn_sections.json',JSON.stringify(out,null,2)+'\n');
 console.log(JSON.stringify(out,null,2));
})().catch(e=>{console.error(e);process.exit(1)});
