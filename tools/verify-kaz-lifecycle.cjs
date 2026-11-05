/* ReamurCMS — Frente AI · Chromium real: a escolha do formulario aparece
 * na home publicada. Requer Playwright e os HTMLs gerados pelo PROBE PHP.
 * Uso: node tools/verify-kaz-lifecycle.cjs [<url-antes> <url-depois>]
 * Por padrao abre file:///tmp/ai_site/{antes,depois}.html (AI_OUT).
 * Gera PNGs em /tmp/ai_shots (ou AI_SHOTS). */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const SITE = process.env.AI_OUT || '/tmp/ai_site';
const U = [process.argv[2] || 'file://' + path.join(SITE, 'antes.html'),
           process.argv[3] || 'file://' + path.join(SITE, 'depois.html')];
const OUT = process.env.AI_SHOTS || '/tmp/ai_shots';
let pass = 0, fail = 0;
const ok = (n, c, x) => {
  if (c) { pass++; console.log('OK   ' + n + (x ? ' · ' + x : '')); }
  else { fail++; console.log('FAIL ' + n + (x ? ' · ' + x : '')); }
};
(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  async function medir(u, viewport = { width: 1280, height: 800 }) {
    const ctx = await b.newContext({ viewport });
    const p = await ctx.newPage();
    const errs = []; p.on('pageerror', e => errs.push(e.message));
    await p.goto(u, { waitUntil: 'networkidle' });
    const r = await p.evaluate(() => {
      const el = document.querySelector('#kaz-probe');
      const cs = el ? getComputedStyle(el) : null;
      const bd = getComputedStyle(document.body);
      return {
        state: document.body.getAttribute('data-kaz-ai'),
        bg: cs ? cs.backgroundColor : null,
        radius: cs ? cs.borderRadius : null,
        fontSize: cs ? cs.fontSize : null,
        legacy: getComputedStyle(document.querySelector('#kaz-legacy')).backgroundColor,
        varPrimary: bd.getPropertyValue('--rms-color-primary').trim(),
        varRadius: bd.getPropertyValue('--rms-radius').trim(),
        varFont: bd.getPropertyValue('--rms-font-size').trim(),
        themePrimary: bd.getPropertyValue('--theme-primary').trim(),
        bodyClass: document.body.className,
        probeVisible: el ? el.getBoundingClientRect().height > 0 : false
      };
    });
    return { ctx, p, r, errs };
  }
  const A = await medir(U[0]), D = await medir(U[1]);
  ok('antes: estado', A.r.state === 'antes', String(A.r.state));
  ok('depois: estado', D.r.state === 'depois', String(D.r.state));
  ok('antes: elemento visivel', A.r.probeVisible === true);
  ok('depois: elemento visivel', D.r.probeVisible === true);
  ok('antes: azul publicado anterior', A.r.bg === 'rgb(37, 99, 235)', String(A.r.bg));
  ok('depois: vermelho escolhido no formulario', D.r.bg === 'rgb(220, 38, 38)', String(D.r.bg));
  ok('cor MUDOU apos Publicar', A.r.bg !== D.r.bg, A.r.bg + ' -> ' + D.r.bg);
  ok('antes: radius 12px', A.r.radius === '12px', String(A.r.radius));
  ok('depois: radius 32px do formulario', D.r.radius === '32px', String(D.r.radius));
  ok('antes: fonte 16px', A.r.fontSize === '16px', String(A.r.fontSize));
  ok('depois: fonte 20px do formulario', D.r.fontSize === '20px', String(D.r.fontSize));
  ok('legado --theme-primary acompanha', A.r.legacy === A.r.bg && D.r.legacy === D.r.bg,
     A.r.legacy + ' -> ' + D.r.legacy);
  ok('variavel --rms-color-primary', D.r.varPrimary === '#dc2626', String(D.r.varPrimary));
  ok('variavel --rms-radius 32px', D.r.varRadius === '32px', String(D.r.varRadius));
  ok('variavel --rms-font-size 20px', D.r.varFont === '20px', String(D.r.varFont));
  ok('--theme-primary no body', D.r.themePrimary === '#dc2626', String(D.r.themePrimary));
  ok('antes: --theme-primary azul', A.r.themePrimary === '#2563eb', String(A.r.themePrimary));
  ok('body class home', A.r.bodyClass.includes('home') && D.r.bodyClass.includes('home'));
  ok('sem excecoes JS', A.errs.length === 0 && D.errs.length === 0,
     JSON.stringify(A.errs.concat(D.errs)));
  await A.p.screenshot({ path: OUT + '/antes.png', fullPage: true });
  await D.p.screenshot({ path: OUT + '/depois.png', fullPage: true });
  await A.ctx.close(); await D.ctx.close();

  const M = await medir(U[1], { width: 390, height: 844 });
  ok('mobile 390: cor publicada', M.r.bg === 'rgb(220, 38, 38)', String(M.r.bg));
  ok('mobile 390: radius 32px', M.r.radius === '32px', String(M.r.radius));
  ok('mobile 390: elemento visivel', M.r.probeVisible === true);
  await M.p.screenshot({ path: OUT + '/depois_mobile.png', fullPage: true });
  await M.ctx.close(); await b.close();
  console.log('\nverify-kaz-lifecycle: ' + pass + ' OK / ' + fail + ' FALHAS');
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
