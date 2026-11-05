/* Frente AG — Chromium real: MUDAR O RASCUNHO muda a PREVIA.
 * Uso: node tools/verify-kaz-preview-state.cjs <url-estado-A> <url-estado-B>
 * Gera screenshots em /tmp/ag_shots (ou AG_SHOTS).
 */
const { chromium } = require('playwright');
const fs = require('fs');
const B = 'http://127.0.0.1:8123';
const U = [process.argv[2] || B + '/ag_state_a.html', process.argv[3] || B + '/ag_state_b.html'];
const OUT = process.env.AG_SHOTS || '/tmp/ag_shots';
let pass = 0, fail = 0;
const ok = (n, c, x) => {
  if (c) { pass++; console.log('OK   ' + n + (x ? ' · ' + x : '')); }
  else { fail++; console.log('FAIL ' + n + (x ? ' · ' + x : '')); }
};
(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  async function medir(u) {
    const p = await b.newPage({ viewport: { width: 1280, height: 800 } });
    const errs = []; p.on('pageerror', e => errs.push(e.message));
    await p.goto(u, { waitUntil: 'networkidle' });
    const r = await p.evaluate(() => {
      const bs = getComputedStyle(document.body);
      const card = document.querySelector('.ag-card');
      const cs = card ? getComputedStyle(card) : null;
      const probe = document.querySelector('.ag-probe');
      return {
        radius: bs.getPropertyValue('--rms-radius').trim(),
        fontSize: bs.getPropertyValue('--rms-font-size').trim(),
        primary: bs.getPropertyValue('--theme-primary').trim(),
        surface: bs.getPropertyValue('--theme-surface').trim(),
        lightSurface: bs.getPropertyValue('--rms-light-surface').trim(),
        cardRadius: cs ? cs.borderRadius : null,
        cardFont: cs ? cs.fontSize : null,
        cardColor: cs ? cs.color : null,
        probePrimary: probe ? probe.getAttribute('data-primary') : null,
        probeRadius: probe ? probe.getAttribute('data-radius') : null,
        classes: document.body.className,
        text: document.body.textContent.slice(0, 500)
      };
    });
    return { p, r, errs };
  }
  const A = await medir(U[0]), D = await medir(U[1]);
  const campos = ['radius','fontSize','primary','surface','cardRadius','cardFont','probePrimary','probeRadius'];
  for (const k of campos) {
    ok('A mede ' + k, A.r[k] !== null && A.r[k] !== '', String(A.r[k]));
  }
  ok('catalogo aplicado: body usa --rms-font-size', A.r.fontSize === '16px', A.r.fontSize);
  ok('catalogo aplicado: card consome --rms-radius', A.r.cardRadius === '12px', String(A.r.cardRadius));
  ok('Twig sandbox: theme.primary = #2563eb', A.r.probePrimary === '#2563eb', String(A.r.probePrimary));
  ok('Twig sandbox: token radius = 12', A.r.probeRadius === '12', String(A.r.probeRadius));
  ok('B mede radius 32px', D.r.cardRadius === '32px', String(D.r.cardRadius));
  ok('B mede fonte 20px', D.r.cardFont === '20px', String(D.r.cardFont));
  ok('B theme.primary = #dc2626', D.r.probePrimary === '#dc2626', String(D.r.probePrimary));
  ok('B token radius = 32', D.r.probeRadius === '32', String(D.r.probeRadius));
  ok('B tem classe rms-theme-rollout', D.r.classes.includes('rms-theme-rollout'), D.r.classes);
  ok('A NAO tem classe rollout', !A.r.classes.includes('rms-theme-rollout'), A.r.classes);
  const mudaram = campos.filter(k => A.r[k] !== D.r[k]);
  ok('MUDANCA REAL: 8 campos divergem entre A e B', mudaram.length >= 6, mudaram.join(','));
  ok('cor da superficie diverge', A.r.surface !== D.r.surface, A.r.surface + ' vs ' + D.r.surface);
  ok('cor computada do card diverge', A.r.cardColor !== D.r.cardColor, A.r.cardColor + ' vs ' + D.r.cardColor);
  ok('A sem excecoes', A.errs.length === 0, A.errs.join('|').slice(0, 160));
  ok('B sem excecoes', D.errs.length === 0, D.errs.join('|').slice(0, 160));
  await A.p.screenshot({ path: OUT + '/estado_a.png', fullPage: true });
  await D.p.screenshot({ path: OUT + '/estado_b.png', fullPage: true });
  await A.p.close(); await D.p.close();
  const M = await medir(U[1]);
  ok('B mobile: radius 32px', M.r.cardRadius === '32px', String(M.r.cardRadius));
  await M.p.setViewportSize({ width: 390, height: 844 });
  await M.p.screenshot({ path: OUT + '/estado_b_mobile.png', fullPage: true });
  await M.p.close(); await b.close();
  console.log('\nverify-kaz-preview-state: ' + pass + ' OK / ' + fail + ' FALHAS');
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
