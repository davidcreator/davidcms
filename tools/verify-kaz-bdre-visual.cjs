/* Frente AN — verificador Chromium headless. A/B: controle sem AN x com AN. */
const { chromium } = require('playwright');
const fs = require('fs');
const BASE = process.env.AN_BASE || 'http://127.0.0.1:8123/bdre/preview';
const OUT = process.env.AN_SHOTS || '/tmp/an_shots';
const U = process.argv.slice(2);
const URLS = U.length >= 2 ? U.slice(0, 2) : [BASE + '/bdre_controle.html', BASE + '/bdre_visual.html'];
const FLAGS = ['--no-sandbox', '--disable-dev-shm-usage'];
const DESK = { width: 1440, height: 900 };
const MOB = { width: 390, height: 844 };
fs.mkdirSync(OUT, { recursive: true });
let P = 0, F = 0; const ERRS = [];
const ok = (n, c, x) => { if (c) { P++; console.log('OK   ' + n + (x ? ' · ' + x : '')); }
                          else { F++; console.log('FAIL ' + n + (x ? ' · ' + x : '')); } };
const probe = () => {
  const h = document.querySelector('[data-blog-block-editor]');
  const cs = h ? getComputedStyle(h) : null;
  const q = s => h ? h.querySelector(s) : null;
  const bg = s => { const e = q(s); return e ? getComputedStyle(e).backgroundColor : ''; };
  const tb = q('.rb-toolbar') || q('.rb-ribbon');
  const btn = q('.rb-button');
  return {
    hostFound: !!h,
    ready: h ? h.getAttribute('data-kaz-bdre-ready') : null,
    editorMounted: !!(h && (h.matches('.rb-editor') || h.querySelector('.rb-editor'))),
    toolbarBg: bg('.rb-toolbar') || bg('.rb-ribbon'),
    editorBg: h && h.matches('.rb-editor') ? getComputedStyle(h).backgroundColor : bg('.rb-editor'),
    canvasBg: bg('.rb-canvas'),
    inspectorBg: bg('.rb-inspector') || bg('.rb-builder-panel') || bg('.rb-layout'),
    buttonBg: btn ? getComputedStyle(btn).backgroundColor : '',
    buttonFont: btn ? getComputedStyle(btn).fontSize : '',
    toolbarDisplay: tb ? getComputedStyle(tb).display : '',
    primary: cs ? cs.getPropertyValue('--kaz-bdre-primary').trim() : '',
    ui: cs ? cs.getPropertyValue('--kaz-ui').trim() : '',
    scrollbarColor: cs ? cs.scrollbarColor : '',
    thumbBg: cs ? getComputedStyle(h, '::-webkit-scrollbar-thumb').backgroundColor : '',
    bar: !!document.querySelector('.kaz-bdre-bar'),
    barVisible: (() => { const b = document.querySelector('.kaz-bdre-bar');
      return b ? getComputedStyle(b).display !== 'none' && b.getBoundingClientRect().height > 0 : false; })(),
    scale: h ? h.getAttribute('data-kaz-ui-scale') || 'normal' : null,
    fold: h ? h.getAttribute('data-kaz-fold') || '' : null,
    diag: typeof window.__kazBdre === 'function' ? window.__kazBdre() : null,
    canvasText: (() => { const c = q('.rb-canvas'); return c ? getComputedStyle(c).color : ''; })(),
    brandColor: (() => { const e = q('.rb-editor-brand'); return e ? getComputedStyle(e).color : ''; })(),
    subtitleColor: (() => { const e = q('.rb-editor-subtitle'); return e ? getComputedStyle(e).color : ''; })(),
    mastheadBg: (() => { const e = q('.rb-editor-masthead'); return e ? getComputedStyle(e).backgroundColor : ''; })(),
    overflow: Math.max(0, document.documentElement.scrollWidth - window.innerWidth),
    hostRight: (() => { return h ? Math.round(h.getBoundingClientRect().right) : 0; })(),
    viewport: window.innerWidth,
    docValue: (() => { const i = document.getElementById('demo-description'); return i ? i.value : ''; })(),
    documentUnchanged: (() => { const i = document.getElementById('demo-description');
      return i ? i.value.indexOf('REAMURCMS_BDRE_JSON_V2:') === 0 : false; })(),
    allPressed: (() => { const b = document.querySelector('[data-kaz-bdre-all]');
      return b ? b.getAttribute('aria-pressed') : null; })(),
    /* Cobertura objetiva: elementos do chrome que ainda exibem fundo claro. */
    clarosForaCanvas: (() => { if (!h) return -1; let n = 0;
      h.querySelectorAll('*').forEach(e => {
        const c = getComputedStyle(e);
        const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(c.backgroundColor);
        if (!m || (+m[1] + +m[2] + +m[3]) <= 690) return;
        if (c.display === 'none' || e.getBoundingClientRect().height <= 2) return;
        if (e.closest('.rb-canvas')) return;
        n++;
      });
      return n; })(),
    /* Prova no motor: a folha AN esta carregada e contem as regras de
       scrollbar e de foco. Nao dependemos da cor do pseudo-elemento, que o
       Chromium headless reporta como transparente mesmo com a regra ativa. */
    cssRules: (() => { let thumb = 0, focus = '', focusOffset = '';
      for (const s of document.styleSheets) { let rs; try { rs = s.cssRules; } catch (e) { continue; }
        for (const r of rs) {
          if (!r.selectorText) continue;
          if (r.selectorText.indexOf('::-webkit-scrollbar-thumb') >= 0) thumb++;
          if (r.selectorText.indexOf(':focus-visible') >= 0) {
            if (r.style && r.style.outline) focus = r.style.outline;
            if (r.style && r.style.outlineOffset) focusOffset = r.style.outlineOffset;
          }
        }
      }
      return { thumb: thumb, focus: focus, focusOffset: focusOffset }; })()
  };
};
async function medir(u, vp, shot, acao, tag) {
  const b = await chromium.launch({ headless: true, args: FLAGS });
  let ctx = null; const before = ERRS.length;
  try {
    ctx = await b.newContext({ viewport: vp });
    const p = await ctx.newPage(); p.setDefaultTimeout(15000);
    p.on('pageerror', e => ERRS.push(tag + ': ' + e.message));
    p.on('console', m => { if (m.type() === 'error') ERRS.push(tag + ': ' + m.text()); });
    await p.goto(u, { waitUntil: 'load' });
    await p.waitForTimeout(1100);
    if (acao) await acao(p);
    await p.waitForTimeout(250);
    const r = await p.evaluate(probe);
    if (shot) await p.screenshot({ path: OUT + '/' + shot, fullPage: true });
    r._novosErros = ERRS.length - before;
    return r;
  } finally { if (ctx) await ctx.close().catch(() => {}); await b.close().catch(() => {}); }
}
const escuro = s => { const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(s || '');
  return m ? (+m[1] + +m[2] + +m[3]) < 260 : false; };
