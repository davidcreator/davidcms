/* Frente AE — prova que o health check distingue os estados e que o
 * fail-loud aparece SOMENTE quando o boot falha. Exige Playwright.
 * Uso: node tools/verify-kaz-health.cjs <quebrado> <correto> <sem-root> [sem-defer-ae]
 */
const { chromium } = require('playwright');
const fs = require('fs');
const B='http://127.0.0.1:8123';
const U=[process.argv[2]||B+'/admin_head_ac.html',
         process.argv[3]||B+'/admin_deferred_ae.html',
         process.argv[4]||B+'/admin_fail.html',
         process.argv[5]||B+'/admin_head_ae.html'];
const HEALTH=fs.readFileSync(__dirname+'/kaz-theme-editor-health.js','utf8');
let pass=0,fail=0;
const ok=(n,c,x)=>{if(c){pass++;console.log('OK   '+n);}else{fail++;console.log('FAIL '+n+(x?' · '+x:''));}};
(async()=>{
  const b=await chromium.launch({headless:true,args:['--no-sandbox']});
  async function medir(url){
    const p=await b.newPage({viewport:{width:1440,height:900}});
    const errs=[]; p.on('pageerror',e=>errs.push(e.message));
    await p.goto(url,{waitUntil:'networkidle'}); await p.waitForTimeout(700);
    await p.addScriptTag({content:HEALTH});
    const r=await p.evaluate(()=>window.__kazHealth());
    const warn=await p.locator('[data-kaz-boot-warning]').count();
    await p.close(); return {r,warn,errs};
  }
  const A=await medir(U[0]), D=await medir(U[1]), F=await medir(U[2]), E=await medir(U[3]);
  ok('health existe nos 4 documentos',[A,D,F,E].every(x=>x.r&&x.r.ferramenta==='kaz-theme-editor-health/ae'));
  ok('QUEBRADO veredito FALHA_BOOT',A.r.veredito==='FALHA_BOOT',A.r.veredito);
  ok('QUEBRADO __kaz ausente',A.r.boot.api===null,JSON.stringify(A.r.boot.api));
  ok('QUEBRADO ready != 1',A.r.boot.ready!=='1',String(A.r.boot.ready));
  ok('QUEBRADO aponta SEM_DEFER',A.r.problemas.some(x=>x.indexOf('SEM_DEFER')===0),A.r.problemas.join('|'));
  ok('QUEBRADO sem excecoes',A.errs.length===0,A.errs.join('|').slice(0,160));
  ok('CORRETO veredito OK',D.r.veredito==='OK',D.r.veredito+' / '+D.r.problemas.join('|'));
  ok('CORRETO __kaz exposto',D.r.boot.api==='object',String(D.r.boot.api));
  ok('CORRETO ready = 1',D.r.boot.ready==='1',String(D.r.boot.ready));
  ok('CORRETO marcador ad+ae',D.r.boot.health==='ad+ae',String(D.r.boot.health));
  ok('CORRETO camadas da cena',D.r.boot.layers>=6,'layers='+D.r.boot.layers);
  ok('CORRETO 9 modulos listados',Object.keys(D.r.modulos).length===9);
  ok('CORRETO asset JS com defer',D.r.assets.js.some(a=>a.defer),JSON.stringify(D.r.assets.js));
  ok('CORRETO asset CSS presente',D.r.assets.css.length>=1);
  ok('CORRETO centro nao colapsou',D.r.layout.centro&&D.r.layout.centro.w>200,JSON.stringify(D.r.layout.centro));
  ok('CORRETO sem overflow horizontal',D.r.layout.overflowHorizontal<=2,String(D.r.layout.overflowHorizontal));
  ok('CORRETO sem aviso fail-loud',D.warn===0,'warn='+D.warn);
  ok('CORRETO CSRF e form presentes',D.r.publicacao.csrf&&D.r.publicacao.form);
  ok('CORRETO sem excecoes',D.errs.length===0,D.errs.join('|').slice(0,160));
  ok('SEM-ROOT aviso fail-loud VISIVEL',F.warn===1,'warn='+F.warn);
  ok('SEM-ROOT health aponta AVISO_VISIVEL',F.r.problemas.some(x=>x.indexOf('AVISO_VISIVEL')===0),F.r.problemas.join('|'));
  ok('SEM-ROOT sem excecoes',F.errs.length===0,F.errs.join('|').slice(0,160));
  ok('AE SEM DEFER ainda inicializa',E.r.boot.ready==='1'&&E.r.boot.api==='object',
     E.r.veredito+' / '+JSON.stringify(E.r.boot));
  ok('AE SEM DEFER vira ressalva, nao falha',E.r.veredito==='OK_COM_RESSALVAS',E.r.veredito);
  ok('AE SEM DEFER aponta SEM_DEFER',E.r.problemas.some(x=>x.indexOf('SEM_DEFER')===0),E.r.problemas.join('|'));
  ok('AE SEM DEFER sem aviso fail-loud',E.warn===0,'warn='+E.warn);
  await b.close();
  console.log('\nverify-kaz-health: '+pass+' OK / '+fail+' FALHAS');
  process.exit(fail?1:0);
})().catch(e=>{console.error(e);process.exit(1);});
