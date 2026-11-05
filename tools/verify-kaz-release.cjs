/* Frente AH — Chromium real: o painel de liberação aparece SEM JavaScript.
 * Uso: node tools/verify-kaz-release.cjs <url-pendente> <url-publicado>
 * Gera screenshots em /tmp/ah_shots (ou AH_SHOTS).
 */
const { chromium } = require('playwright');
const fs = require('fs');
const B = 'http://127.0.0.1:8123';
const U = [process.argv[2] || B + '/ah_pending.html', process.argv[3] || B + '/ah_published.html'];
const OUT = process.env.AH_SHOTS || '/tmp/ah_shots';
let pass = 0, fail = 0;
const ok = (n, c, x) => {
  if (c) { pass++; console.log('OK   ' + n + (x ? ' · ' + x : '')); }
  else { fail++; console.log('FAIL ' + n + (x ? ' · ' + x : '')); }
};
(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  async function medir(u, jsEnabled = true) {
    const ctx = await b.newContext({ viewport: { width: 1280, height: 800 }, javaScriptEnabled: jsEnabled });
    const p = await ctx.newPage();
    const errs = []; p.on('pageerror', e => errs.push(e.message));
    await p.goto(u, { waitUntil: 'networkidle' });
    const r = await p.evaluate(() => {
      const s = document.querySelector('#kaz-release-status');
      if (!s) return { existe: false };
      const alert = s.querySelector('.alert');
      return {
        existe: true,
        verdict: s.getAttribute('data-kaz-release-verdict'),
        pending: s.getAttribute('data-kaz-release-pending'),
        parts: s.getAttribute('data-kaz-release-parts'),
        asset: s.getAttribute('data-kaz-release-asset'),
        role: s.getAttribute('role'),
        alertClass: alert ? alert.className : null,
        bg: alert ? getComputedStyle(alert).backgroundColor : null,
        text: s.textContent.replace(/\s+/g, ' ').trim(),
        inputs: s.querySelectorAll('input,select,textarea,button,[name]').length,
        visible: s.getBoundingClientRect().width > 0 && s.getBoundingClientRect().height > 0
      };
    });
    return { ctx, p, r, errs };
  }
  const A = await medir(U[0]), D = await medir(U[1]);
  ok('pendente: painel existe', A.r.existe === true);
  ok('pendente: visivel', A.r.visible === true);
  ok('pendente: veredito', A.r.verdict === 'RASCUNHO_PENDENTE', String(A.r.verdict));
  ok('pendente: pending=1', A.r.pending === '1', String(A.r.pending));
  ok('pendente: alerta amarelo', String(A.r.alertClass).includes('alert-warning'), String(A.r.alertClass));
  ok('pendente: texto orienta', A.r.text.includes('Rascunho pendente') && A.r.text.includes('Publicar'), A.r.text.slice(0, 140));
  ok('pendente: role=status', A.r.role === 'status', String(A.r.role));
  ok('pendente: sem campos de formulario', A.r.inputs === 0, String(A.r.inputs));
  ok('publicado: veredito', D.r.verdict === 'PUBLICADO', String(D.r.verdict));
  ok('publicado: pending=0', D.r.pending === '0', String(D.r.pending));
  ok('publicado: alerta verde', String(D.r.alertClass).includes('alert-success'), String(D.r.alertClass));
  ok('publicado: cor diferente do pendente', A.r.bg !== D.r.bg, A.r.bg + ' vs ' + D.r.bg);
  ok('publicado: asset -ah', D.r.asset === '1', String(D.r.asset));
  ok('publicado: texto de paridade', D.r.text.includes('mesmo tema'), D.r.text.slice(0, 140));
  ok('ambos sem excecoes', A.errs.length === 0 && D.errs.length === 0);
  await A.p.screenshot({ path: OUT + '/pendente.png', fullPage: true });
  await D.p.screenshot({ path: OUT + '/publicado.png', fullPage: true });
  await A.ctx.close(); await D.ctx.close();

  // PROVA CENTRAL DA AH: o diagnostico sobrevive ao boot quebrado.
  const N = await medir(U[0], false);
  ok('SEM JAVASCRIPT: painel existe', N.r.existe === true);
  ok('SEM JAVASCRIPT: visivel', N.r.visible === true);
  ok('SEM JAVASCRIPT: veredito correto', N.r.verdict === 'RASCUNHO_PENDENTE', String(N.r.verdict));
  ok('SEM JAVASCRIPT: texto completo', N.r.text.includes('Rascunho pendente'), N.r.text.slice(0, 120));
  await N.p.screenshot({ path: OUT + '/sem_javascript.png', fullPage: true });
  await N.ctx.close();

  const M = await medir(U[1], false);
  ok('SEM JAVASCRIPT: publicado correto', M.r.verdict === 'PUBLICADO', String(M.r.verdict));
  await M.p.setViewportSize({ width: 390, height: 844 });
  ok('mobile 390: painel visivel', M.r.visible === true);
  await M.p.screenshot({ path: OUT + '/publicado_mobile.png', fullPage: true });
  await M.ctx.close(); await b.close();
  console.log('\nverify-kaz-release: ' + pass + ' OK / ' + fail + ' FALHAS');
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