const claro = s => { const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(s || '');
  return m ? (+m[1] + +m[2] + +m[3]) > 690 : false; };
const textoClaro = s => { const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(s || '');
  return m ? (+m[1] + +m[2] + +m[3]) > 600 : false; };
/* Razão de contraste WCAG 2.1, calculada sobre as cores computadas. */
function lum(s) { const m = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(s || ''); if (!m) return 0;
  const c = [+m[1], +m[2], +m[3]].map(v => { v /= 255; return v <= .03928 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4); });
  return .2126 * c[0] + .7152 * c[1] + .0722 * c[2]; }
function contraste(a, b) { const x = lum(a), y = lum(b);
  return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); }
(async () => {
  const K = await medir(URLS[0], DESK, 'controle_sem_an.png', null, 'controle');
  ok('controle: host encontrado', K.hostFound);
  ok('controle: editor montado', K.editorMounted);
  ok('controle: toolbar CLARA', claro(K.toolbarBg), K.toolbarBg);
  ok('controle: sem barra KAZ', !K.bar);
  ok('controle: sem token verde', !K.primary, String(K.primary));
  ok('controle: documento preservado', K.documentUnchanged);

  const N = await medir(URLS[1], DESK, 'an_normal.png', null, 'visual');
  ok('AN: editor continua montado', N.editorMounted);
  ok('AN: host marcado pronto', N.ready === '1', String(N.ready));
  ok('AN: toolbar ESCURA', escuro(N.toolbarBg), K.toolbarBg + ' -> ' + N.toolbarBg);
  ok('AN: editor ESCURO', escuro(N.editorBg), N.editorBg);
  ok('AN: inspector ESCURO', escuro(N.inspectorBg), N.inspectorBg);
  ok('AN: botao ESCURO', escuro(N.buttonBg), K.buttonBg + ' -> ' + N.buttonBg);
  ok('AN: titulo BDREditor legivel no escuro', textoClaro(N.brandColor) && !textoClaro(K.brandColor),
     K.brandColor + ' -> ' + N.brandColor);
  ok('AN: subtitulo legivel no escuro (WCAG >= 4.5)',
     contraste(N.subtitleColor, N.mastheadBg) >= 4.5,
     contraste(N.subtitleColor, N.mastheadBg).toFixed(2) + ':1 · ' + N.subtitleColor);
  ok('AN: titulo legivel no escuro (WCAG >= 4.5)',
     contraste(N.brandColor, N.mastheadBg) >= 4.5,
     contraste(N.brandColor, N.mastheadBg).toFixed(2) + ':1');
  ok('controle: titulo NAO tinha contraste no escuro',
     contraste(K.brandColor, N.mastheadBg) < 4.5,
     contraste(K.brandColor, N.mastheadBg).toFixed(2) + ':1');
  ok('controle: chrome com fundos claros', K.clarosForaCanvas >= 20, String(K.clarosForaCanvas));
  ok('AN: ZERO fundos claros no chrome', N.clarosForaCanvas === 0,
     K.clarosForaCanvas + ' -> ' + N.clarosForaCanvas);
  ok('AN: CANVAS continua CLARO', claro(N.canvasBg), N.canvasBg);
  ok('AN: texto do canvas escuro/legivel', /rgb\(23, 32, 51\)|#172033/.test(N.canvasText), N.canvasText);
  ok('AN: accent verde #31efb8', N.primary === '#31efb8', N.primary);
  ok('AN: barra de controles visivel', N.bar && N.barVisible);
  ok('AN: escala normal', N.scale === 'normal' && N.ui === '1', N.scale + '/' + N.ui);
  ok('AN: scrollbar-color tematica', /srgb|rgb/.test(N.scrollbarColor) && !/auto/.test(N.scrollbarColor),
     N.scrollbarColor);
  /* Como na frente AL: o Chromium headless nao desenha o ::-webkit-scrollbar-thumb
     em capturas; a prova do motor e getComputedStyle(scrollbarColor), nao a imagem. */
  ok('AN: regra ::-webkit-scrollbar-thumb carregada', N.cssRules.thumb > 0 && K.cssRules.thumb === 0,
     K.cssRules.thumb + ' -> ' + N.cssRules.thumb);
  ok('AN: foco #174ea6 carregado no motor', /174ea6|23, ?78, ?166/i.test(N.cssRules.focus) && /3px/.test(N.cssRules.focus),
     N.cssRules.focus);
  ok('AN: outline-offset 2px no motor', /2px/.test(N.cssRules.focusOffset), N.cssRules.focusOffset);
  ok('AN: documento identico ao controle', K.docValue === N.docValue && K.docValue.length > 100,
     K.docValue.length + ' bytes');
  ok('AN: diagnostico disponivel', !!N.diag && N.diag.length > 0 && N.diag[0].ready === true);
  ok('AN: documento NAO foi alterado', N.documentUnchanged);
  ok('AN: sem excecoes novas vs controle', N._novosErros <= K._novosErros,
     K._novosErros + ' -> ' + N._novosErros);

  const S = await medir(URLS[1], DESK, 'an_amplo.png', async p => {
    await p.selectOption('select[data-kaz-bdre-scale]', 'large');
  }, 'amplo');
  ok('escala ampla: --kaz-ui 1.30', S.ui === '1.30', S.ui);
  ok('escala ampla: fonte do botao cresceu', parseFloat(S.buttonFont) > parseFloat(N.buttonFont),
     N.buttonFont + ' -> ' + S.buttonFont);
  ok('escala ampla: canvas intacto', claro(S.canvasBg), S.canvasBg);
  ok('escala ampla: seletor reflete estado', S.diag && S.diag[0].scale === 'large');

  const R = await medir(URLS[1], DESK, 'an_recolhido.png', async p => {
    await p.click('[data-kaz-bdre-all]');
  }, 'recolhido');
  ok('recolher tudo: 3 folds ativos', R.fold && R.fold.split(' ').length === 3, String(R.fold));
  ok('recolher tudo: toolbar oculta', R.toolbarDisplay === 'none', R.toolbarDisplay);
  ok('recolher tudo: barra KAZ permanece', R.bar && R.barVisible);
  ok('recolher tudo: aria-pressed=true', R.allPressed === 'true', String(R.allPressed));
  ok('recolher tudo: canvas intacto', claro(R.canvasBg));

  const E = await medir(URLS[1], DESK, 'an_expandido.png', async p => {
    await p.click('[data-kaz-bdre-all]'); await p.click('[data-kaz-bdre-all]');
  }, 'expandido');
  ok('expandir tudo: folds limpos', !E.fold, String(E.fold));
  ok('expandir tudo: toolbar volta', E.toolbarDisplay !== 'none', E.toolbarDisplay);
  ok('expandir tudo: aria-pressed=false', E.allPressed === 'false', String(E.allPressed));
  ok('expandir tudo: documento intacto', E.docValue === K.docValue);

  ok('desktop: zero overflow horizontal', N.overflow === 0 && K.overflow === 0,
     K.overflow + ' / ' + N.overflow);
  const K390 = await medir(URLS[0], MOB, 'controle_mobile.png', null, 'controle390');
  const M = await medir(URLS[1], MOB, 'an_mobile.png', null, 'mobile');
  const excessoK = Math.max(0, K390.hostRight - K390.viewport);
  const excessoM = Math.max(0, M.hostRight - M.viewport);
  ok('390px: controle ja transbordava', excessoK > 300, String(excessoK));
  ok('390px: AN NAO agrava e reduz >= 90% o excesso do host',
     excessoM <= excessoK * .1 && M.overflow <= K390.overflow,
     excessoK + 'px -> ' + excessoM + 'px · pagina ' + K390.overflow + ' -> ' + M.overflow);
  ok('mobile 390: barra visivel', M.bar && M.barVisible);
  ok('mobile 390: toolbar escura', escuro(M.toolbarBg), M.toolbarBg);
  ok('mobile 390: canvas claro', claro(M.canvasBg), M.canvasBg);
  ok('mobile 390: documento preservado', M.documentUnchanged);

  console.log('\nverify-kaz-bdre-visual: ' + P + ' OK / ' + F + ' FALHAS');
  if (F) process.exitCode = 1;
})().catch(e => { console.log('ERRO FATAL ' + e.message); process.exitCode = 1; });
