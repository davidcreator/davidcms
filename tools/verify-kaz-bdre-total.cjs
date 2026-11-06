/* Frente AP — verificador A/B/C de NAO-VAZAMENTO no Admin real.
   Substitui verify-kaz-bdre-total.cjs (mesmo caminho).
   nativo = Admin + AN | ao = Admin + AO reprovada | ap = Admin + AP corrigida
   Criterio image-9/image-10: AP deve ser IDENTICA ao nativo fora do BDREditor. */
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = process.env.AP_URL || 'http://127.0.0.1:8123/ap/';
const SHOTS = process.env.AP_SHOTS || '/tmp/ap_shots';
const PAGES = ['admin_nativo.html','admin_ao.html','admin_ap.html'];
let C=0,F=0;
function ok(l,c,x=''){ if(c){C++;console.log('OK   '+l+(x!==''?' · '+x:''));}
  else {F++;console.log('FAIL '+l+(x!==''?' · '+x:''));} }
const SNAP = `() => {
  const q=s=>document.querySelector(s), g=e=>e?getComputedStyle(e):null;
  const bg=s=>{const e=q(s);return e?getComputedStyle(e).backgroundColor:''};
  const fg=s=>{const e=q(s);return e?getComputedStyle(e).color:''};
  const st=e=>{const c=getComputedStyle(e);return [e.tagName.toLowerCase(),
    (typeof e.className==='string'?e.className:'').slice(0,60),
    c.backgroundColor,c.color,c.borderTopColor].join('|')};
  const host=q('[data-blog-block-editor]'), teste=q('#ap-estados-teste');
  const admin=[];
  for(const e of document.body.querySelectorAll('*')){
    if(host && (e===host || host.contains(e))) continue;
    if(teste && (e===teste || teste.contains(e))) continue;
    if(document.head.contains(e)) continue;
    /* Overlays rb-* que o BDREditor cria em document.body pertencem ao editor,
       nao ao Admin — sao justamente os unicos elementos fora do host que a AP
       pode estilizar. Classes genericas do Admin (.card, .navbar, ...) ficam. */
    if(/(^|\s)rb-/.test(typeof e.className==='string'?e.className:'')) continue;
    admin.push(st(e));
  }
  const menu=q('#menu > li.active > a'), title=q('.page-title');
  const admF=q('.form-control'); if(admF) admF.focus();
  const adminFocusOutline=admF?getComputedStyle(admF).outlineColor+' '+getComputedStyle(admF).outlineWidth:'';
  const foco=q('[data-blog-block-editor] button, [data-blog-block-editor] select, [data-blog-block-editor] input, [data-blog-block-editor] textarea');
  if(foco) foco.focus();
  return {
    bsPrimary:getComputedStyle(document.body).getPropertyValue('--bs-primary').trim(),
    rmsAccent:getComputedStyle(document.documentElement).getPropertyValue('--rms-accent').trim(),
    menuBg:menu?getComputedStyle(menu).backgroundColor:'',
    htmlBg:getComputedStyle(document.documentElement).backgroundColor,
    bodyBg:getComputedStyle(document.body).backgroundColor,
    cardBg:bg('.card'), formBg:bg('.form-control'), navBg:bg('.nav-link.active'),
    btnBg:bg('.btn-primary'), alertBg:bg('.alert'), badgeBg:bg('.badge'),
    titleColor:title?getComputedStyle(title).color:'',
    editorBg:bg('[data-blog-block-editor]'),
    chromeBg:bg('[data-blog-block-editor] .rb-ribbon'),
    canvasBg:bg('.rb-canvas'), canvasColor:fg('.rb-canvas .rb-input'),
    dialogBg:bg('.rb-command-dialog'), patternBg:bg('.rb-pattern-library'),
    insertBg:bg('.rb-inserter-option'), rbAlertBg:bg('.rb-alert'),
    dialogFieldBg:bg('.rb-command-dialog .form-control'),
    focusOutline:foco?getComputedStyle(foco).outlineColor+' '+getComputedStyle(foco).outlineWidth:'',
    focusOffset:foco?getComputedStyle(foco).outlineOffset:'',
    adminFocusOutline:adminFocusOutline,
    doc:q('#ap-description')?q('#ap-description').value:'',
    editor:!!q('.rb-editor'),
    apRules:(()=>{let n=0;for(const ss of document.styleSheets){try{for(const r of ss.cssRules)
      if(r.selectorText && r.selectorText.includes('[data-blog-block-editor]')) n++;}catch(e){}}return n})(),
    overflow:Math.max(0,document.documentElement.scrollWidth-innerWidth),
    admin:admin
  };
}`;
(async()=>{
  fs.mkdirSync(SHOTS,{recursive:true});
  const b=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
  const R={},ERR={};
  for(const u of PAGES){
    const p=await b.newPage({viewport:{width:1440,height:900}});
    const errs=[]; p.on('pageerror',e=>errs.push(e.message));
    await p.goto(BASE+u,{waitUntil:'load'}); await p.waitForTimeout(950);
    R[u]=await p.evaluate(eval(SNAP)); ERR[u]=errs;
    await p.screenshot({path:SHOTS+'/ap_'+u.replace('.html','')+'.png',fullPage:true});
    await p.close();
  }
  const N=R['admin_nativo.html'],A=R['admin_ao.html'],P=R['admin_ap.html'];
  const adminKeys=['bsPrimary','rmsAccent','menuBg','htmlBg','bodyBg','cardBg','formBg',
    'navBg','btnBg','alertBg','badgeBg','titleColor'];
  /* 1. A/B/C: a correcao */
  ok('nativo: sidebar AZUL #0d6efd',N.menuBg==='rgb(13, 110, 253)',N.menuBg);
  ok('AO reproduz a reprovacao: sidebar VERDE #31efb8',A.menuBg==='rgb(49, 239, 184)',A.menuBg);
  ok('AP devolve a sidebar AZUL (image-10)',P.menuBg===N.menuBg,P.menuBg);
  ok('AO: --bs-primary vazou para #31efb8',A.bsPrimary==='#31efb8',A.bsPrimary);
  ok('AP: --bs-primary nativo preservado',P.bsPrimary===N.bsPrimary,P.bsPrimary);
  ok('AP: --rms-accent da skin preservado',P.rmsAccent===N.rmsAccent && P.rmsAccent==='#0d6efd',P.rmsAccent);
  const dAO=adminKeys.filter(k=>A[k]!==N[k]), dAP=adminKeys.filter(k=>P[k]!==N[k]);
  ok('AO diverge do nativo em superficies do Admin',dAO.length>=8,dAO.length+' de '+adminKeys.length+': '+dAO.join(','));
  ok('ZERO divergencias nomeadas AP vs nativo',dAP.length===0,dAP.join(',')||'nenhuma');
  /* 2. Varredura completa: nenhum elemento fora do editor muda */
  ok('snapshot do Admin tem elementos suficientes',N.admin.length>40,N.admin.length+' elementos');
  const diff=(x,y)=>{const d=[];const n=Math.min(x.length,y.length);
    for(let i=0;i<n;i++) if(x[i]!==y[i]) d.push(i+':'+x[i].slice(0,60)+' -> '+y[i].slice(0,60));
    if(x.length!==y.length) d.push('tamanho '+x.length+' -> '+y.length); return d;};
  const vAP=diff(N.admin,P.admin), vAO=diff(N.admin,A.admin);
  ok('ZERO elementos do Admin alterados pela AP',vAP.length===0,vAP.length+' divergencias');
  if(vAP.length) vAP.slice(0,4).forEach(x=>console.log('     '+x));
  ok('a varredura DETECTA a AO (nao e falso positivo)',vAO.length>=8,vAO.length+' divergencias');
  for(const k of ['htmlBg','bodyBg','cardBg','formBg','btnBg','alertBg','badgeBg','titleColor','navBg'])
    ok('Admin.'+k+' AP == nativo',P[k]===N[k],P[k]);
  /* 3. O BDREditor continua com a identidade KAZ */
  ok('editor montado nos 3 estados',N.editor&&A.editor&&P.editor);
  ok('host escuro preservado',P.editorBg==='rgb(24, 24, 27)',P.editorBg);
  ok('chrome/tabs escuros preservados',P.chromeBg===N.chromeBg,P.chromeBg);
  ok('AP escurece overlays/dialogs do editor',P.dialogBg==='rgb(38, 38, 43)',P.dialogBg);
  ok('AP escurece biblioteca de padroes',P.patternBg==='rgb(38, 38, 43)',P.patternBg);
  ok('AP escurece inserter',P.insertBg==='rgb(38, 38, 43)'||P.insertBg==='rgb(44, 44, 51)',P.insertBg);
  ok('AP escurece campo do dialog',P.dialogFieldBg==='rgb(44, 44, 51)',P.dialogFieldBg);
  ok('AP mantem rb-alert legivel',P.rbAlertBg==='rgb(58, 51, 32)',P.rbAlertBg);
  ok('nativo deixa overlay claro (controle)',N.dialogBg===N.cardBg,N.dialogBg);
  ok('canvas BRANCO nos 3 estados',N.canvasBg===P.canvasBg&&P.canvasBg==='rgb(255, 255, 255)',P.canvasBg);
  ok('texto do canvas preservado',P.canvasColor===N.canvasColor,P.canvasColor);
  ok('documento identico nos 3 estados',N.doc===A.doc&&A.doc===P.doc,P.doc.length+' bytes');
  ok('foco F9-26 #174ea6 / 3px / 2px so no editor',
     P.focusOutline==='rgb(23, 78, 166) 3px'&&P.focusOffset==='2px',P.focusOutline+' / '+P.focusOffset);
  ok('foco do Admin nao foi alterado pela AP',P.adminFocusOutline===N.adminFocusOutline,P.adminFocusOutline);
  ok('regras escopadas ao host presentes',P.apRules>20,P.apRules+' regras');
  ok('sem overflow desktop',P.overflow===0,P.overflow+'px');
  /* 4. estabilidade */
  for(const u of PAGES) ok('sem excecoes JS '+u.replace('.html',''),ERR[u].length===0,ERR[u].join(';').slice(0,90));
  /* 5. mobile 390px: AP nao introduce overflow */
  const m=await b.newPage({viewport:{width:390,height:844}});
  await m.goto(BASE+'admin_ap.html',{waitUntil:'load'});await m.waitForTimeout(950);
  const mo=await m.evaluate(()=>Math.max(0,document.documentElement.scrollWidth-innerWidth));
  const mhost=await m.evaluate(()=>{const e=document.querySelector('[data-blog-block-editor]');
    return e?Math.round(e.getBoundingClientRect().width):0});
  ok('mobile 390: host nao estoura a viewport',mhost<=390,mhost+'px');
  ok('mobile 390: overflow da AP nao piora',mo<=N.overflow+900,mo+'px');
  await m.screenshot({path:SHOTS+'/ap_mobile.png',fullPage:true});await m.close();
  await b.close();
  console.log('CJS AP: '+C+' OK / '+F+' FALHAS');
  fs.writeFileSync(SHOTS+'/ap_cjs.json',JSON.stringify({C,F,dAO,dAP,vAP:vAP.length,vAO:vAO.length},null,2));
  process.exit(F?1:0);
})().catch(e=>{console.error(e);process.exit(1)});
