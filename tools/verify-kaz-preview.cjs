/* Frente AF — paridade VISUAL previa x publicado em Chromium real.
 * Uso: node tools/verify-kaz-preview.cjs <url-preview> <url-publicado>
 * Gera screenshots em /tmp/af_shots (ou em AF_SHOTS).
 */
const { chromium } = require('playwright');
const fs = require('fs');
const B = 'http://127.0.0.1:8123';
const U = [process.argv[2] || B + '/af_preview.html', process.argv[3] || B + '/af_published.html'];
const OUT = process.env.AF_SHOTS || '/tmp/af_shots';
let pass = 0, fail = 0;
const ok = (n, c, x) => {
  if (c) { pass++; console.log('OK   ' + n + (x ? ' · ' + x : '')); }
  else { fail++; console.log('FAIL ' + n + (x ? ' · ' + x : '')); }
};
(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  async function medir(u, w, h) {
    const p = await b.newPage({ viewport: { width: w, height: h } });
    const errs = []; p.on('pageerror', e => errs.push(e.message));
    await p.goto(u, { waitUntil: 'networkidle' });
    const r = await p.evaluate(() => {
      const stages = [...document.querySelectorAll('.rms-scene__stage')];
      const layers = [...document.querySelectorAll('.rms-scene__layer')];
      const cs = e => { const c = getComputedStyle(e);
        return { position: c.position, overflow: c.overflow, ratio: c.aspectRatio,
                 containerType: c.containerType, maxWidth: c.maxWidth }; };
      const prim = document.querySelector('.rms-scene__role--primary');
      return { stages: stages.length, layers: layers.length,
        stage: stages[0] ? cs(stages[0]) : null,
        primaria: prim ? getComputedStyle(prim).backgroundColor : null,
        textos: [...document.querySelectorAll('.rms-scene__layer--text')].map(e => ({
          cortado: e.scrollHeight > e.clientHeight + 2,
          sh: e.scrollHeight, ch: e.clientHeight, fs: getComputedStyle(e).fontSize })),
        detalhes: layers.map(e => ({ cls: e.className, style: e.getAttribute('style'), text: e.textContent.trim() })),
        body: document.body.textContent.slice(0, 4000) };
    });
    return { p, r, errs };
  }
  const A = await medir(U[0], 1440, 900), P = await medir(U[1], 1440, 900);
  ok('publicado: 1 stage', P.r.stages === 1, 'stages=' + P.r.stages);
  ok('publicado: 6 camadas', P.r.layers === 6, 'layers=' + P.r.layers);
  ok('publicado: stage posicionado', P.r.stage && P.r.stage.position === 'relative', JSON.stringify(P.r.stage));
  ok('publicado: aspect-ratio 960/540', P.r.stage && P.r.stage.ratio.replace(/\s/g, '') === '960/540', P.r.stage && P.r.stage.ratio);
  ok('publicado: texto da cena', P.r.body.includes('Tema publicado de verdade'));
  ok('publicado: CTA', P.r.body.includes('Começar'));
  ok('previa: 2 stages (publicado + rascunho)', A.r.stages === 2, 'stages=' + A.r.stages);
  ok('previa: 12 camadas', A.r.layers === 12, 'layers=' + A.r.layers);
  ok('previa: CSS estatico aplicado', A.r.stage && A.r.stage.position === 'relative', JSON.stringify(A.r.stage));
  ok('previa: overflow hidden no stage', A.r.stage && A.r.stage.overflow === 'hidden');
  ok('previa: texto publicado', A.r.body.includes('Tema publicado de verdade'));
  ok('previa: texto do rascunho', A.r.body.includes('CENA DO RASCUNHO'));
  ok('previa: sem excecoes', A.errs.length === 0, A.errs.join('|').slice(0, 160));
  ok('publicado: sem excecoes', P.errs.length === 0, P.errs.join('|').slice(0, 160));
  const pegar = r => r.detalhes.map(d => d.style);
  const pub = pegar(P.r), prev = pegar(A.r).slice(0, 6);
  ok('PARIDADE: 6 estilos identicos previa x publicado', JSON.stringify(pub) === JSON.stringify(prev),
     pub.length + ' vs ' + prev.length);
  ok('PARIDADE: textos identicos',
     JSON.stringify(P.r.detalhes.map(d => d.text)) === JSON.stringify(A.r.detalhes.slice(0, 6).map(d => d.text)));
  ok('PARIDADE: cor da camada primary',
     P.r.primaria && P.r.primaria === A.r.primaria, (P.r.primaria || '-') + ' vs ' + (A.r.primaria || '-'));
  ok('previa: container-type inline-size', A.r.stage && A.r.stage.containerType === 'inline-size',
     A.r.stage && A.r.stage.containerType);
  ok('previa: NENHUM texto cortado', A.r.textos.length > 0 && A.r.textos.every(t => !t.cortado),
     JSON.stringify(A.r.textos));
  ok('publicado: NENHUM texto cortado', P.r.textos.length > 0 && P.r.textos.every(t => !t.cortado),
     JSON.stringify(P.r.textos));
  await A.p.screenshot({ path: OUT + '/preview.png', fullPage: true });
  await P.p.screenshot({ path: OUT + '/published.png', fullPage: true });
  await A.p.close(); await P.p.close();
  const M = await medir(U[0], 390, 844);
  ok('previa mobile: 12 camadas', M.r.layers === 12, 'layers=' + M.r.layers);
  ok('previa mobile: stage posicionado', M.r.stage && M.r.stage.position === 'relative');
  await M.p.screenshot({ path: OUT + '/preview_mobile.png', fullPage: true });
  await M.p.close(); await b.close();
  console.log('\nverify-kaz-preview: ' + pass + ' OK / ' + fail + ' FALHAS');
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
