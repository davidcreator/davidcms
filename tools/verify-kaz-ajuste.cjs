/* Frente AM — verificador Chromium headless. AM_BASE ou 3 URLs:
   [controle sem AM, com AM, com AM para mobile]. Um browser por cenario. */
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = process.env.AM_BASE || 'http://127.0.0.1:8123';
const OUT = process.env.AM_SHOTS || '/tmp/am_shots';
const U = process.argv.slice(2);
const URLS = U.length >= 3 ? U.slice(0, 3) : [
  BASE + '/al_normal.html', BASE + '/am_normal.html', BASE + '/am_mobile.html'];
const FLAGS = ['--no-sandbox', '--disable-dev-shm-usage'];
const DESK = { width: 1490, height: 728 };
const MOB = { width: 390, height: 844 };
fs.mkdirSync(OUT, { recursive: true });
let P = 0, F = 0, ERRS = [];
const ok = (n, c, x) => { if (c) { P++; console.log('OK   ' + n + (x ? ' · ' + x : '')); }
                          else { F++; console.log('FAIL ' + n + (x ? ' · ' + x : '')); } };
const probe = () => {
  const r = document.getElementById('kaz-theme-editor');
  const vp = r ? r.querySelector('.kaz-viewport') : null;
  const sb = r ? r.querySelector('[data-kaz-screenbar]') : null;
  const zv = r ? r.querySelector('[data-kaz-zoom-value]') : null;
  const ws = r ? r.querySelector('.kaz-workspace') : null;
  const left = r ? r.querySelector('.kaz-sidebar--left') : null;
  const g = r ? r.querySelector('.kaz-g--selected') : null;
  const rc = g ? g.querySelector('rect') : null;
  const box = e => { if (!e) return null; const b = e.getBoundingClientRect();
    return { x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height) }; };
  const acc = document.getElementById('kaz-acceptance');
  return {
    zoom: zv ? zv.textContent : '',
    overflow: vp ? Math.max(0, vp.scrollHeight - vp.clientHeight, vp.scrollWidth - vp.clientWidth) : -1,
    vpW: vp ? vp.clientWidth : 0, vpH: vp ? vp.clientHeight : 0,
    sbH: sb ? Math.round(sb.getBoundingClientRect().height) : -1,
    leftW: left ? Math.round(left.getBoundingClientRect().width) : -1,
    cols: r ? r.getAttribute('data-kaz-cols') : null,
    colLeft: r ? r.getAttribute('data-kaz-col-left') : null,
    grips: document.querySelectorAll('[data-kaz-grip]').length,
    gripVisible: (() => { const e = document.querySelector('[data-kaz-grip="left"]');
      return e ? getComputedStyle(e).display !== 'none' : false; })(),
    ajuste: typeof window.__kazAjuste === 'function' ? window.__kazAjuste() : null,
    ergo: typeof window.__kazErgo === 'function' ? window.__kazErgo() : null,
    acc: acc ? acc.getAttribute('data-kaz-acceptance') : null,
    health: r ? r.getAttribute('data-kaz-health') : null,
    asset: (document.querySelector('script[src*="kaz-theme-editor.js"]') || {}).getAttribute
      ? document.querySelector('script[src*="kaz-theme-editor.js"]').getAttribute('src') : '',
    hero: (() => { const h = document.querySelector('[data-kaz-layer="ac-hero"]');
      const q = h ? h.querySelector('rect') : null;
      return q ? { x: q.getAttribute('x'), y: q.getAttribute('y'),
                   w: q.getAttribute('width'), h: q.getAttribute('height') } : null; })(),
    heroBox: box(document.querySelector('[data-kaz-layer="ac-hero"] rect')),
    fields: ['x', 'y', 'w', 'h'].map(k => {
      const e = document.querySelector('[data-kaz-field="' + k + '"]'); return e ? e.value : null; }),
    wsCols: ws ? getComputedStyle(ws).gridTemplateColumns : ''
  };
};
async function medir(u, viewport, shot, acao) {
  const b = await chromium.launch({ headless: true, args: FLAGS });
  let ctx = null;
  try {
    ctx = await b.newContext({ viewport });
    const p = await ctx.newPage(); p.setDefaultTimeout(12000);
    p.on('pageerror', e => ERRS.push(u + ': ' + e.message));
    p.on('console', m => { if (m.type() === 'error') ERRS.push(u + ': ' + m.text()); });
    await p.goto(u, { waitUntil: 'load' });
    await p.waitForTimeout(1000);
    if (acao) await acao(p);
    await p.waitForTimeout(200);
    const r = await p.evaluate(probe);
    if (shot) await p.screenshot({ path: OUT + '/' + shot, fullPage: true });
    return r;
  } finally { if (ctx) await ctx.close().catch(() => {}); await b.close().catch(() => {}); }
}
const dragLayer = async p => {
  const b = await p.evaluate(() => { const q = document.querySelector('[data-kaz-layer="ac-hero"] rect');
    const r = q.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; });
  await p.mouse.move(b.x, b.y); await p.mouse.down();
  await p.mouse.move(b.x + 60, b.y + 30, { steps: 8 }); await p.mouse.up();
};
(async () => {
  /* Controle: reproduz image-8.png (sem a camada AM) */
  const K = await medir(URLS[0], DESK, 'controle_sem_am.png');
  ok('controle: zoom inicial 100% (defeito reproduzido)', K.zoom === '100%', K.zoom);
  ok('controle: board cortado', K.overflow > 100, String(K.overflow));
  ok('controle: screenbar em 2 linhas', K.sbH > 55, String(K.sbH) + 'px');
  ok('controle: camada fora da area visivel',
     !!K.heroBox && (K.heroBox.y + K.heroBox.h > DESK.height || K.heroBox.x + K.heroBox.w > DESK.width),
     JSON.stringify(K.heroBox));
  ok('controle: sem alcas', K.grips === 0, String(K.grips));
  ok('controle: painel AJ pronto', K.acc === 'pronto', String(K.acc));

  /* Com AM */
  const N = await medir(URLS[1], DESK, 'am_normal.png');
  ok('AM: ajuste inicializado', !!N.ajuste && N.ajuste.ready === true);
  ok('AM: zoom NAO e mais 100%', N.zoom !== '100%', K.zoom + ' -> ' + N.zoom);
  ok('AM: board CABE sem rolagem', N.overflow <= 4, String(K.overflow) + ' -> ' + String(N.overflow));
  ok('AM: screenbar volta a 1 linha', N.sbH <= 45, K.sbH + 'px -> ' + N.sbH + 'px');
  ok('AM: canvas ganhou altura', N.vpH > K.vpH, K.vpH + ' -> ' + N.vpH);
  ok('AM: camada DENTRO da janela',
     !!N.heroBox && N.heroBox.x >= 0 && N.heroBox.y >= 0 &&
     N.heroBox.x + N.heroBox.w <= DESK.width && N.heroBox.y + N.heroBox.h <= DESK.height,
     JSON.stringify(N.heroBox));
  ok('AM: 2 alcas visiveis', N.grips === 2 && N.gripVisible, N.grips + '/' + N.gripVisible);
  ok('AM: densidade intacta (sem arraste)', N.cols === null, String(N.cols));
  ok('AM: colunas originais', N.leftW >= 200 && N.leftW <= 320, String(N.leftW));
  ok('AM: painel AJ pronto', N.acc === 'pronto', String(N.acc));
  ok('AM: health ad+ae', N.health === 'ad+ae', String(N.health));
  ok('AM: asset -aj', N.asset.indexOf('-aj') !== -1, N.asset.slice(-48));
  ok('AM: ergonomia AL inicializada', !!N.ergo && N.ergo.ready === true);
  ok('AM: zero excecoes ate aqui', ERRS.length === 0, ERRS.join(' | ').slice(0, 160));

  const D = await medir(URLS[1], DESK, 'am_arraste.png', dragLayer);
  ok('arraste: X mudou', D.hero && Number(D.hero.x) > Number(N.hero.x),
     N.hero.x + ' -> ' + (D.hero ? D.hero.x : '?'));
  ok('arraste: Y mudou', D.hero && Number(D.hero.y) > Number(N.hero.y),
     N.hero.y + ' -> ' + (D.hero ? D.hero.y : '?'));
  ok('arraste: camada selecionada', D.hero !== null);
  ok('arraste: inspector acompanha', D.fields[0] !== null && D.fields[0] === D.hero.x,
     JSON.stringify(D.fields) + ' vs ' + JSON.stringify(D.hero));

  const R = await medir(URLS[1], DESK, 'am_redimensionar.png', async p => {
    await dragLayer(p);
    const h = await p.evaluate(() => { const e = document.querySelector('[data-kaz-handle]');
      const b = e.getBoundingClientRect(); return { x: b.x + b.width / 2, y: b.y + b.height / 2 }; });
    await p.mouse.move(h.x, h.y); await p.mouse.down();
    await p.mouse.move(h.x + 50, h.y + 25, { steps: 8 }); await p.mouse.up();
  });
  ok('resize: W cresceu', R.hero && Number(R.hero.w) > Number(D.hero.w),
     D.hero.w + ' -> ' + (R.hero ? R.hero.w : '?'));
  ok('resize: H cresceu', R.hero && Number(R.hero.h) > Number(D.hero.h),
     D.hero.h + ' -> ' + (R.hero ? R.hero.h : '?'));

  const G = await medir(URLS[1], DESK, 'am_colunas.png', async p => {
    const b = await p.evaluate(() => { const e = document.querySelector('[data-kaz-grip="left"]');
      const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + 120 }; });
    await p.mouse.move(b.x, b.y); await p.mouse.down();
    await p.mouse.move(b.x + 70, b.y, { steps: 8 }); await p.mouse.up();
  });
  ok('divisoria: estado ativado', G.cols === '1', String(G.cols));
  ok('divisoria: coluna cresceu', G.leftW > N.leftW + 30, N.leftW + ' -> ' + G.leftW);
  ok('divisoria: largura registrada', G.colLeft !== null && Number(G.colLeft) >= 160, String(G.colLeft));
  ok('divisoria: board continua cabendo', G.overflow <= 4, String(G.overflow));
  ok('divisoria: painel AJ pronto', G.acc === 'pronto', String(G.acc));

  const X = await medir(URLS[1], DESK, 'am_restaurar.png', async p => {
    const b = await p.evaluate(() => { const e = document.querySelector('[data-kaz-grip="left"]');
      const r = e.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + 120 }; });
    await p.mouse.move(b.x, b.y); await p.mouse.down();
    await p.mouse.move(b.x + 70, b.y, { steps: 8 }); await p.mouse.up();
    await p.evaluate(() => document.querySelector('[data-kaz-grip="left"]').dispatchEvent(
      new MouseEvent('dblclick', { bubbles: true })));
  });
  ok('duplo clique restaura densidade', X.cols === null && X.colLeft === null,
     String(X.cols) + '/' + String(X.colLeft));

  const Z = await medir(URLS[1], DESK, null, async p => {
    await p.setViewportSize({ width: 1100, height: 620 });
    await p.waitForTimeout(350);
  });
  ok('resize da janela reajusta zoom', Z.overflow <= 6, String(Z.overflow) + ' @1100');
  ok('resize: painel AJ pronto', Z.acc === 'pronto', String(Z.acc));

  const M = await medir(URLS[2], MOB, 'am_mobile.png');
  ok('mobile: ajuste inicializado', !!M.ajuste && M.ajuste.ready === true);
  ok('mobile: alcas escondidas', M.gripVisible === false, String(M.gripVisible));
  ok('mobile: board cabe', M.overflow <= 6, String(M.overflow));
  ok('mobile: painel AJ pronto', M.acc === 'pronto', String(M.acc));
  ok('mobile: sem excecoes', ERRS.length === 0, ERRS.join(' | ').slice(0, 160));

  ok('zero excecoes JS em todos os cenarios', ERRS.length === 0, ERRS.join(' | ').slice(0, 220));
  console.log('\nverify-kaz-ajuste: ' + P + ' OK / ' + F + ' FALHAS');
  if (F) process.exitCode = 1;
})().catch(e => { console.log('ERRO FATAL ' + e.message); process.exitCode = 1; });
