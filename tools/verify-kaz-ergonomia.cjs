/* Frente AL — verificador Chromium headless (somente apresentacao).
   AL_BASE (padrao http://127.0.0.1:8123) ou 3 URLs explicitas.
   Um browser por cenario: o sandbox tem ~2GB e paginas de 230KB esgotam
   o renderer quando varios contextos ficam vivos. Viewport 900x600. */
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = process.env.AL_BASE || 'http://127.0.0.1:8123';
const OUT = process.env.AL_SHOTS || '/tmp/al_shots';
const U = process.argv.slice(2);
const URLS = U.length >= 3 ? U.slice(0, 3) : [
  BASE + '/al_normal.html', BASE + '/al_tema.html', BASE + '/al_mobile.html'];
const FLAGS = ['--no-sandbox', '--disable-dev-shm-usage'];
fs.mkdirSync(OUT, { recursive: true });
let P = 0, F = 0, ERRS = [];
const ok = (n, c, x) => {
  if (c) { P++; console.log('OK   ' + n + (x ? ' · ' + x : '')); }
  else { F++; console.log('FAIL ' + n + (x ? ' · ' + x : '')); }
};
const probe = () => {
  const root = document.getElementById('kaz-theme-editor');
  const side = root ? root.querySelector('.kaz-sidebar') : null;
  const btn = root ? root.querySelector('.kaz-btn') : null;
  const tool = root ? root.querySelector('.kaz-tool') : null;
  const ico = root ? root.querySelector('.kaz-ico') : null;
  const top = root ? root.querySelector('.kaz-topbar') : null;
  const tabs = root ? root.querySelector('.kaz-module-tabs') : null;
  const bar = root ? root.querySelector('.kaz-toolbar') : null;
  const status = root ? root.querySelector('.kaz-statusbar') : null;
  const cs = e => e ? getComputedStyle(e) : null;
  const csThumb = e => e ? getComputedStyle(e, '::-webkit-scrollbar-thumb') : null;
  const acc = document.getElementById('kaz-acceptance');
  return {
    state: acc ? acc.getAttribute('data-kaz-acceptance') : null,
    health: root ? root.getAttribute('data-kaz-health') : null,
    asset: (document.querySelector('script[src*="kaz-theme-editor.js"]') || {}).getAttribute
      ? document.querySelector('script[src*="kaz-theme-editor.js"]').getAttribute('src') : '',
    ergo: typeof window.__kazErgo === 'function' ? window.__kazErgo() : null,
    uiScale: root ? root.getAttribute('data-kaz-ui-scale') : null,
    fold: root ? root.getAttribute('data-kaz-fold') : null,
    scrollColor: cs(side) ? cs(side).scrollbarColor : '',
    thumbColor: csThumb(side) ? csThumb(side).backgroundColor : '',
    btnFont: cs(btn) ? cs(btn).fontSize : '',
    toolW: cs(tool) ? cs(tool).width : '',
    toolH: cs(tool) ? cs(tool).height : '',
    icoW: cs(ico) ? cs(ico).width : '',
    topMin: cs(top) ? cs(top).minHeight : '',
    tabMax: cs(tabs) ? cs(tabs).maxHeight : '',
    toolbarDisplay: cs(bar) ? cs(bar).display : '',
    tabsDisplay: cs(tabs) ? cs(tabs).display : '',
    statusDisplay: cs(status) ? cs(status).display : '',
    pressed: Array.from(document.querySelectorAll('[data-kaz-ergo-fold]'))
      .map(b => b.getAttribute('data-kaz-ergo-fold') + '=' + b.getAttribute('aria-pressed')).join(','),
    allLabel: (document.querySelector('[data-kaz-ergo-fold="all"]') || {}).textContent || '',
    statusText: (document.querySelector('[data-kaz-ergo-status]') || {}).textContent || '',
    controls: document.querySelectorAll('[data-kaz-ergo-fold]').length,
    allBox: (() => { const e = document.querySelector('[data-kaz-ergo-fold="all"]');
      if (!e) return null; const r = e.getBoundingClientRect();
      return { x: Math.round(r.x), w: Math.round(r.width) }; })(),
    ergoBox: (() => { const e = document.querySelector('.kaz-ergo');
      if (!e) return null; const r = e.getBoundingClientRect();
      return { x: Math.round(r.x), w: Math.round(r.width), h: Math.round(r.height) }; })(),
    scaleSelect: !!document.querySelector('select[data-kaz-ui-scale]'),
    canvas: !!root && !!root.querySelector('[data-kaz-canvas]'),
    stage: (() => { const e = root ? root.querySelector('.kaz-stage') : null;
      return e ? { w: e.clientWidth, h: e.clientHeight } : null; })(),
    nestedScrolls: root ? Array.from(root.querySelectorAll('*')).filter(e =>
      e.scrollHeight > e.clientHeight + 2 && getComputedStyle(e).overflowY === 'auto').length : -1
  };
};
async function medir(u, viewport, shot, acao) {
  const b = await chromium.launch({ headless: true, args: FLAGS });
  let ctx = null;
  try {
    ctx = await b.newContext({ viewport });
    const p = await ctx.newPage();
    p.setDefaultTimeout(10000);
    p.on('pageerror', e => ERRS.push(u + ': ' + e.message));
    p.on('console', m => { if (m.type() === 'error') ERRS.push(u + ': ' + m.text()); });
    await p.goto(u, { waitUntil: 'load' });
    await p.waitForTimeout(900);
    if (acao) await acao(p);
    await p.waitForTimeout(200);
    const r = await p.evaluate(probe);
    if (shot) await p.screenshot({ path: OUT + '/' + shot, fullPage: true });
    return r;
  } finally {
    if (ctx) await ctx.close().catch(() => {});
    await b.close().catch(() => {});
  }
}
(async () => {
  const N = await medir(URLS[0], { width: 900, height: 600 }, 'normal.png');
  ok('normal: painel AJ pronto', N.state === 'pronto', String(N.state));
  ok('normal: health ad+ae intacto', N.health === 'ad+ae', String(N.health));
  ok('normal: asset continua -aj', N.asset.indexOf('-aj') !== -1, N.asset.slice(-48));
  ok('normal: ergonomia inicializada', !!N.ergo && N.ergo.ready === true);
  ok('normal: escala normal', N.uiScale === 'normal' && N.ergo.factor === 1, String(N.uiScale));
  ok('normal: nada recolhido', N.fold === null && N.ergo.folded.length === 0);
  ok('normal: 4 botoes de ferramentas', N.controls === 4, String(N.controls));
  ok('normal: controles VISIVEIS sem rolagem horizontal',
     !!N.ergoBox && N.ergoBox.x >= 0 && N.ergoBox.x < 250 &&
     N.ergoBox.w > 100 && N.ergoBox.h > 0, JSON.stringify(N.ergoBox));
  ok('normal: seletor de elementos', N.scaleSelect === true);
  ok('normal: canvas presente', N.canvas === true);
  ok('normal: scrollbar tematica configurada', N.scrollColor !== '' && N.scrollColor !== 'auto auto', N.scrollColor);
  ok('normal: thumb webkit colorido', /^(rgb|color\()/.test(N.thumbColor), N.thumbColor);
  ok('normal: status acessivel', N.statusText.indexOf('Elementos: normal') === 0, N.statusText);
  ok('normal: sem excecoes', ERRS.length === 0, ERRS.join(' | '));
  const base = { btnFont: parseFloat(N.btnFont), toolW: parseFloat(N.toolW),
                 icoW: parseFloat(N.icoW), topMin: parseFloat(N.topMin) };
  ok('normal: valores base medidos', base.btnFont > 0 && base.toolW > 0 && base.icoW > 0,
     JSON.stringify(base));

  const L = await medir(URLS[0], { width: 900, height: 600 }, 'amplo.png', async p => {
    await p.selectOption('select[data-kaz-ui-scale]', 'large');
  });
  ok('amplo: escala aplicada', L.uiScale === 'large' && L.ergo.factor === 1.3, String(L.uiScale));
  ok('amplo: fonte cresce', parseFloat(L.btnFont) > base.btnFont * 1.2,
     base.btnFont + ' -> ' + L.btnFont);
  ok('amplo: botao cresce', parseFloat(L.toolW) > base.toolW * 1.2,
     base.toolW + ' -> ' + L.toolW);
  ok('amplo: icone cresce', parseFloat(L.icoW) > base.icoW * 1.2,
     base.icoW + ' -> ' + L.icoW);
  ok('amplo: topbar cresce', parseFloat(L.topMin) > base.topMin * 1.2,
     base.topMin + ' -> ' + L.topMin);
  ok('amplo: limite das abas escala', parseFloat(L.tabMax) > 44 * 1.2, L.tabMax);
  ok('amplo: canvas NAO escala junto', !!L.stage && !!N.stage && Math.abs(L.stage.w - N.stage.w) < 2,
     JSON.stringify([N.stage, L.stage]));
  ok('amplo: sem rolagens aninhadas novas', L.nestedScrolls <= N.nestedScrolls,
     N.nestedScrolls + ' -> ' + L.nestedScrolls);
  ok('amplo: status atualizado', L.statusText.indexOf('amplo') !== -1, L.statusText);
  ok('amplo: painel AJ segue pronto', L.state === 'pronto', String(L.state));

  const C = await medir(URLS[0], { width: 900, height: 600 }, 'compacto.png', async p => {
    await p.selectOption('select[data-kaz-ui-scale]', 'compact');
  });
  ok('compacto: escala aplicada', C.uiScale === 'compact' && C.ergo.factor === 0.86, String(C.uiScale));
  ok('compacto: fonte reduz', parseFloat(C.btnFont) < base.btnFont * 0.95,
     base.btnFont + ' -> ' + C.btnFont);
  ok('compacto: botao reduz', parseFloat(C.toolW) < base.toolW * 0.95,
     base.toolW + ' -> ' + C.toolW);
  ok('compacto: icone reduz', parseFloat(C.icoW) < base.icoW * 0.95,
     base.icoW + ' -> ' + C.icoW);

  const R = await medir(URLS[0], { width: 900, height: 600 }, 'recolhido.png', async p => {
    await p.click('[data-kaz-ergo-fold="all"]');
  });
  ok('recolhido: 3 grupos no estado', R.fold === 'toolbar tabs status', String(R.fold));
  ok('recolhido: toolbar oculta', R.toolbarDisplay === 'none', R.toolbarDisplay);
  ok('recolhido: abas ocultas', R.tabsDisplay === 'none', R.tabsDisplay);
  ok('recolhido: status oculta', R.statusDisplay === 'none', R.statusDisplay);
  ok('recolhido: aria-pressed true', (R.pressed.match(/=true/g) || []).length === 4, R.pressed);
  ok('recolhido: botao vira Expandir tudo', R.allLabel === 'Expandir tudo', R.allLabel);
  ok('recolhido: reabertura disponivel', R.controls === 4 && R.scaleSelect === true);
  ok('recolhido: canvas ganha area', !!R.stage && !!N.stage && R.stage.h > N.stage.h,
     JSON.stringify([N.stage, R.stage]));
  ok('recolhido: status descreve grupos', R.statusText.indexOf('3 grupo') !== -1, R.statusText);
  ok('recolhido: painel AJ segue pronto', R.state === 'pronto', String(R.state));

  const X = await medir(URLS[0], { width: 900, height: 600 }, 'expandido.png', async p => {
    await p.click('[data-kaz-ergo-fold="toolbar"]');
    await p.click('[data-kaz-ergo-fold="toolbar"]');
  });
  ok('expandir por grupo funciona', X.fold === null && X.toolbarDisplay !== 'none',
     String(X.fold) + '/' + X.toolbarDisplay);
  ok('aria-pressed volta a false', (X.pressed.match(/=false/g) || []).length === 4, X.pressed);

  const T = await medir(URLS[1], { width: 900, height: 600 }, 'tema.png');
  ok('tema: primary do controller', T.ergo.primary.toLowerCase() === '#dc2626', String(T.ergo.primary));
  ok('tema: surface do controller', T.ergo.surface.toLowerCase() === '#fff7ed', String(T.ergo.surface));
  ok('tema: scrollbar MUDA com o tema', T.scrollColor !== N.scrollColor,
     N.scrollColor + ' -> ' + T.scrollColor);
  ok('tema: thumb MUDA com o tema', T.thumbColor !== N.thumbColor,
     N.thumbColor + ' -> ' + T.thumbColor);
  ok('tema: painel AJ segue pronto', T.state === 'pronto', String(T.state));

  const V = await medir(URLS[1], { width: 900, height: 600 }, 'cor_ao_vivo.png', async p => {
    await p.evaluate(() => {
      const i = document.querySelector('input[type="color"][name="primary"]');
      if (i) { i.value = '#00aa66'; i.dispatchEvent(new Event('input', { bubbles: true })); }
    });
  });
  ok('cor ao vivo: variavel atualizada', V.ergo.primary.toLowerCase() === '#00aa66', String(V.ergo.primary));
  ok('cor ao vivo: scrollbar acompanha', V.scrollColor !== T.scrollColor,
     T.scrollColor + ' -> ' + V.scrollColor);

  const M = await medir(URLS[2], { width: 390, height: 844 }, 'mobile.png');
  ok('mobile: ergonomia inicializada', !!M.ergo && M.ergo.ready === true);
  ok('mobile: controles presentes', M.controls === 4 && M.scaleSelect === true);
  ok('mobile: controles visiveis no inicio',
     !!M.ergoBox && M.ergoBox.x >= 0 && M.ergoBox.x < 120 && M.ergoBox.w > 100,
     JSON.stringify(M.ergoBox));
  ok('mobile: Recolher tudo cabe em 390px',
     !!M.allBox && M.allBox.x >= 0 && M.allBox.x + M.allBox.w <= 390,
     JSON.stringify(M.allBox));
  ok('mobile: canvas presente', M.canvas === true);
  ok('mobile: painel AJ pronto', M.state === 'pronto', String(M.state));
  ok('mobile: sem rolagens aninhadas', M.nestedScrolls <= 1, String(M.nestedScrolls));

  ok('zero excecoes JS em todos os cenarios', ERRS.length === 0, ERRS.join(' | ').slice(0, 220));
  console.log('\nverify-kaz-ergonomia: ' + P + ' OK / ' + F + ' FALHAS');
  if (F) process.exitCode = 1;
})().catch(e => { console.log('ERRO FATAL ' + e.message); process.exitCode = 1; });
