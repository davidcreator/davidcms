/* Frente AD — prova de boot com <script> no <head> SEM defer.
 * Uso: node tools/verify-kaz-boot.cjs <url-antes> <url-depois> [url-defer]
 * Exige Playwright. Sai com 1 se o "depois" nao inicializar. */
const { chromium } = require('playwright');
const A = process.argv[2] || 'http://127.0.0.1:8123/admin_head_ac.html';
const D = process.argv[3] || 'http://127.0.0.1:8123/admin_head_ad.html';
const F = process.argv[4] || 'http://127.0.0.1:8123/admin_deferred_ad.html';
let pass=0,fail=0;
const ok=(n,c,x)=>{ if(c){pass++;console.log('OK   '+n);}else{fail++;console.log('FAIL '+n+(x?' · '+x:''));} };
(async()=>{
  const b=await chromium.launch({headless:true,args:['--no-sandbox']});
  async function open(url){
    const p=await b.newPage({viewport:{width:1440,height:900}});
    const errs=[];
    p.on('pageerror',e=>errs.push('pageerror: '+e.message));
    p.on('console',m=>{ if(m.type()==='error') errs.push('console: '+m.text()); });
    await p.goto(url,{waitUntil:'networkidle'});
    await p.waitForTimeout(180);
    return {p,errs};
  }
  const state=p=>p.evaluate(()=>{
    const r=document.getElementById('kaz-theme-editor');
    return { ready:r?r.getAttribute('data-kaz-ready'):null,
             api:r?(typeof r.__kaz):null,
             saved:r?r.getAttribute('data-kaz-saved'):null,
             modules:document.querySelectorAll('[data-kaz-module]').length,
             panels:Array.from(document.querySelectorAll('[data-kaz-module-panel]')).filter(e=>!e.hidden).length };
  });

  /* --- ANTES: reproduz o sintoma relatado (console limpo, nada responde) --- */
  {
    const {p,errs}=await open(A);
    const s=await state(p);
    ok('ANTES boot NAO ocorreu', s.ready!=='1', 'ready='+s.ready);
    ok('ANTES __kaz ausente', s.api===undefined || s.api===null || s.api==='undefined', 'api='+s.api);
    ok('ANTES console limpo (sintoma)', errs.length===0, errs.join(' | ').slice(0,200));
    const link=p.locator('[data-kaz-module="geral"]').first();
    if(await link.count()) await link.click();
    const s2=await state(p);
    ok('ANTES clique nao abre painel', s2.panels===0, 'panels='+s2.panels);
    await p.close();
  }

  /* --- DEPOIS: JS AD no mesmo <head> sem defer --- */
  for(const [label,url] of [['DEPOIS',D],['DEFER',F]]){
    const {p,errs}=await open(url);
    const s=await state(p);
    ok(label+' boot ocorreu', s.ready==='1', 'ready='+s.ready);
    ok(label+' __kaz exposto', s.api==='object', 'api='+s.api);
    ok(label+' console limpo', errs.length===0, errs.join(' | ').slice(0,200));
    ok(label+' 14 paineis presentes', s.modules>=30, 'modules='+s.modules);
    ok(label+' paineis recolhidos no boot', s.panels===0, 'panels='+s.panels);
    const link=p.locator('[data-kaz-module="geral"]').first();
    if(await link.count()) await link.click();
    const s2=await state(p);
    ok(label+' aba abre 1 painel', s2.panels===1, 'panels='+s2.panels);
    const sc=await p.evaluate(()=>{
      const r=document.getElementById('kaz-theme-editor');
      r.__kaz.setScreen('mobile');
      return {w:r.getAttribute('data-kaz-screen-w'),h:r.getAttribute('data-kaz-screen-h'),
              zoom:r.__kaz.zoom(),layers:r.__kaz.state().layers.length};
    });
    ok(label+' preset de tela responde', sc.w==='390'&&sc.h==='844', JSON.stringify(sc));
    ok(label+' cena com camadas', sc.layers>=6, 'layers='+sc.layers);
    const pub=await p.locator('.kaz-publish summary').count();
    ok(label+' Publicar presente', pub>=1, 'pub='+pub);
    await p.close();
  }
  await b.close();
  console.log('\nverify-kaz-boot: '+pass+' OK / '+fail+' FALHAS');
  process.exit(fail?1:0);
})().catch(e=>{console.error(e);process.exit(1);});
